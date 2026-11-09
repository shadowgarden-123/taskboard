<?php
// Fonctions utilitaires partagées par les pages et l'API

// Échappe une valeur pour l'affichage HTML
function e($valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES, 'UTF-8');
}

// Enregistre un message flash pour la prochaine page
function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

// Renvoie une réponse JSON puis arrête le script
function json_reponse(array $donnees, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE);
    exit;
}

// Récupère et supprime le message flash
function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $message = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $message;
    }
    return null;
}

// Tronque un texte proprement
function tronquer(?string $texte, int $max = 80): string
{
    $texte = trim(preg_replace('/\s+/u', ' ', (string) $texte));
    if (mb_strlen($texte) <= $max) {
        return $texte;
    }
    return mb_substr($texte, 0, $max - 1) . '…';
}

// Vérifie qu'une date au format Y-m-d est réelle
function date_valide(string $date): bool
{
    $jour = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $jour !== false && $jour->format('Y-m-d') === $date;
}

// Libellé lisible d'une priorité
function priorite_label(string $priorite): string
{
    $labels = ['basse' => 'Basse', 'moyenne' => 'Moyenne', 'haute' => 'Haute'];
    return $labels[$priorite] ?? 'Moyenne';
}

// Date relative en français + date exacte pour l'attribut title
function date_relative(?string $date, string $statut = 'a_faire'): array
{
    if (!$date) {
        return ['label' => 'Sans échéance', 'title' => '', 'late' => false, 'days' => null];
    }

    $jour = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$jour) {
        return ['label' => 'Date invalide', 'title' => '', 'late' => false, 'days' => null];
    }

    $aujourdhui = new DateTimeImmutable('today');
    $jours = (int) $aujourdhui->diff($jour)->format('%r%a');
    $enRetard = $statut === 'a_faire' && $jours < 0;

    if ($enRetard) {
        $label = 'En retard de ' . abs($jours) . ' j';
    } elseif ($jours === 0) {
        $label = "Aujourd'hui";
    } elseif ($jours === 1) {
        $label = 'Demain';
    } elseif ($jours === -1) {
        $label = 'Hier';
    } elseif ($jours > 1) {
        $label = 'Dans ' . $jours . ' j';
    } else {
        $label = 'Il y a ' . abs($jours) . ' j';
    }

    return [
        'label' => $label,
        'title' => $jour->format('d/m/Y'),
        'late' => $enRetard,
        'days' => $jours,
    ];
}

// Statistiques de l'utilisateur (toutes tâches confondues)
function get_stats(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(statut = 'terminee'), 0) AS terminees,
                COALESCE(SUM(statut = 'a_faire'), 0) AS a_faire,
                COALESCE(SUM(statut = 'a_faire' AND date_limite IS NOT NULL AND date_limite < CURDATE()), 0) AS en_retard
         FROM tasks
         WHERE user_id = ?"
    );
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();

    return [
        'total' => (int) ($stats['total'] ?? 0),
        'terminees' => (int) ($stats['terminees'] ?? 0),
        'a_faire' => (int) ($stats['a_faire'] ?? 0),
        'en_retard' => (int) ($stats['en_retard'] ?? 0),
    ];
}

// Nettoie et valide les filtres reçus en GET
function normaliser_filtres(array $source): array
{
    $filtres = [
        'q' => trim((string) ($source['q'] ?? '')),
        'categorie' => (string) ($source['categorie'] ?? ''),
        'priorite' => (string) ($source['priorite'] ?? ''),
        'statut' => (string) ($source['statut'] ?? ''),
        'tri' => (string) ($source['tri'] ?? 'date'),
    ];

    if (!ctype_digit($filtres['categorie']) || (int) $filtres['categorie'] <= 0) {
        $filtres['categorie'] = '';
    }
    if (!in_array($filtres['priorite'], ['basse', 'moyenne', 'haute'], true)) {
        $filtres['priorite'] = '';
    }
    if (!in_array($filtres['statut'], ['a_faire', 'terminee'], true)) {
        $filtres['statut'] = '';
    }
    if (!in_array($filtres['tri'], ['date', 'priorite', 'recent'], true)) {
        $filtres['tri'] = 'date';
    }
    if (mb_strlen($filtres['q']) > 100) {
        $filtres['q'] = mb_substr($filtres['q'], 0, 100);
    }

    return $filtres;
}

// Indique si au moins un filtre est actif
function filtres_actifs(array $filtres): bool
{
    return $filtres['q'] !== ''
        || $filtres['categorie'] !== ''
        || $filtres['priorite'] !== ''
        || $filtres['statut'] !== '';
}

// Récupère les tâches de l'utilisateur avec les filtres demandés
function get_tasks(PDO $pdo, int $userId, array $filtres): array
{
    $sql = "SELECT t.id, t.user_id, t.category_id, t.titre, t.description, t.date_limite,
                   t.priorite, t.statut, t.date_creation, c.nom AS categorie
            FROM tasks t
            LEFT JOIN categories c ON c.id = t.category_id AND c.user_id = t.user_id
            WHERE t.user_id = ?";
    $params = [$userId];

    if ($filtres['q'] !== '') {
        $like = '%' . addcslashes($filtres['q'], '%_\\') . '%';
        $sql .= " AND (t.titre LIKE ? OR t.description LIKE ?)";
        $params[] = $like;
        $params[] = $like;
    }

    if ($filtres['categorie'] !== '') {
        $sql .= " AND t.category_id = ?";
        $params[] = (int) $filtres['categorie'];
    }

    if ($filtres['priorite'] !== '') {
        $sql .= " AND t.priorite = ?";
        $params[] = $filtres['priorite'];
    }

    if ($filtres['statut'] !== '') {
        $sql .= " AND t.statut = ?";
        $params[] = $filtres['statut'];
    }

    if ($filtres['tri'] === 'priorite') {
        $sql .= " ORDER BY (t.statut = 'terminee') ASC,
                           FIELD(t.priorite, 'haute', 'moyenne', 'basse') ASC,
                           (t.date_limite IS NULL) ASC, t.date_limite ASC, t.date_creation DESC";
    } elseif ($filtres['tri'] === 'recent') {
        $sql .= " ORDER BY t.date_creation DESC";
    } else {
        $sql .= " ORDER BY (t.statut = 'terminee') ASC,
                           (t.date_limite IS NULL) ASC, t.date_limite ASC, t.date_creation DESC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $taches = $stmt->fetchAll();

    foreach ($taches as &$tache) {
        $tache['id'] = (int) $tache['id'];
        $tache['user_id'] = (int) $tache['user_id'];
        $tache['category_id'] = $tache['category_id'] !== null ? (int) $tache['category_id'] : null;
        $tache['description'] = (string) ($tache['description'] ?? '');
    }
    unset($tache);

    return $taches;
}

// Catégories de l'utilisateur avec le nombre de tâches
function get_categories(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT c.id, c.nom, COUNT(t.id) AS nb
         FROM categories c
         LEFT JOIN tasks t ON t.category_id = c.id AND t.user_id = c.user_id
         WHERE c.user_id = ?
         GROUP BY c.id, c.nom
         ORDER BY c.nom ASC"
    );
    $stmt->execute([$userId]);
    $categories = $stmt->fetchAll();

    foreach ($categories as &$categorie) {
        $categorie['id'] = (int) $categorie['id'];
        $categorie['nb'] = (int) $categorie['nb'];
    }
    unset($categorie);

    return $categories;
}

// Texte lu par les lecteurs d'écran pour les statistiques
function stat_sr(int $nombre, string $singulier, string $pluriel): string
{
    return $nombre . ' ' . ($nombre === 1 ? $singulier : $pluriel);
}

// État vide : aucune tâche ou aucun résultat de filtre
function etat_vide(bool $filtres): string
{
    if ($filtres) {
        return '<div class="empty">'
            . '<p class="empty__title">Rien ne correspond à cette recherche.</p>'
            . '<p class="empty__text">Essayez un autre mot-clé ou retirez un filtre.</p>'
            . '<a class="btn btn--secondary" href="index.php" data-clear-filters>Effacer les filtres</a>'
            . '</div>';
    }

    return '<div class="empty">'
        . '<svg class="empty__illus" viewBox="0 0 120 100" aria-hidden="true" focusable="false">'
        . '<path class="illus-brand" d="M22 34h56v30a16 16 0 0 1-16 16H38a16 16 0 0 1-16-16z" fill="none" stroke-width="1.5"/>'
        . '<path class="illus-brand" d="M78 40h8a10 10 0 0 1 0 20h-8" fill="none" stroke-width="1.5"/>'
        . '<path class="illus-sage" d="M34 24c0-5 4-5 4-10M48 24c0-5 4-5 4-10M62 24c0-5 4-5 4-10" fill="none" stroke-width="1.5" stroke-linecap="round"/>'
        . '<path class="illus-sage" d="M96 66l14-14 6 6-14 14-8 2z" fill="none" stroke-width="1.5" stroke-linejoin="round"/>'
        . '</svg>'
        . '<p class="empty__title">Aucune tâche pour l\'instant.</p>'
        . '<p class="empty__text">Notez ce que vous avez à faire, puis avancez une tâche à la fois.</p>'
        . '<a class="btn btn--primary" href="add_task.php">Ajouter ma première tâche</a>'
        . '</div>';
}
