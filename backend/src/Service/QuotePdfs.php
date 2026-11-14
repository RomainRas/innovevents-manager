<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use InnovEvents\Infrastructure\Connections;
use InnovEvents\Http\HttpError;
final class QuotePdfs
{
    private const DIRECTORY='/var/lib/innovevents/quote-pdfs';
    private function id(mixed $v): string {if(!(is_int($v)||is_string($v))||!preg_match('/^[1-9][0-9]{0,17}$/D',(string)$v))throw new HttpError(422,'invalid_id');return (string)$v;}
    public function source(\PDO $db,string $id): array {
        $q=$db->prepare('SELECT id,client_id,nom,debut,fin,fuseau,lieu,statut,image_reference,image_alternative,revision FROM evenements WHERE id=? FOR SHARE');$q->execute([$id]);$event=$q->fetch();if(!$event)throw new HttpError(404,'event_not_found');
        $q=$db->prepare('SELECT id,entreprise,contact_nom,contact_prenom,email,telephone FROM clients WHERE id=? FOR SHARE');$q->execute([$event['client_id']]);$client=$q->fetch();
        $q=$db->prepare('SELECT v.id,v.numero,v.revision_contenu,v.statut,v.figee_le,v.taux_tva,v.total_ht,v.total_tva,v.total_ttc,v.devise,v.regle_calcul,d.reference FROM devis d JOIN versions_devis v ON v.id=d.version_courante_id AND v.devis_id=d.id WHERE d.evenement_id=? FOR SHARE');$q->execute([$id]);$quote=$q->fetch();if(!$quote)throw new HttpError(422,'quote_required');
        if($quote['statut']!=='brouillon'||$quote['figee_le']!==null||$event['statut']!=='brouillon')throw new HttpError(409,'quote_not_editable');
        foreach(['nom','debut','fin','lieu','image_reference','image_alternative'] as $key)if(!is_string($event[$key])||trim($event[$key])==='')throw new HttpError(422,'event_incomplete');
        if($event['fuseau']!=='Europe/Paris'||$event['fin']<=$event['debut'])throw new HttpError(422,'event_incomplete');
        foreach(['entreprise','contact_nom','contact_prenom','email','telephone'] as $key)if(!is_string($client[$key])||trim($client[$key])==='')throw new HttpError(422,'client_incomplete');
        $q=$db->prepare('SELECT libelle,montant_ht FROM prestations_devis WHERE version_id=? ORDER BY ordre');$q->execute([$quote['id']]);$lines=$q->fetchAll();
        $computed=QuoteAmounts::calculate(['prestations'=>$lines,'taux_tva'=>$quote['taux_tva']]);
        foreach(['total_ht','total_tva','total_ttc','devise','regle_calcul'] as $key)if($quote[$key]!==$computed[$key])throw new HttpError(409,'quote_totals_changed');
        return ['event'=>$event,'client'=>$client,'quote'=>$quote,'prestations'=>$lines];
    }
    public function hash(array $source): string {return hash('sha256',json_encode($source,JSON_THROW_ON_ERROR));}
    public function bytes(array $document): ?string {
        $ref=$document['fichier_reference'];if(!is_string($ref)||!preg_match('/^[a-f0-9]{64}\.pdf$/D',$ref))return null;
        $path=self::DIRECTORY.'/'.$ref;if(!is_file($path))return null;$bytes=file_get_contents($path);
        return is_string($bytes)&&hash_equals($document['empreinte_sha256'],hash('sha256',$bytes))?$bytes:null;
    }
    private function locked(string $id,callable $fn): mixed {
        $db=Connections::quotePdf();$lock='innovevents.quote.'.$id;$q=$db->prepare('SELECT GET_LOCK(?,5)');$q->execute([$lock]);if((int)$q->fetchColumn()!==1)throw new HttpError(503,'quote_busy');
        try{$db->beginTransaction();$result=$fn($db);$db->commit();return $result;}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}finally{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
    }
    public function status(array $input): array {
        (new Authentication())->requireUser(['administrateur']);$id=$this->id($input['event_id']??null);
        return $this->locked($id,function($db)use($id){
            try{$s=$this->source($db,$id);}catch(HttpError $e){if(in_array($e->status,[422,409],true))return ['ready'=>false];throw $e;}
            $q=$db->prepare("SELECT * FROM documents_devis WHERE version_id=? AND revision_contenu=? AND source_empreinte=? AND etat='disponible' ORDER BY id DESC LIMIT 1");$q->execute([$s['quote']['id'],$s['quote']['revision_contenu'],$this->hash($s)]);$doc=$q->fetch();return $doc&&$this->bytes($doc)!==null?['ready'=>true,'document_id'=>(string)$doc['id']]:['ready'=>false];
        });
    }
    public function generate(array $input): array {
        $user=(new Authentication())->requireUser(['administrateur']);$id=$this->id($input['event_id']??null);$revision=$input['revision']??null;if(!is_int($revision)||$revision<1)throw new HttpError(422,'invalid_revision');
        return $this->locked($id,function($db)use($id,$revision,$user){
            $s=$this->source($db,$id);$v=$s['quote'];if((int)$v['revision_contenu']!==$revision)throw new HttpError(409,'quote_revision_conflict');$hash=$this->hash($s);
            $q=$db->prepare("SELECT * FROM documents_devis WHERE version_id=? AND revision_contenu=? AND source_empreinte=? AND etat='disponible' ORDER BY id DESC LIMIT 1");$q->execute([$v['id'],$revision,$hash]);$old=$q->fetch();if($old&&$this->bytes($old)!==null)return ['document_id'=>(string)$old['id']];
            $image=$s['event']['image_reference'];if(!preg_match('/^[a-f0-9]{64}\.(png|jpg)$/D',$image))throw new HttpError(422,'event_image_unavailable');
            $path=EventImages::path($image);$imageBytes=$path!==null?file_get_contents($path):false;if(!is_string($imageBytes)||!hash_equals(substr($image,0,64),hash('sha256',$imageBytes)))throw new HttpError(422,'event_image_unavailable');
            $bytes=QuotePdfRenderer::render($s,$imageBytes);if(!str_starts_with($bytes,'%PDF-'))throw new \RuntimeException('PDF rendering');
            if(!is_dir(self::DIRECTORY)&&!mkdir(self::DIRECTORY,0700,true)&&!is_dir(self::DIRECTORY))throw new \RuntimeException('PDF storage');
            $digest=hash('sha256',$bytes);$file=$digest.'.pdf';$temp=tempnam(self::DIRECTORY,'pending-');if($temp===false)throw new \RuntimeException('PDF storage');
            try{if(file_put_contents($temp,$bytes)!==strlen($bytes)||!rename($temp,self::DIRECTORY.'/'.$file))throw new \RuntimeException('PDF storage');chmod(self::DIRECTORY.'/'.$file,0600);}finally{if(is_file($temp))unlink($temp);}
            $q=$db->prepare('UPDATE versions_devis SET client_snapshot=?,evenement_snapshot=? WHERE id=?');$q->execute([json_encode($s['client'],JSON_THROW_ON_ERROR),json_encode($s['event'],JSON_THROW_ON_ERROR),$v['id']]);
            $q=$db->prepare("UPDATE documents_devis SET etat='obsolete' WHERE version_id=? AND etat='disponible'");$q->execute([$v['id']]);
            $q=$db->prepare("INSERT INTO documents_devis(version_id,revision_contenu,etat,fichier_reference,empreinte_sha256,source_empreinte,genere_le) VALUES(?,?,'disponible',?,?,?,UTC_TIMESTAMP(6))");$q->execute([$v['id'],$revision,$file,$digest,$hash]);$docId=$db->lastInsertId();
            $q=$db->prepare("INSERT INTO journal_a_emettre(id_trace,horodatage,type_action,id_utilisateur,details) VALUES(UUID(),UTC_TIMESTAMP(6),'GENERATION_PDF_DEVIS',?,?)");$q->execute([$user['id'],json_encode(['id_evenement'=>$id],JSON_THROW_ON_ERROR)]);
            return ['document_id'=>(string)$docId];
        });
    }
    public function download(array $input): void {
        (new Authentication())->requireUser(['administrateur']);$docId=$this->id($input['id']??null);$db=Connections::quotePdf();
        $q=$db->prepare('SELECT d.evenement_id FROM documents_devis p JOIN versions_devis v ON v.id=p.version_id JOIN devis d ON d.id=v.devis_id WHERE p.id=?');$q->execute([$docId]);$id=$q->fetchColumn();if($id===false)throw new HttpError(404,'document_not_found');
        $result=$this->locked((string)$id,function($db)use($id,$docId){$s=$this->source($db,(string)$id);$q=$db->prepare('SELECT * FROM documents_devis WHERE id=?');$q->execute([$docId]);$doc=$q->fetch();
            if(!$doc||$doc['etat']!=='disponible'||(string)$doc['version_id']!==(string)$s['quote']['id']||!is_string($doc['source_empreinte'])||!hash_equals($doc['source_empreinte'],$this->hash($s)))throw new HttpError(409,'pdf_obsolete');
            $bytes=$this->bytes($doc);if($bytes===null)throw new HttpError(503,'pdf_unavailable');return ['bytes'=>$bytes,'name'=>'devis-'.$id.'-r'.$doc['revision_contenu'].'.pdf'];});
        header('Content-Type: application/pdf');header('Cache-Control: no-store');header('Content-Disposition: attachment; filename="'.$result['name'].'"');header('Content-Length: '.strlen($result['bytes']));echo $result['bytes'];
    }
}
