<?php

namespace App\Auth;

enum Role: string
{
    case Prensa = 'PRENSA';
    case Hacienda = 'HACIENDA';
    case SuperAdmin = 'SUPER_ADMIN';
}
