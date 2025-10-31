<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Exception;

use CreditResourceBundle\Exception\StrategyNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(StrategyNotFoundException::class)]
final class StrategyNotFoundExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(StrategyNotFoundException::class);

        throw new StrategyNotFoundException();
    }

    public function testExceptionWithMessage(): void
    {
        $message = '未找到计费策略: custom_strategy';
        $exception = new StrategyNotFoundException($message);

        $this->assertSame($message, $exception->getMessage());
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $message = '策略未注册';
        $code = 404;
        $previous = new \Exception('Previous exception');

        $exception = new StrategyNotFoundException($message, $code, $previous);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionIsRuntimeException(): void
    {
        $exception = new StrategyNotFoundException();

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
