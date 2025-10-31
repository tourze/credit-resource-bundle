<?php

declare(strict_types=1);

namespace CreditResourceBundle\Entity;

use CreditResourceBundle\Enum\FeeCycle;
use CreditResourceBundle\Repository\ResourcePriceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;
use Tourze\RBAC\Core\Level0\Role;

/**
 * 在创建资源价格配置时，我们为他配置角色，意思是只有这个角色的用户，才需要计算账单.
 */
#[ORM\Entity(repositoryClass: ResourcePriceRepository::class)]
#[ORM\Table(name: 'credit_resource_price', options: ['comment' => '资源价格'])]
class ResourcePrice implements \Stringable
{
    use SnowflakeKeyAware;
    use TimestampableAware;
    use BlameableAware;

    #[IndexColumn]
    #[TrackColumn]
    #[Assert\NotNull]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '有效', 'default' => 0])]
    private ?bool $valid = false;

    #[TrackColumn]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    #[ORM\Column(length: 200, options: ['comment' => '资源名称'])]
    private ?string $title = null;

    #[TrackColumn]
    #[Assert\NotNull]
    #[Assert\Choice(callback: [FeeCycle::class, 'cases'])]
    #[ORM\Column(length: 30, enumType: FeeCycle::class, options: ['comment' => '计费周期'])]
    private ?FeeCycle $cycle = null;

    #[TrackColumn]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '起始计费数量'])]
    private ?int $minAmount = null;

    #[TrackColumn]
    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '最大计费数量'])]
    private ?int $maxAmount = null;

    #[TrackColumn]
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    #[ORM\Column(type: Types::STRING, length: 20, nullable: false, options: ['comment' => '币种代码'])]
    private ?string $currency = null;

    #[TrackColumn]
    #[Assert\NotNull]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,5})?$/', message: '价格格式不正确')]
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 5, options: ['comment' => '单价'])]
    private ?string $price = null;

    #[TrackColumn]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,5})?$/', message: '封顶价格格式不正确')]
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 5, nullable: true, options: ['comment' => '封顶价格'])]
    private ?string $topPrice = null;

    #[TrackColumn]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,5})?$/', message: '保底价格格式不正确')]
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 5, nullable: true, options: ['comment' => '保底价格'])]
    private ?string $bottomPrice = null;

    /**
     * @var string|null 这个资源ID填写实体类名
     */
    #[TrackColumn]
    #[Assert\NotBlank]
    #[Assert\Length(max: 1000)]
    #[ORM\Column(length: 1000, options: ['comment' => '资源ID'])]
    private ?string $resource = null;

    #[TrackColumn]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '备注'])]
    private ?string $remark = null;

    /**
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(
        name: 'credit_resource_price_role',
        joinColumns: [
            new ORM\JoinColumn(name: 'resource_price_id', referencedColumnName: 'id', onDelete: 'CASCADE'),
        ],
        inverseJoinColumns: [
            new ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id', onDelete: 'CASCADE'),
        ]
    )]
    private Collection $roles;

    #[TrackColumn]
    #[Assert\Length(max: 50)]
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '计费策略类名'])]
    private ?string $billingStrategy = null;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    #[TrackColumn]
    #[Assert\Type(type: 'array')]
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '价格规则（如阶梯价格）'])]
    private ?array $priceRules = null;

    #[TrackColumn]
    #[Assert\GreaterThanOrEqual(value: 0)]
    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '免费额度'])]
    private ?int $freeQuota = null;

    #[TrackColumn]
    #[Assert\Type(type: '\DateTimeImmutable')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '生效开始时间'])]
    private ?\DateTimeImmutable $startTime = null;

    #[TrackColumn]
    #[Assert\Type(type: '\DateTimeImmutable')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '生效结束时间'])]
    private ?\DateTimeImmutable $endTime = null;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
    }

    public function isValid(): ?bool
    {
        return $this->valid;
    }

    public function setValid(?bool $valid): void
    {
        $this->valid = $valid;
    }

    public function getResource(): ?string
    {
        return $this->resource;
    }

    public function setResource(string $resource): void
    {
        $this->resource = $resource;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getCycle(): ?FeeCycle
    {
        return $this->cycle;
    }

    public function setCycle(FeeCycle $cycle): void
    {
        $this->cycle = $cycle;
    }

    public function getMinAmount(): ?int
    {
        return $this->minAmount;
    }

    public function setMinAmount(int $minAmount): void
    {
        $this->minAmount = $minAmount;
    }

    public function getMaxAmount(): ?int
    {
        return $this->maxAmount;
    }

    public function setMaxAmount(?int $maxAmount): void
    {
        $this->maxAmount = $maxAmount;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): void
    {
        $this->currency = $currency;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): void
    {
        $this->price = $price;
    }

    public function getTopPrice(): ?string
    {
        return $this->topPrice;
    }

    public function setTopPrice(?string $topPrice): void
    {
        $this->topPrice = $topPrice;
    }

    public function getBottomPrice(): ?string
    {
        return $this->bottomPrice;
    }

    public function setBottomPrice(?string $bottomPrice): void
    {
        $this->bottomPrice = $bottomPrice;
    }

    public function getRemark(): ?string
    {
        return $this->remark;
    }

    public function setRemark(?string $remark): void
    {
        $this->remark = $remark;
    }

    /**
     * @return Collection<int, Role>
     */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    public function addRole(Role $role): void
    {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
        }
    }

    public function removeRole(Role $role): void
    {
        $this->roles->removeElement($role);
    }

    public function getBillingStrategy(): ?string
    {
        return $this->billingStrategy;
    }

    public function setBillingStrategy(?string $billingStrategy): void
    {
        $this->billingStrategy = $billingStrategy;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function getPriceRules(): ?array
    {
        return $this->priceRules;
    }

    /**
     * @param array<int, array<string, mixed>>|null $priceRules
     */
    public function setPriceRules(?array $priceRules): void
    {
        $this->priceRules = $priceRules;
    }

    public function getFreeQuota(): ?int
    {
        return $this->freeQuota;
    }

    public function setFreeQuota(?int $freeQuota): void
    {
        $this->freeQuota = $freeQuota;
    }

    public function getStartTime(): ?\DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(?\DateTimeImmutable $startTime): void
    {
        $this->startTime = $startTime;
    }

    public function getEndTime(): ?\DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeImmutable $endTime): void
    {
        $this->endTime = $endTime;
    }

    /**
     * 检查价格配置是否在有效期内.
     */
    public function isInValidPeriod(?\DateTimeInterface $date = null): bool
    {
        if (false === $this->isValid()) {
            return false;
        }

        $date ??= new \DateTimeImmutable();

        if (null !== $this->startTime && $date < $this->startTime) {
            return false;
        }

        if (null !== $this->endTime && $date > $this->endTime) {
            return false;
        }

        return true;
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }
}
