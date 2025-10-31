<?php

namespace CreditResourceBundle\Tests\MessageHandler;

use CreditResourceBundle\Message\CreateResourceBillMessage;
use CreditResourceBundle\MessageHandler\CreateResourceBillHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CreateResourceBillHandler::class)]
#[RunTestsInSeparateProcesses]
final class CreateResourceBillHandlerTest extends AbstractIntegrationTestCase
{
    private CreateResourceBillHandler $handler;

    private CreateResourceBillMessage $message;

    protected function onSetUp(): void
    {
        $this->setUpDependencies();
    }

    private function setUpDependencies(): void
    {
        $this->handler = self::getService(CreateResourceBillHandler::class);

        $this->message = new CreateResourceBillMessage();
        $this->message->setBizUserId('nonexistent-user');
        $this->message->setResourcePriceId('nonexistent-price');
        $this->message->setTime('2023-04-01 12:00:00');
    }

    /**
     * 测试当用户未找到时抛出异常.
     */
    public function testInvokeThrowsExceptionWhenUserNotFound(): void
    {
        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('找不到用户信息');

        ($this->handler)($this->message);
    }

    /**
     * 测试当价格配置未找到时抛出异常.
     */
    public function testInvokeThrowsExceptionWhenPriceNotFound(): void
    {
        // 创建一个真实用户用于测试
        $user = $this->createNormalUser('test@example.com');

        $message = new CreateResourceBillMessage();
        $message->setBizUserId($user->getUserIdentifier());
        $message->setResourcePriceId('nonexistent-price');
        $message->setTime('2023-04-01 12:00:00');

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('找不到价格信息');

        ($this->handler)($message);
    }
}
