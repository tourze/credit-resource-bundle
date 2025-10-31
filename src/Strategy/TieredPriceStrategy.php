<?php

declare(strict_types=1);

namespace CreditResourceBundle\Strategy;

use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Interface\BillingStrategyInterface;

/**
 * 阶梯价格计费策略.
 *
 * 根据使用量的不同区间采用不同的单价计算费用
 */
class TieredPriceStrategy implements BillingStrategyInterface
{
    public function calculate(ResourcePrice $price, int $usage, array $context = []): string
    {
        $rules = $price->getPriceRules();

        if ([] === $rules || null === $rules) {
            $unitPrice = $price->getPrice();
            if (null === $unitPrice) {
                throw new \InvalidArgumentException('单价不能为空');
            }
            if (!is_numeric($unitPrice)) {
                throw new \InvalidArgumentException('单价格式无效');
            }

            return bcmul($unitPrice, (string) $usage, 5);
        }

        $total = $this->calculateTieredPrice($rules, $usage, $price);

        return $this->applyPriceLimits($total, $price);
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     *
     * @return numeric-string
     */
    private function calculateTieredPrice(array $rules, int $usage, ResourcePrice $price): string
    {
        // 确保规则按最小值排序
        usort($rules, function ($a, $b) {
            return ($a['min'] ?? 0) <=> ($b['min'] ?? 0);
        });

        $total = '0';
        $remainingUsage = $usage;

        foreach ($rules as $tier) {
            if ($remainingUsage <= 0) {
                break;
            }

            $total = $this->calculateTierCost($tier, $remainingUsage, $usage, $total);
            $remainingUsage -= $this->getTierUsage($tier, $remainingUsage, $usage);
        }

        return $this->handleRemainingUsage($remainingUsage, $rules, $price, $total);
    }

    /**
     * @param array<string, mixed> $tier
     * @param numeric-string       $total
     *
     * @return numeric-string
     */
    private function calculateTierCost(array $tier, int $remainingUsage, int $usage, string $total): string
    {
        $tierMinValue = $tier['min'] ?? 0;
        $tierMaxValue = $tier['max'] ?? PHP_INT_MAX;
        $tierPriceValue = $tier['price'] ?? '0';

        $tierMin = is_numeric($tierMinValue) ? (int) $tierMinValue : 0;
        $tierMax = is_numeric($tierMaxValue) ? (int) $tierMaxValue : PHP_INT_MAX;
        $tierPrice = is_numeric($tierPriceValue) ? (string) $tierPriceValue : '0';

        if ($usage <= $tierMin) {
            return $total;
        }

        $tierUsage = $this->getTierUsage($tier, $remainingUsage, $usage);

        $tierCost = bcmul($tierPrice, (string) $tierUsage, 5);

        return bcadd($total, $tierCost, 5);
    }

    /**
     * @param array<string, mixed> $tier
     */
    private function getTierUsage(array $tier, int $remainingUsage, int $usage): int
    {
        $tierMinValue = $tier['min'] ?? 0;
        $tierMaxValue = $tier['max'] ?? PHP_INT_MAX;

        $tierMin = is_numeric($tierMinValue) ? (int) $tierMinValue : 0;
        $tierMax = is_numeric($tierMaxValue) ? (int) $tierMaxValue : PHP_INT_MAX;

        if ($usage <= $tierMin) {
            return 0;
        }

        return min($remainingUsage, $tierMax - $tierMin);
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     * @param numeric-string                   $total
     *
     * @return numeric-string
     */
    private function handleRemainingUsage(int $remainingUsage, array $rules, ResourcePrice $price, string $total): string
    {
        if ($remainingUsage > 0 && count($rules) > 0) {
            $lastTier = end($rules);
            $lastTierPrice = $lastTier['price'] ?? $price->getPrice();
            $lastPrice = is_numeric($lastTierPrice) ? (string) $lastTierPrice : (string) $price->getPrice();

            // 确保 $lastPrice 是数字字符串
            assert(is_numeric($lastPrice));

            $remainingCost = bcmul($lastPrice, (string) $remainingUsage, 5);

            return bcadd($total, $remainingCost, 5);
        }

        return $total;
    }

    /**
     * @param numeric-string $total
     *
     * @return numeric-string
     */
    private function applyPriceLimits(string $total, ResourcePrice $price): string
    {
        $topPrice = $price->getTopPrice();
        if (null !== $topPrice && is_numeric($topPrice) && bccomp($total, $topPrice, 5) > 0) {
            return $topPrice;
        }

        $bottomPrice = $price->getBottomPrice();
        if (null !== $bottomPrice && is_numeric($bottomPrice) && bccomp($total, $bottomPrice, 5) < 0) {
            return $bottomPrice;
        }

        return $total;
    }

    public function supports(ResourcePrice $price): bool
    {
        // 检查是否配置了阶梯价格规则
        $rules = $price->getPriceRules();

        return null !== $rules && count($rules) > 0;
    }

    public function getName(): string
    {
        return 'tiered_price';
    }

    public function getDescription(): string
    {
        return '阶梯价格计费策略，根据使用量区间采用不同单价';
    }

    /**
     * @return string[]
     */
    public function validateConfiguration(ResourcePrice $price): array
    {
        $errors = [];
        $rules = $price->getPriceRules();

        if ([] === $rules || null === $rules) {
            $errors[] = '阶梯价格策略必须配置价格规则';

            return $errors;
        }

        // 在前面已经检查过 $rules 为空的情况，这里 $rules 必然是数组

        $previousMax = 0;
        foreach ($rules as $index => $tier) {
            if (!is_array($tier)) {
                $errors[] = "第 {$index} 个阶梯规则格式错误";
                continue;
            }

            if (!isset($tier['price'])) {
                $errors[] = "第 {$index} 个阶梯缺少价格配置";
            } elseif (!is_numeric($tier['price']) || bccomp((string) $tier['price'], '0', 5) < 0) {
                $errors[] = "第 {$index} 个阶梯价格必须大于等于0";
            }

            $min = $tier['min'] ?? 0;
            $max = $tier['max'] ?? PHP_INT_MAX;

            if ($min < $previousMax) {
                $errors[] = "第 {$index} 个阶梯的最小值不能小于前一个阶梯的最大值";
            }

            if ($max <= $min) {
                $errors[] = "第 {$index} 个阶梯的最大值必须大于最小值";
            }

            $previousMax = $max;
        }

        return $errors;
    }

    public function getPriority(): int
    {
        // 阶梯价格优先级高于固定价格
        return 10;
    }

    /**
     * 示例配置格式.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getExampleConfiguration(): array
    {
        return [
            [
                'min' => 0,
                'max' => 100,
                'price' => '1.00',
                'description' => '0-100个：1元/个',
            ],
            [
                'min' => 100,
                'max' => 1000,
                'price' => '0.80',
                'description' => '100-1000个：0.8元/个',
            ],
            [
                'min' => 1000,
                'max' => PHP_INT_MAX,
                'price' => '0.50',
                'description' => '1000个以上：0.5元/个',
            ],
        ];
    }
}
