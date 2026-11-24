<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Notification
{
    public function __construct(private PDO $db) {}

    public function forUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM notifications
             WHERE user_id = :id
             ORDER BY created_at DESC"
        );
        $stmt->execute(['id' => $userId]);
        return $stmt->fetchAll();
    }

    public function markRead(int $id, int $userId): void
    {
        $columns = $this->columns();

        $readColumn = null;
        foreach (['lu', 'lue', 'est_lu', 'read_at', 'date_lecture'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                $readColumn = $candidate;
                break;
            }
        }

        if (!$readColumn) {
            return;
        }

        $value = str_ends_with($readColumn, '_at') ||
                 str_starts_with($readColumn, 'date_')
            ? 'CURRENT_TIMESTAMP' : 'TRUE';

        $stmt = $this->db->prepare(
            "UPDATE notifications SET \"$readColumn\" = $value
             WHERE id = :id AND user_id = :user"
        );
        $stmt->execute(['id' => $id, 'user' => $userId]);
    }

    public function create(int $userId, ?int $subscriptionId, string $title, string $message): void
    {
        $columns = $this->columns();
        $fields = [];
        $values = [];
        $params = [];

        $candidates = [
            'user_id' => $userId,
            'souscription_id' => $subscriptionId,
            'titre' => $title,
            'message' => $message,
            'contenu' => $message,
            'lu' => false,
            'created_at' => null,
        ];

        foreach ($candidates as $column => $value) {
            if (in_array($column, $columns, true)) {
                $fields[] = "\"$column\"";
                if ($column === 'created_at') {
                    $values[] = 'CURRENT_TIMESTAMP';
                } else {
                    $values[] = ':' . $column;
                    $params[$column] = $value;
                }
            }
        }

        if (!in_array('user_id', $columns, true) || count($fields) < 2) {
            return;
        }

        $sql = 'INSERT INTO notifications (' . implode(',', $fields) .
               ') VALUES (' . implode(',', $values) . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    private function columns(): array
    {
        $stmt = $this->db->query(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'notifications'"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
