<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Repository\Users;
use InnovEvents\Security\WebSession;
use InnovEvents\Service\Authentication;
WebSession::start();
$u=(new Users())->find('email_normalise','alice@example.invalid');
if (!$u) throw new RuntimeException('Compte démo absent');
$results=[];
foreach (['idle','absolute','credential_changed'] as $case) {
    $_SESSION['uid']=(string)$u['id'];$_SESSION['last']=time();$_SESSION['created']=time();
    $_SESSION['credential']=hash('sha256',$u['mot_de_passe_hash']);
    if ($case==='idle') $_SESSION['last']=time()-1801;
    if ($case==='absolute') $_SESSION['created']=time()-28801;
    if ($case==='credential_changed') $_SESSION['credential']=str_repeat('0',64);
    $results[$case]=(new Authentication())->current()===null && !isset($_SESSION['uid']);
}
session_destroy();
echo json_encode($results,JSON_PRETTY_PRINT).PHP_EOL;
exit(in_array(false,$results,true)?1:0);
