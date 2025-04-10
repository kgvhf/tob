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
    public function testHighLoadPerformance(): void {
        $startTime = microtime(true);

        // 1. Добавляем 5000 заявок на покупку
        for ($i = 1; $i <= 5000; $i++) {
            $this->orderBook->processOrder("USER_$i;$i;0;55;B;" . (100 + $i % 10) . ";10;10");
        }

        // 2. Добавляем 5000 заявок на продажу
        for ($i = 1; $i <= 5000; $i++) {
            $this->orderBook->processOrder("USER_$i;$i;0;55;S;" . (200 + $i % 10) . ";5;5");
        }

        // 3. Частично исполняем случайные заявки
        for ($i = 1; $i <= 1000; $i++) {
            $orderId = rand(1, 5000);
            $this->orderBook->processOrder("USER_$orderId;$orderId;2;55;B;;5;5");
        }

        $executionTime = microtime(true) - $startTime;
        $this->assertLessThan(1.0, $executionTime, "Время выполнения: $executionTime сек");
        echo "Обработано 10 000 заявок за $executionTime сек\n";
    }

    public function testConcurrentOrders(): void {
        // 1. Имитируем "гонку" за лучшую цену
        $output1 = $this->orderBook->processOrder("USER1;1;0;55;B;100;10;10");
        $output2 = $this->orderBook->processOrder("USER2;2;0;55;B;101;5;5");

        $this->assertEquals("55;B;101;5", $output2);
        $this->assertNotEquals($output1, $output2);
    }

    public function testExtremePrices(): void {
        $outputBid = $this->orderBook->processOrder("USER1;1;0;55;B;" . PHP_FLOAT_MAX . ";1;1");
        $outputAsk = $this->orderBook->processOrder("USER2;2;0;55;S;" . PHP_FLOAT_MIN . ";1;1");

        $this->assertEquals("55;B;" . PHP_FLOAT_MAX . ";1", $outputBid);
        $this->assertEquals("55;S;" . PHP_FLOAT_MIN . ";1", $outputAsk);
    }
    public function testCancelAllOrders(): void {
        // 1. Добавляем заявки
        $this->orderBook->processOrder("USER1;1;0;55;B;100;10;10");
        $this->orderBook->processOrder("USER2;2;0;55;B;101;5;5");

        // 2. Отменяем все
        $this->orderBook->processOrder("USER1;1;1;55;B;100;10;0");
        $output = $this->orderBook->processOrder("USER2;2;1;55;B;101;5;0");

        $this->assertEquals("55;B;0;0", $output);
    }
    public function testMemoryLeak(): void {
        $initialMemory = memory_get_usage();

        for ($i = 1; $i <= 1000000; $i++) {
            $this->orderBook->processOrder("USER_$i;$i;0;55;B;100;10;10");
            $this->orderBook->processOrder("USER_$i;$i;1;55;B;100;10;0");
        }

        $memoryUsage = memory_get_usage() - $initialMemory;
        $this->assertLessThan(1024 * 1024, $memoryUsage, "Потребление памяти: $memoryUsage байт");
    }
}