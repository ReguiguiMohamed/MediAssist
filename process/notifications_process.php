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

$database = new Database();
$db = $database->getConnection();

// Define status constants
define('STATUS_PENDING', 0);
define('STATUS_DELIVERED', 1);
define('STATUS_READ', 2);
define('STATUS_ACKNOWLEDGED', 3);

// POST handler for updating notification status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Mark notification as read
    if ($action === 'mark_read' && isset($_POST['notification_id'])) {
        $stmt = $db->prepare("UPDATE notifications SET status = ? WHERE id = ? AND auth_id = ?");
        $result = $stmt->execute([STATUS_READ, $_POST['notification_id'], $auth_id]);
        echo json_encode(['success' => $result]);
        exit;
    }
    
    // Mark reminder as delivered
    if ($action === 'mark_delivered' && isset($_POST['reminder_id'])) {
        $stmt = $db->prepare("UPDATE notifications SET status = ? WHERE id = ? AND auth_id = ?");
        $result = $stmt->execute([STATUS_DELIVERED, $_POST['reminder_id'], $auth_id]);
        echo json_encode(['success' => $result]);
        exit;
    }
    
    // Mark reminder as acknowledged
    if ($action === 'mark_acknowledged' && isset($_POST['reminder_id'])) {
        // 1. Mark as acknowledged
        $stmt = $db->prepare("UPDATE notifications SET status = ? WHERE id = ? AND auth_id = ?");
        $stmt->execute([STATUS_ACKNOWLEDGED, $_POST['reminder_id'], $auth_id]);

        // 2. Get medication info for this notification
        $stmt = $db->prepare("SELECT n.*, m.frequency, m.time_of_day, m.start_date, m.end_date 
                              FROM notifications n 
                              JOIN medications m ON n.reference_id = m.id 
                              WHERE n.id = ? AND n.type = 'medication' AND n.auth_id = ?");
        $stmt->execute([$_POST['reminder_id'], $auth_id]);
        $notif = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($notif) {
            // Parse frequency
            $freqParts = explode(' ', strtolower($notif['frequency']));
            $freqNum = isset($freqParts[0]) ? intval($freqParts[0]) : 1;
            $freqPeriod = isset($freqParts[1]) ? $freqParts[1] : 'daily';

            $currentDue = new DateTime($notif['due_time']);
            $nextDue = null;

            if ($freqPeriod === 'daily') {
                $nextDue = $currentDue->modify('+1 day');
            } elseif ($freqPeriod === 'weekly') {
                $nextDue = $currentDue->modify('+1 week');
            } elseif ($freqPeriod === 'monthly') {
                $nextDue = $currentDue->modify('+1 month');
            }

            // Only update if nextDue is within medication's end_date (if set)
            $endDate = !empty($notif['end_date']) ? new DateTime($notif['end_date']) : null;
            if ($nextDue && (!$endDate || $nextDue <= $endDate)) {
                // 3. Update notification for next intake
                $stmt = $db->prepare("UPDATE notifications SET due_time = ?, status = ? WHERE id = ? AND auth_id = ?");
                $stmt->execute([$nextDue->format('Y-m-d H:i:s'), STATUS_PENDING, $_POST['reminder_id'], $auth_id]);
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Clear notifications (delete status 3 notifications)
    if ($action === 'clear_notifications') {
        $stmt = $db->prepare("DELETE FROM notifications WHERE auth_id = ? AND status = ?");
        $result = $stmt->execute([$auth_id, STATUS_ACKNOWLEDGED]);
        echo json_encode(['success' => $result]);
        exit;
    }
    
    // Mark all notifications as read (keeping this for backward compatibility)
    if ($action === 'mark_all_read') {
        $stmt = $db->prepare("UPDATE notifications SET status = ? WHERE auth_id = ? AND status < ?");
        $result = $stmt->execute([STATUS_READ, $auth_id, STATUS_READ]);
        echo json_encode(['success' => $result]);
        exit;
    }
}

// GET request handling
$action = $_GET['action'] ?? '';

// Get notifications with pagination support
if ($action === 'get_notifications') {
    // No need for pagination since we removed the load more button
    $stmt = $db->prepare("
        SELECT id, auth_id, type, reference_id, due_time, title, message, status
        FROM notifications 
        WHERE auth_id = ? AND status = ? 
        ORDER BY due_time DESC
    ");
    $stmt->bindParam(1, $auth_id, PDO::PARAM_INT);
    $status = STATUS_PENDING; // Show all notifications (status 0)
    $stmt->bindParam(2, $status, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for metadata
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE auth_id = ? AND status = ?");
    $countStmt->execute([$auth_id, STATUS_ACKNOWLEDGED]);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Update the is_read property calculation:
    foreach ($notifications as &$notification) {
        $notification['is_read'] = ($notification['status'] >= STATUS_READ);
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
    exit;
}

// Check for new notifications and active reminders
if ($action === 'check_notifications' || empty($action)) {
    // Check for unread notifications
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE auth_id = ? AND status < ?
    ");
    $stmt->execute([$auth_id, STATUS_READ]);
    $unreadCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get active reminders (due now but not yet delivered)
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("
        SELECT id, type, reference_id, title, message, status, due_time 
        FROM notifications 
        WHERE auth_id = ? 
        AND status = ? 
        AND due_time <= ?
        ORDER BY due_time ASC
    ");
    $stmt->execute([$auth_id, STATUS_PENDING, $now]);
    $activeReminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get next upcoming reminder
    $stmt = $db->prepare("
        SELECT MIN(due_time) as next_time 
        FROM notifications 
        WHERE auth_id = ? 
        AND status = ? 
        AND due_time > ?
    ");
    $stmt->execute([$auth_id, STATUS_PENDING, $now]);
    $nextTime = $stmt->fetch(PDO::FETCH_ASSOC)['next_time'];
    
    echo json_encode([
        'success' => true,
        'hasUnread' => $unreadCount > 0,
        'unreadCount' => $unreadCount,
        'activeReminders' => $activeReminders,
        'nextReminderTime' => $nextTime
    ]);
    exit;
}

// Add this at the end before the final default response
if ($action === 'debug_med_notifications') {
    $stmt = $db->prepare("
        SELECT n.*, m.medication_name, m.dosage, m.frequency, m.start_date, m.time_of_day
        FROM notifications n
        JOIN medications m ON n.reference_id = m.id
        WHERE n.auth_id = ? AND n.type = 'medication'
        ORDER BY n.due_time DESC
    ");
    $stmt->execute([$auth_id]);
    $med_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'medication_notifications' => $med_notifications,
        'current_time' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Default response
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>