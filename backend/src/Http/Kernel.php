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
            '/api/admin/prospect-conversion'=>'POST','/api/admin/client'=>'GET',
            '/api/admin/event-context'=>'GET','/api/admin/event'=>'GET','/api/admin/event-create'=>'POST','/api/admin/event-save'=>'POST',
            '/api/admin/quote-pdf-status'=>'GET','/api/admin/quote-pdf'=>'GET','/api/admin/quote-pdf-generate'=>'POST','/api/admin/quote-draft'=>'GET','/api/admin/quote-draft-save'=>'POST','/api/admin/event-image'=>'GET','/api/admin/event-image-upload'=>'POST',
            '/api/admin/quote-transmission'=>'GET','/api/admin/quote-send'=>'POST','/api/me/quotes'=>'GET','/api/me/quote-pdf'=>'GET',
            '/api/me/quote-decision'=>'POST','/api/admin/quotes'=>'GET','/api/admin/quote-counter-proposal'=>'POST',
            '/api/public/events'=>'GET','/api/public/event'=>'GET','/api/public/event-image'=>'GET','/api/public/reviews'=>'GET','/api/contact'=>'POST',
            '/api/me/profile'=>'GET','/api/me/profile-save'=>'POST','/api/me/deletion-request'=>'POST','/api/me/reviews'=>'GET','/api/me/review'=>'POST',
            '/api/team/reviews'=>'GET','/api/team/review-moderation'=>'POST',
            '/api/admin/dashboard'=>'GET','/api/admin/accounts'=>'GET','/api/admin/account-create'=>'POST','/api/admin/account-status'=>'POST','/api/admin/account-delete'=>'POST'];
        try {
            if (!isset($routes[$path])) throw new HttpError(404,'not_found');
            if (($_SERVER['REQUEST_METHOD']??'')!==$routes[$path]) {
                header('Allow: '.$routes[$path]);throw new HttpError(405,'method_not_allowed');
            }
            if ($path==='/api/health') {
                (new \InnovEvents\Service\Readiness())->check();$result=['status'=>'ready'];
            } else {
                // Lectures publiques sans session : aucun cookie déposé pour un simple visiteur.
                if (!str_starts_with($path,'/api/public/'))WebSession::start();
                $auth=new Authentication();
                if ($routes[$path]==='POST') WebSession::csrf();
                switch ($path) {
                    case '/api/public/events':
                        $result=(new \InnovEvents\Service\PublicSite())->events($_GET);break;
                    case '/api/public/event':
                        $result=(new \InnovEvents\Service\PublicSite())->event($_GET);break;
                    case '/api/public/event-image':
                        (new \InnovEvents\Service\PublicSite())->image($_GET);return;
                    case '/api/admin/dashboard':
                        $result=(new \InnovEvents\Service\AdminAccounts())->dashboard();break;
                    case '/api/admin/accounts':
                        $result=(new \InnovEvents\Service\AdminAccounts())->listing($_GET);break;
                    case '/api/me/profile':
                        $result=(new \InnovEvents\Service\ClientSpace())->profile();break;
                    case '/api/me/reviews':
                        $result=(new \InnovEvents\Service\ClientSpace())->reviews();break;
                    case '/api/team/reviews':
                        $result=(new \InnovEvents\Service\ReviewModeration())->listing($_GET);break;
                    case '/api/public/reviews':
                        $result=(new \InnovEvents\Service\PublicSite())->reviews();break;
                    case '/api/admin/quote-pdf-status':
                        $result=(new \InnovEvents\Service\QuotePdfs())->status($_GET);break;
                    case '/api/admin/quote-pdf':
                        (new \InnovEvents\Service\QuotePdfs())->download($_GET);return;
                    case '/api/admin/quote-transmission':
                        $result=(new \InnovEvents\Service\QuoteTransmissions())->status($_GET);break;
                    case '/api/admin/quotes':
                        $result=(new \InnovEvents\Service\QuoteTransmissions())->overview($_GET);break;
                    case '/api/me/quotes':
                        $result=(new \InnovEvents\Service\ClientQuotes())->listing();break;
                    case '/api/me/quote-pdf':
                        (new \InnovEvents\Service\ClientQuotes())->download($_GET);return;
                    case '/api/admin/quote-draft':
                        $result=(new \InnovEvents\Service\QuoteDrafts())->read($_GET);break;
                    case '/api/admin/event-image':
                        (new \InnovEvents\Service\EventImages())->show($_GET);return;
                    case '/api/admin/event-image-upload':
                        if(!str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'multipart/form-data;'))throw new HttpError(415,'multipart_required');
                        $result=(new \InnovEvents\Service\EventImages())->upload($_POST,$_FILES);break;
                    case '/api/admin/event-context':
                        $result=(new \InnovEvents\Service\EventDrafts())->context($_GET);break;
                    case '/api/admin/event':
                        $result=(new \InnovEvents\Service\EventDrafts())->detail($_GET);break;
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
                    case '/api/admin/event-create':
                    case '/api/admin/quote-pdf-generate':
                    case '/api/admin/quote-send':
                    case '/api/me/quote-decision':
                    case '/api/admin/quote-counter-proposal':
                    case '/api/admin/quote-draft-save':
                    case '/api/admin/event-save':
                    case '/api/admin/account-create':
                    case '/api/admin/account-status':
                    case '/api/admin/account-delete':
                    case '/api/me/profile-save':
                    case '/api/me/deletion-request':
                    case '/api/me/review':
                    case '/api/team/review-moderation':
                    case '/api/contact':
                        if (strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json') throw new HttpError(415,'json_required');
                        $limit=in_array($path,['/api/quote-requests','/api/admin/quote-draft-save'],true)?65536:8192;
                        $raw=file_get_contents('php://input',false,null,0,$limit+1);
                        if (strlen($raw)>$limit) throw new HttpError(413,'payload_too_large');
                        try {$data=json_decode($raw,true,16,JSON_THROW_ON_ERROR);} catch (\JsonException) {throw new HttpError(400,'invalid_json');}
                        if (!is_array($data)) throw new HttpError(422,'invalid_input');
                        if ($path==='/api/contact'){$result=(new \InnovEvents\Service\PublicSite())->contact($data);http_response_code(202);}
                        elseif ($path==='/api/admin/account-create'){$result=(new \InnovEvents\Service\AdminAccounts())->create($data);http_response_code(201);}
                        elseif ($path==='/api/admin/account-status')$result=(new \InnovEvents\Service\AdminAccounts())->setStatus($data);
                        elseif ($path==='/api/admin/account-delete')$result=(new \InnovEvents\Service\AdminAccounts())->delete($data);
                        elseif ($path==='/api/me/profile-save')$result=(new \InnovEvents\Service\ClientSpace())->saveProfile($data);
                        elseif ($path==='/api/me/deletion-request'){$result=(new \InnovEvents\Service\ClientSpace())->requestDeletion($data);http_response_code(202);}
                        elseif ($path==='/api/me/review')$result=(new \InnovEvents\Service\ClientSpace())->submitReview($data);
                        elseif ($path==='/api/team/review-moderation')$result=(new \InnovEvents\Service\ReviewModeration())->moderate($data);
                        elseif ($path==='/api/me/quote-decision')$result=(new \InnovEvents\Service\ClientQuotes())->decide($data);
                        elseif ($path==='/api/admin/quote-counter-proposal')$result=(new \InnovEvents\Service\QuoteDrafts())->counterProposal($data);
                        elseif ($path==='/api/admin/quote-send')$result=(new \InnovEvents\Service\QuoteTransmissions())->send($data);
                        elseif ($path==='/api/admin/quote-pdf-generate')$result=(new \InnovEvents\Service\QuotePdfs())->generate($data);
                        elseif ($path==='/api/admin/quote-draft-save')$result=(new \InnovEvents\Service\QuoteDrafts())->save($data);
                        elseif ($path==='/api/admin/event-create')$result=(new \InnovEvents\Service\EventDrafts())->create($data);
                        elseif ($path==='/api/admin/event-save')$result=(new \InnovEvents\Service\EventDrafts())->save($data);
                        elseif ($path==='/api/admin/prospect-conversion')$result=(new \InnovEvents\Service\ClientConversion())->convert($data);
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
