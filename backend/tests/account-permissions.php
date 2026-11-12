<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Infrastructure\Connections;
$db=Connections::accounts();$results=[];
foreach ([
    'role_update_denied'=>"UPDATE utilisateurs SET role='administrateur' WHERE 1=0",
    'role_insert_denied'=>"INSERT INTO utilisateurs(role) SELECT 'administrateur' WHERE 1=0",
    'delete_denied'=>"DELETE FROM utilisateurs WHERE 1=0",
    'mail_read_denied'=>'SELECT id FROM courriels_a_envoyer LIMIT 0'
] as $name=>$query) {
    try {$db->exec($query);$results[$name]=false;} catch (PDOException $e) {$results[$name]=in_array((int)($e->errorInfo[1]??0),[1142,1143],true);}
}
echo json_encode($results,JSON_PRETTY_PRINT).PHP_EOL;
exit(in_array(false,$results,true)?1:0);
