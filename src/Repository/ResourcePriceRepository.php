<?php

namespace CreditResourceBundle\Repository;

use CreditResourceBundle\Entity\ResourcePrice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DoctrineEnhanceBundle\Repository\CommonRepositoryAware;

/**
 * @method ResourcePrice|null find($id, $lockMode = null, $lockVersion = null)
 * @method ResourcePrice|null findOneBy(array $criteria, array $orderBy = null)
 * @method ResourcePrice[]    findAll()
 * @method ResourcePrice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ResourcePriceRepository extends ServiceEntityRepository
{
    use CommonRepositoryAware;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResourcePrice::class);
    }
}
