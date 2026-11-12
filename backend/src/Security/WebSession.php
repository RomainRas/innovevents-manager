<?php
declare(strict_types=1);
namespace InnovEvents\Security;
use InnovEvents\Http\HttpError;
final class WebSession
{
    public static function start(): void
    {
        $dir='/var/lib/innovevents/sessions';
        if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new \RuntimeException('Session storage');
        session_save_path($dir);
        ini_set('session.use_strict_mode','1');
        ini_set('session.use_only_cookies','1');
        ini_set('session.use_trans_sid','0');
        ini_set('session.gc_maxlifetime','28800');
        session_name('innovevents_session');
        session_set_cookie_params(['lifetime'=>0,'path'=>'/api','secure'=>getenv('APP_ENV')!=='local',
            'httponly'=>true,'samesite'=>'Lax']);
        if (!session_start()) throw new \RuntimeException('Session unavailable');
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }
    public static function csrf(): void
    {
        $origin=$_SERVER['HTTP_ORIGIN']??null;
        if ($origin!==null && $origin!==(getenv('APP_ORIGIN')?:'https://invalid.local')) throw new HttpError(403,'csrf_invalid');
        if (($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site') throw new HttpError(403,'csrf_invalid');
        $token=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
        if (!is_string($token) || !hash_equals($_SESSION['csrf'],$token)) throw new HttpError(403,'csrf_invalid');
    }
    public static function reset(): void
    {
        $_SESSION=[];
        if (!session_regenerate_id(true)) throw new \RuntimeException('Session rotation');
        $_SESSION['csrf']=bin2hex(random_bytes(32));
    }
}
