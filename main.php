<?php

require_once('config.php');
require_once('inc/DB.php');
require_once('inc/Telegram.php');
require_once('inc/Trello.php');

$telegram = new TelegramBot(TELEGRAM_API_TOKEN);
$trello = new TrelloClient(TRELLO_API_KEY, TRELLO_API_TOKEN);
$DB = new Database(DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD);

$boardId = TRELLO_BOARD;

$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $firstName = $update['message']['chat']['first_name'];
    $text = $update['message']['text'];

    if ($text == '/start') {
        if (str_contains( $chatId, '-') ) die;

        $telegram->sendMessage($chatId, "Привіт, " . $firstName . "!");
        $telegram->sendMessage($chatId, 'Введіть ваш емейл');
        $trello->upsertBoard($DB, $boardId, $chatId, $firstName);

    } elseif (str_contains( $text, '@') ){
        if (str_contains( $chatId, '-') ) die;

        if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
            $telegram->sendMessage($chatId, 'Емейл додано');
            $trello->upsertBoard($DB, $boardId, $chatId, $firstName, $text);
            $trello->addMemberToBoard($boardId, $text);
            $boardUrl = $trello->getBoardUrl($boardId);
            $telegram->sendMessage($chatId, 'Ваш лінк на дошку '.$boardUrl);

        }else{
            $telegram->sendMessage($chatId, 'Не вірний формат');
        }

    } elseif (str_contains( $text, '/col ') ){
        $name = implode(' ', array_slice(explode(' ', $text), 1));
        $col = $trello->createColumns($boardId, [$name]);
        if (!empty($col)) {
            $telegram->sendMessage($chatId, 'Колонка створена '.$name );
        }else{
            $telegram->sendMessage($chatId, 'Помилка');
        }
        
    } elseif (str_contains( $text, '/card ') ){
        $name =implode(' ', array_slice(explode(' ', $text), 1));
        if (!empty($name)) {
            $trello->addCard($boardId, $name);
            $telegram->sendMessage($chatId, 'Картка створена '.$name );
        }else{
            $telegram->sendMessage($chatId, 'Помилка');
        }

    }elseif (str_contains( $text, '/bind') ) {
        if (!str_contains( $chatId, '-') ) die;

        $sql = "INSERT INTO chats (chat_id, board_id) 
        VALUES (:chat_id, :board_id)";
        $params = [
            ':chat_id' => $chatId,
            ':board_id' => $boardId
        ];
        $DB->execute($sql, $params);
        $telegram->sendMessage($chatId, 'Чат успішно звязано з дошкой' );
        
    }else{
        $telegram->sendMessage($chatId, 'Помилка');

    }

} 