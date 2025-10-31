<?php

namespace CreditResourceBundle\Tests;

use CreditResourceBundle\AdminMenu;
use Knp\Menu\ItemInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;

/**
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    private AdminMenu $adminMenu;

    protected function onSetUp(): void
    {
        $adminMenu = self::getContainer()->get(AdminMenu::class);
        $this->assertInstanceOf(AdminMenu::class, $adminMenu);
        $this->adminMenu = $adminMenu;
    }

    public function testAddsResourcePriceMenuToExistingCreditCenter(): void
    {
        $rootItem = $this->createMock(ItemInterface::class);
        $creditCenterItem = $this->createMock(ItemInterface::class);
        $resourcePriceItem = $this->createMock(ItemInterface::class);
        $resourceBillItem = $this->createMock(ItemInterface::class);

        $rootItem
            ->expects($this->exactly(2))
            ->method('getChild')
            ->with('积分中心')
            ->willReturn($creditCenterItem)
        ;

        $creditCenterItem
            ->expects($this->exactly(2))
            ->method('addChild')
            ->willReturnCallback(function (string $name) use ($resourcePriceItem, $resourceBillItem) {
                return match ($name) {
                    '资源价格' => $resourcePriceItem,
                    '资源账单' => $resourceBillItem,
                    default => throw new \InvalidArgumentException('Unexpected menu item: ' . $name),
                };
            })
        ;

        $resourcePriceItem
            ->expects($this->once())
            ->method('setUri')
        ;

        $resourceBillItem
            ->expects($this->once())
            ->method('setUri')
        ;

        ($this->adminMenu)($rootItem);
    }

    public function testCreatesNewCreditCenterIfNotExists(): void
    {
        $rootItem = $this->createMock(ItemInterface::class);
        $creditCenterItem = $this->createMock(ItemInterface::class);
        $resourcePriceItem = $this->createMock(ItemInterface::class);
        $resourceBillItem = $this->createMock(ItemInterface::class);

        $rootItem
            ->expects($this->exactly(2))
            ->method('getChild')
            ->with('积分中心')
            ->willReturnOnConsecutiveCalls(null, $creditCenterItem)
        ;

        $rootItem
            ->expects($this->once())
            ->method('addChild')
            ->with('积分中心')
        ;

        $creditCenterItem
            ->expects($this->exactly(2))
            ->method('addChild')
            ->willReturnCallback(function (string $name) use ($resourcePriceItem, $resourceBillItem) {
                return match ($name) {
                    '资源价格' => $resourcePriceItem,
                    '资源账单' => $resourceBillItem,
                    default => throw new \InvalidArgumentException('Unexpected menu item: ' . $name),
                };
            })
        ;

        $resourcePriceItem
            ->expects($this->once())
            ->method('setUri')
        ;

        $resourceBillItem
            ->expects($this->once())
            ->method('setUri')
        ;

        ($this->adminMenu)($rootItem);
    }

    public function testHandlesNullCreditCenterGracefully(): void
    {
        $rootItem = $this->createMock(ItemInterface::class);

        $rootItem
            ->expects($this->exactly(2))
            ->method('getChild')
            ->with('积分中心')
            ->willReturn(null)
        ;

        $rootItem
            ->expects($this->once())
            ->method('addChild')
            ->with('积分中心')
        ;

        ($this->adminMenu)($rootItem);
    }
}
