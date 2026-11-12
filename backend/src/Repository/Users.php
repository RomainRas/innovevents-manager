<?php
declare(strict_types=1);
namespace InnovEvents\Repository;
use InnovEvents\Infrastructure\Connections;
final class Users
{
    public function find(string $column, string $value): ?array
    {
        if (!in_array($column, ['id', 'email_normalise'], true)) throw new \InvalidArgumentException();
        $q=Connections::mysql()->prepare("SELECT id,email,prenom,nom,role,statut,acces_mobile,changement_mdp_requis,mot_de_passe_hash FROM utilisateurs WHERE $column = ?");
        $q->execute([$value]);
        return $q->fetch() ?: null;
    }
    public static function publicData(array $user): array
    {
        return ['id'=>(string)$user['id'],'email'=>$user['email'],'prenom'=>$user['prenom'],
            'nom'=>$user['nom'],'role'=>$user['role'],'password_change_required'=>(bool)$user['changement_mdp_requis']];
    }
}
