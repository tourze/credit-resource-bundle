<?php

namespace CreditResourceBundle\Tests\MessageHandler;

use CreditBundle\Service\AccountService;
use CreditBundle\Service\CurrencyService;
use CreditBundle\Service\TransactionService;
use CreditResourceBundle\Message\CreateResourceBillMessage;
use CreditResourceBundle\MessageHandler\CreateResourceBillHandler;
use CreditResourceBundle\Repository\ResourcePriceRepository;
use CreditResourceBundle\Tests\TestDouble\TestUser;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class CreateResourceBillHandlerTest extends TestCase
{
    private CreateResourceBillHandler $handler;
    private UserLoaderInterface $userLoader;
    private ResourcePriceRepository $resourcePriceRepository;
    private TransactionService $transactionService;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private CurrencyService $currencyService;
    private AccountService $accountService;
    private CreateResourceBillMessage $message;
    private TestUser $testUser;
    
    protected function setUp(): void
    {
        $this->testUser = new TestUser('user123');
        
        $this->userLoader = $this->createMock(UserLoaderInterface::class);
        $this->resourcePriceRepository = $this->createMock(ResourcePriceRepository::class);
        $this->transactionService = $this->createMock(TransactionService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->currencyService = $this->createMock(CurrencyService::class);
        $this->accountService = $this->createMock(AccountService::class);
        
        $this->handler = new CreateResourceBillHandler(
            $this->userLoader,
            $this->resourcePriceRepository,
            $this->transactionService,
            $this->entityManager,
            $this->logger,
            $this->currencyService,
            $this->accountService
        );
        
        $this->message = new CreateResourceBillMessage();
        $this->message->setBizUserId('user123');
        $this->message->setResourcePriceId('price456');
        $this->message->setTime('2023-04-01 12:00:00');
    }
    
    /**
     * 测试当用户未找到时抛出异常
     */
    public function testInvokeThrowsExceptionWhenUserNotFound(): void
    {
        $this->userLoader->expects($this->once())
            ->method('loadUserByIdentifier')
            ->with('user123')
            ->willReturn(null);
            
        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('找不到用户信息');
        
        ($this->handler)($this->message);
    }
    
    /**
     * 测试当价格配置未找到时抛出异常
     */
    public function testInvokeThrowsExceptionWhenPriceNotFound(): void
    {
        $this->userLoader->expects($this->once())
            ->method('loadUserByIdentifier')
            ->with('user123')
            ->willReturn($this->testUser);
            
        $this->resourcePriceRepository->expects($this->once())
            ->method('find')
            ->with('price456')
            ->willReturn(null);
            
        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('找不到价格信息');
        
        ($this->handler)($this->message);
    }
} 