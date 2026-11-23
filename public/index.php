<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Security;
use App\Models\Offre;
use App\Models\Client;
use App\Controllers\AuthController;

require_once dirname(__DIR__) . '/config/Database.php';
require_once dirname(__DIR__) . '/app/Core/Security.php';
require_once dirname(__DIR__) . '/app/Models/User.php';
require_once dirname(__DIR__) . '/app/Models/Client.php';
require_once dirname(__DIR__) . '/app/Models/Offre.php';
require_once dirname(__DIR__) . '/app/Controllers/AuthController.php';

ini_set('session.use_strict_mode', '1');

session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
    'path' => '/',
]);

session_start();

function redirect(string $route): never
{
    header('Location: ?r=' . urlencode($route));
    exit;
}

function page(string $title, string $content): void
{
    $user = Security::currentUser();

    echo '<!DOCTYPE html><html lang="fr"><head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . Security::escape($title) . ' | Orange Fibre</title>';
    echo '<style>
        *{box-sizing:border-box}
        body{margin:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#252525}
        header{background:#ff7900;color:white;padding:18px 6%;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
        header a{color:white;text-decoration:none;margin:5px}
        .logo{font-size:23px;font-weight:bold}
        main{max-width:1100px;margin:35px auto;padding:20px}
        .card{background:white;border-radius:12px;padding:25px;margin-bottom:20px;box-shadow:0 3px 15px #0000000c}
        h1,h2{color:#333}
        input,select,textarea{width:100%;padding:13px;margin:7px 0 16px;border:1px solid #ddd;border-radius:7px}
        label{font-weight:bold;font-size:14px}
        button,.btn{display:inline-block;background:#ff7900;color:white;border:0;padding:13px 20px;border-radius:7px;text-decoration:none;cursor:pointer;font-weight:bold}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:18px}
        .price{font-size:25px;color:#ff7900;font-weight:bold}
        .muted{color:#777}
        .error{background:#ffe7e7;color:#a40000;padding:12px;border-radius:7px;margin-bottom:15px}
        .success{background:#e6f7eb;color:#176c35;padding:12px;border-radius:7px;margin-bottom:15px}
        footer{text-align:center;padding:25px;color:#777}
        @media(max-width:600px){main{padding:12px}header{padding:15px}}
    </style></head><body>';

    echo '<header><a class="logo" href="?r=home">ORANGE FIBRE</a><nav>';
    echo '<a href="?r=home">Accueil</a>';
    echo '<a href="?r=offres">Nos offres</a>';

    if ($user) {
        echo '<a href="?r=dashboard">Mon espace</a>';
        echo '<a href="?r=logout">Déconnexion</a>';
    } else {
        echo '<a href="?r=login">Connexion</a>';
        echo '<a href="?r=register">Inscription</a>';
    }

    echo '</nav></header><main>' . $content . '</main>';
    echo '<footer>Orange Fibre Côte d’Ivoire — Votre connexion, notre priorité.</footer>';
    echo '</body></html>';
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' .
        Security::escape(Security::csrfToken()) . '">';
}

function messageBox(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }

    $message = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return '<div class="' . ($message['success'] ? 'success' : 'error') .
        '">' . Security::escape($message['message']) . '</div>';
}

try {
    $db = Database::connect();
    $auth = new AuthController($db);
    $offresModel = new Offre($db);

    $route = $_GET['r'] ?? 'home';
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        Security::verifyCsrf();
    }

    switch ($route) {

        case 'home':
            page('Accueil', '
                <section class="card">
                    <h1>Bienvenue chez Orange Fibre</h1>
                    <p>Profitez d’une connexion Fibre rapide et adaptée à vos besoins.</p>
                    <a class="btn" href="?r=offres">Découvrir nos offres</a>
                    <a class="btn" href="?r=register">Créer mon compte</a>
                </section>
                <section class="card">
                    <h2>Suivez votre souscription</h2>
                    <p>Connectez-vous pour consulter à tout moment l’évolution de votre demande.</p>
                    <a class="btn" href="?r=login">Accéder à mon espace</a>
                </section>
            ');
            break;

        case 'register':
            if ($method === 'POST') {
                $result = $auth->register($_POST);
                $_SESSION['flash'] = $result;
                redirect($result['success'] ? 'login' : 'register');
            }

            page('Inscription', messageBox() . '
                <section class="card">
                    <h1>Créer un compte client</h1>
                    <form method="POST">
                        ' . csrfField() . '
                        <label>Nom *</label><input name="nom" required maxlength="100">
                        <label>Prénom</label><input name="prenom" maxlength="100">
                        <label>E-mail *</label><input type="email" name="email" required>
                        <label>Téléphone</label><input name="telephone" maxlength="20">
                        <label>Mot de passe *</label><input type="password" name="password" minlength="10" required>
                        <label>Adresse d’installation *</label><input name="adresse" required maxlength="255">
                        <label>Commune</label><input name="commune">
                        <label>Ville</label><input name="ville" value="Abidjan">
                        <label>Quartier</label><input name="quartier">
                        <label>Repère</label><textarea name="repere"></textarea>
                        <button type="submit">Créer mon compte</button>
                    </form>
                </section>
            ');
            break;

        case 'login':
            if ($method === 'POST') {
                $result = $auth->login(
                    $_POST['email'] ?? '',
                    $_POST['password'] ?? ''
                );

                if ($result['success']) {
                    redirect('dashboard');
                }

                $_SESSION['flash'] = $result;
                redirect('login');
            }

            page('Connexion', messageBox() . '
                <section class="card">
                    <h1>Connexion</h1>
                    <form method="POST">
                        ' . csrfField() . '
                        <label>Adresse e-mail</label>
                        <input type="email" name="email" required>
                        <label>Mot de passe</label>
                        <input type="password" name="password" required>
                        <button type="submit">Se connecter</button>
                    </form>
                    <p>Pas encore de compte ? <a href="?r=register">Inscrivez-vous</a></p>
                </section>
            ');
            break;

        case 'logout':
            if ($method !== 'POST') {
                page('Déconnexion', '
                    <section class="card">
                        <h2>Confirmer la déconnexion</h2>
                        <form method="POST">
                            ' . csrfField() . '
                            <button type="submit">Me déconnecter</button>
                        </form>
                    </section>
                ');
                break;
            }

            $auth->logout();
            redirect('home');

        case 'offres':
            $offres = $offresModel->getActiveOffers();
            $content = '<h1>Nos offres Fibre</h1><div class="grid">';

            foreach ($offres as $offre) {
                $avantages = $offre['avantages'];
                if (is_string($avantages)) {
                    $avantages = json_decode($avantages, true) ?? [];
                }

                $content .= '<section class="card">';
                $content .= '<h2>' . Security::escape($offre['nom']) . '</h2>';
                $content .= '<div class="price">' .
                    number_format((float)$offre['prix_mensuel'], 0, ',', ' ') .
                    ' FCFA/mois</div>';
                $content .= '<h3>' . (int)$offre['debit_mbps'] . ' Mb/s</h3>';
                $content .= '<p>' . Security::escape($offre['description']) . '</p>';
                $content .= '<p>Installation : ' .
                    number_format((float)$offre['frais_installation'], 0, ',', ' ') .
                    ' FCFA</p>';

                if (is_array($avantages)) {
                    foreach ($avantages as $avantage) {
                        if (is_scalar($avantage)) {
                            $content .= '<p>✓ ' . Security::escape((string)$avantage) . '</p>';
                        }
                    }
                }

                $content .= '<a class="btn" href="?r=register">Souscrire</a>';
                $content .= '</section>';
            }

            $content .= '</div>';

            if (!$offres) {
                $content .= '<p>Aucune offre disponible actuellement.</p>';
            }

            page('Offres Fibre', $content);
            break;

        case 'dashboard':
            Security::requireLogin();

            $user = Security::currentUser();
            $profile = (new Client($db))->findByUserId($user['id']);

            $content = '<section class="card">';
            $content .= '<h1>Bienvenue ' .
                Security::escape($user['prenom'] . ' ' . $user['nom']) .
                '</h1>';
            $content .= '<p>Adresse e-mail : ' . Security::escape($user['email']) . '</p>';
            $content .= '<p>Rôle : ' . Security::escape($user['role']) . '</p>';

            if ($profile) {
                $content .= '<h2>Mon profil</h2>';
                $content .= '<p>Adresse : ' . Security::escape($profile['adresse']) . '</p>';
                $content .= '<p>Commune : ' . Security::escape($profile['commune']) . '</p>';
                $content .= '<p>Ville : ' . Security::escape($profile['ville']) . '</p>';
                $content .= '<p>Quartier : ' . Security::escape($profile['quartier']) . '</p>';
            }

            $content .= '<a class="btn" href="?r=offres">Voir les offres</a>';
            $content .= '</section>';

            page('Mon espace', $content);
            break;

        default:
            http_response_code(404);
            page('Page introuvable', '<section class="card"><h1>404</h1><p>Cette page n’existe pas.</p><a href="?r=home">Accueil</a></section>');
    }

} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);

    page('Erreur technique', '
        <section class="card">
            <h1>Une erreur technique est survenue.</h1>
            <p>Vérifie la configuration de la base de données et les journaux PHP.</p>
        </section>
    ');
}
