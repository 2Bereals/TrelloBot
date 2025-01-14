<?

require_once('config.php');
require_once('inc/Trello.php');

$trello = new TrelloClient(TRELLO_API_KEY, TRELLO_API_TOKEN);

if (isset($_GET['init'])) {
    $response = file_get_contents("https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/setWebhook?url=" . TELEGRAM_WEBHOOK_URL);
}

if (isset($_GET['list'])) {
    try {
        $boards = $trello->getBoards();
        foreach ($boards as $board) {
            echo "Дошка: {$board['name']} (ID: {$board['id']})\n";
        }
    } catch (Exception $e) {
        echo "Помилка: " . $e->getMessage() . "\n";
    }
}

if (isset($_GET['trelloweb'])) {
    $trello->addWebhookToBoard(TRELLO_BOARD, TRELLO_CALLBACK);
}
