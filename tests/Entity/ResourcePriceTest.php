<?php

namespace CreditResourceBundle\Tests\Entity;

use CreditBundle\Entity\Currency;
use CreditResourceBundle\Entity\ResourcePrice;
use CreditResourceBundle\Enum\FeeCycle;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ResourcePriceTest extends TestCase
{
    private ResourcePrice $resourcePrice;
    
    protected function setUp(): void
    {
        $this->resourcePrice = new ResourcePrice();
    }
    
    /**
     * 测试 ID 的 getter 和 setter
     */
    public function testIdAccessors(): void
    {
        // ID 是由 Doctrine 自动生成的，因此这里只测试 getter
        $this->assertNull($this->resourcePrice->getId());
    }
    
    /**
     * 测试 createTime 的 getter 和 setter
     */
    public function testCreateTimeAccessors(): void
    {
        $dateTime = new DateTimeImmutable();
        
        $this->resourcePrice->setCreateTime($dateTime);
        $this->assertSame($dateTime, $this->resourcePrice->getCreateTime());
        
        $this->resourcePrice->setCreateTime(null);
        $this->assertNull($this->resourcePrice->getCreateTime());
    }
    
    /**
     * 测试 updateTime 的 getter 和 setter
     */
    public function testUpdateTimeAccessors(): void
    {
        $dateTime = new DateTimeImmutable();
        
        $this->resourcePrice->setUpdateTime($dateTime);
        $this->assertSame($dateTime, $this->resourcePrice->getUpdateTime());
        
        $this->resourcePrice->setUpdateTime(null);
        $this->assertNull($this->resourcePrice->getUpdateTime());
    }
    
    /**
     * 测试 createdBy 的 getter 和 setter
     */
    public function testCreatedByAccessors(): void
    {
        $createdBy = '12345';
        
        $this->resourcePrice->setCreatedBy($createdBy);
        $this->assertSame($createdBy, $this->resourcePrice->getCreatedBy());
        
        $this->resourcePrice->setCreatedBy(null);
        $this->assertNull($this->resourcePrice->getCreatedBy());
    }
    
    /**
     * 测试 updatedBy 的 getter 和 setter
     */
    public function testUpdatedByAccessors(): void
    {
        $updatedBy = '12345';
        
        $this->resourcePrice->setUpdatedBy($updatedBy);
        $this->assertSame($updatedBy, $this->resourcePrice->getUpdatedBy());
        
        $this->resourcePrice->setUpdatedBy(null);
        $this->assertNull($this->resourcePrice->getUpdatedBy());
    }
    
    /**
     * 测试 valid 的 getter 和 setter
     */
    public function testValidAccessors(): void
    {
        $this->resourcePrice->setValid(true);
        $this->assertTrue($this->resourcePrice->isValid());
        
        $this->resourcePrice->setValid(false);
        $this->assertFalse($this->resourcePrice->isValid());
        
        $this->resourcePrice->setValid(null);
        $this->assertNull($this->resourcePrice->isValid());
    }
    
    /**
     * 测试 title 的 getter 和 setter
     */
    public function testTitleAccessors(): void
    {
        $title = '测试资源';
        
        $this->resourcePrice->setTitle($title);
        $this->assertSame($title, $this->resourcePrice->getTitle());
        
        // 默认值为 null
        $newResourcePrice = new ResourcePrice();
        $this->assertNull($newResourcePrice->getTitle());
    }
    
    /**
     * 测试 cycle 的 getter 和 setter
     */
    public function testCycleAccessors(): void
    {
        $cycle = FeeCycle::TOTAL_BY_MONTH;
        
        $this->resourcePrice->setCycle($cycle);
        $this->assertSame($cycle, $this->resourcePrice->getCycle());
        
        // 默认值为 null
        $newResourcePrice = new ResourcePrice();
        $this->assertNull($newResourcePrice->getCycle());
    }
    
    /**
     * 测试 minAmount 的 getter 和 setter
     */
    public function testMinAmountAccessors(): void
    {
        $minAmount = 100;
        
        $this->resourcePrice->setMinAmount($minAmount);
        $this->assertSame($minAmount, $this->resourcePrice->getMinAmount());
        
        // 默认值为 null
        $newResourcePrice = new ResourcePrice();
        $this->assertNull($newResourcePrice->getMinAmount());
    }
    
    /**
     * 测试 maxAmount 的 getter 和 setter
     */
    public function testMaxAmountAccessors(): void
    {
        $maxAmount = 1000;
        
        $this->resourcePrice->setMaxAmount($maxAmount);
        $this->assertSame($maxAmount, $this->resourcePrice->getMaxAmount());
        
        $this->resourcePrice->setMaxAmount(null);
        $this->assertNull($this->resourcePrice->getMaxAmount());
    }
    
    /**
     * 测试 currency 的 getter 和 setter
     */
    public function testCurrencyAccessors(): void
    {
        $currency = $this->createMock(Currency::class);
        
        $this->resourcePrice->setCurrency($currency);
        $this->assertSame($currency, $this->resourcePrice->getCurrency());
        
        $this->resourcePrice->setCurrency(null);
        $this->assertNull($this->resourcePrice->getCurrency());
    }
    
    /**
     * 测试 price 的 getter 和 setter
     */
    public function testPriceAccessors(): void
    {
        $price = '9.99999';
        
        $this->resourcePrice->setPrice($price);
        $this->assertSame($price, $this->resourcePrice->getPrice());
        
        // 默认值为 null
        $newResourcePrice = new ResourcePrice();
        $this->assertNull($newResourcePrice->getPrice());
    }
    
    /**
     * 测试 topPrice 的 getter 和 setter
     */
    public function testTopPriceAccessors(): void
    {
        $topPrice = '99.99999';
        
        $this->resourcePrice->setTopPrice($topPrice);
        $this->assertSame($topPrice, $this->resourcePrice->getTopPrice());
        
        $this->resourcePrice->setTopPrice(null);
        $this->assertNull($this->resourcePrice->getTopPrice());
    }
    
    /**
     * 测试 bottomPrice 的 getter 和 setter
     */
    public function testBottomPriceAccessors(): void
    {
        $bottomPrice = '1.00000';
        
        $this->resourcePrice->setBottomPrice($bottomPrice);
        $this->assertSame($bottomPrice, $this->resourcePrice->getBottomPrice());
        
        $this->resourcePrice->setBottomPrice(null);
        $this->assertNull($this->resourcePrice->getBottomPrice());
    }
    
    /**
     * 测试 resource 的 getter 和 setter
     */
    public function testResourceAccessors(): void
    {
        $resource = 'App\\Entity\\TestResource';
        
        $this->resourcePrice->setResource($resource);
        $this->assertSame($resource, $this->resourcePrice->getResource());
        
        // 默认值为 null
        $newResourcePrice = new ResourcePrice();
        $this->assertNull($newResourcePrice->getResource());
    }
    
    /**
     * 测试 remark 的 getter 和 setter
     */
    public function testRemarkAccessors(): void
    {
        $remark = '测试备注信息';
        
        $this->resourcePrice->setRemark($remark);
        $this->assertSame($remark, $this->resourcePrice->getRemark());
        
        $this->resourcePrice->setRemark(null);
        $this->assertNull($this->resourcePrice->getRemark());
    }
    
    /**
     * 测试实体的方法链式调用
     */
    public function testMethodChaining(): void
    {
        $createdBy = '12345';
        $updatedBy = '54321';
        $valid = true;
        $resource = 'App\\Entity\\TestResource';
        $title = '测试资源';
        $cycle = FeeCycle::TOTAL_BY_MONTH;
        $minAmount = 100;
        $maxAmount = 1000;
        $currency = $this->createMock(Currency::class);
        $price = '9.99999';
        $topPrice = '99.99999';
        $bottomPrice = '1.00000';
        $remark = '测试备注信息';
        
        $result = $this->resourcePrice
            ->setCreatedBy($createdBy)
            ->setUpdatedBy($updatedBy)
            ->setValid($valid)
            ->setResource($resource)
            ->setTitle($title)
            ->setCycle($cycle)
            ->setMinAmount($minAmount)
            ->setMaxAmount($maxAmount)
            ->setCurrency($currency)
            ->setPrice($price)
            ->setTopPrice($topPrice)
            ->setBottomPrice($bottomPrice)
            ->setRemark($remark);
        
        $this->assertSame($this->resourcePrice, $result);
        $this->assertSame($createdBy, $this->resourcePrice->getCreatedBy());
        $this->assertSame($updatedBy, $this->resourcePrice->getUpdatedBy());
        $this->assertSame($valid, $this->resourcePrice->isValid());
        $this->assertSame($resource, $this->resourcePrice->getResource());
        $this->assertSame($title, $this->resourcePrice->getTitle());
        $this->assertSame($cycle, $this->resourcePrice->getCycle());
        $this->assertSame($minAmount, $this->resourcePrice->getMinAmount());
        $this->assertSame($maxAmount, $this->resourcePrice->getMaxAmount());
        $this->assertSame($currency, $this->resourcePrice->getCurrency());
        $this->assertSame($price, $this->resourcePrice->getPrice());
        $this->assertSame($topPrice, $this->resourcePrice->getTopPrice());
        $this->assertSame($bottomPrice, $this->resourcePrice->getBottomPrice());
        $this->assertSame($remark, $this->resourcePrice->getRemark());
    }

} 