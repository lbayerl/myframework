<?php

declare(strict_types=1);

namespace App\Enum;

enum TicketType: string
{
    case HARD_TICKET = 'HARD_TICKET';
    case E_TICKET = 'E_TICKET';
    case APP_TICKET = 'APP_TICKET';
}
