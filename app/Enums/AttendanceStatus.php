<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Hadir = 'hadir';
    case TidakHadir = 'tidak_hadir';
    case Izin = 'izin';
}
