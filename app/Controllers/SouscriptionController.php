<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Client;
use App\Models\Offre;
use App\Models\Souscription;
use App\Models\Notification;
use PDO;

final class SouscriptionController
{
    public function __construct(private PDO $db) {}

    public function create(int $userId, array $data): int
    {
        $client = (new Client($this->db))->findByUserId($userId);

        if (!$client) {
            throw new \RuntimeException('Profil client introuvable.');
        }

        $offer = (new Offre($this->db))->findActiveById((int)($data['offre_id'] ?? 0));

        if (!$offer) {
            throw new \RuntimeException('Offre indisponible.');
        }

        $address = trim($data['adresse_installation'] ?? '');

        if ($address === '') {
            throw new \InvalidArgumentException('Adresse obligatoire.');
        }

        $latitude = $data['latitude'] ?? '';
        $longitude = $data['longitude'] ?? '';

        if ($latitude !== '' && (!is_numeric($latitude) || $latitude < -90 || $latitude > 90)) {
            throw new \InvalidArgumentException('Latitude invalide.');
        }

        if ($longitude !== '' && (!is_numeric($longitude) || $longitude < -180 || $longitude > 180)) {
            throw new \InvalidArgumentException('Longitude invalide.');
        }

        $id = (new Souscription($this->db))->create((int)$client['id'], [
            'offre_id' => (int)$offer['id'],
            'adresse_installation' => $address,
            'commune' => trim($data['commune'] ?? ''),
            'observations' => trim($data['observations'] ?? ''),
            'repere' => trim($data['repere'] ?? ''),
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        return $id;
    }
}
