<?php

namespace CiTest;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Класс для приема и обработки сообщений
 * Class ConsumerWorker
 * @package CiTest
 */
class ConsumerWorker extends AbstractWorker
{
    /**
     * Обрабатывает входящие запросы
     */
    public function listen()
    {
        $connection = new AMQPStreamConnection(...$this->config);
        $channel = $connection->channel();

        $table = new AMQPTable();
        $table->set('x-max-priority', $this->maxPriority);

        $channel->queue_declare(
            $this->queue,    // queue name - Имя очереди может содержать до 255 байт UTF-8 символов
            false,        // passive - может использоваться для проверки того, инициирован ли обмен, без того, чтобы изменять состояние сервера
            true,        // durable - убедимся, что RabbitMQ никогда не потеряет очередь при падении - очередь переживёт перезагрузку брокера
            false,        // exclusive - используется только одним соединением, и очередь будет удалена при закрытии соединения
            false,        // autodelete - очередь удаляется, когда отписывается последний подписчик
            false, // nowait If set, the server will not respond to the method. The client should not wait for a reply method. If the server could not complete the method it will raise a channel or connection exception.
            $table
        );

        echo " [*] Waiting for data. To exit press CTRL+C\n";

        $channel->basic_qos(
            0,   // размер предварительной выборки - размер окна предварительнйо выборки в октетах, null означает “без определённого ограничения”
            1,    // количество предварительных выборок - окна предварительных выборок в рамках целого сообщения
            false    // глобальный - global=null означает, что настройки QoS должны применяться для получателей, global=true означает, что настройки QoS должны применяться к каналу
        );

        $channel->basic_consume(
            $this->queue,        // очередь
            '',                  // тег получателя - Идентификатор получателя, валидный в пределах текущего канала. Просто строка
            false,               // не локальный - TRUE: сервер не будет отправлять сообщения соединениям, которые сам опубликовал
            false,               // без подтверждения - false: подтверждения включены, true - подтверждения отключены. отправлять соответствующее подтверждение обработчику, как только задача будет выполнена
            false,                 // эксклюзивная - к очереди можно получить доступ только в рамках текущего соединения
            false,                 // не ждать - TRUE: сервер не будет отвечать методу. Клиент не должен ждать ответа
            [$this, 'callback']    // функция обратного вызова - метод, который будет принимать сообщение
        );

        while (count($channel->callbacks)) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    /**
     * обработка полученного запроса
     * @param AMQPMessage $msg
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function callback(AMQPMessage $msg)
    {
        $data = json_decode($msg->body, true);
        ['branch_id' => $branchId, 'action' => $action] = $data;

        echo " [x] Received message: branch_id: $branchId, action: $action \n";

        $this->sendRequest($data);
//        sleep(3);

        /**
         * Отправляем подтверждение
         *
         * Если получатель умирает, не отправив подтверждения, брокер
         * AMQP пошлёт сообщение другому получателю. Если свободных
         * на данный момент нет - брокер подождёт до тех пор, пока
         * освободится хотя-бы один зарегистрированный получатель
         * на эту очередь, прежде чем попытаться заново доставить
         * сообщение
         */
        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);

        echo " [x] Done\n";
    }

    /**
     * Отправляет POST запрос
     * @param array $message данные для запроса
     * @param string|null $url адрес для отправки
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function sendRequest(array $message, string $url = null): void
    {
//        $url = $url ?? $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/lib/controller.php?handle_branch=1';
        $url = $url ?? 'http://ci-test/lib/controller.php?handle_branch=1';

        $client = new Client();

        BranchHandler::log("Отправляем запрос на url: $url");
        BranchHandler::log('с параметрами: ');
        BranchHandler::log($message);

        try {
            $response = $client->request('POST', $url, [
                'form_params' => $message,
            ]);

            BranchHandler::log("Запрос успешно отправлен [{$response->getStatusCode()}]");
        } catch (ServerException $e) {
            BranchHandler::log('ServerException: Ошибка при отправке запроса через Guzzle');
            BranchHandler::log($e->getMessage());
            BranchHandler::log($e->getCode());
        } catch (Exception $e) {
            BranchHandler::log('Exception: Ошибка при отправке запроса через Guzzle');
            BranchHandler::log($e->getMessage());
            BranchHandler::log($e->getCode());
        }
    }
}
