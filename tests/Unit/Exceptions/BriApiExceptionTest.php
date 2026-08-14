<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\BriApiException;
use PHPUnit\Framework\TestCase;

class BriApiExceptionTest extends TestCase
{
    public function test_exposes_response_code_and_message()
    {
        $exception = new BriApiException('4007301', 'Invalid Field Format');

        $this->assertSame('4007301', $exception->responseCode);
        $this->assertSame('Invalid Field Format', $exception->responseMessage);
        $this->assertSame('BRI SNAP API error [4007301]: Invalid Field Format', $exception->getMessage());
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
