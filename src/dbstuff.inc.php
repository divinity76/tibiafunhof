<?php
declare(strict_types = 1);

function getDB(): \PDO
{
    static $db = null;
    if ($db === null) {
        $db = new \PDO(Config::DB_DSN, Config::DB_USERNAME, Config::DB_PASSWORD, array(
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ));
    }
    return $db;
}