<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * An expected, client-facing failure with its own stable `code` for the
 * error envelope, e.g. new ApiException(401, 'invalid_credentials', '...').
 * Its message is shown to the client, so never put internals in it.
 */
class ApiException extends HttpException
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(int $status, private readonly string $errorCode, string $message, array $headers = [])
    {
        parent::__construct($status, $message, null, $headers);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
