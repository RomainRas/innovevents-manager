<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use PDO;
final class MailQueue
{
    private static function key(): string
    {
        $key=hex2bin(trim(file_get_contents('/run/secrets/mail_key')));
        if ($key===false||strlen($key)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES) throw new \RuntimeException('Mail key');
        return $key;
    }
    /** @param array{reference:string,sha256:string,name:string}|null $attachment PDF interne vérifié par le traitement d’envoi. */
    public static function enqueue(PDO $db,string $type,string $email,string $subject,string $text,int $minutes,?array $attachment=null): string
    {
        $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $data=compact('email','subject','text');if($attachment!==null)$data['attachment']=$attachment;
        $payload=base64_encode($nonce.sodium_crypto_secretbox(json_encode($data,JSON_THROW_ON_ERROR),$nonce,self::key()));
        $id=(string)$db->query('SELECT UUID()')->fetchColumn();
        $q=$db->prepare('INSERT INTO courriels_a_envoyer(id,type,contenu_chiffre,expire_le) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? MINUTE))');
        $q->execute([$id,$type,$payload,$minutes]);
        return $id;
    }
    public static function decode(string $payload): array
    {
        $raw=base64_decode($payload,true);
        if ($raw===false||strlen($raw)<40) throw new \RuntimeException('Mail payload');
        $plain=sodium_crypto_secretbox_open(substr($raw,24),substr($raw,0,24),self::key());
        if ($plain===false) throw new \RuntimeException('Mail authentication');
        return json_decode($plain,true,16,JSON_THROW_ON_ERROR);
    }
}
