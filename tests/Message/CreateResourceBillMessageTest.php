<?php

namespace CreditResourceBundle\Tests\Message;

use CreditResourceBundle\Message\CreateResourceBillMessage;
use PHPUnit\Framework\TestCase;
use Tourze\AsyncContracts\AsyncMessageInterface;

class CreateResourceBillMessageTest extends TestCase
{
    private CreateResourceBillMessage $message;
    
    protected function setUp(): void
    {
        $this->message = new CreateResourceBillMessage();
    }
    
    /**
     * 测试消息实现 AsyncMessageInterface 接口
     */
    public function testImplementation(): void
    {
        $this->assertInstanceOf(AsyncMessageInterface::class, $this->message);
    }
    
    /**
     * 测试 bizUserId 属性的 getter 和 setter
     */
    public function testBizUserIdAccessors(): void
    {
        $bizUserId = '123456789';
        
        $this->message->setBizUserId($bizUserId);
        $this->assertSame($bizUserId, $this->message->getBizUserId());
    }
    
    /**
     * 测试 resourcePriceId 属性的 getter 和 setter
     */
    public function testResourcePriceIdAccessors(): void
    {
        $resourcePriceId = '987654321';
        
        $this->message->setResourcePriceId($resourcePriceId);
        $this->assertSame($resourcePriceId, $this->message->getResourcePriceId());
    }
    
    /**
     * 测试 time 属性的 getter 和 setter
     */
    public function testTimeAccessors(): void
    {
        $time = '2023-04-01 12:00:00';
        
        $this->message->setTime($time);
        $this->assertSame($time, $this->message->getTime());
    }
    
    /**
     * 测试设置和获取所有属性
     */
    public function testAllAccessors(): void
    {
        $bizUserId = '123456789';
        $resourcePriceId = '987654321';
        $time = '2023-04-01 12:00:00';
        
        $this->message->setBizUserId($bizUserId);
        $this->message->setResourcePriceId($resourcePriceId);
        $this->message->setTime($time);
        
        $this->assertSame($bizUserId, $this->message->getBizUserId());
        $this->assertSame($resourcePriceId, $this->message->getResourcePriceId());
        $this->assertSame($time, $this->message->getTime());
    }
    
    /**
     * 测试未设置 bizUserId 时调用 getter 会抛出异常
     */
    public function testGetBizUserIdWithoutSetting(): void
    {
        $this->expectException(\Error::class); // PHP 8 未初始化属性访问会抛出 Error
        $this->message->getBizUserId();
    }
    
    /**
     * 测试未设置 resourcePriceId 时调用 getter 会抛出异常
     */
    public function testGetResourcePriceIdWithoutSetting(): void
    {
        $this->expectException(\Error::class); // PHP 8 未初始化属性访问会抛出 Error
        $this->message->getResourcePriceId();
    }
    
    /**
     * 测试未设置 time 时调用 getter 会抛出异常
     */
    public function testGetTimeWithoutSetting(): void
    {
        $this->expectException(\Error::class); // PHP 8 未初始化属性访问会抛出 Error
        $this->message->getTime();
    }
    
    /**
     * 测试传入空字符串
     */
    public function testEmptyStrings(): void
    {
        $this->message->setBizUserId('');
        $this->assertSame('', $this->message->getBizUserId());
        
        $this->message->setResourcePriceId('');
        $this->assertSame('', $this->message->getResourcePriceId());
        
        $this->message->setTime('');
        $this->assertSame('', $this->message->getTime());
    }
} 