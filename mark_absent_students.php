<?php
function mark_absent_students($conn) {
    date_default_timezone_set('Asia/Manila');
    $today = date('Y-m-d');
    $current_time = date('H:i');  // current time HH:MM
    // Check if attendance system is enabled and get school year dates
    $sysStmt = $conn->prepare("SELECT start_date, end_date FROM school_years WHERE is_active = true AND attendance_enabled = true LIMIT 1");
    $sysStmt->execute();
    $school_year = $sysStmt->fetch(PDO::FETCH_ASSOC);
    if (!$school_year) {
        return; // System disabled or no active school year
    }

    $sy_start = $school_year['start_date'];
    $sy_end = $school_year['end_date'];
    $range_end = ($today < $sy_end) ? $today : $sy_end;

    // Load active students once
    $studentsStmt = $conn->prepare("SELECT lrn, date_enrolled FROM student_tbl WHERE status = 'Active'");
    $studentsStmt->execute();
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$students) return;

    // Load calendar events for the date range once
    $calStmt = $conn->prepare("SELECT date, half_day FROM school_calendar WHERE date BETWEEN :start AND :end AND type IN ('Holiday','Suspension','School Event')");
    $calStmt->execute([':start' => $sy_start, ':end' => $range_end]);
    $calendar = [];
    while ($row = $calStmt->fetch(PDO::FETCH_ASSOC)) {
        $calendar[$row['date']] = $row;
    }

    // Load existing attendance for the date range once (indexed by student_lrn and date)
    // include session timestamps if present
    $attStmt = $conn->prepare("SELECT student_lrn, date, morning_status, afternoon_status, attend_id, am_login_time, am_logout_time, pm_login_time, pm_logout_time FROM attendance_tbl WHERE date BETWEEN :start AND :end");
    $attStmt->execute([':start' => $sy_start, ':end' => $range_end]);
    $attendanceMap = [];
    while ($row = $attStmt->fetch(PDO::FETCH_ASSOC)) {
        $attendanceMap[$row['student_lrn']][$row['date']] = $row;
    }

    // Prepare statements for inserts and updates
    $insertStmt = $conn->prepare("INSERT INTO attendance_tbl (student_lrn, date, morning_status, afternoon_status, am_login_time, am_logout_time, pm_login_time, pm_logout_time) VALUES (:lrn, :date, :morning_status, :afternoon_status, :am_login_time, :am_logout_time, :pm_login_time, :pm_logout_time)");
    $updateStmt = $conn->prepare("UPDATE attendance_tbl SET morning_status = :morning_status, afternoon_status = :afternoon_status, am_login_time = :am_login_time, am_logout_time = :am_logout_time, pm_login_time = :pm_login_time, pm_logout_time = :pm_logout_time WHERE attend_id = :attend_id");

    // Define thresholds/windows to decide presence vs absence (flexible, not fixed times)
    $windows = [
        'am_login_start' => '06:00', 'am_login_end' => '08:30',
        'am_logout_start' => '10:00', 'am_logout_end' => '12:30',
        'pm_login_start' => '12:30', 'pm_login_end' => '13:30',
        'pm_logout_start' => '15:00', 'pm_logout_end' => '17:30'
    ];

    $between = function($time, $start, $end){ return ($time >= $start && $time <= $end); };

    try {
        $conn->beginTransaction();

        foreach ($students as $student) {
            $lrn = $student['lrn'];
            $enrolled_date = $student['date_enrolled'];

            // student-specific start/end
            $start_date = max($enrolled_date, $sy_start);
            if ($start_date > $range_end) continue;

            $start = new DateTime($start_date);
            $end = new DateTime($range_end);

            for ($date = clone $start; $date <= $end; $date->modify('+1 day')) {
                $dateStr = $date->format('Y-m-d');
                $dayOfWeek = $date->format('l');

                // Skip weekends
                if ($dayOfWeek === 'Saturday' || $dayOfWeek === 'Sunday') continue;

                // Calendar check (skip full-day events, respect half-day)
                $suspended_half = null;
                if (isset($calendar[$dateStr])) {
                    $event = $calendar[$dateStr];
                    if (empty($event['half_day'])) continue; // full-day event - skip
                    $suspended_half = strtoupper($event['half_day']);
                }

                $existing = $attendanceMap[$lrn][$dateStr] ?? null;
                // decide morning/afternoon presence based on session timestamps if present
                $am_present = false;
                $pm_present = false;
                if ($existing) {
                    // if there is an am_login or am_logout recorded within AM windows -> present
                    $am_login = $existing['am_login_time'] ?? null;
                    $am_logout = $existing['am_logout_time'] ?? null;
                    $pm_login = $existing['pm_login_time'] ?? null;
                    $pm_logout = $existing['pm_logout_time'] ?? null;

                    if ($am_login && ($between($am_login, $windows['am_login_start'], $windows['am_login_end']) || $between($am_login, $windows['am_login_start'], $windows['am_logout_end']))) $am_present = true;
                    if ($am_logout && $between($am_logout, $windows['am_logout_start'], $windows['am_logout_end'])) $am_present = true;

                    if ($pm_login && $between($pm_login, $windows['pm_login_start'], $windows['pm_login_end'])) $pm_present = true;
                    if ($pm_logout && $between($pm_logout, $windows['pm_logout_start'], $windows['pm_logout_end'])) $pm_present = true;
                }

                // Decide whether to finalize morning/afternoon for this date
                $is_today = ($dateStr === $today);
                $finalize_morning = !$is_today || ($is_today && $current_time >= $windows['am_logout_end']);
                $finalize_afternoon = !$is_today || ($is_today && $current_time >= $windows['pm_logout_end']);

                // Determine final morning/afternoon statuses using finalize flags
                $morning_status = null;
                $afternoon_status = null;

                if ($suspended_half === 'AM') {
                    $morning_status = null; // no class
                } else {
                    if ($finalize_morning) {
                        $morning_status = $am_present ? 'Present' : 'Absent';
                    } else {
                        // still within AM window for today; don't set absent yet
                        $morning_status = $existing['morning_status'] ?? null;
                    }
                }

                if ($suspended_half === 'PM') {
                    $afternoon_status = null; // no class
                } else {
                    if ($finalize_afternoon) {
                        $afternoon_status = $pm_present ? 'Present' : 'Absent';
                    } else {
                        // still within PM window for today; don't set absent yet
                        $afternoon_status = $existing['afternoon_status'] ?? null;
                    }
                }

                if (!$existing) {
                    // If nothing to finalize for today, skip creating a placeholder row
                    if ($morning_status === null && $afternoon_status === null) {
                        continue;
                    }

                    $insertStmt->execute([
                        ':lrn' => $lrn,
                        ':date' => $dateStr,
                        ':morning_status' => $morning_status,
                        ':afternoon_status' => $afternoon_status,
                        ':am_login_time' => null,
                        ':am_logout_time' => null,
                        ':pm_login_time' => null,
                        ':pm_logout_time' => null
                    ]);
                } else {
                    // Update only if we finalized something (avoid overwriting pending values)
                    $needUpdate = false;
                    $u_morning = $existing['morning_status'];
                    $u_afternoon = $existing['afternoon_status'];

                    if ($morning_status !== $u_morning) {
                        $u_morning = $morning_status;
                        $needUpdate = true;
                    }
                    if ($afternoon_status !== $u_afternoon) {
                        $u_afternoon = $afternoon_status;
                        $needUpdate = true;
                    }

                    if ($needUpdate) {
                        $updateStmt->execute([
                            ':morning_status' => $u_morning,
                            ':afternoon_status' => $u_afternoon,
                            ':am_login_time' => $existing['am_login_time'] ?? null,
                            ':am_logout_time' => $existing['am_logout_time'] ?? null,
                            ':pm_login_time' => $existing['pm_login_time'] ?? null,
                            ':pm_logout_time' => $existing['pm_logout_time'] ?? null,
                            ':attend_id' => $existing['attend_id']
                        ]);
                    }
                }
            }
        }

        $conn->commit();
    } catch (Exception $ex) {
        // Rollback if something went wrong and rethrow or log
        if ($conn->inTransaction()) $conn->rollBack();
        error_log('mark_absent_students error: ' . $ex->getMessage());
        throw $ex;
    }
}
?>