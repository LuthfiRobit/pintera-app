<?php

namespace App\Exceptions;

class BriApiException extends \RuntimeException
{
    public function __construct(
        public readonly string $responseCode,
        public readonly string $responseMessage,
    ) {
        parent::__construct("BRI SNAP API error [{$responseCode}]: {$responseMessage}");
    }
}
