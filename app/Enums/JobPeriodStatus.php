<?php

namespace App\Enums;

enum JobPeriodStatus: string
{
    case Aktif = 'aktif';
    case Berakhir = 'berakhir';
    case Diperbarui = 'diperbarui';
}
