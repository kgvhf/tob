<?php

namespace tob;

class OrderBook
{
    private const B = 'B';
    private const S = 'S';

    private const NO_SELL_PRICE = 9999999999999999;

    /**
     * @var InstrumentManager[][]
     */
    private $instruments = [];

    public function processOrder(string $inputLine): ?string
    {
        [$user_id, $clorder_id, $action, $instrument_id, $side, $price, $amount, $amount_rest] = explode(';', $inputLine);

        $instrument_id = (int)$instrument_id;
        $price = (float)$price;
        $amount_rest = (int)$amount_rest;

        if (!isset($this->instruments[$instrument_id])) {
            $this->instruments[$instrument_id] = [
                self::B => new InstrumentManager(true),
                self::S => new InstrumentManager(false)
            ];
        }

        $instrument = $this->instruments[$instrument_id][$side];
        $orderKey = "$user_id:$clorder_id";
        $isBid = ($side === self::B);

        // Сохраняем предыдущие лучшие значения ДО изменений
        $prevBestPrice = $instrument->getBestPrice();
        $prevTotalAmount = $instrument->calculateTotalAmount($prevBestPrice);

        switch ($action) {
            case '0': // Новая заявка
                $instrument->addOrder($orderKey, $price, $amount_rest);
                break;

            case '1': // Удаление
                $instrument->removeOrder($orderKey);
                break;

            case '2': // Исполнение
                $instrument->executeOrder($orderKey, $amount_rest);
                break;
        }

        // Получаем текущие лучшие значения ПОСЛЕ изменений
        $currentBestPrice = $instrument->getBestPrice();
        $currentTotalAmount = $instrument->calculateTotalAmount($currentBestPrice);

        // Возвращаем обновление, если изменилось
        if ($prevBestPrice != $currentBestPrice || $prevTotalAmount != $currentTotalAmount) {
            $outputPrice = $currentBestPrice ?? ($isBid ? 0 : self::NO_SELL_PRICE);
            $outputAmount = $currentBestPrice ? $currentTotalAmount : 0;
            return sprintf("%d;%s;%s;%d", $instrument_id, $side, $outputPrice, $outputAmount);
        }

        return null;
    }
}
