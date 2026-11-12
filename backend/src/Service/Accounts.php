<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use InnovEvents\Infrastructure\Connections;
use InnovEvents\Http\HttpError;
use InnovEvents\Security\LoginLimiter;
use InnovEvents\Security\WebSession;
final class Accounts
{
    private function email(array $input): string
    {
        $email=$input['email']??null;
        if (!is_string($email)||strlen(trim($email))>254||!filter_var(trim($email),FILTER_VALIDATE_EMAIL))throw new HttpError(422,'invalid_email');
        return strtolower(trim($email));
    }
    private function password(array $input): string
    {
        $p=$input['password']??null;
        if (!is_string($p)||strlen($p)>72||preg_match_all('/./us',$p)<8||preg_match('/\p{C}/u',$p)
            ||!preg_match('/\p{Lu}/u',$p)||!preg_match('/\p{Ll}/u',$p)||!preg_match('/\p{N}/u',$p)||!preg_match('/[^\p{L}\p{N}\s]/u',$p))throw new HttpError(422,'password_policy');
        if (!is_string($input['confirmation']??null)||!hash_equals($p,$input['confirmation']))throw new HttpError(422,'password_confirmation');
        return $p;
    }
    private function limit(string $key): void
    {
        if (!(new LoginLimiter())->allow($key,$_SERVER['REMOTE_ADDR']??'127.0.0.1'))throw new HttpError(429,'too_many_attempts');
    }
    public function register(array $input): array
    {
        $email=$this->email($input);$password=$this->password($input);$names=[];
        foreach (['prenom','nom','nom_utilisateur'] as $field) {
            $value=$input[$field]??null;
            if (!is_string($value)||trim($value)===''||preg_match_all('/./us',$value)>100||preg_match('/\p{C}/u',$value))throw new HttpError(422,'invalid_identity');
            $names[]=trim($value);
        }
        $this->limit('register:'.$email);$hash=password_hash($password,PASSWORD_BCRYPT,['cost'=>12]);
        $db=Connections::accounts();$db->beginTransaction();
        try {
            $q=$db->prepare('INSERT INTO utilisateurs(email,email_normalise,prenom,nom,nom_utilisateur,mot_de_passe_hash) VALUES(?,?,?,?,?,?)');
            $q->execute([$email,$email,...$names,$hash]);
            MailQueue::enqueue($db,'confirmation',$email,'Bienvenue chez Innov’Events',"Bonjour {$names[0]},\nVotre compte Innov’Events a été créé. Vous pouvez vous connecter avec votre adresse électronique.\nAucun mot de passe ne figure dans ce courriel.",1440);
            $db->commit();
        } catch (\PDOException $e) {
            if ($db->inTransaction())$db->rollBack();
            if ((int)($e->errorInfo[1]??0)!==1062)throw $e;
        } catch (\Throwable $e) {if ($db->inTransaction())$db->rollBack();throw $e;}
        return ['status'=>'accepted'];
    }
    public function forgot(array $input): array
    {
        $email=$this->email($input);$this->limit('forgot:'.$email);
        $temp='Tmp!7'.bin2hex(random_bytes(16));$hash=password_hash($temp,PASSWORD_BCRYPT,['cost'=>12]);
        $db=Connections::accounts();$db->beginTransaction();
        try {
            $q=$db->prepare('SELECT *,mdp_temporaire_expire_le>UTC_TIMESTAMP() AS pending FROM utilisateurs WHERE email_normalise=? FOR UPDATE');$q->execute([$email]);$u=$q->fetch();
            if ($u&&$u['statut']==='actif'&&!$u['pending']) {
                $db->prepare('UPDATE utilisateurs SET mdp_temporaire_hash=?,mdp_temporaire_expire_le=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE) WHERE id=?')->execute([$hash,$u['id']]);
                MailQueue::enqueue($db,'recuperation',$email,'Votre mot de passe temporaire Innov’Events',"Mot de passe temporaire : $temp\nValable 30 minutes. Connectez-vous puis choisissez immédiatement un nouveau mot de passe.\nSi vous n’avez pas demandé cette récupération, ignorez ce message : votre mot de passe habituel reste valable.",30);
            }
            $db->commit();
        } catch (\Throwable $e) {if ($db->inTransaction())$db->rollBack();throw $e;}
        return ['status'=>'accepted'];
    }
    public function change(array $input): array
    {
        $u=(new Authentication())->current();if (!$u)throw new HttpError(401,'authentication_required');
        $this->limit('change:'.$u['id']);$new=$this->password($input);
        $old=$input['current_password']??null;if (!is_string($old)||strlen($old)>72)throw new HttpError(422,'invalid_input');
        $db=Connections::accounts();$db->beginTransaction();
        try {
            $q=$db->prepare('SELECT *,mdp_temporaire_expire_le>UTC_TIMESTAMP() AS temporary_valid FROM utilisateurs WHERE id=? FOR UPDATE');$q->execute([$u['id']]);$row=$q->fetch();
            $temporary=(bool)($_SESSION['temporary_auth']??false);
            $hash=$temporary?($row['mdp_temporaire_hash']??''):($row['mot_de_passe_hash']??'');
            if (!$row||$row['statut']!=='actif'||($temporary&&!$row['temporary_valid'])||!hash_equals($_SESSION['credential']??'',hash('sha256',$hash)))throw new HttpError(401,'authentication_required');
            if (!password_verify($old,$hash))throw new HttpError(422,'current_password_invalid');
            if (password_verify($new,$row['mot_de_passe_hash'])||($row['mdp_temporaire_hash']&&password_verify($new,$row['mdp_temporaire_hash'])))throw new HttpError(422,'password_must_change');
            $db->prepare('UPDATE utilisateurs SET mot_de_passe_hash=?,changement_mdp_requis=0,mdp_temporaire_hash=NULL,mdp_temporaire_expire_le=NULL WHERE id=?')->execute([password_hash($new,PASSWORD_BCRYPT,['cost'=>12]),$u['id']]);
            $db->commit();
        } catch (\Throwable $e) {if ($db->inTransaction())$db->rollBack();throw $e;}
        WebSession::reset();return ['status'=>'password_changed','csrf'=>$_SESSION['csrf'],'user'=>null];
    }
}
