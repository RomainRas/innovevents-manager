<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Infrastructure\Connections;
$db=Connections::events();$results=[];
foreach(['client_reassignment_denied'=>'UPDATE evenements SET client_id=1 WHERE 1=0','status_update_denied'=>"UPDATE evenements SET statut='accepte' WHERE 1=0",'publication_update_denied'=>'UPDATE evenements SET visible_demande=1 WHERE 1=0','consent_update_denied'=>'UPDATE evenements SET accord_publication=1 WHERE 1=0','event_delete_denied'=>'DELETE FROM evenements WHERE 1=0','users_read_denied'=>'SELECT * FROM utilisateurs LIMIT 0','journal_update_denied'=>"UPDATE journal_a_emettre SET etat='livre' WHERE 1=0"] as $name=>$query){try{$db->exec($query);$results[$name]=false;}catch(PDOException $e){$results[$name]=in_array((int)($e->errorInfo[1]??0),[1142,1143],true);}}
echo json_encode($results,JSON_PRETTY_PRINT).PHP_EOL;exit(in_array(false,$results,true)?1:0);
