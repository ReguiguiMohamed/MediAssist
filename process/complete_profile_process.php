<?php
session_start();
require_once '../config/database.php';
require_once '../includes/Session.php'; // Include session helper

error_reporting(E_ALL);
ini_set('display_errors', 1);
error_log("Starting profile processing (completion/update)");

header('Content-Type: application/json');

if (!isset($_SESSION['auth_id'])) {
    error_log("User not authenticated for profile update.");
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated'
    ]);
    exit();
}

$userId = $_SESSION['auth_id'];
$mode = isset($_POST['mode']) ? $_POST['mode'] : 'edit'; // 'complete' or 'edit'

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }

    $stmt = $db->prepare("SELECT profile_image FROM user_profiles WHERE auth_id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Query preparation failed']);
        exit;
    }

    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($profile && !empty($profile['profile_image'])) {
        $profile_image = 'data:image/jpeg;base64,' . base64_encode($profile['profile_image']);
        echo json_encode(['success' => true, 'profile_image' => $profile_image]);
    } else {
        echo json_encode(['success' => true, 'profile_image' => null]);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    try {
        // --- Input Validation (Basic Example) ---
        $requiredFields = ['first_name', 'last_name', 'date_of_birth', 'gender'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception(ucfirst(str_replace('_', ' ', $field)) . " is required.");
            }
        }
        // Add more specific validation as needed (e.g., date format, gender values)
        //-----------------------------------------

        $existing_profile_image = isset($_POST['existing_profile_image']) ? $_POST['existing_profile_image'] : null;

        // Determine the image to save
        $final_profile_image = null;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $final_profile_image = file_get_contents($_FILES['profile_image']['tmp_name']);
        } elseif (isset($_POST['profile_image_base64']) && !empty($_POST['profile_image_base64'])) {
            $final_profile_image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $_POST['profile_image_base64']));
        } elseif ($existing_profile_image) {
            $final_profile_image = $existing_profile_image;
        }

        // Start transaction
        $db->beginTransaction();

        error_log("Processing profile data for user_id: $userId in mode: $mode");

        // Check if profile exists
        $checkStmt = $db->prepare("SELECT auth_id, first_name, last_name, date_of_birth FROM user_profiles WHERE auth_id = ?");
        $checkStmt->execute([$userId]);
        $existingProfile = $checkStmt->fetch(PDO::FETCH_ASSOC);

        $profileCompleted = isset($_SESSION['profile_completed']) && $_SESSION['profile_completed'] === true;

        if ($existingProfile) {
            error_log("Existing profile found for user_id: $userId. Updating...");

            // Build the update query dynamically, respecting non-editable fields in 'edit' mode
            $updateFields = [
                'gender' => $_POST['gender'],
                'blood_group' => $_POST['blood_group'] ?: null,
                'weight' => $_POST['weight'] ?: null,
                'height' => $_POST['height'] ?: null,
                'phone' => $_POST['phone'] ?: null,
                'address' => $_POST['address'] ?: null,
                'profile_image' => $final_profile_image
            ];
            $params = array_values($updateFields);
            $setClauses = array_map(function($key) { return "$key = ?"; }, array_keys($updateFields));

            if ($mode === 'complete' || !$profileCompleted) {
                // Allow updating all fields
                $updateFields['first_name'] = $_POST['first_name'];
                $updateFields['last_name'] = $_POST['last_name'];
                $updateFields['date_of_birth'] = $_POST['date_of_birth'];
                $updateFields['gender'] = $_POST['gender'];
                $updateFields['blood_group'] = $_POST['blood_group'];
            } else {
                // Only allow updating editable fields
                // Do NOT update first_name, last_name, date_of_birth, gender, blood_group
                error_log("Edit mode: Name, DOB, gender and blood group are read-only for user_id: $userId");
            }

            // Rebuild params and clauses
            $params = array_values($updateFields);
            $setClauses = array_map(function($key) { return "$key = ?"; }, array_keys($updateFields));

            $query = "UPDATE user_profiles SET " . implode(", ", $setClauses) . " WHERE auth_id = ?";
            $params[] = $userId; // Add auth_id to the end of params

            $stmt = $db->prepare($query);
            $result = $stmt->execute($params);
            $message = 'Profile updated successfully';

        } else {
            error_log("No existing profile found for user_id: $userId. Inserting new...");
            // Insert new profile (should only happen in 'complete' mode really)
            $query = "INSERT INTO user_profiles (
                auth_id, first_name, last_name, date_of_birth, 
                gender, blood_group, weight, height, 
                phone, address, profile_image
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $userId,
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['date_of_birth'],
                $_POST['gender'],
                $_POST['blood_group'] ?: null,
                $_POST['weight'] ?: null,
                $_POST['height'] ?: null,
                $_POST['phone'] ?: null,
                $_POST['address'] ?: null,
                $final_profile_image
            ]);
            $message = 'Profile completed successfully';
        }

        if ($result) {
            error_log("Profile data saved to DB successfully for user_id: $userId");

            // Always ensure the profile_completed flag is set correctly in auth_users
            // It should be set to 1 once the required fields are submitted the first time.
            $updateAuthStmt = $db->prepare("UPDATE auth_users SET profile_completed = 1 WHERE id = ? AND profile_completed = 0");
            $authUpdateResult = $updateAuthStmt->execute([$userId]);

            if ($authUpdateResult === false) {
                // Handle potential error, but don't necessarily roll back if the profile saved ok
                error_log("Warning: Failed to update profile_completed flag for user_id: $userId (maybe already set?)");
            }
            
            // Commit transaction
            $db->commit();

            // Update session variables
            $_SESSION['user_name'] = $_POST['first_name'] . ' ' . $_POST['last_name'];
            $_SESSION['profile_completed'] = true; // Mark as completed in session
            unset($_SESSION['profile_incomplete']); // Remove any old incomplete flag

            error_log("Profile processing successful for user_id: $userId");

            echo json_encode([
                'success' => true,
                'message' => $message,
                'redirect' => ($mode === 'complete') ? 'dashboard.html' : null // Redirect only on completion
            ]);

        } else {
            $errorInfo = $stmt->errorInfo();
            throw new PDOException("Profile save failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
            error_log("Transaction rolled back due to error.");
        }

        error_log("Profile processing error for user_id: $userId - " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit();
}

// If neither GET nor POST
echo json_encode([
    'success' => false,
    'message' => 'Invalid request method'
]);
exit();
?>