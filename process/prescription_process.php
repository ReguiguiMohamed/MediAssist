<?php
session_start();
if (!isset($_SESSION['auth_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}
$auth_id = $_SESSION['auth_id'];

require_once '../includes/Session.php';
Session::requireLogin();
require_once '../config/database.php';

class Prescription {
    private $conn;
    private $table = "prescriptions";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($data) {
        $query = "INSERT INTO {$this->table} (auth_id, doctor_name, issue_date, prescription_image, notes)
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            $data['auth_id'],
            $data['doctor_name'],
            $data['issue_date'],
            $data['prescription_image'],
            $data['notes']
        ]);
    }

    public function getUserPrescriptions($auth_id) {
        $query = "SELECT * FROM {$this->table} WHERE auth_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$auth_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // More methods for fetching/updating/deleting prescriptions...
}

$database = new Database();
$db = $database->getConnection();
$prescription = new Prescription($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prescription_image_blob = null;
    if (isset($_FILES['prescription_image']) && $_FILES['prescription_image']['error'] === UPLOAD_ERR_OK) {
        $prescription_image_blob = file_get_contents($_FILES['prescription_image']['tmp_name']);
    }
    $stmt = $db->prepare("INSERT INTO prescriptions (auth_id, doctor_name, issue_date, prescription_image, notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $auth_id,
        $_POST['doctor_name'],
        $_POST['issue_date'],
        $prescription_image_blob,
        $_POST['notes']
    ]);
    if ($stmt) {
        echo json_encode(["success" => true, "message" => "Prescription added successfully"]);
    } else {
        echo json_encode(["success" => false, "error" => "Failed to add prescription"]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $prescriptions = $prescription->getUserPrescriptions($auth_id);
    foreach ($prescriptions as &$presc) {
        if (!empty($presc['prescription_image'])) {
            $presc['prescription_image'] = 'data:image/jpeg;base64,' . base64_encode($presc['prescription_image']);
        }
    }
    echo json_encode(['success' => true, 'prescriptions' => $prescriptions]);
    exit;
}
?>