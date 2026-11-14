<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use InnovEvents\Infrastructure\Connections;
use InnovEvents\Http\HttpError;
/** Devis transmis, consultés par le client connecté uniquement (jamais les brouillons). */
final class ClientQuotes
{
    public function listing(): array {
        $user=(new Authentication())->requireUser(['client']);$db=Connections::clientQuotes();
        $q=$db->prepare("SELECT d.reference,v.id,v.numero,v.statut,v.transmise_le,v.taux_tva,v.total_ht,v.total_tva,v.total_ttc,v.evenement_snapshot,
            (SELECT t.document_id FROM transmissions_devis t JOIN documents_devis p ON p.id=t.document_id WHERE p.version_id=v.id ORDER BY t.id LIMIT 1) AS document_id
            FROM clients c JOIN evenements e ON e.client_id=c.id JOIN devis d ON d.evenement_id=e.id JOIN versions_devis v ON v.devis_id=d.id
            WHERE c.utilisateur_id=? AND v.statut<>'brouillon' AND v.transmise_le IS NOT NULL ORDER BY v.transmise_le DESC,v.id DESC LIMIT 50");
        $q->execute([$user['id']]);$rows=$q->fetchAll();
        $lines=$db->prepare('SELECT libelle,montant_ht FROM prestations_devis WHERE version_id=? ORDER BY ordre');
        return ['devis'=>array_map(static function(array $r)use($lines){
            $lines->execute([$r['id']]);$event=json_decode((string)$r['evenement_snapshot'],true)?:[];
            return ['reference'=>$r['reference'],'version'=>(int)$r['numero'],'statut'=>$r['statut'],'transmis_le'=>$r['transmise_le'],
                'evenement'=>['nom'=>$event['nom']??'','lieu'=>$event['lieu']??'','debut'=>EventSchedule::local($event['debut']??null),'fin'=>EventSchedule::local($event['fin']??null)],
                'prestations'=>$lines->fetchAll(),'taux_tva'=>$r['taux_tva'],'total_ht'=>$r['total_ht'],'total_tva'=>$r['total_tva'],'total_ttc'=>$r['total_ttc'],
                'document_id'=>$r['document_id']===null?null:(string)$r['document_id']];
        },$rows)];
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
