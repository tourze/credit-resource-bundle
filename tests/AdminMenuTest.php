<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests;

use CreditResourceBundle\AdminMenu;
use Knp\Menu\MenuFactory;
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
    private MenuFactory $menuFactory;

    protected function onSetUp(): void
    {
        $adminMenu = self::getContainer()->get(AdminMenu::class);
        $this->assertInstanceOf(AdminMenu::class, $adminMenu);
        $this->adminMenu = $adminMenu;
        $this->menuFactory = new MenuFactory();
    }

    public function testAddsResourcePriceMenuToExistingCreditCenter(): void
    {
        $rootItem = $this->menuFactory->createItem('root');
        $rootItem->addChild('积分中心');

        ($this->adminMenu)($rootItem);

        $creditCenterItem = $rootItem->getChild('积分中心');
        $this->assertNotNull($creditCenterItem);
        $this->assertNotNull($creditCenterItem->getChild('资源价格'));
        $this->assertNotNull($creditCenterItem->getChild('资源账单'));
    }

    public function testCreatesNewCreditCenterIfNotExists(): void
    {
        $rootItem = $this->menuFactory->createItem('root');

        ($this->adminMenu)($rootItem);

        $creditCenterItem = $rootItem->getChild('积分中心');
        $this->assertNotNull($creditCenterItem);
        $this->assertNotNull($creditCenterItem->getChild('资源价格'));
        $this->assertNotNull($creditCenterItem->getChild('资源账单'));
    }

    public function testMenuUriIsSet(): void
    {
        $rootItem = $this->menuFactory->createItem('root');

        ($this->adminMenu)($rootItem);

        $creditCenterItem = $rootItem->getChild('积分中心');
        $this->assertNotNull($creditCenterItem);

        $resourcePriceItem = $creditCenterItem->getChild('资源价格');
        $resourceBillItem = $creditCenterItem->getChild('资源账单');

        $this->assertNotNull($resourcePriceItem);
        $this->assertNotNull($resourceBillItem);
        $this->assertNotEmpty($resourcePriceItem->getUri());
        $this->assertNotEmpty($resourceBillItem->getUri());
    }
}
