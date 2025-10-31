<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Strategy;

use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Enum\FeeCycle;
use CreditResourceBundle\Strategy\FixedPriceStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FixedPriceStrategy::class)]
final class FixedPriceStrategyTest extends TestCase
{
    private FixedPriceStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->strategy = new FixedPriceStrategy();
    }

    private function createResourcePrice(): ResourcePrice
    {
        $price = new ResourcePrice();
        $price->setTitle('测试资源');
        $price->setResource('test_resource');
        $price->setValid(true);
        $price->setCycle(FeeCycle::NEW_BY_DAY);
        $price->setMinAmount(0);

        // currency 现在是字符串类型的币种代码
        $price->setCurrency('CNY');

        return $price;
    }

    public function testGetName(): void
    {
        $this->assertSame('fixed_price', $this->strategy->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertSame('固定单价计费策略，费用 = 单价 × 使用量', $this->strategy->getDescription());
    }

    public function testGetPriority(): void
    {
        $this->assertSame(0, $this->strategy->getPriority());
    }

    public function testSupportsAlwaysReturnsTrue(): void
    {
        $price = $this->createResourcePrice();
        $this->assertTrue($this->strategy->supports($price));
    }

    public function testCalculateBasicPrice(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('10.00');

        $result = $this->strategy->calculate($price, 5);
        $this->assertSame('50.00000', $result);
    }

    public function testCalculateWithTopPrice(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('10.00');
        $price->setTopPrice('30.00');

        // 正常计算应该是 10 * 5 = 50，但有封顶价 30
        $result = $this->strategy->calculate($price, 5);
        $this->assertSame('30.00', $result);
    }

    public function testCalculateWithBottomPrice(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('10.00');
        $price->setBottomPrice('100.00');

        // 正常计算应该是 10 * 1 = 10，但有保底价 100
        $result = $this->strategy->calculate($price, 1);
        $this->assertSame('100.00', $result);
    }

    public function testCalculateWithBothTopAndBottomPrice(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('10.00');
        $price->setTopPrice('200.00');
        $price->setBottomPrice('50.00');

        // 测试不触发保底价和封顶价的情况
        $result = $this->strategy->calculate($price, 10);
        $this->assertSame('100.00000', $result);

        // 测试触发保底价
        $result = $this->strategy->calculate($price, 1);
        $this->assertSame('50.00', $result);

        // 测试触发封顶价
        $result = $this->strategy->calculate($price, 30);
        $this->assertSame('200.00', $result);
    }

    public function testCalculateWithZeroUsage(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('10.00');

        $result = $this->strategy->calculate($price, 0);
        $this->assertSame('0.00000', $result);
    }

    public function testValidateConfigurationWithValidPrice(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('10.00');

        $errors = $this->strategy->validateConfiguration($price);
        $this->assertEmpty($errors);
    }

    public function testValidateConfigurationWithNegativePrice(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('-10.00');

        $errors = $this->strategy->validateConfiguration($price);
        $this->assertContains('单价必须大于等于0', $errors);
    }

    public function testValidateConfigurationWithNullPrice(): void
    {
        $price = $this->createResourcePrice();

        $errors = $this->strategy->validateConfiguration($price);
        $this->assertContains('单价必须大于等于0', $errors);
    }

    public function testValidateConfigurationWithInvalidTopPrice(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('10.00');
        $price->setTopPrice('0.00');

        $errors = $this->strategy->validateConfiguration($price);
        $this->assertContains('封顶价必须大于0', $errors);
    }

    public function testValidateConfigurationWithNegativeBottomPrice(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('10.00');
        $price->setBottomPrice('-10.00');

        $errors = $this->strategy->validateConfiguration($price);
        $this->assertContains('保底价必须大于等于0', $errors);
    }

    public function testValidateConfigurationWithTopPriceLessThanBottomPrice(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('10.00');
        $price->setTopPrice('50.00');
        $price->setBottomPrice('100.00');

        $errors = $this->strategy->validateConfiguration($price);
        $this->assertContains('封顶价必须大于保底价', $errors);
    }

    public function testValidateConfigurationWithAllValidPrices(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('10.00');
        $price->setTopPrice('200.00');
        $price->setBottomPrice('50.00');

        $errors = $this->strategy->validateConfiguration($price);
        $this->assertEmpty($errors);
    }
}
