<?php
session_start();
require '../config/db.php';
require '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    json_reponse(['success' => false, 'message' => 'Non connecté'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_reponse(['success' => false, 'message' => 'Méthode non autorisée'], 405);
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    json_reponse(['success' => false, 'message' => 'Identifiant invalide'], 422);
}

$userId = (int) $_SESSION['user_id'];

try {
    // Vérifie que la tâche appartient à l'utilisateur
    $stmt = $pdo->prepare('SELECT statut FROM tasks WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    $tache = $stmt->fetch();

    if (!$tache) {
        json_reponse(['success' => false, 'message' => 'Tâche introuvable'], 404);
    }

    $stmt = $pdo->prepare(
        "UPDATE tasks
         SET statut = IF(statut = 'a_faire', 'terminee', 'a_faire')
         WHERE id = ? AND user_id = ?"
    );
    $stmt->execute([$id, $userId]);

    $stmt = $pdo->prepare('SELECT statut FROM tasks WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    $statut = $stmt->fetchColumn();

    json_reponse([
        'success' => true,
        'statut' => $statut,
        'stats' => get_stats($pdo, $userId),
    ]);
} catch (PDOException $e) {
    json_reponse(['success' => false, 'message' => 'Erreur serveur'], 500);
}
