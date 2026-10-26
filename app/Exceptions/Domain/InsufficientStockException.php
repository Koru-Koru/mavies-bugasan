<?php

namespace App\Exceptions\Domain;

class InsufficientStockException extends DomainException
{
    public static function for(int $inventoryId, int $requested, int $available): self
    {
        return new self(
            "Not enough stock: requested {$requested} sack(s) but only {$available} available.",
            ['inventory_id' => $inventoryId, 'requested' => $requested, 'available' => $available]
        );
    }
}
