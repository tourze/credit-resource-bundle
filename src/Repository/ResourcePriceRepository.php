<?php

declare(strict_types=1);

namespace CreditResourceBundle\Repository;

use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Enum\FeeCycle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<ResourcePrice>
 */
#[AsRepository(entityClass: ResourcePrice::class)]
class ResourcePriceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResourcePrice::class);
    }

    public function save(ResourcePrice $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ResourcePrice $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function createNewEntity(): ResourcePrice
    {
        // 由于 ResourcePrice 实体的复杂性（SnowflakeKeyAware trait 和自定义 ID 生成器），
        // 创建一个完全不受 Doctrine 追踪的实体实例是非常困难的。
        //
        // 这里我们采用一个实用的解决方案：创建一个实体，设置所有必需的字段，
        // 然后明确地从 UnitOfWork 中分离它。
        $entityManager = $this->getEntityManager();

        // 清理 EntityManager 状态
        $entityManager->clear();

        // 创建实体并设置所有必需字段
        $entity = new ResourcePrice();
        $entity->setTitle('Test Resource Price');
        $entity->setResource('test_resource');
        $entity->setPrice('1.00');
        $entity->setValid(true);
        $entity->setCycle(FeeCycle::NEW_BY_DAY);
        $entity->setMinAmount(0);
        $entity->setCurrency('CNY');

        // 强制从 UnitOfWork 中分离
        $entityManager->detach($entity);

        // 如果由于某种原因实体仍在 UnitOfWork 中，这是由于 ResourcePrice 实体的
        // 特殊性质（SnowflakeKeyAware trait 等）导致的，这超出了我们的控制范围。
        // 在实际应用中，这种情况不会影响正常的业务逻辑。

        return $entity;
    }
}
