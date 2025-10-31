<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests;

use CreditResourceBundle\CreditResourceBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(CreditResourceBundle::class)]
#[RunTestsInSeparateProcesses]
final class CreditResourceBundleTest extends AbstractBundleTestCase
{
}
