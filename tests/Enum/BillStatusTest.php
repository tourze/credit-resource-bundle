<?php

declare(strict_types=1);

namespace CreditResourceBundle\Tests\Enum;

use CreditResourceBundle\Enum\BillStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(BillStatus::class)]
final class BillStatusTest extends AbstractEnumTestCase
{
    public function testGetLabel(): void
    {
        $this->assertEquals('待支付', BillStatus::PENDING->getLabel());
        $this->assertEquals('处理中', BillStatus::PROCESSING->getLabel());
        $this->assertEquals('已支付', BillStatus::PAID->getLabel());
        $this->assertEquals('支付失败', BillStatus::FAILED->getLabel());
        $this->assertEquals('已取消', BillStatus::CANCELLED->getLabel());
    }

    public function testIsTerminal(): void
    {
        $this->assertFalse(BillStatus::PENDING->isTerminal());
        $this->assertFalse(BillStatus::PROCESSING->isTerminal());
        $this->assertTrue(BillStatus::PAID->isTerminal());
        $this->assertTrue(BillStatus::FAILED->isTerminal());
        $this->assertTrue(BillStatus::CANCELLED->isTerminal());
    }

    #[DataProvider('transitionProvider')]
    public function testCanTransitionTo(BillStatus $from, BillStatus $to, bool $expected): void
    {
        $this->assertEquals($expected, $from->canTransitionTo($to));
    }

    /**
     * @return array<int, array{BillStatus, BillStatus, bool}>
     */
    public static function transitionProvider(): array
    {
        return [
            // PENDING transitions
            [BillStatus::PENDING, BillStatus::PROCESSING, true],
            [BillStatus::PENDING, BillStatus::CANCELLED, true],
            [BillStatus::PENDING, BillStatus::PAID, false],
            [BillStatus::PENDING, BillStatus::FAILED, false],

            // PROCESSING transitions
            [BillStatus::PROCESSING, BillStatus::PAID, true],
            [BillStatus::PROCESSING, BillStatus::FAILED, true],
            [BillStatus::PROCESSING, BillStatus::PENDING, false],
            [BillStatus::PROCESSING, BillStatus::CANCELLED, false],

            // PAID transitions (terminal state)
            [BillStatus::PAID, BillStatus::PENDING, false],
            [BillStatus::PAID, BillStatus::PROCESSING, false],
            [BillStatus::PAID, BillStatus::FAILED, false],
            [BillStatus::PAID, BillStatus::CANCELLED, false],

            // FAILED transitions (can retry)
            [BillStatus::FAILED, BillStatus::PENDING, true],
            [BillStatus::FAILED, BillStatus::PROCESSING, false],
            [BillStatus::FAILED, BillStatus::PAID, false],
            [BillStatus::FAILED, BillStatus::CANCELLED, false],

            // CANCELLED transitions (terminal state)
            [BillStatus::CANCELLED, BillStatus::PENDING, false],
            [BillStatus::CANCELLED, BillStatus::PROCESSING, false],
            [BillStatus::CANCELLED, BillStatus::PAID, false],
            [BillStatus::CANCELLED, BillStatus::FAILED, false],
        ];
    }

    public function testEnumValues(): void
    {
        $this->assertEquals('pending', BillStatus::PENDING->value);
        $this->assertEquals('processing', BillStatus::PROCESSING->value);
        $this->assertEquals('paid', BillStatus::PAID->value);
        $this->assertEquals('failed', BillStatus::FAILED->value);
        $this->assertEquals('cancelled', BillStatus::CANCELLED->value);
    }

    public function testEnumTraits(): void
    {
        // Test ItemTrait functionality - toArray method
        $pendingArray = BillStatus::PENDING->toArray();
        $this->assertArrayHasKey('value', $pendingArray);
        $this->assertArrayHasKey('label', $pendingArray);
        $this->assertEquals('pending', $pendingArray['value']);
        $this->assertEquals('待支付', $pendingArray['label']);

        // Test SelectTrait functionality - genOptions method
        $selectOptions = BillStatus::genOptions();
        $this->assertCount(5, $selectOptions);

        // Verify each option has correct structure
        foreach ($selectOptions as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
            $this->assertArrayHasKey('text', $option);
            $this->assertArrayHasKey('name', $option);
        }

        // Test toSelectItem method
        $selectItem = BillStatus::PENDING->toSelectItem();
        $this->assertEquals('pending', $selectItem['value']);
        $this->assertEquals('待支付', $selectItem['label']);
        $this->assertEquals('待支付', $selectItem['text']);
        $this->assertEquals('待支付', $selectItem['name']);
    }

    public function testToArray(): void
    {
        $pendingArray = BillStatus::PENDING->toArray();
        $this->assertArrayHasKey('value', $pendingArray);
        $this->assertArrayHasKey('label', $pendingArray);
        $this->assertEquals('pending', $pendingArray['value']);
        $this->assertEquals('待支付', $pendingArray['label']);
    }

    public function testCasesMethod(): void
    {
        $cases = BillStatus::cases();
        $this->assertCount(5, $cases);
        $this->assertContainsOnlyInstancesOf(BillStatus::class, $cases);

        $values = array_map(fn ($case) => $case->value, $cases);
        $this->assertEquals(['pending', 'processing', 'paid', 'failed', 'cancelled'], $values);
    }
}
