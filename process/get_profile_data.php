<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['auth_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$auth_id = $_SESSION['auth_id'];
$db = (new Database())->getConnection();

$stmt = $db->prepare("SELECT * FROM user_profiles WHERE auth_id = ?");
$stmt->execute([$auth_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

$profile_completed = isset($_SESSION['profile_completed']) && $_SESSION['profile_completed'];

if ($profile) {
    // Encode image if present
    if (!empty($profile['profile_image'])) {
        $profile['profile_image'] = 'data:image/jpeg;base64,' . base64_encode($profile['profile_image']);
    }
    echo json_encode([
        'success' => true,
        'profile' => $profile,
        'profile_completed' => $profile_completed
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Profile not found',
        'profile_completed' => false
    ]);
}
exit();