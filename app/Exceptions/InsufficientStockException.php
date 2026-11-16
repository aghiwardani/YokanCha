<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(public string $productName)
    {
        parent::__construct("Insufficient stock for {$productName}");
    }
}
