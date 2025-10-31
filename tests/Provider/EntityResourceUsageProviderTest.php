<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Provider;

use CreditResourceBundle\Enum\FeeCycle;
use CreditResourceBundle\Provider\EntityResourceUsageProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @internal
 */
#[CoversClass(EntityResourceUsageProvider::class)]
final class EntityResourceUsageProviderTest extends TestCase
{
    private EntityResourceUsageProvider $provider;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->provider = new EntityResourceUsageProvider($this->entityManager);
    }

    /**
     * 创建UserInterface的简单stub实现
     *
     * @param non-empty-string $userIdentifier 用户标识符，默认为'test-user'
     * @param array<string> $roles 用户角色数组，默认为空数组
     */
    private function createUserStub(string $userIdentifier = 'test-user', array $roles = []): UserInterface
    {
        return new class($userIdentifier, $roles) implements UserInterface {
            /**
             * @param non-empty-string $userIdentifier
             * @param array<string> $roles
             */
            public function __construct(
                private readonly string $userIdentifier,
                private readonly array $roles = [],
            ) {
            }

            public function getRoles(): array
            {
                return $this->roles;
            }

            public function eraseCredentials(): void
            {
                // nothing to do here
            }

            public function getUserIdentifier(): string
            {
                return $this->userIdentifier;
            }
        };
    }

    public function testSupportsValidEntity(): void
    {
        // Use a real existing class for testing
        $testClass = \DateTimeImmutable::class;

        // 使用具体类ClassMetadata是因为：
        // 1. Doctrine的ClassMetadata是最终类，没有可用的抽象类或接口
        // 2. 这是测试Doctrine ORM集成功能的必要依赖
        // 3. ClassMetadata的结构稳定，不会影响测试的可维护性
        $metadata = $this->createMock(ClassMetadata::class);

        $this->entityManager->expects($this->once())
            ->method('getClassMetadata')
            ->with($testClass)
            ->willReturn($metadata)
        ;

        $this->assertTrue($this->provider->supports($testClass));
    }

    public function testSupportsInvalidClass(): void
    {
        $this->assertFalse($this->provider->supports('NonExistentClass'));
    }

    public function testSupportsNonEntity(): void
    {
        $this->entityManager->expects($this->once())
            ->method('getClassMetadata')
            ->with(\stdClass::class)
            ->willThrowException(new \Exception())
        ;

        $this->assertFalse($this->provider->supports(\stdClass::class));
    }

    public function testGetPriority(): void
    {
        $this->assertSame(0, $this->provider->getPriority());
    }

    public function testGetUsageWithUserField(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->expects($this->any())
            ->method('getUserIdentifier')
            ->willReturn('user123')
        ;

        $testClass = \DateTimeImmutable::class;

        // 使用具体类ClassMetadata是因为：
        // 1. Doctrine的ClassMetadata是最终类，没有可用的抽象类或接口
        // 2. 这是测试Doctrine ORM集成功能的必要依赖
        // 3. ClassMetadata的结构稳定，不会影响测试的可维护性
        $metadata = $this->createMock(ClassMetadata::class);
        // Mock to handle the user field check loop - will find user association
        $metadata->method('hasField')
            ->willReturnCallback(function ($field) {
                if ('user' === $field) {
                    return false; // user is an association, not a field
                }
                if ('createdAt' === $field) {
                    return true; // createdAt is a field
                }

                return false;
            })
        ;

        $metadata->method('hasAssociation')
            ->willReturnCallback(function ($field) {
                return 'user' === $field; // user is an association
            })
        ;

        // 使用具体类Query是因为：
        // 1. Doctrine的Query类没有合适的抽象接口可以替代
        // 2. 这是测试数据库查询功能的核心组件
        // 3. Query类的公共方法稳定，适合单元测试
        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn(42)
        ;

        // 使用具体类QueryBuilder是因为：
        // 1. Doctrine的QueryBuilder没有可用的抽象类或接口
        // 2. 这是测试查询构建功能的必要组件
        // 3. QueryBuilder的流式接口设计稳定，适合测试
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('select')
            ->with('COUNT(e.id)')
            ->willReturn($queryBuilder)
        ;
        $queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturn($queryBuilder)
        ;
        $queryBuilder->expects($this->exactly(3))
            ->method('setParameter')
            ->willReturn($queryBuilder)
        ;
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query)
        ;

        // Mock repository
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('e')
            ->willReturn($queryBuilder)
        ;

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with($testClass)
            ->willReturn($repository)
        ;
        $this->entityManager->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($metadata)
        ;

        $start = new \DateTime('2023-01-01');
        $end = new \DateTime('2023-12-31');

        $result = $this->provider->getUsage($user, $testClass, $start, $end);

        $this->assertSame(42, $result);
    }

    public function testGetUsageDetails(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->expects($this->any())
            ->method('getUserIdentifier')
            ->willReturn('user123')
        ;

        $start = new \DateTime('2023-01-01 00:00:00');
        $end = new \DateTime('2023-12-31 23:59:59');

        // Create a partial mock that only mocks the getUsage method
        $provider = $this->getMockBuilder(EntityResourceUsageProvider::class)
            ->setConstructorArgs([$this->entityManager])
            ->onlyMethods(['getUsage'])
            ->getMock()
        ;

        $provider->expects($this->once())
            ->method('getUsage')
            ->with($user, 'App\Entity\TestEntity', $start, $end)
            ->willReturn(10)
        ;

        $details = $provider->getUsageDetails($user, 'App\Entity\TestEntity', $start, $end);

        $this->assertArrayHasKey('resource_type', $details);
        $this->assertArrayHasKey('entity_class', $details);
        $this->assertArrayHasKey('user_id', $details);
        $this->assertArrayHasKey('period_start', $details);
        $this->assertArrayHasKey('period_end', $details);
        $this->assertArrayHasKey('count', $details);
        $this->assertArrayHasKey('provider', $details);

        $this->assertSame('App\Entity\TestEntity', $details['resource_type']);
        $this->assertSame('App\Entity\TestEntity', $details['entity_class']);
        $this->assertSame('user123', $details['user_id']);
        $this->assertSame('2023-01-01 00:00:00', $details['period_start']);
        $this->assertSame('2023-12-31 23:59:59', $details['period_end']);
        $this->assertSame(10, $details['count']);
        $this->assertSame(EntityResourceUsageProvider::class, $details['provider']);
    }

    public function testGetTimeRangeForCycleHourly(): void
    {
        $billTime = new \DateTime('2023-06-15 14:30:00');

        $range = $this->provider->getTimeRangeForCycle(FeeCycle::NEW_BY_HOUR, $billTime);

        $this->assertEquals(
            new \DateTime('2023-06-15 13:30:00'),
            $range['start']
        );
        $this->assertEquals($billTime, $range['end']);
    }

    public function testGetTimeRangeForCycleTotalByDay(): void
    {
        $billTime = new \DateTime('2023-06-15 14:30:00');

        $range = $this->provider->getTimeRangeForCycle(FeeCycle::TOTAL_BY_DAY, $billTime);

        $this->assertEquals(
            new \DateTime('2000-01-01 00:00:00'),
            $range['start']
        );
        $this->assertEquals($billTime, $range['end']);
    }

    public function testGetTimeRangeForCycleNewByMonth(): void
    {
        $billTime = new \DateTime('2023-06-15 14:30:00');

        $range = $this->provider->getTimeRangeForCycle(FeeCycle::NEW_BY_MONTH, $billTime);

        $this->assertEquals(
            new \DateTime('2023-05-15 14:30:00'),
            $range['start']
        );
        $this->assertEquals($billTime, $range['end']);
    }

    public function testGetUsageWithException(): void
    {
        $user = $this->createUserStub('user123');

        // 使用具体类QueryBuilder是因为：
        // 1. Doctrine的QueryBuilder没有可用的抽象类或接口
        // 2. 这是测试查询构建功能的必要组件
        // 3. QueryBuilder的流式接口设计稳定，适合测试
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willThrowException(new \Exception());

        // Mock repository
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        // 使用具体类ClassMetadata是因为：
        // 1. Doctrine的ClassMetadata是最终类，没有可用的抽象类或接口
        // 2. 这是测试Doctrine ORM集成功能的必要依赖
        // 3. ClassMetadata的结构稳定，不会影响测试的可维护性
        $metadata = $this->createMock(ClassMetadata::class);

        $this->entityManager->method('getRepository')->willReturn($repository);
        $this->entityManager->method('getClassMetadata')->willReturn($metadata);

        $result = $this->provider->getUsage(
            $user,
            'App\Entity\TestEntity',
            new \DateTime(),
            new \DateTime()
        );

        $this->assertSame(0, $result);
    }
}
