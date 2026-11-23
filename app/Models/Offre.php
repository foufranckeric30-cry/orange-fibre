<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Offre
{
    public function __construct(private PDO $db) {}

    public function getActiveOffers(): array
    {
        $stmt = $this->db->query(
            'SELECT id, nom, description, debit_mbps,
                    prix_mensuel, frais_installation, avantages
             FROM offres
             WHERE active = TRUE
             ORDER BY prix_mensuel ASC'
        );

        return $stmt->fetchAll();
    }

    public function findActiveById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM offres
             WHERE id = :id AND active = TRUE'
        );

        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }
}
