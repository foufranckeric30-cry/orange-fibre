<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

final class Souscription
{
    public function __construct(private PDO $db) {}

    public function create(int $clientId, array $data): int
    {
        $this->db->beginTransaction();

        try {
            $numero = 'OF-' . date('Ymd') . '-' .
                strtoupper(bin2hex(random_bytes(4)));

            $stmt = $this->db->prepare("
                INSERT INTO souscriptions (
                    client_id, offre_id, adresse_installation,
                    commune, statut, observations, numero_demande,
                    repere, latitude, longitude
                ) VALUES (
                    :client, :offre, :adresse, :commune,
                    'en_attente', :observations, :numero,
                    :repere, :latitude, :longitude
                ) RETURNING id
            ");

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

            $id = (int)$stmt->fetchColumn();

            $history = $this->db->prepare("
                INSERT INTO historique_statuts
                (souscription_id, nouveau_statut, commentaire)
                VALUES (:id, 'en_attente', 'Demande créée')
            ");
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

    public function completeInstallation(
        int $subscriptionId,
        int $technicianId
    ): string {
        $this->db->beginTransaction();

        try {
            $query = $this->db->prepare("
                SELECT s.*, c.code_zone, i.statut AS installation_statut,
                       i.technicien_id AS installation_technicien
                FROM souscriptions s
                JOIN communes c ON LOWER(c.nom) = LOWER(s.commune)
                JOIN installations i ON i.souscription_id = s.id
                WHERE s.id = :id
                FOR UPDATE OF s, i
            ");

            $query->execute(['id' => $subscriptionId]);
            $row = $query->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new RuntimeException(
                    'Souscription, commune ou installation introuvable.'
                );
            }

            if ((int)$row['technicien_id'] !== $technicianId ||
                (int)$row['installation_technicien'] !== $technicianId) {
                throw new RuntimeException(
                    'Seul le technicien affecté peut valider.'
                );
            }

            if ($row['installation_statut'] !== 'terminee') {
                throw new RuntimeException(
                    'L’installation doit être terminée avant validation.'
                );
            }

            if (!empty($row['identifiant_fibre'])) {
                $this->db->commit();
                return $row['identifiant_fibre'];
            }

            $code = $row['code_zone'];

            if (!preg_match('/^[0-9]{2}$/', $code)) {
                throw new RuntimeException('Code de commune invalide.');
            }

            $nd = null;

            for ($i = 0; $i < 20; $i++) {
                $candidate = '27' . $code .
                    str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                $check = $this->db->prepare("
                    SELECT 1 FROM souscriptions
                    WHERE identifiant_fibre = :nd
                ");
                $check->execute(['nd' => $candidate]);

                if (!$check->fetchColumn()) {
                    $nd = $candidate;
                    break;
                }
            }

            if ($nd === null) {
                throw new RuntimeException(
                    'Impossible de générer un ND disponible.'
                );
            }

            $update = $this->db->prepare("
                UPDATE souscriptions
                SET identifiant_fibre = :nd,
                    statut = 'terminee',
                    date_validation = CURRENT_TIMESTAMP,
                    date_fin_installation = CURRENT_TIMESTAMP,
                    date_attribution_fibre = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");

            $update->execute([
                'nd' => $nd,
                'id' => $subscriptionId,
            ]);

            $history = $this->db->prepare("
                INSERT INTO historique_statuts
                (souscription_id, ancien_statut, nouveau_statut,
                 commentaire, utilisateur_id)
                VALUES (
                    :id, 'en_installation', 'terminee',
                    'Installation validée. ND attribué : ' || :nd,
                    :user
                )
            ");

            $history->execute([
                'id' => $subscriptionId,
                'nd' => $nd,
                'user' => $technicianId,
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
}
