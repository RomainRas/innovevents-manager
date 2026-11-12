<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Infrastructure\Connections;
$results=[];$db=Connections::quotes();
foreach([
 'status_insert_denied'=>"INSERT INTO prospects(statut) SELECT 'converti' WHERE 1=0",
 'prospect_update_denied'=>"UPDATE prospects SET besoin='x' WHERE 1=0",
 'prospect_read_denied'=>'SELECT * FROM prospects LIMIT 0',
 'prospect_delete_denied'=>'DELETE FROM prospects WHERE 1=0',
 'settings_update_denied'=>"UPDATE parametres_application SET valeur='x' WHERE 1=0",
 'user_read_denied'=>'SELECT * FROM utilisateurs LIMIT 0'
] as $name=>$query){try{$db->exec($query);$results[$name]=false;}catch(PDOException $e){$results[$name]=in_array((int)($e->errorInfo[1]??0),[1142,1143],true);}}
echo json_encode($results,JSON_PRETTY_PRINT).PHP_EOL;exit(in_array(false,$results,true)?1:0);
