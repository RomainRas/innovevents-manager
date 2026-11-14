<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use InnovEvents\Infrastructure\Connections;
use InnovEvents\Http\HttpError;
/** Devis transmis, consultés et décidés par le client connecté uniquement (jamais les brouillons). */
final class ClientQuotes
{
    private const CHOICES=['accepte','refuse','modification'];
    public function listing(): array {
        $user=(new Authentication())->requireUser(['client']);$db=Connections::clientQuotes();
        $q=$db->prepare("SELECT d.reference,v.id,v.numero,v.statut,v.transmise_le,v.taux_tva,v.total_ht,v.total_tva,v.total_ttc,v.evenement_snapshot,d.version_courante_id,
            (SELECT t.document_id FROM transmissions_devis t JOIN documents_devis p ON p.id=t.document_id WHERE p.version_id=v.id ORDER BY t.id LIMIT 1) AS document_id,
            x.choix,x.motif,x.decidee_le
            FROM clients c JOIN evenements e ON e.client_id=c.id JOIN devis d ON d.evenement_id=e.id JOIN versions_devis v ON v.devis_id=d.id LEFT JOIN decisions_devis x ON x.version_id=v.id
            WHERE c.utilisateur_id=? AND v.statut<>'brouillon' AND v.transmise_le IS NOT NULL ORDER BY v.transmise_le DESC,v.id DESC LIMIT 50");
        $q->execute([$user['id']]);$rows=$q->fetchAll();
        $lines=$db->prepare('SELECT libelle,montant_ht FROM prestations_devis WHERE version_id=? ORDER BY ordre');
        return ['devis'=>array_map(static function(array $r)use($lines){
            $lines->execute([$r['id']]);$event=json_decode((string)$r['evenement_snapshot'],true)?:[];
            return ['version_id'=>(string)$r['id'],'reference'=>$r['reference'],'version'=>(int)$r['numero'],'statut'=>$r['statut'],'transmis_le'=>$r['transmise_le'],
                // Seule la version courante en étude peut recevoir une réponse.
                'decidable'=>$r['statut']==='etude_client'&&(string)$r['version_courante_id']===(string)$r['id'],
                'remplace'=>(string)$r['version_courante_id']!==(string)$r['id'],
                'decision'=>$r['choix']===null?null:['choix'=>$r['choix'],'motif'=>$r['motif'],'decidee_le'=>$r['decidee_le']],
                'evenement'=>['nom'=>$event['nom']??'','lieu'=>$event['lieu']??'','debut'=>EventSchedule::local($event['debut']??null),'fin'=>EventSchedule::local($event['fin']??null)],
                'prestations'=>$lines->fetchAll(),'taux_tva'=>$r['taux_tva'],'total_ht'=>$r['total_ht'],'total_tva'=>$r['total_tva'],'total_ttc'=>$r['total_ttc'],
                'document_id'=>$r['document_id']===null?null:(string)$r['document_id']];
        },$rows)];
    }
    public function decide(array $input): array {
        $user=(new Authentication())->requireUser(['client']);
        $versionId=$input['version_id']??null;if(!is_string($versionId)||!preg_match('/^[1-9][0-9]{0,17}$/D',$versionId))throw new HttpError(422,'invalid_id');
        $choice=$input['choix']??null;if(!in_array($choice,self::CHOICES,true))throw new HttpError(422,'invalid_choice');
        $key=$input['cle']??null;if(!is_string($key)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$key))throw new HttpError(422,'invalid_key');
        $reason=null;
        if($choice==='modification'){
            $reason=$input['motif']??null;
            if(!is_string($reason)||trim($reason)===''||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$reason))throw new HttpError(422,'validation_failed',['motif'=>'Indiquez le motif de votre demande de modification.']);
            $reason=trim($reason);if(preg_match_all('/./us',$reason)>2000)throw new HttpError(422,'validation_failed',['motif'=>'Le motif est limité à 2 000 caractères.']);
        }
        $db=Connections::quoteDecision();
        $q=$db->prepare('SELECT d.evenement_id FROM versions_devis v JOIN devis d ON d.id=v.devis_id WHERE v.id=?');$q->execute([$versionId]);$eventId=$q->fetchColumn();
        if($eventId===false)throw new HttpError(404,'quote_not_found');
        // Verrou partagé avec la préparation, le PDF et la transmission du même devis.
        $lock='innovevents.quote.'.$eventId;$q=$db->prepare('SELECT GET_LOCK(?,5)');$q->execute([$lock]);if((int)$q->fetchColumn()!==1)throw new HttpError(503,'quote_busy');
        try{
            $db->beginTransaction();
            $q=$db->prepare('SELECT version_id,choix FROM decisions_devis WHERE cle_idempotence=?');$q->execute([$key]);$old=$q->fetch();
            if($old){if((string)$old['version_id']!==$versionId||$old['choix']!==$choice)throw new HttpError(409,'idempotency_conflict');$db->commit();return ['statut'=>$choice,'repetition'=>true];}
            $q=$db->prepare('SELECT v.statut,v.numero,v.total_ttc,d.reference,d.version_courante_id,e.id AS evenement_id,e.nom,e.statut AS evenement_statut,c.utilisateur_id,c.entreprise
                FROM versions_devis v JOIN devis d ON d.id=v.devis_id JOIN evenements e ON e.id=d.evenement_id JOIN clients c ON c.id=e.client_id WHERE v.id=? FOR UPDATE OF v,e');
            $q->execute([$versionId]);$row=$q->fetch();
            // Devis d'un autre client : même réponse qu'un devis inexistant.
            if(!$row||(string)$row['utilisateur_id']!==(string)$user['id'])throw new HttpError(404,'quote_not_found');
            if((string)$row['version_courante_id']!==$versionId)throw new HttpError(409,'quote_version_replaced');
            if($row['statut']!=='etude_client')throw new HttpError(409,'quote_already_decided');
            $q=$db->prepare('INSERT INTO decisions_devis(version_id,auteur_id,choix,motif,cle_idempotence) VALUES(?,?,?,?,?)');$q->execute([$versionId,$user['id'],$choice,$reason,$key]);
            $q=$db->prepare("UPDATE versions_devis SET statut=? WHERE id=? AND statut='etude_client'");$q->execute([$choice,$versionId]);if($q->rowCount()!==1)throw new HttpError(409,'quote_already_decided');
            if($choice==='accepte'){
                // Synchronisation retenue en conception : l'acceptation du devis fait passer l'événement brouillon à « accepté ».
                if($row['evenement_statut']==='brouillon'){
                    $q=$db->prepare("UPDATE evenements SET statut='accepte',revision=revision+1 WHERE id=? AND statut='brouillon'");$q->execute([$row['evenement_id']]);
                    $q=$db->prepare("INSERT INTO journal_a_emettre(id_trace,horodatage,type_action,id_utilisateur,details) VALUES(UUID(),UTC_TIMESTAMP(6),'CHANGEMENT_STATUT_EVENEMENT',?,?)");
                    $q->execute([$user['id'],json_encode(['id_evenement'=>(string)$row['evenement_id'],'ancien_statut'=>'brouillon','nouveau_statut'=>'accepte'],JSON_THROW_ON_ERROR)]);
                }
                $recipient=getenv('AGENCY_EMAIL')?:'agence@example.invalid';if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))throw new \RuntimeException('Agency configuration');
                MailQueue::enqueue($db,'devis_accepte',$recipient,'Devis accepté : '.$row['reference'],
                    "Le client {$row['entreprise']} a accepté le devis {$row['reference']} (version {$row['numero']}) pour l’événement « {$row['nom']} ».\nMontant TTC : {$row['total_ttc']} €.\nL’événement est désormais au statut accepté : vous pouvez lancer son organisation.",2880);
            }
            $db->commit();return ['statut'=>$choice,'repetition'=>false];
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}finally{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
    }
    public function download(array $input): void {
        $user=(new Authentication())->requireUser(['client']);$id=$input['id']??null;
        if(!is_string($id)||!preg_match('/^[1-9][0-9]{0,17}$/D',$id))throw new HttpError(422,'invalid_id');
        $q=Connections::clientQuotes()->prepare("SELECT p.*,d.reference,v.numero FROM documents_devis p JOIN versions_devis v ON v.id=p.version_id JOIN devis d ON d.id=v.devis_id JOIN evenements e ON e.id=d.evenement_id JOIN clients c ON c.id=e.client_id
            WHERE p.id=? AND c.utilisateur_id=? AND v.statut<>'brouillon' AND EXISTS(SELECT 1 FROM transmissions_devis t WHERE t.document_id=p.id)");
        $q->execute([$id,$user['id']]);$doc=$q->fetch();
        // Même réponse qu'un document inexistant : ne pas révéler les devis des autres clients.
        if(!$doc)throw new HttpError(404,'document_not_found');
        $bytes=(new QuotePdfs())->bytes($doc);if($bytes===null)throw new HttpError(503,'pdf_unavailable');
        $name='devis-'.preg_replace('/[^A-Za-z0-9-]/','-',$doc['reference']).'-v'.$doc['numero'].'.pdf';
        header('Content-Type: application/pdf');header('Cache-Control: no-store');header('Content-Disposition: attachment; filename="'.$name.'"');header('Content-Length: '.strlen($bytes));echo $bytes;
    }
}
