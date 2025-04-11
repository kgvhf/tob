<?php

namespace tob;

use SplMaxHeap;
use SplMinHeap;

class PriceManager
{
    private $heap;

    public function __construct(bool $isBid)
    {
        $this->heap = $isBid ? new SplMaxHeap() : new SplMinHeap();
    }

    public function insertPrice(float $price): void
    {
        $this->heap->insert($price);
    }

    public function removePrice(float $price): void
    {
        $newHeap = $this->heap instanceof SplMaxHeap ? new SplMaxHeap() : new SplMinHeap();
        foreach ($this->heap as $heapPrice) {
            if ($heapPrice !== $price) {
                $newHeap->insert($heapPrice);
            }
        }
        $this->heap = $newHeap;
    }

    public function getBestPrice(): ?float
    {
        return $this->heap->isEmpty() ? null : $this->heap->top();
    }

    public function isEmpty(): bool
    {
        return $this->heap->isEmpty();
    }
}