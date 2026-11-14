<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Infrastructure\Connections;
// Refus SQL attendus : les comptes de transmission et de consultation client restent limités.
$checks=[
    'send_amounts_denied'=>[Connections::quoteSend(),'UPDATE versions_devis SET total_ht=0 WHERE 1=0'],
    'send_lines_denied'=>[Connections::quoteSend(),'DELETE FROM prestations_devis WHERE 1=0'],
    'send_role_denied'=>[Connections::quoteSend(),"UPDATE utilisateurs SET role='administrateur' WHERE 1=0"],
    'send_password_read_denied'=>[Connections::quoteSend(),'SELECT mot_de_passe_hash FROM utilisateurs WHERE 1=0'],
    'send_client_identity_denied'=>[Connections::quoteSend(),"UPDATE clients SET email='x' WHERE 1=0"],
    'send_transmission_update_denied'=>[Connections::quoteSend(),"UPDATE transmissions_devis SET etat='confirme' WHERE 1=0"],
    'send_event_status_denied'=>[Connections::quoteSend(),"UPDATE evenements SET statut='accepte' WHERE 1=0"],
    'client_quote_update_denied'=>[Connections::clientQuotes(),"UPDATE versions_devis SET statut='accepte' WHERE 1=0"],
    'client_users_read_denied'=>[Connections::clientQuotes(),'SELECT email FROM utilisateurs WHERE 1=0'],
    'client_transmission_insert_denied'=>[Connections::clientQuotes(),"INSERT INTO transmissions_devis(document_id) VALUES(0)"],
];
$results=[];
foreach($checks as $name=>[$db,$sql]){try{$db->query($sql);$results[$name]=false;}catch(PDOException $e){$results[$name]=in_array((int)($e->errorInfo[1]??0),[1142,1143],true);}}
echo json_encode($results,JSON_PRETTY_PRINT).PHP_EOL;exit(in_array(false,$results,true)?1:0);
