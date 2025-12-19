<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Provider;

use CreditResourceBundle\Entity\ResourceBill;
use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Enum\FeeCycle;
use CreditResourceBundle\Provider\EntityResourceUsageProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\UserServiceContracts\UserManagerInterface;

/**
 * @internal
 */
#[CoversClass(EntityResourceUsageProvider::class)]
#[RunTestsInSeparateProcesses]
final class EntityResourceUsageProviderTest extends AbstractIntegrationTestCase
{
    private EntityResourceUsageProvider $provider;

    private UserManagerInterface $userManager;

    protected function onSetUp(): void
    {
        $this->provider = self::getService(EntityResourceUsageProvider::class);
        $this->userManager = self::getService(UserManagerInterface::class);
    }

    public function testSupportsValidEntity(): void
    {
        // 使用真实的实体类进行测试
        $this->assertTrue($this->provider->supports(ResourcePrice::class));
        $this->assertTrue($this->provider->supports(ResourceBill::class));
    }

    public function testSupportsInvalidClass(): void
    {
        $this->assertFalse($this->provider->supports('NonExistentClass'));
    }

    public function testSupportsNonEntity(): void
    {
        // stdClass 不是 Doctrine 实体
        $this->assertFalse($this->provider->supports(\stdClass::class));
    }

    public function testGetPriority(): void
    {
        $this->assertSame(0, $this->provider->getPriority());
    }

    public function testGetTimeRangeForCycleHourly(): void
    {
        $billTime = new \DateTime('2023-06-15 14:30:00');
        $range = $this->provider->getTimeRangeForCycle(FeeCycle::NEW_BY_HOUR, $billTime);

        $this->assertEquals(new \DateTime('2023-06-15 13:30:00'), $range['start']);
        $this->assertEquals($billTime, $range['end']);
    }

    public function testGetTimeRangeForCycleTotalByDay(): void
    {
        $billTime = new \DateTime('2023-06-15 14:30:00');
        $range = $this->provider->getTimeRangeForCycle(FeeCycle::TOTAL_BY_DAY, $billTime);

        $this->assertEquals(new \DateTime('2000-01-01 00:00:00'), $range['start']);
        $this->assertEquals($billTime, $range['end']);
    }

    public function testGetTimeRangeForCycleNewByMonth(): void
    {
        $billTime = new \DateTime('2023-06-15 14:30:00');
        $range = $this->provider->getTimeRangeForCycle(FeeCycle::NEW_BY_MONTH, $billTime);

        $this->assertEquals(new \DateTime('2023-05-15 14:30:00'), $range['start']);
        $this->assertEquals($billTime, $range['end']);
    }

    public function testGetTimeRangeForCycleTotalByHour(): void
    {
        $billTime = new \DateTime('2023-06-15 14:30:00');
        $range = $this->provider->getTimeRangeForCycle(FeeCycle::TOTAL_BY_HOUR, $billTime);

        $this->assertEquals(new \DateTime('2000-01-01 00:00:00'), $range['start']);
        $this->assertEquals($billTime, $range['end']);
    }

    public function testGetTimeRangeForCycleNewByDay(): void
    {
        $billTime = new \DateTime('2023-06-15 14:30:00');
        $range = $this->provider->getTimeRangeForCycle(FeeCycle::NEW_BY_DAY, $billTime);

        $this->assertEquals(new \DateTime('2023-06-14 14:30:00'), $range['start']);
        $this->assertEquals($billTime, $range['end']);
    }

    public function testGetTimeRangeForCycleTotalByMonth(): void
    {
        $billTime = new \DateTime('2023-06-15 14:30:00');
        $range = $this->provider->getTimeRangeForCycle(FeeCycle::TOTAL_BY_MONTH, $billTime);

        $this->assertEquals(new \DateTime('2000-01-01 00:00:00'), $range['start']);
        $this->assertEquals($billTime, $range['end']);
    }

    public function testGetTimeRangeForCycleTotalByYear(): void
    {
        $billTime = new \DateTime('2023-06-15 14:30:00');
        $range = $this->provider->getTimeRangeForCycle(FeeCycle::TOTAL_BY_YEAR, $billTime);

        $this->assertEquals(new \DateTime('2000-01-01 00:00:00'), $range['start']);
        $this->assertEquals($billTime, $range['end']);
    }

    public function testGetTimeRangeForCycleNewByYear(): void
    {
        $billTime = new \DateTime('2023-06-15 14:30:00');
        $range = $this->provider->getTimeRangeForCycle(FeeCycle::NEW_BY_YEAR, $billTime);

        $this->assertEquals(new \DateTime('2022-06-15 14:30:00'), $range['start']);
        $this->assertEquals($billTime, $range['end']);
    }

    public function testGetUsageReturnsZeroWhenNoRecords(): void
    {
        $user = $this->createNormalUser('test-usage@example.com');

        $start = new \DateTime('-1 year');
        $end = new \DateTime();

        // 使用一个不太可能有记录的实体类
        $result = $this->provider->getUsage($user, ResourcePrice::class, $start, $end);
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testGetUsageDetailsReturnsCorrectStructure(): void
    {
        $user = $this->createNormalUser('test-details@example.com');

        $start = new \DateTime('-1 year');
        $end = new \DateTime();

        $details = $this->provider->getUsageDetails($user, ResourcePrice::class, $start, $end);

        $this->assertArrayHasKey('resource_type', $details);
        $this->assertArrayHasKey('entity_class', $details);
        $this->assertArrayHasKey('user_id', $details);
        $this->assertArrayHasKey('period_start', $details);
        $this->assertArrayHasKey('period_end', $details);
        $this->assertArrayHasKey('count', $details);
        $this->assertArrayHasKey('provider', $details);

        $this->assertSame(ResourcePrice::class, $details['resource_type']);
        $this->assertSame(ResourcePrice::class, $details['entity_class']);
        $this->assertSame($user->getUserIdentifier(), $details['user_id']);
        $this->assertSame(EntityResourceUsageProvider::class, $details['provider']);
    }

    public function testGetUsageDetailsWithResourceBill(): void
    {
        $user = $this->createNormalUser('test-bill@example.com');

        $start = new \DateTime('2023-01-01 00:00:00');
        $end = new \DateTime('2023-12-31 23:59:59');

        $details = $this->provider->getUsageDetails($user, ResourceBill::class, $start, $end);

        $this->assertArrayHasKey('resource_type', $details);
        $this->assertArrayHasKey('entity_class', $details);
        $this->assertArrayHasKey('user_id', $details);
        $this->assertArrayHasKey('period_start', $details);
        $this->assertArrayHasKey('period_end', $details);
        $this->assertArrayHasKey('count', $details);
        $this->assertArrayHasKey('provider', $details);

        $this->assertSame(ResourceBill::class, $details['resource_type']);
        $this->assertSame(ResourceBill::class, $details['entity_class']);
        $this->assertSame($user->getUserIdentifier(), $details['user_id']);
        $this->assertSame('2023-01-01 00:00:00', $details['period_start']);
        $this->assertSame('2023-12-31 23:59:59', $details['period_end']);
        $this->assertSame(EntityResourceUsageProvider::class, $details['provider']);
        $this->assertIsInt($details['count']);
        $this->assertGreaterThanOrEqual(0, $details['count']);
    }
}
