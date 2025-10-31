<?php

namespace CreditResourceBundle\Tests\DependencyInjection;

use CreditResourceBundle\DependencyInjection\CreditResourceExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(CreditResourceExtension::class)]
final class CreditResourceExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private CreditResourceExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension = new CreditResourceExtension();
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.environment', 'test');
    }

    public function testGetAlias(): void
    {
        $this->assertEquals('credit_resource', $this->extension->getAlias());
    }
}
