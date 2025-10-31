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

Credit resource management bundle providing resource pricing and automatic billing functionality 
for Symfony applications. Supports flexible billing strategies with asynchronous bill 
processing via message queue.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Advanced Usage](#advanced-usage)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)
- [Authors](#authors)
- [Changelog](#changelog)

## Features

- Resource pricing management with multiple billing strategies
- Automatic billing functionality with customizable rules
- Flexible billing cycles (hourly, daily, monthly, yearly)
- Tiered pricing support for complex billing scenarios  
- Asynchronous bill processing via message queue
- Free quota and price ceiling/floor controls
- Integration with Symfony Messenger for scalable processing

## Requirements

- PHP >= 8.1
- Symfony >= 7.3
- Doctrine ORM >= 3.0

## Installation

```bash
composer require tourze/credit-resource-bundle
```

## Quick Start

### 1. Register Bundle

Register the bundle in your Symfony application:

```php
// config/bundles.php
return [
    // ...
    CreditResourceBundle\CreditResourceBundle::class => ['all' => true],
];
```

### 2. Configure Database

Run database migrations to create the required table structures:

```bash
php bin/console doctrine:migrations:migrate
```

### 3. Create Resource Price Configuration

```php
use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Enum\FeeCycle;

$resourcePrice = new ResourcePrice();
$resourcePrice->setTitle('VPN Usage');
$resourcePrice->setResource('App\Entity\VpnSession'); 
$resourcePrice->setCycle(FeeCycle::NEW_BY_HOUR);
$resourcePrice->setPrice('0.01');
$resourcePrice->setValid(true);

$entityManager->persist($resourcePrice);
$entityManager->flush();
```

### 4. Generate Bills

```bash
# Manual billing execution
php bin/console billing:create-resource-bill

# Or configure as cron job (runs at 1st minute of every hour)
# 1 * * * * php /path/to/project/bin/console billing:create-resource-bill
```

## Configuration

### Billing Strategies

The bundle supports multiple billing strategies:

#### Fixed Price Strategy
```php
// Simple fixed price per unit
$resourcePrice->setBillingStrategy(null); // Uses default fixed strategy
$resourcePrice->setPrice('1.00'); // $1 per unit
```

#### Tiered Price Strategy
```php
$resourcePrice->setBillingStrategy(TieredPriceStrategy::class);
$resourcePrice->setPriceRules([
    ['min' => 0, 'max' => 100, 'price' => '1.00'],     // First 100 units: $1 each
    ['min' => 100, 'max' => 1000, 'price' => '0.80'],  // Next 900 units: $0.80 each
    ['min' => 1000, 'max' => PHP_INT_MAX, 'price' => '0.50'] // 1000+: $0.50 each
]);
```

### Billing Cycles

Configure different billing cycles based on your needs:

```php
// Calculate new items added in the current hour
$resourcePrice->setCycle(FeeCycle::NEW_BY_HOUR);

// Calculate total items accumulated by end of day
$resourcePrice->setCycle(FeeCycle::TOTAL_BY_DAY);
```

### Price Controls

```php
// Set free quota (first 100 units free)
$resourcePrice->setFreeQuota(100);

// Set minimum charge (at least $5 even if usage is low)
$resourcePrice->setBottomPrice('5.00');

// Set maximum charge (never more than $100)
$resourcePrice->setTopPrice('100.00');
```

## Advanced Usage

### Custom Resource Usage Providers

Implement the `ResourceUsageProviderInterface` to define custom usage calculation:

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
        // Custom usage calculation logic
        return $this->calculateCustomUsage($user, $start, $end);
    }
    
    public function getUsageDetails(
        UserInterface $user,
        string $resourceType,
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): array {
        // Return detailed usage information
        return [];
    }
    
    public function getPriority(): int
    {
        return 0;
    }
}
```

### Custom Billing Strategies

Create custom billing strategies for complex pricing rules:

```php
use CreditResourceBundle\Interface\BillingStrategyInterface;
use CreditResourceBundle\Entity\ResourcePrice;

class VolumeDiscountStrategy implements BillingStrategyInterface
{
    public function calculate(ResourcePrice $price, int $usage, array $context = []): string
    {
        $basePrice = bcmul($price->getPrice(), (string) $usage, 5);
        
        // Apply volume discount
        if ($usage > 1000) {
            $discount = bcmul($basePrice, '0.10', 5); // 10% discount
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
        return 'Volume discount strategy with 10% discount for usage over 1000 units';
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

## Security

When using this bundle in production:

- Ensure proper access controls on admin interfaces
- Validate all pricing configurations before deployment
- Monitor billing logs for anomalies
- Use database transactions for critical billing operations
- Implement proper error handling and alerting

## Contributing

Issues and Pull Requests are welcome. Please ensure:

1. Code follows PSR-12 coding standards
2. All tests must pass (`./vendor/bin/phpunit`)
3. PHPStan analysis passes (`./vendor/bin/phpstan analyse`)
4. New features require corresponding tests
5. Update relevant documentation

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Authors

- Tourze Team

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.