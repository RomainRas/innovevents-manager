<?php
declare(strict_types=1);
namespace InnovEvents\Service;
use InnovEvents\Http\HttpError;
use InnovEvents\Infrastructure\Connections;
/**
 * API de l'application mobile (PWA) : consultation et contact en mobilité pour Chloé et José.
 * Réservée aux employés et à l'administratrice dont le compte a l'accès mobile (utilisateurs.acces_mobile).
 * Réutilise le compte MySQL limité de l'équipe : lecture des clients et événements, ajout de notes.
 */
final class MobileApp
{
    private static function user(): array {
        $u=(new Authentication())->requireUser(['employe','administrateur']);
        if((int)($u['acces_mobile']??0)!==1)throw new HttpError(403,'mobile_access_required');
        return $u;
    }
    private static function id(mixed $v): string {if(!(is_int($v)||is_string($v))||!preg_match('/^[1-9][0-9]{0,17}$/D',(string)$v))throw new HttpError(422,'invalid_id');return (string)$v;}
    /** Tableau de bord : tous les événements à venir ou en cours (fin non passée), hors annulés, du plus proche au plus lointain. */
    public function events(): array {
        self::user();
        $rows=Connections::team()->query("SELECT e.id,e.nom,e.debut,e.fin,e.lieu,e.statut,c.entreprise FROM evenements e JOIN clients c ON c.id=e.client_id
            WHERE e.statut<>'annule' AND e.debut IS NOT NULL AND (e.fin>=UTC_TIMESTAMP() OR (e.fin IS NULL AND e.debut>=UTC_TIMESTAMP())) ORDER BY e.debut,e.id LIMIT 200")->fetchAll();
        return ['evenements'=>array_map(static fn($r)=>['id'=>(string)$r['id'],'nom'=>$r['nom']??'Événement sans nom','debut'=>EventSchedule::local($r['debut']),
            'fin'=>EventSchedule::local($r['fin']),'lieu'=>$r['lieu'],'statut'=>$r['statut'],'client'=>$r['entreprise']],$rows),'genere_le'=>gmdate('c')];
    }
    public function event(array $input): array {
        self::user();$id=self::id($input['id']??null);$db=Connections::team();
        $s=$db->prepare('SELECT e.id,e.nom,e.debut,e.fin,e.lieu,e.statut,e.client_id,c.entreprise,c.contact_prenom,c.contact_nom FROM evenements e JOIN clients c ON c.id=e.client_id WHERE e.id=?');
        $s->execute([$id]);$e=$s->fetch();if(!$e)throw new HttpError(404,'event_not_found');
        $s=$db->prepare('SELECT n.id,n.contenu,n.cree_le,u.prenom FROM notes_evenement n JOIN utilisateurs u ON u.id=n.auteur_id WHERE n.evenement_id=? ORDER BY n.cree_le DESC,n.id DESC LIMIT 5');
        $s->execute([$id]);
        return ['evenement'=>['id'=>(string)$e['id'],'nom'=>$e['nom']??'Événement sans nom','debut'=>EventSchedule::local($e['debut']),'fin'=>EventSchedule::local($e['fin']),
            'lieu'=>$e['lieu'],'statut'=>$e['statut'],'client'=>['id'=>(string)$e['client_id'],'entreprise'=>$e['entreprise'],'contact'=>$e['contact_prenom'].' '.$e['contact_nom']]],
            'notes'=>array_map(static fn($n)=>['id'=>(string)$n['id'],'contenu'=>$n['contenu'],'cree_le'=>$n['cree_le'],'auteur'=>$n['prenom']],$s->fetchAll())];
    }
    /** Fiche contact : téléphone, courriel et adresse postale, rendus cliquables par l'application. */
    public function client(array $input): array {
        self::user();$id=self::id($input['id']??null);
        $s=Connections::team()->prepare('SELECT id,entreprise,contact_prenom,contact_nom,email,telephone,adresse_ligne1,adresse_ligne2,code_postal,ville,pays_code FROM clients WHERE id=?');
        $s->execute([$id]);$c=$s->fetch();if(!$c)throw new HttpError(404,'client_not_found');
        $city=trim(($c['code_postal']??'').' '.($c['ville']??''));
        $address=implode(', ',array_filter([$c['adresse_ligne1'],$c['adresse_ligne2'],$city],static fn($v)=>is_string($v)&&trim($v)!==''));
        // Un numéro composable : chiffres et « + » initial uniquement.
        $phone=preg_replace('/(?!^\+)[^0-9]/','',trim((string)$c['telephone']));
        return ['client'=>['id'=>(string)$c['id'],'entreprise'=>$c['entreprise'],'contact'=>$c['contact_prenom'].' '.$c['contact_nom'],
            'telephone'=>$c['telephone'],'telephone_composable'=>strlen(preg_replace('/\D/','',$phone))>=6?$phone:null,
            'email'=>filter_var($c['email'],FILTER_VALIDATE_EMAIL)?$c['email']:null,'adresse'=>$address===''?null:$address]];
    }
    public function addNote(array $input): array {
        $user=self::user();$event=self::id($input['evenement_id']??null);$text=$input['contenu']??null;
        if(!is_string($text)||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$text)||!mb_check_encoding($text,'UTF-8')||trim($text)===''||preg_match_all('/./us',trim($text))>5000)
            throw new HttpError(422,'validation_failed',['contenu'=>'Saisissez la note (5 000 caractères au maximum).']);
        $db=Connections::team();
        $s=$db->prepare('SELECT id FROM evenements WHERE id=?');$s->execute([$event]);if($s->fetchColumn()===false)throw new HttpError(404,'event_not_found');
        $db->prepare('INSERT INTO notes_evenement(evenement_id,auteur_id,contenu) VALUES(?,?,?)')->execute([$event,$user['id'],trim($text)]);
        return $this->event(['id'=>$event]);
    }
}
