<?php

/**
 * SSO Client SDK - Validation Exception
 *
 * (c) 2024-2025 Today is Life GmbH
 * Author: Guido Mitschke
 * License: MIT
 */

declare(strict_types=1);

namespace TodayIsLife\SsoClient\Exceptions;

/**
 * Exception thrown when input validation fails
 */
class ValidationException extends SsoClientException
{
    private array $errors;

    public function __construct(
        string $message = 'Validation failed',
        array $errors = [],
        int $code = 400,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Check if a specific field has errors
     */
    public function hasFieldErrors(string $field): bool
    {
        return !empty($this->errors[$field]);
    }

    /**
     * Get all error messages as flat array
     */
    public function getAllErrorMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $fieldErrors) {
            if (is_array($fieldErrors)) {
                $messages = array_merge($messages, $fieldErrors);
            } else {
                $messages[] = $fieldErrors;
            }
        }
        return $messages;
    }

    /**
     * Get formatted error message including field errors
     */
    public function getFormattedMessage(): string
    {
        $message = $this->getMessage();

        if (!empty($this->errors)) {
            $errorMessages = $this->getAllErrorMessages();
            $message .= ': ' . implode(', ', $errorMessages);
        }

        return $message;
    }
}