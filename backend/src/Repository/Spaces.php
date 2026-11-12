<?php
declare(strict_types=1);
namespace InnovEvents\Repository;
use InnovEvents\Infrastructure\Connections;
final class Spaces
{
    public function events(string $userId): array
    {
        $q=Connections::mysql()->prepare('SELECT e.id,e.nom,e.debut,e.fin,e.statut FROM evenements e JOIN clients c ON c.id=e.client_id WHERE c.utilisateur_id=? AND e.debut>=UTC_TIMESTAMP() AND e.statut<>? ORDER BY e.debut,e.id LIMIT 3');
        $q->execute([$userId,'annule']);return $q->fetchAll();
    }
    public function notes(): array
    {
        return Connections::mysql()->query('SELECT id,contenu FROM notes_globales ORDER BY cree_le DESC,id DESC LIMIT 20')->fetchAll();
    }
    public function logs(): array
    {
        $logs=Connections::mongo()->selectCollection('innovevents_demo','logs')->find([],['sort'=>['horodatage'=>-1,'_id'=>-1],'limit'=>20]);
        return array_map(static fn($l)=>['type'=>$l['type_action'],'at'=>$l['horodatage']->toDateTime()->format(DATE_ATOM),'actor'=>$l['id_utilisateur'],'details'=>$l['details']],iterator_to_array($logs));
    }
}
