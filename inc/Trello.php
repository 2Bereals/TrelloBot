<?php

class TrelloClient
{
    private $apiKey;
    private $apiToken;
    private $apiUrl = 'https://api.trello.com/1';

    public function __construct($apiKey, $apiToken)
    {
        $this->apiKey = $apiKey;
        $this->apiToken = $apiToken;
    }

    /**
     * Виконання запиту до API Trello
     * @param string $endpoint API-метод
     * @param string $method HTTP-метод (GET, POST, PUT, DELETE)
     * @param array $data Дані запиту
     * @return array|null Відповідь API
     */
    private function apiRequest($endpoint, $method = 'GET', $data = [])
    {
        $url = $this->apiUrl . $endpoint . '?key=' . $this->apiKey . '&token=' . $this->apiToken;

        if ($method === 'GET' && !empty($data)) {
            $url .= '&' . http_build_query($data);
        }

        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n",
                'method' => $method,
                'content' => $method === 'GET' ? null : json_encode($data)
            ],
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        return $response ? json_decode($response, true) : null;
    }

    /**
     * Отримання списку дошок користувача
     * @return array Список дошок
     */
    public function getBoards()
    {
        return $this->apiRequest('/members/me/boards');
    }

    /**
     * Отримання списків на дошці
     * @param string $boardId ID дошки
     * @return array Список списків
     */
    public function getListsOnBoard($boardId)
    {
        return $this->apiRequest("/boards/$boardId/lists");
    }

    /**
     * Створення нової картки
     * @param string $listId ID списку
     * @param string $name Назва картки
     * @param string $desc Опис картки
     * @return array Відповідь API
     */
    public function createCard($listId, $name, $desc = '')
    {
        $data = [
            'idList' => $listId,
            'name' => $name,
            'desc' => $desc
        ];
        return $this->apiRequest('/cards', 'POST', $data);
    }

    /**
     * Переміщення картки до іншого списку
     * @param string $cardId ID картки
     * @param string $newListId ID нового списку
     * @return array Відповідь API
     */
    public function moveCard($cardId, $newListId)
    {
        $data = [
            'idList' => $newListId,
        ];
        return $this->apiRequest("/cards/$cardId", 'PUT', $data);
    }

    /**
     * Отримання посилання на дошку Trello по її ID
     * @param string $boardId ID дошки Trello
     * @return string Ссилка на дошку
     */
    public function getBoardUrl($boardId)
    {
        return "https://trello.com/b/$boardId";
    }

    /**
     * Отримання інформації про картку
     * @param string $cardId ID картки
     * @return array Дані картки
     */
    public function getCard($cardId)
    {
        return $this->apiRequest("/cards/$cardId");
    }

    /**
     * Додавання або оновлення запису в базі даних для дошки
     * @param object $db Екземпляр класу для роботи з базою даних
     * @param string $boardId ID дошки (board_id)
     * @param string $telegramId ID користувача Telegram (telegram_id)
     * @param string|null $email Електронна пошта, пов'язана з дошкою (може бути null)
     * @return void
     */
    public function upsertBoard($db, $boardId, $telegramId, $name,  $email = null)
    {
        $sql = "INSERT INTO boards (board_id, telegram_id, email, first_name) 
                VALUES (:board_id, :telegram_id, :email , :first_name)
                ON DUPLICATE KEY UPDATE 
                    email = VALUES(email)";
        $params = [
            ':board_id' => $boardId,
            ':telegram_id' => $telegramId,
            ':email' => $email,
            ':first_name' => $name
        ];
        $db->execute($sql, $params);
    }

    /**
     * Надати користувачу доступ до дошки Trello за email
     * @param string $boardId ID дошки Trello
     * @param string $email Email користувача
     * @param string $role Роль користувача на дошці (normal, admin, observer)
     * @return array|null Відповідь API
     */
    public function addMemberToBoard($boardId, $email, $role = 'normal')
    {
        $data = [
            'email' => $email,
            'type' => $role
        ];
        
        return $this->apiRequest("/boards/$boardId/members", 'PUT', $data);
    }

    /**
     * Створення колонок на дошці Trello
     * @param string $boardId ID дошки Trello
     * @param array $columns Массив назв колонок для створення
     * @return array Відповідь API з інформацією про створені колонки
     */
    public function createColumns($boardId, $columns)
    {
        $existingLists = $this->apiRequest("/boards/$boardId/lists");

        $existingColumnNames = [];
        foreach ($existingLists as $list) {
            $existingColumnNames[] = $list['name'];
        }

        $createdLists = [];

        foreach ($columns as $columnName) {
            if (in_array($columnName, $existingColumnNames)) {
                continue;
            }

            $data = [
                'name' => $columnName,
                'idBoard' => $boardId,
            ];

            $response = $this->apiRequest('/lists', 'POST', $data);
            
            if ($response && isset($response['id'])) {
                $createdLists[$columnName] = $response['id'];
            } else {
                throw new Exception("Не вдалося створити колонку: $columnName");
            }
        }

        return $createdLists;
    }

    /**
     * Додавання карток у колонки на дошці Trello
     * @param array $createdLists Масив створених колонок (назва колонки => ID колонки)
     * @param array $cards Массив карток, де ключ - назва колонки, а значення - масив назв карток
     * @return array Відповідь API з інформацією про створені картки
     */
    public function createCards($createdLists, $cards)
    {
        $createdCards = [];

        foreach ($cards as $columnName => $cardNames) {
            if (!isset($createdLists[$columnName])) {
                throw new Exception("Колонка $columnName не знайдена серед створених.");
            }

            $listId = $createdLists[$columnName];
            foreach ($cardNames as $cardName) {
                $data = [
                    'idList' => $listId,
                    'name' => $cardName,
                ];

                $response = $this->apiRequest('/cards', 'POST', $data);
                if ($response) {
                    $createdCards[$cardName] = $response;
                } else {
                    throw new Exception("Не вдалося створити картку: $cardName у колонці: $columnName");
                }
            }
        }

        return $createdCards;
    }

    /**
     * Додавання картки у першу колонку на дошці
     * @param string $boardId ID дошки
     * @param string $cardName Назва картки
     * @return array Відповідь API з інформацією про створену картку
     */
    public function addCard($boardId, $cardName)
    {
        if (!isset($boardId) || !is_string($boardId) || strlen($boardId) != 24 || !ctype_alnum($boardId)) {
            throw new Exception("Передано некоректне значення для boardId.");
        }

        $lists = $this->apiRequest("/boards/$boardId/lists");
        if (empty($lists)) {
            throw new Exception("На дошці з ID '$boardId' немає колонок.");
        }

        $firstList = reset($lists);
        if (!isset($firstList['id'])) {
            throw new Exception("Не вдалося визначити першу колонку на дошці з ID '$boardId'.");
        }

        $listId = $firstList['id'];

        $data = [
            'idList' => $listId,
            'name' => $cardName,
        ];

        $response = $this->apiRequest('/cards', 'POST', $data);
        if ($response) {
            return $response;
        } else {
            throw new Exception("Не вдалося створити картку: $cardName у першій колонці на дошці: $boardId");
        }
    }

    /**
     * Обробка запиту вебхука від Trello
     * 
     * @return array|null Масив з інформацією про картку та колонку
     */
    public function handleRequest()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (isset($data['action'])) {
            $actionType = $data['action']['type'];
            $cardName = $data['action']['data']['card']['name'] ?? '';
            $listName = $data['action']['data']['list']['name'] ?? '';

            if ($actionType === 'updateCard') {
                http_response_code(200);
                return [
                    'card' => $cardName,
                    'column' => $listName
                ];
            }
        }
        
    }

    /**
     * Додавання вебхука до дошки Trello
     * @param string $boardId ID дошки
     * @param string $callbackUrl URL-адреса, на яку будуть надсилатися події вебхука
     * @param string $description Опис вебхука
     * 
     * @return array|null Масив з інформацією про створений вебхук, або null у разі помилки
     */
    public function addWebhookToBoard($boardId, $callbackUrl, $description = 'Webhook')
    {
        if (empty($boardId) || empty($callbackUrl)) {
            throw new Exception("Параметри boardId та callbackUrl є обов'язковими.");
        }
    
        $data = [
            'idModel' => $boardId,
            'callbackURL' => $callbackUrl,
            'description' => $description,
        ];
    
        $response = $this->apiRequest('/webhooks', 'POST', $data);
        return $response;
    }

}
