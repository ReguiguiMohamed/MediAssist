<?php
date_default_timezone_set('Africa/Tunis');

session_start();
if (!isset($_SESSION['auth_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}
$auth_id = $_SESSION['auth_id'];

require_once '../includes/Session.php';
Session::requireLogin();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Define status constants to match notifications_process.php
define('STATUS_PENDING', 0);
define('STATUS_DELIVERED', 1);
define('STATUS_READ', 2);
define('STATUS_ACKNOWLEDGED', 3);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate appointment date and time
        $appointmentDateTime = new DateTime($_POST['appointment_date'] . ' ' . $_POST['appointment_time']);
        $now = new DateTime();
        
        // Check if appointment is in the past
        if ($appointmentDateTime < $now) {
            echo json_encode([
                'success' => false, 
                'error' => 'Cannot schedule appointments in the past. Please select a future date and time.'
            ]);
            exit;
        }
        
        $db->beginTransaction();
        
        // Insert appointment
        $stmt = $db->prepare("INSERT INTO appointments (
            auth_id, appointment_type, doctor_name, location, 
            appointment_date, appointment_time, notes, reminder_time
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $success = $stmt->execute([
            $auth_id,
            $_POST['appointment_type'],
            $_POST['doctor_name'],
            $_POST['location'],
            $_POST['appointment_date'],
            $_POST['appointment_time'],
            $_POST['notes'],
            $_POST['reminder_time'] ?? 30
        ]);

        if ($success) {
            $appointment_id = $db->lastInsertId();
            
            // Calculate due_time (notification time) based on appointment and reminder time
            $appointmentDateTime = new DateTime($_POST['appointment_date'] . ' ' . $_POST['appointment_time']);
            $reminderMinutes = intval($_POST['reminder_time'] ?? 30);
            $dueTime = clone $appointmentDateTime;
            $dueTime->modify("-{$reminderMinutes} minutes");

            // Insert notification
            $stmt = $db->prepare("INSERT INTO notifications (
                auth_id, type, reference_id, title, message, status, due_time
            ) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $auth_id,
                'appointment',
                $appointment_id,
                'Appointment Reminder',
                'Upcoming appointment with Dr. ' . $_POST['doctor_name'] . ' (' . $_POST['appointment_type'] . ') at ' . $_POST['appointment_time'],
                STATUS_PENDING,
                $dueTime->format('Y-m-d H:i:s')
            ]);

            $db->commit();
            echo json_encode(["success" => true]);
        } else {
            throw new Exception("Failed to add appointment");
        }
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit;
}

// GET handler for fetching appointments
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("SELECT * FROM appointments WHERE auth_id = ? ORDER BY appointment_date ASC, appointment_time ASC");
    $stmt->execute([$auth_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'appointments' => $appointments]);
    exit;
}
?>