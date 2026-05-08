<?php

namespace App\Exceptions;

use RuntimeException;

class LastOwnerGuardException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'This business needs at least one Owner. Promote another member to Owner before changing your role.'
        );
    }
}
