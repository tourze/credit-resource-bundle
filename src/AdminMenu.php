<?php

namespace CreditResourceBundle;

use CreditResourceBundle\Entity\ResourcePrice;
use Knp\Menu\ItemInterface;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

class AdminMenu implements MenuProviderInterface
{
    public function __construct(private readonly LinkGeneratorInterface $linkGenerator)
    {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (!$item->getChild('积分中心')) {
            $item->addChild('积分中心');
        }

        $item->getChild('积分中心')->addChild('资源价格')->setUri($this->linkGenerator->getCurdListPage(ResourcePrice::class));
    }
}
