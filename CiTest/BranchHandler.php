<?php

namespace CiTest;

use Exception;

/**
 * Класс для обработки действий с ветками
 * Class BranchHandler
 * @package CiTest
 */
class BranchHandler
{
    private $action;
    private $id;
    private $rcBranchName;
    private $branchName;
    private static $logPath;

    /**
     * BranchHandler конструктор
     * @param int $id номер обрабатывемой ветки
     * @param string $action действие, выполняемое с веткой
     * @throws Exception
     */
    public function __construct(int $id, string $action)
    {
        if (!in_array($action, ['add', 'remove'])) {
            throw new Exception('Некорректное действие с веткой:' . $action);
        }

        $this->auth();
        $this->id = $id;
        $this->action = $action;
        $this->branchName = "branch_$id";
    }

    /**
     * Обрабатывает действия с веткой
     * @return string название релизной ветки
     * @throws Exception
     */
    public function handleBranch(): string
    {
        self::log("Начало обработки ветки $this->branchName");

        // создаем новую релизную ветку
        $this->createNewRelBranch();

        if ($this->action == 'add') {
            // мерджим все, находящиеся в релизе
            $this->mergeAllRcBranchesToNewRc();
            // добавляем текущую
            $this->addBranchToRel();
        } else {
            // извлекаем ветку из релиза
            $this->removeBranchFromRel();
            // мерджим все, находящиеся в релизе
            $this->mergeAllRcBranchesToNewRc();
        }

        self::log('Обновляем инкремент релизной ветки');
        self::updateIncrement();

        return $this->rcBranchName;
    }

    /**
     * Извлекает ветку из релиза
     */
    private function removeBranchFromRel(): void
    {
        self::log("Извлекаем ветку $this->branchName из релиза $this->rcBranchName");

        $this->updateBranchStatus();
    }

    /**
     * Мерджит в релизную ветку добавляемую ветку
     * @return void
     * @throws Exception
     */
    private function addBranchToRel(): void
    {
        self::log("Мерджим в релизную ветку добавляемую ветку");

        $this->gitMerge([$this->branchName]);

        self::log("Мердж успешно завершен, обновляем статус ветки $this->branchName");

        $this->updateBranchStatus();
    }

    /**
     * Мерджит ветки в релизную
     * @param array $branchNames названия веток
     * @return void
     * @throws Exception
     */
    private function gitMerge(array $branchNames): void
    {
        foreach ($branchNames as $branchName) {
            self::log("Мерджим ветку $branchName в релиз");

            exec("git merge {$branchName} 2>&1", $mergeLog, $returnCode);

            if ($returnCode != 0) {
                $errorMessage = "Проблемы при мердже ветки $branchName";

                self::log($errorMessage);

                $this->logMergeError($mergeLog, $branchName);
                $this->abortMerge();
                $this->cancelRcUpdate();

                throw new Exception($errorMessage);
            }
        }
    }

    /**
     * Удаляет созданную релизную ветку
     * @throws Exception
     */
    private function cancelRcUpdate(): void
    {
        self::log("Удаляем созданную релизную ветку $this->rcBranchName");

        $this->exec('git checkout master');
        $this->exec("git branch -D $this->rcBranchName");
    }

    /**
     * Устанавливает конфиг
     * @throws Exception
     */
    private function auth(): void
    {
//        $this->exec('git config --local --list', $output);
        $this->exec('git config user.email "fateevv85@gmail.com"');
        $this->exec('git config user.name "Vasiliy"');
    }

    /**
     * Мерджит в релизную ветку все ветки, которые есть в релизе
     * @return void
     * @throws Exception
     */
    private function mergeAllRcBranchesToNewRc(): void
    {
        self::log('Мерджим в релизную ветку все ветки, которые есть в релизе');

        $this->exec("git checkout $this->rcBranchName");

        $rcBranches = self::getReleaseCandidates();
        $rcBranchNames = array_column($rcBranches, 'name');

        self::log('Ветки, которые уже есть в релизе:');
        self::log($rcBranchNames);

        if (!$rcBranchNames) {
            self::log('Веток нет');
        } else {
            $this->gitMerge($rcBranchNames);

            self::log('Ветки добавлены');
        }
    }

    /**
     * Отменяет мердж
     * @return void
     * @throws Exception
     */
    private function abortMerge(): void
    {
        self::log('Отмена мерджа');

        $this->exec('git merge --abort');
    }

    /**
     * Создает отдельный лог с информацией об ошибке
     * @param mixed $logMessage сообщение для логирования
     * @param string $problemBranch ветка, с которой произошла ошибка
     * @return void
     */
    private function logMergeError($logMessage, string $problemBranch): void
    {
        $originalLogPath = self::$logPath;
        // путь для отдельного лога
        $errorLogPath = __DIR__ . "/../logs/{$this->rcBranchName}_merge_error_" . date('Y-m-d_H-i') . '.log';

        self::log('Создаем лог с информацией об ошибке:');
        self::log($errorLogPath);
        self::logSetup($errorLogPath);
        self::log('---START---');
        self::log($logMessage, $problemBranch);
        self::log('---END---');

        self::logSetup($originalLogPath);
    }

    /**
     * Задает путь для лога
     * @param string $pathName абсолютный путь
     */
    public static function logSetup(string $pathName)
    {
        self::$logPath = $pathName;
    }

    /**
     * Логирует сообщение
     * todo Вынести в отдельный класс логгера
     * @param mixed $log сообщение для логирования
     * @param string $problemBranch ветка, с которой произошла ошибка
     */
    public static function log($log, string $problemBranch = ''): void
    {
        if (self::$logPath == '') {
            // todo check this
            self::$logPath = dirname(@$_SERVER['PWD'] . $_SERVER['SCRIPT_FILENAME']) . '/' . basename($_SERVER['SCRIPT_FILENAME'], '.php') . '.log';
        }

        $fileName = self::$logPath;
        $logPath = dirname($fileName);

        if (!is_dir($logPath)) {
            mkdir($logPath, 0777, true);
        }

        $fh = fopen($fileName, 'a');

        if ($fh) {
            $logString = date("Y.m.d H:i:s") . ' pid:' . getmypid() . ' ';

            if (is_array($log) || is_object($log)) {
                $log = print_r($log, true);
            }

            $addComment = ($problemBranch) ? "Проблема при мердже ветки $problemBranch:" : '';
            $logString .= " $addComment $log \r\n";

            fwrite($fh, $logString);
            fclose($fh);
        }
    }

    /**
     * Создает новую релизную ветку
     * @throws Exception
     */
    public function createNewRelBranch(): void
    {
        $this->exec('git checkout master');

        // получаем номер последней релизной ветки для генерации нового названия
        $lastIncrement = self::getLastIncrement();
        $this->rcBranchName = 'rc_' . ++$lastIncrement;

        $this->exec("git branch $this->rcBranchName");
        $this->exec("git checkout $this->rcBranchName");

        self::log('Создаем новую релизную ветку: ' . $this->rcBranchName);

        $this->exec('git branch', 1);
    }

    /**
     * Выполняет команду, при ошибке выкидывает исключение
     * @param string $command команда
     * @param bool $log логировать строки вывода программы
     * @throws Exception
     */
    private static function exec(string $command, $log = false): void
    {
        exec($command . ' 2>&1', $output, $status);

        if ($log) {
            self::log($output);
        }

        if ($status) {
            $message = "Ошибка при выполнении команды $command, статус [$status] : "
                . implode("\n", $output);

            throw new Exception($message);
        }
    }

    /**
     * Увеличивает порядковый номер релизной ветки
     * @return mixed
     */
    private static function updateIncrement(): void
    {
        $sql = 'update ' . self::getIncrementTableName() . ' set `increment` = `increment` + 1 where id = 1';
        Db::call()->execute($sql, []);
    }

    /**
     * Получает порядковый номер последней созданной релизной ветки
     * @return int
     * @throws Exception
     */
    public static function getLastIncrement(): int
    {
        $query = Db::call()->queryOne('select `increment` from ' . self::getIncrementTableName(), []);
        $increment = (int)$query['increment'];

        if (!is_int($increment)) {
            throw new Exception('Error with ' . __FUNCTION__ . ': неверное значение инкремента');
        }

        return $increment;
    }

    /**
     * Получает список веток не в релизе
     * @return array
     */
    public static function getBranches(): array
    {
        return self::getBranchesWithStatus(0);
    }

    /**
     * Получает список веток в релизе
     * @return array
     */
    public static function getReleaseCandidates(): array
    {
        return self::getBranchesWithStatus(1);
    }

    /**
     * Получает ветки с указанным статусом
     * @param bool $status true - список веток не в релизе, false - список веток в релизе
     * @return array
     */
    private static function getBranchesWithStatus(bool $status = false): array
    {
        $branches = Db::call()->queryAll('select `id`, `branch_name` as `name` from ' . self::getBranchTableName() . ' where `status` = ' . (int)$status);

        return $branches;
    }

    /**
     * Обновляет статус ветки
     * @return mixed
     */
    public function updateBranchStatus(): void
    {
        $status = ($this->action == 'add') ? 1 : 0;
        $sql = 'update ' . self::getBranchTableName() . " set status = $status where id = $this->id";

        Db::call()->execute($sql, []);
    }

    /**
     * Получает название таблицы со статусами веток
     * @return string
     */
    private static function getBranchTableName(): string
    {
        return 'branch_status';
    }

    /**
     * Получает название таблицы с порядковым номером релизной ветки
     * @return string
     */
    private static function getIncrementTableName(): string
    {
        return 'rc_increment';
    }
}
