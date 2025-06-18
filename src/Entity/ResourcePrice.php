<?php

namespace CreditResourceBundle\Entity;

use CreditBundle\Entity\Currency;
use CreditResourceBundle\Enum\FeeCycle;
use CreditResourceBundle\Repository\ResourcePriceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Service\SnowflakeIdGenerator;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Attribute\CreatedByColumn;
use Tourze\DoctrineUserBundle\Attribute\UpdatedByColumn;

/**
 * 在创建资源价格配置时，我们为他配置角色，意思是只有这个角色的用户，才需要计算账单
 */
#[ORM\Entity(repositoryClass: ResourcePriceRepository::class)]
#[ORM\Table(name: 'credit_resource_price', options: ['comment' => '资源价格'])]
class ResourcePrice
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(SnowflakeIdGenerator::class)]
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['comment' => 'ID'])]
    private ?string $id = null;

    #[CreatedByColumn]
    #[ORM\Column(nullable: true, options: ['comment' => '创建人'])]
    private ?string $createdBy = null;

    #[UpdatedByColumn]
    #[ORM\Column(nullable: true, options: ['comment' => '更新人'])]
    private ?string $updatedBy = null;

    #[IndexColumn]
    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '有效', 'default' => 0])]
    private ?bool $valid = false;

    #[TrackColumn]
    #[ORM\Column(length: 200, options: ['comment' => '资源名称'])]
    private ?string $title = null;

    #[TrackColumn]
    #[ORM\Column(length: 30, enumType: FeeCycle::class, options: ['comment' => '计费周期'])]
    private ?FeeCycle $cycle = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '起始计费数量'])]
    private ?int $minAmount = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '最大计费数量'])]
    private ?int $maxAmount = null;

    #[TrackColumn]
    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(nullable: false, options: ['comment' => '币种'])]
    private ?Currency $currency = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 5, options: ['comment' => '单价'])]
    private ?string $price = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 5, nullable: true, options: ['comment' => '封顶价格'])]
    private ?string $topPrice = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 5, nullable: true, options: ['comment' => '保底价格'])]
    private ?string $bottomPrice = null;

    /**
     * @var string|null 这个资源ID填写实体类名
     */
    #[TrackColumn]
    #[ORM\Column(length: 1000, options: ['comment' => '资源ID'])]
    private ?string $resource = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '备注'])]
    private ?string $remark = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setCreatedBy(?string $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setUpdatedBy(?string $updatedBy): self
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function isValid(): ?bool
    {
        return $this->valid;
    }

    public function setValid(?bool $valid): self
    {
        $this->valid = $valid;

        return $this;
    }

    public function getResource(): ?string
    {
        return $this->resource;
    }

    public function setResource(string $resource): self
    {
        $this->resource = $resource;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getCycle(): ?FeeCycle
    {
        return $this->cycle;
    }

    public function setCycle(FeeCycle $cycle): self
    {
        $this->cycle = $cycle;

        return $this;
    }

    public function getMinAmount(): ?int
    {
        return $this->minAmount;
    }

    public function setMinAmount(int $minAmount): self
    {
        $this->minAmount = $minAmount;

        return $this;
    }

    public function getMaxAmount(): ?int
    {
        return $this->maxAmount;
    }

    public function setMaxAmount(?int $maxAmount): self
    {
        $this->maxAmount = $maxAmount;

        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(?Currency $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getTopPrice(): ?string
    {
        return $this->topPrice;
    }

    public function setTopPrice(?string $topPrice): self
    {
        $this->topPrice = $topPrice;

        return $this;
    }

    public function getBottomPrice(): ?string
    {
        return $this->bottomPrice;
    }

    public function setBottomPrice(?string $bottomPrice): self
    {
        $this->bottomPrice = $bottomPrice;

        return $this;
    }

    public function getRemark(): ?string
    {
        return $this->remark;
    }

    public function setRemark(?string $remark): self
    {
        $this->remark = $remark;

        return $this;
    }
}
