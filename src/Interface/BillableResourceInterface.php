<?php

declare(strict_types=1);

namespace CreditResourceBundle\Interface;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 可计费资源接口.
 *
 * 实现此接口的类可以被资源计费系统统计和计费
 */
interface BillableResourceInterface
{
    /**
     * 获取资源类型标识.
     *
     * 用于在 ResourcePrice 中标识资源类型
     *
     * @return string 资源类型，如 "entity.user", "api.call", "storage.space"
     */
    public function getResourceType(): string;

    /**
     * 获取资源名称.
     *
     * 用于展示的友好名称
     *
     * @return string 资源名称，如 "用户数", "API调用次数", "存储空间"
     */
    public function getResourceName(): string;

    /**
     * 获取指定用户在指定时间段内的资源使用量.
     *
     * @param UserInterface      $user  用户
     * @param \DateTimeInterface $start 开始时间
     * @param \DateTimeInterface $end   结束时间
     *
     * @return int 使用量
     */
    public function getUsageCount(UserInterface $user, \DateTimeInterface $start, \DateTimeInterface $end): int;

    /**
     * 获取指定用户在指定时间段内的资源使用详情.
     *
     * 用于提供更详细的使用信息，将被存储在账单的 usageDetails 字段
     *
     * @param UserInterface      $user  用户
     * @param \DateTimeInterface $start 开始时间
     * @param \DateTimeInterface $end   结束时间
     *
     * @return array<string, mixed> 使用详情
     */
    public function getUsageDetails(UserInterface $user, \DateTimeInterface $start, \DateTimeInterface $end): array;

    /**
     * 获取资源的计量单位.
     *
     * @return string 单位，如 "个", "次", "GB", "小时"
     */
    public function getUnit(): string;

    /**
     * 检查资源是否支持实时统计
     *
     * 某些资源可能只能在特定时间统计（如月底统计）
     */
    public function supportsRealtimeUsage(): bool;
}
