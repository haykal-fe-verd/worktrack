<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Aktif = 'aktif';
    case Selesai = 'selesai';
    case Diperbarui = 'diperbarui';
}
