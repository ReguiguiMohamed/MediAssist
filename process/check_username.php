<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (isset($_GET['username'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $username = filter_var($_GET['username'], FILTER_SANITIZE_STRING);
        
        $stmt = $db->prepare("SELECT id FROM auth_users WHERE username = ?");
        $stmt->execute([$username]);
        
        echo json_encode([
            'available' => !$stmt->fetch(),
            'success' => true
        ]);
    } catch(PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Username parameter missing'
    ]);
}