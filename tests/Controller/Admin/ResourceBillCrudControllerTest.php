<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Controller\Admin;

use BizUserBundle\Entity\BizUser;
use CreditResourceBundle\Controller\Admin\ResourceBillCrudController;
use CreditResourceBundle\Entity\ResourceBill;
use CreditResourceBundle\Enum\BillStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\BizRoleBundle\Repository\BizRoleRepository;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(ResourceBillCrudController::class)]
#[RunTestsInSeparateProcesses]
final class ResourceBillCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    protected function getEntityFqcn(): string
    {
        return ResourceBill::class;
    }

    protected function getControllerService(): AbstractCrudController&ResourceBillCrudController
    {
        return self::getService(ResourceBillCrudController::class);
    }

    /**
     * 覆盖父类方法，创建具有ROLE_SUPER_ADMIN权限的管理员用户
     * ResourceBillCrudController要求ROLE_SUPER_ADMIN权限
     */
    protected function createAdminUser(string $username = 'admin', string $password = 'password'): UserInterface
    {
        $em = self::getEntityManager();

        // 尝试查找已存在的用户
        $user = $em->getRepository(BizUser::class)->findOneBy(['username' => $username]);

        if ($user instanceof BizUser) {
            return $user;
        }

        // 创建新用户
        $user = new BizUser();
        $user->setUsername($username);
        $user->setValid(true);

        // 设置密码
        $passwordHasher = self::getService(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $passwordHasher);
        $user->setPasswordHash($passwordHasher->hashPassword($user, $password));

        // 添加ROLE_ADMIN和ROLE_SUPER_ADMIN角色
        // 注意：ROLE_SUPER_ADMIN用户也需要ROLE_ADMIN作为基础角色
        $roleRepository = self::getService(BizRoleRepository::class);
        self::assertInstanceOf(BizRoleRepository::class, $roleRepository);
        $user->addAssignRole($roleRepository->findOrCreate('ROLE_ADMIN'));
        $user->addAssignRole($roleRepository->findOrCreate('ROLE_SUPER_ADMIN'));

        // 如果用户名是邮箱格式，也设置邮箱
        if (false !== filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $user->setEmail($username);
        }

        // 保存用户
        $em->persist($user);
        $em->flush();

        return $user;
    }

    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '用户' => ['用户'];
        yield '资源价格配置' => ['资源价格配置'];
        yield '扣费账户' => ['扣费账户'];
        yield '账单时间' => ['账单时间'];
        yield '统计周期开始' => ['统计周期开始'];
        yield '统计周期结束' => ['统计周期结束'];
        yield '使用量' => ['使用量'];
        yield '单价' => ['单价'];
        yield '总价' => ['总价'];
        yield '实际扣费金额' => ['实际扣费金额'];
        yield '账单状态' => ['账单状态'];
        yield '创建时间' => ['创建时间'];
    }

    public static function provideEditPageFields(): iterable
    {
        yield 'user' => ['user'];
        yield 'resourcePrice' => ['resourcePrice'];
        yield 'account' => ['account'];
        yield 'billTime' => ['billTime'];
        yield 'periodStart' => ['periodStart'];
        yield 'periodEnd' => ['periodEnd'];
        yield 'usage' => ['usage'];
        yield 'usageDetails' => ['usageDetails'];
        yield 'unitPrice' => ['unitPrice'];
        yield 'totalPrice' => ['totalPrice'];
        yield 'actualPrice' => ['actualPrice'];
        yield 'status' => ['status'];
        yield 'transaction' => ['transaction'];
        yield 'failureReason' => ['failureReason'];
        yield 'paidAt' => ['paidAt'];
    }

    public static function provideNewPageFields(): iterable
    {
        // NEW 操作被禁用，但测试框架要求至少返回一个字段
        // 返回一个虚拟字段以满足测试要求
        yield 'id' => ['id'];
    }

    public function testEntityHasRequiredProperties(): void
    {
        $reflection = new \ReflectionClass(ResourceBill::class);

        // 验证实体具有必需的属性
        $this->assertTrue($reflection->hasProperty('id'));
        $this->assertTrue($reflection->hasProperty('user'));
        $this->assertTrue($reflection->hasProperty('resourcePrice'));
        $this->assertTrue($reflection->hasProperty('account'));
        $this->assertTrue($reflection->hasProperty('billTime'));
        $this->assertTrue($reflection->hasProperty('periodStart'));
        $this->assertTrue($reflection->hasProperty('periodEnd'));
        $this->assertTrue($reflection->hasProperty('usage'));
        $this->assertTrue($reflection->hasProperty('usageDetails'));
        $this->assertTrue($reflection->hasProperty('unitPrice'));
        $this->assertTrue($reflection->hasProperty('totalPrice'));
        $this->assertTrue($reflection->hasProperty('actualPrice'));
        $this->assertTrue($reflection->hasProperty('status'));
        $this->assertTrue($reflection->hasProperty('transaction'));
        $this->assertTrue($reflection->hasProperty('failureReason'));
        $this->assertTrue($reflection->hasProperty('paidAt'));
    }

    public function testBillStatusEnumValues(): void
    {
        $expectedValues = ['pending', 'processing', 'paid', 'failed', 'cancelled'];
        $actualValues = array_map(fn (BillStatus $status) => $status->value, BillStatus::cases());

        $this->assertEquals($expectedValues, $actualValues);
    }

    public function testBillStatusLabels(): void
    {
        $this->assertEquals('待支付', BillStatus::PENDING->getLabel());
        $this->assertEquals('处理中', BillStatus::PROCESSING->getLabel());
        $this->assertEquals('已支付', BillStatus::PAID->getLabel());
        $this->assertEquals('支付失败', BillStatus::FAILED->getLabel());
        $this->assertEquals('已取消', BillStatus::CANCELLED->getLabel());
    }

    public function testValidationErrors(): void
    {
        // NEW 操作已禁用，验证Controller配置中确实禁用了NEW操作
        // 这符合业务逻辑：账单应该通过系统自动生成，而不是手动创建

        $controller = $this->getControllerService();
        $actions = $controller->configureActions(Actions::new());

        $disabledActions = $actions->getAsDto(Crud::PAGE_INDEX)->getDisabledActions();

        // 验证NEW操作确实被禁用
        $this->assertContains(Action::NEW, $disabledActions, 'NEW action should be disabled for ResourceBillCrudController');

        // 注释：如果NEW操作未被禁用（理论情况），应该测试表单验证：
        // $client = static::createClient();
        // $this->loginAsAdmin($client);
        // $crawler = $client->request('GET', $this->generateAdminUrl(Action::NEW));
        // $form = $crawler->selectButton('Create')->form();
        // $client->submit($form);
        // $this->assertResponseStatusCodeSame(422);
        // $this->assertStringContainsString('should not be blank', $crawler->filter('.invalid-feedback')->text());
    }
}
