<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Infrastructure\Connections;
$db=Connections::eventImages();$results=[];
foreach(['status_denied'=>"UPDATE evenements SET statut='accepte' WHERE 1=0",'publication_denied'=>'UPDATE evenements SET visible_demande=1 WHERE 1=0','client_reassignment_denied'=>'UPDATE evenements SET client_id=1 WHERE 1=0','delete_denied'=>'DELETE FROM evenements WHERE 1=0','client_read_denied'=>'SELECT * FROM clients LIMIT 0','event_name_update_denied'=>"UPDATE evenements SET nom='x' WHERE 1=0"] as $name=>$query){try{$db->exec($query);$results[$name]=false;}catch(PDOException $e){$results[$name]=in_array((int)($e->errorInfo[1]??0),[1142,1143],true);}}
echo json_encode($results,JSON_PRETTY_PRINT).PHP_EOL;exit(in_array(false,$results,true)?1:0);
