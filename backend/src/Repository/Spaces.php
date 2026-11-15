<?php
declare(strict_types=1);

namespace InnovEvents\Repository;

use InnovEvents\Infrastructure\Connections;

/** Lectures simples affichées dans « Mon espace » selon le rôle. */
final class Spaces
{
    /** Les trois prochains événements non annulés d'un client (widget de l'espace client). */
    public function events(string $userId): array
    {
        $query = Connections::mysql()->prepare(
            'SELECT e.id, e.nom, e.debut, e.fin, e.statut
             FROM evenements e
             JOIN clients c ON c.id = e.client_id
             WHERE c.utilisateur_id = ? AND e.debut >= UTC_TIMESTAMP() AND e.statut <> ?
             ORDER BY e.debut, e.id
             LIMIT 3'
        );
        $query->execute([$userId, 'annule']);

        return $query->fetchAll();
    }

    /** Les 20 dernières notes globales de l'équipe (employés et administratrice). */
    public function notes(): array
    {
        return Connections::mysql()
            ->query('SELECT id, contenu FROM notes_globales ORDER BY cree_le DESC, id DESC LIMIT 20')
            ->fetchAll();
    }

    /** Les 20 dernières entrées du journal MongoDB, pour la page « Journaux » de l'administratrice. */
    public function logs(): array
    {
        $logs = Connections::mongo()
            ->selectCollection('innovevents_demo', 'logs')
            ->find([], ['sort' => ['horodatage' => -1, '_id' => -1], 'limit' => 20]);

        return array_map(static fn($log) => [
            'type'    => $log['type_action'],
            'at'      => $log['horodatage']->toDateTime()->format(DATE_ATOM),
            'actor'   => $log['id_utilisateur'],
            'details' => $log['details'],
        ], iterator_to_array($logs));
    }
}
