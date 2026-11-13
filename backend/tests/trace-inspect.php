<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Infrastructure\Connections;
$trace=trim(stream_get_contents(STDIN));if(!preg_match('/^[a-f0-9-]{36}$/',$trace))exit(2);
$logs=Connections::mongo()->selectCollection('innovevents_demo','logs');$row=$logs->findOne(['id_trace'=>$trace]);
echo json_encode(['count'=>$logs->countDocuments(['id_trace'=>$trace]),'type'=>$row['type_action']??null,'actor'=>$row['id_utilisateur']??null,'details'=>$row['details']??null]);
