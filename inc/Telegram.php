<?php

class TelegramBot
{
    private $apiUrl;

    public function __construct($token)
    {
        $this->apiUrl = "https://api.telegram.org/bot$token/";
    }

    /**
     * Надіслати повідомлення в чат
     * @param int|string $chatId ID чату або username
     * @param string $message Текст повідомлення
     * @param array $options Додаткові параметри (наприклад, клавіатура)
     * @return array Відповідь API
     */
    public function sendMessage($chatId, $message, $options = [])
    {
        $data = array_merge($options, [
            'chat_id' => $chatId,
            'text' => $message,
        ]);
        return $this->apiRequest("sendMessage", $data);
    }

    /**
     * Отримати оновлення від Telegram (webhook або long polling)
     * @return array|false Оновлення або false, якщо нічого немає
     */
    public function getUpdates($offset = 0, $timeout = 10)
    {
        $data = [
            'offset' => $offset,
            'timeout' => $timeout,
        ];
        return $this->apiRequest("getUpdates", $data);
    }

    /**
     * Відправити запит до Telegram API
     * @param string $method Метод API
     * @param array $data Параметри запиту
     * @return array Відповідь API
     */
    private function apiRequest($method, $data = [])
    {
        $url = $this->apiUrl . $method;
        $options = [
            'http' => [
                'header'  => "Content-Type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($data),
            ],
        ];
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        return $response ? json_decode($response, true) : false;
    }
    
}
