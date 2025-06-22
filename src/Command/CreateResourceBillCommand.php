<?php

namespace CreditResourceBundle\Command;

use BizUserBundle\Entity\BizRole;
use BizUserBundle\Repository\BizRoleRepository;
use Carbon\CarbonImmutable;
use CreditResourceBundle\Message\CreateResourceBillMessage;
use CreditResourceBundle\Repository\ResourcePriceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\Symfony\CronJob\Attribute\AsCronTask;
use Yiisoft\Arrays\ArrayHelper;

/**
 * 我们每小时执行一次
 */
#[AsCronTask('1 * * * *')]
#[AsCommand(name: self::NAME, description: '创建付费账单并扣费')]
class CreateResourceBillCommand extends Command
{
    public const NAME = 'billing:create-resource-bill';
    public function __construct(
        private readonly ResourcePriceRepository $resourcePriceRepository,
        private readonly BizRoleRepository $roleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $roles = $this->roleRepository->findBy([
            'valid' => true,
            'billable' => true,
        ]);
        if (empty($roles)) {
            $output->writeln('没有可以收费的角色，跳过处理');

            return Command::FAILURE;
        }

        $time = CarbonImmutable::now()->startOfHour(); // 我们只做到小时级别的费用，所以就检测这个好了

        $roleIds = ArrayHelper::getColumn($roles, function (BizRole $role) {
            return $role->getId();
        });
        
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
        $users = $conn->executeQuery($sql, ['roleIds' => $roleIds], ['roleIds' => \Doctrine\DBAL\ArrayParameterType::STRING])->fetchAllAssociative();

        $resourcePrices = $this->resourcePriceRepository->findBy(['valid' => true]);

        foreach ($users as $userData) {
            foreach ($resourcePrices as $resourcePrice) {
                $message = new CreateResourceBillMessage();
                $message->setTime($time->toDateTimeString());
                $message->setBizUserId($userData['id']);
                $message->setResourcePriceId($resourcePrice->getId());
                $this->messageBus->dispatch($message);
            }
        }

        return Command::SUCCESS;
    }
}
