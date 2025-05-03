<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];

        // Query to check user credentials
        $stmt = $db->prepare("SELECT id, username, password, profile_completed FROM auth_users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Set session variables using only auth_id
            Session::set('auth_id', $user['id']);
            Session::set('user_id', $user['id']); // <-- Add this line
            Session::set('username', $user['username']);
            Session::set('profile_completed', (bool)$user['profile_completed']);
            
            // Log successful login
            error_log("User logged in successfully: " . $user['id']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'redirect' => !$user['profile_completed'] ? 'profile.html?mode=complete' : 'dashboard.html'
            ]);
        } else {
            error_log("Failed login attempt for email: " . $email);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email or password'
            ]);
        }
    } catch(PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error occurred'
        ]);
    }
    exit();
}