<?php

require 'vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$dbHost = $_ENV['DB_HOST'];
$dbName = $_ENV['DB_NAME'];
$dbUser = $_ENV['DB_USER'];
$dbPassword = $_ENV['DB_PASSWORD'];
$dbPort = $_ENV['DB_PORT'];

$dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8";
$pdo = new PDO($dsn, $dbUser, $dbPassword);

$queries = [
    "CREATE TABLE IF NOT EXISTS users (
        id BIGINT PRIMARY KEY,
        balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        last_request TIMESTAMP NULL
    )",
    "CREATE TABLE IF NOT EXISTS transactions (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($queries as $query) {
    try {
        $pdo->exec($query);
        echo "Таблица успешно создана или уже существует.\n";
    } catch (PDOException $e) {
        echo "Ошибка при создании таблицы: " . $e->getMessage() . "\n";
    }
}