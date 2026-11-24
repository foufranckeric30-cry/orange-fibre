<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Security;
use App\Core\Upload;
use App\Models\User;
use App\Models\Client;
use App\Models\Offre;
use App\Models\Souscription;
use App\Models\Installation;
use App\Models\Notification;
use App\Models\Reclamation;
use App\Controllers\AuthController;
use App\Controllers\SouscriptionController;

require_once dirname(__DIR__) . '/config/Database.php';
require_once dirname(__DIR__) . '/app/Core/Security.php';
require_once dirname(__DIR__) . '/app/Core/Upload.php';

foreach ([
    'User', 'Client', 'Offre', 'Souscription',
    'Installation', 'Notification', 'Reclamation'
] as $model) {
    require_once dirname(__DIR__) . "/app/Models/$model.php";
}

require_once dirname(__DIR__) . '/app/Controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/Controllers/SouscriptionController.php';

ini_set('session.use_strict_mode', '1');

session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
    'path' => '/',
]);

session_start();

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function go(string $route): never
{
    header('Location: ?r=' . urlencode($route));
    exit;
}

function flash(string $message, bool $success = false): void
{
    $_SESSION['flash'] = ['message' => $message, 'success' => $success];
}

function showFlash(): string
{
    if (empty($_SESSION['flash'])) return '';

    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return '<div class="' . ($f['success'] ? 'success' : 'error') . '">' .
        e($f['message']) . '</div>';
}

function csrf(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Security::csrfToken()) . '">';
}

function field(string $label, string $name, string $type = 'text', bool $required = false, string $value = ''): string
{
    return '<label>' . e($label) . '</label><input type="' . e($type) .
        '" name="' . e($name) . '" value="' . e($value) . '"' .
        ($required ? ' required' : '') . '>';
}

function layout(string $title, string $content): void
{
    $user = Security::currentUser();

    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . e($title) . ' | Orange Fibre</title>';
    echo '<link rel="stylesheet" href="assets/css/style.css"></head><body>';

    echo '<header class="topbar"><a class="brand" href="?r=home">ORANGE FIBRE</a><nav>';
    echo '<a href="?r=home">Accueil</a><a href="?r=offres">Offres</a>';

    if ($user) {
        echo '<a href="?r=dashboard">Tableau de bord</a>';
        echo '<a href="?r=notifications">Notifications</a>';
        echo '<form class="inline" method="post" action="?r=logout">' . csrf() .
             '<button class="nav-button">Déconnexion</button></form>';
    } else {
        echo '<a href="?r=login">Connexion</a><a href="?r=register">Inscription</a>';
    }

    echo '</nav></header><main>' . showFlash() . $content . '</main>';
    echo '<footer>Orange Fibre Côte d’Ivoire · Service client</footer>';
    echo '<script src="assets/js/app.js"></script></body></html>';
}

function requireRole(array $roles): array
{
    Security::requireLogin();
    $user = Security::currentUser();

    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Accès interdit.');
    }

    return $user;
}

function statusLabel(string $status): string
{
    return match ($status) {
        'en_attente' => 'En attente',
        'en_etude' => 'En étude',
        'acceptee' => 'Acceptée',
        'rejetee' => 'Rejetée',
        'en_installation' => 'En installation',
        'terminee' => 'Terminée',
        'annulee' => 'Annulée',
        default => $status,
    };
}

function table(string $headers, array $rows): string
{
    $html = '<div class="table-wrap"><table><thead><tr>' . $headers . '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $value) {
            $html .= '<td>' . e($value) . '</td>';
        }
        $html .= '</tr>';
    }

    return $html . '</tbody></table></div>';
}

try {
    $db = Database::connect();
    $auth = new AuthController($db);
    $users = new User($db);
    $clients = new Client($db);
    $offers = new Offre($db);
    $subscriptions = new Souscription($db);
    $installations = new Installation($db);
    $notifications = new Notification($db);
    $complaints = new Reclamation($db);

    $route = $_GET['r'] ?? 'home';
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        Security::verifyCsrf();
    }

    switch ($route) {

        case 'home':
            layout('Accueil', '
                <section class="hero">
                    <div><span class="eyebrow">CONNECTIVITÉ HAUT DÉBIT</span>
                    <h1>La Fibre qui vous rapproche de l’essentiel.</h1>
                    <p>Découvrez nos offres Internet Fibre et profitez d’une connexion adaptée à votre quotidien.</p>
                    <a class="button" href="?r=offres">Découvrir les offres</a>
                    <a class="button secondary" href="?r=register">Créer un compte</a></div>
                </section>
                <section class="section">
                    <h2>Nos services</h2>
                    <div class="grid">
                        <article class="card"><h3>Offres Fibre</h3><p>Choisissez votre débit et votre abonnement.</p></article>
                        <article class="card"><h3>Suivi permanent</h3><p>Consultez à tout moment l’évolution de votre demande.</p></article>
                        <article class="card"><h3>Installation</h3><p>Suivez la prise en charge de votre raccordement.</p></article>
                    </div>
                </section>
            ');
            break;

        case 'register':
            if ($method === 'POST') {
                $result = $auth->register($_POST);
                flash($result['message'], $result['success']);
                go($result['success'] ? 'login' : 'register');
            }

            layout('Inscription', '
                <section class="form-card">
                    <h1>Créer un compte client</h1>
                    <form method="post">' . csrf() .
                    field('Nom *', 'nom', 'text', true) .
                    field('Prénom', 'prenom') .
                    field('E-mail *', 'email', 'email', true) .
                    field('Téléphone', 'telephone', 'tel') .
                    field('Mot de passe (10 caractères minimum) *', 'password', 'password', true) .
                    field('Adresse d’installation *', 'adresse', 'text', true) .
                    field('Commune', 'commune') .
                    field('Ville', 'ville', 'text', false, 'Abidjan') .
                    field('Quartier', 'quartier') .
                    '<label>Repère</label><textarea name="repere"></textarea>
                    <button class="button">Créer mon compte</button></form>
                </section>
            ');
            break;

        case 'login':
            if ($method === 'POST') {
                $result = $auth->login($_POST['email'] ?? '', $_POST['password'] ?? '');
                if ($result['success']) go('dashboard');
                flash($result['message']);
                go('login');
            }

            layout('Connexion', '
                <section class="form-card">
                    <h1>Connexion</h1>
                    <form method="post">' . csrf() .
                    field('Adresse e-mail', 'email', 'email', true) .
                    field('Mot de passe', 'password', 'password', true) .
                    '<button class="button">Se connecter</button></form>
                    <p>Pas de compte ? <a href="?r=register">Inscrivez-vous</a></p>
                </section>
            ');
            break;

        case 'logout':
            if ($method !== 'POST') go('home');
            $auth->logout();
            go('home');

        case 'offres':
            $items = $offers->getActiveOffers();
            $html = '<h1>Nos offres Fibre</h1><div class="grid">';

            foreach ($items as $offer) {
                $benefits = is_array($offer['avantages'])
                    ? $offer['avantages']
                    : (json_decode($offer['avantages'], true) ?? []);

                $html .= '<article class="card offer"><h2>' . e($offer['nom']) . '</h2>';
                $html .= '<p class="price">' . number_format((float)$offer['prix_mensuel'], 0, ',', ' ') . ' FCFA</p>';
                $html .= '<h3>' . (int)$offer['debit_mbps'] . ' Mb/s</h3>';
                $html .= '<p>' . e($offer['description']) . '</p>';
                $html .= '<p>Installation : ' . number_format((float)$offer['frais_installation'], 0, ',', ' ') . ' FCFA</p>';

                foreach ($benefits as $benefit) {
                    if (is_scalar($benefit)) $html .= '<p>✓ ' . e($benefit) . '</p>';
                }

                $html .= '<a class="button" href="?r=subscribe&id=' . (int)$offer['id'] . '">Choisir cette offre</a></article>';
            }

            $html .= '</div>';
            if (!$items) $html .= '<p>Aucune offre disponible.</p>';
            layout('Offres', $html);
            break;

        case 'subscribe':
            $user = requireRole(['client']);
            $offer = $offers->findActiveById((int)($_GET['id'] ?? $_POST['offre_id'] ?? 0));
            if (!$offer) {
                flash('Offre introuvable ou inactive.');
                go('offres');
            }

            if ($method === 'POST') {
                try {
                    $id = (new SouscriptionController($db))->create((int)$user['id'], $_POST);
                    flash('Votre demande a été enregistrée. Référence : ' . $id, true);
                    go('my-subscriptions');
                } catch (Throwable $e) {
                    error_log($e->getMessage());
                    flash($e instanceof InvalidArgumentException ? $e->getMessage() : 'Impossible d’enregistrer la demande.');
                    go('subscribe&id=' . (int)$offer['id']);
                }
            }

            $profile = $clients->findByUserId((int)$user['id']);

            layout('Souscrire', '
                <section class="form-card">
                    <h1>Souscrire à ' . e($offer['nom']) . '</h1>
                    <p>' . (int)$offer['debit_mbps'] . ' Mb/s · ' .
                    number_format((float)$offer['prix_mensuel'], 0, ',', ' ') . ' FCFA/mois</p>
                    <form method="post">' . csrf() .
                    '<input type="hidden" name="offre_id" value="' . (int)$offer['id'] . '">' .
                    field('Adresse d’installation *', 'adresse_installation', 'text', true, $profile['adresse'] ?? '') .
                    field('Commune', 'commune', 'text', false, $profile['commune'] ?? '') .
                    field('Repère', 'repere', 'text', false, $profile['repere'] ?? '') .
                    field('Latitude GPS', 'latitude', 'number') .
                    field('Longitude GPS', 'longitude', 'number') .
                    '<label>Observations</label><textarea name="observations"></textarea>
                    <button class="button">Confirmer la souscription</button></form>
                </section>
            ');
            break;

        case 'dashboard':
            $user = Security::currentUser();
            if (!$user) go('login');

            switch ($user['role']) {
                case 'client': go('client-dashboard');
                case 'agent': go('agent-dashboard');
                case 'technicien': go('technician-dashboard');
                case 'superviseur': go('supervisor-dashboard');
                case 'admin': go('admin-dashboard');
                default:
                    http_response_code(403);
                    exit('Rôle non autorisé.');
            }

        case 'client-dashboard':
            $user = requireRole(['client']);
            $profile = $clients->findByUserId((int)$user['id']);

            layout('Espace client', '
                <h1>Bonjour ' . e($user['prenom'] . ' ' . $user['nom']) . '</h1>
                <div class="grid">
                    <a class="card link-card" href="?r=offres"><h2>Nos offres</h2><p>Découvrir les abonnements.</p></a>
                    <a class="card link-card" href="?r=my-subscriptions"><h2>Mes souscriptions</h2><p>Suivre mes demandes.</p></a>
                    <a class="card link-card" href="?r=my-complaints"><h2>Mes réclamations</h2><p>Consulter mes réclamations.</p></a>
                    <a class="card link-card" href="?r=notifications"><h2>Notifications</h2><p>Consulter mes alertes.</p></a>
                </div>
                <section class="card"><h2>Mon profil</h2>
                <p>' . e($profile['adresse'] ?? '') . '</p>
                <p>' . e($profile['commune'] ?? '') . ' · ' . e($profile['ville'] ?? '') . '</p>
                <p>' . e($user['email']) . '</p></section>
            ');
            break;

        case 'my-subscriptions':
            $user = requireRole(['client']);
            $profile = $clients->findByUserId((int)$user['id']);
            if (!$profile) throw new RuntimeException('Profil client introuvable.');

            $items = $subscriptions->findByClient((int)$profile['id']);
            $html = '<h1>Mes souscriptions</h1>';

            foreach ($items as $item) {
                $html .= '<article class="card"><h2>' . e($item['numero_demande']) . '</h2>';
                $html .= '<p>Offre : ' . e($item['offre_nom']) . '</p>';
                $html .= '<p>Débit : ' . (int)$item['debit_mbps'] . ' Mb/s</p>';
                $html .= '<p>Statut : <strong>' . e(statusLabel($item['statut'])) . '</strong></p>';
                $html .= '<p>Adresse : ' . e($item['adresse_installation']) . '</p>';
                $html .= '<a class="button" href="?r=tracking&id=' . (int)$item['id'] . '">Voir le suivi</a></article>';
            }

            if (!$items) $html .= '<p>Aucune souscription pour le moment.</p>';
            layout('Mes souscriptions', $html);
            break;

        case 'tracking':
            $user = requireRole(['client']);
            $profile = $clients->findByUserId((int)$user['id']);
            $item = $subscriptions->findOne((int)($_GET['id'] ?? 0));

            if (!$item || !$profile || (int)$item['client_id'] !== (int)$profile['id']) {
                http_response_code(404);
                exit('Demande introuvable.');
            }

            $history = $subscriptions->history((int)$item['id'], true);
            $html = '<section class="card"><h1>Suivi de la demande ' . e($item['numero_demande']) . '</h1>';
            $html .= '<h2>' . e(statusLabel($item['statut'])) . '</h2>';
            $html .= '<p>Offre : ' . e($item['offre_nom']) . '</p>';
            $html .= '<p>Adresse : ' . e($item['adresse_installation']) . '</p>';
            $html .= '<p>Date : ' . e($item['date_souscription']) . '</p></section>';
            $html .= '<section class="card"><h2>Historique</h2>';

            foreach ($history as $h) {
                $html .= '<div class="timeline"><strong>' . e(statusLabel($h['nouveau_statut'])) . '</strong>';
                $html .= '<p>' . e($h['commentaire']) . '</p><small>' . e($h['created_at']) . '</small></div>';
            }

            $html .= '</section>';
            layout('Suivi de demande', $html);
            break;

        case 'notifications':
            $user = requireRole(['client', 'agent', 'technicien', 'superviseur', 'admin']);
            $items = $notifications->forUser((int)$user['id']);
            $html = '<h1>Mes notifications</h1>';

            foreach ($items as $n) {
                $html .= '<article class="card"><h3>' . e($n['titre'] ?? 'Notification') . '</h3>';
                $html .= '<p>' . e($n['message'] ?? $n['contenu'] ?? '') . '</p>';
                $html .= '<small>' . e($n['created_at'] ?? '') . '</small></article>';
            }

            if (!$items) $html .= '<p>Aucune notification.</p>';
            layout('Notifications', $html);
            break;

        case 'complaint':
            $user = requireRole(['client']);
            if ($method === 'POST') {
                $profile = $clients->findByUserId((int)$user['id']);
                if (!$profile) throw new RuntimeException('Profil client introuvable.');

                $complaints->create(
                    (int)$profile['id'],
                    !empty($_POST['souscription_id']) ? (int)$_POST['souscription_id'] : null,
                    trim($_POST['sujet'] ?? ''),
                    trim($_POST['description'] ?? '')
                );

                flash('Réclamation enregistrée.', true);
                go('my-complaints');
            }

            layout('Nouvelle réclamation', '
                <section class="form-card"><h1>Déposer une réclamation</h1>
                <form method="post">' . csrf() .
                field('Sujet', 'sujet', 'text', true) .
                '<label>Description</label><textarea name="description" required></textarea>
                <button class="button">Envoyer</button></form></section>
            ');
            break;

        case 'my-complaints':
            $user = requireRole(['client']);
            $profile = $clients->findByUserId((int)$user['id']);
            $items = $complaints->forClient((int)$profile['id']);
            $html = '<h1>Mes réclamations</h1><a class="button" href="?r=complaint">Nouvelle réclamation</a>';

            foreach ($items as $item) {
                $html .= '<article class="card"><h3>' . e($item['sujet'] ?? 'Réclamation') . '</h3>';
                $html .= '<p>' . e($item['description'] ?? '') . '</p>';
                $html .= '<p>Statut : ' . e($item['statut'] ?? 'Enregistrée') . '</p></article>';
            }

            layout('Réclamations', $html);
            break;

        case 'agent-dashboard':
            $user = requireRole(['agent', 'admin', 'superviseur']);
            $items = $subscriptions->findAll();
            $html = '<h1>Espace agent</h1><p>Gestion des demandes de souscription.</p>';
            $html .= '<p>Total des demandes : ' . count($items) . '</p>';

            foreach ($items as $item) {
                $html .= '<article class="card"><h3>' . e($item['numero_demande']) . '</h3>';
                $html .= '<p>Client : ' . e($item['prenom'] . ' ' . $item['nom']) . '</p>';
                $html .= '<p>Offre : ' . e($item['offre_nom']) . '</p>';
                $html .= '<p>Statut : ' . e(statusLabel($item['statut'])) . '</p>';
                $html .= '<a class="button" href="?r=manage-subscription&id=' . (int)$item['id'] . '">Gérer</a></article>';
            }

            layout('Espace agent', $html);
            break;

        case 'manage-subscription':
            $user = requireRole(['agent', 'admin', 'superviseur']);
            $id = (int)($_GET['id'] ?? 0);
            $item = $subscriptions->findOne($id);
            if (!$item) throw new RuntimeException('Demande introuvable.');

            if ($method === 'POST') {
                $subscriptions->updateStatus(
                    $id,
                    $_POST['statut'] ?? '',
                    (int)$user['id'],
                    trim($_POST['commentaire'] ?? '')
                );

                if (in_array($_POST['statut'], ['acceptee', 'en_installation'], true)) {
                    $notifications->create(
                        (int)$item['client_id'],
                        $id,
                        'Mise à jour de votre souscription',
                        'Le statut de votre demande est maintenant : ' . statusLabel($_POST['statut'])
                    );
                }

                flash('Statut mis à jour.', true);
                go('agent-dashboard');
            }

            layout('Gestion de demande', '
                <section class="form-card"><h1>' . e($item['numero_demande']) . '</h1>
                <p>Client : ' . e($item['prenom'] . ' ' . $item['nom']) . '</p>
                <p>Téléphone : ' . e($item['telephone']) . '</p>
                <p>Adresse : ' . e($item['adresse_installation']) . '</p>
                <form method="post">' . csrf() . '
                <label>Nouveau statut</label><select name="statut">
                    <option value="en_attente">En attente</option>
                    <option value="en_etude">En étude</option>
                    <option value="acceptee">Acceptée</option>
                    <option value="rejetee">Rejetée</option>
                    <option value="en_installation">En installation</option>
                    <option value="terminee">Terminée</option>
                    <option value="annulee">Annulée</option>
                </select>
                <label>Commentaire</label><textarea name="commentaire"></textarea>
                <button class="button">Enregistrer</button></form></section>
            ');
            break;

        case 'technician-dashboard':
            $user = requireRole(['technicien', 'admin', 'superviseur']);
            $items = $installations->findForTechnician((int)$user['id']);
            $html = '<h1>Espace technicien</h1>';

            foreach ($items as $item) {
                $html .= '<article class="card"><h3>' . e($item['numero_demande']) . '</h3>';
                $html .= '<p>Client : ' . e($item['prenom'] . ' ' . $item['nom']) . '</p>';
                $html .= '<p>Téléphone : ' . e($item['telephone']) . '</p>';
                $html .= '<p>Adresse : ' . e($item['adresse_installation']) . '</p>';
                $html .= '<p>Commune : ' . e($item['commune']) . '</p>';
                $html .= '<p>Repère : ' . e($item['repere']) . '</p>';
                if ($item['latitude'] !== null && $item['longitude'] !== null) {
                    $html .= '<a target="_blank" rel="noopener" href="https://www.google.com/maps?q=' .
                        e($item['latitude']) . ',' . e($item['longitude']) . '">Ouvrir la localisation</a>';
                }
                $html .= '<p>Statut : ' . e($item['statut']) . '</p>';
                $html .= '<a class="button" href="?r=installation&id=' . (int)$item['id'] . '">Mettre à jour</a></article>';
            }

            if (!$items) $html .= '<p>Aucune intervention affectée.</p>';
            layout('Espace technicien', $html);
            break;

        case 'installation':
            $user = requireRole(['technicien', 'admin', 'superviseur']);
            $id = (int)($_GET['id'] ?? 0);

            $stmt = $db->prepare(
                "SELECT i.* FROM installations i WHERE i.id = :id"
            );
            $stmt->execute(['id' => $id]);
            $installation = $stmt->fetch();

            if (!$installation) throw new RuntimeException('Installation introuvable.');

            if ($user['role'] === 'technicien' && (int)$installation['technicien_id'] !== (int)$user['id']) {
                http_response_code(403);
                exit('Cette intervention ne vous est pas attribuée.');
            }

            if ($method === 'POST') {
                $proof = Upload::image($_FILES['preuve'] ?? []);
                $data = [
                    'statut' => $_POST['statut'] ?? 'en_cours',
                    'date_prevue' => $_POST['date_prevue'] ?? '',
                    'numero_serie_box' => trim($_POST['numero_serie_box'] ?? ''),
                    'modele_box' => trim($_POST['modele_box'] ?? ''),
                    'puissance_signal' => trim($_POST['puissance_signal'] ?? ''),
                    'observations' => trim($_POST['observations'] ?? ''),
                    'preuve_installation' => $proof,
                ];

                $installations->update($id, $data);

                if ($data['statut'] === 'terminee') {
                    if ($user['role'] !== 'technicien') {
                        throw new RuntimeException(
                            'Seul le technicien affecté peut valider l’installation.'
                        );
                    }

                    $nd = $subscriptions->completeInstallation(
                        $id,
                        (int)$user['id']
                    );

                    flash('Installation validée. ND attribué : ' . $nd, true);
                } else {
                    flash('Intervention mise à jour.', true);
                }
                go('technician-dashboard');
            }

            layout('Intervention', '
                <section class="form-card"><h1>Compte rendu d’installation</h1>
                <form method="post" enctype="multipart/form-data">' . csrf() . '
                <label>Statut</label><select name="statut">
                    <option value="a_planifier">À planifier</option>
                    <option value="planifiee">Planifiée</option>
                    <option value="en_cours">En cours</option>
                    <option value="terminee">Terminée</option>
                    <option value="echec">Échec</option>
                </select>' .
                field('Date prévue', 'date_prevue', 'datetime-local') .
                field('Numéro de série de la box', 'numero_serie_box') .
                field('Modèle de box', 'modele_box') .
                field('Puissance du signal', 'puissance_signal') .
                '<label>Observations</label><textarea name="observations"></textarea>
                <label>Photo de preuve (JPEG, PNG ou WEBP, 5 Mo maximum)</label>
                <input type="file" name="preuve" accept="image/jpeg,image/png,image/webp">
                <button class="button">Enregistrer le compte rendu</button></form></section>
            ');
            break;

        case 'supervisor-dashboard':
            requireRole(['superviseur', 'admin']);
            $stats = $subscriptions->stats();
            $html = '<h1>Tableau de bord superviseur</h1><div class="grid">';

            foreach ($stats as $stat) {
                $html .= '<article class="card"><h3>' . e(statusLabel($stat['statut'])) . '</h3>';
                $html .= '<p class="price">' . (int)$stat['total'] . '</p></article>';
            }

            $html .= '</div><a class="button" href="?r=agent-dashboard">Voir les demandes</a>';
            $html .= '<a class="button" href="?r=all-installations">Voir les installations</a>';
            layout('Superviseur', $html);
            break;

        case 'all-installations':
            requireRole(['superviseur', 'admin']);
            $items = $installations->findAll();
            $html = '<h1>Toutes les installations</h1>';

            foreach ($items as $item) {
                $html .= '<article class="card"><h3>' . e($item['numero_demande']) . '</h3>';
                $html .= '<p>Client : ' . e($item['prenom'] . ' ' . $item['nom']) . '</p>';
                $html .= '<p>Technicien : ' . e($item['technicien_prenom'] . ' ' . $item['technicien_nom']) . '</p>';
                $html .= '<p>Statut : ' . e($item['statut']) . '</p></article>';
            }

            layout('Installations', $html);
            break;

        case 'admin-dashboard':
            requireRole(['admin']);
            $userCount = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $clientCount = (int)$db->query("SELECT COUNT(*) FROM clients")->fetchColumn();
            $offerCount = (int)$db->query("SELECT COUNT(*) FROM offres WHERE active = TRUE")->fetchColumn();
            $subscriptionCount = (int)$db->query("SELECT COUNT(*) FROM souscriptions")->fetchColumn();

            layout('Administration', '
                <h1>Administration Orange Fibre</h1>
                <div class="grid">
                    <article class="card"><h3>Utilisateurs</h3><p class="price">' . $userCount . '</p></article>
                    <article class="card"><h3>Clients</h3><p class="price">' . $clientCount . '</p></article>
                    <article class="card"><h3>Offres actives</h3><p class="price">' . $offerCount . '</p></article>
                    <article class="card"><h3>Souscriptions</h3><p class="price">' . $subscriptionCount . '</p></article>
                </div>
                <div class="grid">
                    <a class="card link-card" href="?r=admin-users"><h2>Utilisateurs</h2></a>
                    <a class="card link-card" href="?r=admin-offers"><h2>Offres</h2></a>
                    <a class="card link-card" href="?r=agent-dashboard"><h2>Souscriptions</h2></a>
                    <a class="card link-card" href="?r=admin-complaints"><h2>Réclamations</h2></a>
                    <a class="card link-card" href="?r=supervisor-dashboard"><h2>Statistiques</h2></a>
                </div>
            ');
            break;

        case 'admin-users':
            requireRole(['admin']);

            if ($method === 'POST') {
                $role = $_POST['role'] ?? '';
                $allowed = ['agent', 'technicien', 'superviseur', 'admin'];

                if (!in_array($role, $allowed, true)) {
                    throw new InvalidArgumentException('Rôle invalide.');
                }

                $password = $_POST['password'] ?? '';
                if (strlen($password) < 10) {
                    throw new InvalidArgumentException('Mot de passe trop court.');
                }

                $stmt = $db->prepare(
                    "INSERT INTO users (nom, prenom, email, telephone, password, role)
                     VALUES (:nom, :prenom, :email, :telephone, :password, :role)"
                );
                $stmt->execute([
                    'nom' => trim($_POST['nom'] ?? ''),
                    'prenom' => trim($_POST['prenom'] ?? ''),
                    'email' => strtolower(trim($_POST['email'] ?? '')),
                    'telephone' => trim($_POST['telephone'] ?? ''),
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                ]);

                flash('Utilisateur créé.', true);
                go('admin-users');
            }

            $items = $db->query(
                "SELECT id, nom, prenom, email, telephone, role, actif
                 FROM users ORDER BY id DESC"
            )->fetchAll();

            $html = '<h1>Gestion des utilisateurs</h1><section class="form-card">
                <h2>Créer un membre du personnel</h2><form method="post">' . csrf() .
                field('Nom', 'nom', 'text', true) .
                field('Prénom', 'prenom') .
                field('E-mail', 'email', 'email', true) .
                field('Téléphone', 'telephone') .
                field('Mot de passe', 'password', 'password', true) .
                '<label>Rôle</label><select name="role">
                    <option value="agent">Agent</option>
                    <option value="technicien">Technicien</option>
                    <option value="superviseur">Superviseur</option>
                    <option value="admin">Administrateur</option>
                </select><button class="button">Créer</button></form></section>';

            foreach ($items as $item) {
                $html .= '<article class="card"><strong>' . e($item['prenom'] . ' ' . $item['nom']) . '</strong>';
                $html .= '<p>' . e($item['email']) . ' · ' . e($item['role']) . '</p>';
                $html .= '<p>Compte : ' . ($item['actif'] ? 'Actif' : 'Désactivé') . '</p>';

                if ((int)$item['id'] !== (int)Security::currentUser()['id']) {
                    $html .= '<form method="post" action="?r=toggle-user">' . csrf() .
                        '<input type="hidden" name="id" value="' . (int)$item['id'] . '">' .
                        '<button class="button secondary">Activer / désactiver</button></form>';
                }

                $html .= '</article>';
            }

            layout('Utilisateurs', $html);
            break;

        case 'toggle-user':
            $admin = requireRole(['admin']);
            if ($method !== 'POST') go('admin-users');

            $id = (int)($_POST['id'] ?? 0);
            if ($id === (int)$admin['id']) {
                throw new RuntimeException('Vous ne pouvez pas désactiver votre propre compte.');
            }

            $stmt = $db->prepare(
                "UPDATE users SET actif = NOT actif,
                 updated_at = CURRENT_TIMESTAMP WHERE id = :id"
            );
            $stmt->execute(['id' => $id]);
            flash('État du compte modifié.', true);
            go('admin-users');

        case 'admin-offers':
            requireRole(['admin']);

            if ($method === 'POST') {
                $stmt = $db->prepare(
                    "INSERT INTO offres
                    (nom, description, debit_mbps, prix_mensuel,
                     frais_installation, active, avantages)
                    VALUES (:nom, :description, :debit, :price,
                            :installation, TRUE, CAST(:benefits AS jsonb))"
                );

                $benefits = array_filter(array_map('trim', explode("\n", $_POST['avantages'] ?? '')));

                $stmt->execute([
                    'nom' => trim($_POST['nom'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                    'debit' => (int)($_POST['debit_mbps'] ?? 0),
                    'price' => $_POST['prix_mensuel'] ?? 0,
                    'installation' => $_POST['frais_installation'] ?? 0,
                    'benefits' => json_encode(array_values($benefits), JSON_THROW_ON_ERROR),
                ]);

                flash('Offre créée.', true);
                go('admin-offers');
            }

            $items = $offers->getActiveOffers();
            $html = '<h1>Gestion des offres</h1><section class="form-card">
                <h2>Ajouter une offre</h2><form method="post">' . csrf() .
                field('Nom', 'nom', 'text', true) .
                '<label>Description</label><textarea name="description"></textarea>' .
                field('Débit (Mb/s)', 'debit_mbps', 'number', true) .
                field('Prix mensuel (FCFA)', 'prix_mensuel', 'number', true) .
                field('Frais d’installation (FCFA)', 'frais_installation', 'number') .
                '<label>Avantages (un par ligne)</label><textarea name="avantages"></textarea>
                <button class="button">Ajouter l’offre</button></form></section>';

            foreach ($items as $item) {
                $html .= '<article class="card"><h3>' . e($item['nom']) . '</h3>';
                $html .= '<p>' . (int)$item['debit_mbps'] . ' Mb/s · ' .
                    number_format((float)$item['prix_mensuel'], 0, ',', ' ') . ' FCFA</p>';
                $html .= '<form method="post" action="?r=toggle-offer">' . csrf() .
                    '<input type="hidden" name="id" value="' . (int)$item['id'] . '">' .
                    '<button class="button secondary">Désactiver</button></form></article>';
            }

            layout('Offres administrateur', $html);
            break;

        case 'toggle-offer':
            requireRole(['admin']);
            if ($method !== 'POST') go('admin-offers');

            $stmt = $db->prepare(
                "UPDATE offres SET active = NOT active WHERE id = :id"
            );
            $stmt->execute(['id' => (int)($_POST['id'] ?? 0)]);
            flash('État de l’offre modifié.', true);
            go('admin-offers');

        case 'admin-complaints':
            requireRole(['admin', 'superviseur']);
            $items = $complaints->all();
            $html = '<h1>Réclamations clients</h1>';

            foreach ($items as $item) {
                $html .= '<article class="card"><h3>' . e($item['sujet'] ?? 'Réclamation') . '</h3>';
                $html .= '<p>Client : ' . e($item['prenom'] . ' ' . $item['nom']) . '</p>';
                $html .= '<p>' . e($item['description'] ?? '') . '</p>';
                $html .= '<p>Statut : ' . e($item['statut'] ?? 'Enregistrée') . '</p></article>';
            }

            layout('Réclamations', $html);
            break;

        default:
            http_response_code(404);
            layout('Page introuvable', '<section class="card"><h1>404</h1><p>Page introuvable.</p><a href="?r=home">Retour à l’accueil</a></section>');
    }

} catch (Throwable $e) {
    error_log('[Orange Fibre] ' . $e->getMessage());
    http_response_code(500);
    layout('Erreur', '<section class="card"><h1>Erreur technique</h1><p>Une opération n’a pas pu aboutir. Consultez les journaux du serveur.</p></section>');
}
