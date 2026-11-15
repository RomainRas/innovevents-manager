<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;
/**
 * Espace employé (hérité par l'administratrice) : consultation de tous les clients et événements,
 * notes collaboratives sur les événements et statut des tâches assignées.
 * Règle des notes : l'auteur modifie ou supprime ses notes ; l'administratrice peut modifier ou supprimer toutes les notes.
 */
final class TeamSpace
{
    private const ROLES=['employe','administrateur'];
    private const NEXT=['a_faire'=>'en_cours','en_cours'=>'termine'];
    private static function id(mixed $v): string {if(!(is_int($v)||is_string($v))||!preg_match('/^[1-9][0-9]{0,17}$/D',(string)$v))throw new HttpError(422,'invalid_id');return (string)$v;}
    private static function search(array $input): string {
        $q=$input['q']??'';if(!is_string($q)||preg_match_all('/./us',$q)>100||preg_match('/[\x00-\x1F\x7F]/',$q))throw new HttpError(422,'invalid_filter');
        return trim($q);
    }
    private static function like(string $q): string {return '%'.addcslashes($q,'%_\\').'%';}
    private static function event(array $r): array {
        return ['id'=>(string)$r['id'],'nom'=>$r['nom']??'Événement sans nom','debut'=>EventSchedule::local($r['debut']),'fin'=>EventSchedule::local($r['fin']),
            'lieu'=>$r['lieu'],'statut'=>$r['statut']]+(array_key_exists('entreprise',$r)?['client'=>$r['entreprise'],'client_id'=>(string)$r['client_id']]:[]);
    }
    public function clients(array $input): array {
        (new Authentication())->requireUser(self::ROLES);$q=self::search($input);$params=[];$where='';
        if($q!==''){$where=' WHERE c.entreprise LIKE ? OR c.contact_nom LIKE ? OR c.contact_prenom LIKE ? OR c.email LIKE ? OR c.ville LIKE ?';$params=array_fill(0,5,self::like($q));}
        $s=Connections::team()->prepare('SELECT c.id,c.entreprise,c.contact_prenom,c.contact_nom,c.email,c.telephone,c.ville,
            (SELECT COUNT(*) FROM evenements e WHERE e.client_id=c.id) AS evenements FROM clients c'.$where.' ORDER BY c.entreprise,c.id LIMIT 200');
        $s->execute($params);
        return ['clients'=>array_map(static fn($r)=>['id'=>(string)$r['id'],'entreprise'=>$r['entreprise'],'contact'=>$r['contact_prenom'].' '.$r['contact_nom'],
            'email'=>$r['email'],'telephone'=>$r['telephone'],'ville'=>$r['ville'],'evenements'=>(int)$r['evenements']],$s->fetchAll())];
    }
    public function client(array $input): array {
        (new Authentication())->requireUser(self::ROLES);$id=self::id($input['id']??null);$db=Connections::team();
        $s=$db->prepare('SELECT id,entreprise,contact_prenom,contact_nom,email,telephone,adresse_ligne1,adresse_ligne2,code_postal,ville,pays_code,utilisateur_id,cree_le FROM clients WHERE id=?');
        $s->execute([$id]);$c=$s->fetch();if(!$c)throw new HttpError(404,'client_not_found');
        $s=$db->prepare('SELECT id,nom,debut,fin,lieu,statut FROM evenements WHERE client_id=? ORDER BY debut IS NULL,debut DESC,id DESC');$s->execute([$id]);
        return ['client'=>['id'=>(string)$c['id'],'entreprise'=>$c['entreprise'],'contact'=>$c['contact_prenom'].' '.$c['contact_nom'],'email'=>$c['email'],'telephone'=>$c['telephone'],
            'adresse'=>array_values(array_filter([$c['adresse_ligne1'],$c['adresse_ligne2'],trim(($c['code_postal']??'').' '.($c['ville']??'')),$c['pays_code']],static fn($v)=>is_string($v)&&trim($v)!=='')),
            'compte'=>$c['utilisateur_id']!==null,'cree_le'=>$c['cree_le'],
            // Valeurs séparées, pour préremplir la modification de la fiche par l'administratrice.
            'champs'=>array_intersect_key($c,array_flip(['entreprise','contact_prenom','contact_nom','email','telephone','adresse_ligne1','adresse_ligne2','code_postal','ville']))],'evenements'=>array_map([self::class,'event'],$s->fetchAll())];
    }
    public function events(array $input): array {
        (new Authentication())->requireUser(self::ROLES);$q=self::search($input);
        $status=$input['statut']??'';if(!in_array($status,['','brouillon','accepte','en_cours','termine','annule'],true))throw new HttpError(422,'invalid_filter');
        $where=[];$params=[];
        if($status!==''){$where[]='e.statut=?';$params[]=$status;}
        if($q!==''){$where[]='(e.nom LIKE ? OR e.lieu LIKE ? OR c.entreprise LIKE ?)';array_push($params,...array_fill(0,3,self::like($q)));}
        $s=Connections::team()->prepare('SELECT e.id,e.nom,e.debut,e.fin,e.lieu,e.statut,e.client_id,c.entreprise FROM evenements e JOIN clients c ON c.id=e.client_id'
            .($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY e.debut IS NULL,e.debut DESC,e.id DESC LIMIT 200');
        $s->execute($params);return ['evenements'=>array_map([self::class,'event'],$s->fetchAll())];
    }
    public function eventDetail(array $input): array {
        $user=(new Authentication())->requireUser(self::ROLES);$id=self::id($input['id']??null);$db=Connections::team();
        $s=$db->prepare('SELECT e.id,e.nom,e.debut,e.fin,e.lieu,e.type_evenement,e.theme,e.participants_estimes,e.statut,e.client_id,c.entreprise,c.contact_prenom,c.contact_nom,c.telephone,c.email
            FROM evenements e JOIN clients c ON c.id=e.client_id WHERE e.id=?');
        $s->execute([$id]);$e=$s->fetch();if(!$e)throw new HttpError(404,'event_not_found');
        $admin=$user['role']==='administrateur';
        $s=$db->prepare('SELECT n.id,n.contenu,n.cree_le,n.modifie_le,n.auteur_id,u.prenom,u.nom FROM notes_evenement n JOIN utilisateurs u ON u.id=n.auteur_id WHERE n.evenement_id=? ORDER BY n.cree_le DESC,n.id DESC');
        $s->execute([$id]);
        $notes=array_map(static fn($n)=>['id'=>(string)$n['id'],'contenu'=>$n['contenu'],'cree_le'=>$n['cree_le'],'modifiee'=>$n['modifie_le']!==$n['cree_le'],
            'auteur'=>$n['prenom'].' '.mb_substr($n['nom'],0,1).'.','modifiable'=>$admin||(string)$n['auteur_id']===(string)$user['id']],$s->fetchAll());
        $s=$db->prepare('SELECT t.id,t.titre,t.description,t.statut,t.assigne_a,u.prenom,u.nom FROM taches t LEFT JOIN utilisateurs u ON u.id=t.assigne_a WHERE t.evenement_id=? ORDER BY FIELD(t.statut,\'en_cours\',\'a_faire\',\'termine\'),t.id');
        $s->execute([$id]);
        $tasks=array_map(static fn($t)=>['id'=>(string)$t['id'],'titre'=>$t['titre'],'description'=>$t['description'],'statut'=>$t['statut'],
            'assigne'=>$t['assigne_a']===null?null:$t['prenom'].' '.$t['nom'],'a_moi'=>(string)$t['assigne_a']===(string)$user['id'],
            'suivant'=>(string)$t['assigne_a']===(string)$user['id']?(self::NEXT[$t['statut']]??null):null],$s->fetchAll());
        return ['evenement'=>self::event($e)+['type_evenement'=>$e['type_evenement'],'theme'=>$e['theme'],'participants'=>$e['participants_estimes']===null?null:(int)$e['participants_estimes'],
            'contact'=>$e['contact_prenom'].' '.$e['contact_nom'],'telephone'=>$e['telephone'],'email'=>$e['email']],'notes'=>$notes,'taches'=>$tasks];
    }
    private static function content(mixed $v): string {
        if(!is_string($v)||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$v)||!mb_check_encoding($v,'UTF-8')||trim($v)===''||preg_match_all('/./us',trim($v))>5000)
            throw new HttpError(422,'validation_failed',['contenu'=>'Rédigez la note (5 000 caractères au maximum).']);
        return trim($v);
    }
    /** Note existante modifiable par l'utilisateur : la sienne, ou n'importe laquelle pour l'administratrice. */
    private function ownedNote(\PDO $db,array $user,string $id): array {
        $s=$db->prepare('SELECT id,evenement_id,auteur_id FROM notes_evenement WHERE id=?');$s->execute([$id]);$n=$s->fetch();
        if(!$n)throw new HttpError(404,'note_not_found');
        if($user['role']!=='administrateur'&&(string)$n['auteur_id']!==(string)$user['id'])throw new HttpError(403,'not_note_author');
        return $n;
    }
    public function saveNote(array $input): array {
        $user=(new Authentication())->requireUser(self::ROLES);$text=self::content($input['contenu']??null);$db=Connections::team();
        if(($input['id']??null)!==null&&($input['id']??'')!==''){
            $n=$this->ownedNote($db,$user,self::id($input['id']));
            $db->prepare('UPDATE notes_evenement SET contenu=? WHERE id=?')->execute([$text,$n['id']]);
            return $this->eventDetail(['id'=>(string)$n['evenement_id']]);
        }
        $event=self::id($input['evenement_id']??null);
        $s=$db->prepare('SELECT id FROM evenements WHERE id=?');$s->execute([$event]);if($s->fetchColumn()===false)throw new HttpError(404,'event_not_found');
        $db->prepare('INSERT INTO notes_evenement(evenement_id,auteur_id,contenu) VALUES(?,?,?)')->execute([$event,$user['id'],$text]);
        return $this->eventDetail(['id'=>$event]);
    }
    public function deleteNote(array $input): array {
        $user=(new Authentication())->requireUser(self::ROLES);$db=Connections::team();
        $n=$this->ownedNote($db,$user,self::id($input['id']??null));
        $db->prepare('DELETE FROM notes_evenement WHERE id=?')->execute([$n['id']]);
        return $this->eventDetail(['id'=>(string)$n['evenement_id']]);
    }
    /** Seule la personne assignée fait avancer sa tâche : à faire → en cours → terminé. */
    public function taskStatus(array $input): array {
        $user=(new Authentication())->requireUser(self::ROLES);$id=self::id($input['id']??null);
        $to=$input['statut']??null;if(!in_array($to,['en_cours','termine'],true))throw new HttpError(422,'invalid_status');
        $db=Connections::team();
        $s=$db->prepare('SELECT evenement_id,assigne_a,statut FROM taches WHERE id=?');$s->execute([$id]);$t=$s->fetch();
        if(!$t)throw new HttpError(404,'task_not_found');
        if((string)$t['assigne_a']!==(string)$user['id'])throw new HttpError(403,'task_not_assigned');
        $from=array_search($to,self::NEXT,true);
        $s=$db->prepare('UPDATE taches SET statut=? WHERE id=? AND assigne_a=? AND statut=?');$s->execute([$to,$id,$user['id'],$from]);
        if($s->rowCount()!==1)throw new HttpError(409,'task_status_changed');
        return $this->eventDetail(['id'=>(string)$t['evenement_id']]);
    }
}
