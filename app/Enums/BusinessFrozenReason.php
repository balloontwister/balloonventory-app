<?php

namespace App\Enums;

/**
 * Why a business is frozen. Lets the ownership-transfer flow auto-thaw only the
 * freeze it created, without un-suspending a business an admin froze for a
 * different reason (e.g. non-payment).
 */
enum BusinessFrozenReason: string
{
    case Suspended = 'suspended';
    case OwnershipTransfer = 'ownership_transfer';
    case Ownerless = 'ownerless';
}
