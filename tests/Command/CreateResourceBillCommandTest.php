<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Command;

use CreditResourceBundle\Command\CreateResourceBillCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(CreateResourceBillCommand::class)]
#[RunTestsInSeparateProcesses]
final class CreateResourceBillCommandTest extends AbstractCommandTestCase
{
    private CreateResourceBillCommand $command;

    private CommandTester $commandTester;

    protected function onSetUp(): void
    {
        $this->command = self::getService(CreateResourceBillCommand::class);
        $this->commandTester = new CommandTester($this->command);
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    /**
     * 测试命令名称和描述.
     */
    public function testCommandConfiguration(): void
    {
        $reflectionClass = new \ReflectionClass(CreateResourceBillCommand::class);
        $attributes = $reflectionClass->getAttributes();

        foreach ($attributes as $attribute) {
            if ('Symfony\Component\Console\Attribute\AsCommand' === $attribute->getName()) {
                $args = $attribute->getArguments();
                $this->assertEquals('billing:create-resource-bill', $args['name']);
                $this->assertEquals('创建付费账单并扣费', $args['description']);
                break;
            }
        }
    }

    /**
     * 测试命令 cron 任务设置.
     */
    public function testCronTaskAttribute(): void
    {
        $reflectionClass = new \ReflectionClass(CreateResourceBillCommand::class);
        $attributes = $reflectionClass->getAttributes();

        $hasCronAttribute = false;
        $cronExpression = null;

        foreach ($attributes as $attribute) {
            if ('Tourze\Symfony\CronJob\Attribute\AsCronTask' === $attribute->getName()) {
                $hasCronAttribute = true;
                $args = $attribute->getArguments();
                $cronExpression = $args['expression'] ?? $args[0] ?? null;
                break;
            }
        }

        $this->assertTrue($hasCronAttribute, 'Command should have AsCronTask attribute');
        $this->assertEquals('1 * * * *', $cronExpression, 'Cron expression should be "1 * * * *"');
    }

    /**
     * 测试命令执行（正常情况，有资源价格配置）.
     */
    public function testCommandExecuteWithResourcePrices(): void
    {
        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('找到 3 个有效的资源价格配置', $this->commandTester->getDisplay());
        $this->assertStringContainsString('账单生成任务完成，共发送 0 个账单创建消息', $this->commandTester->getDisplay());
    }

    /**
     * 测试命令的 dry-run 选项.
     */
    public function testCommandExecuteWithDryRun(): void
    {
        $exitCode = $this->commandTester->execute(['--dry-run' => true]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
    }

    /**
     * 测试命令的资源价格ID选项.
     */
    public function testCommandExecuteWithResourcePriceId(): void
    {
        $exitCode = $this->commandTester->execute(['--resource-price-id' => '123']);

        $this->assertEquals(Command::SUCCESS, $exitCode);
    }

    /**
     * 测试 --dry-run 选项.
     */
    public function testOptionDryRun(): void
    {
        $exitCode = $this->commandTester->execute(['--dry-run' => true]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('测试运行完成', $this->commandTester->getDisplay());
    }

    /**
     * 测试 --resource-price-id 选项.
     */
    public function testOptionResourcePriceId(): void
    {
        $exitCode = $this->commandTester->execute(['--resource-price-id' => '456']);

        $this->assertEquals(Command::SUCCESS, $exitCode);
    }

    /**
     * 测试 --time 选项.
     */
    public function testOptionTime(): void
    {
        $exitCode = $this->commandTester->execute(['--time' => '2024-01-01 10:00:00']);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('账单时间：2024-01-01 10:00:00', $this->commandTester->getDisplay());
    }
}
