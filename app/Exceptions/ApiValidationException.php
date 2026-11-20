<?php

namespace App\Exceptions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A 422 with field errors AND its own stable `code`, for failures the frontend
 * must tell apart from plain invalid input, such as `registration_rejected`.
 * The envelope is the usual { message, errors, code } (see ErrorResponder).
 */
class ApiValidationException extends ValidationException
{
    /**
     * @param  array<string, array<int, string>|string>  $messages  field => message(s)
     */
    public function __construct(private readonly string $errorCode, string $message, array $messages)
    {
        $validator = Validator::make([], []);

        foreach ($messages as $field => $list) {
            foreach ((array) $list as $text) {
                $validator->errors()->add($field, $text);
            }
        }

        parent::__construct($validator);

        $this->message = $message;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
