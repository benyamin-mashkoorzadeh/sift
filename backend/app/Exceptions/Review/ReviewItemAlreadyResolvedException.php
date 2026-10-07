<?php

namespace App\Exceptions\Review;

use RuntimeException;

class ReviewItemAlreadyResolvedException extends RuntimeException
{
    public const USER_MESSAGE = 'This review item has already been resolved.';

    public function __construct()
    {
        parent::__construct(self::USER_MESSAGE);
    }
}
