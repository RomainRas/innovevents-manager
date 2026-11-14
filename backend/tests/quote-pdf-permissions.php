<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Infrastructure\Connections;
$db=Connections::quotePdf();$results=[];
foreach(['event_status_denied'=>"UPDATE evenements SET statut='accepte' WHERE 1=0",'quote_status_denied'=>"UPDATE versions_devis SET statut='accepte' WHERE 1=0",'amount_update_denied'=>'UPDATE versions_devis SET total_ht=0 WHERE 1=0','client_update_denied'=>"UPDATE clients SET entreprise='x' WHERE 1=0",'document_delete_denied'=>'DELETE FROM documents_devis WHERE 1=0','journal_update_denied'=>"UPDATE journal_a_emettre SET etat='livre' WHERE 1=0"] as $name=>$sql){try{$db->exec($sql);$results[$name]=false;}catch(PDOException $e){$results[$name]=in_array((int)($e->errorInfo[1]??0),[1142,1143],true);}}
echo json_encode($results,JSON_PRETTY_PRINT).PHP_EOL;exit(in_array(false,$results,true)?1:0);
