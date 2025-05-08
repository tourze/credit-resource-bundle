<?php

namespace CreditResourceBundle\Entity;

use CreditBundle\Entity\Currency;
use CreditResourceBundle\Enum\FeeCycle;
use CreditResourceBundle\Repository\ResourcePriceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Service\SnowflakeIdGenerator;
use Tourze\DoctrineTimestampBundle\Attribute\CreateTimeColumn;
use Tourze\DoctrineTimestampBundle\Attribute\UpdateTimeColumn;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Attribute\CreatedByColumn;
use Tourze\DoctrineUserBundle\Attribute\UpdatedByColumn;
use Tourze\EasyAdmin\Attribute\Action\Creatable;
use Tourze\EasyAdmin\Attribute\Action\Deletable;
use Tourze\EasyAdmin\Attribute\Action\Editable;
use Tourze\EasyAdmin\Attribute\Column\BoolColumn;
use Tourze\EasyAdmin\Attribute\Column\ExportColumn;
use Tourze\EasyAdmin\Attribute\Column\ListColumn;
use Tourze\EasyAdmin\Attribute\Field\FormField;
use Tourze\EasyAdmin\Attribute\Filter\Filterable;
use Tourze\EasyAdmin\Attribute\Permission\AsPermission;

/**
 * 在创建资源价格配置时，我们为他配置角色，意思是只有这个角色的用户，才需要计算账单
 */
#[AsPermission(title: '资源价格')]
#[Deletable]
#[Editable]
#[Creatable]
#[ORM\Entity(repositoryClass: ResourcePriceRepository::class)]
#[ORM\Table(name: 'credit_resource_price', options: ['comment' => '资源价格'])]
class ResourcePrice
{
    #[Filterable]
    #[IndexColumn]
    #[ListColumn(order: 98, sorter: true)]
    #[ExportColumn]
    #[CreateTimeColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '创建时间'])]
    private ?\DateTimeInterface $createTime = null;

    #[UpdateTimeColumn]
    #[ListColumn(order: 99, sorter: true)]
    #[Filterable]
    #[ExportColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '更新时间'])]
    private ?\DateTimeInterface $updateTime = null;

    public function setCreateTime(?\DateTimeInterface $createdAt): void
    {
        $this->createTime = $createdAt;
    }

    public function getCreateTime(): ?\DateTimeInterface
    {
        return $this->createTime;
    }

    public function setUpdateTime(?\DateTimeInterface $updateTime): void
    {
        $this->updateTime = $updateTime;
    }

    public function getUpdateTime(): ?\DateTimeInterface
    {
        return $this->updateTime;
    }

    #[ExportColumn]
    #[ListColumn(order: -1, sorter: true)]
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

    #[BoolColumn]
    #[IndexColumn]
    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '有效', 'default' => 0])]
    #[ListColumn(order: 97)]
    #[FormField(order: 97)]
    private ?bool $valid = false;

    #[TrackColumn]
    #[ListColumn]
    #[FormField(span: 12)]
    #[ORM\Column(length: 200, options: ['comment' => '资源名称'])]
    private ?string $title = null;

    #[TrackColumn]
    #[ListColumn]
    #[FormField(span: 8)]
    #[ORM\Column(length: 30, enumType: FeeCycle::class, options: ['comment' => '计费周期'])]
    private ?FeeCycle $cycle = null;

    #[TrackColumn]
    #[ListColumn]
    #[FormField(span: 8)]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '起始计费数量'])]
    private ?int $minAmount = null;

    #[TrackColumn]
    #[ListColumn]
    #[FormField(span: 8)]
    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '最大计费数量'])]
    private ?int $maxAmount = null;

    #[TrackColumn]
    #[ListColumn(title: '币种')]
    #[FormField(title: '币种', span: 6)]
    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(nullable: false, options: ['comment' => '币种'])]
    private ?Currency $currency = null;

    #[TrackColumn]
    #[ListColumn]
    #[FormField(span: 6)]
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 5, options: ['comment' => '单价'])]
    private ?string $price = null;

    #[TrackColumn]
    #[ListColumn]
    #[FormField(span: 6)]
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 5, nullable: true, options: ['comment' => '封顶价格'])]
    private ?string $topPrice = null;

    #[TrackColumn]
    #[ListColumn]
    #[FormField(span: 6)]
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 5, nullable: true, options: ['comment' => '保底价格'])]
    private ?string $bottomPrice = null;

    /**
     * @var string|null 这个资源ID填写实体类名
     */
    #[TrackColumn]
    #[Filterable]
    #[FormField]
    #[ORM\Column(length: 1000, options: ['comment' => '资源ID'])]
    private ?string $resource = null;

    #[FormField]
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
