<?php

namespace CreditResourceBundle\Tests;

use CreditResourceBundle\AdminMenu;
use CreditResourceBundle\Entity\ResourcePrice;
use Knp\Menu\ItemInterface;
use PHPUnit\Framework\TestCase;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;

class AdminMenuTest extends TestCase
{
    public function testInvokeCreatesMenuStructure(): void
    {
        $linkGenerator = $this->createMock(LinkGeneratorInterface::class);
        $linkGenerator->expects($this->once())
            ->method('getCurdListPage')
            ->with(ResourcePrice::class)
            ->willReturn('/admin/resource-price');

        $creditCenterItem = $this->createMock(ItemInterface::class);
        $creditCenterItem->expects($this->once())
            ->method('addChild')
            ->with('资源价格')
            ->willReturnSelf();
        $creditCenterItem->expects($this->once())
            ->method('setUri')
            ->with('/admin/resource-price')
            ->willReturnSelf();

        $rootItem = $this->createMock(ItemInterface::class);
        $rootItem->expects($this->exactly(2))
            ->method('getChild')
            ->with('积分中心')
            ->willReturnOnConsecutiveCalls(null, $creditCenterItem);
        $rootItem->expects($this->once())
            ->method('addChild')
            ->with('积分中心')
            ->willReturn($creditCenterItem);

        $adminMenu = new AdminMenu($linkGenerator);
        $adminMenu($rootItem);
    }

    public function testInvokeUsesExistingCreditCenterMenu(): void
    {
        $linkGenerator = $this->createMock(LinkGeneratorInterface::class);
        $linkGenerator->expects($this->once())
            ->method('getCurdListPage')
            ->with(ResourcePrice::class)
            ->willReturn('/admin/resource-price');

        $creditCenterItem = $this->createMock(ItemInterface::class);
        $creditCenterItem->expects($this->once())
            ->method('addChild')
            ->with('资源价格')
            ->willReturnSelf();
        $creditCenterItem->expects($this->once())
            ->method('setUri')
            ->with('/admin/resource-price')
            ->willReturnSelf();

        $rootItem = $this->createMock(ItemInterface::class);
        $rootItem->expects($this->exactly(2))
            ->method('getChild')
            ->with('积分中心')
            ->willReturn($creditCenterItem);
        $rootItem->expects($this->never())
            ->method('addChild');

        $adminMenu = new AdminMenu($linkGenerator);
        $adminMenu($rootItem);
    }
}