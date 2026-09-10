<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case StaffInput = 'staff_input';
    case Viewer = 'viewer';
}
