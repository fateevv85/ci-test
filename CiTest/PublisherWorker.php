<?php

namespace CiTest;

use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Класс для отправки сообщений в очередь
 * Class PublisherWorker
 * @package CiTest
 */
class PublisherWorker extends AbstractWorker
{
    /**
     * Отправляет сообщение в очередь
     * @throws Exception
     */
    public function execute(): void
    {
        $connection = new AMQPStreamConnection(...$this->config);
        $channel = $connection->channel();

        $table = new AMQPTable();
        $table->set('x-max-priority', $this->maxPriority);

        $channel->queue_declare($this->queue, false, true, false, false, false, $table);

        if (!key_exists('branch_id', $_POST) || !key_exists('action', $_POST)) {
            $errorMessage = 'Не хватает параметров';

            BranchHandler::log($errorMessage);
            throw new Exception($errorMessage);
        }

        $priority = ($_POST['action'] == 'add') ? 1 : 2;
        $messageData = json_encode($_POST);

        $msg = new AMQPMessage(
            $messageData,
            [
                'delivery_mode' => 2, //создаёт сообщение постоянным, чтобы оно не потерялось при падении или закрытии сервера
                'priority' => $priority,
            ]
        );

        $channel->basic_publish($msg, '', $this->queue);

        BranchHandler::log("Запрос $messageData с приоритетом $priority отправлен в очередь $this->queue");

        $channel->close();
        $connection->close();
    }
}
