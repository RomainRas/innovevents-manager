<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Infrastructure\Connections;
use InnovEvents\Service\MailQueue;
$data=json_decode(stream_get_contents(STDIN),true,8,JSON_THROW_ON_ERROR);
if (!preg_match('/^mail-test-[a-f0-9]{16}@example\.invalid$/',$data['email']??''))exit(2);
$db=Connections::accounts();$db->beginTransaction();
$id=MailQueue::enqueue($db,'confirmation',$data['email'],'Test reprise courriel','Message fictif de vérification de reprise.',60);
$db->commit();echo $id;
