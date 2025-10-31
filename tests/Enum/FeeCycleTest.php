<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Enum;

use CreditResourceBundle\Enum\FeeCycle;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(FeeCycle::class)]
final class FeeCycleTest extends AbstractEnumTestCase
{
    /**
     * 测试所有枚举用例都能正确实例化.
     */
    public function testEnumInstantiation(): void
    {
        $this->assertInstanceOf(FeeCycle::class, FeeCycle::TOTAL_BY_YEAR);
        $this->assertInstanceOf(FeeCycle::class, FeeCycle::TOTAL_BY_MONTH);
        $this->assertInstanceOf(FeeCycle::class, FeeCycle::TOTAL_BY_DAY);
        $this->assertInstanceOf(FeeCycle::class, FeeCycle::TOTAL_BY_HOUR);
        $this->assertInstanceOf(FeeCycle::class, FeeCycle::NEW_BY_YEAR);
        $this->assertInstanceOf(FeeCycle::class, FeeCycle::NEW_BY_MONTH);
        $this->assertInstanceOf(FeeCycle::class, FeeCycle::NEW_BY_DAY);
        $this->assertInstanceOf(FeeCycle::class, FeeCycle::NEW_BY_HOUR);
    }

    /**
     * 测试所有枚举值正确.
     */
    public function testEnumValues(): void
    {
        $this->assertSame('total-by-year', FeeCycle::TOTAL_BY_YEAR->value);
        $this->assertSame('total-by-month', FeeCycle::TOTAL_BY_MONTH->value);
        $this->assertSame('total-by-day', FeeCycle::TOTAL_BY_DAY->value);
        $this->assertSame('total-by-hour', FeeCycle::TOTAL_BY_HOUR->value);
        $this->assertSame('new-by-year', FeeCycle::NEW_BY_YEAR->value);
        $this->assertSame('new-by-month', FeeCycle::NEW_BY_MONTH->value);
        $this->assertSame('new-by-day', FeeCycle::NEW_BY_DAY->value);
        $this->assertSame('new-by-hour', FeeCycle::NEW_BY_HOUR->value);
    }

    /**
     * 测试 getLabel 方法返回正确的标签.
     */
    public function testGetLabel(): void
    {
        $this->assertSame('按年总计', FeeCycle::TOTAL_BY_YEAR->getLabel());
        $this->assertSame('按月总计', FeeCycle::TOTAL_BY_MONTH->getLabel());
        $this->assertSame('按日总计', FeeCycle::TOTAL_BY_DAY->getLabel());
        $this->assertSame('按小时总计', FeeCycle::TOTAL_BY_HOUR->getLabel());
        $this->assertSame('按年新增', FeeCycle::NEW_BY_YEAR->getLabel());
        $this->assertSame('按月新增', FeeCycle::NEW_BY_MONTH->getLabel());
        $this->assertSame('按日新增', FeeCycle::NEW_BY_DAY->getLabel());
        $this->assertSame('按小时新增', FeeCycle::NEW_BY_HOUR->getLabel());
    }

    public function testToArray(): void
    {
        $totalByYearArray = FeeCycle::TOTAL_BY_YEAR->toArray();
        $this->assertArrayHasKey('value', $totalByYearArray);
        $this->assertArrayHasKey('label', $totalByYearArray);
        $this->assertEquals('total-by-year', $totalByYearArray['value']);
        $this->assertEquals('按年总计', $totalByYearArray['label']);

        $newByDayArray = FeeCycle::NEW_BY_DAY->toArray();
        $this->assertEquals('new-by-day', $newByDayArray['value']);
        $this->assertEquals('按日新增', $newByDayArray['label']);
    }
}
