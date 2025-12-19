<?php

declare(strict_types=1);

namespace CreditResourceBundle\Controller\Admin;

use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Enum\FeeCycle;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Symfony\Component\Security\Core\User\UserInterface;

#[AdminCrud(
    routePath: '/credit-resource/resource-price',
    routeName: 'credit_resource_resource_price'
)]
final class ResourcePriceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ResourcePrice::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('资源价格')
            ->setEntityLabelInPlural('资源价格管理')
            ->setPageTitle(Crud::PAGE_INDEX, '资源价格列表')
            ->setPageTitle(Crud::PAGE_NEW, '新建资源价格')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑资源价格')
            ->setPageTitle(Crud::PAGE_DETAIL, '资源价格详情')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['title', 'resource', 'currency', 'remark'])
            ->showEntityActionsInlined()
            ->setFormThemes(['@EasyAdmin/crud/form_theme.html.twig'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnIndex()
        ;

        yield BooleanField::new('valid', '有效状态')
            ->renderAsSwitch(false)
            ->setHelp('设置此资源价格配置是否启用')
        ;

        yield TextField::new('title', '资源名称')
            ->setColumns('col-md-6')
            ->setRequired(true)
            ->setMaxLength(200)
            ->setHelp('用于识别的资源价格配置名称')
        ;

        yield TextField::new('resource', '资源ID')
            ->setColumns('col-md-6')
            ->setRequired(true)
            ->setMaxLength(1000)
            ->setHelp('关联的实体类名，如 App\Entity\User')
        ;

        yield ChoiceField::new('cycle', '计费周期')
            ->setColumns('col-md-6')
            ->setRequired(true)
            ->setChoices(array_combine(
                array_map(fn ($c) => $c->getLabel(), FeeCycle::cases()),
                FeeCycle::cases()
            ))
            ->renderAsBadges([
                FeeCycle::TOTAL_BY_YEAR->value => 'primary',
                FeeCycle::TOTAL_BY_MONTH->value => 'success',
                FeeCycle::TOTAL_BY_DAY->value => 'info',
                FeeCycle::TOTAL_BY_HOUR->value => 'warning',
                FeeCycle::NEW_BY_YEAR->value => 'secondary',
                FeeCycle::NEW_BY_MONTH->value => 'dark',
                FeeCycle::NEW_BY_DAY->value => 'light',
                FeeCycle::NEW_BY_HOUR->value => 'danger',
            ])
            ->setHelp('选择计费的时间周期')
        ;

        yield TextField::new('currency', '币种代码')
            ->setColumns('col-md-6')
            ->setRequired(true)
            ->setMaxLength(20)
            ->setHelp('如：CNY、USD等')
        ;

        yield IntegerField::new('minAmount', '起始计费数量')
            ->setColumns('col-md-6')
            ->setRequired(true)
            ->setHelp('开始计费的最小数量')
        ;

        yield IntegerField::new('maxAmount', '最大计费数量')
            ->setColumns('col-md-6')
            ->setHelp('计费的最大数量上限，为空表示无上限')
        ;

        yield MoneyField::new('price', '单价')
            ->setColumns('col-md-6')
            ->setCurrency('CNY')
            ->setStoredAsCents(false)
            ->setRequired(true)
            ->setHelp('每单位的价格')
        ;

        yield MoneyField::new('topPrice', '封顶价格')
            ->setColumns('col-md-6')
            ->setCurrency('CNY')
            ->setStoredAsCents(false)
            ->setHelp('价格上限，超过此价格不再计费')
        ;

        yield MoneyField::new('bottomPrice', '保底价格')
            ->setColumns('col-md-6')
            ->setCurrency('CNY')
            ->setStoredAsCents(false)
            ->setHelp('最低收费价格')
        ;

        yield IntegerField::new('freeQuota', '免费额度')
            ->setColumns('col-md-6')
            ->setHelp('免费使用的数量')
        ;

        yield TextField::new('billingStrategy', '计费策略类名')
            ->setColumns('col-md-6')
            ->setMaxLength(50)
            ->setHelp('自定义计费策略类')
            ->hideOnIndex()
        ;

        if (Crud::PAGE_EDIT === $pageName || Crud::PAGE_NEW === $pageName) {
            yield CodeEditorField::new('priceRules', '价格规则')
                ->setLanguage('javascript')
                ->setNumOfRows(10)
                ->setHelp('阶梯价格等复杂计费规则的JSON配置')
                ->hideOnIndex()
            ;
        } else {
            yield ArrayField::new('priceRules', '价格规则')
                ->hideOnIndex()
                ->onlyOnDetail()
            ;
        }

        yield AssociationField::new('roles', '适用角色')
            ->setColumns('col-md-12')
            ->hideOnIndex()
            ->setHelp('只有这些角色的用户才会被计费')
        ;

        yield DateTimeField::new('startTime', '生效开始时间')
            ->setColumns('col-md-6')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('价格配置生效的开始时间')
            ->hideOnIndex()
        ;

        yield DateTimeField::new('endTime', '生效结束时间')
            ->setColumns('col-md-6')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('价格配置生效的结束时间')
            ->hideOnIndex()
        ;

        yield TextareaField::new('remark', '备注')
            ->setColumns('col-md-12')
            ->setMaxLength(255)
            ->setHelp('价格配置的备注说明')
            ->hideOnIndex()
        ;

        yield TextField::new('createdFromIp', '创建IP')
            ->onlyOnDetail()
        ;

        yield TextField::new('updatedFromIp', '更新IP')
            ->onlyOnDetail()
        ;

        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->onlyOnDetail()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;

        yield AssociationField::new('createdBy', '创建者')
            ->onlyOnDetail()
            ->formatValue(function ($value, $entity) {
                if ($value instanceof UserInterface) {
                    return $value->getUserIdentifier();
                }

                return $value;
            })
        ;

        yield AssociationField::new('updatedBy', '更新者')
            ->onlyOnDetail()
            ->formatValue(function ($value, $entity) {
                if ($value instanceof UserInterface) {
                    return $value->getUserIdentifier();
                }

                return $value;
            })
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('valid', '有效状态'))
            ->add(ChoiceFilter::new('cycle', '计费周期')->setChoices(
                array_combine(
                    array_map(fn ($c) => $c->getLabel(), FeeCycle::cases()),
                    array_map(fn ($c) => $c->value, FeeCycle::cases())
                )
            ))
            ->add('currency')
            ->add('resource')
            ->add(NumericFilter::new('minAmount', '起始计费数量'))
            ->add(NumericFilter::new('maxAmount', '最大计费数量'))
            ->add(NumericFilter::new('price', '单价'))
            ->add(NumericFilter::new('freeQuota', '免费额度'))
            ->add(DateTimeFilter::new('startTime', '生效开始时间'))
            ->add(DateTimeFilter::new('endTime', '生效结束时间'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
            ->add(DateTimeFilter::new('updateTime', '更新时间'))
            ->add('createdBy')
            ->add('updatedBy')
        ;
    }
}
