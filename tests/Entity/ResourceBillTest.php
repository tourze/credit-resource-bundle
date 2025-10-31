<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Entity;

use Carbon\CarbonImmutable;
use CreditResourceBundle\Entity\ResourceBill;
use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Enum\BillStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(ResourceBill::class)]
final class ResourceBillTest extends AbstractEntityTestCase
{
    private ResourceBill $bill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bill = new ResourceBill();
    }

    /**
     * 创建UserInterface的简单stub实现
     *
     * @param non-empty-string $userIdentifier 用户标识符，默认为'test-user'
     * @param array<string> $roles 用户角色数组，默认为空数组
     */
    private function createUserStub(string $userIdentifier = 'test-user', array $roles = []): UserInterface
    {
        return new class($userIdentifier, $roles) implements UserInterface {
            /**
             * @param non-empty-string $userIdentifier
             * @param array<string> $roles
             */
            public function __construct(
                private readonly string $userIdentifier,
                private readonly array $roles = [],
            ) {
            }

            public function getRoles(): array
            {
                return $this->roles;
            }

            public function eraseCredentials(): void
            {
                // nothing to do here
            }

            public function getUserIdentifier(): string
            {
                return $this->userIdentifier;
            }
        };
    }

    protected function createEntity(): ResourceBill
    {
        return new ResourceBill();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        $billTime = CarbonImmutable::parse('2023-04-01 00:00:00');
        $periodStart = CarbonImmutable::parse('2023-04-01 00:00:00');
        $periodEnd = CarbonImmutable::parse('2023-04-30 23:59:59');
        yield 'billTime' => ['billTime', $billTime];
        yield 'periodStart' => ['periodStart', $periodStart];
        yield 'periodEnd' => ['periodEnd', $periodEnd];
        yield 'unitPrice' => ['unitPrice', '2.00000'];
        yield 'totalPrice' => ['totalPrice', '100.00000'];
        yield 'actualPrice' => ['actualPrice', '100.00000'];
        yield 'usage' => ['usage', 50];
        yield 'usageDetails' => ['usageDetails', ['detail' => 'value']];
        yield 'status' => ['status', BillStatus::PENDING];
        yield 'failureReason' => ['failureReason', 'test failure'];
        yield 'transaction' => ['transaction', null];
        yield 'paidAt' => ['paidAt', null];
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->bill->getId());
        $this->assertNull($this->bill->getUser());
        $this->assertNull($this->bill->getResourcePrice());
        $this->assertNull($this->bill->getStatus());
        $this->assertNull($this->bill->getTotalPrice());
        $this->assertNull($this->bill->getUsage());
        $this->assertNull($this->bill->getTransaction());
        $this->assertNull($this->bill->getPaidAt());
    }

    public function testSettersAndGetters(): void
    {
        $user = $this->createUserStub();
        // 使用具体类ResourcePrice是因为：
        // 1. 这是业务实体类，是领域模型的核心组件
        // 2. 测试需要验证实体间的关联关系
        // 3. 实体类的接口稳定，适合单元测试
        $resourcePrice = $this->createMock(ResourcePrice::class);
        $billTime = CarbonImmutable::parse('2023-04-01 00:00:00');
        $periodStart = CarbonImmutable::parse('2023-04-01 00:00:00');
        $periodEnd = CarbonImmutable::parse('2023-04-30 23:59:59');

        $this->bill->setUser($user);
        $this->bill->setResourcePrice($resourcePrice);
        $this->bill->setBillTime($billTime);
        $this->bill->setPeriodStart($periodStart);
        $this->bill->setPeriodEnd($periodEnd);
        $this->bill->setUnitPrice('2.00000');
        $this->bill->setTotalPrice('100.00000');
        $this->bill->setActualPrice('100.00000');
        $this->bill->setUsage(50);
        $this->bill->setUsageDetails(['detail' => 'value']);
        $this->bill->setStatus(BillStatus::PAID);
        $this->bill->setFailureReason('test failure');

        $this->assertSame($user, $this->bill->getUser());
        $this->assertSame($resourcePrice, $this->bill->getResourcePrice());
        $this->assertSame($billTime, $this->bill->getBillTime());
        $this->assertSame($periodStart, $this->bill->getPeriodStart());
        $this->assertSame($periodEnd, $this->bill->getPeriodEnd());
        $this->assertEquals('2.00000', $this->bill->getUnitPrice());
        $this->assertEquals('100.00000', $this->bill->getTotalPrice());
        $this->assertEquals('100.00000', $this->bill->getActualPrice());
        $this->assertEquals(50, $this->bill->getUsage());
        $this->assertEquals(['detail' => 'value'], $this->bill->getUsageDetails());
        $this->assertEquals(BillStatus::PAID, $this->bill->getStatus());
        $this->assertEquals('test failure', $this->bill->getFailureReason());
    }

    public function testStatusTransitions(): void
    {
        // 测试初始状态可以转换到任何状态
        $this->assertTrue($this->bill->canTransitionTo(BillStatus::PENDING));
        $this->assertTrue($this->bill->canTransitionTo(BillStatus::PROCESSING));

        $this->bill->setStatus(BillStatus::PENDING);
        $this->assertTrue($this->bill->canTransitionTo(BillStatus::PROCESSING));
        $this->assertTrue($this->bill->canTransitionTo(BillStatus::CANCELLED));
        $this->assertFalse($this->bill->canTransitionTo(BillStatus::PAID));

        $this->bill->setStatus(BillStatus::PROCESSING);
        $this->assertTrue($this->bill->canTransitionTo(BillStatus::PAID));
        $this->assertTrue($this->bill->canTransitionTo(BillStatus::FAILED));
        $this->assertFalse($this->bill->canTransitionTo(BillStatus::PENDING));
    }

    public function testPaidAt(): void
    {
        $this->assertNull($this->bill->getPaidAt());

        $paidAt = new \DateTimeImmutable('2023-04-01 12:00:00');
        $this->bill->setPaidAt($paidAt);

        $this->assertSame($paidAt, $this->bill->getPaidAt());
    }

    public function testToString(): void
    {
        // 使用具体类ResourcePrice是因为：
        // 1. 这是业务实体类，是领域模型的核心组件
        // 2. 测试需要验证实体间的关联关系
        // 3. 实体类的接口稳定，适合单元测试
        $resourcePrice = $this->createMock(ResourcePrice::class);
        $resourcePrice->method('getTitle')->willReturn('Test Resource');

        $billTime = CarbonImmutable::parse('2023-04-01 12:00:00');

        $this->bill->setResourcePrice($resourcePrice);
        $this->bill->setBillTime($billTime);
        $this->bill->setStatus(BillStatus::PENDING);

        $expected = 'Test Resource - 2023-04-01 12:00:00 (pending)';
        $this->assertEquals($expected, (string) $this->bill);
    }

    public function testToStringWithNullValues(): void
    {
        $expected = 'N/A - N/A (N/A)';
        $this->assertEquals($expected, (string) $this->bill);
    }
}
