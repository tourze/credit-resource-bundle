<?php

namespace CreditResourceBundle\Tests\DependencyInjection;

use CreditResourceBundle\DependencyInjection\CreditResourceExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CreditResourceExtensionTest extends TestCase
{
    public function testLoadRegistersServices(): void
    {
        $container = new ContainerBuilder();
        $extension = new CreditResourceExtension();
        
        $extension->load([], $container);
        
        // 验证扩展加载了配置
        $this->assertNotEmpty($container->getDefinitions());
    }
    
    public function testGetAlias(): void
    {
        $extension = new CreditResourceExtension();
        $this->assertEquals('credit_resource', $extension->getAlias());
    }
}