<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Infrastructure\Connections;
// Refus SQL attendus : le compte des réponses client ne touche ni aux montants, ni aux décisions passées, ni aux comptes.
$db=Connections::quoteDecision();$results=[];
foreach([
    'amounts_denied'=>'UPDATE versions_devis SET total_ttc=0 WHERE 1=0',
    'transmission_dates_denied'=>'UPDATE versions_devis SET transmise_le=NULL WHERE 1=0',
    'current_version_denied'=>'UPDATE devis SET version_courante_id=NULL WHERE 1=0',
    'decision_update_denied'=>"UPDATE decisions_devis SET choix='accepte' WHERE 1=0",
    'decision_delete_denied'=>'DELETE FROM decisions_devis WHERE 1=0',
    'event_details_denied'=>"UPDATE evenements SET nom='x' WHERE 1=0",
    'users_read_denied'=>'SELECT mot_de_passe_hash FROM utilisateurs WHERE 1=0',
    'lines_denied'=>'DELETE FROM prestations_devis WHERE 1=0',
] as $name=>$sql){try{$db->query($sql);$results[$name]=false;}catch(PDOException $e){$results[$name]=in_array((int)($e->errorInfo[1]??0),[1142,1143],true);}}
echo json_encode($results,JSON_PRETTY_PRINT).PHP_EOL;exit(in_array(false,$results,true)?1:0);
