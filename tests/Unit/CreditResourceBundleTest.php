<?php

namespace CreditResourceBundle\Tests\Unit;

use CreditBundle\CreditBundle;
use CreditResourceBundle\CreditResourceBundle;
use PHPUnit\Framework\TestCase;
use Tourze\Symfony\CronJob\CronJobBundle;

class CreditResourceBundleTest extends TestCase
{
    public function testGetBundleDependencies(): void
    {
        $bundle = new CreditResourceBundle();
        $dependencies = CreditResourceBundle::getBundleDependencies();

        $this->assertArrayHasKey(CreditBundle::class, $dependencies);
        $this->assertArrayHasKey(CronJobBundle::class, $dependencies);
        $this->assertEquals(['all' => true], $dependencies[CreditBundle::class]);
        $this->assertEquals(['all' => true], $dependencies[CronJobBundle::class]);
    }

    public function testBundleInstanceCreation(): void
    {
        $bundle = new CreditResourceBundle();
        $this->assertInstanceOf(CreditResourceBundle::class, $bundle);
    }
}