<?php
require 'includes/auth.php';
require 'config/db.php';
require 'includes/functions.php';

$userId = (int) $_SESSION['user_id'];
$id = filter_var($_POST['id'] ?? $_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    flash('error', 'Tâche introuvable.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $userId]);
$tache = $stmt->fetch();

if (!$tache) {
    flash('error', 'Tâche introuvable ou accès refusé.');
    header('Location: index.php');
    exit;
}

$categories = get_categories($pdo, $userId);
$valeurs = [
    'titre' => $tache['titre'],
    'description' => (string) ($tache['description'] ?? ''),
    'date_limite' => (string) ($tache['date_limite'] ?? ''),
    'priorite' => $tache['priorite'],
    'category_id' => $tache['category_id'] !== null ? (string) $tache['category_id'] : '',
];
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valeurs = [
        'titre' => trim((string) ($_POST['titre'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'date_limite' => trim((string) ($_POST['date_limite'] ?? '')),
        'priorite' => (string) ($_POST['priorite'] ?? 'moyenne'),
        'category_id' => (string) ($_POST['category_id'] ?? ''),
    ];

    if ($valeurs['titre'] === '') {
        $erreurs['titre'] = 'Le titre est obligatoire.';
    } elseif (mb_strlen($valeurs['titre']) > 200) {
        $erreurs['titre'] = 'Le titre ne doit pas dépasser 200 caractères.';
    }

    if (mb_strlen($valeurs['description']) > 5000) {
        $erreurs['description'] = 'La description est trop longue (5000 caractères maximum).';
    }

    if ($valeurs['date_limite'] !== '' && !date_valide($valeurs['date_limite'])) {
        $erreurs['date_limite'] = 'La date limite n’est pas valide.';
    }

    if (!in_array($valeurs['priorite'], ['basse', 'moyenne', 'haute'], true)) {
        $erreurs['priorite'] = 'Choisissez une priorité valide.';
    }

    if ($valeurs['category_id'] !== '') {
        $idCategorie = filter_var($valeurs['category_id'], FILTER_VALIDATE_INT);
        if (!$idCategorie) {
            $erreurs['category_id'] = 'Choisissez une catégorie valide.';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id = ? AND user_id = ?');
            $stmt->execute([$idCategorie, $userId]);
            if ((int) $stmt->fetchColumn() === 0) {
                $erreurs['category_id'] = 'Cette catégorie ne vous appartient pas.';
            }
        }
    }

    if (!$erreurs) {
        $stmt = $pdo->prepare(
            'UPDATE tasks
             SET titre = ?, description = ?, date_limite = ?, priorite = ?, category_id = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $valeurs['titre'],
            $valeurs['description'] !== '' ? $valeurs['description'] : null,
            $valeurs['date_limite'] !== '' ? $valeurs['date_limite'] : null,
            $valeurs['priorite'],
            $valeurs['category_id'] !== '' ? (int) $valeurs['category_id'] : null,
            $id,
            $userId,
        ]);

        flash('success', 'Tâche mise à jour.');
        header('Location: index.php');
        exit;
    }
}

$page_title = 'Modifier la tâche';
$page_active = 'tasks';
require 'includes/header.php';
?>
<main id="contenu" class="container">
    <div class="page-head">
        <p class="micro-label">Édition</p>
        <h2>Modifier la <em class="mark">tâche</em></h2>
        <p class="page-head__sub">Ajustez ce qui doit l’être, puis revenez à la liste.</p>
    </div>

    <form class="form-card" method="post" action="edit_task.php?id=<?= (int) $id ?>" novalidate>
        <input type="hidden" name="id" value="<?= (int) $id ?>">

        <div class="field<?= isset($erreurs['titre']) ? ' field--error' : '' ?>">
            <label class="field__label" for="titre">Titre</label>
            <div class="field__control">
                <input class="field__input" type="text" id="titre" name="titre" value="<?= e($valeurs['titre']) ?>"
                       maxlength="200" required
                       <?= isset($erreurs['titre']) ? 'aria-invalid="true" aria-describedby="err-titre"' : '' ?>>
            </div>
            <?php if (isset($erreurs['titre'])): ?>
            <p class="field__error" id="err-titre">
                <svg class="icon icon--sm" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10 6v5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="10" cy="14" r=".9" fill="currentColor"/></svg>
                <?= e($erreurs['titre']) ?>
            </p>
            <?php endif; ?>
        </div>

        <div class="field<?= isset($erreurs['description']) ? ' field--error' : '' ?>">
            <label class="field__label" for="description">Description <span class="field__optional">(facultative)</span></label>
            <div class="field__control">
                <textarea class="field__input field__textarea" id="description" name="description" rows="4"
                          <?= isset($erreurs['description']) ? 'aria-invalid="true" aria-describedby="err-description"' : '' ?>><?= e($valeurs['description']) ?></textarea>
            </div>
            <?php if (isset($erreurs['description'])): ?>
            <p class="field__error" id="err-description">
                <svg class="icon icon--sm" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10 6v5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="10" cy="14" r=".9" fill="currentColor"/></svg>
                <?= e($erreurs['description']) ?>
            </p>
            <?php endif; ?>
        </div>

        <div class="form-grid">
            <div class="field<?= isset($erreurs['date_limite']) ? ' field--error' : '' ?>">
                <label class="field__label" for="date_limite">Date limite <span class="field__optional">(facultative)</span></label>
                <div class="field__control">
                    <input class="field__input" type="date" id="date_limite" name="date_limite" value="<?= e($valeurs['date_limite']) ?>"
                           <?= isset($erreurs['date_limite']) ? 'aria-invalid="true" aria-describedby="err-date"' : '' ?>>
                </div>
                <?php if (isset($erreurs['date_limite'])): ?>
                <p class="field__error" id="err-date">
                    <svg class="icon icon--sm" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10 6v5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="10" cy="14" r=".9" fill="currentColor"/></svg>
                    <?= e($erreurs['date_limite']) ?>
                </p>
                <?php endif; ?>
            </div>

            <div class="field<?= isset($erreurs['priorite']) ? ' field--error' : '' ?>">
                <label class="field__label" for="priorite">Priorité</label>
                <div class="field__control field__control--select">
                    <select id="priorite" name="priorite">
                        <option value="basse"<?= $valeurs['priorite'] === 'basse' ? ' selected' : '' ?>>Basse</option>
                        <option value="moyenne"<?= $valeurs['priorite'] === 'moyenne' ? ' selected' : '' ?>>Moyenne</option>
                        <option value="haute"<?= $valeurs['priorite'] === 'haute' ? ' selected' : '' ?>>Haute</option>
                    </select>
                </div>
                <?php if (isset($erreurs['priorite'])): ?>
                <p class="field__error" id="err-priorite">
                    <svg class="icon icon--sm" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10 6v5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="10" cy="14" r=".9" fill="currentColor"/></svg>
                    <?= e($erreurs['priorite']) ?>
                </p>
                <?php endif; ?>
            </div>

            <div class="field<?= isset($erreurs['category_id']) ? ' field--error' : '' ?>">
                <label class="field__label" for="category_id">Catégorie</label>
                <div class="field__control field__control--select">
                    <select id="category_id" name="category_id"
                            <?= isset($erreurs['category_id']) ? 'aria-invalid="true" aria-describedby="err-categorie"' : '' ?>>
                        <option value="">Sans catégorie</option>
                        <?php foreach ($categories as $categorie): ?>
                        <option value="<?= $categorie['id'] ?>"<?= $valeurs['category_id'] === (string) $categorie['id'] ? ' selected' : '' ?>><?= e($categorie['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (isset($erreurs['category_id'])): ?>
                <p class="field__error" id="err-categorie">
                    <svg class="icon icon--sm" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10 6v5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="10" cy="14" r=".9" fill="currentColor"/></svg>
                    <?= e($erreurs['category_id']) ?>
                </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn--secondary" href="index.php">Annuler</a>
            <button class="btn btn--primary" type="submit">Enregistrer les modifications</button>
        </div>
    </form>
</main>
<?php require 'includes/footer.php'; ?>
