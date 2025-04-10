<?php

namespace tests;

use PHPUnit\Framework\TestCase;
use tob\OrderBook;

class OrderBookTest extends TestCase
{
    private $orderBook;

    protected function setUp(): void
    {
        $this->orderBook = new OrderBook();
    }

    // 1. Тест добавления новой заявки
    public function testAddNewOrder()
    {
        $output = $this->orderBook->processOrder("A;1;0;55;B;100;2;2");
        $this->assertEquals("55;B;100;2", $output);
    }

    // 2. Тест снятия заявки
    public function testCancelOrder()
    {
        $this->orderBook->processOrder("A;1;0;55;B;100;2;2");
        $output = $this->orderBook->processOrder("A;1;1;55;B;100;2;0");
        $this->assertEquals("55;B;0;0", $output);
    }

    // 3. Тест частичного исполнения
    public function testPartialExecution()
    {
        $this->orderBook->processOrder("A;1;0;55;B;100;2;2");
        $output = $this->orderBook->processOrder("A;1;2;55;B;100;1;1");
        $this->assertEquals("55;B;100;1", $output);
    }

    // 4. Тест обновления лучшей цены
    public function testBestPriceUpdate()
    {
        $this->orderBook->processOrder("A;1;0;55;B;100;2;2");
        $output = $this->orderBook->processOrder("A;2;0;55;B;101;3;3");
        $this->assertEquals("55;B;101;3", $output);
    }

    // 5. Тест отсутствия заявок
    public function testNoOrders()
    {
        // Добавляем и затем снимаем заявку
        $this->orderBook->processOrder("A;1;0;55;B;100;2;2");
        $output = $this->orderBook->processOrder("A;1;1;55;B;100;2;0");
        $this->assertEquals("55;B;0;0", $output);
    }

    // 6. Тест из задания (полный сценарий)
    public function testFullScenario()
    {
        $inputs = [
            "A;1;0;55;B;100;2;2" => "55;B;100;2",
            "A;2;0;55;B;101;3;3" => "55;B;101;3",
            "B;1;0;55;B;99;10;10" => null,
            "A;2;1;55;B;101;3;0" => "55;B;100;2",
            "B;2;0;55;B;100;3;3" => "55;B;100;5",
            "A;1;2;55;B;100;1;1" => "55;B;100;4"
        ];

        foreach ($inputs as $input => $expected) {
            $output = $this->orderBook->processOrder($input);
            $this->assertEquals($expected, $output);
        }
    }
}