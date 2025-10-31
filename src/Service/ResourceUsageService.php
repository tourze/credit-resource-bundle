<?php

declare(strict_types=1);

namespace CreditResourceBundle\Service;

use CreditResourceBundle\Exception\ProviderNotFoundException;
use CreditResourceBundle\Interface\ResourceUsageProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 资源使用量统计服务
 *
 * 负责协调各种资源使用量提供者，获取用户的资源使用情况
 */
class ResourceUsageService
{
    /**
     * @var ResourceUsageProviderInterface[]
     */
    private array $providers;

    /**
     * @param iterable<ResourceUsageProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(tag: 'credit_resource.usage_provider')]
        iterable $providers,
    ) {
        $this->providers = [];
        foreach ($providers as $provider) {
            $this->providers[] = $provider;
        }

        // 按优先级排序
        usort($this->providers, function ($a, $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
    }

    /**
     * 获取用户的资源使用详情.
     *
     * @param UserInterface      $user         用户
     * @param string             $resourceType 资源类型
     * @param \DateTimeInterface $start        开始时间
     * @param \DateTimeInterface $end          结束时间
     *
     * @return array<string, mixed> 使用详情
     *
     * @throws \RuntimeException 如果没有找到支持的提供者
     */
    public function getUsageDetails(
        UserInterface $user,
        string $resourceType,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): array {
        $provider = $this->findProvider($resourceType);

        if (null === $provider) {
            throw new ProviderNotFoundException(sprintf('没有找到支持资源类型 "%s" 的使用量提供者', $resourceType));
        }

        return $provider->getUsageDetails($user, $resourceType, $start, $end);
    }

    /**
     * 查找支持指定资源类型的提供者.
     *
     * @param string $resourceType 资源类型
     */
    private function findProvider(string $resourceType): ?ResourceUsageProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($resourceType)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * 批量获取用户的多种资源使用量.
     *
     * @param UserInterface      $user          用户
     * @param array<string>      $resourceTypes 资源类型数组
     * @param \DateTimeInterface $start         开始时间
     * @param \DateTimeInterface $end           结束时间
     *
     * @return array<string, int|array<string, bool|int|string>> 键为资源类型，值为使用量或错误信息
     */
    public function getBatchUsage(
        UserInterface $user,
        array $resourceTypes,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): array {
        $result = [];

        foreach ($resourceTypes as $resourceType) {
            try {
                $result[$resourceType] = $this->getUsage($user, $resourceType, $start, $end);
            } catch (\RuntimeException $e) {
                // 记录错误但继续处理其他资源
                $result[$resourceType] = [
                    'error' => true,
                    'message' => $e->getMessage(),
                    'usage' => 0,
                ];
            }
        }

        return $result;
    }

    /**
     * 获取用户的资源使用量.
     *
     * @param UserInterface      $user         用户
     * @param string             $resourceType 资源类型
     * @param \DateTimeInterface $start        开始时间
     * @param \DateTimeInterface $end          结束时间
     *
     * @return int 使用量
     *
     * @throws \RuntimeException 如果没有找到支持的提供者
     */
    public function getUsage(
        UserInterface $user,
        string $resourceType,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): int {
        $provider = $this->findProvider($resourceType);

        if (null === $provider) {
            throw new ProviderNotFoundException(sprintf('没有找到支持资源类型 "%s" 的使用量提供者', $resourceType));
        }

        return $provider->getUsage($user, $resourceType, $start, $end);
    }

    /**
     * 检查是否有支持指定资源类型的提供者.
     *
     * @param string $resourceType 资源类型
     */
    public function hasProvider(string $resourceType): bool
    {
        return null !== $this->findProvider($resourceType);
    }

    /**
     * 获取所有支持的资源类型.
     *
     * @return array<string>
     */
    public function getSupportedResourceTypes(): array
    {
        $types = [];

        // 这里简单地收集所有提供者声明支持的类型
        // 实际使用中可能需要更复杂的逻辑
        foreach ($this->providers as $provider) {
            // 由于接口只提供 supports() 方法，无法直接获取支持的类型列表
            // 这里需要提供者实现额外的方法或使用配置
            if (method_exists($provider, 'getSupportedTypes')) {
                $types = array_merge($types, $provider->getSupportedTypes());
            }
        }

        return array_unique($types);
    }

    /**
     * 添加使用量提供者（主要用于测试）.
     */
    public function addProvider(ResourceUsageProviderInterface $provider): void
    {
        $this->providers[] = $provider;

        // 重新排序
        usort($this->providers, function ($a, $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
    }
}
