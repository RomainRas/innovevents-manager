<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use InnovEvents\Infrastructure\Connections;
use InnovEvents\Http\HttpError;
use InnovEvents\Security\LoginLimiter;
final class QuoteRequests
{
    public function config(): array
    {
        $q=Connections::mysql()->prepare('SELECT valeur FROM parametres_application WHERE cle=?');
        $q->execute(['remerciement_demande_devis']);$value=$q->fetchColumn();
        if (!is_string($value)||$value==='')throw new \RuntimeException('Missing quote configuration');
        return ['message'=>$value];
    }
    public function saveThanks(array $input): array
    {
        $user=(new Authentication())->requireUser(['administrateur']);
        $message=$input['message']??null;
        if (!is_string($message)||trim($message)===''||preg_match_all('/./us',$message)>2000||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$message))throw new HttpError(422,'invalid_message');
        $q=Connections::settings()->prepare('UPDATE parametres_application SET valeur=?,modifie_par=? WHERE cle=?');
        $q->execute([trim($message),$user['id'],'remerciement_demande_devis']);
        return ['message'=>trim($message)];
    }
    private function validate(array $input): array
    {
        $data=[];$errors=[];
        foreach (['entreprise'=>200,'nom'=>100,'prenom'=>100,'email'=>254,'telephone'=>32,'lieu'=>255,'type_evenement'=>100,'date_souhaitee'=>10,'besoin'=>5000] as $key=>$max) {
            $value=$input[$key]??null;
            if (!is_string($value)||trim($value)===''||preg_match_all('/./us',$value)>$max||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$value)) {
                $errors[$key]="Renseignez ce champ (maximum $max caractères).";continue;
            }
            $data[$key]=trim($value);
        }
        if (isset($data['email'])&&!filter_var($data['email'],FILTER_VALIDATE_EMAIL))$errors['email']='Saisissez une adresse électronique valide.';
        if (isset($data['telephone'])&&(!preg_match('/^[+0-9(). \-]+$/',$data['telephone'])||strlen(preg_replace('/\D/','',$data['telephone']))<6))$errors['telephone']='Saisissez un numéro de téléphone valide.';
        if (isset($data['date_souhaitee'])) {
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$data['date_souhaitee']);
            if (!$date||$date->format('Y-m-d')!==$data['date_souhaitee']||$data['date_souhaitee']<'1000-01-01')$errors['date_souhaitee']='Saisissez une date valide.';
        }
        $count=$input['participants_estimes']??null;
        if (!(is_int($count)||is_string($count))||!preg_match('/^[1-9][0-9]{0,9}$/',(string)$count)||(float)$count>4294967295)$errors['participants_estimes']='Saisissez un nombre entier strictement positif.';
        else $data['participants_estimes']=(int)$count;
        if ($errors)throw new HttpError(422,'validation_failed',$errors);
        return $data;
    }
    public function submit(array $input): array
    {
        $key=$input['submission_key']??null;
        if (!is_string($key)||!preg_match('/^[a-f0-9]{64}$/',$key))throw new HttpError(422,'invalid_submission');
        $data=$this->validate($input);
        $hash=hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));$db=Connections::quotes();
        $lookup=function()use($db,$key,$hash):?array {
            $q=$db->prepare('SELECT empreinte,remerciement FROM soumissions_devis WHERE cle=?');$q->execute([$key]);$row=$q->fetch();
            if (!$row)return null;
            if (!hash_equals($row['empreinte'],$hash))throw new HttpError(409,'submission_conflict');
            return ['status'=>'accepted','message'=>$row['remerciement']];
        };
        if ($previous=$lookup())return $previous;
        $ip=$_SERVER['REMOTE_ADDR']??'127.0.0.1';
        if (!(new LoginLimiter())->allow('quote:'.$ip,$ip))throw new HttpError(429,'too_many_attempts');
        $message=$this->config()['message'];
        $recipient=getenv('AGENCY_EMAIL')?:'agence@example.invalid';
        if (!filter_var($recipient,FILTER_VALIDATE_EMAIL))throw new \RuntimeException('Agency configuration');
        $db->beginTransaction();
        try {
            $q=$db->prepare('INSERT INTO prospects(entreprise,nom,prenom,email,telephone,lieu,type_evenement,date_souhaitee,participants_estimes,besoin) VALUES(?,?,?,?,?,?,?,?,?,?)');
            $q->execute(array_map(fn($k)=>$data[$k],['entreprise','nom','prenom','email','telephone','lieu','type_evenement','date_souhaitee','participants_estimes','besoin']));
            $id=$db->lastInsertId();
            $q=$db->prepare('INSERT INTO soumissions_devis(cle,empreinte,prospect_id,remerciement) VALUES(?,?,?,?)');$q->execute([$key,$hash,$id,$message]);
            $labels=['entreprise'=>'Entreprise','nom'=>'Nom','prenom'=>'Prénom','email'=>'Adresse électronique','telephone'=>'Téléphone','lieu'=>'Lieu','type_evenement'=>'Type d’événement','date_souhaitee'=>'Date souhaitée','participants_estimes'=>'Participants estimés','besoin'=>'Besoin'];
            $lines=[];foreach($data as $field=>$value)$lines[]=$labels[$field].' : '.$value;
            MailQueue::enqueue($db,'demande_devis',$recipient,'Nouvelle demande de devis Innov’Events',implode("\n",$lines),1440);
            $db->commit();return ['status'=>'accepted','message'=>$message];
        } catch (\PDOException $e) {
            if ($db->inTransaction())$db->rollBack();
            if ((int)($e->errorInfo[1]??0)===1062&&($previous=$lookup()))return $previous;
            throw $e;
        } catch (\Throwable $e) {if ($db->inTransaction())$db->rollBack();throw $e;}
    }
}
