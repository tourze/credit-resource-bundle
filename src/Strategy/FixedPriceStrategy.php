<?php

declare(strict_types=1);

namespace CreditResourceBundle\Strategy;

use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Interface\BillingStrategyInterface;

/**
 * 固定价格计费策略.
 *
 * 按固定单价 × 使用量计算费用
 */
class FixedPriceStrategy implements BillingStrategyInterface
{
    public function calculate(ResourcePrice $price, int $usage, array $context = []): string
    {
        // 基础计算：单价 × 使用量
        $unitPrice = $price->getPrice();
        if (null === $unitPrice) {
            throw new \InvalidArgumentException('单价不能为空');
        }
        if (!is_numeric($unitPrice)) {
            throw new \InvalidArgumentException('单价格式无效');
        }

        $total = bcmul($unitPrice, (string) $usage, 5);

        // 应用封顶价
        $topPrice = $price->getTopPrice();
        if (null !== $topPrice) {
            if (!is_numeric($topPrice)) {
                throw new \InvalidArgumentException('封顶价格式无效');
            }
            if (bccomp($total, $topPrice, 5) > 0) {
                return $topPrice;
            }
        }

        // 应用保底价
        $bottomPrice = $price->getBottomPrice();
        if (null !== $bottomPrice) {
            if (!is_numeric($bottomPrice)) {
                throw new \InvalidArgumentException('保底价格式无效');
            }
            if (bccomp($total, $bottomPrice, 5) < 0) {
                return $bottomPrice;
            }
        }

        return $total;
    }

    public function supports(ResourcePrice $price): bool
    {
        // 固定价格策略是默认策略，支持所有配置
        return true;
    }

    public function getName(): string
    {
        return 'fixed_price';
    }

    public function getDescription(): string
    {
        return '固定单价计费策略，费用 = 单价 × 使用量';
    }

    public function validateConfiguration(ResourcePrice $price): array
    {
        $errors = [];

        // 验证价格必须大于等于0
        $unitPrice = $price->getPrice();
        if (null === $unitPrice || !is_numeric($unitPrice) || bccomp($unitPrice, '0', 5) < 0) {
            $errors[] = '单价必须大于等于0';
        }

        // 验证封顶价必须大于0
        $topPrice = $price->getTopPrice();
        if (null !== $topPrice && (!is_numeric($topPrice) || bccomp($topPrice, '0', 5) <= 0)) {
            $errors[] = '封顶价必须大于0';
        }

        // 验证保底价必须大于等于0
        $bottomPrice = $price->getBottomPrice();
        if (null !== $bottomPrice && (!is_numeric($bottomPrice) || bccomp($bottomPrice, '0', 5) < 0)) {
            $errors[] = '保底价必须大于等于0';
        }

        // 验证封顶价必须大于保底价
        if (null !== $topPrice
            && null !== $bottomPrice
            && is_numeric($topPrice)
            && is_numeric($bottomPrice)
            && bccomp($topPrice, $bottomPrice, 5) < 0) {
            $errors[] = '封顶价必须大于保底价';
        }

        return $errors;
    }

    public function getPriority(): int
    {
        // 默认策略，优先级最低
        return 0;
    }
}
