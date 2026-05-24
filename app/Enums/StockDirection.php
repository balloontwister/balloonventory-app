<?php

namespace App\Enums;

enum StockDirection: string
{
    case In = 'in';
    case Out = 'out';
    case Removed = 'removed';       // SKU soft-deleted from this business's inventory
    case Restored = 'restored';     // SKU re-added to inventory after removal
    case Adjusted = 'adjusted';     // Manual quantity correction (future)
}
