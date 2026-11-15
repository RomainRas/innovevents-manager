<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;
/**
 * Gestion par l'administratrice (Chloé) des fiches clients, des événements, de leur statut et des tâches.
 * Journal exigé : CREATION_CLIENT, MODIFICATION_CLIENT, SUPPRESSION_CLIENT, CREATION_EVENEMENT, CHANGEMENT_STATUT_EVENEMENT.
 * Les suppressions sont refusées dès qu'elles effaceraient un historique commercial (devis, demande, compte) :
 * un événement avec devis s'annule, un client avec événements ou compte se conserve.
 */
final class AdminRecords
{
    private const STATUSES=['accepte','en_cours','termine','annule'];
    private static function id(mixed $v,string $error='invalid_id'): string {if(!(is_int($v)||is_string($v))||!preg_match('/^[1-9][0-9]{0,17}$/D',(string)$v))throw new HttpError(422,$error);return (string)$v;}
    private static function text(array $input,string $key,int $max,bool $required,array &$errors): ?string {
        $v=$input[$key]??'';
        if(!is_string($v)||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$v)||!mb_check_encoding($v,'UTF-8')||preg_match_all('/./us',trim($v))>$max){$errors[$key]="Maximum $max caractères.";return null;}
        $v=trim($v);if($v===''){if($required)$errors[$key]="Renseignez ce champ (maximum $max caractères).";return null;}
        return $v;
    }
    private static function log(\PDO $db,string $type,string $user,array $details): void {
        $db->prepare('INSERT INTO journal_a_emettre(id_trace,horodatage,type_action,id_utilisateur,details) VALUES(UUID(),UTC_TIMESTAMP(6),?,?,?)')
            ->execute([$type,$user,json_encode($details,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
    }
    private static function confirmed(array $input): void {if(($input['confirmation']??null)!==true)throw new HttpError(422,'confirmation_required');}

    // ---- Clients -------------------------------------------------------------------------------------------
    private function clientData(array $input): array {
        $errors=[];$d=[];
        foreach(['entreprise'=>200,'contact_prenom'=>100,'contact_nom'=>100,'email'=>254,'telephone'=>32] as $k=>$max)$d[$k]=self::text($input,$k,$max,true,$errors);
        foreach(['adresse_ligne1'=>255,'adresse_ligne2'=>255,'code_postal'=>20,'ville'=>120] as $k=>$max)$d[$k]=self::text($input,$k,$max,false,$errors);
        if($d['email']!==null&&!filter_var($d['email'],FILTER_VALIDATE_EMAIL))$errors['email']='Saisissez une adresse électronique valide.';
        if($d['telephone']!==null&&(!preg_match('/^[+0-9(). \-]+$/',$d['telephone'])||strlen(preg_replace('/\D/','',$d['telephone']))<6))$errors['telephone']='Saisissez un numéro de téléphone valide.';
        if($d['code_postal']!==null&&!preg_match('/^[A-Za-z0-9 \-]{2,20}$/',$d['code_postal']))$errors['code_postal']='Saisissez un code postal valide.';
        if($errors)throw new HttpError(422,'validation_failed',$errors);
        $d['pays_code']=($d['adresse_ligne1']||$d['ville'])?'FR':null;
        return $d;
    }
    public function createClient(array $input): array {
        $user=(new Authentication())->requireUser(['administrateur']);$d=$this->clientData($input);$db=Connections::records();
        try{
            $db->beginTransaction();
            $db->prepare('INSERT INTO clients(entreprise,contact_prenom,contact_nom,email,telephone,adresse_ligne1,adresse_ligne2,code_postal,ville,pays_code) VALUES(?,?,?,?,?,?,?,?,?,?)')
                ->execute(array_values($d));
            $id=(string)$db->lastInsertId();
            self::log($db,'CREATION_CLIENT',(string)$user['id'],['id_client'=>$id,'nom_client'=>$d['entreprise']]);
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        return ['id'=>$id];
    }
    public function saveClient(array $input): array {
        $user=(new Authentication())->requireUser(['administrateur']);$id=self::id($input['id']??null);$d=$this->clientData($input);$db=Connections::records();
        try{
            $db->beginTransaction();
            $s=$db->prepare('SELECT utilisateur_id FROM clients WHERE id=? FOR UPDATE');$s->execute([$id]);$row=$s->fetch();
            if(!$row)throw new HttpError(404,'client_not_found');
            // L'adresse de connexion d'un client avec compte se change par la gestion des comptes, pas par la fiche.
            $db->prepare('UPDATE clients SET entreprise=?,contact_prenom=?,contact_nom=?,email=?,telephone=?,adresse_ligne1=?,adresse_ligne2=?,code_postal=?,ville=?,pays_code=? WHERE id=?')
                ->execute([...array_values($d),$id]);
            self::log($db,'MODIFICATION_CLIENT',(string)$user['id'],['id_client'=>$id]);
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        return ['id'=>$id];
    }
    public function deleteClient(array $input): array {
        $user=(new Authentication())->requireUser(['administrateur']);$id=self::id($input['id']??null);self::confirmed($input);$db=Connections::records();
        try{
            $db->beginTransaction();
            $s=$db->prepare('SELECT entreprise,utilisateur_id FROM clients WHERE id=? FOR UPDATE');$s->execute([$id]);$row=$s->fetch();
            if(!$row)throw new HttpError(404,'client_not_found');
            if($row['utilisateur_id']!==null)throw new HttpError(409,'client_has_account');
            $s=$db->prepare('SELECT COUNT(*) FROM evenements WHERE client_id=?');$s->execute([$id]);if((int)$s->fetchColumn()>0)throw new HttpError(409,'client_has_events');
            $s=$db->prepare('SELECT COUNT(*) FROM prospects WHERE client_id=?');$s->execute([$id]);if((int)$s->fetchColumn()>0)throw new HttpError(409,'client_has_requests');
            $db->prepare('DELETE FROM clients WHERE id=?')->execute([$id]);
            self::log($db,'SUPPRESSION_CLIENT',(string)$user['id'],['id_client'=>$id,'nom_client'=>$row['entreprise']]);
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        return ['id'=>$id,'supprime'=>true];
    }

    // ---- Événements ----------------------------------------------------------------------------------------
    private function eventData(array $input): array {
        $errors=[];$d=[];
        $d['nom']=self::text($input,'nom',200,true,$errors);
        foreach(['lieu'=>255,'type_evenement'=>100,'theme'=>100] as $k=>$max)$d[$k]=self::text($input,$k,$max,false,$errors);
        foreach(['debut','fin'] as $k){try{$d[$k]=EventSchedule::utc($input[$k]??null);}catch(\InvalidArgumentException $e){$errors[$k]=$e->getMessage();}}
        if(!isset($errors['debut'])&&!isset($errors['fin'])&&$d['debut']&&$d['fin']&&$d['fin']<=$d['debut'])$errors['fin']='La fin doit être postérieure au début.';
        $v=$input['participants_estimes']??null;
        if($v===''||$v===null)$d['participants_estimes']=null;
        elseif(!(is_int($v)||is_string($v))||!preg_match('/^[1-9][0-9]{0,9}$/',(string)$v)||(float)$v>4294967295)$errors['participants_estimes']='Saisissez un entier strictement positif.';
        else $d['participants_estimes']=(int)$v;
        if($errors)throw new HttpError(422,'validation_failed',$errors);
        return $d;
    }
    public function staff(): array {
        (new Authentication())->requireUser(['administrateur']);
        $rows=Connections::records()->query("SELECT id,prenom,nom,role FROM utilisateurs WHERE role IN ('employe','administrateur') AND statut='actif' AND supprime_le IS NULL ORDER BY prenom,nom,id")->fetchAll();
        $clients=Connections::records()->query('SELECT id,entreprise FROM clients ORDER BY entreprise,id LIMIT 500')->fetchAll();
        return ['equipe'=>array_map(static fn($r)=>['id'=>(string)$r['id'],'nom'=>$r['prenom'].' '.$r['nom'],'role'=>$r['role']],$rows),
            'clients'=>array_map(static fn($r)=>['id'=>(string)$r['id'],'entreprise'=>$r['entreprise']],$clients)];
    }
    public function eventForEdit(array $input): array {
        (new Authentication())->requireUser(['administrateur']);$id=self::id($input['id']??null);
        $s=Connections::records()->prepare('SELECT e.id,e.client_id,e.nom,e.debut,e.fin,e.lieu,e.type_evenement,e.theme,e.participants_estimes,e.statut,e.revision,
            (SELECT COUNT(*) FROM devis d WHERE d.evenement_id=e.id) AS devis FROM evenements e WHERE e.id=?');
        $s->execute([$id]);$e=$s->fetch();if(!$e)throw new HttpError(404,'event_not_found');
        return ['evenement'=>['id'=>(string)$e['id'],'client_id'=>(string)$e['client_id'],'nom'=>$e['nom'],'debut'=>EventSchedule::local($e['debut']),'fin'=>EventSchedule::local($e['fin']),
            'lieu'=>$e['lieu'],'type_evenement'=>$e['type_evenement'],'theme'=>$e['theme'],'participants_estimes'=>$e['participants_estimes']===null?null:(int)$e['participants_estimes'],
            'statut'=>$e['statut'],'revision'=>(int)$e['revision'],'avec_devis'=>(int)$e['devis']>0]]+$this->staff();
    }
    public function createEvent(array $input): array {
        $user=(new Authentication())->requireUser(['administrateur']);$client=self::id($input['client_id']??null,'invalid_client');$d=$this->eventData($input);$db=Connections::records();
        try{
            $db->beginTransaction();
            $s=$db->prepare('SELECT id FROM clients WHERE id=?');$s->execute([$client]);if($s->fetchColumn()===false)throw new HttpError(404,'client_not_found');
            $db->prepare("INSERT INTO evenements(client_id,nom,debut,fin,fuseau,lieu,type_evenement,theme,participants_estimes) VALUES(?,?,?,?,'Europe/Paris',?,?,?,?)")
                ->execute([$client,$d['nom'],$d['debut'],$d['fin'],$d['lieu'],$d['type_evenement'],$d['theme'],$d['participants_estimes']]);
            $id=(string)$db->lastInsertId();
            self::log($db,'CREATION_EVENEMENT',(string)$user['id'],['id_evenement'=>$id,'nom_evenement'=>$d['nom']]);
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        return ['id'=>$id];
    }
    /** Modification avec contrôle de révision : deux personnes ne s'écrasent pas sans le savoir. */
    public function saveEvent(array $input): array {
        (new Authentication())->requireUser(['administrateur']);$id=self::id($input['id']??null);$revision=self::id($input['revision']??null,'invalid_revision');$d=$this->eventData($input);$db=Connections::records();
        try{
            $db->beginTransaction();
            $s=$db->prepare('SELECT statut,revision FROM evenements WHERE id=? FOR UPDATE');$s->execute([$id]);$row=$s->fetch();
            if(!$row)throw new HttpError(404,'event_not_found');
            if($row['statut']==='annule')throw new HttpError(409,'event_cancelled');
            if((string)$row['revision']!==$revision)throw new HttpError(409,'event_revision_conflict');
            $db->prepare('UPDATE evenements SET nom=?,debut=?,fin=?,lieu=?,type_evenement=?,theme=?,participants_estimes=?,revision=revision+1 WHERE id=?')
                ->execute([$d['nom'],$d['debut'],$d['fin'],$d['lieu'],$d['type_evenement'],$d['theme'],$d['participants_estimes'],$id]);
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        return $this->eventForEdit(['id'=>$id]);
    }
    public function setEventStatus(array $input): array {
        $user=(new Authentication())->requireUser(['administrateur']);$id=self::id($input['id']??null);
        $to=$input['statut']??null;if(!in_array($to,self::STATUSES,true))throw new HttpError(422,'invalid_status');$db=Connections::records();
        try{
            $db->beginTransaction();
            $s=$db->prepare('SELECT statut,nom,debut,fin FROM evenements WHERE id=? FOR UPDATE');$s->execute([$id]);$row=$s->fetch();
            if(!$row)throw new HttpError(404,'event_not_found');
            if($row['statut']==='annule')throw new HttpError(409,'event_cancelled');
            if($row['statut']===$to)throw new HttpError(409,'status_unchanged');
            if($to!=='annule'&&($row['nom']===null||$row['debut']===null||$row['fin']===null))throw new HttpError(422,'event_incomplete');
            $db->prepare('UPDATE evenements SET statut=?,revision=revision+1 WHERE id=?')->execute([$to,$id]);
            self::log($db,'CHANGEMENT_STATUT_EVENEMENT',(string)$user['id'],['id_evenement'=>$id,'ancien_statut'=>$row['statut'],'nouveau_statut'=>$to]);
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        return $this->eventForEdit(['id'=>$id]);
    }
    /** Suppression réservée aux événements sans devis ; ses notes, tâches et avis sont supprimés avec lui. */
    public function deleteEvent(array $input): array {
        (new Authentication())->requireUser(['administrateur']);$id=self::id($input['id']??null);self::confirmed($input);$db=Connections::records();
        try{
            $db->beginTransaction();
            $s=$db->prepare('SELECT id FROM evenements WHERE id=? FOR UPDATE');$s->execute([$id]);if($s->fetchColumn()===false)throw new HttpError(404,'event_not_found');
            $s=$db->prepare('SELECT COUNT(*) FROM devis WHERE evenement_id=?');$s->execute([$id]);if((int)$s->fetchColumn()>0)throw new HttpError(409,'event_has_quote');
            foreach(['notes_evenement','taches','avis'] as $t)$db->prepare("DELETE FROM $t WHERE evenement_id=?")->execute([$id]);
            $db->prepare('DELETE FROM evenements WHERE id=?')->execute([$id]);
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        return ['id'=>$id,'supprime'=>true];
    }

    // ---- Tâches --------------------------------------------------------------------------------------------
    public function createTask(array $input): array {
        (new Authentication())->requireUser(['administrateur']);$event=self::id($input['evenement_id']??null);$errors=[];
        $title=self::text($input,'titre',200,true,$errors);$description=self::text($input,'description',2000,false,$errors);
        $assignee=$input['assigne_a']??'';
        if($assignee!==''&&$assignee!==null&&!(is_string($assignee)&&preg_match('/^[1-9][0-9]{0,17}$/D',$assignee)))$errors['assigne_a']='Choisissez une personne de l’équipe.';
        if($errors)throw new HttpError(422,'validation_failed',$errors);
        $db=Connections::records();
        $s=$db->prepare("SELECT statut FROM evenements WHERE id=?");$s->execute([$event]);$status=$s->fetchColumn();
        if($status===false)throw new HttpError(404,'event_not_found');if($status==='annule')throw new HttpError(409,'event_cancelled');
        if($assignee!==''&&$assignee!==null){
            $s=$db->prepare("SELECT id FROM utilisateurs WHERE id=? AND role IN ('employe','administrateur') AND statut='actif' AND supprime_le IS NULL");$s->execute([$assignee]);
            if($s->fetchColumn()===false)throw new HttpError(422,'validation_failed',['assigne_a'=>'Choisissez une personne active de l’équipe.']);
        }else $assignee=null;
        $db->prepare('INSERT INTO taches(evenement_id,assigne_a,titre,description) VALUES(?,?,?,?)')->execute([$event,$assignee,$title,$description]);
        return ['id'=>(string)$db->lastInsertId()];
    }
    public function deleteTask(array $input): array {
        (new Authentication())->requireUser(['administrateur']);$id=self::id($input['id']??null);
        $s=Connections::records()->prepare('DELETE FROM taches WHERE id=?');$s->execute([$id]);
        if($s->rowCount()!==1)throw new HttpError(404,'task_not_found');
        return ['id'=>$id,'supprime'=>true];
    }
}
