<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Exception;

use CreditResourceBundle\Exception\ZeroUsageException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(ZeroUsageException::class)]
final class ZeroUsageExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(ZeroUsageException::class);

        throw new ZeroUsageException();
    }

    public function testExceptionWithMessage(): void
    {
        $message = '使用量为零: 无法创建零使用量账单';
        $exception = new ZeroUsageException($message);

        $this->assertSame($message, $exception->getMessage());
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $message = '使用量不能为零';
        $code = 400;
        $previous = new \Exception('Previous exception');

        $exception = new ZeroUsageException($message, $code, $previous);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionIsRuntimeException(): void
    {
        $exception = new ZeroUsageException();

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
