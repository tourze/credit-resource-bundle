<?php

declare(strict_types=1);

namespace CreditResourceBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum BillStatus: string implements Itemable, Labelable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PAID = 'paid';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => '待支付',
            self::PROCESSING => '处理中',
            self::PAID => '已支付',
            self::FAILED => '支付失败',
            self::CANCELLED => '已取消',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::PAID, self::FAILED, self::CANCELLED], true);
    }

    public function canTransitionTo(self $status): bool
    {
        return match ($this) {
            self::PENDING => in_array($status, [self::PROCESSING, self::CANCELLED], true),
            self::PROCESSING => in_array($status, [self::PAID, self::FAILED], true),
            self::PAID => false,
            self::FAILED => in_array($status, [self::PENDING], true), // 允许重试
            self::CANCELLED => false,
        };
    }
}
