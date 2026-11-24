<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Installation
{
    public function __construct(private PDO $db) {}

    public function findForTechnician(int $technicianId): array
    {
        $stmt = $this->db->prepare(
            "SELECT i.*, s.numero_demande, s.adresse_installation,
                    s.commune, s.repere, s.latitude, s.longitude,
                    u.nom, u.prenom, u.telephone
             FROM installations i
             JOIN souscriptions s ON s.id = i.souscription_id
             JOIN clients c ON c.id = s.client_id
             LEFT JOIN users u ON u.id = c.user_id
             WHERE i.technicien_id = :id
             ORDER BY i.date_prevue NULLS LAST, i.created_at DESC"
        );
        $stmt->execute(['id' => $technicianId]);
        return $stmt->fetchAll();
    }

    public function findAll(): array
    {
        return $this->db->query(
            "SELECT i.*, s.numero_demande, s.adresse_installation,
                    s.commune, u.nom, u.prenom, u.telephone,
                    t.nom AS technicien_nom, t.prenom AS technicien_prenom
             FROM installations i
             JOIN souscriptions s ON s.id = i.souscription_id
             LEFT JOIN clients c ON c.id = s.client_id
             LEFT JOIN users u ON u.id = c.user_id
             LEFT JOIN users t ON t.id = i.technicien_id
             ORDER BY i.date_prevue NULLS LAST"
        )->fetchAll();
    }

    public function assign(int $subscriptionId, int $technicianId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO installations
                (souscription_id, technicien_id, statut)
             VALUES (:subscription, :technician, 'a_planifier')
             ON CONFLICT (souscription_id)
             DO UPDATE SET technicien_id = EXCLUDED.technicien_id,
                           updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([
            'subscription' => $subscriptionId,
            'technician' => $technicianId,
        ]);

        $update = $this->db->prepare(
            "UPDATE souscriptions SET technicien_id = :technician,
             updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        $update->execute([
            'technician' => $technicianId,
            'id' => $subscriptionId,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['a_planifier', 'planifiee', 'en_cours', 'terminee', 'echec'];
        if (!in_array($data['statut'], $allowed, true)) {
            throw new \InvalidArgumentException('Statut installation invalide.');
        }

        $stmt = $this->db->prepare(
            "UPDATE installations SET
                statut = :status,
                date_prevue = COALESCE(:planned, date_prevue),
                date_realisation = CASE
                    WHEN :status_date = 'terminee'
                    THEN CURRENT_TIMESTAMP ELSE date_realisation END,
                numero_serie_box = :serial,
                modele_box = :model,
                puissance_signal = :signal,
                observations = :observations,
                preuve_installation = COALESCE(:proof, preuve_installation),
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );

        $stmt->execute([
            'status' => $data['statut'],
            'status_date' => $data['statut'],
            'planned' => $data['date_prevue'] ?: null,
            'serial' => $data['numero_serie_box'] ?: null,
            'model' => $data['modele_box'] ?: null,
            'signal' => $data['puissance_signal'] ?: null,
            'observations' => $data['observations'] ?: null,
            'proof' => $data['preuve_installation'] ?: null,
            'id' => $id,
        ]);
    }
}
