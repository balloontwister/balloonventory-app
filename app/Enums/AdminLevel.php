<?php

namespace App\Enums;

enum AdminLevel: string
{
    case SiteAdmin = 'site_admin';
    case SuperAdmin = 'super_admin';
}
