<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Client
{
    public function __construct(private PDO $db) {}

    public function createProfile(int $userId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO clients
                (user_id, adresse, commune, ville, quartier, repere,
                 latitude, longitude)
             VALUES
                (:user_id, :adresse, :commune, :ville, :quartier, :repere,
                 :latitude, :longitude)
             RETURNING id'
        );

        $stmt->execute([
            'user_id' => $userId,
            'adresse' => $data['adresse'],
            'commune' => $data['commune'] ?: null,
            'ville' => $data['ville'] ?: 'Abidjan',
            'quartier' => $data['quartier'] ?: null,
            'repere' => $data['repere'] ?: null,
            'latitude' => null,
            'longitude' => null,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, u.nom, u.prenom, u.email, u.telephone
             FROM clients c
             JOIN users u ON u.id = c.user_id
             WHERE c.user_id = :user_id'
        );

        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetch() ?: null;
    }
}
