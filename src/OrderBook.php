<?php

namespace tob;
class OrderBook
{
    private $instruments = [];

    public function processOrder(string $inputLine): ?string
    {
        [$user_id, $clorder_id, $action, $instrument_id, $side, $price, $amount, $amount_rest] = explode(';', $inputLine);

        $instrument_id = (int)$instrument_id;
        $price = (float)$price;
        $amount_rest = (int)$amount_rest;

        if (!isset($this->instruments[$instrument_id])) {
            $this->instruments[$instrument_id] = [
                'B' => ['orders' => [], 'prices' => []],
                'S' => ['orders' => [], 'prices' => []]
            ];
        }

        $instrument = &$this->instruments[$instrument_id][$side];
        $orderKey = "$user_id:$clorder_id";
        $isBid = ($side === 'B');

        // Сохраняем предыдущие лучшие значения ДО изменений
        $prevBestPrice = $instrument['prices'][0] ?? null;
        $prevTotalAmount = $this->calculateTotalAmount($instrument, $prevBestPrice);

        switch ($action) {
            case '0': // Новая заявка
                $instrument['orders'][$orderKey] = ['price' => $price, 'amount_rest' => $amount_rest];
                $this->insertPriceSorted($instrument['prices'], $price, $isBid);
                break;

            case '1': // Удаление
                if (!isset($instrument['orders'][$orderKey])) break;
                $price = $instrument['orders'][$orderKey]['price'];
                unset($instrument['orders'][$orderKey]);
                $this->cleanupPrice($instrument, $price);
                break;

            case '2': // Исполнение
                if (!isset($instrument['orders'][$orderKey])) break;
                $oldPrice = $instrument['orders'][$orderKey]['price'];
                $instrument['orders'][$orderKey]['amount_rest'] = $amount_rest;

                if ($amount_rest === 0) {
                    unset($instrument['orders'][$orderKey]);
                    $this->cleanupPrice($instrument, $oldPrice);
                }
                break;
        }

        // Получаем текущие лучшие значения ПОСЛЕ изменений
        $currentBestPrice = $instrument['prices'][0] ?? null;
        $currentTotalAmount = $this->calculateTotalAmount($instrument, $currentBestPrice);

        // Возвращаем обновление, если изменилось
        if ($prevBestPrice != $currentBestPrice || $prevTotalAmount != $currentTotalAmount) {
            $outputPrice = $currentBestPrice ?? ($isBid ? 0 : 9999999999999999);
            $outputAmount = $currentBestPrice ? $currentTotalAmount : 0;
            return sprintf("%d;%s;%s;%d", $instrument_id, $side, $outputPrice, $outputAmount);
        }

        return null;
    }

    private function insertPriceSorted(array &$prices, float $price, bool $isBid): void
    {
        // Для покупок (Bid) сортируем по убыванию, для продаж (Ask) - по возрастанию
        $comparator = $isBid
            ? fn($a, $b) => $b <=> $a  // Убывание
            : fn($a, $b) => $a <=> $b; // Возрастание

        // Ищем место для вставки
        $low = 0;
        $high = count($prices) - 1;
        $index = count($prices); // По умолчанию в конец

        while ($low <= $high) {
            $mid = (int)(($low + $high) / 2);
            $cmp = $comparator($prices[$mid], $price);

            if ($cmp === 0) {
                $index = $mid;
                break;
            } elseif ($cmp < 0) {
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
                $index = $mid;
            }
        }

        // Вставляем цену, если её ещё нет в массиве
        if (!in_array($price, $prices, true)) {
            array_splice($prices, $index, 0, $price);
        }
    }


    private function cleanupPrice(array &$instrument, float $price): void
    {
        // Удаляем цену, если больше нет заявок с такой ценой
        $hasOrdersWithPrice = false;
        foreach ($instrument['orders'] as $order) {
            if ($order['price'] == $price) {
                $hasOrdersWithPrice = true;
                break;
            }
        }

        if (!$hasOrdersWithPrice) {
            $key = array_search($price, $instrument['prices'], true);
            if ($key !== false) {
                array_splice($instrument['prices'], $key, 1);
            }
        }
    }

    private function calculateTotalAmount(array $instrument, ?float $price): int
    {
        if ($price === null) return 0;

        $total = 0;
        foreach ($instrument['orders'] as $order) {
            // Сравнение с учётом возможных ошибок округления float
            if (abs($order['price'] - $price) < 0.00001) {
                $total += $order['amount_rest'];
            }
        }
        return $total;
    }
}
