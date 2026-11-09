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
    // La clause user_id empêche de supprimer la tâche d'un autre utilisateur
    $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);

    if ($stmt->rowCount() === 0) {
        json_reponse(['success' => false, 'message' => 'Tâche introuvable'], 404);
    }

    json_reponse([
        'success' => true,
        'stats' => get_stats($pdo, $userId),
    ]);
} catch (PDOException $e) {
    json_reponse(['success' => false, 'message' => 'Erreur serveur'], 500);
}
