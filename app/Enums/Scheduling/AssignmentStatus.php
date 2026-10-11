<?php

namespace App\Enums\Scheduling;

enum AssignmentStatus: string
{
    case Planned = 'planned';
    case Cancelled = 'cancelled';
    case Confirmed = 'confirmed';

    public function canTransitionTo(self $status): bool
    {
        return $this === self::Planned
            && in_array($status, [self::Cancelled, self::Confirmed], true);
    }
}
