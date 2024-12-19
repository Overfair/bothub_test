<?php

require 'vendor/autoload.php';

use TelegramBot\Api\BotApi;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$token = $_ENV['TELEGRAM_BOT_TOKEN'];
$bot = new BotApi($token);

$dbHost = $_ENV['DB_HOST'];
$dbName = $_ENV['DB_NAME'];
$dbUser = $_ENV['DB_USER'];
$dbPassword = $_ENV['DB_PASSWORD'];
$dbPort = $_ENV['DB_PORT'];

$dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8";
$pdo = new PDO($dsn, $dbUser, $dbPassword);

function getUser($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $stmt = $pdo->prepare("INSERT INTO users (id, balance) VALUES (:id, 0.00)");
        $stmt->execute(['id' => $userId]);
        $user = ['id' => $userId, 'balance' => 0.00];
    }

    return $user;
}

function updateBalance($pdo, $userId, $amount) {
    // Задаем самый высокий уровень изоляции
    $pdo->exec("SET TRANSACTION ISOLATION LEVEL SERIALIZABLE");
    $pdo->beginTransaction();

    try {
        // Проверяем на отрицательный баланс
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + :amount WHERE id = :id AND balance + :amount >= 0");
        $stmt->execute(['amount' => $amount, 'id' => $userId]);

        if ($stmt->rowCount() == 0) {
            throw new Exception("Недостаточно средств на счёте.");
        }

        // Получаем обновленный баланс
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $newBalance = $stmt->fetchColumn();

        // Создаем лог транзакции
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount) VALUES (:user_id, :amount)");
        $stmt->execute(['user_id' => $userId, 'amount' => $amount]);

        $pdo->commit();
        return $newBalance;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function checkCoolDown($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT last_request FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    $lastRequest = $stmt->fetchColumn();

    // Получаем текущее время
    $currentTimestamp = new DateTime();

    if ($lastRequest) {
        $lastRequestTimestamp = new DateTime($lastRequest);

        // Ограничиваем время между запросами (5 секунд)
        $interval = $currentTimestamp->getTimestamp() - $lastRequestTimestamp->getTimestamp();
        if ($interval < 5) {
            throw new Exception("Слишком частые запросы, пожалуйста попробуйте попозже");
        }
    }

    $stmt = $pdo->prepare("UPDATE users SET last_request = :current_time WHERE id = :id");
    $stmt->execute(['current_time' => $currentTimestamp->format('Y-m-d H:i:s'), 'id' => $userId]);
}

// Получаем содержимое запроса
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if ($update && isset($update['message'])) {
    $message = $update['message'];
    $userId = $message['chat']['id'];
    $text = str_replace(',', '.', trim($message['text'])); // Пользователь может использовать . и ,

    try {
        checkCoolDown($pdo, $userId);
        $user = getUser($pdo, $userId);

        if (is_numeric($text)) {
            $amount = (float)$text;

            // Ограничиваем сумму операции
            if (abs($amount) > 1000) {
                throw new Exception("Максимальная сумма одной операции: $1000.");
            }

            $newBalance = updateBalance($pdo, $userId, $amount);
            $bot->sendMessage($userId, "Ваш баланс: $" . number_format($newBalance, 2));
        } else {
            $bot->sendMessage($userId, "Пожалуйста, отправьте сумму снятия/пополнения.");
        }
    } catch (Exception $e) {
        $bot->sendMessage($userId, $e->getMessage());
    }
}

http_response_code(200);