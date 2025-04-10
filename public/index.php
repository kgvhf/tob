<?php

// Пример использования
use tob\OrderBook;

require __DIR__ . '/../vendor/autoload.php';

$tob = new OrderBook();
$inputs = [
    "A;1;0;55;B;100;2;2",
    "A;2;0;55;B;101;3;3",
    "B;1;0;55;B;99;10;10",
    "A;2;1;55;B;101;3;0",
    "B;2;0;55;B;100;3;3",
    "A;1;2;55;B;100;1;1"
];

foreach ($inputs as $input) {
    $output = $tob->processOrder($input);
    echo "Вход: $input \t| Выход: " . ($output ?? "-") . "\n";
}


//$service = new \tob\ToBService();
//foreach ($inputs as $input) {
//    $service->processLine($input);
//}