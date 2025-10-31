<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Service;

use CreditResourceBundle\Exception\ProviderNotFoundException;
use CreditResourceBundle\Interface\ResourceUsageProviderInterface;
use CreditResourceBundle\Service\ResourceUsageService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @internal
 */
#[CoversClass(ResourceUsageService::class)]
final class ResourceUsageServiceTest extends TestCase
{
    private ResourceUsageService $resourceUsageService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resourceUsageService = new ResourceUsageService([]);
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

    public function testGetUsageWithValidProvider(): void
    {
        $user = $this->createUserStub();
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

        $this->resourceUsageService->addProvider($testProvider);

        $usage = $this->resourceUsageService->getUsage($user, $resourceType, $start, $end);

        $this->assertEquals(100, $usage);
    }

    public function testGetUsageWithInvalidProviderThrowsException(): void
    {
        $user = $this->createUserStub();
        $resourceType = 'nonexistent_resource';
        $start = new \DateTimeImmutable('-1 day');
        $end = new \DateTimeImmutable();

        $this->expectException(ProviderNotFoundException::class);
        $this->expectExceptionMessage('没有找到支持资源类型 "nonexistent_resource" 的使用量提供者');

        $this->resourceUsageService->getUsage($user, $resourceType, $start, $end);
    }

    public function testGetUsageDetailsWithValidProvider(): void
    {
        $user = $this->createUserStub();
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

        $this->resourceUsageService->addProvider($testProvider);

        $details = $this->resourceUsageService->getUsageDetails($user, $resourceType, $start, $end);

        $this->assertIsArray($details);
        $this->assertEquals(100, $details['usage']);
        $this->assertEquals('test details', $details['details']);
    }

    public function testGetBatchUsageWithMultipleResources(): void
    {
        $user = $this->createUserStub();
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

        $this->resourceUsageService->addProvider($provider1);
        $this->resourceUsageService->addProvider($provider2);

        $result = $this->resourceUsageService->getBatchUsage($user, $resourceTypes, $start, $end);

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

        $this->resourceUsageService->addProvider($testProvider);

        $this->assertTrue($this->resourceUsageService->hasProvider($resourceType));
    }

    public function testHasProviderReturnsFalseForUnsupportedResource(): void
    {
        $this->assertFalse($this->resourceUsageService->hasProvider('nonexistent_resource'));
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

        $this->resourceUsageService->addProvider($testProvider);

        $types = $this->resourceUsageService->getSupportedResourceTypes();

        $this->assertIsArray($types);
        $this->assertContains('resource1', $types);
        $this->assertContains('resource2', $types);
    }

    public function testGetSupportedResourceTypesWithoutProviders(): void
    {
        $types = $this->resourceUsageService->getSupportedResourceTypes();

        $this->assertIsArray($types);
        $this->assertEmpty($types);
    }

    public function testProviderPrioritySorting(): void
    {
        $user = $this->createUserStub();
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

        $this->resourceUsageService->addProvider($lowPriorityProvider);
        $this->resourceUsageService->addProvider($highPriorityProvider);

        // 高优先级提供者应该被使用
        $usage = $this->resourceUsageService->getUsage($user, $resourceType, $start, $end);

        $this->assertEquals(20, $usage);
    }

    public function testAddProviderMaintainsPriorityOrder(): void
    {
        $user = $this->createUserStub();
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
        $this->resourceUsageService->addProvider($provider1);
        $this->resourceUsageService->addProvider($provider2);
        $this->resourceUsageService->addProvider($provider3);

        // 最高优先级的提供者应该被使用
        $usage = $this->resourceUsageService->getUsage($user, $resourceType, $start, $end);

        $this->assertEquals(15, $usage);
    }
}
