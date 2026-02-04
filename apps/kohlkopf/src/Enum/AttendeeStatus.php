<?php

declare(strict_types=1);

namespace App\Enum;

enum AttendeeStatus: string
{
    case INTERESTED = 'INTERESTED';
    case ATTENDING = 'ATTENDING';
    case DECLINED = 'DECLINED';
    case PARTICIPATED = 'PARTICIPATED';
}
