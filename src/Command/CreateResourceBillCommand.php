<?php

namespace CreditResourceBundle\Command;

use BizUserBundle\Entity\BizRole;
use BizUserBundle\Repository\BizRoleRepository;
use BizUserBundle\Repository\BizUserRepository;
use Carbon\Carbon;
use CreditResourceBundle\Message\CreateResourceBillMessage;
use CreditResourceBundle\Repository\ResourcePriceRepository;
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
#[AsCommand(name: 'billing:create-resource-bill', description: '创建付费账单并扣费')]
class CreateResourceBillCommand extends Command
{
    public function __construct(
        private readonly ResourcePriceRepository $resourcePriceRepository,
        private readonly BizRoleRepository $roleRepository,
        private readonly BizUserRepository $userRepository,
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

        $time = Carbon::now()->startOfHour(); // 我们只做到小时级别的费用，所以就检测这个好了

        $users = $this->userRepository
            ->createQueryBuilder('a')
            ->where('a.assignRoles IN (:assignRoles)')
            ->setParameter('assignRoles', ArrayHelper::getColumn($roles, function (BizRole $role) {
                return $role->getId();
            }))
            ->getQuery()
            ->toIterable();

        $resourcePrices = $this->resourcePriceRepository->findBy(['valid' => true]);

        foreach ($users as $bizUser) {
            foreach ($resourcePrices as $resourcePrice) {
                $message = new CreateResourceBillMessage();
                $message->setTime($time->toDateTimeString());
                $message->setBizUserId($bizUser->getId());
                $message->setResourcePriceId($resourcePrice->getId());
                $this->messageBus->dispatch($message);
            }
        }

        return Command::SUCCESS;
    }
}
