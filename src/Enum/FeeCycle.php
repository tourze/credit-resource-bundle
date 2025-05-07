<?php

namespace CreditResourceBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * 费用周期
 */
enum FeeCycle: string implements Labelable, Itemable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case TOTAL_BY_YEAR = 'total-by-year';
    case TOTAL_BY_MONTH = 'total-by-month';
    case TOTAL_BY_DAY = 'total-by-day';
    case TOTAL_BY_HOUR = 'total-by-hour';
    case NEW_BY_YEAR = 'new-by-year';
    case NEW_BY_MONTH = 'new-by-month';
    case NEW_BY_DAY = 'new-by-day';
    case NEW_BY_HOUR = 'new-by-hour';

    public function getLabel(): string
    {
        return match ($this) {
            self::TOTAL_BY_YEAR => '按年总计',
            self::TOTAL_BY_MONTH => '按月总计',
            self::TOTAL_BY_DAY => '按日总计',
            self::TOTAL_BY_HOUR => '按小时总计',

            self::NEW_BY_YEAR => '按年新增',
            self::NEW_BY_MONTH => '按月新增',
            self::NEW_BY_DAY => '按日新增',
            self::NEW_BY_HOUR => '按小时新增',
        };
    }
}
