<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Infrastructure\Connections;
$db=Connections::journal();$results=[];
foreach(['source_update_denied'=>"UPDATE journal_a_emettre SET details=JSON_OBJECT() WHERE 1=0",'journal_delete_denied'=>'DELETE FROM journal_a_emettre WHERE 1=0','client_read_denied'=>'SELECT * FROM clients LIMIT 0'] as $name=>$query){try{$db->exec($query);$results[$name]=false;}catch(PDOException $e){$results[$name]=in_array((int)($e->errorInfo[1]??0),[1142,1143],true);}}
try{Connections::audit()->selectCollection('innovevents_demo','logs')->deleteOne(['_id'=>new MongoDB\BSON\ObjectId('000000000000000000000000')]);$results['mongo_delete_denied']=false;}catch(MongoDB\Driver\Exception\BulkWriteException $e){$results['mongo_delete_denied']=$e->getCode()===13;}
echo json_encode($results,JSON_PRETTY_PRINT).PHP_EOL;exit(in_array(false,$results,true)?1:0);
