<?php
declare(strict_types=1);
namespace InnovEvents\Infrastructure;

use MongoDB\Client;
use PDO;
use RuntimeException;

final class Connections
{
    private static function secret(string $name): string
    {
        $value = file_get_contents('/run/secrets/' . $name);
        if ($value === false || trim($value) === '') {
            throw new RuntimeException('Configuration indisponible');
        }
        return trim($value);
    }

    public static function mysql(): PDO
    {
        return new PDO('mysql:host=mysql;dbname=innovevents_demo;charset=utf8mb4',
            'ecf_api_read', self::secret('mysql_api'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
    }

    public static function mongo(): Client
    {
        return new Client('mongodb://mongodb:27017', [
            'username' => 'ecf_api_read', 'password' => self::secret('mongo_api'),
            'authSource' => 'innovevents_demo', 'serverSelectionTimeoutMS' => 2000,
            'connectTimeoutMS' => 1000, 'socketTimeoutMS' => 2000,
        ]);
    }
}
