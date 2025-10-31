<?php

declare(strict_types=1);

namespace CreditResourceBundle\Interface;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 资源使用量提供者接口.
 *
 * 用于提供特定类型资源的使用量统计
 */
interface ResourceUsageProviderInterface
{
    /**
     * 检查是否支持指定的资源类型.
     *
     * @param string $resourceType 资源类型
     */
    public function supports(string $resourceType): bool;

    /**
     * 获取用户的资源使用量.
     *
     * @param UserInterface      $user         用户
     * @param string             $resourceType 资源类型
     * @param \DateTimeInterface $start        开始时间
     * @param \DateTimeInterface $end          结束时间
     *
     * @return int 使用量
     */
    public function getUsage(
        UserInterface $user,
        string $resourceType,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): int;

    /**
     * 获取用户的资源使用详情.
     *
     * @param UserInterface      $user         用户
     * @param string             $resourceType 资源类型
     * @param \DateTimeInterface $start        开始时间
     * @param \DateTimeInterface $end          结束时间
     *
     * @return array<string, mixed> 使用详情
     */
    public function getUsageDetails(
        UserInterface $user,
        string $resourceType,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): array;

    /**
     * 获取提供者的优先级.
     *
     * 数字越大优先级越高，当多个提供者支持同一资源类型时，使用优先级最高的
     */
    public function getPriority(): int;
}
