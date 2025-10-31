<?php

namespace CreditResourceBundle;

use CreditBundle\CreditBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\Symfony\CronJob\CronJobBundle;

class CreditResourceBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            CreditBundle::class => ['all' => true],
            DoctrineBundle::class => ['all' => true],
            CronJobBundle::class => ['all' => true],
        ];
    }
}
