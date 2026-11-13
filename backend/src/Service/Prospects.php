<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use InnovEvents\Infrastructure\Connections;
use InnovEvents\Http\HttpError;
final class Prospects
{
    private function authorize(): void { (new Authentication())->requireUser(['administrateur']); }
    private function id(mixed $value): string
    {
        if (!(is_string($value)||is_int($value))||!preg_match('/^[1-9][0-9]{0,17}$/',(string)$value))throw new HttpError(422,'invalid_id');
        return (string)$value;
    }
    public function listing(array $input): array
    {
        $this->authorize();$status=$input['status']??'a_contacter';$search=$input['search']??'';
        if (!is_string($status)||!in_array($status,['','a_contacter','echoue','converti'],true)||!is_string($search)||preg_match_all('/./us',$search)>200)throw new HttpError(422,'invalid_filter');
        $where=[];$params=[];
        if ($status!==''){$where[]='statut=?';$params[]=$status;}
        if (trim($search)!==''){$where[]="LOCATE(?,CONCAT_WS(' ',entreprise,nom,prenom,email))>0";$params[]=trim($search);}
        if (isset($input['before'])&&$input['before']!==''){$where[]='id<?';$params[]=$this->id($input['before']);}
        $q=Connections::prospects()->prepare('SELECT id,entreprise,nom,prenom,type_evenement,date_souhaitee,statut FROM prospects'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY id DESC LIMIT 26');
        $q->execute($params);$rows=$q->fetchAll();$more=count($rows)>25;if($more)array_pop($rows);
        return ['prospects'=>$rows,'next'=>$more?(string)$rows[24]['id']:null];
    }
    public function detail(array $input): array
    {
        $this->authorize();$id=$this->id($input['id']??null);
        $q=Connections::prospects()->prepare('SELECT * FROM prospects WHERE id=?');$q->execute([$id]);$row=$q->fetch();
        if (!$row)throw new HttpError(404,'prospect_not_found');
        return ['prospect'=>$row];
    }
    public function fail(array $input): array
    {
        $this->authorize();$id=$this->id($input['id']??null);$message=$input['message']??null;
        if (!is_string($message)||trim($message)===''||preg_match_all('/./us',$message)>2000||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$message))throw new HttpError(422,'invalid_message');
        $message=trim($message);$db=Connections::prospects();$db->beginTransaction();
        try {
            $q=$db->prepare('SELECT * FROM prospects WHERE id=? FOR UPDATE');$q->execute([$id]);$row=$q->fetch();
            if (!$row)throw new HttpError(404,'prospect_not_found');
            if ($row['statut']==='echoue'&&$row['message_echec']===$message) {$db->commit();return ['status'=>'echoue'];}
            if ($row['statut']!=='a_contacter')throw new HttpError(409,'prospect_already_processed');
            $q=$db->prepare("UPDATE prospects SET statut='echoue',message_echec=?,qualifie_le=UTC_TIMESTAMP(6) WHERE id=?");$q->execute([$message,$id]);
            MailQueue::enqueue($db,'qualification_echec',$row['email'],'Suite à votre demande Innov’Events',$message,1440);
            $db->commit();return ['status'=>'echoue'];
        } catch (\Throwable $e) {if ($db->inTransaction())$db->rollBack();throw $e;}
    }
}
