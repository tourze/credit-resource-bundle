<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Exception;

use CreditResourceBundle\Exception\ProviderNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(ProviderNotFoundException::class)]
final class ProviderNotFoundExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(ProviderNotFoundException::class);

        throw new ProviderNotFoundException();
    }

    public function testExceptionWithMessage(): void
    {
        $message = '未找到提供者: custom_provider';
        $exception = new ProviderNotFoundException($message);

        $this->assertSame($message, $exception->getMessage());
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $message = '提供者未注册';
        $code = 404;
        $previous = new \Exception('Previous exception');

        $exception = new ProviderNotFoundException($message, $code, $previous);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionIsRuntimeException(): void
    {
        $exception = new ProviderNotFoundException();

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
