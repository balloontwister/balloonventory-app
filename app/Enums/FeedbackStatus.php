<?php

namespace App\Enums;

enum FeedbackStatus: string
{
    case Open = 'open';             // Awaiting admin review
    case Resolved = 'resolved';     // Reviewed and acted on
    case Dismissed = 'dismissed';   // Reviewed and rejected / no action needed
}
