<?php

declare(strict_types=1);

namespace CreditResourceBundle\Repository;

use CreditResourceBundle\Entity\ResourceBill;
use CreditResourceBundle\Enum\BillStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<ResourceBill>
 */
#[AsRepository(entityClass: ResourceBill::class)]
final class ResourceBillRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResourceBill::class);
    }

    /**
     * 查找用户的账单.
     *
     * @param array<string, mixed> $criteria 额外的查询条件
     *
     * @return ResourceBill[]
     */
    public function findByUser(UserInterface $user, array $criteria = []): array
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.user = :user')
            ->setParameter('user', $user)
            ->orderBy('b.billTime', 'DESC')
        ;

        foreach ($criteria as $field => $value) {
            $qb->andWhere("b.{$field} = :{$field}")
                ->setParameter($field, $value)
            ;
        }

        /** @var ResourceBill[] */
        return $qb->getQuery()->getResult();
    }

    /**
     * 查找待处理的账单.
     *
     * @return ResourceBill[]
     */
    public function findPendingBills(int $limit = 100): array
    {
        /** @var ResourceBill[] */
        return $this->createQueryBuilder('b')
            ->andWhere('b.status = :status')
            ->setParameter('status', BillStatus::PENDING)
            ->orderBy('b.createTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找失败的账单（可重试）.
     *
     * @return ResourceBill[]
     */
    public function findRetryableBills(\DateTimeInterface $failedBefore, int $limit = 100): array
    {
        /** @var ResourceBill[] */
        return $this->createQueryBuilder('b')
            ->andWhere('b.status = :status')
            ->andWhere('b.updateTime < :failedBefore')
            ->setParameter('status', BillStatus::FAILED)
            ->setParameter('failedBefore', $failedBefore)
            ->orderBy('b.updateTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 统计用户的账单汇总.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUserBillSummary(UserInterface $user, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select([
                'COUNT(b.id) as totalCount',
                'SUM(CASE WHEN b.status = :paid THEN 1 ELSE 0 END) as paidCount',
                'SUM(CASE WHEN b.status = :failed THEN 1 ELSE 0 END) as failedCount',
                'SUM(CASE WHEN b.status = :paid THEN b.actualPrice ELSE 0 END) as totalAmount',
                'rp.id as resourcePriceId',
                'rp.title as resourceTitle',
            ])
            ->leftJoin('b.resourcePrice', 'rp')
            ->andWhere('b.user = :user')
            ->andWhere('b.billTime BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('paid', BillStatus::PAID)
            ->setParameter('failed', BillStatus::FAILED)
            ->groupBy('rp.id')
        ;

        /** @var array<int, array<string, mixed>> */
        return $qb->getQuery()->getResult();
    }

    /**
     * 检查是否已存在相同的账单（防止重复生成）.
     */
    public function existsBill(
        UserInterface $user,
        string $resourcePriceId,
        \DateTimeInterface $periodStart,
        \DateTimeInterface $periodEnd,
    ): bool {
        $count = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.user = :user')
            ->andWhere('b.resourcePrice = :resourcePriceId')
            ->andWhere('b.periodStart = :periodStart')
            ->andWhere('b.periodEnd = :periodEnd')
            ->setParameter('user', $user)
            ->setParameter('resourcePriceId', $resourcePriceId)
            ->setParameter('periodStart', $periodStart)
            ->setParameter('periodEnd', $periodEnd)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $count > 0;
    }

    public function save(ResourceBill $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ResourceBill $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function createNewEntity(): ResourceBill
    {
        $entity = new ResourceBill();
        // 设置基本字段以避免测试框架的持久化检查失败
        $entity->setBillTime(new \DateTimeImmutable());
        $entity->setPeriodStart(new \DateTimeImmutable('-1 day'));
        $entity->setPeriodEnd(new \DateTimeImmutable());
        $entity->setStatus(BillStatus::PENDING);
        $entity->setUsage(1);
        $entity->setUnitPrice('1.00');
        $entity->setTotalPrice('1.00');
        $entity->setActualPrice('1.00');

        return $entity;
    }
}
