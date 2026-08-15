<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Thrown by Wallet::topup() when the wallet balance credit itself has already
 * committed successfully, but the subsequent AutoAllocationEngine::run() step
 * (invoked outside that transaction) throws.
 *
 * Callers MUST distinguish this from a genuine topup failure: the money is
 * already safely credited to the wallet when this exception is thrown, so it
 * must never be treated as "wallet not credited, please retry the topup".
 */
class AutoAllocationFailedException extends Exception
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
