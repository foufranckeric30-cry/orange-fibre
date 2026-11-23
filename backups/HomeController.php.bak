<?php
declare(strict_types=1);

namespace App\Controllers;

use PDO;
use Throwable;

class HomeController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function index(): void
    {
        $offres = [];
        $erreur = false;

        try {
            $stmt = $this->pdo->query(
                "SELECT id, nom, description, debit_mbps,
                        prix_mensuel, frais_installation
                 FROM offres
                 WHERE active = TRUE
                 ORDER BY prix_mensuel ASC"
            );

            $offres = $stmt->fetchAll();

        } catch (Throwable $e) {
            error_log($e->getMessage());
            $erreur = true;
        }

        require dirname(__DIR__) . '/Views/home.php';
    }
}
