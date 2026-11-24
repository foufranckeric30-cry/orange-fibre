<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Reclamation
{
    public function __construct(private PDO $db) {}

    public function create(int $clientId, ?int $subscriptionId, string $subject, string $message): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO reclamations
                (client_id, souscription_id, sujet, description)
             VALUES (:client, :subscription, :subject, :message)"
        );
        $stmt->execute([
            'client' => $clientId,
            'subscription' => $subscriptionId,
            'subject' => $subject,
            'message' => $message,
        ]);
    }

    public function all(): array
    {
        return $this->db->query(
            "SELECT r.*, u.nom, u.prenom, u.email
             FROM reclamations r
             JOIN clients c ON c.id = r.client_id
             LEFT JOIN users u ON u.id = c.user_id
             ORDER BY r.id DESC"
        )->fetchAll();
    }

    public function forClient(int $clientId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM reclamations
             WHERE client_id = :id ORDER BY id DESC"
        );
        $stmt->execute(['id' => $clientId]);
        return $stmt->fetchAll();
    }
}
