<?php
session_start();
require_once '../includes/Session.php';

// Log the logout action
error_log("User logout initiated: user_id=" . ($_SESSION['user_id'] ?? 'unknown'));

// Clear all session data
Session::destroy();

// Send JSON response if it's an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Logout successful',
        'redirect' => '../HTML/login.html'
    ]);
} else {
    // Regular browser request - redirect to login page
    header('Location: ../HTML/login.html');
}
exit();