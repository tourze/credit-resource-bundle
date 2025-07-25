<?php

namespace CreditResourceBundle\MessageHandler;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use CreditBundle\Exception\TransactionException;
use CreditBundle\Service\AccountService;
use CreditBundle\Service\CurrencyService;
use CreditBundle\Service\TransactionService;
use CreditResourceBundle\Enum\FeeCycle;
use CreditResourceBundle\Message\CreateResourceBillMessage;
use CreditResourceBundle\Repository\ResourcePriceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

class CreateResourceBillHandler
{
    public function __construct(
        private readonly UserLoaderInterface $userLoader,
        private readonly ResourcePriceRepository $resourcePriceRepository,
        private readonly TransactionService $transactionService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly CurrencyService $currencyService,
        private readonly AccountService $accountService,
    ) {
    }

    public function __invoke(CreateResourceBillMessage $message): void
    {
        $bizUser = $this->userLoader->loadUserByIdentifier($message->getBizUserId());
        if ($bizUser === null) {
            throw new UnrecoverableMessageHandlingException('找不到用户信息');
        }

        $resourcePrice = $this->resourcePriceRepository->find($message->getResourcePriceId());
        if ($resourcePrice === null) {
            throw new UnrecoverableMessageHandlingException('找不到价格信息');
        }

        if ($resourcePrice->getPrice() <= 0) {
            // 价格不对，没必要继续
            return;
        }

        // 资源不存在，我们就不处理了
        if (!class_exists($resourcePrice->getResource())) {
            return;
        }

        // TODO 这里要查找出这个计费计划关联到的所有用户，通过角色关联？

        $time = CarbonImmutable::createFromTimeString($message->getTime());
        $repo = $this->entityManager->getRepository($resourcePrice->getResource());
        $amount = 0;

        switch ($resourcePrice->getCycle()) {
            case FeeCycle::TOTAL_BY_YEAR:
                // 每年1月的1号凌晨才做这个
                if (1 !== $time->month && 1 !== $time->day) {
                    return;
                }

                $amount = (int) $repo
                    ->createQueryBuilder('a')
                    ->select('COUNT(a.id)')
                    ->getQuery()
                    ->getSingleScalarResult();
                break;
            case FeeCycle::NEW_BY_YEAR:
                // 每年1月的1号凌晨才做这个
                if (1 !== $time->month && 1 !== $time->day) {
                    return;
                }

                $startTime = $time->subYear()->startOfYear();
                $endTime = $startTime->endOfYear();
                $amount = (int) $repo
                    ->createQueryBuilder('a')
                    ->select('COUNT(a.id)')
                    ->where('a.createTime BETWEEN :startTime AND :endTime')
                    ->setParameter('startTime', $startTime)
                    ->setParameter('endTime', $endTime)
                    ->getQuery()
                    ->getSingleScalarResult();
                break;

            case FeeCycle::TOTAL_BY_MONTH:
                // 每月的一号凌晨才做这个
                if (1 !== $time->day) {
                    return;
                }

                $amount = (int) $repo
                    ->createQueryBuilder('a')
                    ->select('COUNT(a.id)')
                    ->getQuery()
                    ->getSingleScalarResult();
                break;
            case FeeCycle::NEW_BY_MONTH:
                // 每月的一号凌晨才做这个
                if (1 !== $time->day) {
                    return;
                }

                // 这里特地没有 subMonth，是因为月份的操作，有一个 overflow 的问题
                $startTime = $time->subDay()->startOfMonth();
                $endTime = $startTime->endOfMonth();
                $amount = (int) $repo
                    ->createQueryBuilder('a')
                    ->select('COUNT(a.id)')
                    ->where('a.createTime BETWEEN :startTime AND :endTime')
                    ->setParameter('startTime', $startTime)
                    ->setParameter('endTime', $endTime)
                    ->getQuery()
                    ->getSingleScalarResult();
                break;

            case FeeCycle::TOTAL_BY_DAY:
                // 每日凌晨才做这个
                if (0 !== $time->hour) {
                    return;
                }

                $amount = (int) $repo
                    ->createQueryBuilder('a')
                    ->select('COUNT(a.id)')
                    ->getQuery()
                    ->getSingleScalarResult();
                break;
            case FeeCycle::NEW_BY_DAY:
                // 每日凌晨才做这个
                if (0 !== $time->hour) {
                    return;
                }

                $startTime = $time->subDay()->startOfDay();
                $endTime = $startTime->endOfDay();
                $amount = (int) $repo
                    ->createQueryBuilder('a')
                    ->select('COUNT(a.id)')
                    ->where('a.createTime BETWEEN :startTime AND :endTime')
                    ->setParameter('startTime', $startTime)
                    ->setParameter('endTime', $endTime)
                    ->getQuery()
                    ->getSingleScalarResult();
                break;

            case FeeCycle::TOTAL_BY_HOUR:
                // 每个小时的0分才做这个
                if (0 !== $time->minute) {
                    return;
                }

                $amount = (int) $repo
                    ->createQueryBuilder('a')
                    ->select('COUNT(a.id)')
                    ->getQuery()
                    ->getSingleScalarResult();
                break;
            case FeeCycle::NEW_BY_HOUR:
                // 每个小时的0分才做这个
                if (0 !== $time->minute) {
                    return;
                }

                $startTime = $time->subHour()->startOfHour();
                $endTime = $startTime->endOfHour();
                $amount = (int) $repo
                    ->createQueryBuilder('a')
                    ->select('COUNT(a.id)')
                    ->where('a.createTime BETWEEN :startTime AND :endTime')
                    ->setParameter('startTime', $startTime)
                    ->setParameter('endTime', $endTime)
                    ->getQuery()
                    ->getSingleScalarResult();
                break;
        }

        // 最小数量控制
        if ($resourcePrice->getMinAmount() > 0 && $resourcePrice->getMinAmount() > $amount) {
            $amount = $resourcePrice->getMinAmount();
        }

        // 最大数量控制
        if ($resourcePrice->getMaxAmount() > 0 && $resourcePrice->getMaxAmount() > $amount) {
            $amount = $resourcePrice->getMaxAmount();
        }

        // 没有数量，跳过吧
        if ($amount <= 0) {
            $this->logger->debug('账单为0，不用计算', [
                'user' => $bizUser,
            ]);

            return;
        }

        $currency = $resourcePrice->getCurrency();
        if ($currency === null) {
            $currency = $this->currencyService->ensureCurrencyByCode('CNY', '余额');
        }
        $account = $this->accountService->getAccountByUser($bizUser, $currency);

        // 开始计费
        // TODO 暂时没账单，我们直接扣除系统用户的预储值；

        // 5位小数
        $money = BigDecimal::of($amount)->multipliedBy($resourcePrice->getPrice())->toScale(5);
        try {
            // 我们在这里没做余额校验，意思就是等他超出余额
            $this->transactionService->decrease(
                'BILL-' . $resourcePrice->getId() . '-' . Uuid::v4()->toRfc4122(),
                $account,
                (float) $money->__toString(),
            );
        } catch (TransactionException $e) {
            $this->logger->error('计算资源账单时发生异常', [
                'money' => $money,
                'exception' => $e,
            ]);
        }
    }
}
