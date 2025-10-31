<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Exception;

use CreditResourceBundle\Exception\InvalidBillStateException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidBillStateException::class)]
final class InvalidBillStateExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(InvalidBillStateException::class);

        throw new InvalidBillStateException();
    }

    public function testExceptionWithMessage(): void
    {
        $message = '账单状态无效: 已支付账单无法取消';
        $exception = new InvalidBillStateException($message);

        $this->assertSame($message, $exception->getMessage());
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $message = '无效的状态转换';
        $code = 400;
        $previous = new \Exception('Previous exception');

        $exception = new InvalidBillStateException($message, $code, $previous);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionIsRuntimeException(): void
    {
        $exception = new InvalidBillStateException();

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
