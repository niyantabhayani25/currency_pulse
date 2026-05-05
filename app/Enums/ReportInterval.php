<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportInterval: string
{
    case Monthly = 'monthly';
    case Weekly  = 'weekly';
    case Daily   = 'daily';
}
