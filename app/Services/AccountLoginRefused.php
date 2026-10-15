<?php

namespace App\Services;

use RuntimeException;

/**
 * "This person may not sign in", with a plain message for them and a short code the caller can act on (for example
 * `email_not_confirmed` lets a sign in page offer "send the link again"). Thrown by AccountAuthService::authenticate().
 */
class AccountLoginRefused extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason, public readonly ?string $email = null)
    {
        parent::__construct($message);
    }
}
