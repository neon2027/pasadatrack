<?php

namespace App\Enums\Attendance;

enum AttendanceSource: string
{
    case Manual = 'manual';
    case Biometric = 'biometric';
}
