<?php

declare(strict_types=1);

namespace CreditResourceBundle\Entity;

use CreditBundle\Entity\Account;
use CreditBundle\Entity\Transaction;
use CreditResourceBundle\Enum\BillStatus;
use CreditResourceBundle\Repository\ResourceBillRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;

#[ORM\Entity(repositoryClass: ResourceBillRepository::class)]
#[ORM\Table(name: 'credit_resource_bill', options: ['comment' => '资源账单'])]
#[ORM\Index(name: 'credit_resource_bill_idx_user_status', columns: ['user_id', 'status'])]
class ResourceBill implements \Stringable
{
    use SnowflakeKeyAware;
    use TimestampableAware;
    use BlameableAware;

    #[ORM\ManyToOne(targetEntity: UserInterface::class)]
    #[ORM\JoinColumn(nullable: false, options: ['comment' => '用户'])]
    private ?UserInterface $user = null;

    #[ORM\ManyToOne(targetEntity: ResourcePrice::class)]
    #[ORM\JoinColumn(nullable: false, options: ['comment' => '资源价格配置'])]
    private ?ResourcePrice $resourcePrice = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false, options: ['comment' => '扣费账户'])]
    private ?Account $account = null;

    #[IndexColumn]
    #[TrackColumn]
    #[Assert\NotNull]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '账单时间'])]
    private ?\DateTimeImmutable $billTime = null;

    #[TrackColumn]
    #[Assert\NotNull]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '统计周期开始时间'])]
    private ?\DateTimeImmutable $periodStart = null;

    #[TrackColumn]
    #[Assert\NotNull]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '统计周期结束时间'])]
    private ?\DateTimeImmutable $periodEnd = null;

    #[TrackColumn]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(value: 0)]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '使用量'])]
    private ?int $usage = null;

    /**
     * @var array<string, mixed>|null
     */
    #[TrackColumn]
    #[Assert\Type(type: 'array')]
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '使用详情'])]
    private ?array $usageDetails = null;

    #[TrackColumn]
    #[Assert\NotNull]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,5})?$/', message: '单价格式不正确')]
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 5, options: ['comment' => '单价'])]
    private ?string $unitPrice = null;

    #[TrackColumn]
    #[Assert\NotNull]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,5})?$/', message: '总价格式不正确')]
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 5, options: ['comment' => '总价'])]
    private ?string $totalPrice = null;

    #[TrackColumn]
    #[Assert\NotNull]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,5})?$/', message: '实际扣费金额格式不正确')]
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 5, options: ['comment' => '实际扣费金额'])]
    private ?string $actualPrice = null;

    #[IndexColumn]
    #[TrackColumn]
    #[Assert\NotNull]
    #[Assert\Choice(callback: [BillStatus::class, 'cases'], message: '无效的账单状态')]
    #[ORM\Column(length: 30, enumType: BillStatus::class, options: ['comment' => '账单状态'])]
    private ?BillStatus $status = null;

    #[ORM\ManyToOne(targetEntity: Transaction::class)]
    #[ORM\JoinColumn(nullable: true, options: ['comment' => '关联交易记录'])]
    private ?Transaction $transaction = null;

    #[TrackColumn]
    #[Assert\Length(max: 1000)]
    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '失败原因'])]
    private ?string $failureReason = null;

    #[TrackColumn]
    #[Assert\Type(type: '\DateTimeImmutable')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '支付时间'])]
    private ?\DateTimeImmutable $paidAt = null;

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): void
    {
        $this->user = $user;
    }

    public function getResourcePrice(): ?ResourcePrice
    {
        return $this->resourcePrice;
    }

    public function setResourcePrice(?ResourcePrice $resourcePrice): void
    {
        $this->resourcePrice = $resourcePrice;
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): void
    {
        $this->account = $account;
    }

    public function getBillTime(): ?\DateTimeImmutable
    {
        return $this->billTime;
    }

    public function setBillTime(\DateTimeImmutable $billTime): void
    {
        $this->billTime = $billTime;
    }

    public function getPeriodStart(): ?\DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function setPeriodStart(\DateTimeImmutable $periodStart): void
    {
        $this->periodStart = $periodStart;
    }

    public function getPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(\DateTimeImmutable $periodEnd): void
    {
        $this->periodEnd = $periodEnd;
    }

    public function getUsage(): ?int
    {
        return $this->usage;
    }

    public function setUsage(int $usage): void
    {
        $this->usage = $usage;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getUsageDetails(): ?array
    {
        return $this->usageDetails;
    }

    /**
     * @param array<string, mixed>|null $usageDetails
     */
    public function setUsageDetails(?array $usageDetails): void
    {
        $this->usageDetails = $usageDetails;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): void
    {
        $this->totalPrice = $totalPrice;
    }

    public function getActualPrice(): ?string
    {
        return $this->actualPrice;
    }

    public function setActualPrice(string $actualPrice): void
    {
        $this->actualPrice = $actualPrice;
    }

    public function getStatus(): ?BillStatus
    {
        return $this->status;
    }

    public function setStatus(BillStatus $status): void
    {
        $this->status = $status;
    }

    public function canTransitionTo(BillStatus $newStatus): bool
    {
        if (null === $this->status) {
            return true;
        }

        return $this->status->canTransitionTo($newStatus);
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(?Transaction $transaction): void
    {
        $this->transaction = $transaction;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): void
    {
        $this->failureReason = $failureReason;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): void
    {
        $this->paidAt = $paidAt;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s (%s)',
            $this->resourcePrice?->getTitle() ?? 'N/A',
            $this->billTime?->format('Y-m-d H:i:s') ?? 'N/A',
            $this->status->value ?? 'N/A'
        );
    }
}
