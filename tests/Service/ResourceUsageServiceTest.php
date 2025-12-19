<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Service;

use CreditResourceBundle\Exception\ProviderNotFoundException;
use CreditResourceBundle\Interface\ResourceUsageProviderInterface;
use CreditResourceBundle\Service\ResourceUsageService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ResourceUsageService::class)]
#[RunTestsInSeparateProcesses]
final class ResourceUsageServiceTest extends AbstractIntegrationTestCase
{
    private ResourceUsageService $service;

    private UserInterface $testUser;

    protected function onSetUp(): void
    {
        $this->service = self::getContainer()->get(ResourceUsageService::class);

        // 使用 InMemoryUser 作为测试用户
        $this->testUser = new InMemoryUser('test-user', null, ['ROLE_USER']);
    }

    public function testGetUsageWithValidProvider(): void
    {
        $resourceType = 'test_resource';
        $start = new \DateTimeImmutable('-1 day');
        $end = new \DateTimeImmutable();

        // 创建测试提供者
        $testProvider = new class implements ResourceUsageProviderInterface {
            public function supports(string $resourceType): bool
            {
                return 'test_resource' === $resourceType;
            }

            public function getUsage(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): int
            {
                return 100;
            }

            public function getUsageDetails(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): array
            {
                return ['usage' => 100, 'details' => 'test'];
            }

            public function getPriority(): int
            {
                return 0;
            }
        };

        $this->service->addProvider($testProvider);

        $usage = $this->service->getUsage($this->testUser, $resourceType, $start, $end);

        $this->assertEquals(100, $usage);
    }

    public function testGetUsageWithInvalidProviderThrowsException(): void
    {
        $resourceType = 'nonexistent_resource';
        $start = new \DateTimeImmutable('-1 day');
        $end = new \DateTimeImmutable();

        $this->expectException(ProviderNotFoundException::class);
        $this->expectExceptionMessage('没有找到支持资源类型 "nonexistent_resource" 的使用量提供者');

        $this->service->getUsage($this->testUser, $resourceType, $start, $end);
    }

    public function testGetUsageDetailsWithValidProvider(): void
    {
        $resourceType = 'test_resource';
        $start = new \DateTimeImmutable('-1 day');
        $end = new \DateTimeImmutable();

        // 创建测试提供者
        $testProvider = new class implements ResourceUsageProviderInterface {
            public function supports(string $resourceType): bool
            {
                return 'test_resource' === $resourceType;
            }

            public function getUsage(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): int
            {
                return 100;
            }

            public function getUsageDetails(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): array
            {
                return [
                    'usage' => 100,
                    'details' => 'test details',
                    'timestamp' => time(),
                ];
            }

            public function getPriority(): int
            {
                return 0;
            }
        };

        $this->service->addProvider($testProvider);

        $details = $this->service->getUsageDetails($this->testUser, $resourceType, $start, $end);

        $this->assertIsArray($details);
        $this->assertEquals(100, $details['usage']);
        $this->assertEquals('test details', $details['details']);
    }

    public function testGetBatchUsageWithMultipleResources(): void
    {
        $resourceTypes = ['resource1', 'resource2', 'nonexistent'];
        $start = new \DateTimeImmutable('-1 day');
        $end = new \DateTimeImmutable();

        // 创建测试提供者
        $provider1 = new class implements ResourceUsageProviderInterface {
            public function supports(string $resourceType): bool
            {
                return 'resource1' === $resourceType;
            }

            public function getUsage(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): int
            {
                return 50;
            }

            public function getUsageDetails(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): array
            {
                return ['usage' => 50];
            }

            public function getPriority(): int
            {
                return 0;
            }
        };

        $provider2 = new class implements ResourceUsageProviderInterface {
            public function supports(string $resourceType): bool
            {
                return 'resource2' === $resourceType;
            }

            public function getUsage(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): int
            {
                return 75;
            }

            public function getUsageDetails(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): array
            {
                return ['usage' => 75];
            }

            public function getPriority(): int
            {
                return 0;
            }
        };

        $this->service->addProvider($provider1);
        $this->service->addProvider($provider2);

        $result = $this->service->getBatchUsage($this->testUser, $resourceTypes, $start, $end);

        $this->assertIsArray($result);
        $this->assertEquals(50, $result['resource1']);
        $this->assertEquals(75, $result['resource2']);
        $this->assertIsArray($result['nonexistent']);
        $this->assertArrayHasKey('error', $result['nonexistent']);
        $this->assertTrue($result['nonexistent']['error']);
    }

    public function testHasProviderReturnsTrueForSupportedResource(): void
    {
        $resourceType = 'test_resource';

        // 创建测试提供者
        $testProvider = new class implements ResourceUsageProviderInterface {
            public function supports(string $resourceType): bool
            {
                return 'test_resource' === $resourceType;
            }

            public function getUsage(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): int
            {
                return 100;
            }

            public function getUsageDetails(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): array
            {
                return ['usage' => 100];
            }

            public function getPriority(): int
            {
                return 0;
            }
        };

        $this->service->addProvider($testProvider);

        $this->assertTrue($this->service->hasProvider($resourceType));
    }

    public function testHasProviderReturnsFalseForUnsupportedResource(): void
    {
        $this->assertFalse($this->service->hasProvider('nonexistent_resource'));
    }

    public function testGetSupportedResourceTypesWithProviders(): void
    {
        // 创建测试提供者，实现 getSupportedTypes 方法
        $testProvider = new class implements ResourceUsageProviderInterface {
            public function supports(string $resourceType): bool
            {
                return in_array($resourceType, ['resource1', 'resource2'], true);
            }

            public function getUsage(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): int
            {
                return 100;
            }

            public function getUsageDetails(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): array
            {
                return ['usage' => 100];
            }

            public function getPriority(): int
            {
                return 0;
            }

            /**
             * @return array<int, string>
             */
            public function getSupportedTypes(): array
            {
                return ['resource1', 'resource2'];
            }
        };

        $this->service->addProvider($testProvider);

        $types = $this->service->getSupportedResourceTypes();

        $this->assertIsArray($types);
        $this->assertContains('resource1', $types);
        $this->assertContains('resource2', $types);
    }

    public function testGetSupportedResourceTypesWithoutProviders(): void
    {
        $types = $this->service->getSupportedResourceTypes();

        $this->assertIsArray($types);
        $this->assertEmpty($types);
    }

    public function testProviderPrioritySorting(): void
    {
        $resourceType = 'test_resource';
        $start = new \DateTimeImmutable('-1 day');
        $end = new \DateTimeImmutable();

        // 创建低优先级提供者
        $lowPriorityProvider = new class implements ResourceUsageProviderInterface {
            public function supports(string $resourceType): bool
            {
                return 'test_resource' === $resourceType;
            }

            public function getUsage(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): int
            {
                return 10;
            }

            public function getUsageDetails(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): array
            {
                return ['usage' => 10];
            }

            public function getPriority(): int
            {
                return 1;
            }
        };

        // 创建高优先级提供者
        $highPriorityProvider = new class implements ResourceUsageProviderInterface {
            public function supports(string $resourceType): bool
            {
                return 'test_resource' === $resourceType;
            }

            public function getUsage(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): int
            {
                return 20;
            }

            public function getUsageDetails(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): array
            {
                return ['usage' => 20];
            }

            public function getPriority(): int
            {
                return 10;
            }
        };

        $this->service->addProvider($lowPriorityProvider);
        $this->service->addProvider($highPriorityProvider);

        // 高优先级提供者应该被使用
        $usage = $this->service->getUsage($this->testUser, $resourceType, $start, $end);

        $this->assertEquals(20, $usage);
    }

    public function testAddProviderMaintainsPriorityOrder(): void
    {
        $resourceType = 'test_resource';
        $start = new \DateTimeImmutable('-1 day');
        $end = new \DateTimeImmutable();

        // 创建提供者，优先级递增
        $provider1 = new class(5) implements ResourceUsageProviderInterface {
            private int $priority;

            public function __construct(int $priority)
            {
                $this->priority = $priority;
            }

            public function supports(string $resourceType): bool
            {
                return 'test_resource' === $resourceType;
            }

            public function getUsage(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): int
            {
                return $this->priority;
            }

            public function getUsageDetails(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): array
            {
                return ['usage' => $this->priority];
            }

            public function getPriority(): int
            {
                return $this->priority;
            }
        };

        $provider2 = new class(10) implements ResourceUsageProviderInterface {
            private int $priority;

            public function __construct(int $priority)
            {
                $this->priority = $priority;
            }

            public function supports(string $resourceType): bool
            {
                return 'test_resource' === $resourceType;
            }

            public function getUsage(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): int
            {
                return $this->priority;
            }

            public function getUsageDetails(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): array
            {
                return ['usage' => $this->priority];
            }

            public function getPriority(): int
            {
                return $this->priority;
            }
        };

        $provider3 = new class(15) implements ResourceUsageProviderInterface {
            private int $priority;

            public function __construct(int $priority)
            {
                $this->priority = $priority;
            }

            public function supports(string $resourceType): bool
            {
                return 'test_resource' === $resourceType;
            }

            public function getUsage(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): int
            {
                return $this->priority;
            }

            public function getUsageDetails(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): array
            {
                return ['usage' => $this->priority];
            }

            public function getPriority(): int
            {
                return $this->priority;
            }
        };

        // 按优先级递增顺序添加
        $this->service->addProvider($provider1);
        $this->service->addProvider($provider2);
        $this->service->addProvider($provider3);

        // 最高优先级的提供者应该被使用
        $usage = $this->service->getUsage($this->testUser, $resourceType, $start, $end);

        $this->assertEquals(15, $usage);
    }
}
