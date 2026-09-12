<?php

namespace App\Enums;

enum DocumentType: string
{
    case PR = 'PR';
    case PO = 'PO';
    case DO = 'DO';
    case WO = 'WO';
}
