<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Infrastructure\Connections;
$db=Connections::prospects();$results=[];
foreach(['delete_denied'=>'DELETE FROM prospects WHERE 1=0','contact_update_denied'=>"UPDATE prospects SET email='test@example.invalid' WHERE 1=0",'client_link_denied'=>'UPDATE prospects SET client_id=1 WHERE 1=0','users_read_denied'=>'SELECT * FROM utilisateurs LIMIT 0','mail_read_denied'=>'SELECT * FROM courriels_a_envoyer LIMIT 0'] as $name=>$query){try{$db->exec($query);$results[$name]=false;}catch(PDOException $e){$results[$name]=in_array((int)($e->errorInfo[1]??0),[1142,1143],true);}}
echo json_encode($results,JSON_PRETTY_PRINT).PHP_EOL;exit(in_array(false,$results,true)?1:0);
