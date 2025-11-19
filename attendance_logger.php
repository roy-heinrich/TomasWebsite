<?php
date_default_timezone_set('Asia/Manila');
require_once 'session.php';
require_once 'config.php'; // Uses PDO $conn for PostgreSQL

header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get LRN from POST data
$student_lrn = $_POST['student_lrn'] ?? '';

// Validate LRN
if (empty($student_lrn)) {
    echo json_encode(['success' => false, 'message' => 'No LRN provided']);
    exit;
}

// Check if student exists and is active
$stmt = $conn->prepare("SELECT * FROM student_tbl WHERE lrn = :lrn AND status = 'Active'");
$stmt->execute([':lrn' => $student_lrn]);
$student = $stmt->fetch();

if (!$student) {
    echo json_encode(['success' => false, 'message' => 'Student not found or inactive']);
    exit;
}

$current_date = date('Y-m-d');
$current_time = date('H:i:s');
$current_day = date('l');
$windows = [
    'am_login' => ['start' => '06:00', 'end' => '08:30'],
    'am_logout' => ['start' => '10:00', 'end' => '12:30'],
    'pm_login' => ['start' => '12:30', 'end' => '13:30'],
    'pm_logout' => ['start' => '15:00', 'end' => '17:30'],
];

function time_between($time, $start, $end) {
    return ($time >= $start && $time <= $end);
}

$tHM = date('H:i');
$action = null;
if (time_between($tHM, $windows['am_login']['start'], $windows['am_login']['end'])) {
    $action = 'am_login';
} elseif (time_between($tHM, $windows['am_logout']['start'], $windows['am_logout']['end'])) {
    $action = 'am_logout';
} elseif (time_between($tHM, $windows['pm_login']['start'], $windows['pm_login']['end'])) {
    $action = 'pm_login';
} elseif (time_between($tHM, $windows['pm_logout']['start'], $windows['pm_logout']['end'])) {
    $action = 'pm_logout';
} else {
    if ($tHM < $windows['am_login']['start']) {
        echo json_encode(['success' => false, 'message' => 'Too early for attendance', 'already_logged' => false]);
        exit;
    }
    if ($tHM > $windows['pm_logout']['end']) {
        $action = 'pm_logout';
    } elseif ($tHM > $windows['am_logout']['end'] && $tHM < $windows['pm_login']['start']) {
        $action = 'am_logout';
    } else {
        $action = 'am_login';
    }
}

// Check if attendance system is enabled and within school year dates
$sy_stmt = $conn->prepare("SELECT start_date, end_date FROM school_years WHERE is_active = TRUE AND attendance_enabled = TRUE LIMIT 1");
$sy_stmt->execute();
$school_year = $sy_stmt->fetch();

if (!$school_year) {
    echo json_encode([
        'success' => false, 
        'message' => 'Attendance system is currently disabled',
        'already_logged' => false
    ]);
    exit;
}

$start_date = $school_year['start_date'];
$end_date = $school_year['end_date'];

// Check if current date is within school year
if ($current_date < $start_date || $current_date > $end_date) {
    echo json_encode([
        'success' => false, 
        'message' => 'Attendance not recorded: outside school year',
        'already_logged' => false
    ]);
    exit;
}

// Block weekends
if ($current_day === 'Saturday' || $current_day === 'Sunday') {
    echo json_encode([
        'success' => false, 
        'message' => 'Attendance not recorded on weekends',
        'already_logged' => false
    ]);
    exit;
}

// Check for calendar events (full day or half day)
$calendar_check = $conn->prepare("SELECT * FROM school_calendar WHERE date = :date AND type IN ('Holiday', 'Suspension', 'School Event') LIMIT 1");
$calendar_check->execute([':date' => $current_date]);
$event = $calendar_check->fetch();

if ($event) {
    // Handle full day events
    if (empty($event['half_day'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Attendance not recorded: ' . $event['description'],
            'already_logged' => false
        ]);
        exit;
    }

    // For half-day events we must allow the other session's window to complete.
    // e.g., if the PM is suspended (half_day = 'PM'), AM logout window (which may cross noon)
    // should still allow AM logout up to the configured am_logout end.
    $suspended_half = strtoupper($event['half_day']);

    // Current time in H:i for comparisons
    $now_hm = date('H:i');

    if ($suspended_half === 'AM') {
        // AM is suspended → block any AM session actions (login/logout)
        // Determine if action would be AM-related
        if (in_array($action, ['am_login', 'am_logout'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Attendance not recorded (AM suspended): ' . $event['description'],
                'already_logged' => false
            ]);
            exit;
        }
    } else {
        // PM is suspended → block PM session actions, but allow AM actions until am_logout end
        if (in_array($action, ['pm_login', 'pm_logout'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Attendance not recorded (PM suspended): ' . $event['description'],
                'already_logged' => false
            ]);
            exit;
        }

        // If the current time is after the am_logout window end, then AM actions are no longer allowed
        if ($now_hm > $windows['am_logout']['end']) {
            // Any AM action attempted after am_logout window should be blocked when PM is suspended
            if (in_array($action, ['am_login', 'am_logout'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Attendance not recorded: ' . $event['description'],
                    'already_logged' => false
                ]);
                exit;
            }
        }
    }
}

// Check for existing attendance today
$attendance_check = $conn->prepare("SELECT * FROM attendance_tbl WHERE student_lrn = :lrn AND date = :date LIMIT 1");
$attendance_check->execute([':lrn' => $student_lrn, ':date' => $current_date]);
$attendance = $attendance_check->fetch();

function mark_session_present(&$row, $session) {
    if ($session === 'am') {
        $row['morning_status'] = 'Present';
    } else {
        $row['afternoon_status'] = 'Present';
    }
}

try {
    if (!$attendance) {
        $insert_sql = "INSERT INTO attendance_tbl (student_lrn, date, morning_status, afternoon_status, am_login_time, am_logout_time, pm_login_time, pm_logout_time) VALUES (:lrn, :date, :morning_status, :afternoon_status, :am_login_time, :am_logout_time, :pm_login_time, :pm_logout_time)";
        $params = [
            ':lrn' => $student_lrn,
            ':date' => $current_date,
            ':morning_status' => null,
            ':afternoon_status' => null,
            ':am_login_time' => null,
            ':am_logout_time' => null,
            ':pm_login_time' => null,
            ':pm_logout_time' => null,
        ];

        switch ($action) {
            case 'am_login':
                $params[':am_login_time'] = $current_time;
                $params[':morning_status'] = 'Present';
                break;
            case 'am_logout':
                $params[':am_logout_time'] = $current_time;
                $params[':morning_status'] = 'Present';
                break;
            case 'pm_login':
                $params[':pm_login_time'] = $current_time;
                $params[':afternoon_status'] = 'Present';
                break;
            case 'pm_logout':
                $params[':pm_logout_time'] = $current_time;
                $params[':afternoon_status'] = 'Present';
                break;
        }

        $insert_stmt = $conn->prepare($insert_sql);
        $success = $insert_stmt->execute($params);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Attendance recorded', 'action' => $action]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to record attendance']);
        }
        exit;
    } else {
        $update_fields = [];
        $update_params = [':attend_id' => $attendance['attend_id']];

        $am_login = $attendance['am_login_time'] ?? null;
        $am_logout = $attendance['am_logout_time'] ?? null;
        $pm_login = $attendance['pm_login_time'] ?? null;
        $pm_logout = $attendance['pm_logout_time'] ?? null;

        switch ($action) {
            case 'am_login':
                if (!empty($am_login)) {
                    echo json_encode(['success' => false, 'message' => 'AM login already recorded', 'already_logged' => true]);
                    exit;
                }
                $update_fields[] = "am_login_time = :am_login_time";
                $update_params[':am_login_time'] = $current_time;
                $update_fields[] = "morning_status = 'Present'";
                break;
            case 'am_logout':
                if (!empty($am_logout)) {
                    echo json_encode(['success' => false, 'message' => 'AM logout already recorded', 'already_logged' => true]);
                    exit;
                }
                $update_fields[] = "am_logout_time = :am_logout_time";
                $update_params[':am_logout_time'] = $current_time;
                $update_fields[] = "morning_status = 'Present'";
                break;
            case 'pm_login':
                if (!empty($pm_login)) {
                    echo json_encode(['success' => false, 'message' => 'PM login already recorded', 'already_logged' => true]);
                    exit;
                }
                $update_fields[] = "pm_login_time = :pm_login_time";
                $update_params[':pm_login_time'] = $current_time;
                $update_fields[] = "afternoon_status = 'Present'";
                break;
            case 'pm_logout':
                if (!empty($pm_logout)) {
                    echo json_encode(['success' => false, 'message' => 'PM logout already recorded', 'already_logged' => true]);
                    exit;
                }
                $update_fields[] = "pm_logout_time = :pm_logout_time";
                $update_params[':pm_logout_time'] = $current_time;
                $update_fields[] = "afternoon_status = 'Present'";
                break;
        }

        if (count($update_fields) > 0) {
            $sql = 'UPDATE attendance_tbl SET ' . implode(', ', $update_fields) . ' WHERE attend_id = :attend_id';
            $stmt = $conn->prepare($sql);
            $success = $stmt->execute($update_params);
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Attendance updated', 'action' => $action]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update attendance']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Nothing to update']);
        }
        exit;
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'column') !== false) {
        echo json_encode(['success' => false, 'message' => 'Database migration required: add session columns to attendance_tbl', 'error' => $e->getMessage()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
    exit;
}

// Legacy single-column logic removed. This logger now uses am_login_time, am_logout_time,
// pm_login_time, and pm_logout_time exclusively. If you still need to migrate old
// `login_time`/`logout_time` values, run a one-time SQL migration before dropping those columns.