<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Infrastructure\Connections;
use MongoDB\BSON\UTCDateTime;
$once=in_array('--once',$argv,true);
do {
    $db=null;
    try {
        $db=Connections::journal();$db->beginTransaction();
        $row=$db->query("SELECT * FROM journal_a_emettre WHERE etat='en_attente' AND (prochain_essai_le IS NULL OR prochain_essai_le<=UTC_TIMESTAMP(6)) ORDER BY horodatage,id_trace LIMIT 1 FOR UPDATE SKIP LOCKED")->fetch();
        if(!$row){$db->commit();if(!$once)sleep(2);continue;}
        try {
            $document=['horodatage'=>new UTCDateTime(new DateTimeImmutable($row['horodatage'],new DateTimeZone('UTC'))),'type_action'=>$row['type_action'],'id_utilisateur'=>$row['id_utilisateur']===null?null:(string)$row['id_utilisateur'],'details'=>json_decode($row['details'],true,16,JSON_THROW_ON_ERROR),'id_trace'=>$row['id_trace'],'version_schema'=>(int)$row['version_schema']];
            try {Connections::audit()->selectCollection('innovevents_demo','logs')->insertOne($document,['writeConcern'=>new MongoDB\Driver\WriteConcern(1,2000,true)]);}
            catch(MongoDB\Driver\Exception\BulkWriteException $e){if($e->getCode()!==11000)throw $e;}
            $db->prepare("UPDATE journal_a_emettre SET etat='livre',livre_le=UTC_TIMESTAMP(6),tentatives=tentatives+1,prochain_essai_le=NULL,derniere_erreur=NULL WHERE id_trace=?")->execute([$row['id_trace']]);
        } catch(Throwable $e){
            $db->prepare("UPDATE journal_a_emettre SET tentatives=tentatives+1,etat=?,prochain_essai_le=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 60 SECOND),derniere_erreur=? WHERE id_trace=?")->execute([(int)$row['tentatives']>=4?'echec':'en_attente',get_class($e),$row['id_trace']]);
            error_log('Journal delivery failed: '.get_class($e));
        }
        $db->commit();
    }catch(Throwable $e){if($db&&$db->inTransaction())$db->rollBack();error_log('Journal queue unavailable');if($once)exit(1);sleep(5);}
}while(!$once);
