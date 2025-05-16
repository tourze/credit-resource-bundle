<?php

namespace CreditResourceBundle\Tests\Command;

use BizUserBundle\Repository\BizRoleRepository;
use BizUserBundle\Repository\BizUserRepository;
use CreditResourceBundle\Command\CreateResourceBillCommand;
use CreditResourceBundle\Repository\ResourcePriceRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateResourceBillCommandTest extends TestCase
{
    private CreateResourceBillCommand $command;
    private CommandTester $commandTester;
    private ResourcePriceRepository $resourcePriceRepository;
    private BizRoleRepository $roleRepository;
    private BizUserRepository $userRepository;
    private MessageBusInterface $messageBus;
    
    protected function setUp(): void
    {
        $this->resourcePriceRepository = $this->createMock(ResourcePriceRepository::class);
        $this->roleRepository = $this->createMock(BizRoleRepository::class);
        $this->userRepository = $this->createMock(BizUserRepository::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        
        $this->command = new CreateResourceBillCommand(
            $this->resourcePriceRepository,
            $this->roleRepository,
            $this->userRepository,
            $this->messageBus
        );
        
        $this->commandTester = new CommandTester($this->command);
    }
    
    /**
     * 测试命令名称和描述
     */
    public function testCommandConfiguration(): void
    {
        $reflectionClass = new \ReflectionClass(CreateResourceBillCommand::class);
        $attributes = $reflectionClass->getAttributes();
        
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'Symfony\Component\Console\Attribute\AsCommand') {
                $args = $attribute->getArguments();
                $this->assertEquals('billing:create-resource-bill', $args['name']);
                $this->assertEquals('创建付费账单并扣费', $args['description']);
                break;
            }
        }
    }
    
    /**
     * 测试没有可收费角色时提前返回
     */
    public function testExecuteReturnsFailureWhenNoBillableRoles(): void
    {
        $this->roleRepository->expects($this->once())
            ->method('findBy')
            ->with(['valid' => true, 'billable' => true])
            ->willReturn([]);
        
        $this->commandTester->execute([]);
        
        $this->assertStringContainsString('没有可以收费的角色，跳过处理', $this->commandTester->getDisplay());
        $this->assertEquals(1, $this->commandTester->getStatusCode()); // FAILURE
    }
    
    
    
    /**
     * 测试命令 cron 任务设置
     */
    public function testCronTaskAttribute(): void
    {
        $reflectionClass = new \ReflectionClass(CreateResourceBillCommand::class);
        $attributes = $reflectionClass->getAttributes();
        
        $hasCronAttribute = false;
        $cronExpression = null;
        
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'Tourze\Symfony\CronJob\Attribute\AsCronTask') {
                $hasCronAttribute = true;
                $cronExpression = $attribute->getArguments()[0];
                break;
            }
        }
        
        $this->assertTrue($hasCronAttribute, 'Command should have AsCronTask attribute');
        $this->assertEquals('1 * * * *', $cronExpression, 'Cron expression should be "1 * * * *"');
    }
} 