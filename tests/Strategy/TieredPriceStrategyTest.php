<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Strategy;

use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Enum\FeeCycle;
use CreditResourceBundle\Strategy\TieredPriceStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TieredPriceStrategy::class)]
final class TieredPriceStrategyTest extends TestCase
{
    private TieredPriceStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->strategy = new TieredPriceStrategy();
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
        $this->assertSame('tiered_price', $this->strategy->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertSame('阶梯价格计费策略，根据使用量区间采用不同单价', $this->strategy->getDescription());
    }

    public function testGetPriority(): void
    {
        $this->assertSame(10, $this->strategy->getPriority());
    }

    public function testSupportsWithEmptyRules(): void
    {
        $price = $this->createResourcePrice();
        $price->setPriceRules([]);

        $this->assertFalse($this->strategy->supports($price));
    }

    public function testSupportsWithValidRules(): void
    {
        $price = $this->createResourcePrice();
        $price->setPriceRules([
            ['min' => 0, 'max' => 100, 'price' => '1.00'],
        ]);

        $this->assertTrue($this->strategy->supports($price));
    }

    public function testCalculateWithNoRules(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('10.00');
        $price->setPriceRules([]);

        $result = $this->strategy->calculate($price, 5);
        $this->assertSame('50.00000', $result);
    }

    public function testCalculateWithSingleTier(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('10.00');
        $price->setPriceRules([
            ['min' => 0, 'max' => 100, 'price' => '2.00'],
        ]);

        $result = $this->strategy->calculate($price, 50);
        $this->assertSame('100.00000', $result);
    }

    public function testCalculateWithMultipleTiers(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('10.00');
        $price->setPriceRules([
            ['min' => 0, 'max' => 100, 'price' => '1.00'],
            ['min' => 100, 'max' => 200, 'price' => '0.80'],
            ['min' => 200, 'max' => PHP_INT_MAX, 'price' => '0.50'],
        ]);

        // 使用150个：前100个按1.00，后50个按0.80
        // 100 * 1.00 + 50 * 0.80 = 100 + 40 = 140
        $result = $this->strategy->calculate($price, 150);
        $this->assertSame('140.00000', $result);
    }

    public function testCalculateExceedingAllTiers(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('10.00');
        $price->setPriceRules([
            ['min' => 0, 'max' => 100, 'price' => '1.00'],
        ]);

        // 使用150个：前100个按1.00，后50个按最后一个阶梯价格1.00
        $result = $this->strategy->calculate($price, 150);
        $this->assertSame('150.00000', $result);
    }

    public function testCalculateWithTopPrice(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('10.00');
        $price->setTopPrice('50.00');
        $price->setPriceRules([
            ['min' => 0, 'max' => 100, 'price' => '1.00'],
        ]);

        // 计算应该是100，但有封顶价50
        $result = $this->strategy->calculate($price, 100);
        $this->assertSame('50.00', $result);
    }

    public function testCalculateWithBottomPrice(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('10.00');
        $price->setBottomPrice('50.00');
        $price->setPriceRules([
            ['min' => 0, 'max' => 100, 'price' => '1.00'],
        ]);

        // 计算应该是10，但有保底价50
        $result = $this->strategy->calculate($price, 10);
        $this->assertSame('50.00', $result);
    }

    public function testCalculateWithUnorderedRules(): void
    {
        $price = $this->createResourcePrice();
        $price->setPrice('10.00');
        $price->setPriceRules([
            ['min' => 100, 'max' => 200, 'price' => '0.80'],
            ['min' => 0, 'max' => 100, 'price' => '1.00'],
        ]);

        // 应该能正确排序并计算
        $result = $this->strategy->calculate($price, 150);
        $this->assertSame('140.00000', $result);
    }

    public function testValidateConfigurationWithNoRules(): void
    {
        $price = $this->createResourcePrice();
        $price->setPriceRules([]);

        $errors = $this->strategy->validateConfiguration($price);
        $this->assertContains('阶梯价格策略必须配置价格规则', $errors);
    }

    public function testValidateConfigurationWithInvalidRule(): void
    {
        $price = $this->createResourcePrice();
        $price->setPriceRules([
            'invalid_rule',
        ]);

        $errors = $this->strategy->validateConfiguration($price);
        $this->assertContains('第 0 个阶梯规则格式错误', $errors);
    }

    public function testValidateConfigurationWithMissingPrice(): void
    {
        $price = $this->createResourcePrice();
        $price->setPriceRules([
            ['min' => 0, 'max' => 100],
        ]);

        $errors = $this->strategy->validateConfiguration($price);
        $this->assertContains('第 0 个阶梯缺少价格配置', $errors);
    }

    public function testValidateConfigurationWithNegativePrice(): void
    {
        $price = $this->createResourcePrice();
        $price->setPriceRules([
            ['min' => 0, 'max' => 100, 'price' => '-1.00'],
        ]);

        $errors = $this->strategy->validateConfiguration($price);
        $this->assertContains('第 0 个阶梯价格必须大于等于0', $errors);
    }

    public function testValidateConfigurationWithOverlappingRanges(): void
    {
        $price = $this->createResourcePrice();
        $price->setPriceRules([
            ['min' => 0, 'max' => 100, 'price' => '1.00'],
            ['min' => 50, 'max' => 150, 'price' => '0.80'],
        ]);

        $errors = $this->strategy->validateConfiguration($price);
        $this->assertContains('第 1 个阶梯的最小值不能小于前一个阶梯的最大值', $errors);
    }

    public function testValidateConfigurationWithInvalidRange(): void
    {
        $price = $this->createResourcePrice();
        $price->setPriceRules([
            ['min' => 100, 'max' => 50, 'price' => '1.00'],
        ]);

        $errors = $this->strategy->validateConfiguration($price);
        $this->assertContains('第 0 个阶梯的最大值必须大于最小值', $errors);
    }

    public function testValidateConfigurationWithValidRules(): void
    {
        $price = $this->createResourcePrice();
        $price->setPriceRules([
            ['min' => 0, 'max' => 100, 'price' => '1.00'],
            ['min' => 100, 'max' => 200, 'price' => '0.80'],
        ]);

        $errors = $this->strategy->validateConfiguration($price);
        $this->assertEmpty($errors);
    }

    public function testGetExampleConfiguration(): void
    {
        $example = $this->strategy->getExampleConfiguration();

        $this->assertCount(3, $example);
        $this->assertArrayHasKey('min', $example[0]);
        $this->assertArrayHasKey('max', $example[0]);
        $this->assertArrayHasKey('price', $example[0]);
        $this->assertArrayHasKey('description', $example[0]);
    }
}
