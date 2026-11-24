<?php
declare(strict_types=1);

namespace App\Core;

final class Upload
{
    public static function image(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Erreur lors de l’envoi de la photo.');
        }

        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new \RuntimeException('La photo dépasse 5 Mo.');
        }

        $mime = mime_content_type($file['tmp_name']);

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($extensions[$mime]) || getimagesize($file['tmp_name']) === false) {
            throw new \RuntimeException('Format de photo non autorisé.');
        }

        $directory = dirname(__DIR__, 2) . '/public/uploads';

        if (!is_dir($directory)) {
            mkdir($directory, 0750, true);
        }

        $name = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];

        if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) {
            throw new \RuntimeException('Impossible de sauvegarder la photo.');
        }

        return 'uploads/' . $name;
    }
}
