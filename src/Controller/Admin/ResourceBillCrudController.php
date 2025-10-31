<?php

declare(strict_types=1);

namespace CreditResourceBundle\Controller\Admin;

use CreditResourceBundle\Entity\ResourceBill;
use CreditResourceBundle\Enum\BillStatus;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

#[AdminCrud(
    routePath: '/credit-resource/resource-bill',
    routeName: 'credit_resource_resource_bill'
)]
final class ResourceBillCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ResourceBill::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('资源账单')
            ->setEntityLabelInPlural('资源账单管理')
            ->setPageTitle(Crud::PAGE_INDEX, '账单列表')
            ->setPageTitle(Crud::PAGE_NEW, '创建账单')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑账单')
            ->setPageTitle(Crud::PAGE_DETAIL, '账单详情')
            ->setDefaultSort(['billTime' => 'DESC'])
            ->setSearchFields(['user.username', 'resourcePrice.title', 'failureReason'])
            ->showEntityActionsInlined()
            ->setFormThemes(['@EasyAdmin/crud/form_theme.html.twig'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            // 禁用新建操作，账单应该通过系统自动生成
            ->disable(Action::NEW)
            // 只允许查看和编辑状态相关字段
            ->setPermission(Action::EDIT, 'ROLE_SUPER_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        // 基础字段
        yield IdField::new('id', 'ID')
            ->onlyOnIndex()
        ;

        // 关联字段
        yield AssociationField::new('user', '用户')
            ->autocomplete()
            ->setRequired(true)
            ->setHelp('选择需要计费的用户')
        ;

        yield AssociationField::new('resourcePrice', '资源价格配置')
            ->autocomplete()
            ->setRequired(true)
            ->setHelp('选择对应的资源价格配置')
        ;

        yield AssociationField::new('account', '扣费账户')
            ->autocomplete()
            ->setRequired(true)
            ->setHelp('选择用于扣费的账户')
        ;

        // 时间字段
        yield DateTimeField::new('billTime', '账单时间')
            ->setRequired(true)
            ->setHelp('账单生成时间')
        ;

        yield DateTimeField::new('periodStart', '统计周期开始')
            ->setRequired(true)
            ->setHelp('资源使用统计的开始时间')
        ;

        yield DateTimeField::new('periodEnd', '统计周期结束')
            ->setRequired(true)
            ->setHelp('资源使用统计的结束时间')
        ;

        // 使用量相关
        yield IntegerField::new('usage', '使用量')
            ->setRequired(true)
            ->setHelp('资源的具体使用数量')
        ;

        if (Crud::PAGE_DETAIL === $pageName || Crud::PAGE_EDIT === $pageName) {
            yield CodeEditorField::new('usageDetails', '使用详情')
                ->setLanguage('javascript')
                ->setNumOfRows(10)
                ->setHelp('JSON格式的使用详情数据，包含具体的使用信息')
                ->hideOnIndex()
            ;
        }

        // 价格字段 - 使用 TextField 而不是 MoneyField，因为是 DECIMAL 字符串
        yield TextField::new('unitPrice', '单价')
            ->setRequired(true)
            ->setHelp('每单位资源的价格')
            ->setFormTypeOption('attr', ['step' => '0.00001'])
        ;

        yield TextField::new('totalPrice', '总价')
            ->setRequired(true)
            ->setHelp('总价 = 单价 × 使用量')
            ->setFormTypeOption('attr', ['step' => '0.00001'])
        ;

        yield TextField::new('actualPrice', '实际扣费金额')
            ->setRequired(true)
            ->setHelp('实际从账户扣除的金额，可能包含优惠或封顶处理')
            ->setFormTypeOption('attr', ['step' => '0.00001'])
        ;

        // 状态字段
        yield ChoiceField::new('status', '账单状态')
            ->setRequired(true)
            ->setHelp('账单的当前处理状态')
            ->setChoices(array_combine(
                array_map(fn (BillStatus $s) => $s->getLabel(), BillStatus::cases()),
                BillStatus::cases()
            ))
            ->renderAsBadges([
                BillStatus::PENDING->value => 'warning',
                BillStatus::PROCESSING->value => 'info',
                BillStatus::PAID->value => 'success',
                BillStatus::FAILED->value => 'danger',
                BillStatus::CANCELLED->value => 'secondary',
            ])
        ;

        // 关联交易记录
        yield AssociationField::new('transaction', '关联交易记录')
            ->autocomplete()
            ->setHelp('关联的积分交易记录')
            ->hideOnIndex()
        ;

        // 失败原因
        yield TextareaField::new('failureReason', '失败原因')
            ->setMaxLength(1000)
            ->setHelp('如果账单处理失败，记录失败的具体原因')
            ->hideOnIndex()
            ->setRequired(false)
        ;

        // 支付时间
        yield DateTimeField::new('paidAt', '支付时间')
            ->setHelp('账单支付完成的时间')
            ->hideOnIndex()
            ->setRequired(false)
        ;

        // 系统字段
        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm()
            ->setHelp('账单记录的创建时间')
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->onlyOnDetail()
            ->setHelp('账单记录的最后更新时间')
        ;

        if (Crud::PAGE_DETAIL === $pageName) {
            yield AssociationField::new('createBy', '创建者')
                ->setHelp('创建此账单记录的用户')
            ;

            yield AssociationField::new('updateBy', '更新者')
                ->setHelp('最后更新此账单记录的用户')
            ;
        }
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            // 状态过滤
            ->add(ChoiceFilter::new('status', '账单状态')->setChoices(
                array_combine(
                    array_map(fn (BillStatus $s) => $s->getLabel(), BillStatus::cases()),
                    array_map(fn (BillStatus $s) => $s->value, BillStatus::cases())
                )
            ))
            // 用户过滤
            ->add(EntityFilter::new('user', '用户'))
            // 资源价格配置过滤
            ->add(EntityFilter::new('resourcePrice', '资源配置'))
            // 扣费账户过滤
            ->add(EntityFilter::new('account', '扣费账户'))
            // 时间过滤
            ->add(DateTimeFilter::new('billTime', '账单时间'))
            ->add(DateTimeFilter::new('periodStart', '周期开始时间'))
            ->add(DateTimeFilter::new('periodEnd', '周期结束时间'))
            ->add(DateTimeFilter::new('paidAt', '支付时间'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }
}
