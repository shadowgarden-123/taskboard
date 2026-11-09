<?php
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = $page_title ?? 'Mes tâches';
$page_active = $page_active ?? '';
$connecte = isset($_SESSION['user_id']);
$nomUtilisateur = (string) ($_SESSION['nom'] ?? '');
$initiale = $connecte && $nomUtilisateur !== ''
    ? mb_strtoupper(mb_substr($nomUtilisateur, 0, 1))
    : '?';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TaskBoard — <?= e($page_title) ?></title>
    <meta name="description" content="TaskBoard, un gestionnaire de tâches simple, lisible et accessible.">
    <meta name="theme-color" id="meta-theme-color" content="#FAF7F2">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%23FAF7F2'/%3E%3Ctext x='32' y='47' text-anchor='middle' font-family='Georgia,serif' font-style='italic' font-size='44' fill='%23C8553D'%3ET%3C/text%3E%3C/svg%3E">

    <script>
    // Applique le thème avant le premier rendu (évite le flash)
    (function () {
        var t = null;
        try { t = localStorage.getItem('theme'); } catch (e) {}
        if (!t) t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.dataset.theme = t;
    })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,500..700,0..100;1,9..144,500..700,0..100&amp;family=IBM+Plex+Sans:wght@400;500;600&amp;family=IBM+Plex+Mono:wght@400;500&amp;display=swap">
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
<a class="skip-link" href="#contenu">Aller au contenu</a>
<div id="header-sentinel" aria-hidden="true"></div>

<header class="site-header" id="site-header">
    <div class="header__inner">
        <h1 class="logo"><a class="logo__link" href="<?= $connecte ? 'index.php' : 'login.php' ?>"><span class="logo__t">T</span>askBoard</a></h1>

        <?php if ($connecte): ?>
        <nav class="nav" aria-label="Principale">
            <a class="nav__link" href="index.php"<?= $page_active === 'tasks' ? ' aria-current="page"' : '' ?>>Mes tâches</a>
            <a class="nav__link" href="index.php#categories"<?= $page_active === 'categories' ? ' aria-current="page"' : '' ?>>Catégories</a>
        </nav>
        <?php endif; ?>

        <div class="header__actions">
            <button class="icon-btn theme-toggle" id="theme-toggle" type="button" aria-label="Passer en mode sombre">
                <svg class="icon icon--moon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <svg class="icon icon--sun" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </button>

            <?php if ($connecte): ?>
            <span class="avatar" aria-hidden="true"><?= e($initiale) ?></span>
            <a class="nav__link nav__link--logout" href="logout.php">Déconnexion</a>
            <?php endif; ?>
        </div>
    </div>
</header>
