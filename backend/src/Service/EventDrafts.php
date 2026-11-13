<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use InnovEvents\Infrastructure\Connections;
use InnovEvents\Http\HttpError;
final class EventDrafts
{
    private function id(mixed $id): string {if(!(is_int($id)||is_string($id))||!preg_match('/^[1-9][0-9]{0,17}$/',(string)$id))throw new HttpError(422,'invalid_id');return (string)$id;}
    public function context(array $input): array {
        (new Authentication())->requireUser(['administrateur']);$db=Connections::events();
        $q=$db->prepare('SELECT p.*,c.entreprise AS client_entreprise,e.id AS evenement_id FROM prospects p JOIN clients c ON c.id=p.client_id LEFT JOIN evenements e ON e.prospect_id=p.id WHERE p.id=? AND p.statut=?');$q->execute([$this->id($input['prospect_id']??null),'converti']);$p=$q->fetch();
        if(!$p)throw new HttpError(404,'converted_prospect_not_found');
        return ['client_id'=>(string)$p['client_id'],'client_entreprise'=>$p['client_entreprise'],'event_id'=>$p['evenement_id']===null?null:(string)$p['evenement_id'],'suggested'=>['nom'=>$p['type_evenement'],'lieu'=>$p['lieu'],'type_evenement'=>$p['type_evenement'],'participants_estimes'=>$p['participants_estimes'],'date_souhaitee'=>$p['date_souhaitee']]];
    }
    private function output(array $row): array {
        return ['id'=>(string)$row['id'],'client_id'=>(string)$row['client_id'],'prospect_id'=>$row['prospect_id']===null?null:(string)$row['prospect_id'],'nom'=>$row['nom'],'lieu'=>$row['lieu'],'type_evenement'=>$row['type_evenement'],'participants_estimes'=>$row['participants_estimes'],'debut'=>EventSchedule::local($row['debut']),'fin'=>EventSchedule::local($row['fin']),'fuseau'=>$row['fuseau'],'statut'=>$row['statut'],'revision'=>(int)$row['revision']];
    }
    public function detail(array $input): array {
        (new Authentication())->requireUser(['administrateur']);$q=Connections::events()->prepare('SELECT * FROM evenements WHERE id=?');$q->execute([$this->id($input['id']??null)]);$row=$q->fetch();if(!$row)throw new HttpError(404,'event_not_found');return ['event'=>$this->output($row)];
    }
    private function validate(array $input): array {
        $data=[];$errors=[];
        foreach(['nom'=>200,'lieu'=>255,'type_evenement'=>100] as $field=>$max){$v=$input[$field]??'';
            if(!is_string($v)||preg_match_all('/./us',$v)>$max||preg_match('/[\x00-\x1F\x7F]/',$v)||($field==='nom'&&trim($v)===''))$errors[$field]="Vérifiez ce champ (maximum $max caractères).";
            else $data[$field]=trim($v)===''?null:trim($v);
        }
        foreach(['debut','fin'] as $field){try{$data[$field]=EventSchedule::utc($input[$field]??null);}catch(\InvalidArgumentException $e){$errors[$field]=$e->getMessage();}}
        if(!isset($errors['debut'])&&!isset($errors['fin'])&&($data['debut']??null)&&($data['fin']??null)&&$data['fin']<=$data['debut'])$errors['fin']='La fin doit être postérieure au début.';
        $v=$input['participants_estimes']??null;
        if($v===''||$v===null)$data['participants_estimes']=null;
        elseif(!(is_int($v)||is_string($v))||!preg_match('/^[1-9][0-9]{0,9}$/',(string)$v)||(float)$v>4294967295)$errors['participants_estimes']='Saisissez un entier strictement positif.';
        else $data['participants_estimes']=(int)$v;
        if($errors)throw new HttpError(422,'validation_failed',$errors);return $data;
    }
    public function create(array $input): array {
        $user=(new Authentication())->requireUser(['administrateur']);$id=$this->id($input['prospect_id']??null);$data=$this->validate($input);$hash=hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));$db=Connections::events();
        // Serialize retries for this prospect without granting UPDATE on prospects.
        $lock='innovevents.event.'.$id;$q=$db->prepare('SELECT GET_LOCK(?,5)');$q->execute([$lock]);
        if((int)$q->fetchColumn()!==1)throw new HttpError(503,'event_busy');
        try{
            $db->beginTransaction();
            $q=$db->prepare('SELECT client_id,statut FROM prospects WHERE id=? FOR SHARE');$q->execute([$id]);$p=$q->fetch();if(!$p)throw new HttpError(404,'prospect_not_found');if($p['statut']!=='converti')throw new HttpError(409,'prospect_not_converted');
            $q=$db->prepare('SELECT id,creation_empreinte FROM evenements WHERE prospect_id=?');$q->execute([$id]);$existing=$q->fetch();
            if($existing){if(!is_string($existing['creation_empreinte'])||!hash_equals($existing['creation_empreinte'],$hash))throw new HttpError(409,'event_already_exists');$db->commit();return ['event_id'=>(string)$existing['id']];}
            $q=$db->prepare('INSERT INTO evenements(client_id,prospect_id,nom,lieu,type_evenement,debut,fin,participants_estimes,fuseau,creation_empreinte) VALUES(?,?,?,?,?,?,?,?,?,?)');$q->execute([$p['client_id'],$id,$data['nom'],$data['lieu'],$data['type_evenement'],$data['debut'],$data['fin'],$data['participants_estimes'],'Europe/Paris',$hash]);$eventId=$db->lastInsertId();
            $q=$db->prepare("INSERT INTO journal_a_emettre(id_trace,horodatage,type_action,id_utilisateur,details) VALUES(UUID(),UTC_TIMESTAMP(6),'CREATION_EVENEMENT',?,?)");$q->execute([$user['id'],json_encode(['id_evenement'=>(string)$eventId,'nom_evenement'=>$data['nom']],JSON_THROW_ON_ERROR)]);
            $db->commit();return ['event_id'=>(string)$eventId];
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        finally{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
    }
    public function save(array $input): array {
        (new Authentication())->requireUser(['administrateur']);$id=$this->id($input['id']??null);$revision=$this->id($input['revision']??null);$data=$this->validate($input);$db=Connections::events();$db->beginTransaction();
        try{
            $q=$db->prepare('SELECT * FROM evenements WHERE id=? FOR UPDATE');$q->execute([$id]);$row=$q->fetch();if(!$row)throw new HttpError(404,'event_not_found');
            if($row['statut']!=='brouillon')throw new HttpError(409,'event_not_draft');
            $same=true;foreach($data as $k=>$v){$actual=$row[$k];if(in_array($k,['debut','fin'],true)&&$actual!==null)$actual=substr($actual,0,19);if($k==='participants_estimes'&&$actual!==null)$actual=(int)$actual;if($actual!==$v)$same=false;}
            if((string)$row['revision']!==$revision){if($same&&(int)$row['revision']===(int)$revision+1){$db->commit();return ['event_id'=>$id,'revision'=>(int)$row['revision']];}throw new HttpError(409,'event_revision_conflict');}
            $q=$db->prepare('UPDATE evenements SET nom=?,lieu=?,type_evenement=?,debut=?,fin=?,participants_estimes=?,revision=revision+1 WHERE id=?');$q->execute([$data['nom'],$data['lieu'],$data['type_evenement'],$data['debut'],$data['fin'],$data['participants_estimes'],$id]);$db->commit();return ['event_id'=>$id,'revision'=>(int)$revision+1];
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
