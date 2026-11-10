<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Infrastructure\Connections;
$results=[];
$sql=Connections::mysql();
$results['mysql_demo_read']=(int)$sql->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn()===3;
foreach (['mysql_other_database_denied'=>'SELECT * FROM mysql.user LIMIT 1',
          'mysql_write_denied'=>'UPDATE notes_globales SET contenu=contenu WHERE 1=0'] as $name=>$query) {
    try { $sql->query($query); $results[$name]=false; }
    catch (PDOException $e) { $results[$name]=in_array((int)($e->errorInfo[1]??0),[1142,1044],true); }
}
$mongo=Connections::mongo();
$logs=$mongo->selectCollection('innovevents_demo','logs');
$results['mongo_logs_read']=$logs->countDocuments()===4;
try { $mongo->selectCollection('admin','system.users')->findOne(); $results['mongo_admin_denied']=false; }
catch (MongoDB\Driver\Exception\CommandException $e) { $results['mongo_admin_denied']=$e->getCode()===13; }
try { $logs->updateOne(['_id'=>new MongoDB\BSON\ObjectId('000000000000000000000000')],['$set'=>['version_schema'=>1]]); $results['mongo_write_denied']=false; }
catch (MongoDB\Driver\Exception\BulkWriteException $e) { $results['mongo_write_denied']=$e->getCode()===13; }
echo json_encode($results,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit(in_array(false,$results,true)?1:0);
