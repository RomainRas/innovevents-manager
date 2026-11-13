<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Infrastructure\Connections;
$db=Connections::conversion();$results=[];
foreach(['client_account_insert_denied'=>'INSERT INTO clients(utilisateur_id) SELECT 1 WHERE 1=0','client_update_denied'=>"UPDATE clients SET entreprise='x' WHERE 1=0",'client_delete_denied'=>'DELETE FROM clients WHERE 1=0','users_read_denied'=>'SELECT * FROM utilisateurs LIMIT 0','journal_update_denied'=>"UPDATE journal_a_emettre SET etat='livre' WHERE 1=0"] as $name=>$query){try{$db->exec($query);$results[$name]=false;}catch(PDOException $e){$results[$name]=in_array((int)($e->errorInfo[1]??0),[1142,1143],true);}}
echo json_encode($results,JSON_PRETTY_PRINT).PHP_EOL;exit(in_array(false,$results,true)?1:0);
