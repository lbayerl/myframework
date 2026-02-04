<?php

declare(strict_types=1);

namespace App\Enum;

enum ConcertStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case CANCELLED = 'CANCELLED';
}
