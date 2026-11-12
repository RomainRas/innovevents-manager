<?php
declare(strict_types=1);
namespace InnovEvents\Http;
use InnovEvents\Repository\Spaces;
use InnovEvents\Repository\Users;
use InnovEvents\Security\WebSession;
use InnovEvents\Service\Authentication;
final class Kernel
{
    public function run(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
        $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
        $routes=['/api/health'=>'GET','/api/auth/session'=>'GET','/api/auth/login'=>'POST',
            '/api/auth/logout'=>'POST','/api/me/events'=>'GET','/api/team/notes'=>'GET','/api/admin/logs'=>'GET'];
        try {
            if (!isset($routes[$path])) throw new HttpError(404,'not_found');
            if (($_SERVER['REQUEST_METHOD']??'')!==$routes[$path]) {
                header('Allow: '.$routes[$path]);throw new HttpError(405,'method_not_allowed');
            }
            if ($path==='/api/health') {
                (new \InnovEvents\Service\Readiness())->check();$result=['status'=>'ready'];
            } else {
                WebSession::start();$auth=new Authentication();
                if ($routes[$path]==='POST') WebSession::csrf();
                switch ($path) {
                    case '/api/auth/session':
                        $u=$auth->current();$result=['user'=>$u?Users::publicData($u):null,'csrf'=>$_SESSION['csrf']];break;
                    case '/api/auth/login':
                        if (strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json') throw new HttpError(415,'json_required');
                        $raw=file_get_contents('php://input',false,null,0,8193);
                        if (strlen($raw)>8192) throw new HttpError(413,'payload_too_large');
                        try {$data=json_decode($raw,true,16,JSON_THROW_ON_ERROR);} catch (\JsonException) {throw new HttpError(400,'invalid_json');}
                        if (!is_array($data)) throw new HttpError(422,'invalid_input');
                        $result=['user'=>$auth->login($data),'csrf'=>$_SESSION['csrf']];break;
                    case '/api/auth/logout':
                        WebSession::reset();$result=['user'=>null,'csrf'=>$_SESSION['csrf']];break;
                    case '/api/me/events':
                        $u=$auth->requireUser(['client']);
                        $result=['events'=>(new Spaces())->events((string)$u['id'])];break;
                    case '/api/team/notes':
                        $auth->requireUser(['employe','administrateur']);
                        $result=['notes'=>(new Spaces())->notes()];break;
                    case '/api/admin/logs':
                        $auth->requireUser(['administrateur']);
                        $result=['logs'=>(new Spaces())->logs()];break;
                }
            }
            echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        } catch (HttpError $e) {
            http_response_code($e->status);if ($e->status===429) header('Retry-After: 900');
            echo json_encode(['error'=>$e->getMessage()]);
        } catch (\Throwable $e) {
            error_log('Request failed: '.get_class($e));http_response_code(503);echo json_encode(['status'=>'unavailable']);
        }
    }
}
