<?php

namespace CiTest;

/**
 * Класс для работы с БД
 * Class Db
 * @package CiTest
 */
class Db
{
    private $config;

    private static $instance;
    private $conn;

    private function __construct()
    {
    }

    public static function call()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function getConnection()
    {
        if (is_null($this->conn)) {
            $this->config = require_once __DIR__ . '/../config/db.php';

            $this->conn = new \PDO(
                $this->prepareDsnString(),
                $this->config['login'],
                $this->config['password'],
                [\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"]
            );
            $this->conn->setAttribute(
                \PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC
            );
            $this->conn->setAttribute(
                \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION
            );
        }
        return $this->conn;
    }

    private function prepareDsnString()
    {
        return sprintf("%s:host=%s;dbname=%s;charset=%s",
            $this->config['driver'],
            $this->config['host'],
            $this->config['dataBase'],
            $this->config['charset']
        );
    }

    private function query($sql, $params)
    {
        $pdoStatement = $this->getConnection()->prepare($sql);
        $pdoStatement->execute($params);

        return $pdoStatement;
    }

    public function execute($sql, $params)
    {
        $this->query($sql, $params);
        return true;
    }

    public function queryOne($sql, $params)
    {
        return $this->query($sql, $params)->fetch();
    }

    public function queryAll($sql, $params = null)
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function getObject($sql, $params, $class)
    {
        $query = $this->query($sql, $params);
        $query->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $class);
        return $query->fetch();
    }

    public function getObjects($sql, $class, $params = null)
    {
        $query = $this->query($sql, $params)->fetchAll(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $class);
        return $query;
    }

    public function lastInsertId()
    {
        return $this->conn->lastinsertid();
    }

    public function inTransaction()
    {
        return $this->conn->inTransaction();
    }

    public function beginTransaction()
    {
        return $this->conn->beginTransaction();
    }

    public function rollBack()
    {
        return $this->conn->rollBack();
    }

    public function commit()
    {
        return $this->conn->commit();
    }
}