<?php

namespace Finller\Kpi\Enums;

enum KpiInterval: string
{
    case Day = "day";
    case Week = "week";
    case Month = "month";
    case Year = "year";

    public function dateFormatComparator()
    {
        return match ($this) {
            KpiInterval::Day => 'Y-m-d',
            KpiInterval::Week => 'Y-W',
            KpiInterval::Month => 'Y-m',
            KpiInterval::Year => 'Y',
        };
    }
}
