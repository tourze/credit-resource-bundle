<?php

declare(strict_types=1);

namespace CreditResourceBundle\MessageHandler;

use Carbon\CarbonImmutable;
use CreditResourceBundle\Message\CreateResourceBillMessage;
use CreditResourceBundle\Repository\ResourcePriceRepository;
use CreditResourceBundle\Service\BillService;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
#[WithMonologChannel(channel: 'credit_resource')]
final readonly class CreateResourceBillHandler
{
    public function __construct(
        private UserLoaderInterface $userLoader,
        private ResourcePriceRepository $resourcePriceRepository,
        private BillService $billService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateResourceBillMessage $message): void
    {
        // 加载用户
        $bizUser = $this->userLoader->loadUserByIdentifier($message->getBizUserId());
        if (null === $bizUser) {
            throw new UnrecoverableMessageHandlingException('找不到用户信息');
        }

        // 加载资源价格配置
        $resourcePrice = $this->resourcePriceRepository->find($message->getResourcePriceId());
        if (null === $resourcePrice) {
            throw new UnrecoverableMessageHandlingException('找不到价格信息');
        }

        // 解析账单时间
        $billTime = CarbonImmutable::createFromTimeString($message->getTime());

        try {
            // 生成账单
            $bill = $this->billService->generateBill($bizUser, $resourcePrice, $billTime);

            $this->logger->info('账单生成成功', [
                'bill_id' => $bill->getId(),
                'user_id' => $bizUser->getUserIdentifier(),
                'resource' => $resourcePrice->getTitle(),
                'bill_time' => $billTime->toDateTimeString(),
            ]);

            // 立即处理账单（执行扣费）
            $this->billService->processBill($bill);

            $this->logger->info('账单处理成功', [
                'bill_id' => $bill->getId(),
                'transaction_id' => $bill->getTransaction()?->getId(),
            ]);
        } catch (\RuntimeException $e) {
            // 一般性错误（如资源使用量为0、账单已存在等）
            $this->logger->info('账单生成跳过', [
                'user_id' => $bizUser->getUserIdentifier(),
                'resource_price_id' => $resourcePrice->getId(),
                'reason' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            // 其他错误
            $this->logger->error('账单处理失败', [
                'user_id' => $bizUser->getUserIdentifier(),
                'resource_price_id' => $resourcePrice->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 重新抛出异常，让消息队列决定是否重试
            throw $e;
        }
    }
}
