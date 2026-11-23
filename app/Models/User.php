<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class User
{
    public function __construct(private PDO $db) {}

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nom, prenom, email, telephone,
                    password, role, actif
             FROM users
             WHERE LOWER(email) = LOWER(:email)
             LIMIT 1'
        );

        $stmt->execute(['email' => $email]);

        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nom, prenom, email, telephone, role, actif
             FROM users WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users
                (nom, prenom, email, telephone, password, role, actif)
             VALUES
                (:nom, :prenom, :email, :telephone, :password, :role, TRUE)
             RETURNING id'
        );

        $stmt->execute([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => strtolower($data['email']),
            'telephone' => $data['telephone'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => 'client',
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function verifyPassword(string $password, array $user): bool
    {
        return password_verify($password, $user['password']);
    }
}
