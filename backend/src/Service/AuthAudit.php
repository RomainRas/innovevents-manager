<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use InnovEvents\Infrastructure\Connections;
use MongoDB\BSON\UTCDateTime;
final class AuthAudit
{
    public function record(bool $success,?string $id,string $email,string $ip): void
    {
        $bytes=random_bytes(16);
        $bytes[6]=chr((ord($bytes[6])&15)|64);$bytes[8]=chr((ord($bytes[8])&63)|128);
        $hex=bin2hex($bytes);
        $trace=substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
        $client=Connections::audit();
        $client->selectCollection('innovevents_demo','logs')->insertOne([
            'horodatage'=>new UTCDateTime(),'type_action'=>$success?'CONNEXION_REUSSIE':'CONNEXION_ECHOUEE',
            'id_utilisateur'=>$id,'details'=>$success?['ip'=>$ip]:['email_utilise'=>$email,'ip'=>$ip],
            'id_trace'=>$trace,'version_schema'=>1,
        ], ['writeConcern'=>new \MongoDB\Driver\WriteConcern(1,2000,true)]);
    }
}
