<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Service;

use CreditBundle\Entity\Account;
use CreditBundle\Service\TransactionService;
use CreditResourceBundle\Entity\ResourceBill;
use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Enum\BillStatus;
use CreditResourceBundle\Enum\FeeCycle;
use CreditResourceBundle\Exception\BillAlreadyExistsException;
use CreditResourceBundle\Exception\InvalidBillStateException;
use CreditResourceBundle\Exception\ZeroUsageException;
use CreditResourceBundle\Interface\ResourceUsageProviderInterface;
use CreditResourceBundle\Service\BillService;
use CreditResourceBundle\Service\ResourceUsageService;
use CreditResourceBundle\Strategy\FixedPriceStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(BillService::class)]
#[RunTestsInSeparateProcesses]
final class BillServiceTest extends AbstractIntegrationTestCase
{
    private BillService $billService;

    private ?string $testCurrency = null;

    protected function onSetUp(): void
    {
        $this->billService = self::getService(BillService::class);

        // Clear database
        self::getEntityManager()->createQuery('DELETE FROM CreditResourceBundle\Entity\ResourceBill')->execute();
        self::getEntityManager()->createQuery('DELETE FROM CreditResourceBundle\Entity\ResourcePrice')->execute();
        self::getEntityManager()->createQuery('DELETE FROM CreditBundle\Entity\Account')->execute();

        // Reset currency cache
        $this->testCurrency = null;

        // 注册测试资源使用量提供者
        $this->registerTestUsageProvider();
    }

    private function registerTestUsageProvider(): void
    {
        $testProvider = new class implements ResourceUsageProviderInterface {
            public function supports(string $resourceType): bool
            {
                return 'test_resource' === $resourceType;
            }

            public function getUsage(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): int
            {
                return 100;
            }

            public function getUsageDetails(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): array
            {
                return ['test' => 100];
            }

            public function getPriority(): int
            {
                return 0;
            }
        };

        self::getContainer()->set('test_usage_provider', $testProvider);
        self::getService(ResourceUsageService::class)->addProvider($testProvider);
    }

    public function testGenerateBill(): void
    {
        $user = $this->createPersistableUser('test@example.com');
        $resourcePrice = $this->createResourcePrice();

        $billTime = new \DateTimeImmutable();

        // 创建测试账户 - 现在使用真实集成测试
        $account = $this->createAccount($user);

        $bill = $this->billService->generateBill($user, $resourcePrice, $billTime);

        $this->assertInstanceOf(ResourceBill::class, $bill);
        $this->assertEquals($user, $bill->getUser());
        $this->assertEquals($resourcePrice, $bill->getResourcePrice());
        $this->assertEquals(100, $bill->getUsage());
        $this->assertEquals('100.00000', $bill->getActualPrice());
        $this->assertEquals(BillStatus::PENDING, $bill->getStatus());
    }

    public function testCreateBillWithZeroUsageThrowsException(): void
    {
        // 为此测试创建一个返回0使用量的提供者
        $zeroProvider = new class implements ResourceUsageProviderInterface {
            public function supports(string $resourceType): bool
            {
                return 'test_resource' === $resourceType;
            }

            public function getUsage(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): int
            {
                return 0;
            }

            public function getUsageDetails(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): array
            {
                return [];
            }

            public function getPriority(): int
            {
                return 10; // 更高优先级以覆盖默认提供者
            }
        };

        self::getService(ResourceUsageService::class)->addProvider($zeroProvider);

        $user = $this->createNormalUser('test@example.com');
        $resourcePrice = $this->createResourcePrice();
        $billTime = new \DateTimeImmutable();

        $this->expectException(ZeroUsageException::class);

        $this->billService->generateBill($user, $resourcePrice, $billTime);
    }

    public function testCreateBillAlreadyExistsThrowsException(): void
    {
        $user = $this->createNormalUser('test@example.com');
        $resourcePrice = $this->createResourcePrice();
        $billTime = new \DateTimeImmutable();

        // 先创建一个账单来模拟已存在的情况
        $existingBill = $this->createPendingBill($user);
        $existingBill->setResourcePrice($resourcePrice);
        $existingBill->setBillTime($billTime);
        self::getEntityManager()->persist($existingBill);
        self::getEntityManager()->flush();

        $this->expectException(BillAlreadyExistsException::class);

        $this->billService->generateBill($user, $resourcePrice, $billTime);
    }

    public function testProcessBillSuccess(): void
    {
        $bill = $this->createPendingBill();

        // 为账户添加足够的积分记录
        $account = $bill->getAccount();
        $this->assertNotNull($account, '账单的账户不能为空');
        self::getService(TransactionService::class)->increase(
            'TEST_DEPOSIT',
            $account,
            1000.0,
            '测试充值'
        );

        $this->billService->processBill($bill);

        $this->assertEquals(BillStatus::PAID, $bill->getStatus());
        $this->assertNotNull($bill->getPaidAt());
    }

    public function testProcessBillInsufficientBalance(): void
    {
        $bill = $this->createPendingBill();

        // 不为账户添加任何积分，保持余额为0

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('没有可消费的积分记录');

        $this->billService->processBill($bill);
    }

    public function testProcessBillAlreadyPaidThrowsException(): void
    {
        $bill = $this->createPendingBill();
        $bill->setStatus(BillStatus::PAID);

        $this->expectException(InvalidBillStateException::class);

        $this->billService->processBill($bill);
    }

    public function testCancelBillSuccess(): void
    {
        $bill = $this->createPendingBill();

        $this->billService->cancelBill($bill, '用户取消');

        $this->assertEquals(BillStatus::CANCELLED, $bill->getStatus());
        $this->assertEquals('用户取消', $bill->getFailureReason());
    }

    public function testCancelPaidBillThrowsException(): void
    {
        $bill = $this->createPendingBill();
        $bill->setStatus(BillStatus::PAID);

        $this->expectException(InvalidBillStateException::class);

        $this->billService->cancelBill($bill, '尝试取消');
    }

    public function testGetStrategyReturnsCorrectStrategy(): void
    {
        $resourcePrice = $this->createResourcePrice();

        $strategy = $this->billService->getStrategy($resourcePrice);

        $this->assertInstanceOf(FixedPriceStrategy::class, $strategy);
    }

    private function createResourcePrice(): ResourcePrice
    {
        $resourcePrice = new ResourcePrice();
        $resourcePrice->setTitle('测试资源');
        $resourcePrice->setResource('test_resource');
        $resourcePrice->setPrice('1.00');
        $resourcePrice->setValid(true);
        $resourcePrice->setCycle(FeeCycle::NEW_BY_DAY);
        $resourcePrice->setMinAmount(0);
        $resourcePrice->setFreeQuota(0); // 确保没有免费额度

        // 创建真实的 Currency 实体
        $currency = $this->createCurrency();
        $resourcePrice->setCurrency($currency);

        self::getEntityManager()->persist($resourcePrice);
        self::getEntityManager()->flush();

        return $resourcePrice;
    }

    private function createCurrency(): string
    {
        if (null === $this->testCurrency) {
            $suffix = uniqid();
            $this->testCurrency = 'CNY' . $suffix;
        }

        return $this->testCurrency;
    }

    private function createPendingBill(?UserInterface $providedUser = null): ResourceBill
    {
        $resourcePrice = $this->createResourcePrice();
        $user = $providedUser ?? $this->createPersistableUser('test@example.com');
        $account = $this->createAccount($user);

        $bill = new ResourceBill();
        $bill->setUser($user);
        $bill->setResourcePrice($resourcePrice);
        $bill->setAccount($account);
        $bill->setBillTime(new \DateTimeImmutable());
        $bill->setPeriodStart(new \DateTimeImmutable('-1 day'));
        $bill->setPeriodEnd(new \DateTimeImmutable());
        $bill->setStatus(BillStatus::PENDING);
        $bill->setUsage(100);
        $bill->setUnitPrice('1.00');
        $bill->setTotalPrice('100.00');
        $bill->setActualPrice('100.00');

        self::getEntityManager()->persist($bill);
        self::getEntityManager()->flush();

        return $bill;
    }

    private function createPersistableUser(string $identifier): UserInterface
    {
        return $this->createNormalUser($identifier);
    }

    private function createAccount(UserInterface $user): Account
    {
        $currency = $this->createCurrency();

        $account = new Account();
        $account->setName('测试账户' . uniqid());
        $account->setUser($user);
        $account->setCurrency($currency);
        $account->setEndingBalance('1000.00');

        $em = self::getEntityManager();
        $em->persist($account);
        $em->flush();

        return $account;
    }

    public function testQueryBills(): void
    {
        $criteria = ['status' => BillStatus::PENDING];
        $expectedBill = $this->createPendingBill();

        $result = $this->billService->queryBills($criteria);

        $this->assertCount(1, $result);
        $this->assertEquals($expectedBill->getId(), $result[0]->getId());
        $this->assertEquals(BillStatus::PENDING, $result[0]->getStatus());
    }

    public function testRetryBill(): void
    {
        $bill = $this->createPendingBill();
        $bill->setStatus(BillStatus::FAILED);
        $bill->setFailureReason('支付失败');

        // 为账户添加足够的积分记录
        $account = $bill->getAccount();
        $this->assertNotNull($account, '账单的账户不能为空');
        self::getService(TransactionService::class)->increase(
            'TEST_DEPOSIT_RETRY',
            $account,
            1000.0,
            '测试充值用于重试'
        );

        $this->billService->retryBill($bill);

        $this->assertEquals(BillStatus::PAID, $bill->getStatus());
        $this->assertNull($bill->getFailureReason());
        $this->assertNotNull($bill->getPaidAt());
    }

    public function testRetryBillInvalidState(): void
    {
        $bill = $this->createPendingBill();
        $bill->setStatus(BillStatus::PAID);

        $this->expectException(InvalidBillStateException::class);
        $this->expectExceptionMessage('只能重试失败的账单');

        $this->billService->retryBill($bill);
    }
}
