<?php
date_default_timezone_set('Africa/Tunis');
session_start();
require_once '../includes/Session.php';
Session::requireLogin();
require_once '../config/database.php';

header('Content-Type: application/json');

$auth_id = $_SESSION['auth_id'] ?? null;
if (!$auth_id) {
    echo json_encode(['success' => false, 'events' => []]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Fetch appointments
$appointments = [];
$stmt = $db->prepare("SELECT * FROM appointments WHERE auth_id = ?");
$stmt->execute([$auth_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $start = $row['appointment_date'];
    if (!empty($row['appointment_time'])) {
        $start .= 'T' . $row['appointment_time'];
    }
    $appointments[] = [
        'id' => 'appt' . $row['id'],
        'title' => $row['doctor_name'] . ' (' . $row['appointment_type'] . ')',
        'start' => $start,
        'end' => $start,
        'className' => 'event-appointment',
        'extendedProps' => [
            'type' => 'Appointment',
            'location' => $row['location'],
            'notes' => $row['notes'],
            'reminder_time' => $row['reminder_time'],
            'created_at' => $row['created_at'],
        ]
    ];
}

// Fetch medications
$medications = [];
$stmt = $db->prepare("SELECT * FROM medications WHERE auth_id = ?");
$stmt->execute([$auth_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Parse frequency, e.g. "2 daily" or "1 daily"
    $freqParts = explode(' ', strtolower($row['frequency']));
    $freqNum = isset($freqParts[0]) ? intval($freqParts[0]) : 1;
    $freqPeriod = isset($freqParts[1]) ? $freqParts[1] : 'daily';

    $startDate = new DateTime($row['start_date']);
    $endDate = !empty($row['end_date']) ? new DateTime($row['end_date']) : new DateTime('+30 days'); // show for 30 days if no end
    $today = new DateTime();

    // Only show future or current events
    $periodEnd = $endDate > $today ? $endDate : $today;

    // For daily frequency
    if ($freqPeriod === 'daily') {
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($startDate, $interval, $periodEnd->modify('+1 day'));
        foreach ($period as $date) {
            // If multiple times per day, space them out (e.g., 2 daily: 08:00, 20:00)
            for ($i = 0; $i < $freqNum; $i++) {
                $time = $row['time_of_day'];
                if ($freqNum > 1) {
                    // Distribute times evenly in 24h (e.g., 2 daily: 08:00, 20:00)
                    $base = 24 / $freqNum;
                    $hour = str_pad((int)($base * $i), 2, '0', STR_PAD_LEFT);
                    $time = $hour . substr($row['time_of_day'], 2); // keep minutes/seconds
                }
                $start = $date->format('Y-m-d') . 'T' . $time;
                $medications[] = [
                    'id' => 'med' . $row['id'] . '_' . $date->format('Ymd') . '_' . $i,
                    'title' => $row['medication_name'] . (isset($row['dosage']) ? ' ' . $row['dosage'] : ''),
                    'start' => $start,
                    'end' => null,
                    'className' => 'event-medication',
                    'extendedProps' => [
                        'type' => 'Medication',
                        'dosage' => $row['dosage'],
                        'frequency' => $row['frequency'],
                        'start_date' => $row['start_date'],
                        'end_date' => $row['end_date'],
                        'time_of_day' => $time,
                        'notes' => $row['notes'],
                        'created_at' => $row['created_at']
                    ]
                ];
            }
        }
    }

    // For monthly frequency
    if ($freqPeriod === 'monthly') {
        $interval = new DateInterval('P1M');
        $period = new DatePeriod($startDate, $interval, $periodEnd->modify('+1 month'));
        foreach ($period as $date) {
            $start = $date->format('Y-m-d') . 'T' . $row['time_of_day'];
            $medications[] = [
                'id' => 'med' . $row['id'] . '_' . $date->format('Ym'),
                'title' => $row['medication_name'] . (isset($row['dosage']) ? ' ' . $row['dosage'] : ''),
                'start' => $start,
                'end' => null,
                'className' => 'event-medication',
                'extendedProps' => [
                    'type' => 'Medication',
                    'dosage' => $row['dosage'],
                    'frequency' => $row['frequency'],
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                    'time_of_day' => $row['time_of_day'],
                    'notes' => $row['notes'],
                    'created_at' => $row['created_at']
                ]
            ];
        }
    }
    if ($freqPeriod === 'weekly') {
        $interval = new DateInterval('P1W');
        $period = new DatePeriod($startDate, $interval, $periodEnd->modify('+1 week'));
        foreach ($period as $date) {
            $start = $date->format('Y-m-d') . 'T' . $row['time_of_day'];
            $medications[] = [
                'id' => 'med' . $row['id'] . '_' . $date->format('Ymd'),
                'title' => $row['medication_name'] . (isset($row['dosage']) ? ' ' . $row['dosage'] : ''),
                'start' => $start,
                'end' => null,
                'className' => 'event-medication',
                'extendedProps' => [
                    'type' => 'Medication',
                    'dosage' => $row['dosage'],
                    'frequency' => $row['frequency'],
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                    'time_of_day' => $row['time_of_day'],
                    'notes' => $row['notes'],
                    'created_at' => $row['created_at']
                ]
            ];
        }
    }
}

echo json_encode([
    'success' => true,
    'events' => array_merge($appointments, $medications)
]);
exit;
?>