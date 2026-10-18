<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a request action breaks a business rule (single-request rule,
 * inactive document type, duplicate payment reference, wrong status, ...).
 * The message is written for the student and is safe to show.
 */
class RequestNotAllowedException extends RuntimeException
{
    public static function activeRequestExists(): self
    {
        return new self(
            'You already have an ongoing request or a completed request that has not been archived. '
            .'Archive your completed request (or wait for the current one to finish) before submitting a new one.'
        );
    }

    public static function documentUnavailable(): self
    {
        return new self('This document type is not available for request right now.');
    }

    public static function duplicatePaymentReference(): self
    {
        return new self('This payment reference number has already been used on another request.');
    }

    public static function invalidState(string $action): self
    {
        return new self("This request can no longer be {$action}.");
    }
}
