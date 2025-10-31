<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Repository;

use CreditBundle\Entity\Account;
use CreditResourceBundle\Entity\ResourceBill;
use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Enum\BillStatus;
use CreditResourceBundle\Enum\FeeCycle;
use CreditResourceBundle\Repository\ResourceBillRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(ResourceBillRepository::class)]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ResourceBillRepositoryTest extends AbstractRepositoryTestCase
{
    private ResourceBillRepository $repository;

    private static ?string $userEntityClass = null;

    private ?string $testCurrency = null;

    private ?ResourcePrice $testResourcePrice = null;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(ResourceBillRepository::class);

        $em = self::getEntityManager();

        // 只在第一次运行时获取用户类
        if (null === self::$userEntityClass) {
            $metadata = $em->getClassMetadata(UserInterface::class);
            self::$userEntityClass = $metadata->getName();
        }

        // Reset caches
        $this->testCurrency = null;
        $this->testResourcePrice = null;
    }

    public function testFindByUser(): void
    {
        $user = $this->createNormalUser('test@example.com');
        $otherUser = $this->createNormalUser('other@example.com');

        $bill1 = $this->createBill($user, BillStatus::PAID);
        $bill2 = $this->createBill($user, BillStatus::PENDING);
        $otherBill = $this->createBill($otherUser, BillStatus::PAID);

        self::getService(EntityManagerInterface::class)->flush();

        $userBills = $this->repository->findByUser($user);

        $this->assertCount(2, $userBills);
        $this->assertContains($bill1, $userBills);
        $this->assertContains($bill2, $userBills);
        $this->assertNotContains($otherBill, $userBills);
    }

    public function testFindByUserWithCriteria(): void
    {
        $user = $this->createNormalUser('test@example.com');

        $paidBill = $this->createBill($user, BillStatus::PAID);
        $pendingBill = $this->createBill($user, BillStatus::PENDING);

        self::getService(EntityManagerInterface::class)->flush();

        $paidBills = $this->repository->findByUser($user, ['status' => BillStatus::PAID]);

        $this->assertCount(1, $paidBills);
        $this->assertContains($paidBill, $paidBills);
        $this->assertNotContains($pendingBill, $paidBills);
    }

    public function testFindPendingBills(): void
    {
        $user = $this->createNormalUser('test@example.com');

        $pendingBill1 = $this->createBill($user, BillStatus::PENDING);
        $pendingBill2 = $this->createBill($user, BillStatus::PENDING);
        $paidBill = $this->createBill($user, BillStatus::PAID);

        self::getService(EntityManagerInterface::class)->flush();

        $pendingBills = $this->repository->findPendingBills();

        $this->assertCount(2, $pendingBills);
        $this->assertContains($pendingBill1, $pendingBills);
        $this->assertContains($pendingBill2, $pendingBills);
        $this->assertNotContains($paidBill, $pendingBills);
    }

    public function testFindRetryableBills(): void
    {
        $user = $this->createNormalUser('test@example.com');

        $oldFailedBill = $this->createBill($user, BillStatus::FAILED);
        $oldFailedBill->setUpdateTime(new \DateTimeImmutable('-2 hours'));

        $recentFailedBill = $this->createBill($user, BillStatus::FAILED);
        $recentFailedBill->setUpdateTime(new \DateTimeImmutable('-30 minutes'));

        $paidBill = $this->createBill($user, BillStatus::PAID);

        self::getService(EntityManagerInterface::class)->flush();

        $retryableBills = $this->repository->findRetryableBills(new \DateTimeImmutable('-1 hour'));

        $this->assertCount(1, $retryableBills);
        $this->assertContains($oldFailedBill, $retryableBills);
        $this->assertNotContains($recentFailedBill, $retryableBills);
        $this->assertNotContains($paidBill, $retryableBills);
    }

    public function testGetUserBillSummary(): void
    {
        $user = $this->createNormalUser('test@example.com');
        $resourcePrice = $this->createResourcePrice();

        // Create bills with different statuses
        $this->createBillWithPrice($user, BillStatus::PAID, $resourcePrice, '100.00');
        $this->createBillWithPrice($user, BillStatus::PAID, $resourcePrice, '150.00');
        $this->createBillWithPrice($user, BillStatus::FAILED, $resourcePrice, '200.00');
        $this->createBillWithPrice($user, BillStatus::PENDING, $resourcePrice, '50.00');

        self::getService(EntityManagerInterface::class)->flush();

        $start = new \DateTimeImmutable('-1 month');
        $end = new \DateTimeImmutable('+1 month');

        $summary = $this->repository->getUserBillSummary($user, $start, $end);

        $this->assertCount(1, $summary);
        $this->assertEquals(4, $summary[0]['totalCount']);
        $this->assertEquals(2, $summary[0]['paidCount']);
        $this->assertEquals(1, $summary[0]['failedCount']);
        $this->assertEquals('250.00', $summary[0]['totalAmount']);
        $this->assertEquals('测试资源', $summary[0]['resourceTitle']);
    }

    public function testExistsBill(): void
    {
        $user = $this->createNormalUser('test@example.com');
        $resourcePrice = $this->createResourcePrice();
        $account = $this->createAccount($user);

        $periodStart = new \DateTimeImmutable('2023-01-01');
        $periodEnd = new \DateTimeImmutable('2023-01-31');

        $bill = new ResourceBill();
        $bill->setUser($user);
        $bill->setResourcePrice($resourcePrice);
        $bill->setAccount($account);
        $bill->setPeriodStart($periodStart);
        $bill->setPeriodEnd($periodEnd);
        $bill->setBillTime(new \DateTimeImmutable());
        $bill->setStatus(BillStatus::PENDING);
        $bill->setUsage(100);
        $bill->setUnitPrice('1.00');
        $bill->setTotalPrice('100.00');
        $bill->setActualPrice('100.00');

        $em = self::getEntityManager();
        $em->persist($bill);
        $em->flush();

        // Test existing bill
        $resourcePriceId = $resourcePrice->getId();
        $this->assertNotNull($resourcePriceId);
        $exists = $this->repository->existsBill(
            $user,
            $resourcePriceId,
            $periodStart,
            $periodEnd
        );
        $this->assertTrue($exists);

        // Test non-existing bill with different period
        $exists = $this->repository->existsBill(
            $user,
            $resourcePriceId,
            new \DateTimeImmutable('2023-02-01'),
            new \DateTimeImmutable('2023-02-28')
        );
        $this->assertFalse($exists);
    }

    private function createBill(UserInterface $user, BillStatus $status): ResourceBill
    {
        $resourcePrice = $this->createResourcePrice();
        $account = $this->createAccount($user);

        $bill = new ResourceBill();
        $bill->setUser($user);
        $bill->setResourcePrice($resourcePrice);
        $bill->setAccount($account);
        $bill->setBillTime(new \DateTimeImmutable());
        $bill->setPeriodStart(new \DateTimeImmutable('-1 month'));
        $bill->setPeriodEnd(new \DateTimeImmutable());
        $bill->setStatus($status);
        $bill->setUsage(100);
        $bill->setUnitPrice('1.00');
        $bill->setTotalPrice('100.00');
        $bill->setActualPrice('100.00');

        $em = self::getEntityManager();
        $em->persist($bill);

        return $bill;
    }

    private function createBillWithPrice(
        UserInterface $user,
        BillStatus $status,
        ResourcePrice $resourcePrice,
        string $actualPrice,
    ): ResourceBill {
        $account = $this->createAccount($user);

        $bill = new ResourceBill();
        $bill->setUser($user);
        $bill->setResourcePrice($resourcePrice);
        $bill->setAccount($account);
        $bill->setBillTime(new \DateTimeImmutable());
        $bill->setPeriodStart(new \DateTimeImmutable('-1 week'));
        $bill->setPeriodEnd(new \DateTimeImmutable());
        $bill->setStatus($status);
        $bill->setUsage(100);
        $bill->setUnitPrice($actualPrice);
        $bill->setTotalPrice($actualPrice);
        $bill->setActualPrice($actualPrice);

        $em = self::getEntityManager();
        $em->persist($bill);

        return $bill;
    }

    private function createResourcePrice(): ResourcePrice
    {
        if (null === $this->testResourcePrice) {
            $currency = $this->createCurrency();

            $resourcePrice = new ResourcePrice();
            $resourcePrice->setTitle('测试资源');
            $resourcePrice->setResource('test_resource');
            $resourcePrice->setPrice('1.00');
            $resourcePrice->setValid(true);
            $resourcePrice->setCycle(FeeCycle::TOTAL_BY_MONTH);
            $resourcePrice->setMinAmount(0);
            $resourcePrice->setCurrency($currency);

            $em = self::getEntityManager();
            $em->persist($resourcePrice);

            $this->testResourcePrice = $resourcePrice;
        }

        return $this->testResourcePrice;
    }

    private function createCurrency(): string
    {
        if (null === $this->testCurrency) {
            $suffix = uniqid();
            $this->testCurrency = 'CNY' . $suffix;
        }

        return $this->testCurrency;
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

        return $account;
    }

    private function createBillWithAccount(UserInterface $user, BillStatus $status, Account $account): ResourceBill
    {
        $resourcePrice = $this->createResourcePrice();

        $bill = new ResourceBill();
        $bill->setUser($user);
        $bill->setResourcePrice($resourcePrice);
        $bill->setAccount($account);
        $bill->setBillTime(new \DateTimeImmutable());
        $bill->setPeriodStart(new \DateTimeImmutable('-1 month'));
        $bill->setPeriodEnd(new \DateTimeImmutable());
        $bill->setStatus($status);
        $bill->setUsage(100);
        $bill->setUnitPrice('1.00');
        $bill->setTotalPrice('100.00');
        $bill->setActualPrice('100.00');

        $em = self::getEntityManager();
        $em->persist($bill);

        return $bill;
    }

    protected function onTearDown(): void
    {
        // 不做任何事情，避免基类的 cleanDatabase 被调用
    }

    protected function createNewEntity(): object
    {
        $entity = new ResourceBill();

        // 创建必需的关联实体
        $user = $this->createNormalUser('test@example.com');
        $resourcePrice = $this->createResourcePrice();
        $account = $this->createAccount($user);

        // 设置基本字段和关联
        $entity->setUser($user);
        $entity->setResourcePrice($resourcePrice);
        $entity->setAccount($account);
        $entity->setBillTime(new \DateTimeImmutable());
        $entity->setPeriodStart(new \DateTimeImmutable('-1 hour'));
        $entity->setPeriodEnd(new \DateTimeImmutable());
        $entity->setUsage(100);
        $entity->setUnitPrice('0.10');
        $entity->setTotalPrice('10.00');
        $entity->setActualPrice('10.00');
        $entity->setStatus(BillStatus::PENDING);

        return $entity;
    }

    /**
     * @return ServiceEntityRepository<ResourceBill>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }

    public function testSave(): void
    {
        $user = $this->createNormalUser('test@example.com');
        $bill = $this->createBill($user, BillStatus::PENDING);

        $this->repository->save($bill, true);

        $this->assertNotNull($bill->getId());
        $foundBill = $this->repository->find($bill->getId());
        $this->assertSame($bill, $foundBill);
    }

    public function testRemove(): void
    {
        $user = $this->createNormalUser('test@example.com');
        $bill = $this->createBill($user, BillStatus::PENDING);

        $em = self::getEntityManager();
        $em->persist($bill);
        $em->flush();

        $id = $bill->getId();
        $this->assertNotNull($id);

        $this->repository->remove($bill, true);

        $foundBill = $this->repository->find($id);
        $this->assertNull($foundBill);
    }

    public function testFindOneByWithOrderByClause(): void
    {
        $user = $this->createNormalUser('test@example.com');
        $resourcePrice = $this->createResourcePrice();

        $billB = $this->createBillWithPrice($user, BillStatus::PENDING, $resourcePrice, '200.00');
        $billA = $this->createBillWithPrice($user, BillStatus::PENDING, $resourcePrice, '100.00');

        self::getService(EntityManagerInterface::class)->flush();

        $result = $this->repository->findOneBy([], ['actualPrice' => 'ASC']);

        // 由于存在fixture数据，我们需要验证返回的是价格最低的记录
        $this->assertNotNull($result);
        $this->assertLessThanOrEqual($billA->getActualPrice(), $result->getActualPrice());
    }

    public function testFindByAssociationQuery(): void
    {
        $user1 = $this->createNormalUser('user1@example.com');
        $user2 = $this->createNormalUser('user2@example.com');

        $bill1 = $this->createBill($user1, BillStatus::PENDING);
        $bill2 = $this->createBill($user2, BillStatus::PENDING);

        self::getService(EntityManagerInterface::class)->flush();

        $result = $this->repository->findBy(['user' => $user1]);

        $this->assertCount(1, $result);
        $this->assertContains($bill1, $result);
        $this->assertNotContains($bill2, $result);
    }

    public function testCountByAssociationQuery(): void
    {
        $user1 = $this->createNormalUser('user1@example.com');
        $user2 = $this->createNormalUser('user2@example.com');

        $this->createBill($user1, BillStatus::PENDING);
        $this->createBill($user1, BillStatus::PAID);
        $this->createBill($user2, BillStatus::PENDING);

        self::getService(EntityManagerInterface::class)->flush();

        $count = $this->repository->count(['user' => $user1]);

        $this->assertEquals(2, $count);
    }

    public function testFindByNullableUsageDetailsQuery(): void
    {
        $user = $this->createNormalUser('test@example.com');

        $billWithDetails = $this->createBill($user, BillStatus::PENDING);
        $billWithDetails->setUsageDetails(['detail1' => 'value1']);

        $billWithoutDetails = $this->createBill($user, BillStatus::PENDING);
        $billWithoutDetails->setUsageDetails(null);

        self::getService(EntityManagerInterface::class)->flush();

        $result = $this->repository->findBy(['usageDetails' => null]);

        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertContains($billWithoutDetails, $result);
        $this->assertNotContains($billWithDetails, $result);
    }

    public function testFindByNullableFailureReasonQuery(): void
    {
        $user = $this->createNormalUser('test@example.com');

        $billWithFailure = $this->createBill($user, BillStatus::FAILED);
        $billWithFailure->setFailureReason('Payment failed');

        $billWithoutFailure = $this->createBill($user, BillStatus::PENDING);
        $billWithoutFailure->setFailureReason(null);

        self::getService(EntityManagerInterface::class)->flush();

        $result = $this->repository->findBy(['failureReason' => null]);

        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertContains($billWithoutFailure, $result);
        $this->assertNotContains($billWithFailure, $result);
    }

    public function testCountByNullableUsageDetailsQuery(): void
    {
        $user = $this->createNormalUser('test@example.com');

        $billWithDetails = $this->createBill($user, BillStatus::PENDING);
        $billWithDetails->setUsageDetails(['detail1' => 'value1']);

        $billWithoutDetails = $this->createBill($user, BillStatus::PENDING);
        $billWithoutDetails->setUsageDetails(null);

        self::getService(EntityManagerInterface::class)->flush();

        $count = $this->repository->count(['usageDetails' => null]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountByNullableFailureReasonQuery(): void
    {
        $user = $this->createNormalUser('test@example.com');

        $billWithFailure = $this->createBill($user, BillStatus::FAILED);
        $billWithFailure->setFailureReason('Payment failed');

        $billWithoutFailure = $this->createBill($user, BillStatus::PENDING);
        $billWithoutFailure->setFailureReason(null);

        self::getService(EntityManagerInterface::class)->flush();

        $count = $this->repository->count(['failureReason' => null]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByTransactionAssociationQuery(): void
    {
        $user = $this->createNormalUser('test@example.com');

        $billWithTransaction = $this->createBill($user, BillStatus::PAID);
        $billWithoutTransaction = $this->createBill($user, BillStatus::PENDING);

        self::getService(EntityManagerInterface::class)->flush();

        $result = $this->repository->findBy(['transaction' => null]);

        $this->assertGreaterThanOrEqual(2, $result);
        $this->assertContains($billWithTransaction, $result);
        $this->assertContains($billWithoutTransaction, $result);
    }

    public function testCountByTransactionAssociationQuery(): void
    {
        $user = $this->createNormalUser('test@example.com');

        $billWithTransaction = $this->createBill($user, BillStatus::PAID);
        $billWithoutTransaction = $this->createBill($user, BillStatus::PENDING);

        self::getService(EntityManagerInterface::class)->flush();

        $count = $this->repository->count(['transaction' => null]);

        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function testFindByResourcePriceAssociationQuery(): void
    {
        $user = $this->createNormalUser('test@example.com');

        $currency = $this->createCurrency();

        $resourcePrice1 = new ResourcePrice();
        $resourcePrice1->setTitle('Resource 1');
        $resourcePrice1->setResource('test_resource_1');
        $resourcePrice1->setPrice('1.00');
        $resourcePrice1->setValid(true);
        $resourcePrice1->setCycle(FeeCycle::TOTAL_BY_MONTH);
        $resourcePrice1->setMinAmount(0);
        $resourcePrice1->setCurrency($currency);

        $resourcePrice2 = new ResourcePrice();
        $resourcePrice2->setTitle('Resource 2');
        $resourcePrice2->setResource('test_resource_2');
        $resourcePrice2->setPrice('2.00');
        $resourcePrice2->setValid(true);
        $resourcePrice2->setCycle(FeeCycle::TOTAL_BY_MONTH);
        $resourcePrice2->setMinAmount(0);
        $resourcePrice2->setCurrency($currency);

        $em = self::getEntityManager();
        $em->persist($resourcePrice1);
        $em->persist($resourcePrice2);

        $bill1 = $this->createBillWithPrice($user, BillStatus::PENDING, $resourcePrice1, '100.00');
        $bill2 = $this->createBillWithPrice($user, BillStatus::PENDING, $resourcePrice2, '200.00');

        self::getService(EntityManagerInterface::class)->flush();

        $result = $this->repository->findBy(['resourcePrice' => $resourcePrice1]);

        $this->assertCount(1, $result);
        $this->assertContains($bill1, $result);
        $this->assertNotContains($bill2, $result);
    }

    public function testCountByResourcePriceAssociationQuery(): void
    {
        $user = $this->createNormalUser('test@example.com');

        $currency = $this->createCurrency();

        $resourcePrice1 = new ResourcePrice();
        $resourcePrice1->setTitle('Resource 1');
        $resourcePrice1->setResource('test_resource_1');
        $resourcePrice1->setPrice('1.00');
        $resourcePrice1->setValid(true);
        $resourcePrice1->setCycle(FeeCycle::TOTAL_BY_MONTH);
        $resourcePrice1->setMinAmount(0);
        $resourcePrice1->setCurrency($currency);

        $resourcePrice2 = new ResourcePrice();
        $resourcePrice2->setTitle('Resource 2');
        $resourcePrice2->setResource('test_resource_2');
        $resourcePrice2->setPrice('2.00');
        $resourcePrice2->setValid(true);
        $resourcePrice2->setCycle(FeeCycle::TOTAL_BY_MONTH);
        $resourcePrice2->setMinAmount(0);
        $resourcePrice2->setCurrency($currency);

        $em = self::getEntityManager();
        $em->persist($resourcePrice1);
        $em->persist($resourcePrice2);

        $this->createBillWithPrice($user, BillStatus::PENDING, $resourcePrice1, '100.00');
        $this->createBillWithPrice($user, BillStatus::PENDING, $resourcePrice1, '150.00');
        $this->createBillWithPrice($user, BillStatus::PENDING, $resourcePrice2, '200.00');

        self::getService(EntityManagerInterface::class)->flush();

        $count = $this->repository->count(['resourcePrice' => $resourcePrice1]);

        $this->assertEquals(2, $count);
    }

    public function testFindByAccountAssociationQuery(): void
    {
        $user1 = $this->createNormalUser('user1@example.com');
        $user2 = $this->createNormalUser('user2@example.com');

        $account1 = $this->createAccount($user1);
        $account2 = $this->createAccount($user2);

        $bill1 = $this->createBillWithAccount($user1, BillStatus::PENDING, $account1);
        $bill2 = $this->createBillWithAccount($user2, BillStatus::PENDING, $account2);

        self::getService(EntityManagerInterface::class)->flush();

        $result = $this->repository->findBy(['account' => $account1]);

        $this->assertCount(1, $result);
        $this->assertContains($bill1, $result);
        $this->assertNotContains($bill2, $result);
    }

    public function testCountByAccountAssociationQuery(): void
    {
        $user1 = $this->createNormalUser('user1@example.com');
        $user2 = $this->createNormalUser('user2@example.com');

        $account1 = $this->createAccount($user1);
        $account2 = $this->createAccount($user2);

        $bill1 = $this->createBillWithAccount($user1, BillStatus::PENDING, $account1);
        $bill2 = $this->createBillWithAccount($user1, BillStatus::PAID, $account1);
        $bill3 = $this->createBillWithAccount($user2, BillStatus::PENDING, $account2);

        self::getService(EntityManagerInterface::class)->flush();

        $count = $this->repository->count(['account' => $account1]);

        $this->assertEquals(2, $count);
    }

    public function testFindByNullablePaidAtQuery(): void
    {
        $user = $this->createNormalUser('test@example.com');

        $paidBill = $this->createBill($user, BillStatus::PAID);
        $paidBill->setPaidAt(new \DateTimeImmutable());

        $unpaidBill = $this->createBill($user, BillStatus::PENDING);
        $unpaidBill->setPaidAt(null);

        self::getService(EntityManagerInterface::class)->flush();

        $result = $this->repository->findBy(['paidAt' => null]);

        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertContains($unpaidBill, $result);
        $this->assertNotContains($paidBill, $result);
    }

    public function testCountByNullablePaidAtQuery(): void
    {
        $user = $this->createNormalUser('test@example.com');

        $paidBill = $this->createBill($user, BillStatus::PAID);
        $paidBill->setPaidAt(new \DateTimeImmutable());

        $unpaidBill = $this->createBill($user, BillStatus::PENDING);
        $unpaidBill->setPaidAt(null);

        self::getService(EntityManagerInterface::class)->flush();

        $count = $this->repository->count(['paidAt' => null]);

        $this->assertGreaterThanOrEqual(1, $count);
    }
}
