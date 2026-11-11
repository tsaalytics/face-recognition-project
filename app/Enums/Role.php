<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Hr = 'hr';
    case Employee = 'employee';
}
