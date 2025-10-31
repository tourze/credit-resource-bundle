<?php

declare(strict_types=1);

namespace CreditResourceBundle;

use CreditResourceBundle\Entity\ResourceBill;
use CreditResourceBundle\Entity\ResourcePrice;
use Knp\Menu\ItemInterface;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

readonly class AdminMenu implements MenuProviderInterface
{
    public function __construct(private LinkGeneratorInterface $linkGenerator)
    {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (null === $item->getChild('积分中心')) {
            $item->addChild('积分中心');
        }

        $creditCenterItem = $item->getChild('积分中心');
        if (null !== $creditCenterItem) {
            $creditCenterItem->addChild('资源价格')->setUri($this->linkGenerator->getCurdListPage(ResourcePrice::class));
            $creditCenterItem->addChild('资源账单')->setUri($this->linkGenerator->getCurdListPage(ResourceBill::class));
        }
    }
}
