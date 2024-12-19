<?php

require 'vendor/autoload.php';

use TelegramBot\Api\BotApi;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$token = $_ENV['TELEGRAM_BOT_TOKEN'];

$webhookUrl = 'https://781f-95-59-153-178.ngrok-free.app/bot.php';

$bot = new BotApi($token);

try {
    $bot->setWebhook($webhookUrl);
    echo "Webhook установлен: $webhookUrl\n";
} catch (Exception $e) {
    echo "Ошибка установки Webhook: " . $e->getMessage() . "\n";
}