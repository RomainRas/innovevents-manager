<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use InnovEvents\Infrastructure\Connections;
use InnovEvents\Http\HttpError;
final class QuoteDrafts
{
    private function id(mixed $id): string {if(!(is_int($id)||is_string($id))||!preg_match('/^[1-9][0-9]{0,17}$/D',(string)$id))throw new HttpError(422,'invalid_id');return (string)$id;}
    private function current(\PDO $db,string $event): ?array {
        $q=$db->prepare('SELECT v.*,d.reference FROM devis d JOIN versions_devis v ON v.id=d.version_courante_id AND v.devis_id=d.id WHERE d.evenement_id=?');$q->execute([$event]);$v=$q->fetch();
        if(!$v)return null;
        $q=$db->prepare('SELECT libelle,montant_ht FROM prestations_devis WHERE version_id=? ORDER BY ordre');$q->execute([$v['id']]);$v['prestations']=$q->fetchAll();return $v;
    }
    public function read(array $input): array {
        (new Authentication())->requireUser(['administrateur']);$id=$this->id($input['event_id']??null);$db=Connections::quoteDrafts();
        $q=$db->prepare('SELECT id,nom,statut FROM evenements WHERE id=?');$q->execute([$id]);$event=$q->fetch();if(!$event)throw new HttpError(404,'event_not_found');
        $v=$this->current($db,$id);
        return ['event'=>$event,'quote'=>$v?['reference'=>$v['reference'],'revision'=>(int)$v['revision_contenu'],'statut'=>$v['statut'],'editable'=>$v['statut']==='brouillon'&&$v['figee_le']===null,'prestations'=>$v['prestations'],'taux_tva'=>$v['taux_tva'],'total_ht'=>$v['total_ht'],'total_tva'=>$v['total_tva'],'total_ttc'=>$v['total_ttc']]:null];
    }
    /**
     * Contre-proposition après une demande de modification : nouvelle version brouillon,
     * reprise des prestations et du taux de la version transmise, qui reste conservée.
     */
    public function counterProposal(array $input): array {
        (new Authentication())->requireUser(['administrateur']);$id=$this->id($input['event_id']??null);
        $from=$input['version']??null;if(!is_int($from)||$from<1||$from>100000)throw new HttpError(422,'invalid_version');
        $db=Connections::quoteDrafts();$lock='innovevents.quote.'.$id;
        $q=$db->prepare('SELECT GET_LOCK(?,5)');$q->execute([$lock]);if((int)$q->fetchColumn()!==1)throw new HttpError(503,'quote_busy');
        try{
            $db->beginTransaction();
            $q=$db->prepare('SELECT statut FROM evenements WHERE id=? FOR SHARE');$q->execute([$id]);$status=$q->fetchColumn();
            if($status===false)throw new HttpError(404,'event_not_found');if($status!=='brouillon')throw new HttpError(409,'event_not_draft');
            $v=$this->current($db,$id);if(!$v)throw new HttpError(409,'quote_required');
            // Répétition après réponse perdue : la nouvelle version existe déjà.
            if((int)$v['numero']===$from+1&&$v['statut']==='brouillon'){$db->commit();return ['version'=>(int)$v['numero'],'revision'=>(int)$v['revision_contenu'],'repetition'=>true];}
            if((int)$v['numero']!==$from||$v['statut']!=='modification')throw new HttpError(409,'counter_proposal_not_allowed');
            $q=$db->prepare('INSERT INTO versions_devis(devis_id,numero) VALUES(?,?)');$q->execute([$v['devis_id'],$from+1]);$newId=$db->lastInsertId();
            $q=$db->prepare('UPDATE versions_devis SET revision_contenu=1,devise=?,taux_tva=?,regle_calcul=?,total_ht=?,total_tva=?,total_ttc=? WHERE id=?');
            $q->execute([$v['devise'],$v['taux_tva'],$v['regle_calcul'],$v['total_ht'],$v['total_tva'],$v['total_ttc'],$newId]);
            $q=$db->prepare('INSERT INTO prestations_devis(version_id,ordre,libelle,montant_ht) VALUES(?,?,?,?)');foreach($v['prestations'] as $i=>$line)$q->execute([$newId,$i+1,$line['libelle'],$line['montant_ht']]);
            $q=$db->prepare('UPDATE devis SET version_courante_id=? WHERE id=?');$q->execute([$newId,$v['devis_id']]);
            $db->commit();return ['version'=>$from+1,'revision'=>1,'repetition'=>false];
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}finally{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
    }
    public function save(array $input): array {
        (new Authentication())->requireUser(['administrateur']);$id=$this->id($input['event_id']??null);
        $revision=$input['revision']??null;if(!is_int($revision)||$revision<0||$revision>2147483646)throw new HttpError(422,'invalid_revision');
        $data=QuoteAmounts::calculate($input);$db=Connections::quoteDrafts();$lock='innovevents.quote.'.$id;
        $q=$db->prepare('SELECT GET_LOCK(?,5)');$q->execute([$lock]);if((int)$q->fetchColumn()!==1)throw new HttpError(503,'quote_busy');
        try{
            $db->beginTransaction();$q=$db->prepare('SELECT statut FROM evenements WHERE id=? FOR SHARE');$q->execute([$id]);$status=$q->fetchColumn();
            if($status===false)throw new HttpError(404,'event_not_found');if($status!=='brouillon')throw new HttpError(409,'event_not_draft');
            $v=$this->current($db,$id);
            if($v){
                if($v['statut']!=='brouillon'||$v['figee_le']!==null)throw new HttpError(409,'quote_not_editable');
                if((int)$v['revision_contenu']!==$revision){
                    if((int)$v['revision_contenu']===$revision+1&&$v['prestations']===$data['prestations']&&$v['taux_tva']===$data['taux_tva']){$db->commit();return ['revision'=>(int)$v['revision_contenu'],'reference'=>$v['reference']]+$data;}
                    throw new HttpError(409,'quote_revision_conflict');
                }
                $versionId=$v['id'];$reference=$v['reference'];$next=$revision+1;
                $q=$db->prepare("UPDATE documents_devis SET etat='obsolete' WHERE version_id=? AND etat='disponible'");$q->execute([$versionId]);
                $q=$db->prepare('DELETE FROM prestations_devis WHERE version_id=?');$q->execute([$versionId]);
            }else{
                if($revision!==0)throw new HttpError(409,'quote_revision_conflict');
                $q=$db->prepare('SELECT id,reference FROM devis WHERE evenement_id=?');$q->execute([$id]);$d=$q->fetch();
                if(!$d){$reference='IE-'.$id;$q=$db->prepare('INSERT INTO devis(evenement_id,reference) VALUES(?,?)');$q->execute([$id,$reference]);$devisId=$db->lastInsertId();}else{$devisId=$d['id'];$reference=$d['reference'];}
                $q=$db->prepare('INSERT INTO versions_devis(devis_id,numero) VALUES(?,1)');$q->execute([$devisId]);$versionId=$db->lastInsertId();$next=1;
                $q=$db->prepare('UPDATE devis SET version_courante_id=? WHERE id=?');$q->execute([$versionId,$devisId]);
            }
            $q=$db->prepare('UPDATE versions_devis SET revision_contenu=?,devise=?,taux_tva=?,regle_calcul=?,total_ht=?,total_tva=?,total_ttc=? WHERE id=?');$q->execute([$next,$data['devise'],$data['taux_tva'],$data['regle_calcul'],$data['total_ht'],$data['total_tva'],$data['total_ttc'],$versionId]);
            $q=$db->prepare('INSERT INTO prestations_devis(version_id,ordre,libelle,montant_ht) VALUES(?,?,?,?)');foreach($data['prestations'] as $i=>$line)$q->execute([$versionId,$i+1,$line['libelle'],$line['montant_ht']]);
            $db->commit();return ['revision'=>$next,'reference'=>$reference]+$data;
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}finally{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
    }
}
