<?php
require 'includes/auth.php';
require 'config/db.php';
require 'includes/functions.php';

$userId = (int) $_SESSION['user_id'];

// ------------------------------------------------------------
// Gestion des catégories (POST classique, sans AJAX)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_category') {
        $nom = trim((string) ($_POST['nom'] ?? ''));

        if ($nom === '') {
            flash('error', 'Le nom de la catégorie est obligatoire.');
        } elseif (mb_strlen($nom) > 100) {
            flash('error', 'Le nom de la catégorie ne doit pas dépasser 100 caractères.');
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE user_id = ? AND nom = ?');
            $stmt->execute([$userId, $nom]);

            if ((int) $stmt->fetchColumn() > 0) {
                flash('error', 'Cette catégorie existe déjà.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO categories (user_id, nom) VALUES (?, ?)');
                $stmt->execute([$userId, $nom]);
                flash('success', 'Catégorie « ' . $nom . ' » ajoutée.');
            }
        }
    } elseif ($action === 'delete_category') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

        if (!$id) {
            flash('error', 'Catégorie introuvable.');
        } else {
            $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);

            if ($stmt->rowCount() > 0) {
                flash('success', 'Catégorie supprimée. Les tâches concernées sont conservées.');
            } else {
                flash('error', 'Catégorie introuvable.');
            }
        }
    }

    header('Location: index.php#categories');
    exit;
}

// ------------------------------------------------------------
// Données de la page
// ------------------------------------------------------------
$filtres = normaliser_filtres($_GET);
$categories = get_categories($pdo, $userId);
$taches = get_tasks($pdo, $userId, $filtres);
$stats = get_stats($pdo, $userId);
$filtresActifs = filtres_actifs($filtres);

$nomsCategories = [];
foreach ($categories as $categorie) {
    $nomsCategories[$categorie['id']] = $categorie['nom'];
}

$chips = [];
if ($filtres['q'] !== '') {
    $chips[] = ['key' => 'q', 'label' => 'Recherche : « ' . $filtres['q'] . ' »'];
}
if ($filtres['categorie'] !== '' && isset($nomsCategories[(int) $filtres['categorie']])) {
    $chips[] = ['key' => 'categorie', 'label' => $nomsCategories[(int) $filtres['categorie']]];
}
if ($filtres['priorite'] !== '') {
    $chips[] = ['key' => 'priorite', 'label' => 'Priorité ' . priorite_label($filtres['priorite'])];
}
if ($filtres['statut'] !== '') {
    $chips[] = ['key' => 'statut', 'label' => $filtres['statut'] === 'a_faire' ? 'À faire' : 'Terminées'];
}

// Carte de tâche (le même rendu est reconstruit côté JS)
function render_task(array $tache): string
{
    $date = date_relative($tache['date_limite'], $tache['statut']);
    $terminee = $tache['statut'] === 'terminee';
    $classes = 'task' . ($terminee ? ' is-done' : '') . ($date['late'] ? ' task--late' : '');
    $titre = e($tache['titre']);
    $description = tronquer($tache['description'], 80);

    ob_start();
    ?>
    <li class="task-item">
        <div class="task-item__inner">
            <article class="<?= $classes ?>" data-id="<?= (int) $tache['id'] ?>"
                     data-priorite="<?= e($tache['priorite']) ?>" data-statut="<?= e($tache['statut']) ?>"
                     data-late="<?= $date['late'] ? '1' : '0' ?>">
                <label class="check">
                    <input class="check__input" type="checkbox" <?= $terminee ? 'checked' : '' ?>
                           aria-label="<?= $terminee ? 'Rouvrir' : 'Terminer' ?> : <?= $titre ?>">
                    <span class="check__box">
                        <svg class="check__mark" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                            <path d="M5 10.5l3.2 3.2L15 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </label>

                <div class="task__body">
                    <h3 class="task__title" title="<?= $titre ?>"><?= $titre ?></h3>
                    <?php if ($description !== ''): ?>
                    <p class="task__desc"><?= e($description) ?></p>
                    <?php endif; ?>

                    <div class="task__meta">
                        <span class="task__cat"><?= $tache['categorie'] ? e($tache['categorie']) : 'Sans catégorie' ?></span>
                        <span class="task__sep" aria-hidden="true">·</span>
                        <?php if ($tache['date_limite']): ?>
                        <time class="task__date<?= $date['late'] ? ' task__date--late' : '' ?>"
                              datetime="<?= e($tache['date_limite']) ?>" title="<?= e($date['title']) ?>">
                            <?php if ($date['late']): ?><span aria-hidden="true">●</span> <?php endif; ?><?= e($date['label']) ?>
                        </time>
                        <?php else: ?>
                        <span class="task__date">Sans échéance</span>
                        <?php endif; ?>
                        <span class="badge badge--<?= e($tache['priorite']) ?>"><?= e(priorite_label($tache['priorite'])) ?></span>
                    </div>
                </div>

                <div class="task__actions">
                    <a class="icon-btn" href="edit_task.php?id=<?= (int) $tache['id'] ?>" aria-label="Modifier : <?= $titre ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M4 20h4L18.5 9.5l-4-4L4 16v4Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                            <path d="m13.5 6.5 4 4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                    </a>
                    <button class="icon-btn icon-btn--danger" type="button"
                            data-delete="<?= (int) $tache['id'] ?>" data-title="<?= $titre ?>"
                            aria-label="Supprimer : <?= $titre ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </article>
        </div>
    </li>
    <?php
    return (string) ob_get_clean();
}

$page_title = 'Mes tâches';
$page_active = 'tasks';
require 'includes/header.php';
?>
<main id="contenu" class="container">
    <section aria-labelledby="titre-page">
        <div class="page-head">
            <div class="page-head__row">
                <div>
                    <p class="micro-label">Tableau de bord</p>
                    <h2 id="titre-page">Mes <em class="mark">tâches</em></h2>
                </div>
                <a class="btn btn--primary" href="add_task.php">
                    <svg class="icon" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                        <path d="M10 4v12M4 10h12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    Ajouter une tâche
                </a>
            </div>
            <p class="page-head__sub">Une liste courte, des priorités claires, et le plaisir de cocher.</p>
        </div>

        <section class="stats" aria-label="Statistiques des tâches">
            <div class="stat">
                <span class="stat__value" data-stat-value="terminees" data-count="<?= $stats['terminees'] ?>" aria-hidden="true"><?= $stats['terminees'] ?></span>
                <span class="sr-only" data-stat-sr="terminees"><?= e(stat_sr($stats['terminees'], 'tâche terminée', 'tâches terminées')) ?></span>
                <span class="stat__label">Terminées</span>
            </div>
            <div class="stat">
                <span class="stat__value" data-stat-value="a_faire" data-count="<?= $stats['a_faire'] ?>" aria-hidden="true"><?= $stats['a_faire'] ?></span>
                <span class="sr-only" data-stat-sr="a_faire"><?= e(stat_sr($stats['a_faire'], 'tâche à faire', 'tâches à faire')) ?></span>
                <span class="stat__label">À faire</span>
            </div>
            <div class="stat">
                <span class="stat__value" data-stat-value="en_retard" data-count="<?= $stats['en_retard'] ?>" aria-hidden="true"><?= $stats['en_retard'] ?></span>
                <span class="sr-only" data-stat-sr="en_retard"><?= e(stat_sr($stats['en_retard'], 'tâche en retard', 'tâches en retard')) ?></span>
                <span class="stat__label">En retard</span>
            </div>
        </section>

        <form class="filters" id="filters-form" method="get" action="index.php" aria-label="Filtrer les tâches">
            <div class="search">
                <svg class="search__icon" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                    <circle cx="8.5" cy="8.5" r="5.5" fill="none" stroke="currentColor" stroke-width="1.6"/>
                    <path d="m13 13 4 4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                <label class="sr-only" for="search">Rechercher une tâche</label>
                <input class="search__input" type="search" id="search" name="q" value="<?= e($filtres['q']) ?>"
                       placeholder="Rechercher une tâche…" autocomplete="off">
            </div>

            <div class="filters__row">
                <div class="select">
                    <label class="sr-only" for="filtre-categorie">Catégorie</label>
                    <select id="filtre-categorie" name="categorie">
                        <option value="">Toutes les catégories</option>
                        <?php foreach ($categories as $categorie): ?>
                        <option value="<?= $categorie['id'] ?>"<?= $filtres['categorie'] === (string) $categorie['id'] ? ' selected' : '' ?>><?= e($categorie['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="select">
                    <label class="sr-only" for="filtre-priorite">Priorité</label>
                    <select id="filtre-priorite" name="priorite">
                        <option value="">Toutes les priorités</option>
                        <option value="basse"<?= $filtres['priorite'] === 'basse' ? ' selected' : '' ?>>Basse</option>
                        <option value="moyenne"<?= $filtres['priorite'] === 'moyenne' ? ' selected' : '' ?>>Moyenne</option>
                        <option value="haute"<?= $filtres['priorite'] === 'haute' ? ' selected' : '' ?>>Haute</option>
                    </select>
                </div>

                <div class="select">
                    <label class="sr-only" for="filtre-statut">Statut</label>
                    <select id="filtre-statut" name="statut">
                        <option value="">Tous les statuts</option>
                        <option value="a_faire"<?= $filtres['statut'] === 'a_faire' ? ' selected' : '' ?>>À faire</option>
                        <option value="terminee"<?= $filtres['statut'] === 'terminee' ? ' selected' : '' ?>>Terminées</option>
                    </select>
                </div>

                <div class="select">
                    <label class="sr-only" for="filtre-tri">Trier par</label>
                    <select id="filtre-tri" name="tri">
                        <option value="date"<?= $filtres['tri'] === 'date' ? ' selected' : '' ?>>Par échéance</option>
                        <option value="priorite"<?= $filtres['tri'] === 'priorite' ? ' selected' : '' ?>>Par priorité</option>
                        <option value="recent"<?= $filtres['tri'] === 'recent' ? ' selected' : '' ?>>Plus récentes</option>
                    </select>
                </div>
            </div>

            <div class="chips" id="active-filters" role="group" aria-label="Filtres actifs"<?= $chips ? '' : ' hidden' ?>>
                <?php foreach ($chips as $chip): ?>
                <span class="chip">
                    <?= e($chip['label']) ?>
                    <button class="chip__remove" type="button" data-filter="<?= e($chip['key']) ?>"
                            aria-label="Retirer le filtre <?= e($chip['label']) ?>">
                        <svg viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                            <path d="M4 4l8 8M12 4l-8 8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                    </button>
                </span>
                <?php endforeach; ?>
            </div>
        </form>

        <div class="list-head">
            <p class="micro-label">Mes tâches · <span id="task-count" aria-live="polite"><?= count($taches) ?></span></p>
        </div>

        <div id="task-region">
            <?php if ($taches): ?>
            <ul class="task-list">
                <?php foreach ($taches as $tache): ?>
                <?= render_task($tache) ?>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <?= etat_vide($filtresActifs) ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="categories" id="categories" aria-labelledby="titre-categories">
        <div class="section-head">
            <p class="micro-label">Organisation</p>
            <h2 id="titre-categories">Catégories</h2>
        </div>

        <div class="categories__box">
            <?php if ($categories): ?>
            <ul class="cat-list">
                <?php foreach ($categories as $categorie): ?>
                <li class="cat-item">
                    <span class="cat-item__name"><?= e($categorie['nom']) ?></span>
                    <span class="cat-item__count"><?= $categorie['nb'] ?> tâche<?= $categorie['nb'] === 1 ? '' : 's' ?></span>
                    <form class="cat-item__form" method="post" action="index.php#categories"
                          data-confirm="Supprimer la catégorie « <?= e($categorie['nom']) ?> » ? Les tâches concernées seront conservées, sans catégorie.">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="id" value="<?= $categorie['id'] ?>">
                        <button class="icon-btn icon-btn--danger" type="submit" aria-label="Supprimer la catégorie <?= e($categorie['nom']) ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="categories__empty">Aucune catégorie pour l’instant. Ajoutez-en une ci-dessous.</p>
            <?php endif; ?>

            <form class="cat-add" method="post" action="index.php#categories">
                <input type="hidden" name="action" value="add_category">
                <div class="field field--inline">
                    <label class="field__label" for="nom-categorie">Nouvelle catégorie</label>
                    <div class="field__control">
                        <input class="field__input" type="text" id="nom-categorie" name="nom" maxlength="100"
                               placeholder="Ex. Lectures" required>
                    </div>
                </div>
                <button class="btn btn--secondary" type="submit">Ajouter</button>
            </form>
        </div>
    </section>
</main>
<?php require 'includes/footer.php'; ?>
