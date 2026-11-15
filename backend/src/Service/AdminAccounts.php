<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;
/**
 * Espace administrateur (Chloé) : tableau de bord et gestion des comptes employés et clients.
 * Aucun compte administrateur ne peut être créé, suspendu ou supprimé depuis l'application.
 */
final class AdminAccounts
{
    private const ROLES=['client','employe'];
    private static function id(mixed $v): string {if(!(is_int($v)||is_string($v))||!preg_match('/^[1-9][0-9]{0,17}$/D',(string)$v))throw new HttpError(422,'invalid_id');return (string)$v;}
    private static function origin(): string {$o=getenv('APP_ORIGIN');return is_string($o)&&preg_match('#^https?://[A-Za-z0-9.:-]+$#D',$o)?$o:'http://127.0.0.1:5173';}
    private static function text(mixed $value,int $max): ?string {
        if(!is_string($value)||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$value)||!mb_check_encoding($value,'UTF-8'))return null;
        $value=trim($value);return $value===''||preg_match_all('/./us',$value)>$max?null:$value;
    }
    public function dashboard(): array {
        (new Authentication())->requireUser(['administrateur']);$db=Connections::admin();
        $events=$db->query("SELECT e.id,e.nom,e.debut,e.statut,c.entreprise FROM evenements e JOIN clients c ON c.id=e.client_id
            WHERE e.debut>=UTC_TIMESTAMP() AND e.statut<>'annule' ORDER BY e.debut,e.id LIMIT 3")->fetchAll();
        $notes=$db->query("SELECT n.id,n.contenu,n.cree_le,e.id AS evenement_id,e.nom AS evenement,u.prenom FROM notes_evenement n
            JOIN evenements e ON e.id=n.evenement_id JOIN utilisateurs u ON u.id=n.auteur_id ORDER BY n.cree_le DESC,n.id DESC LIMIT 5")->fetchAll();
        $count=fn(string $sql)=>(int)$db->query($sql)->fetchColumn();
        return [
            'prochains_evenements'=>array_map(static fn($r)=>['id'=>(string)$r['id'],'nom'=>$r['nom']??'Événement sans nom','debut'=>EventSchedule::local($r['debut']),'statut'=>$r['statut'],'client'=>$r['entreprise']],$events),
            'notes_recentes'=>array_map(static fn($r)=>['id'=>(string)$r['id'],'contenu'=>$r['contenu'],'cree_le'=>$r['cree_le'],'evenement_id'=>(string)$r['evenement_id'],'evenement'=>$r['evenement']??'Événement sans nom','auteur'=>$r['prenom']],$notes),
            'indicateurs'=>[
                // Client actif : au moins un événement au statut « en cours ».
                'clients_actifs'=>$count("SELECT COUNT(DISTINCT client_id) FROM evenements WHERE statut='en_cours'"),
                'evenements_brouillon'=>$count("SELECT COUNT(*) FROM evenements WHERE statut='brouillon'"),
                'avis_a_moderer'=>$count("SELECT COUNT(*) FROM avis WHERE statut='en_attente'"),
                'demandes_suppression'=>$count("SELECT COUNT(*) FROM demandes_suppression WHERE statut='en_attente'"),
            ]];
    }
    public function listing(array $input): array {
        (new Authentication())->requireUser(['administrateur']);
        $role=$input['role']??'';if(!in_array($role,['',...self::ROLES],true))throw new HttpError(422,'invalid_filter');
        $search=$input['q']??'';if(!is_string($search)||preg_match_all('/./us',$search)>100)throw new HttpError(422,'invalid_filter');
        $where=["u.role IN ('client','employe')",'u.supprime_le IS NULL'];$params=[];
        if($role!==''){$where[]='u.role=?';$params[]=$role;}
        if(trim($search)!==''){$where[]='(u.email LIKE ? OR u.prenom LIKE ? OR u.nom LIKE ? OR c.entreprise LIKE ?)';$like='%'.addcslashes(trim($search),'%_\\').'%';array_push($params,$like,$like,$like,$like);}
        $db=Connections::admin();
        $q=$db->prepare('SELECT u.id,u.email,u.prenom,u.nom,u.role,u.statut,u.acces_mobile,u.changement_mdp_requis,u.cree_le,c.id AS client_id,c.entreprise,
            (SELECT d.cree_le FROM demandes_suppression d WHERE d.utilisateur_id=u.id AND d.statut=\'en_attente\') AS suppression_demandee_le
            FROM utilisateurs u LEFT JOIN clients c ON c.utilisateur_id=u.id WHERE '.implode(' AND ',$where).' ORDER BY (suppression_demandee_le IS NULL),u.role,u.nom,u.prenom,u.id LIMIT 200');
        $q->execute($params);
        $free=$db->query('SELECT id,entreprise,contact_prenom,contact_nom,email FROM clients WHERE utilisateur_id IS NULL ORDER BY entreprise,id LIMIT 200')->fetchAll();
        return ['comptes'=>array_map(static fn($r)=>['id'=>(string)$r['id'],'email'=>$r['email'],'prenom'=>$r['prenom'],'nom'=>$r['nom'],'role'=>$r['role'],'statut'=>$r['statut'],
                'acces_mobile'=>(bool)$r['acces_mobile'],'premiere_connexion'=>(bool)$r['changement_mdp_requis'],'cree_le'=>$r['cree_le'],
                'client_id'=>$r['client_id']===null?null:(string)$r['client_id'],'entreprise'=>$r['entreprise'],'suppression_demandee_le'=>$r['suppression_demandee_le']],$q->fetchAll()),
            'fiches_sans_compte'=>array_map(static fn($r)=>['id'=>(string)$r['id'],'entreprise'=>$r['entreprise'],'contact'=>$r['contact_prenom'].' '.$r['contact_nom'],'email'=>$r['email']],$free)];
    }
    /** Employé : identité saisie. Client : rattaché à une fiche client existante sans compte, identité reprise de la fiche. */
    public function create(array $input): array {
        $admin=(new Authentication())->requireUser(['administrateur']);
        $role=$input['role']??null;if(!in_array($role,self::ROLES,true))throw new HttpError(422,'validation_failed',['role'=>'Choisissez le type de compte.']);
        $db=Connections::admin();$clientId=null;
        if($role==='client'){
            $clientId=self::id($input['client_id']??null);
            $q=$db->prepare('SELECT contact_prenom,contact_nom,email,utilisateur_id FROM clients WHERE id=?');$q->execute([$clientId]);$fiche=$q->fetch();
            if(!$fiche)throw new HttpError(404,'client_not_found');
            if($fiche['utilisateur_id']!==null)throw new HttpError(409,'client_has_account');
            $data=['prenom'=>$fiche['contact_prenom'],'nom'=>$fiche['contact_nom'],'email'=>trim($fiche['email'])];$mobile=0;
        }else{
            $errors=[];$data=[];
            foreach(['prenom'=>100,'nom'=>100,'email'=>254] as $key=>$max){$v=self::text($input[$key]??null,$max);if($v===null)$errors[$key]="Renseignez ce champ (maximum $max caractères).";else $data[$key]=$v;}
            if(isset($data['email'])&&!filter_var($data['email'],FILTER_VALIDATE_EMAIL))$errors['email']='Saisissez une adresse électronique valide.';
            if($errors)throw new HttpError(422,'validation_failed',$errors);
            $mobile=($input['acces_mobile']??false)===true?1:0;
        }
        if(!filter_var($data['email'],FILTER_VALIDATE_EMAIL))throw new HttpError(422,'validation_failed',['client_id'=>'La fiche client n’a pas d’adresse électronique valide.']);
        $email=strtolower($data['email']);$password='Ie!7'.bin2hex(random_bytes(10));
        try{
            $db->beginTransaction();
            $q=$db->prepare('INSERT INTO utilisateurs(email,email_normalise,prenom,nom,nom_utilisateur,mot_de_passe_hash,role,changement_mdp_requis,acces_mobile) VALUES(?,?,?,?,?,?,?,1,?)');
            $q->execute([$data['email'],$email,$data['prenom'],$data['nom'],mb_substr($data['prenom'].' '.$data['nom'],0,100),password_hash($password,PASSWORD_BCRYPT,['cost'=>12]),$role,$mobile]);
            $id=(string)$db->lastInsertId();
            if($clientId!==null){
                $q=$db->prepare('UPDATE clients SET utilisateur_id=? WHERE id=? AND utilisateur_id IS NULL');$q->execute([$id,$clientId]);
                if($q->rowCount()!==1)throw new HttpError(409,'client_has_account');
            }
            MailQueue::enqueue($db,'compte_cree',$data['email'],'Votre compte Innov’Events',
                "Bonjour {$data['prenom']},\nInnov’Events vous a ouvert un compte ".($role==='client'?'client':'employé').".\nIdentifiant : {$data['email']}\nMot de passe par défaut : $password\nÀ votre première connexion, vous devrez choisir un nouveau mot de passe.\nConnexion : ".self::origin()."/connexion",10080);
            $db->commit();
        }catch(\PDOException $e){
            if($db->inTransaction())$db->rollBack();
            if($e->getCode()==='23000')throw new HttpError(409,'email_taken');
            throw $e;
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        return ['id'=>$id,'role'=>$role,'email'=>$data['email']];
    }
    /** Cible modifiable : compte employé ou client, non supprimé, verrouillé pour la transaction. */
    private function target(\PDO $db,string $id): array {
        $q=$db->prepare('SELECT u.id,u.email,u.prenom,u.nom,u.role,u.statut,u.supprime_le,c.id AS client_id FROM utilisateurs u LEFT JOIN clients c ON c.utilisateur_id=u.id WHERE u.id=? FOR UPDATE OF u');
        $q->execute([$id]);$row=$q->fetch();
        // Un administrateur est traité comme inexistant : aucune action possible sur ce type de compte.
        if(!$row||!in_array($row['role'],self::ROLES,true)||$row['supprime_le']!==null)throw new HttpError(404,'account_not_found');
        return $row;
    }
    public function setStatus(array $input): array {
        (new Authentication())->requireUser(['administrateur']);
        $id=self::id($input['id']??null);$status=$input['statut']??null;if(!in_array($status,['actif','suspendu'],true))throw new HttpError(422,'invalid_status');
        $db=Connections::admin();
        try{
            $db->beginTransaction();$this->target($db,$id);
            // La suspension coupe aussi l'accès mobile ; la session ouverte est refusée à la requête suivante.
            $db->prepare('UPDATE utilisateurs SET statut=?,acces_mobile=IF(?=\'suspendu\',0,acces_mobile) WHERE id=?')->execute([$status,$status,$id]);
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        return ['id'=>$id,'statut'=>$status];
    }
    /**
     * Suppression d'un compte : identité, adresse et mot de passe anonymisés, avis du client effacés, fiche client
     * détachée et coordonnées du contact effacées, demande de suppression marquée traitée, courriel de confirmation.
     */
    public function delete(array $input): array {
        $admin=(new Authentication())->requireUser(['administrateur']);
        $id=self::id($input['id']??null);if(($input['confirmation']??null)!==true)throw new HttpError(422,'confirmation_required');
        $db=Connections::admin();
        try{
            $db->beginTransaction();$row=$this->target($db,$id);
            $alias='supprime-'.$id.'@supprime.invalid';
            $db->prepare("UPDATE utilisateurs SET email=?,email_normalise=?,prenom='Compte',nom='supprimé',nom_utilisateur=?,mot_de_passe_hash=?,
                statut='suspendu',acces_mobile=0,changement_mdp_requis=0,mdp_temporaire_hash=NULL,mdp_temporaire_expire_le=NULL,supprime_le=UTC_TIMESTAMP(6) WHERE id=?")
                ->execute([$alias,$alias,'supprime-'.$id,password_hash(bin2hex(random_bytes(32)),PASSWORD_BCRYPT,['cost'=>12]),$id]);
            $db->prepare('DELETE FROM avis WHERE auteur_id=?')->execute([$id]);
            if($row['client_id']!==null){
                $db->prepare("UPDATE clients SET utilisateur_id=NULL,contact_prenom='Contact',contact_nom='supprimé',email=?,telephone='Non communiqué',
                    adresse_ligne1=NULL,adresse_ligne2=NULL,code_postal=NULL,ville=NULL WHERE id=?")->execute([$alias,$row['client_id']]);
                // Journal exigé : modification d'un client (ses coordonnées sont effacées, l'historique commercial reste).
                $db->prepare("INSERT INTO journal_a_emettre(id_trace,horodatage,type_action,id_utilisateur,details) VALUES(UUID(),UTC_TIMESTAMP(6),'MODIFICATION_CLIENT',?,?)")
                    ->execute([$admin['id'],json_encode(['id_client'=>(string)$row['client_id']],JSON_THROW_ON_ERROR)]);
            }
            $db->prepare("UPDATE demandes_suppression SET statut='traitee',traitee_le=UTC_TIMESTAMP(6) WHERE utilisateur_id=? AND statut='en_attente'")->execute([$id]);
            // Dernier message à l'ancienne adresse, conservée uniquement dans la file chiffrée le temps de l'envoi.
            MailQueue::enqueue($db,'compte_supprime',$row['email'],'Suppression de votre compte Innov’Events',
                "Bonjour {$row['prenom']},\nVotre compte Innov’Events a été supprimé et vos informations personnelles ont été effacées.\nLes documents que la loi impose de conserver (devis et factures) sont gardés sans vos coordonnées pendant la durée légale.",10080);
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        return ['id'=>$id,'supprime'=>true];
    }
}
