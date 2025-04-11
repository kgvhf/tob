<?php

namespace tob;

class InstrumentManager
{
    private $orders = [];
    private $prices;

    public function __construct(bool $isBid)
    {
        $this->prices = new PriceManager($isBid);
    }

    public function addOrder(string $orderKey, float $price, int $amount_rest): void
    {
        $this->orders[$orderKey] = ['price' => $price, 'amount_rest' => $amount_rest];
        $this->prices->insertPrice($price);
    }

    public function removeOrder(string $orderKey): void
    {
        if (!isset($this->orders[$orderKey])) return;

        $price = $this->orders[$orderKey]['price'];
        unset($this->orders[$orderKey]);
        $this->prices->removePrice($price);
    }

    public function executeOrder(string $orderKey, int $amount_rest): void
    {
        if (!isset($this->orders[$orderKey])) return;

        $this->orders[$orderKey]['amount_rest'] = $amount_rest;

        if ($amount_rest === 0) {
            $this->removeOrder($orderKey);
        }
    }

    public function getBestPrice(): ?float
    {
        return $this->prices->getBestPrice();
    }

    public function calculateTotalAmount(?float $price): int
    {
        if ($price === null) return 0;

        $total = 0;
        foreach ($this->orders as $order) {
            if (abs($order['price'] - $price) < 0.00001) {
                $total += $order['amount_rest'];
            }
        }
        return $total;
    }
}