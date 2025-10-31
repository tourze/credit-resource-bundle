<?php

declare(strict_types=1);

namespace CreditResourceBundle\Interface;

use CreditResourceBundle\Entity\ResourcePrice;

/**
 * 计费策略接口.
 *
 * 实现不同的计费策略，如固定价格、阶梯价格、套餐价格等
 */
interface BillingStrategyInterface
{
    /**
     * 计算费用.
     *
     * @param ResourcePrice        $price   资源价格配置
     * @param int                  $usage   使用量
     * @param array<string, mixed> $context 额外的上下文信息
     *
     * @return string 计算出的费用（使用字符串保持精度）
     */
    public function calculate(ResourcePrice $price, int $usage, array $context = []): string;

    /**
     * 检查是否支持指定的资源价格配置.
     *
     * @param ResourcePrice $price 资源价格配置
     */
    public function supports(ResourcePrice $price): bool;

    /**
     * 获取策略名称.
     */
    public function getName(): string;

    /**
     * 获取策略描述.
     */
    public function getDescription(): string;

    /**
     * 验证价格配置是否有效.
     *
     * @param ResourcePrice $price 资源价格配置
     *
     * @return string[] 错误信息数组，如果为空则表示配置有效
     */
    public function validateConfiguration(ResourcePrice $price): array;

    /**
     * 获取策略的优先级.
     *
     * 数字越大优先级越高
     */
    public function getPriority(): int;
}
