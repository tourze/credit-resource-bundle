<?php

namespace CreditResourceBundle\DataFixtures;

use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Enum\FeeCycle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ResourcePriceFixtures extends Fixture
{
    public const RESOURCE_PRICE_1 = 'resource_price_1';
    public const RESOURCE_PRICE_2 = 'resource_price_2';
    public const RESOURCE_PRICE_3 = 'resource_price_3';

    public function load(ObjectManager $manager): void
    {
        $resourcePrice1 = new ResourcePrice();
        $resourcePrice1->setTitle('云服务器基础版');
        $resourcePrice1->setResource('cloud_server_basic');
        $resourcePrice1->setPrice('0.50');
        $resourcePrice1->setValid(true);
        $resourcePrice1->setCycle(FeeCycle::TOTAL_BY_HOUR);
        $resourcePrice1->setMinAmount(1);
        $resourcePrice1->setCurrency('CNY');
        $resourcePrice1->setRemark('基础云服务器按小时计费');

        $resourcePrice2 = new ResourcePrice();
        $resourcePrice2->setTitle('对象存储');
        $resourcePrice2->setResource('object_storage');
        $resourcePrice2->setPrice('0.10');
        $resourcePrice2->setValid(true);
        $resourcePrice2->setCycle(FeeCycle::TOTAL_BY_MONTH);
        $resourcePrice2->setMinAmount(0);
        $resourcePrice2->setCurrency('CNY');
        $resourcePrice2->setRemark('对象存储按月计费');

        $resourcePrice3 = new ResourcePrice();
        $resourcePrice3->setTitle('CDN流量');
        $resourcePrice3->setResource('cdn_traffic');
        $resourcePrice3->setPrice('0.25');
        $resourcePrice3->setValid(true);
        $resourcePrice3->setCycle(FeeCycle::NEW_BY_DAY);
        $resourcePrice3->setMinAmount(0);
        $resourcePrice3->setCurrency('CNY');
        $resourcePrice3->setRemark('CDN流量按日新增计费');

        $manager->persist($resourcePrice1);
        $manager->persist($resourcePrice2);
        $manager->persist($resourcePrice3);

        $manager->flush();

        $this->addReference(self::RESOURCE_PRICE_1, $resourcePrice1);
        $this->addReference(self::RESOURCE_PRICE_2, $resourcePrice2);
        $this->addReference(self::RESOURCE_PRICE_3, $resourcePrice3);
    }
}
