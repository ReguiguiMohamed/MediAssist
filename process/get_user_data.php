<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

if (!Session::isAuthenticated()) {
    echo json_encode([
        'success' => false,
        'redirect' => 'login.html'
    ]);
    exit();
}

$auth_id = Session::get('auth_id');

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get user profile info (optional)
    $stmt = $db->prepare("SELECT u.username, p.first_name, p.last_name, u.profile_completed
                          FROM auth_users u
                          LEFT JOIN user_profiles p ON u.id = p.auth_id
                          WHERE u.id = ?");
    $stmt->execute([$auth_id]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get counts
    $medStmt = $db->prepare("SELECT COUNT(*) FROM medications WHERE auth_id = ?");
    $medStmt->execute([$auth_id]);
    $medicationCount = $medStmt->fetchColumn();

    $apptStmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE auth_id = ? AND appointment_date >= CURDATE()");
    $apptStmt->execute([$auth_id]);
    $appointmentCount = $apptStmt->fetchColumn();

    $prescStmt = $db->prepare("SELECT COUNT(*) FROM prescriptions WHERE auth_id = ?");
    $prescStmt->execute([$auth_id]);
    $prescriptionCount = $prescStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'userName' => $userData['first_name'] ?? $userData['username'] ?? 'User',
        'profileIncomplete' => !$userData['profile_completed'],
        'medicationCount' => $medicationCount,
        'appointmentCount' => $appointmentCount,
        'prescriptionCount' => $prescriptionCount
    ]);
} catch (Exception $e) {
    error_log("Dashboard data error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error loading dashboard data']);
}
?>