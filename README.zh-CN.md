# credit-resource-bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/credit-resource-bundle.svg?style=flat-square)]
(https://packagist.org/packages/tourze/credit-resource-bundle)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/tests.yml
?branch=master&style=flat-square)](https://github.com/tourze/php-monorepo/actions)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/php-monorepo?style=flat-square)]
(https://codecov.io/gh/tourze/php-monorepo)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/credit-resource-bundle.svg?style=flat-square)]
(https://packagist.org/packages/tourze/credit-resource-bundle)
[![License](https://img.shields.io/packagist/l/tourze/credit-resource-bundle.svg?style=flat-square)]
(https://packagist.org/packages/tourze/credit-resource-bundle)

信用资源管理包，为 Symfony 应用提供资源定价和自动计费功能。支持灵活的计费策略，
通过消息队列异步处理账单。

## 目录

- [功能特性](#功能特性)
- [系统要求](#系统要求)
- [安装](#安装)
- [快速开始](#快速开始)
- [配置说明](#配置说明)
- [高级用法](#高级用法)
- [安全性](#安全性)
- [贡献指南](#贡献指南)
- [许可证](#许可证)
- [作者](#作者)
- [版本历史](#版本历史)

## 功能特性

- 支持多种计费策略的资源定价管理
- 可自定义规则的自动计费功能
- 灵活的计费周期（按小时、按日、按月、按年）
- 阶梯定价支持复杂的计费场景
- 消息队列异步处理账单
- 免费额度和价格封顶/保底控制
- 与 Symfony Messenger 集成，支持可扩展处理

## 系统要求

- PHP >= 8.1
- Symfony >= 7.3
- Doctrine ORM >= 3.0

## 安装

```bash
composer require tourze/credit-resource-bundle
```

## 快速开始

### 1. 注册 Bundle

在你的 Symfony 应用中注册 Bundle：

```php
// config/bundles.php
return [
    // ...
    CreditResourceBundle\CreditResourceBundle::class => ['all' => true],
];
```

### 2. 配置数据库

运行数据库迁移以创建所需的表结构：

```bash
php bin/console doctrine:migrations:migrate
```

### 3. 创建资源价格配置

```php
use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Enum\FeeCycle;

$resourcePrice = new ResourcePrice();
$resourcePrice->setTitle('VPN 使用量');
$resourcePrice->setResource('App\Entity\VpnSession'); 
$resourcePrice->setCycle(FeeCycle::NEW_BY_HOUR);
$resourcePrice->setPrice('0.01');
$resourcePrice->setValid(true);

$entityManager->persist($resourcePrice);
$entityManager->flush();
```

### 4. 生成账单

```bash
# 手动执行计费
php bin/console billing:create-resource-bill

# 或配置为定时任务（每小时第1分钟执行）
# 1 * * * * php /path/to/project/bin/console billing:create-resource-bill
```

## 配置说明

### 计费策略

Bundle 支持多种计费策略：

#### 固定价格策略
```php
// 简单的按单位固定价格
$resourcePrice->setBillingStrategy(null); // 使用默认固定策略
$resourcePrice->setPrice('1.00'); // 每单位 1 元
```

#### 阶梯价格策略
```php
$resourcePrice->setBillingStrategy(TieredPriceStrategy::class);
$resourcePrice->setPriceRules([
    ['min' => 0, 'max' => 100, 'price' => '1.00'],     // 前100个单位：每个1元
    ['min' => 100, 'max' => 1000, 'price' => '0.80'],  // 接下来900个：每个0.8元
    ['min' => 1000, 'max' => PHP_INT_MAX, 'price' => '0.50'] // 1000个以上：每个0.5元
]);
```

### 计费周期

根据需要配置不同的计费周期：

```php
// 计算当前小时内新增的项目
$resourcePrice->setCycle(FeeCycle::NEW_BY_HOUR);

// 计算到当日结束时累计的总项目
$resourcePrice->setCycle(FeeCycle::TOTAL_BY_DAY);
```

### 价格控制

```php
// 设置免费额度（前100个单位免费）
$resourcePrice->setFreeQuota(100);

// 设置最低收费（即使使用量很少也至少收费5元）
$resourcePrice->setBottomPrice('5.00');

// 设置最高收费（永远不超过100元）
$resourcePrice->setTopPrice('100.00');
```

## 高级用法

### 自定义资源使用量提供者

实现 `ResourceUsageProviderInterface` 来定义自定义的使用量计算：

```php
use CreditResourceBundle\Interface\ResourceUsageProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class CustomUsageProvider implements ResourceUsageProviderInterface
{
    public function supports(string $resourceType): bool
    {
        return $resourceType === 'custom_resource';
    }
    
    public function getUsage(
        UserInterface $user,
        string $resourceType,
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): int {
        // 自定义使用量计算逻辑
        return $this->calculateCustomUsage($user, $start, $end);
    }
    
    public function getUsageDetails(
        UserInterface $user,
        string $resourceType,
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): array {
        // 返回详细的使用量信息
        return [];
    }
    
    public function getPriority(): int
    {
        return 0;
    }
}
```

### 自定义计费策略

为复杂的定价规则创建自定义计费策略：

```php
use CreditResourceBundle\Interface\BillingStrategyInterface;
use CreditResourceBundle\Entity\ResourcePrice;

class VolumeDiscountStrategy implements BillingStrategyInterface
{
    public function calculate(ResourcePrice $price, int $usage, array $context = []): string
    {
        $basePrice = bcmul($price->getPrice(), (string) $usage, 5);
        
        // 应用批量折扣
        if ($usage > 1000) {
            $discount = bcmul($basePrice, '0.10', 5); // 10% 折扣
            return bcsub($basePrice, $discount, 5);
        }
        
        return $basePrice;
    }
    
    public function supports(ResourcePrice $price): bool
    {
        return true;
    }
    
    public function getName(): string
    {
        return 'volume_discount';
    }
    
    public function getDescription(): string
    {
        return '批量折扣策略，超过1000个单位享受10%折扣';
    }
    
    public function validateConfiguration(ResourcePrice $price): array
    {
        return [];
    }
    
    public function getPriority(): int
    {
        return 0;
    }
}
```

## 安全性

在生产环境中使用此 Bundle 时：

- 确保管理界面有适当的访问控制
- 在部署前验证所有定价配置
- 监控计费日志中的异常情况
- 对关键计费操作使用数据库事务
- 实现适当的错误处理和告警

## 贡献指南

欢迎提交 Issue 和 Pull Request。请确保：

1. 代码遵循 PSR-12 编码规范
2. 所有测试必须通过（`./vendor/bin/phpunit`）
3. PHPStan 分析通过（`./vendor/bin/phpstan analyse`）
4. 新功能需要编写相应的测试
5. 更新相关文档

## 许可证

本项目采用 MIT 许可证。详情请参阅 [LICENSE](LICENSE) 文件。

## 作者

- Tourze Team

## 版本历史

请查看 [CHANGELOG.md](CHANGELOG.md) 了解版本更新记录。
