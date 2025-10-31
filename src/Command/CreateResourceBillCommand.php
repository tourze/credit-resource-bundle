<?php

declare(strict_types=1);

namespace CreditResourceBundle\Command;

use Carbon\CarbonImmutable;
use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Message\CreateResourceBillMessage;
use CreditResourceBundle\Repository\ResourcePriceRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\Symfony\CronJob\Attribute\AsCronTask;

/**
 * 我们每小时执行一次
 */
#[AsCronTask(expression: '1 * * * *')]
#[AsCommand(name: self::NAME, description: '创建付费账单并扣费')]
class CreateResourceBillCommand extends Command
{
    public const NAME = 'billing:create-resource-bill';

    public function __construct(
        private readonly ResourcePriceRepository $resourcePriceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                '仅测试运行，不实际创建账单'
            )
            ->addOption(
                'resource-price-id',
                null,
                InputOption::VALUE_REQUIRED,
                '仅处理指定的资源价格ID'
            )
            ->addOption(
                'time',
                null,
                InputOption::VALUE_REQUIRED,
                '指定账单时间（格式：Y-m-d H:i:s）'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRunOption = $input->getOption('dry-run');
        $isDryRun = is_bool($isDryRunOption) ? $isDryRunOption : false;

        $resourcePriceIdOption = $input->getOption('resource-price-id');
        $resourcePriceId = is_string($resourcePriceIdOption) ? $resourcePriceIdOption : null;

        $billTime = $this->determineBillTime($input);
        $io->info(sprintf('开始生成资源账单，账单时间：%s', $billTime->toDateTimeString()));

        $resourcePrices = $this->getResourcePrices($resourcePriceId, $billTime);
        if ([] === $resourcePrices) {
            $io->warning('没有找到有效的资源价格配置');

            return Command::SUCCESS;
        }

        $io->info(sprintf('找到 %d 个有效的资源价格配置', count($resourcePrices)));

        $totalMessages = $this->processResourcePrices($resourcePrices, $isDryRun, $io);

        $this->outputResult($isDryRun, $totalMessages, $io);

        return Command::SUCCESS;
    }

    private function determineBillTime(InputInterface $input): CarbonImmutable
    {
        $timeOption = $input->getOption('time');

        if (is_string($timeOption)) {
            return CarbonImmutable::parse($timeOption);
        }

        return CarbonImmutable::now()->startOfHour();
    }

    /**
     * @param ResourcePrice[] $resourcePrices
     */
    private function processResourcePrices(array $resourcePrices, bool $isDryRun, SymfonyStyle $io): int
    {
        $totalMessages = 0;

        foreach ($resourcePrices as $resourcePrice) {
            $totalMessages += $this->processResourcePrice($resourcePrice, $isDryRun, $io);
        }

        return $totalMessages;
    }

    private function processResourcePrice(ResourcePrice $resourcePrice, bool $isDryRun, SymfonyStyle $io): int
    {
        $io->section(sprintf('处理资源价格：%s', $resourcePrice->getTitle()));

        $users = $this->getUsersForResourcePrice($resourcePrice);
        if ([] === $users) {
            $io->note('没有找到需要计费的用户');

            return 0;
        }

        $io->info(sprintf('找到 %d 个需要计费的用户', count($users)));

        return $this->processUsers($users, $resourcePrice, $isDryRun, $io);
    }

    /**
     * @param array<string, mixed>[] $users
     */
    private function processUsers(array $users, ResourcePrice $resourcePrice, bool $isDryRun, SymfonyStyle $io): int
    {
        $messageCount = 0;

        foreach ($users as $userData) {
            if ($isDryRun) {
                $userId = is_scalar($userData['id']) ? (string) $userData['id'] : '';
                $io->writeln(sprintf(
                    '  [DRY-RUN] 将为用户 %s 创建 %s 的账单',
                    $userId,
                    $resourcePrice->getTitle()
                ));
                continue;
            }

            $this->createAndDispatchMessage($userData, $resourcePrice);
            ++$messageCount;
        }

        return $messageCount;
    }

    /**
     * @param array<string, mixed> $userData
     */
    private function createAndDispatchMessage(array $userData, ResourcePrice $resourcePrice): void
    {
        $message = new CreateResourceBillMessage();
        $message->setTime(CarbonImmutable::now()->startOfHour()->toDateTimeString());

        // 确保 bizUserId 是字符串类型
        $userId = $userData['id'];
        if (!is_string($userId) && !is_int($userId)) {
            throw new \InvalidArgumentException('User ID must be string or integer');
        }
        $bizUserId = is_string($userId) ? $userId : (string) $userId;
        $message->setBizUserId($bizUserId);

        $resourcePriceId = $resourcePrice->getId();
        if (null !== $resourcePriceId) {
            $message->setResourcePriceId($resourcePriceId);
        }

        $this->messageBus->dispatch($message);
    }

    private function outputResult(bool $isDryRun, int $totalMessages, SymfonyStyle $io): void
    {
        if ($isDryRun) {
            $io->success('测试运行完成');
        } else {
            $io->success(sprintf('账单生成任务完成，共发送 %d 个账单创建消息', $totalMessages));
        }
    }

    /**
     * 获取需要处理的资源价格配置.
     *
     * @return ResourcePrice[]
     */
    private function getResourcePrices(?string $resourcePriceId, CarbonImmutable $billTime): array
    {
        if (null !== $resourcePriceId) {
            $resourcePrice = $this->resourcePriceRepository->find($resourcePriceId);

            return null !== $resourcePrice && $resourcePrice->isInValidPeriod($billTime) ? [$resourcePrice] : [];
        }

        // 获取所有有效的资源价格配置
        $allPrices = $this->resourcePriceRepository->findBy(['valid' => true]);

        // 过滤出在有效期内的配置
        return array_filter($allPrices, function (ResourcePrice $price) use ($billTime): bool {
            return $price->isInValidPeriod($billTime);
        });
    }

    /**
     * 获取需要为指定资源价格计费的用户.
     */
    /**
     * @return array<string, mixed>[]
     */
    private function getUsersForResourcePrice(ResourcePrice $resourcePrice): array
    {
        $roles = $resourcePrice->getRoles();

        if ($roles->isEmpty()) {
            // 如果没有配置角色，则不对任何用户计费
            return [];
        }

        $roleIds = [];
        foreach ($roles as $role) {
            $roleIds[] = $role->getName();
        }

        // 使用原生 SQL 查询具有指定角色的用户
        $sql = '
            SELECT DISTINCT u.* 
            FROM biz_user u 
            WHERE EXISTS (
                SELECT 1 
                FROM JSONB_ARRAY_ELEMENTS_TEXT(u.assign_roles::jsonb) AS role_id 
                WHERE role_id::text = ANY(:roleIds)
            )
        ';

        $conn = $this->entityManager->getConnection();

        return $conn->executeQuery(
            $sql,
            ['roleIds' => $roleIds],
            ['roleIds' => ArrayParameterType::STRING]
        )->fetchAllAssociative();
    }
}
