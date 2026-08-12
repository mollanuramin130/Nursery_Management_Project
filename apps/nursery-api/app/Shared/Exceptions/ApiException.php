<?php

namespace App\Shared\Exceptions;

use Exception;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ApiException extends Exception implements HttpExceptionInterface
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 400,
        private readonly string $errorCode = 'BAD_REQUEST',
        private readonly mixed $data = null,
        private readonly mixed $errors = null,
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return [];
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function data(): mixed
    {
        return $this->data;
    }

    public function errors(): mixed
    {
        return $this->errors;
    }

    public static function inventoryInsufficient(int $availableQty): self
    {
        return new self(
            message: 'Insufficient inventory',
            statusCode: 409,
            errorCode: 'INVENTORY_INSUFFICIENT',
            data: ['available_qty' => $availableQty],
        );
    }
}
