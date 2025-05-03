<?php
session_start();
require_once '../includes/Session.php';
Session::requireLogin();
require_once '../config/database.php';

class EmergencyContact {
    private $conn;
    private $table = "emergency_contacts";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function addContact($data) {
        $query = "INSERT INTO {$this->table} (auth_id, name, relationship, phone)
                  VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            $data['auth_id'],
            $data['name'],
            $data['relationship'],
            $data['phone']
        ]);
    }

    public function getUserContacts($auth_id) {
        $query = "SELECT id, name, relationship, phone FROM {$this->table} WHERE auth_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$auth_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$database = new Database();
$db = $database->getConnection();
$contact = new EmergencyContact($db);

$auth_id = $_SESSION['auth_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'auth_id' => $auth_id,
        'name' => $_POST['name'],
        'relationship' => $_POST['relationship'],
        'phone' => $_POST['phone']
    ];
    if ($contact->addContact($data)) {
        echo json_encode(["success" => true, "message" => "Emergency contact added successfully"]);
    } else {
        echo json_encode(["success" => false, "error" => "Failed to add contact"]);
    }
    exit;
}

// GET: List contacts
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $contacts = $contact->getUserContacts($auth_id);
    echo json_encode(['success' => true, 'contacts' => $contacts]);
    exit;
}
?>