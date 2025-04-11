<?php

namespace tob;

class OrderBook
{
    private const B = 'B';
    private const S = 'S';

    private const NO_SELL_PRICE = 9999999999999999;
    private $instruments = [];

    public function processOrder(string $inputLine): ?string
    {
        [$user_id, $clorder_id, $action, $instrument_id, $side, $price, $amount, $amount_rest] = explode(';', $inputLine);

        $instrument_id = (int)$instrument_id;
        $price = (float)$price;
        $amount_rest = (int)$amount_rest;

        if (!isset($this->instruments[$instrument_id])) {
            $this->instruments[$instrument_id] = [
                self::B => ['orders' => [], 'prices' => new PriceManager(true)],
                self::S => ['orders' => [], 'prices' => new PriceManager(false)]
            ];
        }

        $instrument = &$this->instruments[$instrument_id][$side];
        $orderKey = "$user_id:$clorder_id";
        $isBid = ($side === self::B);

        // Сохраняем предыдущие лучшие значения ДО изменений
        $prevBestPrice = $instrument['prices']->getBestPrice();
        $prevTotalAmount = $this->calculateTotalAmount($instrument, $prevBestPrice);

        switch ($action) {
            case '0': // Новая заявка
                $this->addOrder($instrument, $orderKey, $price, $amount_rest);
                break;

            case '1': // Удаление
                $this->removeOrder($instrument, $orderKey);
                break;

            case '2': // Исполнение
                $this->executeOrder($instrument, $orderKey, $amount_rest);
                break;
        }

        // Получаем текущие лучшие значения ПОСЛЕ изменений
        $currentBestPrice = $instrument['prices']->getBestPrice();
        $currentTotalAmount = $this->calculateTotalAmount($instrument, $currentBestPrice);

        // Возвращаем обновление, если изменилось
        if ($prevBestPrice != $currentBestPrice || $prevTotalAmount != $currentTotalAmount) {
            $outputPrice = $currentBestPrice ?? ($isBid ? 0 : self::NO_SELL_PRICE);
            $outputAmount = $currentBestPrice ? $currentTotalAmount : 0;
            return sprintf("%d;%s;%s;%d", $instrument_id, $side, $outputPrice, $outputAmount);
        }

        return null;
    }

    private function addOrder(array &$instrument, string $orderKey, float $price, int $amount_rest): void
    {
        $instrument['orders'][$orderKey] = ['price' => $price, 'amount_rest' => $amount_rest];
        $instrument['prices']->insertPrice($price);
    }

    private function removeOrder(array &$instrument, string $orderKey): void
    {
        if (!isset($instrument['orders'][$orderKey])) return;

        $price = $instrument['orders'][$orderKey]['price'];
        unset($instrument['orders'][$orderKey]);
        $instrument['prices']->removePrice($price);
    }

    private function executeOrder(array &$instrument, string $orderKey, int $amount_rest): void
    {
        if (!isset($instrument['orders'][$orderKey])) return;

        $price = $instrument['orders'][$orderKey]['price'];
        $instrument['orders'][$orderKey]['amount_rest'] = $amount_rest;

        if ($amount_rest === 0) {
            unset($instrument['orders'][$orderKey]);
            $instrument['prices']->removePrice($price);
        }
    }

    private function calculateTotalAmount(array $instrument, ?float $price): int
    {
        if ($price === null) return 0;

        $total = 0;
        foreach ($instrument['orders'] as $order) {
            if (abs($order['price'] - $price) < 0.00001) {
                $total += $order['amount_rest'];
            }
        }
        return $total;
    }
}
