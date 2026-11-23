
<?php
$e = static fn($value) => htmlspecialchars(
    (string) $value,
    ENT_QUOTES,
    'UTF-8'
);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Découvrez les offres Internet Fibre Orange Fibre.">
    <title>Orange Fibre | Internet haut débit</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<header class="header">
    <div class="container navbar">
        <a href="/" class="logo">
            <span class="logo-symbol">O</span>
            <span>orange<span class="logo-light"> fibre</span></span>
        </a>

        <button class="menu-toggle" id="menuToggle"
                aria-label="Ouvrir le menu" aria-expanded="false">
            ☰
        </button>

        <nav class="nav-links" id="navLinks">
            <a href="#accueil">Accueil</a>
            <a href="#offres">Nos offres</a>
            <a href="#avantages">Nos avantages</a>
            <a href="#contact">Contact</a>
            <a href="#offres" class="nav-button">Découvrir les offres</a>
        </nav>
    </div>
</header>

<main>

<section class="hero" id="accueil">
    <div class="container hero-content">
        <div class="hero-text">
            <span class="eyebrow">INTERNET HAUT DÉBIT</span>

            <h1>Une connexion rapide pour <span>voir plus grand.</span></h1>

            <p>
                Découvrez une expérience Internet pensée pour vos
                divertissements, votre travail et toute votre famille.
            </p>

            <div class="hero-actions">
                <a href="#offres" class="btn btn-orange">
                    Découvrir les offres
                </a>
                <a href="#avantages" class="btn btn-outline">
                    En savoir plus
                </a>
            </div>

            <div class="hero-note">
                <span class="status-dot"></span>
                Découvrez nos solutions Internet Fibre
            </div>
        </div>

        <div class="hero-visual">
            <div class="visual-circle"></div>
            <div class="speed-card">
                <span class="speed-icon">⌁</span>
                <span class="speed-title">Internet Fibre</span>
                <strong>Connecté</strong>
                <small>À la maison et au quotidien</small>
            </div>
            <div class="floating-tag">La puissance du haut débit</div>
        </div>
    </div>
</section>

<section class="trust-bar">
    <div class="container trust-content">
        <div><strong>01</strong><span>Découvrez nos offres</span></div>
        <div><strong>02</strong><span>Choisissez votre formule</span></div>
        <div><strong>03</strong><span>Faites votre demande</span></div>
    </div>
</section>

<section class="section offers-section" id="offres">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">NOS SOLUTIONS</span>
            <h2>Choisissez votre <span>offre Fibre</span></h2>
            <p>Comparez les formules disponibles et trouvez celle qui vous convient.</p>
        </div>

        <?php if ($erreur): ?>
            <div class="notice">
                Les offres ne sont pas encore disponibles.
                Vérifiez la configuration de la base de données.
            </div>
        <?php elseif (empty($offres)): ?>
            <div class="empty-state">
                <div class="empty-icon">◎</div>
                <h3>Nos offres arrivent bientôt</h3>
                <p>Notre catalogue sera affiché ici dès que les offres auront été ajoutées.</p>
            </div>
        <?php else: ?>
            <div class="offers-grid">
                <?php foreach ($offres as $offre): ?>
                    <article class="offer-card">
                        <span class="offer-label">OFFRE FIBRE</span>
                        <h3><?= $e($offre['nom']) ?></h3>

                        <div class="offer-speed">
                            <?= $e($offre['debit_mbps']) ?>
                            <span>Mbps</span>
                        </div>

                        <p><?= $e($offre['description'] ?? '') ?></p>

                        <div class="offer-price">
                            <strong><?= number_format((float)$offre['prix_mensuel'], 0, ',', ' ') ?></strong>
                            <span>FCFA / mois</span>
                        </div>

                        <p class="installation">
                            Installation :
                            <?= number_format((float)$offre['frais_installation'], 0, ',', ' ') ?>
                            FCFA
                        </p>

                        <a href="#contact" class="btn btn-orange btn-full">
                            Demander cette offre
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section advantages-section" id="avantages">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">POURQUOI LA FIBRE ?</span>
            <h2>Une meilleure expérience <span>Internet</span></h2>
            <p>Les avantages d'une connexion adaptée à vos usages.</p>
        </div>

        <div class="advantages-grid">
            <article class="advantage-card">
                <div class="advantage-icon">↗</div>
                <h3>Rapidité</h3>
                <p>Profitez d'une connexion haut débit pour vos activités numériques.</p>
            </article>

            <article class="advantage-card">
                <div class="advantage-icon">⌂</div>
                <h3>Pour toute la famille</h3>
                <p>Connectez plusieurs appareils et partagez vos moments en ligne.</p>
            </article>

            <article class="advantage-card">
                <div class="advantage-icon">▣</div>
                <h3>Divertissement</h3>
                <p>Regardez vos contenus et profitez de vos loisirs numériques.</p>
            </article>
        </div>
    </div>
</section>

<section class="contact-section" id="contact">
    <div class="container contact-content">
        <div>
            <span class="eyebrow">BESOIN D'INFORMATIONS ?</span>
            <h2>Votre prochaine connexion commence ici.</h2>
            <p>Découvrez nos offres et préparez votre demande de souscription.</p>
        </div>
        <a href="#offres" class="btn btn-white">Voir les offres</a>
    </div>
</section>

</main>

<footer class="footer">
    <div class="container footer-content">
        <a href="/" class="logo footer-logo">
            <span class="logo-symbol">O</span>
            <span>orange fibre</span>
        </a>
        <p>Projet de démonstration indépendant, non affilié à Orange Côte d'Ivoire.</p>
        <span>© <?= date('Y') ?> Orange Fibre — Projet pédagogique.</span>
    </div>
</footer>

<script src="/assets/js/app.js"></script>
</body>
</html>
