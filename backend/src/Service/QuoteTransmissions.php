<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use InnovEvents\Infrastructure\Connections;
use InnovEvents\Http\HttpError;
/**
 * Transmission du devis au client : envoi par la plateforme (courriel avec PDF joint)
 * ou déclaration d'un envoi manuel. La première transmission fige la version,
 * la passe en « étude côté client » et crée le compte client s'il n'existe pas.
 */
final class QuoteTransmissions
{
    private function id(mixed $v): string {if(!(is_int($v)||is_string($v))||!preg_match('/^[1-9][0-9]{0,17}$/D',(string)$v))throw new HttpError(422,'invalid_id');return (string)$v;}
    private function locked(string $id,callable $fn): mixed {
        $db=Connections::quoteSend();$lock='innovevents.quote.'.$id;$q=$db->prepare('SELECT GET_LOCK(?,5)');$q->execute([$lock]);if((int)$q->fetchColumn()!==1)throw new HttpError(503,'quote_busy');
        try{$db->beginTransaction();$result=$fn($db);$db->commit();return $result;}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}finally{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
    }
    /** Version courante du devis de l'événement, avec le client et l'historique des transmissions. */
    private function state(\PDO $db,string $id,bool $lock=false): array {
        $q=$db->prepare('SELECT id,nom,client_id FROM evenements WHERE id=?');$q->execute([$id]);$event=$q->fetch();if(!$event)throw new HttpError(404,'event_not_found');
        $q=$db->prepare('SELECT id,utilisateur_id,entreprise,contact_prenom,contact_nom,email FROM clients WHERE id=?'.($lock?' FOR UPDATE':''));$q->execute([$event['client_id']]);$client=$q->fetch();
        $q=$db->prepare('SELECT v.id,v.numero,v.statut,v.revision_contenu,v.client_snapshot,d.reference FROM devis d JOIN versions_devis v ON v.id=d.version_courante_id AND v.devis_id=d.id WHERE d.evenement_id=?'.($lock?' FOR UPDATE OF v':''));$q->execute([$id]);$quote=$q->fetch()?:null;
        $history=[];
        if($quote){$q=$db->prepare('SELECT t.id,t.document_id,t.mode,t.destinataire,t.etat,t.cree_le,t.confirmee_le FROM transmissions_devis t JOIN documents_devis p ON p.id=t.document_id WHERE p.version_id=? ORDER BY t.id');$q->execute([$quote['id']]);$history=$q->fetchAll();}
        return ['event'=>$event,'client'=>$client,'quote'=>$quote,'history'=>$history];
    }
    /** Document que l'administratrice peut transmettre, ou null : PDF courant pour un brouillon, PDF déjà transmis ensuite. */
    private function sendable(\PDO $db,array $st,string $id): ?array {
        $quote=$st['quote'];if(!$quote)return null;$pdfs=new QuotePdfs();
        if($quote['statut']==='brouillon'){
            try{$s=$pdfs->source($db,$id);}catch(HttpError $e){if(in_array($e->status,[409,422],true))return null;throw $e;}
            $q=$db->prepare("SELECT * FROM documents_devis WHERE version_id=? AND revision_contenu=? AND source_empreinte=? AND etat='disponible' ORDER BY id DESC LIMIT 1");$q->execute([$s['quote']['id'],$s['quote']['revision_contenu'],$pdfs->hash($s)]);$doc=$q->fetch();
            return $doc&&$pdfs->bytes($doc)!==null?['document'=>$doc,'email'=>$s['client']['email'],'prenom'=>$s['client']['contact_prenom'],'event'=>$s['event']['nom']]:null;
        }
        if($quote['statut']!=='etude_client'||!$st['history'])return null;
        $q=$db->prepare('SELECT * FROM documents_devis WHERE id=?');$q->execute([$st['history'][0]['document_id']]);$doc=$q->fetch();
        $snapshot=json_decode((string)$quote['client_snapshot'],true);
        return $doc&&is_array($snapshot)&&$pdfs->bytes($doc)!==null?['document'=>$doc,'email'=>$snapshot['email'],'prenom'=>$snapshot['contact_prenom'],'event'=>$st['event']['nom']]:null;
    }
    private function publicHistory(array $history): array {
        return array_map(static fn($t)=>['id'=>(string)$t['id'],'mode'=>$t['mode'],'destinataire'=>$t['destinataire'],'etat'=>$t['etat'],'cree_le'=>$t['cree_le'],'confirmee_le'=>$t['confirmee_le']],$history);
    }
    public function status(array $input): array {
        (new Authentication())->requireUser(['administrateur']);$id=$this->id($input['event_id']??null);
        return $this->locked($id,function($db)use($id){
            $st=$this->state($db,$id);$quote=$st['quote'];$send=$this->sendable($db,$st,$id);
            return ['statut'=>$quote['statut']??null,'reference'=>$quote['reference']??null,'version'=>$quote?(int)$quote['numero']:null,
                'destinataire'=>$send['email']??$st['client']['email'],'compte_client'=>$st['client']['utilisateur_id']!==null,
                'document_id'=>$send?(string)$send['document']['id']:null,
                'en_attente'=>(bool)array_filter($st['history'],static fn($t)=>$t['etat']==='en_attente'),
                'transmissions'=>$this->publicHistory($st['history'])];
        });
    }
    public function send(array $input): array {
        $user=(new Authentication())->requireUser(['administrateur']);$id=$this->id($input['event_id']??null);$docId=$this->id($input['document_id']??null);
        $mode=$input['mode']??null;if(!in_array($mode,['automatique','manuel'],true))throw new HttpError(422,'invalid_mode');
        $key=$input['cle']??null;if(!is_string($key)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$key))throw new HttpError(422,'invalid_key');
        if($mode==='manuel'&&($input['confirmation']??null)!==true)throw new HttpError(422,'manual_confirmation_required');
        return $this->locked($id,function($db)use($id,$docId,$mode,$key,$user){
            // Répétition d'une demande déjà enregistrée (réponse perdue) : même résultat, sans nouvel envoi.
            $q=$db->prepare('SELECT t.*,v.statut,d.evenement_id FROM transmissions_devis t JOIN documents_devis p ON p.id=t.document_id JOIN versions_devis v ON v.id=p.version_id JOIN devis d ON d.id=v.devis_id WHERE t.cle_idempotence=?');$q->execute([$key]);$old=$q->fetch();
            if($old){if((string)$old['document_id']!==$docId||$old['mode']!==$mode||(string)$old['evenement_id']!==$id)throw new HttpError(409,'idempotency_conflict');
                return ['transmission'=>$this->publicHistory([$old])[0],'statut'=>$old['statut'],'compte_cree'=>false,'repetition'=>true];}
            $st=$this->state($db,$id,true);$quote=$st['quote'];
            if(!$quote||!in_array($quote['statut'],['brouillon','etude_client'],true))throw new HttpError(409,'quote_not_transmissible');
            if(array_filter($st['history'],static fn($t)=>$t['etat']==='en_attente'))throw new HttpError(409,'transmission_pending');
            $send=$this->sendable($db,$st,$id);
            if(!$send||(string)$send['document']['id']!==$docId)throw new HttpError(409,'pdf_obsolete');
            $created=false;
            if($quote['statut']==='brouillon'){
                $client=$st['client'];
                if($client['utilisateur_id']===null){
                    $email=strtolower(trim($client['email']));
                    $q=$db->prepare('SELECT id FROM utilisateurs WHERE email_normalise=?');$q->execute([$email]);
                    // Un compte existant n'est jamais rattaché automatiquement : l'inscription ne prouve pas la possession de l'adresse.
                    if($q->fetchColumn()!==false)throw new HttpError(409,'account_email_conflict');
                    $password='Ie!7'.bin2hex(random_bytes(10));
                    $username=mb_substr(trim($client['contact_prenom'].' '.$client['contact_nom']),0,100);
                    $q=$db->prepare("INSERT INTO utilisateurs(email,email_normalise,prenom,nom,nom_utilisateur,mot_de_passe_hash,role,changement_mdp_requis) VALUES(?,?,?,?,?,?,'client',1)");
                    $q->execute([trim($client['email']),$email,$client['contact_prenom'],$client['contact_nom'],$username,password_hash($password,PASSWORD_BCRYPT,['cost'=>12])]);
                    $q=$db->prepare('UPDATE clients SET utilisateur_id=? WHERE id=? AND utilisateur_id IS NULL');$q->execute([$db->lastInsertId(),$client['id']]);
                    if($q->rowCount()!==1)throw new HttpError(409,'client_changed');
                    MailQueue::enqueue($db,'compte_client',trim($client['email']),'Votre espace client Innov’Events',
                        "Bonjour {$client['contact_prenom']},\nInnov’Events vous a ouvert un espace client pour consulter vos devis.\nIdentifiant : ".trim($client['email'])."\nMot de passe par défaut : $password\nÀ votre première connexion, vous devrez choisir un nouveau mot de passe.\nConnexion : ".$this->origin()."/connexion",10080);
                    $created=true;
                }
                $q=$db->prepare("UPDATE versions_devis SET statut='etude_client',figee_le=UTC_TIMESTAMP(6),transmise_le=UTC_TIMESTAMP(6) WHERE id=? AND statut='brouillon'");$q->execute([$quote['id']]);
                if($q->rowCount()!==1)throw new HttpError(409,'quote_revision_conflict');
            }
            $doc=$send['document'];$mailId=null;
            if($mode==='automatique'){
                $name='devis-'.preg_replace('/[^A-Za-z0-9-]/','-',$quote['reference']).'-v'.$quote['numero'].'.pdf';
                $mailId=MailQueue::enqueue($db,'devis',$send['email'],'Votre devis Innov’Events '.$quote['reference'],
                    "Bonjour {$send['prenom']},\nVeuillez trouver ci-joint le devis {$quote['reference']} (version {$quote['numero']}) pour l’événement « {$send['event']} ».\nVous pouvez aussi le consulter et y répondre depuis votre espace client : ".$this->origin()."/espace\nL’équipe Innov’Events",2880,
                    ['reference'=>$doc['fichier_reference'],'sha256'=>$doc['empreinte_sha256'],'name'=>$name]);
            }
            $q=$db->prepare('INSERT INTO transmissions_devis(document_id,acteur_id,mode,destinataire,etat,cle_idempotence,confirmee_le,courriel_id) VALUES(?,?,?,?,?,?,'.($mode==='manuel'?'UTC_TIMESTAMP(6)':'NULL').',?)');
            $q->execute([$doc['id'],$user['id'],$mode,$send['email'],$mode==='manuel'?'confirme':'en_attente',$key,$mailId]);
            $q=$db->prepare('SELECT * FROM transmissions_devis WHERE id=?');$q->execute([$db->lastInsertId()]);
            return ['transmission'=>$this->publicHistory([$q->fetch()])[0],'statut'=>'etude_client','compte_cree'=>$created,'repetition'=>false];
        });
    }
    private function origin(): string {$o=getenv('APP_ORIGIN');return is_string($o)&&preg_match('#^https?://[A-Za-z0-9.:-]+$#D',$o)?$o:'http://127.0.0.1:5173';}
}
