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

    public static function accounts(): PDO { return self::accountDatabase('ecf_accounts','mysql_accounts'); }
    public static function mail(): PDO { return self::accountDatabase('ecf_mail','mysql_mail'); }
    public static function quotes(): PDO { return self::accountDatabase('ecf_quotes','mysql_quotes'); }
    public static function prospects(): PDO { return self::accountDatabase('ecf_prospects_admin','mysql_prospects_admin'); }
    public static function settings(): PDO { return self::accountDatabase('ecf_settings','mysql_settings'); }
    private static function accountDatabase(string $user,string $secret): PDO
    {
        return new PDO('mysql:host=mysql;dbname=innovevents_demo;charset=utf8mb4',$user,self::secret($secret),[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    }

    public static function mongo(): Client
    {
        return new Client('mongodb://mongodb:27017', [
            'username' => 'ecf_api_read', 'password' => self::secret('mongo_api'),
            'authSource' => 'innovevents_demo', 'serverSelectionTimeoutMS' => 2000,
            'connectTimeoutMS' => 1000, 'socketTimeoutMS' => 2000,
        ]);
    }

    public static function audit(): Client
    {
        return new Client('mongodb://mongodb:27017', [
            'username'=>'ecf_auth_log', 'password'=>self::secret('mongo_auth'),
            'authSource'=>'innovevents_demo','serverSelectionTimeoutMS'=>2000,
            'connectTimeoutMS'=>1000,'socketTimeoutMS'=>2000,
        ]);
    }
}
