<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';
require_once '../includes/Validator.php';

// Add detailed logging
error_log("Signup process started");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        error_log("POST data received: " . print_r($_POST, true));

        // Validate inputs
        $validator = new Validator();
        $rules = [
            'email' => 'required|email',
            'username' => 'required|min:3',
            'password' => 'required|min:6'
        ];

        if (!$validator->validate($_POST, $rules)) {
            error_log("Validation failed: " . print_r($validator->getErrors(), true));
            echo json_encode([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->getErrors()
            ]);
            exit();
        }

        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Check if email exists
        $stmt = $db->prepare("SELECT id FROM auth_users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => 'Email already exists'
            ]);
            exit();
        }

        // Insert new user
        $query = "INSERT INTO auth_users (email, username, password) VALUES (?, ?, ?)";
        $stmt = $db->prepare($query);
        $result = $stmt->execute([$email, $username, $password]);
        
        if ($result) {
            $newUserId = $db->lastInsertId();
            $_SESSION['user_id'] = $newUserId; 
            $_SESSION['auth_id'] = $newUserId;
            $_SESSION['profile_incomplete'] = true;
        
            // Insert into user_profiles
            $stmt = $db->prepare("INSERT INTO user_profiles (auth_id) VALUES (?)");
            $stmt->execute([$newUserId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Registration successful',
                'redirect' => 'profile.html?mode=complete' // Specify the intended redirect URL
            ]);
            exit(); // Ensure no further output
        } else {
            throw new Exception("Failed to insert user");
        }
    } catch (Exception $e) {
        error_log("Error in signup process: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'An unexpected error occurred. Please try again.'
        ]);
        exit();
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}
?>