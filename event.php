<?php

require_once('config.php');
require_once('inc/DB.php');
require_once('inc/Telegram.php');
require_once('inc/Trello.php');

$telegram = new TelegramBot(TELEGRAM_API_TOKEN);
$trello = new TrelloClient(TRELLO_API_KEY, TRELLO_API_TOKEN);
$DB = new Database(DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD);

$boardId = TRELLO_BOARD;

$data = $trello->handleRequest();
if (empty(!$data)) {
    $card = $data['card'];
    $column = $data['column'];
    if(empty($card) || empty($column) ) die;
    $text = " ".$card." переміщено ".$column." ";

    $sql = "SELECT chat_id FROM chats WHERE board_id = :board_id";
    $params = [
        ':board_id' => $boardId
    ];
    $result = $DB->fetch($sql, $params);
    
    if (!empty($result)) {
        $chatId = $result['chat_id'];
        $telegram->sendMessage($chatId, $text);
    }

}


