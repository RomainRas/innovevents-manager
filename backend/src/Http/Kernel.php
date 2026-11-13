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
            '/api/auth/logout'=>'POST','/api/auth/register'=>'POST','/api/auth/forgot'=>'POST','/api/auth/password'=>'POST',
            '/api/me/events'=>'GET','/api/team/notes'=>'GET','/api/admin/logs'=>'GET',
            '/api/quote-request/config'=>'GET','/api/quote-requests'=>'POST','/api/admin/quote-thanks'=>'POST',
            '/api/admin/prospects'=>'GET','/api/admin/prospect'=>'GET','/api/admin/prospect-failure'=>'POST',
            '/api/admin/prospect-conversion'=>'POST','/api/admin/client'=>'GET'];
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
                    case '/api/admin/client':
                        $result=(new \InnovEvents\Service\ClientConversion())->detail($_GET);break;
                    case '/api/admin/prospects':
                        $result=(new \InnovEvents\Service\Prospects())->listing($_GET);break;
                    case '/api/admin/prospect':
                        $result=(new \InnovEvents\Service\Prospects())->detail($_GET);break;
                    case '/api/quote-request/config':
                        $result=(new \InnovEvents\Service\QuoteRequests())->config();break;
                    case '/api/auth/session':
                        $u=$auth->current();$result=['user'=>$u?Users::publicData($u):null,'csrf'=>$_SESSION['csrf']];break;
                    case '/api/auth/login':
                    case '/api/auth/register':
                    case '/api/auth/forgot':
                    case '/api/auth/password':
                    case '/api/quote-requests':
                    case '/api/admin/quote-thanks':
                    case '/api/admin/prospect-failure':
                    case '/api/admin/prospect-conversion':
                        if (strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json') throw new HttpError(415,'json_required');
                        $limit=$path==='/api/quote-requests'?32768:8192;
                        $raw=file_get_contents('php://input',false,null,0,$limit+1);
                        if (strlen($raw)>$limit) throw new HttpError(413,'payload_too_large');
                        try {$data=json_decode($raw,true,16,JSON_THROW_ON_ERROR);} catch (\JsonException) {throw new HttpError(400,'invalid_json');}
                        if (!is_array($data)) throw new HttpError(422,'invalid_input');
                        if ($path==='/api/admin/prospect-conversion')$result=(new \InnovEvents\Service\ClientConversion())->convert($data);
                        elseif ($path==='/api/admin/prospect-failure')$result=(new \InnovEvents\Service\Prospects())->fail($data);
                        elseif ($path==='/api/quote-requests')$result=(new \InnovEvents\Service\QuoteRequests())->submit($data);
                        elseif ($path==='/api/admin/quote-thanks')$result=(new \InnovEvents\Service\QuoteRequests())->saveThanks($data);
                        elseif ($path==='/api/auth/login')$result=['user'=>$auth->login($data),'csrf'=>$_SESSION['csrf']];
                        else {
                            $accounts=new \InnovEvents\Service\Accounts();
                            $result=match($path){'/api/auth/register'=>$accounts->register($data),'/api/auth/forgot'=>$accounts->forgot($data),'/api/auth/password'=>$accounts->change($data)};
                            if ($path!=='/api/auth/password')http_response_code(202);
                        }
                        break;
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
            echo json_encode(['error'=>$e->getMessage()]+($e->fields?['fields'=>$e->fields]:[]));
        } catch (\Throwable $e) {
            error_log('Request failed: '.get_class($e));http_response_code(503);echo json_encode(['status'=>'unavailable']);
        }
    }
}
