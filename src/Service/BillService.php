<?php

declare(strict_types=1);

namespace CreditResourceBundle\Service;

use CreditBundle\Service\AccountService;
use CreditBundle\Service\TransactionService;
use CreditResourceBundle\Entity\ResourceBill;
use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Enum\BillStatus;
use CreditResourceBundle\Exception\BillAlreadyExistsException;
use CreditResourceBundle\Exception\InvalidBillStateException;
use CreditResourceBundle\Exception\StrategyNotFoundException;
use CreditResourceBundle\Exception\ZeroUsageException;
use CreditResourceBundle\Interface\BillingStrategyInterface;
use CreditResourceBundle\Provider\EntityResourceUsageProvider;
use CreditResourceBundle\Repository\ResourceBillRepository;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 账单管理服务
 *
 * 负责账单的生成、处理、查询等核心功能
 */
#[WithMonologChannel(channel: 'credit_resource')]
class BillService
{
    /**
     * @var BillingStrategyInterface[]
     */
    private array $strategies;

    /**
     * @param iterable<BillingStrategyInterface> $strategies
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ResourceBillRepository $billRepository,
        private readonly ResourceUsageService $usageService,
        private readonly AccountService $accountService,
        private readonly TransactionService $transactionService,
        private readonly EntityResourceUsageProvider $entityUsageProvider,
        private readonly LoggerInterface $logger,
        #[AutowireIterator(tag: 'credit_resource.billing_strategy')]
        iterable $strategies,
    ) {
        $this->strategies = [];
        foreach ($strategies as $strategy) {
            $this->strategies[] = $strategy;
        }

        // 按优先级排序
        usort($this->strategies, function ($a, $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
    }

    /**
     * 生成账单.
     *
     * @param UserInterface      $user          用户
     * @param ResourcePrice      $resourcePrice 资源价格配置
     * @param \DateTimeInterface $billTime      账单时间
     *
     * @return ResourceBill 生成的账单
     *
     * @throws \Exception
     */
    public function generateBill(
        UserInterface $user,
        ResourcePrice $resourcePrice,
        \DateTimeInterface $billTime,
    ): ResourceBill {
        // 计算统计周期
        $cycle = $resourcePrice->getCycle();
        if (null === $cycle) {
            throw new \InvalidArgumentException('资源价格配置的计费周期不能为空');
        }

        $timeRange = $this->entityUsageProvider->getTimeRangeForCycle(
            $cycle,
            $billTime
        );

        // 检查是否已存在账单（防止重复生成）
        $resourcePriceId = $resourcePrice->getId();
        if (null === $resourcePriceId) {
            throw new \InvalidArgumentException('资源价格配置的ID不能为空');
        }

        if ($this->billRepository->existsBill(
            $user,
            $resourcePriceId,
            $timeRange['start'],
            $timeRange['end']
        )) {
            throw new BillAlreadyExistsException(sprintf('账单已存在：用户 %s，资源 %s，周期 %s - %s', $user->getUserIdentifier(), $resourcePrice->getTitle(), $timeRange['start']->format('Y-m-d H:i:s'), $timeRange['end']->format('Y-m-d H:i:s')));
        }

        // 获取资源使用量
        $resource = $resourcePrice->getResource();
        if (null === $resource) {
            throw new \InvalidArgumentException('资源价格配置的资源类型不能为空');
        }

        $usage = $this->usageService->getUsage(
            $user,
            $resource,
            $timeRange['start'],
            $timeRange['end']
        );

        // 如果使用量为0且不强制计费，则不生成账单
        if (0 === $usage && !$this->shouldBillZeroUsage($resourcePrice)) {
            throw new ZeroUsageException('资源使用量为0，跳过账单生成');
        }

        // 获取使用详情
        $usageDetails = $this->usageService->getUsageDetails(
            $user,
            $resource,
            $timeRange['start'],
            $timeRange['end']
        );

        // 计算费用
        $strategy = $this->findStrategy($resourcePrice);
        $totalPrice = $strategy->calculate($resourcePrice, $usage);

        // 考虑免费额度
        $actualPrice = $this->applyFreeQuota($resourcePrice, $usage, $totalPrice);

        // 获取用户账户
        $currency = $resourcePrice->getCurrency();
        if (null === $currency) {
            throw new \InvalidArgumentException('资源价格配置的币种不能为空');
        }

        $account = $this->accountService->getAccountByUser(
            $user,
            $currency
        );

        // 创建账单
        $bill = new ResourceBill();
        $bill->setUser($user);
        $bill->setResourcePrice($resourcePrice);
        $bill->setAccount($account);
        $bill->setBillTime(new \DateTimeImmutable($billTime->format('Y-m-d H:i:s')));
        $bill->setPeriodStart(new \DateTimeImmutable($timeRange['start']->format('Y-m-d H:i:s')));
        $bill->setPeriodEnd(new \DateTimeImmutable($timeRange['end']->format('Y-m-d H:i:s')));
        $bill->setUsage($usage);
        $bill->setUsageDetails($usageDetails);

        $price = $resourcePrice->getPrice();
        if (null === $price) {
            throw new \InvalidArgumentException('资源价格配置的单价不能为空');
        }

        $bill->setUnitPrice($price);
        $bill->setTotalPrice($totalPrice);
        $bill->setActualPrice($actualPrice);
        $bill->setStatus(BillStatus::PENDING);

        $this->entityManager->persist($bill);
        $this->entityManager->flush();

        $this->logger->info('账单生成成功', [
            'bill_id' => $bill->getId(),
            'user_id' => $user->getUserIdentifier(),
            'resource' => $resourcePrice->getTitle(),
            'usage' => $usage,
            'actual_price' => $actualPrice,
        ]);

        return $bill;
    }

    /**
     * 检查是否应该为零使用量生成账单.
     *
     * @param ResourcePrice $resourcePrice 资源价格配置
     */
    private function shouldBillZeroUsage(ResourcePrice $resourcePrice): bool
    {
        // 如果有保底价，则需要生成账单
        $bottomPrice = $resourcePrice->getBottomPrice();
        if (null === $bottomPrice) {
            return false;
        }

        if (!is_numeric($bottomPrice)) {
            throw new \InvalidArgumentException('资源价格配置的保底价格式无效');
        }

        return bccomp($bottomPrice, '0', 5) > 0;
    }

    /**
     * 获取适用的计费策略（公开方法用于测试）.
     *
     * @param ResourcePrice $resourcePrice 资源价格配置
     *
     * @throws StrategyNotFoundException
     */
    public function getStrategy(ResourcePrice $resourcePrice): BillingStrategyInterface
    {
        return $this->findStrategy($resourcePrice);
    }

    /**
     * 查找适用的计费策略.
     *
     * @param ResourcePrice $resourcePrice 资源价格配置
     *
     * @throws StrategyNotFoundException
     */
    private function findStrategy(ResourcePrice $resourcePrice): BillingStrategyInterface
    {
        // 如果配置了特定策略，尝试使用
        $configuredStrategy = $this->findConfiguredStrategy($resourcePrice);
        if (null !== $configuredStrategy) {
            return $configuredStrategy;
        }

        // 否则使用第一个支持的策略
        return $this->findSupportingStrategy($resourcePrice);
    }

    private function findConfiguredStrategy(ResourcePrice $resourcePrice): ?BillingStrategyInterface
    {
        if (null === $resourcePrice->getBillingStrategy()) {
            return null;
        }

        foreach ($this->strategies as $strategy) {
            if (get_class($strategy) === $resourcePrice->getBillingStrategy()
                && $strategy->supports($resourcePrice)) {
                return $strategy;
            }
        }

        return null;
    }

    private function findSupportingStrategy(ResourcePrice $resourcePrice): BillingStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($resourcePrice)) {
                return $strategy;
            }
        }

        throw new StrategyNotFoundException(sprintf('没有找到支持资源价格 %s 的计费策略', $resourcePrice->getTitle()));
    }

    /**
     * 应用免费额度.
     *
     * @param ResourcePrice $resourcePrice 资源价格配置
     * @param int           $usage         使用量
     * @param string        $totalPrice    总价格
     *
     * @return string 实际价格
     */
    private function applyFreeQuota(
        ResourcePrice $resourcePrice,
        int $usage,
        string $totalPrice,
    ): string {
        $freeQuota = $resourcePrice->getFreeQuota() ?? 0;

        // 如果没有免费额度，返回原价格
        if ($freeQuota <= 0) {
            return $totalPrice;
        }

        // 如果使用量不超过免费额度，完全免费
        if ($usage <= $freeQuota) {
            return '0';
        }

        // 计算超出免费额度部分的费用
        $billableUsage = $usage - $freeQuota;
        $actualPrice = $this->calculateActualPrice($resourcePrice, $billableUsage);

        // 应用价格限制
        return $this->applyPriceLimits($resourcePrice, $actualPrice);
    }

    /**
     * 计算实际价格
     */
    private function calculateActualPrice(ResourcePrice $resourcePrice, int $billableUsage): string
    {
        $unitPrice = $resourcePrice->getPrice();
        if (null === $unitPrice) {
            throw new \InvalidArgumentException('资源价格配置的单价不能为空');
        }

        if (!is_numeric($unitPrice)) {
            throw new \InvalidArgumentException('资源价格配置的单价格式无效');
        }

        return bcmul($unitPrice, (string) $billableUsage, 5);
    }

    /**
     * 应用价格限制（封顶价和保底价）.
     */
    private function applyPriceLimits(ResourcePrice $resourcePrice, string $actualPrice): string
    {
        if (!is_numeric($actualPrice)) {
            throw new \InvalidArgumentException('计算出的实际价格格式无效');
        }

        // 应用封顶价
        $topPrice = $resourcePrice->getTopPrice();
        if (null !== $topPrice) {
            if (!is_numeric($topPrice)) {
                throw new \InvalidArgumentException('资源价格配置的封顶价格式无效');
            }
            if (bccomp($actualPrice, $topPrice, 5) > 0) {
                return $topPrice;
            }
        }

        // 应用保底价
        $bottomPrice = $resourcePrice->getBottomPrice();
        if (null !== $bottomPrice) {
            if (!is_numeric($bottomPrice)) {
                throw new \InvalidArgumentException('资源价格配置的保底价格式无效');
            }
            if (bccomp($actualPrice, $bottomPrice, 5) < 0) {
                return $bottomPrice;
            }
        }

        return $actualPrice;
    }

    /**
     * 查询账单.
     *
     * @param array<string, mixed> $criteria 查询条件
     *
     * @return ResourceBill[]
     */
    public function queryBills(array $criteria): array
    {
        return $this->billRepository->findBy($criteria);
    }

    /**
     * 获取用户账单汇总.
     *
     * @param UserInterface      $user  用户
     * @param \DateTimeInterface $start 开始时间
     * @param \DateTimeInterface $end   结束时间
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBillSummary(
        UserInterface $user,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): array {
        return $this->billRepository->getUserBillSummary($user, $start, $end);
    }

    /**
     * 重试失败的账单.
     *
     * @param ResourceBill $bill 账单
     *
     * @throws \Exception
     */
    public function retryBill(ResourceBill $bill): void
    {
        if (BillStatus::FAILED !== $bill->getStatus()) {
            throw new InvalidBillStateException('只能重试失败的账单');
        }

        // 重置状态为待处理
        $bill->setStatus(BillStatus::PENDING);
        $bill->setFailureReason(null);
        $this->entityManager->flush();

        // 重新处理账单
        $this->processBill($bill);
    }

    /**
     * 处理账单（执行扣费）.
     *
     * @param ResourceBill $bill 账单
     *
     * @throws \Exception
     */
    public function processBill(ResourceBill $bill): void
    {
        // 检查账单状态
        $currentStatus = $bill->getStatus();
        if (null === $currentStatus || !$bill->canTransitionTo(BillStatus::PROCESSING)) {
            $statusValue = null !== $currentStatus ? $currentStatus->value : 'null';
            throw new InvalidBillStateException(sprintf('账单 %s 当前状态 %s 不能转换到处理中状态', $bill->getId(), $statusValue));
        }

        // 更新账单状态为处理中
        $bill->setStatus(BillStatus::PROCESSING);
        $this->entityManager->flush();

        try {
            // 如果实际费用为0，直接标记为已支付
            $actualPrice = $bill->getActualPrice();
            if (null === $actualPrice || '0' === $actualPrice || (is_numeric($actualPrice) && 0 === bccomp($actualPrice, '0', 5))) {
                $bill->setStatus(BillStatus::PAID);
                $bill->setPaidAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                $this->logger->info('账单费用为0，直接标记为已支付', [
                    'bill_id' => $bill->getId(),
                ]);

                return;
            }

            // 执行扣费
            $account = $bill->getAccount();
            if (null === $account) {
                throw new \InvalidArgumentException('账单关联的账户不能为空');
            }

            $this->transactionService->decrease(
                'BILL_' . $bill->getId(),
                $account,
                (float) $actualPrice,
                sprintf(
                    '%s - %s至%s',
                    $bill->getResourcePrice()?->getTitle() ?? '未知资源',
                    $bill->getPeriodStart()?->format('Y-m-d H:i:s') ?? '未知时间',
                    $bill->getPeriodEnd()?->format('Y-m-d H:i:s') ?? '未知时间'
                )
            );

            // decrease 方法是 void，不返回值
            $transaction = null;

            // 更新账单状态为已支付
            $bill->setStatus(BillStatus::PAID);
            $bill->setPaidAt(new \DateTimeImmutable());
            $bill->setTransaction($transaction);
            $this->entityManager->flush();

            $this->logger->info('账单扣费成功', [
                'bill_id' => $bill->getId(),
                'amount' => $bill->getActualPrice(),
            ]);
        } catch (\Exception $e) {
            // 更新账单状态为失败
            $bill->setStatus(BillStatus::FAILED);
            $bill->setFailureReason($e->getMessage());
            $this->entityManager->flush();

            $this->logger->error('账单扣费失败', [
                'bill_id' => $bill->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 取消账单.
     *
     * @param ResourceBill $bill   账单
     * @param string       $reason 取消原因
     *
     * @throws \Exception
     */
    public function cancelBill(ResourceBill $bill, string $reason): void
    {
        $currentStatus = $bill->getStatus();
        if (null === $currentStatus || !$bill->canTransitionTo(BillStatus::CANCELLED)) {
            $statusValue = null !== $currentStatus ? $currentStatus->value : 'null';
            throw new InvalidBillStateException(sprintf('账单 %s 当前状态 %s 不能取消', $bill->getId(), $statusValue));
        }

        $bill->setStatus(BillStatus::CANCELLED);
        $bill->setFailureReason($reason);
        $this->entityManager->flush();

        $this->logger->info('账单已取消', [
            'bill_id' => $bill->getId(),
            'reason' => $reason,
        ]);
    }
}
