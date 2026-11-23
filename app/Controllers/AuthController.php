<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Models\User;
use App\Models\Client;
use PDO;
use PDOException;
use Throwable;

final class AuthController
{
    private User $users;
    private Client $clients;

    public function __construct(private PDO $db)
    {
        $this->users = new User($db);
        $this->clients = new Client($db);
    }

    public function register(array $data): array
    {
        $nom = trim($data['nom'] ?? '');
        $prenom = trim($data['prenom'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $telephone = trim($data['telephone'] ?? '');
        $password = $data['password'] ?? '';
        $adresse = trim($data['adresse'] ?? '');

        if (
            $nom === '' || $email === '' ||
            $password === '' || $adresse === ''
        ) {
            return ['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Adresse e-mail invalide.'];
        }

        if (strlen($password) < 10) {
            return ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 10 caractères.'];
        }

        if ($this->users->findByEmail($email)) {
            return ['success' => false, 'message' => 'Cette adresse e-mail possède déjà un compte.'];
        }

        try {
            $this->db->beginTransaction();

            $userId = $this->users->create([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone,
                'password' => $password,
            ]);

            $this->clients->createProfile($userId, [
                'adresse' => $adresse,
                'commune' => trim($data['commune'] ?? ''),
                'ville' => trim($data['ville'] ?? 'Abidjan'),
                'quartier' => trim($data['quartier'] ?? ''),
                'repere' => trim($data['repere'] ?? ''),
            ]);

            $this->db->commit();

            return ['success' => true, 'message' => 'Compte créé. Vous pouvez maintenant vous connecter.'];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($e instanceof PDOException && $e->getCode() === '23505') {
                return ['success' => false, 'message' => 'Cette adresse e-mail est déjà utilisée.'];
            }

            error_log($e->getMessage());

            return ['success' => false, 'message' => 'Impossible de créer le compte. Vérifiez les informations et réessayez.'];
        }
    }

    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail(strtolower(trim($email)));

        if (
            !$user ||
            !$user['actif'] ||
            !$this->users->verifyPassword($password, $user)
        ) {
            return [
                'success' => false,
                'message' => 'Identifiants incorrects ou compte désactivé.'
            ];
        }

        Security::login($user);

        return ['success' => true, 'message' => 'Connexion réussie.'];
    }

    public function logout(): void
    {
        Security::logout();
    }
}
