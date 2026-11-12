<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use InnovEvents\Service\QuoteRequests;
$data=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
if(!preg_match('/^quote-resilience-[a-f0-9]{16}@example\.invalid$/',$data['email']??''))exit(2);
$_SERVER['REMOTE_ADDR']='127.0.0.1';
try { echo json_encode((new QuoteRequests())->submit($data),JSON_THROW_ON_ERROR); }
catch(Throwable $e){echo json_encode(['error_class'=>get_class($e)]);exit(1);}
