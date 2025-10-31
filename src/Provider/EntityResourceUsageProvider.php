<?php

declare(strict_types=1);

namespace CreditResourceBundle\Provider;

use CreditResourceBundle\Enum\FeeCycle;
use CreditResourceBundle\Interface\ResourceUsageProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 基于 Doctrine 实体的资源使用量提供者.
 *
 * 支持统计任何 Doctrine 实体的数量作为资源使用量
 */
class EntityResourceUsageProvider implements ResourceUsageProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function supports(string $resourceType): bool
    {
        // 检查是否为实体类名
        if (!class_exists($resourceType)) {
            return false;
        }

        // 检查是否为 Doctrine 实体
        try {
            $this->entityManager->getClassMetadata($resourceType);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getUsageDetails(
        UserInterface $user,
        string $resourceType,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): array {
        $count = $this->getUsage($user, $resourceType, $start, $end);

        return [
            'resource_type' => $resourceType,
            'entity_class' => $resourceType,
            'user_id' => $user->getUserIdentifier(),
            'period_start' => $start->format('Y-m-d H:i:s'),
            'period_end' => $end->format('Y-m-d H:i:s'),
            'count' => $count,
            'provider' => self::class,
        ];
    }

    public function getUsage(
        UserInterface $user,
        string $resourceType,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): int {
        // 确保是有效的实体类名
        if (!class_exists($resourceType)) {
            return 0;
        }

        /** @var class-string $resourceType */
        $repository = $this->entityManager->getRepository($resourceType);
        $qb = $repository->createQueryBuilder('e');
        $qb->select('COUNT(e.id)');

        $metadata = $this->entityManager->getClassMetadata($resourceType);

        $this->addUserFilter($qb, $metadata, $user);
        $this->addTimeFilter($qb, $metadata, $start, $end);

        try {
            return (int) $qb->getQuery()->getSingleScalarResult();
        } catch (\Exception) {
            return 0;
        }
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function addUserFilter(QueryBuilder $qb, ClassMetadata $metadata, UserInterface $user): void
    {
        $userFields = ['user', 'owner', 'createdBy', 'userId'];
        $userFieldFound = false;

        foreach ($userFields as $field) {
            if ($metadata->hasField($field) || $metadata->hasAssociation($field)) {
                $qb->andWhere("e.{$field} = :user")
                    ->setParameter('user', $user)
                ;
                $userFieldFound = true;
                break;
            }
        }

        if (!$userFieldFound && $metadata->hasField('userId')) {
            $qb->andWhere('e.userId = :userId')
                ->setParameter('userId', $user->getUserIdentifier())
            ;
        }
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function addTimeFilter(QueryBuilder $qb, ClassMetadata $metadata, \DateTimeInterface $start, \DateTimeInterface $end): void
    {
        $timeFields = ['createdAt', 'createTime', 'created'];
        foreach ($timeFields as $field) {
            if ($metadata->hasField($field)) {
                $qb->andWhere("e.{$field} BETWEEN :start AND :end")
                    ->setParameter('start', $start)
                    ->setParameter('end', $end)
                ;
                break;
            }
        }
    }

    public function getPriority(): int
    {
        // 默认优先级
        return 0;
    }

    /**
     * 根据计费周期获取查询的时间范围.
     *
     * @param FeeCycle           $cycle    计费周期
     * @param \DateTimeInterface $billTime 账单时间
     *
     * @return array{start: \DateTimeInterface, end: \DateTimeInterface}
     */
    public function getTimeRangeForCycle(FeeCycle $cycle, \DateTimeInterface $billTime): array
    {
        $billDateTime = $billTime instanceof \DateTimeImmutable ? $billTime : new \DateTimeImmutable($billTime->format('Y-m-d H:i:s'));
        $start = $billDateTime->modify('-1 day'); // default fallback
        $end = $billDateTime;

        switch ($cycle) {
            case FeeCycle::TOTAL_BY_HOUR:
            case FeeCycle::NEW_BY_HOUR:
                $start = $billDateTime->modify('-1 hour');
                break;

            case FeeCycle::TOTAL_BY_DAY:
            case FeeCycle::NEW_BY_DAY:
                $start = $billDateTime->modify('-1 day');
                break;

            case FeeCycle::TOTAL_BY_MONTH:
            case FeeCycle::NEW_BY_MONTH:
                $start = $billDateTime->modify('-1 month');
                break;

            case FeeCycle::TOTAL_BY_YEAR:
            case FeeCycle::NEW_BY_YEAR:
                $start = $billDateTime->modify('-1 year');
                break;
        }

        // 对于 TOTAL_BY_* 类型，需要从很早的时间开始统计
        if (in_array($cycle, [
            FeeCycle::TOTAL_BY_HOUR,
            FeeCycle::TOTAL_BY_DAY,
            FeeCycle::TOTAL_BY_MONTH,
            FeeCycle::TOTAL_BY_YEAR,
        ], true)) {
            $start = new \DateTimeImmutable('2000-01-01 00:00:00');
        }

        return ['start' => $start, 'end' => $end];
    }
}
