<?php
date_default_timezone_set('Africa/Tunis');

session_start();
require_once '../includes/Session.php';
Session::requireLogin();
require_once '../config/database.php';

header('Content-Type: application/json');

class Medication {
    private $conn;
    private $table = "medications";
    public function __construct($db) { $this->conn = $db; }

    public function create($data) {
        // Validate end date if provided
        if (!empty($data['end_date'])) {
            $endDate = new DateTime($data['end_date']);
            $today = new DateTime(date('Y-m-d'));
            
            if ($endDate < $today) {
                return ['success' => false, 'message' => 'End date cannot be in the past. Please select today or a future date.'];
            }
        }
        
        $query = "INSERT INTO {$this->table} (auth_id, medication_name, dosage, frequency, start_date, end_date, time_of_day, image, notes)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $result = $stmt->execute([
            $data['auth_id'],
            $data['medication_name'],
            $data['dosage'],
            $data['frequency'],
            $data['start_date'],
            $data['end_date'],
            $data['time_of_day'],
            $data['image'],
            $data['notes']
        ]);
        
        return ['success' => $result];
    }

    public function getUserMedications($auth_id) {
        $query = "SELECT * FROM {$this->table} WHERE auth_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$auth_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    function addMedicationReminder($db, $auth_id, $medication_id, $medication_name, $dosage, $start_date, $time_of_day, $frequency) {
        $now = new DateTime();
        $start = new DateTime($start_date . ' ' . $time_of_day);
        
        // Parse frequency
        $freqParts = explode(' ', strtolower($frequency));
        $freqNum = isset($freqParts[0]) ? intval($freqParts[0]) : 1;
        $freqPeriod = isset($freqParts[1]) ? $freqParts[1] : 'daily';
        
        // Check if medication should be taken today
        $today = new DateTime(date('Y-m-d') . ' ' . $time_of_day);
        $startDay = new DateTime($start_date);
        $todayDay = new DateTime(date('Y-m-d'));
        
        // If start date is today or in the past, and the time hasn't passed too much (within 6 hours)
        $timeThreshold = clone $now;
        $timeThreshold->modify('-6 hours'); // Allow notifications for medications due within the last 6 hours
        
        $due_time = null;
        
        // If medication started today or earlier
        if ($startDay <= $todayDay) {
            // If the medication time today is still upcoming or recently passed
            if ($today > $timeThreshold && $today <= $now->modify('+1 hour')) {
                // Use today's time
                $due_time = clone $today;
            } else {
                // Start from today but find the next occurrence
                $due_time = clone $today;
                
                // If today's time has passed by more than the threshold, move to next occurrence
                if ($today < $timeThreshold) {
                    if ($freqPeriod === 'daily') {
                        $due_time->modify('+1 day');
                    } elseif ($freqPeriod === 'weekly') {
                        $due_time->modify('+1 week');
                    } elseif ($freqPeriod === 'monthly') {
                        $due_time->modify('+1 month');
                    } else {
                        // Default to daily if unknown
                        $due_time->modify('+1 day');
                    }
                }
            }
        } else {
            // If start date is in the future, use that
            $due_time = clone $start;
        }
        
        $message = "Time to take your medication: $medication_name ($dosage)";
        
        // Define status constants if not already defined
        if (!defined('STATUS_PENDING')) define('STATUS_PENDING', 0);
        
        $stmt = $db->prepare("
            INSERT INTO notifications (auth_id, type, reference_id, title, message, due_time, status)
            VALUES (?, 'medication', ?, ?, ?, ?, 0)
        ");
        return $stmt->execute([
            $auth_id,
            $medication_id,
            "Medication Reminder",
            $message,
            $due_time->format('Y-m-d H:i:s')
        ]);
    }
    
}

$database = new Database();
$db = $database->getConnection();
$medication = new Medication($db);

$auth_id = $_SESSION['auth_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $image = null;
    if (isset($_FILES['med_image']) && $_FILES['med_image']['error'] === UPLOAD_ERR_OK) {
        $image = file_get_contents($_FILES['med_image']['tmp_name']);
    }
    $data = [
        'auth_id' => $auth_id,
        'medication_name' => $_POST['med_name'],
        'dosage' => $_POST['dosage'],
        'frequency' => $_POST['frequency'],
        'start_date' => $_POST['start_date'],
        'end_date' => $_POST['end_date'] ?? null,
        'time_of_day' => $_POST['time_of_day'],
        'image' => $image,
        'notes' => $_POST['notes'] ?? ''
    ];
    
    $result = $medication->create($data);
    
    if ($result['success']) {
        // Add this section to create a reminder after successful medication creation
        $medication_id = $db->lastInsertId(); // Get the newly created medication ID
        $medication->addMedicationReminder(
            $db, 
            $auth_id, 
            $medication_id, 
            $_POST['med_name'], 
            $_POST['dosage'], 
            $_POST['start_date'],
            $_POST['time_of_day'],
            $_POST['frequency']
        );
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Failed to add medication.']);
    }
    exit;
}



// GET: List medications
$medications = $medication->getUserMedications($auth_id);
foreach ($medications as &$med) {
    if (!empty($med['image'])) {
        $med['image'] = 'data:image/jpeg;base64,' . base64_encode($med['image']);
    }
}
echo json_encode(['success' => true, 'medications' => $medications]);
exit;
?>