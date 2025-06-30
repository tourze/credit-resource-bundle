<?php

namespace CreditResourceBundle\Tests\Repository;

use CreditResourceBundle\Repository\ResourcePriceRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\TestCase;

class ResourcePriceRepositoryTest extends TestCase
{
    /**
     * 测试 Repository 继承自 ServiceEntityRepository
     */
    public function testRepositoryInheritance(): void
    {
        $reflectionClass = new \ReflectionClass(ResourcePriceRepository::class);
        $parentClass = $reflectionClass->getParentClass();
        
        $this->assertNotFalse($parentClass);
        $this->assertEquals(ServiceEntityRepository::class, $parentClass->getName());
    }
    
    /**
     * 测试 Repository 关联到正确的实体类
     */
    public function testRepositoryHandlesCorrectEntityClass(): void
    {
        $repositoryClass = new \ReflectionClass(ResourcePriceRepository::class);
        $docComment = $repositoryClass->getDocComment();
        
        $this->assertNotFalse($docComment);
        
        // 通过正则表达式查找 @method 注释中的实体类
        preg_match_all('/@method\s+(\S+)/', $docComment, $matches);
        
        $hasResourcePriceEntity = false;
        foreach ($matches[1] as $match) {
            if (strpos($match, 'ResourcePrice') !== false) {
                $hasResourcePriceEntity = true;
                break;
            }
        }
        
        $this->assertTrue($hasResourcePriceEntity);
    }
} 