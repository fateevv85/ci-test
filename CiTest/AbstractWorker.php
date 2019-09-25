<?php
namespace CiTest;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Class AbstractWorker
 * @package CiTest
 */
class AbstractWorker
{
    protected $config;
    protected $queue;
    protected $maxPriority;

    /**
     * AbstractWorker constructor.
     * @param string $queue название очереди
     * @param int $maxPriority максимальный приоритет очереди
     */
    public function __construct(string $queue = 'ci_queue', int $maxPriority = 10)
    {
        $this->queue = $queue;
        $this->config = require_once __DIR__ . '/../config/rabbit.php';
        $this->maxPriority = $maxPriority;
    }
}
