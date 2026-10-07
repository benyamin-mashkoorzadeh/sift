<?php

namespace App\Exceptions\Review;

use RuntimeException;

class ReviewPersistenceException extends RuntimeException
{
    public const USER_MESSAGE = 'Sift could not save this question for review right now. Please try again.';
}
