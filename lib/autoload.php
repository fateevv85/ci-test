<?php
spl_autoload_register(function ($className) {
    $ds = DIRECTORY_SEPARATOR;
    $className = ltrim($className, '\\');
    $fileName = $className;

    if ($lastNsPos = strripos($className, '\\')) {
        $namespace = substr($className, 0, $lastNsPos);
        $className = substr($className, $lastNsPos + 1);
        $fileName = str_replace('\\', $ds, $namespace) . $ds . $className;
    }

    $fileName = __DIR__ . $ds . '..' . $ds . $fileName . '.php';

    if (file_exists($fileName)) {
        require $fileName;
    }
});