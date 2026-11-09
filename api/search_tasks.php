<?php
session_start();
require '../config/db.php';
require '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    json_reponse(['success' => false, 'message' => 'Non connecté'], 401);
}

$userId = (int) $_SESSION['user_id'];
$filtres = normaliser_filtres($_GET);
$taches = get_tasks($pdo, $userId, $filtres);

json_reponse([
    'success' => true,
    'tasks' => $taches,
    'stats' => get_stats($pdo, $userId),
    'count' => count($taches),
    'filters' => $filtres,
]);
