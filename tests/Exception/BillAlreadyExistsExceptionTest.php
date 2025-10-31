<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Exception;

use CreditResourceBundle\Exception\BillAlreadyExistsException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(BillAlreadyExistsException::class)]
final class BillAlreadyExistsExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(BillAlreadyExistsException::class);

        throw new BillAlreadyExistsException();
    }

    public function testExceptionWithMessage(): void
    {
        $message = '账单已存在: BILL-123';
        $exception = new BillAlreadyExistsException($message);

        $this->assertSame($message, $exception->getMessage());
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $message = '账单已存在';
        $code = 409;
        $previous = new \Exception('Previous exception');

        $exception = new BillAlreadyExistsException($message, $code, $previous);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionIsRuntimeException(): void
    {
        $exception = new BillAlreadyExistsException();

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
