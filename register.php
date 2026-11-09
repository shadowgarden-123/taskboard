<?php
session_start();
require 'config/db.php';
require 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$nom = '';
$email = '';
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $motDePasse = $_POST['mot_de_passe'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    if ($nom === '') {
        $erreurs['nom'] = 'Renseignez votre nom.';
    } elseif (mb_strlen($nom) > 100) {
        $erreurs['nom'] = 'Le nom ne doit pas dépasser 100 caractères.';
    }

    if ($email === '') {
        $erreurs['email'] = 'Renseignez votre adresse email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs['email'] = 'Cette adresse email n’est pas valide.';
    }

    if ($motDePasse === '') {
        $erreurs['mot_de_passe'] = 'Choisissez un mot de passe.';
    } elseif (mb_strlen($motDePasse) < 6) {
        $erreurs['mot_de_passe'] = 'Le mot de passe doit contenir au moins 6 caractères.';
    }

    if ($confirmation !== $motDePasse) {
        $erreurs['confirmation'] = 'Les deux mots de passe ne correspondent pas.';
    }

    if (!$erreurs) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$email]);

        if ((int) $stmt->fetchColumn() > 0) {
            $erreurs['email'] = 'Un compte existe déjà avec cette adresse email.';
        }
    }

    if (!$erreurs) {
        $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (nom, email, mot_de_passe) VALUES (?, ?, ?)');
        $stmt->execute([$nom, $email, $hash]);

        flash('success', 'Votre compte a été créé. Vous pouvez maintenant vous connecter.');
        header('Location: login.php');
        exit;
    }
}

$page_title = 'Inscription';
require 'includes/header.php';
?>
<main id="contenu" class="container auth">
    <section class="auth__panel" aria-labelledby="titre-inscription">
        <p class="micro-label">TaskBoard</p>
        <h2 id="titre-inscription">Créer un <em class="mark">compte</em></h2>
        <p class="auth__intro">Quelques champs, et vos tâches trouvent enfin une maison.</p>

        <form class="form" method="post" action="register.php" novalidate>
            <div class="field<?= isset($erreurs['nom']) ? ' field--error' : '' ?>">
                <label class="field__label" for="nom">Nom</label>
                <div class="field__control">
                    <input class="field__input" type="text" id="nom" name="nom" value="<?= e($nom) ?>"
                           autocomplete="name" maxlength="100" required
                           <?= isset($erreurs['nom']) ? 'aria-invalid="true" aria-describedby="err-nom"' : '' ?>>
                </div>
                <?php if (isset($erreurs['nom'])): ?>
                <p class="field__error" id="err-nom">
                    <svg class="icon icon--sm" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10 6v5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="10" cy="14" r=".9" fill="currentColor"/></svg>
                    <?= e($erreurs['nom']) ?>
                </p>
                <?php endif; ?>
            </div>

            <div class="field<?= isset($erreurs['email']) ? ' field--error' : '' ?>">
                <label class="field__label" for="email">Email</label>
                <div class="field__control">
                    <input class="field__input" type="email" id="email" name="email" value="<?= e($email) ?>"
                           autocomplete="email" inputmode="email" required
                           <?= isset($erreurs['email']) ? 'aria-invalid="true" aria-describedby="err-email"' : '' ?>>
                </div>
                <?php if (isset($erreurs['email'])): ?>
                <p class="field__error" id="err-email">
                    <svg class="icon icon--sm" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10 6v5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="10" cy="14" r=".9" fill="currentColor"/></svg>
                    <?= e($erreurs['email']) ?>
                </p>
                <?php endif; ?>
            </div>

            <div class="field<?= isset($erreurs['mot_de_passe']) ? ' field--error' : '' ?>">
                <label class="field__label" for="mot_de_passe">Mot de passe</label>
                <div class="field__control">
                    <input class="field__input" type="password" id="mot_de_passe" name="mot_de_passe"
                           autocomplete="new-password" minlength="6" required
                           <?= isset($erreurs['mot_de_passe']) ? 'aria-invalid="true" aria-describedby="err-mot-de-passe"' : '' ?>>
                </div>
                <?php if (isset($erreurs['mot_de_passe'])): ?>
                <p class="field__error" id="err-mot-de-passe">
                    <svg class="icon icon--sm" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10 6v5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="10" cy="14" r=".9" fill="currentColor"/></svg>
                    <?= e($erreurs['mot_de_passe']) ?>
                </p>
                <?php endif; ?>
                <p class="field__hint">6 caractères minimum.</p>
            </div>

            <div class="field<?= isset($erreurs['confirmation']) ? ' field--error' : '' ?>">
                <label class="field__label" for="confirmation">Confirmation du mot de passe</label>
                <div class="field__control">
                    <input class="field__input" type="password" id="confirmation" name="confirmation"
                           autocomplete="new-password" minlength="6" required
                           <?= isset($erreurs['confirmation']) ? 'aria-invalid="true" aria-describedby="err-confirmation"' : '' ?>>
                </div>
                <?php if (isset($erreurs['confirmation'])): ?>
                <p class="field__error" id="err-confirmation">
                    <svg class="icon icon--sm" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10 6v5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="10" cy="14" r=".9" fill="currentColor"/></svg>
                    <?= e($erreurs['confirmation']) ?>
                </p>
                <?php endif; ?>
            </div>

            <button class="btn btn--primary btn--block" type="submit">Créer mon compte</button>
        </form>

        <p class="auth__switch">Déjà un compte ? <a href="login.php">Se connecter</a></p>
    </section>

    <aside class="auth__aside">
        <svg class="auth__illus" viewBox="0 0 360 420" aria-hidden="true" focusable="false">
            <circle class="illus-brand" cx="180" cy="196" r="140" fill="none" stroke-width="1.5"/>
            <circle class="illus-sage" cx="180" cy="196" r="104" fill="none" stroke-width="1.5"/>
            <path class="illus-brand" d="M46 320c74-64 194-64 268 0" fill="none" stroke-width="1.5" stroke-linecap="round"/>
            <path class="illus-sage" d="M76 118a110 110 0 0 1 208 0" fill="none" stroke-width="1.5" stroke-linecap="round"/>
            <path class="illus-sage" d="M96 250c28-34 62-52 84-52s56 18 84 52" fill="none" stroke-width="1.5" stroke-linecap="round"/>
            <circle class="illus-fill-brand" cx="180" cy="196" r="7"/>
            <circle class="illus-fill-sage" cx="116" cy="196" r="4"/>
            <circle class="illus-fill-sage" cx="244" cy="196" r="4"/>
            <path class="illus-brand" d="M180 56v-16M180 352v-16M40 196H24M336 196h-16" fill="none" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <blockquote class="auth__quote">« Une tâche à la fois. »</blockquote>
        <p class="auth__aside-note">Le calme avant la liste.</p>
    </aside>
</main>
<?php require 'includes/footer.php'; ?>
