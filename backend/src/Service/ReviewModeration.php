<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;
/** Modération des avis par les employés et l'administratrice : seuls les avis validés sont publiés (vue v_avis_publics). */
final class ReviewModeration
{
    private const FILTERS=['en_attente','valide','refuse'];
    public function listing(array $input): array {
        (new Authentication())->requireUser(['employe','administrateur']);
        $status=$input['statut']??'en_attente';if(!in_array($status,self::FILTERS,true))throw new HttpError(422,'invalid_filter');
        $q=Connections::reviews()->prepare("SELECT a.id,a.contenu,a.statut,a.cree_le,a.modere_le,e.nom AS evenement,e.fin,
            (e.statut<>'brouillon' AND e.visible_demande=1 AND e.accord_publication=1) AS evenement_public,u.prenom,LEFT(u.nom,1) AS initiale
            FROM avis a JOIN evenements e ON e.id=a.evenement_id JOIN utilisateurs u ON u.id=a.auteur_id
            WHERE a.statut=? ORDER BY ".($status==='en_attente'?'a.cree_le,a.id':'a.modere_le DESC,a.id DESC').' LIMIT 100');
        $q->execute([$status]);
        return ['avis'=>array_map(static fn(array $r)=>['id'=>(string)$r['id'],'contenu'=>$r['contenu'],'statut'=>$r['statut'],'depose_le'=>$r['cree_le'],
            'modere_le'=>$r['modere_le'],'evenement'=>$r['evenement']??'Événement sans nom','evenement_public'=>(bool)$r['evenement_public'],
            'auteur'=>$r['prenom'].' '.$r['initiale'].'.'],$q->fetchAll())];
    }
    public function moderate(array $input): array {
        $user=(new Authentication())->requireUser(['employe','administrateur']);
        $id=$input['id']??null;if(!is_string($id)||!preg_match('/^[1-9][0-9]{0,17}$/D',$id))throw new HttpError(422,'invalid_id');
        $decision=$input['decision']??null;if(!in_array($decision,['valide','refuse'],true))throw new HttpError(422,'invalid_decision');
        $db=Connections::reviews();
        $q=$db->prepare("UPDATE avis SET statut=?,moderateur_id=?,modere_le=UTC_TIMESTAMP(6) WHERE id=? AND statut='en_attente'");
        $q->execute([$decision,$user['id'],$id]);
        if($q->rowCount()!==1){
            $q=$db->prepare('SELECT statut FROM avis WHERE id=?');$q->execute([$id]);
            throw $q->fetchColumn()===false?new HttpError(404,'review_not_found'):new HttpError(409,'review_already_moderated');
        }
        return ['id'=>$id,'statut'=>$decision];
    }
}
