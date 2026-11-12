<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use InnovEvents\Repository\Users;
use InnovEvents\Security\WebSession;
use InnovEvents\Security\LoginLimiter;
use InnovEvents\Http\HttpError;
final class Authentication
{
    public function current(): ?array
    {
        if (!isset($_SESSION['uid'])) return null;
        $u=(new Users())->find('id',(string)$_SESSION['uid']);
        if (!$u || $u['statut']!=='actif' || time()-($_SESSION['last']??0)>1800
            || time()-($_SESSION['created']??0)>28800
            || !hash_equals($_SESSION['credential']??'',hash('sha256',$u['mot_de_passe_hash']))) {
            WebSession::reset();return null;
        }
        $_SESSION['last']=time();
        return $u;
    }
    public function requireUser(array $roles=[]): array
    {
        $u=$this->current();
        if (!$u) throw new HttpError(401,'authentication_required');
        if ((bool)$u['changement_mdp_requis']) throw new HttpError(403,'password_change_required');
        if ($roles && !in_array($u['role'],$roles,true)) throw new HttpError(403,'forbidden');
        return $u;
    }
    public function login(array $input): array
    {
        unset($_SESSION['uid'],$_SESSION['created'],$_SESSION['last'],$_SESSION['credential']);
        if (!is_string($input['email']??null)||!is_string($input['password']??null)) throw new HttpError(422,'invalid_input');
        $email=trim($input['email']);$password=$input['password'];
        if (strlen($email)>254 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)>72 || $password==='') throw new HttpError(422,'invalid_input');
        // Ne pas accepter un rôle ou un identifiant utilisateur envoyé par le navigateur.
        $ip=$_SERVER['REMOTE_ADDR']??'127.0.0.1';
        if (!filter_var($ip,FILTER_VALIDATE_IP)) $ip='127.0.0.1';
        $u=(new Users())->find('email_normalise',strtolower($email));
        if (!(new LoginLimiter())->allow(strtolower($email),$ip)) {
            (new AuthAudit())->record(false,$u?(string)$u['id']:null,$email,$ip);
            throw new HttpError(429,'too_many_attempts');
        }
        $hash=$u['mot_de_passe_hash']??'$2y$10$opK.m3XooPgJCZVLxKCezekkj4HyLKmJSE54KM2ZGjbPwGpWkkMaC';
        $valid=password_verify($password,$hash);
        $valid=$valid && $u!==null && $u['statut']==='actif';
        (new AuthAudit())->record($valid,$u?(string)$u['id']:null,$email,$ip);
        if (!$valid) throw new HttpError(401,'invalid_credentials');
        WebSession::reset();
        $_SESSION['uid']=(string)$u['id'];$_SESSION['created']=time();$_SESSION['last']=time();
        $_SESSION['credential']=hash('sha256',$u['mot_de_passe_hash']);
        return Users::publicData($u);
    }
}
