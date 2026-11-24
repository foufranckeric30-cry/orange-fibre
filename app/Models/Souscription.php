<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Souscription
{
    public function __construct(private PDO $db) {}

    public function create(int $clientId, array $data): int
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO souscriptions
                (client_id, offre_id, adresse_installation, commune,
                 statut, observations, numero_demande, repere,
                 latitude, longitude)
                VALUES
                (:client, :offre, :adresse, :commune,
                 'en_attente', :observations, :numero, :repere,
                 :latitude, :longitude)
                RETURNING id"
            );

            $numero = 'OF-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

            $stmt->execute([
                'client' => $clientId,
                'offre' => $data['offre_id'],
                'adresse' => $data['adresse_installation'],
                'commune' => $data['commune'] ?: null,
                'observations' => $data['observations'] ?: null,
                'numero' => $numero,
                'repere' => $data['repere'] ?: null,
                'latitude' => $data['latitude'] ?: null,
                'longitude' => $data['longitude'] ?: null,
            ]);

            $id = (int) $stmt->fetchColumn();

            $history = $this->db->prepare(
                "INSERT INTO historique_statuts
                (souscription_id, ancien_statut, nouveau_statut,
                 commentaire, visible_client)
                VALUES (:id, NULL, 'en_attente',
                        'Demande de souscription enregistrée', TRUE)"
            );
            $history->execute(['id' => $id]);

            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function findByClient(int $clientId): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, o.nom AS offre_nom, o.debit_mbps,
                    o.prix_mensuel
             FROM souscriptions s
             JOIN offres o ON o.id = s.offre_id
             WHERE s.client_id = :id
             ORDER BY s.date_souscription DESC"
        );
        $stmt->execute(['id' => $clientId]);
        return $stmt->fetchAll();
    }

    public function findAll(): array
    {
        return $this->db->query(
            "SELECT s.*, c.adresse AS adresse_client,
                    u.nom, u.prenom, u.telephone,
                    o.nom AS offre_nom, o.debit_mbps
             FROM souscriptions s
             JOIN clients c ON c.id = s.client_id
             LEFT JOIN users u ON u.id = c.user_id
             JOIN offres o ON o.id = s.offre_id
             ORDER BY s.date_souscription DESC"
        )->fetchAll();
    }

    public function findOne(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, c.adresse AS adresse_client,
                    c.quartier, c.repere AS repere_client,
                    c.latitude AS latitude_client,
                    c.longitude AS longitude_client,
                    u.nom, u.prenom, u.email, u.telephone,
                    o.nom AS offre_nom, o.debit_mbps,
                    o.prix_mensuel
             FROM souscriptions s
             JOIN clients c ON c.id = s.client_id
             LEFT JOIN users u ON u.id = c.user_id
             JOIN offres o ON o.id = s.offre_id
             WHERE s.id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function history(int $id, bool $clientOnly = false): array
    {
        $sql = "SELECT h.*, u.nom, u.prenom
                FROM historique_statuts h
                LEFT JOIN users u ON u.id = h.utilisateur_id
                WHERE h.souscription_id = :id";

        if ($clientOnly) {
            $sql .= " AND h.visible_client = TRUE";
        }

        $sql .= " ORDER BY h.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    public function updateStatus(
        int $id,
        string $status,
        int $userId,
        string $comment = ''
    ): void {
        $allowed = [
            'en_attente', 'en_etude', 'acceptee',
            'rejetee', 'en_installation', 'annulee'
        ];

        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Statut invalide.');
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                "SELECT statut FROM souscriptions
                 WHERE id = :id FOR UPDATE"
            );
            $stmt->execute(['id' => $id]);
            $old = $stmt->fetchColumn();

            if ($old === false) {
                throw new \RuntimeException('Souscription introuvable.');
            }

            $update = $this->db->prepare(
                "UPDATE souscriptions
                 SET statut = :status, updated_at = CURRENT_TIMESTAMP,
                     date_validation = CASE
                         WHEN :status_date = 'acceptee'
                         THEN COALESCE(date_validation, CURRENT_TIMESTAMP)
                         ELSE date_validation END
                 WHERE id = :id"
            );
            $update->execute(['status' => $status, 'status_date' => $status, 'id' => $id]);

            $history = $this->db->prepare(
                "INSERT INTO historique_statuts
                (souscription_id, ancien_statut, nouveau_statut,
                 commentaire, utilisateur_id, visible_client)
                VALUES (:id, :old, :new, :comment, :user, TRUE)"
            );
            $history->execute([
                'id' => $id,
                'old' => $old,
                'new' => $status,
                'comment' => $comment ?: 'Mise à jour du dossier',
                'user' => $userId,
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }


    /**
     * Valide une installation et attribue un ND unique.
     * Format : 27 + code zone + 6 chiffres aléatoires.
     */
    public function completeInstallation(
        int $installationId,
        int $actorId
    ): string {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                "SELECT
                    i.id,
                    i.technicien_id,
                    i.statut AS installation_statut,
                    s.id AS souscription_id,
                    s.statut AS souscription_statut,
                    s.identifiant_fibre,
                    c.code_zone
                 FROM installations i
                 JOIN souscriptions s ON s.id = i.souscription_id
                 LEFT JOIN communes c
                    ON LOWER(TRIM(c.nom)) = LOWER(TRIM(s.commune))
                 WHERE i.id = :id
                 FOR UPDATE OF i, s"
            );

            $stmt->execute(['id' => $installationId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                throw new \RuntimeException('Installation introuvable.');
            }

            if ((int)$item['technicien_id'] !== $actorId) {
                throw new \RuntimeException(
                    'Seul le technicien affecté peut valider cette installation.'
                );
            }

            if ($item['installation_statut'] !== 'terminee') {
                throw new \RuntimeException(
                    'L’installation doit être marquée comme terminée.'
                );
            }

            // Ne jamais générer un deuxième ND.
            if (!empty($item['identifiant_fibre'])) {
                $this->db->commit();
                return $item['identifiant_fibre'];
            }

            $zone = trim((string)($item['code_zone'] ?? ''));

            if (!preg_match('/^\d{2}$/', $zone)) {
                throw new \RuntimeException(
                    'Code de zone introuvable. Vérifiez la commune du client.'
                );
            }

            $nd = null;

            // La contrainte UNIQUE PostgreSQL reste la protection finale.
            for ($attempt = 0; $attempt < 20; $attempt++) {
                $candidate = '27' . $zone .
                    str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                $this->db->exec('SAVEPOINT nd_generation');

                try {
                    $update = $this->db->prepare(
                        "UPDATE souscriptions
                         SET identifiant_fibre = :nd,
                             date_attribution_fibre = CURRENT_TIMESTAMP,
                             date_fin_installation = CURRENT_TIMESTAMP,
                             statut = 'terminee',
                             updated_at = CURRENT_TIMESTAMP
                         WHERE id = :id
                           AND identifiant_fibre IS NULL"
                    );

                    $update->execute([
                        'nd' => $candidate,
                        'id' => $item['souscription_id'],
                    ]);

                    if ($update->rowCount() === 1) {
                        $nd = $candidate;
                        $this->db->exec('RELEASE SAVEPOINT nd_generation');
                        break;
                    }

                    $this->db->exec('RELEASE SAVEPOINT nd_generation');

                    $existing = $this->db->prepare(
                        "SELECT identifiant_fibre
                         FROM souscriptions WHERE id = :id"
                    );
                    $existing->execute(['id' => $item['souscription_id']]);
                    $nd = $existing->fetchColumn() ?: null;

                    if ($nd !== null) {
                        break;
                    }
                } catch (\PDOException $e) {
                    $this->db->exec('ROLLBACK TO SAVEPOINT nd_generation');
                    $this->db->exec('RELEASE SAVEPOINT nd_generation');

                    if ($e->getCode() !== '23505') {
                        throw $e;
                    }
                }
            }

            if ($nd === null) {
                throw new \RuntimeException(
                    'Impossible de générer un ND unique. Réessayez.'
                );
            }

            $history = $this->db->prepare(
                "INSERT INTO historique_statuts
                 (souscription_id, ancien_statut, nouveau_statut,
                  commentaire, utilisateur_id, visible_client)
                 VALUES (:id, :old, 'terminee',
                         :comment, :user, TRUE)"
            );

            $history->execute([
                'id' => $item['souscription_id'],
                'old' => $item['souscription_statut'],
                'comment' => 'Installation validée. ND attribué : ' . $nd,
                'user' => $actorId,
            ]);

            $this->db->commit();

            return $nd;

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function stats(): array
    {
        return $this->db->query(
            "SELECT statut, COUNT(*) AS total
             FROM souscriptions GROUP BY statut ORDER BY statut"
        )->fetchAll();
    }
}
