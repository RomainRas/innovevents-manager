<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Infrastructure\Connections;
$db=Connections::quoteDrafts();$results=[];
foreach(['event_status_denied'=>"UPDATE evenements SET statut='accepte' WHERE 1=0",'quote_status_denied'=>"UPDATE versions_devis SET statut='accepte' WHERE 1=0",'snapshot_denied'=>'UPDATE versions_devis SET client_snapshot=NULL WHERE 1=0','client_write_denied'=>"UPDATE clients SET entreprise='x' WHERE 1=0",'users_read_denied'=>'SELECT * FROM utilisateurs LIMIT 0','quote_delete_denied'=>'DELETE FROM devis WHERE 1=0'] as $name=>$sql){try{$db->exec($sql);$results[$name]=false;}catch(PDOException $e){$results[$name]=in_array((int)($e->errorInfo[1]??0),[1142,1143],true);}}
echo json_encode($results,JSON_PRETTY_PRINT).PHP_EOL;exit(in_array(false,$results,true)?1:0);
