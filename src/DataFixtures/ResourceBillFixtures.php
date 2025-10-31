<?php

namespace CreditResourceBundle\DataFixtures;

use CreditBundle\Entity\Account;
use CreditResourceBundle\Entity\ResourceBill;
use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Enum\BillStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\UserServiceContracts\UserManagerInterface;

class ResourceBillFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly UserManagerInterface $userManager,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $resourcePrice = $this->getReference(ResourcePriceFixtures::RESOURCE_PRICE_1, ResourcePrice::class);

        // 获取或创建测试用户
        $user = $this->getOrCreateTestUser();

        // 创建测试账户
        $account = new Account();
        $account->setName('测试账户');
        $account->setCurrency('CNY');
        $account->setUser($user);
        $account->setEndingBalance('1000.00');
        $manager->persist($account);

        // 创建测试账单
        $bill = new ResourceBill();
        $bill->setUser($user);
        $bill->setAccount($account);
        $bill->setResourcePrice($resourcePrice);
        $bill->setBillTime(new \DateTimeImmutable());
        $bill->setPeriodStart(new \DateTimeImmutable('-1 hour'));
        $bill->setPeriodEnd(new \DateTimeImmutable());
        $bill->setUsage(100);
        $bill->setUnitPrice('0.50000');
        $bill->setTotalPrice('50.00000');
        $bill->setActualPrice('50.00000');
        $bill->setStatus(BillStatus::PAID);

        $manager->persist($bill);
        $manager->flush();
    }

    private function getOrCreateTestUser(): UserInterface
    {
        // 尝试加载已存在的用户
        $user = $this->userManager->loadUserByIdentifier('test-admin');

        // 如果用户不存在，创建一个新的测试用户
        if (null === $user) {
            $user = $this->userManager->createUser('test-admin', '测试管理员');
            $this->userManager->saveUser($user);
        }

        return $user;
    }

    public function getDependencies(): array
    {
        return [
            ResourcePriceFixtures::class,
        ];
    }
}
