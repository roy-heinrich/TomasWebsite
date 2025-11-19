<?php
require_once 'session.php';

// Handle logout request
if (isset($_POST['logout'])) {
    $_SESSION = array();
    session_destroy();
    header("Location: login.php");
    exit;
}

// Redirect to login if user is not logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Redirect if user is not a Teacher
if ($_SESSION['user']['user_role'] !== 'Teacher') {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$advisory_section = $user['advisory_section'];
$teacher_id = $user['id'];

require_once 'config.php';

// Compute profile image URL
$profileImage = $user['profile_image'] ?? '';
if (strpos($profileImage, 'http') === 0) {
    $profileImageUrl = $profileImage;
} else if (!empty($profileImage)) {
    $profileImageUrl = getSupabaseUrl($profileImage);
} else {
    $profileImageUrl = "https://ui-avatars.com/api/?name=" . urlencode($user['fullname']) . "&size=200&background=random";
}

// Get today's date
$today = date('Y-m-d');

// Get today's absent count (AM session)
$am_absent_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT a.student_lrn) as am_absent_count
    FROM attendance_tbl a
    WHERE a.date = :today
      AND a.morning_status = 'Absent'
      AND a.student_lrn IN (
        SELECT lrn FROM student_tbl WHERE year_section = :advisory_section
      )
");
$am_absent_stmt->execute([':today' => $today, ':advisory_section' => $advisory_section]);
$am_absent_count = $am_absent_stmt->fetchColumn();

// Get today's absent count (PM session)
$pm_absent_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT a.student_lrn) as pm_absent_count
    FROM attendance_tbl a
    WHERE a.date = :today
      AND a.afternoon_status = 'Absent'
      AND a.student_lrn IN (
        SELECT lrn FROM student_tbl WHERE year_section = :advisory_section
      )
");
$pm_absent_stmt->execute([':today' => $today, ':advisory_section' => $advisory_section]);
$pm_absent_count = $pm_absent_stmt->fetchColumn();

// Get students in teacher's section
$students_stmt = $conn->prepare("SELECT * FROM student_tbl WHERE year_section = :advisory_section AND status = 'Active'");
$students_stmt->execute([':advisory_section' => $advisory_section]);
$students = [];
while ($row = $students_stmt->fetch()) {
    $students[$row['lrn']] = $row;
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_report'])) {
        $student_lrn = $_POST['student_lrn'];
        $date_range_start = $_POST['date_range_start'];
        $date_range_end = $_POST['date_range_end'];
        $absent_sessions = (int)$_POST['absent_sessions'];
        $details = $_POST['details'];

        // Check parent phone for selected student
        $parent_stmt = $conn->prepare("SELECT parent_number FROM student_tbl WHERE lrn = :lrn LIMIT 1");
        $parent_stmt->execute([':lrn' => $student_lrn]);
        $parent_number = $parent_stmt->fetchColumn();

        // Normalize and validate basic phone presence (digits only) - adjust length check as needed
        $parent_digits = preg_replace('/\D+/', '', (string)$parent_number);
        if (empty($parent_digits) || strlen($parent_digits) < 7) {
            $_SESSION['error'] = "Cannot submit report: selected student does not have a valid parent phone number.";
            header("Location: AbsentReports.php#submit-report-section");
            exit;
        }

        $insert_stmt = $conn->prepare("
            INSERT INTO absent_reports_tbl 
            (student_lrn, teacher_id, date_range_start, date_range_end, absent_sessions, details, status)
            VALUES (:student_lrn, :teacher_id, :date_range_start, :date_range_end, :absent_sessions, :details, 'pending')
        ");
        $success = $insert_stmt->execute([
            ':student_lrn' => $student_lrn,
            ':teacher_id' => $teacher_id,
            ':date_range_start' => $date_range_start,
            ':date_range_end' => $date_range_end,
            ':absent_sessions' => $absent_sessions,
            ':details' => $details
        ]);
        if ($success) {
            $_SESSION['success'] = "Absent report submitted successfully!";
            header("Location: AbsentReports.php#submit-report-section");
            exit;
        } else {
            $_SESSION['error'] = "Error submitting report.";
        }
    }

    if (isset($_POST['update_report'])) {
        $report_id = $_POST['report_id'];
        $absent_sessions = (int)$_POST['absent_sessions'];
        $details = $_POST['details'];

        $update_stmt = $conn->prepare("
            UPDATE absent_reports_tbl 
            SET absent_sessions = :absent_sessions, details = :details
            WHERE report_id = :report_id AND teacher_id = :teacher_id
        ");
        $success = $update_stmt->execute([
            ':absent_sessions' => $absent_sessions,
            ':details' => $details,
            ':report_id' => $report_id,
            ':teacher_id' => $teacher_id
        ]);
        if ($success) {
            $_SESSION['success'] = "Report updated successfully!";
            header("Location: AbsentReports.php#submitted-reports-section");
            exit;
        } else {
            $_SESSION['error'] = "Error updating report.";
        }
    }

    if (isset($_POST['delete_report'])) {
        $report_id = $_POST['report_id'];
        $delete_stmt = $conn->prepare("DELETE FROM absent_reports_tbl WHERE report_id = :report_id AND teacher_id = :teacher_id");
        $success = $delete_stmt->execute([
            ':report_id' => $report_id,
            ':teacher_id' => $teacher_id
        ]);
        if ($success) {
            $_SESSION['success'] = "Report deleted successfully!";
            header("Location: AbsentReports.php#submitted-reports-section");
            exit;
        } else {
            $_SESSION['error'] = "Error deleting report.";
        }
    }

    // Archive/Unarchive report
   if (isset($_POST['archive_report']) || isset($_POST['unarchive_report'])) {
    $report_id = $_POST['report_id'];
    // Always set boolean explicitly
    $archive_status = isset($_POST['archive_report']) ? true : false;
    $archive_stmt = $conn->prepare("UPDATE absent_reports_tbl SET teacher_archived = :archived WHERE report_id = :report_id");
    $archive_stmt->bindValue(':archived', $archive_status, PDO::PARAM_BOOL);
    $archive_stmt->bindValue(':report_id', $report_id, PDO::PARAM_INT);
    $success = $archive_stmt->execute();
        if ($success) {
            $_SESSION['success'] = "Report " . ($archive_status ? "archived" : "unarchived") . " successfully!";
        } else {
            $_SESSION['error'] = "Error updating report.";
        }
        header("Location: AbsentReports.php#submitted-reports-section");
        exit;
    }

    header("Location: AbsentReports.php");
    exit;
}

// Pagination for Absence Records
$per_page_absences = 10;
$page_absences = isset($_GET['page_absences']) ? (int)$_GET['page_absences'] : 1;
$offset_absences = ($page_absences - 1) * $per_page_absences;

// Get filtered records with pagination
$filtered_records = [];
$total_absences = 0;

if (isset($_GET['filter'])) {
    $student_lrn = $_GET['student_lrn'];
    $date_from = $_GET['date_from'];
    $date_to = $_GET['date_to'];

    // Count total records for pagination
    $count_stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM attendance_tbl a
        JOIN student_tbl s ON a.student_lrn = s.lrn
        WHERE a.student_lrn = :student_lrn
          AND a.date BETWEEN :date_from AND :date_to
          AND (a.morning_status = 'Absent' OR a.afternoon_status = 'Absent')
    ");
    $count_stmt->execute([
        ':student_lrn' => $student_lrn,
        ':date_from' => $date_from,
        ':date_to' => $date_to
    ]);
    $total_absences = $count_stmt->fetchColumn();

    // Get paginated records
    $records_stmt = $conn->prepare("
        SELECT a.*, s.stud_name, s.year_section, s.gender
        FROM attendance_tbl a
        JOIN student_tbl s ON a.student_lrn = s.lrn
        WHERE a.student_lrn = :student_lrn
          AND a.date BETWEEN :date_from AND :date_to
          AND (a.morning_status = 'Absent' OR a.afternoon_status = 'Absent')
        ORDER BY a.date DESC
        LIMIT :limit OFFSET :offset
    ");
    $records_stmt->bindValue(':student_lrn', $student_lrn);
    $records_stmt->bindValue(':date_from', $date_from);
    $records_stmt->bindValue(':date_to', $date_to);
    $records_stmt->bindValue(':limit', $per_page_absences, PDO::PARAM_INT);
    $records_stmt->bindValue(':offset', $offset_absences, PDO::PARAM_INT);
    $records_stmt->execute();
    $filtered_records = $records_stmt->fetchAll();
}

// Pagination and Filtering for Submitted Reports
$per_page_reports = 10;
$page_reports = isset($_GET['page_reports']) ? (int)$_GET['page_reports'] : 1;
$offset_reports = ($page_reports - 1) * $per_page_reports;

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$show_archived = isset($_GET['show']) && $_GET['show'] === 'archived';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build reports query with filter
$reports_query = "
    SELECT r.*, s.stud_name 
    FROM absent_reports_tbl r
    JOIN student_tbl s ON r.student_lrn = s.lrn
    WHERE r.teacher_id = :teacher_id
";
$params = [':teacher_id' => $teacher_id];

if ($status_filter !== 'all') {
    $reports_query .= " AND r.status = :status";
    $params[':status'] = $status_filter;
}
$reports_query .= $show_archived ? " AND r.teacher_archived = TRUE" : " AND r.teacher_archived = FALSE";
if (!empty($search)) {
    $reports_query .= " AND (s.stud_name ILIKE :search OR CAST(r.report_id AS TEXT) ILIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// Count total reports for pagination
$count_reports_query = "
    SELECT COUNT(*) as total 
    FROM absent_reports_tbl r
    JOIN student_tbl s ON r.student_lrn = s.lrn
    WHERE r.teacher_id = :teacher_id
";
$count_params = [':teacher_id' => $teacher_id];
if ($status_filter !== 'all') {
    $count_reports_query .= " AND r.status = :status";
    $count_params[':status'] = $status_filter;
}
$count_reports_query .= $show_archived ? " AND r.teacher_archived = TRUE" : " AND r.teacher_archived = FALSE";
if (!empty($search)) {
    $count_reports_query .= " AND (s.stud_name ILIKE :search OR CAST(r.report_id AS TEXT) ILIKE :search)";
    $count_params[':search'] = '%' . $search . '%';
}

$count_reports_stmt = $conn->prepare($count_reports_query);
$count_reports_stmt->execute($count_params);
$total_reports = $count_reports_stmt->fetchColumn();

// Add pagination and ordering
$reports_query .= " ORDER BY r.submitted_at DESC LIMIT :limit OFFSET :offset";
$params[':limit'] = $per_page_reports;
$params[':offset'] = $offset_reports;

$reports_stmt = $conn->prepare($reports_query);
foreach ($params as $key => $value) {
    $reports_stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$reports_stmt->execute();
$reports = $reports_stmt->fetchAll();

// Get approved reports count
$approved_stmt = $conn->prepare("SELECT COUNT(*) FROM absent_reports_tbl WHERE teacher_id = :teacher_id AND status = 'approved'");
$approved_stmt->execute([':teacher_id' => $teacher_id]);
$approved_count = $approved_stmt->fetchColumn();

// Get pending reports count
$pending_stmt = $conn->prepare("SELECT COUNT(*) FROM absent_reports_tbl WHERE teacher_id = :teacher_id AND status = 'pending'");
$pending_stmt->execute([':teacher_id' => $teacher_id]);
$pending_count = $pending_stmt->fetchColumn();

// Get absentees for the last 30 days (not just top 3)
$thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
$top_absentees_stmt = $conn->prepare("
    SELECT s.stud_name, s.year_section,
        SUM(
            CASE WHEN a.morning_status = 'Absent' THEN 1 ELSE 0 END +
            CASE WHEN a.afternoon_status = 'Absent' THEN 1 ELSE 0 END
        ) AS absent_sessions
    FROM attendance_tbl a
    JOIN student_tbl s ON a.student_lrn = s.lrn
    WHERE a.date BETWEEN :from AND :to
      AND s.year_section = :advisory_section
      AND (a.morning_status = 'Absent' OR a.afternoon_status = 'Absent')
    GROUP BY a.student_lrn, s.stud_name, s.year_section
    HAVING SUM(
        CASE WHEN a.morning_status = 'Absent' THEN 1 ELSE 0 END +
        CASE WHEN a.afternoon_status = 'Absent' THEN 1 ELSE 0 END
    ) > 0
    ORDER BY absent_sessions DESC
");
$top_absentees_stmt->execute([
    ':from' => $thirty_days_ago,
    ':to' => $today,
    ':advisory_section' => $advisory_section
]);
$top_absentees = $top_absentees_stmt->fetchAll();

// Generate reset URLs
$reset_params = $_GET;
unset($reset_params['search'], $reset_params['status'], $reset_params['page_reports']);
$reset_params['show'] = 'active';
$reset_url = 'AbsentReports.php?' . http_build_query($reset_params) . '#submitted-reports-section';

$reset_archive_params = $_GET;
unset($reset_archive_params['search'], $reset_archive_params['status'], $reset_archive_params['page_reports']);
$reset_archive_params['show'] = 'archived';
$reset_archive_url = 'AbsentReports.php?' . http_build_query($reset_archive_params) . '#submitted-reports-section';

?>

<?php if (isset($_SESSION['success'])): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: "<?= addslashes($_SESSION['success']) ?>",
        confirmButtonColor: '#3085d6',
      });
    });
  </script>
  <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: "<?= addslashes($_SESSION['error']) ?>",
        confirmButtonColor: '#d33',
      });
    });
  </script>
  <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Absent Reports</title>

    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">
     <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

       <!-- Chart.js and Custom Chart Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
 <style>
/* Fix for overlapping sidebar collapse items */
        @media (min-width: 992px) {
            /* Increase z-index when sidebar is toggled */
            body.sidebar-toggled .sidebar {
                z-index: 1050 !important;
            }
            
            /* Increase z-index for collapse items */
            #collapsePages, #collapseAttendance {
                z-index: 1051 !important;
            }
            
            /* Ensure collapse items appear above content */
            .collapse {
                position: relative;
            }
        }


@media (min-width: 992px) {
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100%;
        width: 224px; /* default sidebar width */
      
    }

    #content-wrapper {
        margin-left: 224px;
       
    }


    body.sidebar-toggled #content-wrapper {
        margin-left: 105px; /* content follows sidebar */
    }
}



/* Hide admin name and title on collapse */
.sidebar.toggled #adminProfile .d-md-block,
.sidebar.toggled #sidebarTitle,
.sidebar.toggled hr.sidebar-divider {
    display: none !important;
}

/* Ensure text is white in profile */
#adminProfile .text-white {
    color: white !important;
}

/* Tighter spacing between name and title */
#adminProfile .admin-name {
    margin-bottom: -2px;
    line-height: 1;
     white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px; /* You can increase/decrease depending on sidebar width */
}
#adminProfile .admin-role {
    margin-top: -4px;
    line-height: 1;
}

/* Add top padding to admin image when sidebar is collapsed */
.sidebar.toggled #adminProfile {
    padding-top: 2rem !important;
}

 /* Padding for admin image in mobile view */
        @media (max-width: 767.98px) {
            #adminProfile {
                padding-top: 1.5rem !important;
                
            }
            #adminProfile img {
                width: 50px;
                height: 50px;
            }
        }

/* Custom hover effect for sidebar nav items */
.nav-item:not(.no-arrow):hover,
.nav-item:not(.no-arrow):hover .nav-link {
    background-color:rgba(68, 48, 248, 0.28) !important; /* Dark blue */
    color: white !important;
    border-radius: 0.35rem; /* Optional: round corners slightly like SB Admin 2 style */
}

/* Ensure icons and text inside stay white on hover */
.nav-item:not(.no-arrow):hover .nav-link i,
.nav-item:not(.no-arrow):hover .nav-link span {
    color: white !important;
} 

 /* New styles for loading indicators */
        .chart-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 10;
        }
        
        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

          :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --warning: #f72585;
            --light: #f8f9fa;
            --dark: #212529;
        }

           .badge-absent {
            background-color: #dc3545;
            color: white;
        }
        
        .badge-present {
            background-color: #28a745;
            color: white;
        }

         .summary-badge {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        @media (max-width: 576px) {
  .absences-title {
    font-size: 1.2rem !important;
  }
}
     

    .progress {
            height: 8px;
            border-radius: 4px;
            margin-top: 5px;
        }
        
        
        .top-absentee-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }
        
        .top-absentee-item:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .top-absentee-item:last-child {
            border-bottom: none;
        }

         .alert-warning-custom {
            background-color: #fff8e6;
            border-radius: 0 8px 8px 0;
        }

         .btn-primary-custom {
            background: linear-gradient(to right, var(--primary), var(--secondary));
            border: none;
            border-radius: 6px;
            font-weight: 500;
            color:rgb(249, 249, 249);

        }
        
        .btn-primary-custom:hover {
            background: linear-gradient(to right, #3a55d6, #3931b3);
            color:rgb(249, 249, 249);

        }

        @media (max-width: 576px) {
  /* Make card title text smaller */
  .card-header h5 {
    font-size: 1rem;
  }

  /* Smaller font inside absentee items */
  .top-absentee-item strong,
  .top-absentee-item span,
  .top-absentee-item .small {
    font-size: 0.85rem;
  }

  /* Reduce padding and spacing */
  .top-absentee-item {
    padding: 8px 10px;
  }

  .top-absentee-item .progress {
    height: 6px;
  }

  /* Smaller dropdown width */
  #timeFilter {
    font-size: 0.85rem;
    padding: 2px 6px;
  }

  /* Adjust icon spacing */
  .card-header i {
    margin-right: 6px;
    font-size: 0.9rem;
  }

  /* Optional: Reduce heading margins */
  .card-header h5.mb-0 {
    margin-bottom: 0;
  }
    .alert-warning-custom {
            background-color: #fff8e6;
            border-radius: 0 8px 8px 0;
    font-size: 0.8rem !important;
             
        }
}
   
    /* Submitted Reports Styles */
.submitted-reports .card-header {
  background-color: #4169E1;
  color: white;
  padding: 1rem;
  border-radius: 0.5rem 0.5rem 0 0;
}

.submitted-reports .card-header h5 {
  font-size: 1.1rem;
  font-weight: 600;
}

.submitted-reports .input-group input,
.submitted-reports .form-control {
  font-size: 0.9rem;
}

.status-badge {
  padding: 5px 10px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  color: white;
}

.badge-approved {
  background-color: #28a745;
}

.badge-rejected {
  background-color: #dc3545;
}

.badge-pending {
  background-color: #ffc107;
  color: black;
}

/* Table Styling */
.submitted-reports .table thead {
  background-color: #f1f3f5;
}

.submitted-reports .table td,
.submitted-reports .table th {
  vertical-align: middle;
  font-size: 0.9rem;
}

/* Pagination */
.submitted-reports .pagination .page-link {
  font-size: 0.85rem;
  padding: 6px 12px;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
  .submitted-reports .card-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }

  .submitted-reports .input-group {
    width: 100% !important;
    margin-bottom: 10px;
  }

  .submitted-reports #reportStatusFilter {
    width: 100% !important;
  }

}

/* ✅ Prioritize the title to the left */
@media (max-width: 768px) {
  .submitted-reports .card-header {
    flex-direction: column;
    align-items: flex-start !important;
    text-align: left;
    gap: 10px;
  }

  .submitted-reports .card-header h5 {
    width: 100%;
    text-align: left !important;
  }

  .submitted-reports .input-group,
  .submitted-reports #reportStatusFilter {
    width: 100% !important;
  }
}
.submitted-reports .table {
  min-width: 950px; /* Forces horizontal scroll on small screens */
}


    @media (max-width: 768px) {
  .absent-info-card .card-header h5 {
    font-size: 1rem;
  }

  .absent-info-card .alert-warning-custom {
    font-size: 0.8rem;
    padding: 8px 10px;
  }

  .absent-info-card ol.small {
    font-size: 0.8rem;
    padding-left: 1rem;
  }

  .absent-info-card h6 {
    font-size: 0.9rem;
    margin-top: 1rem;
  }

  .absent-info-card .card-body {
    padding: 1rem;
  }

  .absent-info-card {
    margin-bottom: 1rem;
  }
}

        /* Added styles for modals and buttons */
        .btn-action {
            padding: 5px 10px;
            margin: 0 2px;
            font-size: 0.85rem;
        }
        
        .modal-content {
            border-radius: 10px;
        }
        
        .modal-header {
            background-color: #4169E1;
            color: white;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .btn-submit-report {
            background: linear-gradient(to right, #4169E1, #3a55d6);
            color: white;
            font-weight: 600;
            padding: 8px 20px;
            border-radius: 6px;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-submit-report:hover {
            background: linear-gradient(to right, #3a55d6, #2a45c5);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            color:#eee;
        }
        
        .btn-submit-report:active {
            transform: translateY(0);
        }
        
        .edit-form-group {
            margin-bottom: 1.2rem;
        }
        
        .form-control:focus {
            border-color: #4169E1;
            box-shadow: 0 0 0 0.2rem rgba(65, 105, 225, 0.25);
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
        }
        
        .badge-approved {
            background-color: #28a745;
        }
        
        .badge-rejected {
            background-color: #dc3545;
        }
        
        .badge-pending {
            background-color: #ffc107;
            color: black;
        }
         /* New styles for responsive tables */
        .table-responsive {
            overflow-x: auto;
        }
        
        .scrollable-section {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-approved {
            background-color: #28a745;
            color: white;
        }
        
        .badge-pending {
            background-color: #ffc107;
            color: black;
        }
        
        .badge-rejected {
            background-color: #dc3545;
            color: white;
        }
        
        /* Pagination styles */
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .pagination-info {
            display: flex;
            align-items: center;
            margin-right: 15px;
            font-size: 0.9rem;
        }
        
        .pagination-links {
            display: flex;
        }
        
        .page-link {
            margin: 0 3px;
            border-radius: 4px;
            min-width: 30px;
            text-align: center;
        }
        
        .page-item.active .page-link {
            background-color: #4169E1;
            border-color: #4169E1;
        }
        
        .page-item.disabled .page-link {
            color: #6c757d;
        }

           #timeFilter {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background-image: none; /* remove default arrow image */
        padding-right: 10px; /* adjust for spacing */
    }

    /* Optional: For Chrome/Safari to remove default dropdown icon space */
    #timeFilter::-ms-expand {
        display: none; /* IE10+ */
    }
    
    /* Archive badge */
    .badge-archived {
        background-color: #6c757d;
        color: white;
    }

     /* Centered table headers and rows */
        .centered-table th,
        .centered-table td {
            text-align: center;
            vertical-align: middle;
        }
        
        /* Wider admin remarks column */
        .admin-remarks-cell {
            min-width: 200px;
            max-width: 300px;
            word-wrap: break-word;
        }
        
        /* Reset button styling */
        .reset-btn {
            margin-left: 10px;
        }

        @media (max-width: 768px) {
  .pagination-container {
    flex-direction: column;
    align-items: center;
  }
  
  .pagination-info {
    margin-right: 0;
    margin-bottom: 10px;
    text-align: center;
    width: 100%;
  }
  
  .pagination-links {
    width: 100%;
    justify-content: center;
  }
  
  .pagination {
    flex-wrap: wrap;
    justify-content: center;
  }
  
  .page-item {
    margin: 2px;
  }
  
}

/* Mobile view styles for View Absences Records table */
@media (max-width: 767.98px) {
    /* Make the table headers wider and more readable */
    #view-absences-section table th {
        min-width: 120px; /* Minimum width for all headers */
        white-space: nowrap; /* Prevent text wrapping */
        padding: 8px 5px; /* Adjust padding for better spacing */
        font-size: 0.8rem; /* Slightly larger font */
    }

    /* Specific column adjustments */
    #view-absences-section table th:nth-child(1) { /* Date column */
        min-width: 100px;
    }
    
    #view-absences-section table th:nth-child(2) { /* Student Name column */
        min-width: 150px;
    }
    
    #view-absences-section table th:nth-child(3), /* Year & Section */
    #view-absences-section table th:nth-child(4) { /* Gender */
        min-width: 80px;
    }
    
    #view-absences-section table th:nth-child(5) { /* LRN */
        min-width: 100px;
    }
    
    #view-absences-section table th:nth-child(6), /* AM Status */
    #view-absences-section table th:nth-child(7) { /* PM Status */
        min-width: 90px;
    }

    /* Adjust table cells to match headers */
    #view-absences-section table td {
        padding: 6px 3px;
        font-size: 0.8rem;
        white-space: nowrap;
    }

    /* Make the table horizontally scrollable */
    #view-absences-section .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    /* Adjust the filter form for mobile */
    #view-absences-section .row > div {
        margin-bottom: 10px;
    }
    
    #view-absences-section .btn {
        width: 100%;
    }
}

/* Extra small devices (phones, 575px and down) */
@media (max-width: 575.98px) {
    /* Make headers even wider on very small screens */
    #view-absences-section table th:nth-child(2) { /* Student Name */
        min-width: 130px;
    }
    
    #view-absences-section table th:nth-child(5) { /* LRN */
        min-width: 90px;
    }
    
    /* Reduce some padding to save space */
    #view-absences-section table th,
    #view-absences-section table td {
        padding: 5px 2px;
    }
}
    </style>
</head>

<body id="page-top">


    <!-- Page Wrapper -->
    <div id="wrapper">

     <!-- Sidebar -->
 <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar"> 
    


<!-- Sidebar Title -->
   <div class="sidebar-heading text-white pl-3 d-none d-md-block " id="sidebarTitle" style="font-size: 1.1rem; margin-top: 1rem; margin-bottom: 0.10rem; font-weight: 800;">
    TEACHER
</div>

    <!-- White Divider Below Title -->
    <hr class="sidebar-divider d-none d-md-block" style="border-top: 1px solid white; margin-top: 1px; margin-bottom: 8px;">

<!-- Admin Info Block -->
<div class="d-flex align-items-center justify-content-center flex-column flex-md-row text-center text-md-left px-3 mb-3 pt-2" id="adminProfile">
    <img src="<?= htmlspecialchars($profileImageUrl) ?>" class="rounded-circle mb-2 mb-md-0" width="45" height="45" alt="Admin Image"style="border: 1.5px solid gray;">
    <div class="ml-md-3 d-none d-md-block text-white">
      <div class="font-weight-bold admin-name" title="<?= htmlspecialchars($user['fullname']) ?>">
    <?= htmlspecialchars($user['fullname']) ?>
</div>
        <small class="admin-role"><?= htmlspecialchars($user['advisory_section']) ?></small>
    </div>
</div>

             <hr class="sidebar-divider">
              <!-- Nav Item - Dashboard -->
                <li class="nav-item"> 
                    <a class="nav-link" href="Teacher_Account.php">
                         <i class="fas fa-tachometer-alt"></i> 
                         <span>Dashboard</span></a>
                         </li>
                                                                           
                                                <!-- Nav Item - Student Records -->
                                                  <li class="nav-item"> 
                                                    <a class="nav-link" href="My_Students.php">
                                                         <i class="fas fa-user-graduate"></i> 
                                                         <span>My Students</span></a>
                                                         </li>

                                                        <!-- Nav Item - Student Attendance -->
                                                           <li class="nav-item"> 
                                                    <a class="nav-link" href="Section_Logs.php">
                                                         <i class="fas fa-list-alt"></i> 
                                                         <span>Attendance Logs</span></a>
                                                         </li>

                                                          <li class="nav-item active"> 
                                                    <a class="nav-link" href="AbsentReports.php">
                                                         <i class="fas fa-edit"></i> 
                                                         <span>Absent Reports</span></a>
                                                         </li>
                                                       
                                                           <li class="nav-item"> 
                                                    <a class="nav-link" href="barcode_scanner.php">
                                                         <i class="fas fa-calendar-week"></i> 
                                                         <span>Scan Attendance</span></a>
                                                         </li>

                                                              <!-- Divider -->
                                                                <hr class="sidebar-divider" style="margin-top: 20px;">
                                                                 <!-- Nav Item - Logout -->
                                                                   <li class="nav-item">
                                                                     <a class="nav-link" href="#" data-toggle="modal" data-target="#logoutModal">
                                                                        <i class="fas fa-sign-out-alt"></i>
                                                                         <span>Log Out</span></a>
                                                                         </li>
                                                                          <!-- Sidebar Toggler (Sidebar) -->
                                                                            <div class="text-center d-none d-md-inline"  style="margin-top: 20px;">
                                                                                 <button class="rounded-circle border-0" id="sidebarToggle"></button> 
                                                                                </div> 
                                                                            </ul>
        <!-- End of Sidebar -->


        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

                    <!-- Sidebar Toggle (Topbar) -->
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>

                    <!-- Topbar Search -->
                    <form
                        class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search">
                        <div class="input-group">
                            <input type="text" class="form-control bg-light border-0 small" placeholder="Search for..."
                                aria-label="Search" aria-describedby="basic-addon2">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="button">
                                    <i class="fas fa-search fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Topbar Navbar -->
                    <ul class="navbar-nav ml-auto">

                        <!-- Nav Item - Search Dropdown (Visible Only XS) -->
                        <li class="nav-item dropdown no-arrow d-sm-none">
                            <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-search fa-fw"></i>
                            </a>
                            <!-- Dropdown - Messages -->
                            <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in"
                                aria-labelledby="searchDropdown">
                                <form class="form-inline mr-auto w-100 navbar-search">
                                    <div class="input-group">
                                        <input type="text" class="form-control bg-light border-0 small"
                                            placeholder="Search for..." aria-label="Search"
                                            aria-describedby="basic-addon2">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button">
                                                <i class="fas fa-search fa-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </li>

                      
                      

                        <div class="topbar-divider d-none d-sm-block"></div>

                        <!-- Nav Item - User Information -->
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($user['fullname']) ?></span>
                                <img class="img-profile rounded-circle"
                                    src="<?= htmlspecialchars($profileImageUrl) ?>">
                            </a>
                            <!-- Dropdown - User Information -->
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#profileModal">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Profile
                                </a>

                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>

                    </ul>

                </nav>
                <!-- End of Topbar -->
              

                     <div class="container-fluid">
                    <!-- Dashboard Header -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-table" style="color: rgb(11, 104, 245); font-size: 1.3rem; margin-right: 0.8rem;"></i>
                            <h2 class="fw-bolder mb-0" style="color: rgb(11, 104, 245); font-size: 1.5rem; font-weight: 800;">Absent Reports</h2>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row g-4">
                        <!-- Approved Reports Card -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card shadow h-100 py-3 px-2" style="border-left: 8px solid #1cc88a;">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col mr-3">
                                            <div class="text-sm text-success text-uppercase mb-2" style="font-size: 1.2rem; font-weight: 800;">
                                                Approved Reports
                                            </div>
                                            <div class="mb-0 text-gray-800" style="font-size: 2rem; font-weight: 700;">
                                                <?= $approved_count ?>
                                            </div>
                                        </div>
                                        <div class="col-auto pe-3">
                                            <i class="fas fa-check-circle fa-4x text-success"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Today's Absent Card -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card shadow h-100 py-3 px-2" style="border-left: 8px solid #007bff;">
                                <!-- AM/PM Toggle Button -->
                                <div class="position-absolute" style="top: 10px; right: 10px;">
                                    <div class="btn-group btn-group-sm" role="group" aria-label="AM/PM Toggle">
                                        <button type="button" class="btn btn-outline-primary active py-1 px-2 session-toggle" data-session="am" style="font-size: 0.7rem;">AM</button>
                                        <button type="button" class="btn btn-outline-primary py-1 px-2 session-toggle" data-session="pm" style="font-size: 0.7rem;">PM</button>
                                    </div>
                                </div>
                                
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col mr-3">
                                            <div class="text-sm text-primary text-uppercase mb-2" style="font-size: 1.2rem; font-weight: 800;">
                                                Absent today
                                            </div>
                                            <div class="mb-0 text-gray-800" id="attendancePercentage" style="font-size: 2rem; font-weight: 700;">
                                                <?= $am_absent_count ?>
                                            </div>
                                        </div>
                                        <div class="col-auto pe-3">
                                            <i class="fas fa-user-clock fa-4x text-danger"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Reports Card -->
                        <div class="col-xl-4 col-md-6 mb-4">                             
                            <div class="card shadow h-100 py-3 px-2 position-relative" style="border-left: 8px solid #36b9cc;">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col mr-3">
                                            <div class="text-sm text-info text-uppercase mb-2" style="font-size: 1.2rem; font-weight: 800;">
                                                Pending Reports
                                            </div>
                                            <div class="mb-0 text-gray-800" style="font-size: 2rem; font-weight: 700;">
                                                <?= $pending_count ?>
                                            </div>
                                        </div>
                                        <div class="col-auto pe-3">
                                            <i class="fas fa-file-alt fa-4x text-info"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- View Absences & Records -->
                    <section class="py-4 mb-4" id="view-absences-section">
                        <div class="container-fluid px-1 px-sm-3">
                            <div class="card shadow" style="border-top: 8px solid #4169E1; border-radius: 1rem;">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                                        <h3 class="text-start mb-2 fw-bold text-primary absences-title" style="font-weight: 900;">
                                            <i class="fas fa-clipboard-list"></i> View Absences Records
                                        </h3>
                                        <span class="badge summary-badge">
                                            <i class="fas fa-info-circle mr-1"></i> Showing records: <?= $advisory_section ?>
                                        </span>
                                    </div>

                                    <!-- Filter Section -->
                                    <form method="GET" action="AbsentReports.php#view-absences-section">
                                        <input type="hidden" name="filter" value="1">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Student</label>
                                                <select class="form-control form-control-sm" name="student_lrn" required>
                                                    <option value="">Select Student</option>
                                                    <?php foreach ($students as $lrn => $student): ?>
                                                        <option value="<?= $lrn ?>" <?= isset($_GET['student_lrn']) && $_GET['student_lrn'] == $lrn ? 'selected' : '' ?>>
                                                            <?= $student['stud_name'] ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Date From</label>
                                                <input type="date" class="form-control form-control-sm" name="date_from" value="<?= isset($_GET['date_from']) ? $_GET['date_from'] : '' ?>" required>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">To</label>
                                                <input type="date" class="form-control form-control-sm" name="date_to" value="<?= isset($_GET['date_to']) ? $_GET['date_to'] : '' ?>" required>
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end mb-3">
                                                <button class="btn btn-primary btn-sm w-100" type="submit">
                                                    <i class="fas fa-search mr-1"></i> Filter
                                                </button>
                                            </div>
                                        </div>
                                    </form>

                                    <!-- Absences Table -->
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover align-middle text-center">
                                            <thead style="background-color: #4169E1; color: white;">
                                                <tr>                                        

                                                     <th>Date</th>
                                                    <th>Student Name</th>
                                                    <th>Year & Section</th>
                                                    <th>Gender</th>
                                                    <th>LRN</th>
                                                    <th>AM Status</th>
                                                    <th>PM Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($filtered_records)): ?>
                                                    <?php foreach ($filtered_records as $record): ?>
                                                        <tr>
                                                            <td><?= date('D, d M Y', strtotime($record['date'])) ?></td>
                                                            <td><?= $record['stud_name'] ?></td>
                                                            <td><?= $record['year_section'] ?></td>
                                                            <td><?= $record['gender'] ?></td>
                                                            <td><?= $record['student_lrn'] ?></td>
                                                            <td>
                                                                <span class="badge <?= $record['morning_status'] === 'Absent' ? 'badge-absent' : 'badge-present' ?>">
                                                                    <?= $record['morning_status'] ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge <?= $record['afternoon_status'] === 'Absent' ? 'badge-absent' : 'badge-present' ?>">
                                                                    <?= $record['afternoon_status'] ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                       <td colspan="7" class="text-center py-4">
                        <i class="fas fa-copy fa-4x text-muted mb-3"></i>
                        <p class="text-muted mb-0">No records found. Please select a student and date range to view absences.</p>
                    </td>   
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Pagination for Absence Records -->
                                    <?php if ($total_absences > 0): ?>
                                        <div class="pagination-container mt-4">
                                            <div class="pagination-info">
                                                Showing <?= min($per_page_absences, $total_absences - $offset_absences) ?> of <?= $total_absences ?> records
                                            </div>
                                            <div class="pagination-links">
                                                <ul class="pagination">
                                                    <?php if ($page_absences > 1): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_absences' => $page_absences - 1])) ?>">Previous</a>
                                                        </li>
                                                    <?php else: ?>
                                                        <li class="page-item disabled">
                                                            <a class="page-link" href="#">Previous</a>
                                                        </li>
                                                    <?php endif; ?>
                                                    
                                                    <?php 
                                                    $total_pages_absences = ceil($total_absences / $per_page_absences);
                                                    $start_page = max(1, $page_absences - 2);
                                                    $end_page = min($total_pages_absences, $start_page + 4);
                                                    
                                                    if ($start_page > 1) {
                                                        echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page_absences' => 1])) . '">1</a></li>';
                                                        if ($start_page > 2) {
                                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                        }
                                                    }
                                                    
                                                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                        <li class="page-item <?= $i == $page_absences ? 'active' : '' ?>">
                                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_absences' => $i])) ?>#view-absences-section"><?= $i ?></a>
                                                           
                                                        </li>
                                                    <?php endfor; 
                                                    
                                                    if ($end_page < $total_pages_absences) {
                                                        if ($end_page < $total_pages_absences - 1) {
                                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                        }
                                                        echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page_absences' => $total_pages_absences])) . '">' . $total_pages_absences . '</a></li>';
                                                    }
                                                    ?>
                                                    
                                                    <?php if ($page_absences < $total_pages_absences): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_absences' => $page_absences + 1])) ?>#view-absences-section">Next</a>
                                                        </li>
                                                    <?php else: ?>
                                                        <li class="page-item disabled">
                                                            <a class="page-link" href="#">Next</a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Top Absentees and Information Section -->
                    <div class="row mt-2">
                        <div class="col-md-7 mb-4">
                            <div class="card shadow" style="border-radius: 1rem;">
                                <div class="card-header text-white" style="background-color: #4169E1;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><i class="fas fa-clock mr-2 text-white"></i>Track Absences</h5>
                                        <select id="timeFilter" class="form-control form-control-sm w-auto">
                                            <option value="month" selected>Last 30 days</option>                                        
                                        </select>
                                    </div>
                                </div>
                                <div class="card-body scrollable-section">
                                    <?php if (!empty($top_absentees)): ?>
                                        <?php foreach ($top_absentees as $absentee): ?>
                                            <div class="top-absentee-item">
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between fw-bold">
                                                        <strong><?= $absentee['stud_name'] ?></strong>
                                                        <span class="text-danger"><?= $absentee['absent_sessions'] ?> sessions</span>
                                                    </div>

                                                   <div class="d-flex justify-content-between text-muted small">
                                                        <span><?= $absentee['year_section'] ?></span>
                                                         <span>
                                                       <?php 
                                                     $daysAbsent = $absentee['absent_sessions'] / 2;
                                                     echo (fmod($daysAbsent, 1) == 0) ? number_format($daysAbsent, 0) : number_format($daysAbsent, 1);
                                                                 ?> days absent
                                                        </span>
                                                    </div>        

                                                    <hr style="border: 2px solid #f08080; opacity: 1;">
                                                </div>
                                            </div>
                                     <?php endforeach; ?>
                          <?php else: ?>
                       <div class="text-center py-5" style="min-height: 300px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                      <i class="fas fa-check-circle text-primary mb-3" style="font-size: 4rem;"></i>
                      <p class="h5 text-muted">No significant absences in your class!</p>
                       </div>
                     <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-5 absent-info-card">
                            <div class="card shadow" style="border-radius: 1rem;">
                                <div class="card-header text-white" style="background-color: #4169E1;">
                                    <h5 class="mb-0"><i class="fas fa-info-circle mr-2 text-white"></i>Report Guidelines</h5>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-warning-custom">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        <strong>Note:</strong> 2 missed sessions = 1 day absent
                                    </div>

                                    <h6 class="mt-3"><i class="fas fa-bullhorn mr-2 text-primary"></i>Reporting Process</h6>
                                    <ol class="pl-4 small">
                                        <li>Teacher submits absent report</li>
                                        <li>Admin verifies the report</li>
                                        <li>Admin sends SMS to parents</li>
                                    </ol>

                                    <h6 class="mt-3"><i class="fas fa-lightbulb mr-2 text-warning"></i>Tips for Teachers</h6>
                                    <ol class="pl-4 small">
                                        <li>Double-check student name and LRN before submitting</li>
                                        <li>Select the correct date range to avoid report rejection</li>
                                        <li>Ensure the absent session count matches actual attendance logs</li>
                                        <li>Use the notes section for special circumstances or explanations</li>
                                        <li>Avoid submitting duplicate reports for the same period</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Absent Report Section -->
                    <div class="card mt-4 shadow-lg" id="submit-report-section">
                        <div class="card-header" style="background-color: #4169E1;">
                            <h5 class="mb-0" style="color:rgb(250, 250, 250);"><i class="fas fa-paper-plane mr-2 text-white"></i>Submit Absent Report</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning-custom mb-4">
                                <i class="fas fa-info-circle mr-2"></i>
                                    Select the date range and student(s) to report absences. Reports will be verified by the admin and then sent as SMS to parents. 
            <strong>A valid parent phone number is required, otherwise the report cannot be submitted.</strong>
                            </div>
                            
                            <form method="POST" id="absentReportForm" action="AbsentReports.php#submit-report-section">
                                <input type="hidden" name="submit_report" value="1">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label font-weight-bold">Student</label>
                                       <select class="form-control" name="student_lrn" required>
    <option value="">Select Student</option>
    <?php foreach ($students as $lrn => $student): ?>
        <option value="<?= $lrn ?>" data-parent="<?= htmlspecialchars($student['parent_number']) ?>">
            <?= $student['stud_name'] ?> (<?= $student['lrn'] ?>)
        </option>
    <?php endforeach; ?>
</select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label font-weight-bold">From Date</label>
                                        <input type="date" class="form-control" name="date_range_start" required>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label font-weight-bold">To Date</label>
                                        <input type="date" class="form-control" name="date_range_end" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label font-weight-bold">Absent Sessions Count</label>
                                        <input type="number" class="form-control" name="absent_sessions" id="absent_sessions_input" min="1" required>
                                        <small class="form-text text-muted">2 sessions = 1 day absent</small>
                                        <div id="absentSessionsHint" class="small text-muted mt-1">Auto-calculated sessions will appear here after selecting the date range.</div>
                                    </div>
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label font-weight-bold">Notes (Optional)</label>
                                        <textarea class="form-control" name="details" rows="3" placeholder="Add any additional details about the absences..."></textarea>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-end mt-2">
                                    <button type="reset" class="btn btn-outline-secondary mr-2">Reset</button>
                                   <button type="submit" class="btn btn-submit-report px-4" id="submitReportBtn">
                                 <span id="submitReportText"><i class="fas fa-paper-plane mr-2"></i>Submit Report</span>
                                 <span id="submitReportLoading" style="display:none;">
                                 <span class="spinner-border spinner-border-sm"></span> Submitting...
                                 </span>
                                 </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    
                     <!-- Submitted Reports Section -->
                    <div class="card mt-4 shadow submitted-reports" id="submitted-reports-section">                  
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="background-color: #4169E1;">
                            <h5 class="mb-0"><i class="fas fa-history mr-2 text-white"></i>
                                <?= $show_archived ? 'Archived Reports' : 'Submitted Reports' ?>
                            </h5>
                            
                            <div class="d-flex align-items-center flex-wrap">
                                <!-- Archive Toggle -->
                                <?php
                                $params_active = array_merge($_GET, ['show' => 'active']);
                                unset($params_active['page_reports']); // Reset pagination
                                $params_archived = array_merge($_GET, ['show' => 'archived']);
                                unset($params_archived['page_reports']);
                                ?>
                                <div class="btn-group btn-group-sm mr-2 mb-2">
                                    <a href="?<?= http_build_query($params_active) ?>#submitted-reports-section" 
                                       class="btn <?= !$show_archived ? 'btn-light active' : 'btn-outline-light' ?>">
                                        <i class="fas fa-list mr-1"></i> Active
                                    </a>
                                    <a href="?<?= http_build_query($params_archived) ?>#submitted-reports-section" 
                                       class="btn <?= $show_archived ? 'btn-light active' : 'btn-outline-light' ?>">
                                        <i class="fas fa-archive mr-1"></i> Archived
                                    </a>
                                </div>
                                
                                <!-- Search Bar -->
                                <form method="GET" action="AbsentReports.php#submitted-reports-section" class="mb-2 mr-2">
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="search" class="form-control form-control-sm" 
                                               placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-light" type="submit">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                        <input type="hidden" name="show" value="<?= $show_archived ? 'archived' : 'active' ?>">
                                        <input type="hidden" name="status" value="<?= $status_filter ?>">
                                        <?php if (isset($_GET['filter'])): ?>
                                            <input type="hidden" name="filter" value="1">
                                            <input type="hidden" name="student_lrn" value="<?= $_GET['student_lrn'] ?>">
                                            <input type="hidden" name="date_from" value="<?= $_GET['date_from'] ?>">
                                            <input type="hidden" name="date_to" value="<?= $_GET['date_to'] ?>">
                                        <?php endif; ?>
                                    </div>
                                </form>
                                
                                <!-- Status Filter -->
                                <form id="statusFilterForm" method="GET" action="AbsentReports.php#submitted-reports-section" class="mb-2">
                                    <select id="reportStatusFilter" name="status" class="form-control form-control-sm">
                                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                        <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                    </select>
                                    <input type="hidden" name="page_reports" value="1">
                                    <input type="hidden" name="show" value="<?= $show_archived ? 'archived' : 'active' ?>">
                                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                    <?php if (isset($_GET['filter'])): ?>
                                        <input type="hidden" name="filter" value="1">
                                        <input type="hidden" name="student_lrn" value="<?= $_GET['student_lrn'] ?>">
                                        <input type="hidden" name="date_from" value="<?= $_GET['date_from'] ?>">
                                        <input type="hidden" name="date_to" value="<?= $_GET['date_to'] ?>">
                                    <?php endif; ?>
                                </form>
                                
                                <!-- Reset Button -->
                                <div class="mb-2">
                                    <?php if (!$show_archived): ?>
                                        <a href="<?= $reset_url ?>" class="btn btn-outline-light btn-sm reset-btn">
                                            <i class="fas fa-sync-alt mr-1"></i> Reset
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= $reset_archive_url ?>" class="btn btn-outline-light btn-sm reset-btn">
                                            <i class="fas fa-sync-alt mr-1"></i> Reset
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover centered-table">
                                    <thead class="thead-light">
                                        <tr>                                                                          

                                        <th style="width: 90px;">Report ID</th>
                                          <th style="width: 200px;">Student</th>
                                          <th style="width: 180px;">Date Range</th>
                                          <th style="width: 80px;">Sessions</th>
                                          <th style="width: 100px;">Status</th>
                                          <th style="width: 200px;">Admin Remarks</th>
                                          <th style="width: 120px;">Submitted</th>
                                          <th style="width: 100px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($reports)): ?>
                                            <?php foreach ($reports as $report): ?>
                                                <tr>
                                                    <td class="text-center">#<?= $report['report_id'] ?></td>
                                                    <td class="text-center"><?= $report['stud_name'] ?></td>
                                                    <td class="text-center">
                                                        <?= date('M d', strtotime($report['date_range_start'])) ?> - 
                                                        <?= date('M d, Y', strtotime($report['date_range_end'])) ?>
                                                    </td>
                                                    <td class="text-center"><?= $report['absent_sessions'] ?></td>
                                                    <td class="text-center">
                                                        <?php 
                                                        $badge_class = '';
                                                        if ($report['status'] === 'approved') $badge_class = 'badge-approved';
                                                        elseif ($report['status'] === 'pending') $badge_class = 'badge-pending';
                                                        elseif ($report['status'] === 'rejected') $badge_class = 'badge-rejected';
                                                        ?>
                                                        <span class="status-badge <?= $badge_class ?>">
                                                            <?= ucfirst($report['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center admin-remarks-cell"><?= $report['admin_remarks'] ?: '--' ?></td>
                                                    <td class="text-center"><?php $dt = new DateTime($report['submitted_at'], new DateTimeZone('UTC'));$dt->setTimezone(new DateTimeZone('Asia/Manila'));echo $dt->format('M d, Y h:i A');?></td>
                                                    <td class="text-center">
                                                         <?php if ($report['status'] === 'pending'): ?>
        <!-- Pending: Show Edit and Delete -->
        <button class="btn btn-action btn-outline-primary edit-btn" 
                data-id="<?= $report['report_id'] ?>"
                data-student="<?= $report['student_lrn'] ?>"
                data-start="<?= $report['date_range_start'] ?>"
                data-end="<?= $report['date_range_end'] ?>"
                data-sessions="<?= $report['absent_sessions'] ?>"
                data-details="<?= htmlspecialchars($report['details']) ?>">
            <i class="fas fa-edit"></i>
        </button>
        <form method="POST" style="display:inline;" class="delete-form">
            <input type="hidden" name="delete_report" value="1">
            <input type="hidden" name="report_id" value="<?= $report['report_id'] ?>">
            <button type="button" class="btn btn-action btn-outline-danger delete-btn">
                <i class="fas fa-trash"></i>
            </button>
        </form>
    <?php else: ?>
        <!-- Approved/Rejected: Show Archive Button -->
        <form method="POST" style="display:inline;" class="archive-form">
            <input type="hidden" name="report_id" value="<?= $report['report_id'] ?>">
            <?php if (!$report['teacher_archived']): ?>
                <button type="button" class="btn btn-action btn-outline-warning archive-btn" title="Archive Report">
                    <i class="fas fa-archive"></i>
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-action btn-outline-success unarchive-btn" title="Restore Report">
                    <i class="fas fa-trash-restore"></i>
                </button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center">No reports found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                           
                            <!-- Pagination for Submitted Reports -->
                            <?php if ($total_reports > 0): ?>
                                <div class="pagination-container mt-4">
                                    <div class="pagination-info">
                                        Showing <?= min($per_page_reports, $total_reports - $offset_reports) ?> of <?= $total_reports ?> reports
                                    </div>
                                    <div class="pagination-links">
                                        <ul class="pagination">
                                            <?php if ($page_reports > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_reports' => $page_reports - 1])) ?>#submitted-reports-section">Previous</a>
                                                </li>
                                            <?php else: ?>
                                                <li class="page-item disabled">
                                                    <a class="page-link" href="#">Previous</a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php 
                                            $total_pages_reports = ceil($total_reports / $per_page_reports);
                                            $start_page = max(1, $page_reports - 2);
                                            $end_page = min($total_pages_reports, $start_page + 4);
                                            
                                            if ($start_page > 1) {
                                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page_reports' => 1])) . '">1</a></li>';
                                                if ($start_page > 2) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                            }
                                            
                                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                <li class="page-item <?= $i == $page_reports ? 'active' : '' ?>">
                                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_reports' => $i])) ?>#submitted-reports-section"><?= $i ?></a>
                                                </li>
                                            <?php endfor; 
                                            
                                            if ($end_page < $total_pages_reports) {
                                                if ($end_page < $total_pages_reports - 1) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page_reports' => $total_pages_reports])) . '">' . $total_pages_reports . '</a></li>';
                                            }
                                            ?>
                                            
                                            <?php if ($page_reports < $total_pages_reports): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page_reports' => $page_reports + 1])) ?>#submitted-reports-section">Next</a>
                                                </li>
                                            <?php else: ?>
                                                <li class="page-item disabled">
                                                    <a class="page-link" href="#">Next</a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

          

                <!-- Edit Report Modal -->
    <div class="modal fade" id="editReportModal" tabindex="-1" role="dialog" aria-labelledby="editReportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editReportModalLabel"><i class="fas fa-edit mr-2"></i>Edit Absent Report</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editReportForm">
                        <input type="hidden" name="update_report" value="1">
                        <input type="hidden" name="report_id" id="edit_report_id">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold">Student</label>
                                <select class="form-control" id="edit_student_lrn" disabled>
                                    <?php foreach ($students as $lrn => $student): ?>
                                        <option value="<?= $lrn ?>"><?= $student['stud_name'] ?> (<?= $student['lrn'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label font-weight-bold">From Date</label>
                                <input type="date" class="form-control" id="edit_date_range_start" disabled>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label font-weight-bold">To Date</label>
                                <input type="date" class="form-control" id="edit_date_range_end" disabled>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label font-weight-bold">Absent Sessions Count</label>
                                <input type="number" class="form-control" name="absent_sessions" id="edit_absent_sessions" min="1" required>
                                <small class="form-text text-muted">2 sessions = 1 day absent</small>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label font-weight-bold">Notes</label>
                                <textarea class="form-control" name="details" id="edit_details" rows="3"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveChangesBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>


                  

<div id="loadingOverlay" 
     style="display:none; 
            position:fixed; 
            top:0; 
            left:0; 
            width:100vw; 
            height:100vh; 
            background:rgba(0,0,0,0.6); 
            z-index:9999; 
            align-items:center; 
            justify-content:center;">
    <div style="text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center; height:100vh;">
        <div class="loading-spinner"></div>
        <div style="font-weight:600; color:#fff; margin-top:10px;">Loading...</div>
    </div>
</div>




 <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; 2025 Tomas SM. Bautista Elementary School.<br> All rights reserved.</span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->


    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal-->
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="exampleModalLabel"><i class="fas fa-sign-out-alt mr-2 text-white"></i>Confirm Logout</h5>
                <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">Are you sure you want to <strong>Logout</strong>? This will sign you out of your account. </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                <form method="POST" action="" style="display: inline;">
                    <button type="submit" name="logout" class="btn btn-primary">Logout</button>
                </form>
            </div>
        </div>
    </div>
</div>


         <!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="profileForm" action="update_profile.php" method="POST" enctype="multipart/form-data">
      <div class="modal-content">
        <div class="modal-header" style="background-color: #007bff; color: white;">
          <h5 class="modal-title" id="profileModalLabel">Edit Profile</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white;">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id" value="<?= $user['id'] ?>">

          <!-- Profile Image Upload + Preview -->
          <div class="form-group d-flex align-items-center">
            <img id="previewImagee" src="<?= htmlspecialchars($profileImageUrl) ?>" alt="Preview" style="width: 80px; height: 80px; border-radius: 10px; border: 1px solid #ccc; object-fit: cover; margin-right: 15px;">
            <div style="flex: 1;">
              <label><strong>Profile Image</strong></label>
              <input type="file" name="profile_image" class="form-control-file" id="profileImageInput" accept=".jpg,.jpeg,.png,.webp">
            </div>
          </div>

          <!-- Profile Fields -->
          <div class="form-group">
            <label><strong>Full Name</strong></label>
            <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname']) ?>" required>
          </div>

          <div class="form-group">
            <label><strong>Username</strong></label>
            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
          </div>

          <div class="form-group">
            <label><strong>Email</strong></label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
          </div>

          <hr>
          <h6><strong>Change Password (Optional)</strong></h6>

          <div class="form-group position-relative">
            <label>Current Password</label>
            <input type="password" name="current_password" class="form-control password-field" id="currentPassword">
            <i class="fas fa-eye eye-icon" onclick="togglePassword('currentPassword', this)" style="position: absolute; right: 15px; top: 42px; cursor: pointer;"></i>
          </div>

          <div class="form-group position-relative">
            <label>New Password</label>
            <input type="password" name="new_password" class="form-control password-field" id="newPassword">
            <i class="fas fa-eye eye-icon" onclick="togglePassword('newPassword', this)" style="position: absolute; right: 15px; top: 42px; cursor: pointer;"></i>
          </div>
        </div>

        <div class="modal-footer">
            <button type="submit" name="update_profile" class="btn btn-primary" id="saveProfileBtn">
    <span class="btn-text">Save Changes</span>
</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>


    <!-- Bootstrap core JavaScript-->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="js/sb-admin-2.min.js"></script>

    <!-- Page level plugins -->
    <script src="vendor/chart.js/Chart.min.js"></script>



    <script>
function showLoadingOverlay() {
    $('#loadingOverlay').fadeIn(100);
}
function hideLoadingOverlay() {
    $('#loadingOverlay').fadeOut(100);
}

// --- VERIFY ABSENCES SECTION ---

// Filter form submit
$('form[action*="AbsentReports.php#view-absences-section"]').on('submit', function() {
    showLoadingOverlay();
});

// Pagination links
$(document).on('click', '#view-absences-section .pagination .page-link', function(e) {
    showLoadingOverlay();
    // Let browser follow the link
});

// --- SUBMITTED REPORTS SECTION (ACTIVE/ARCHIVED) ---

// Search form submit (active)
$('form[action*="AbsentReports.php#submitted-reports-section"]').on('submit', function() {
    showLoadingOverlay();
});

// Status filter change (active)
$('#reportStatusFilter').on('change', function() {
    showLoadingOverlay();
    $(this).closest('form').submit();
});

// Reset button (active)
$('.reset-btn').on('click', function(e) {
    showLoadingOverlay();
    // Let browser follow the link
});

// Pagination links (active/archived)
$(document).on('click', '#submitted-reports-section .pagination .page-link', function(e) {
    showLoadingOverlay();
});

// Toggle active/archived buttons
$(document).on('click', '#submitted-reports-section .btn-group .btn', function(e) {
    showLoadingOverlay();
});

// Status filter change (archived)
$('#archivedReportStatusFilter').on('change', function() {
    showLoadingOverlay();
    $(this).closest('form').submit();
});

// Search form submit (archived)
$('#archivedFilterForm').on('submit', function() {
    showLoadingOverlay();
});
</script>



    <script>
        $(document).ready(function() {

               if (window.location.hash) {
        const target = $(window.location.hash);
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 20
            }, 500);
        }
    }

 
    // Archive/Unarchive button with SweetAlert loading state
    $(document).on('click', '.archive-btn, .unarchive-btn', function(e) {
        e.preventDefault();
        const form = $(this).closest('form');
        const isArchive = $(this).hasClass('archive-btn');
        const actionText = isArchive ? 'archive' : 'restore';

        Swal.fire({
            title: `Confirm ${isArchive ? 'Archiving' : 'Restoring'}`,
            text: `Are you sure you want to ${actionText} this report?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: isArchive ? '#ffc107' : '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `Yes, ${actionText} it!`
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading overlay and message
               
                Swal.fire({
                    title: isArchive ? 'Archiving...' : 'Restoring...',
                    text: `Please wait while the report is being ${actionText}d.`,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                // Add hidden input for action and submit form
                const actionInput = $('<input>')
                    .attr('type', 'hidden')
                    .attr('name', isArchive ? 'archive_report' : 'unarchive_report')
                    .val('1');
                form.append(actionInput);
                form.submit();
            }
        });
    });
    

            // Handle AM/PM toggle
            $('.session-toggle').click(function() {
                $('.session-toggle').removeClass('active');
                $(this).addClass('active');
                
                const session = $(this).data('session');
                if (session === 'am') {
                    $('#attendancePercentage').text('<?= $am_absent_count ?>');
                } else {
                    $('#attendancePercentage').text('<?= $pm_absent_count ?>');
                }
            });
            
          // Handle edit button click
            $(document).on('click', '.edit-btn', function() {
                const reportId = $(this).data('id');
                const studentLrn = $(this).data('student');
                const dateStart = $(this).data('start');
                const dateEnd = $(this).data('end');
                const sessions = $(this).data('sessions');
                const details = $(this).data('details');
                
                $('#edit_report_id').val(reportId);
                $('#edit_student_lrn').val(studentLrn);
                $('#edit_date_range_start').val(dateStart);
                $('#edit_date_range_end').val(dateEnd);
                $('#edit_absent_sessions').val(sessions);
                $('#edit_details').val(details);
                
                $('#editReportModal').modal('show');
            });
            
            // Save changes in the modal
            $('#saveChangesBtn').click(function() {
                $('#editReportForm').submit();
            });
        
            
          // Validate selected student has parent number on change
$('#absentReportForm select[name="student_lrn"]').on('change', function() {
    const parent = $(this).find('option:selected').data('parent') || '';
    const clean = ('' + parent).replace(/\D/g, '');
    if (!clean || clean.length < 7) {
        $('#submitReportBtn').prop('disabled', true);
        if (!$('#noParentAlert').length) {
            $('<div id="noParentAlert" class="mt-2 text-danger small">Selected student has no valid parent phone number. Report cannot be submitted.</div>')
                .insertAfter('#absentReportForm select[name="student_lrn"]');
        }
    } else {
        $('#submitReportBtn').prop('disabled', false);
        $('#noParentAlert').remove();
    }
});

// Replace existing submit handler with parent-check + confirmation + loading
$('#absentReportForm').off('submit').on('submit', function(e) {
    e.preventDefault();

    // Parent presence guard (client-side)
    const parent = $(this).find('select[name="student_lrn"] option:selected').data('parent') || '';
    const clean = ('' + parent).replace(/\D/g, '');
    if (!clean || clean.length < 7) {
        Swal.fire({
            icon: 'error',
            title: 'Missing Parent Number',
            text: 'Selected student does not have a valid parent phone number. Please update student info before reporting.',
        });
        return;
    }

    const startDate = new Date($('input[name="date_range_start"]').val());
    const endDate = new Date($('input[name="date_range_end"]').val());

    if (startDate > endDate) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Date Range',
            text: 'The start date must be before the end date.',
        });
        return;
    }

    Swal.fire({
        title: 'Confirm Submission',
        text: 'Are you sure you want to submit this absent report?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, submit it!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            $('#submitReportBtn').prop('disabled', true);
            $('#submitReportText').hide();
            $('#submitReportLoading').show();
            // submit the native form
            e.currentTarget.submit();
        }
    });
});
            
            // Delete button with SweetAlert
           $(document).on('click', '.delete-btn', function() {
                const form = $(this).closest('form');
                
                Swal.fire({
                    title: 'Delete Report?',
                    text: 'Are you sure you want to delete this report? This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
            
            // Show success/error messages
            <?php if (isset($_SESSION['success'])): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '<?= addslashes($_SESSION['success']) ?>',
                    confirmButtonColor: '#3085d6',
                });
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: '<?= addslashes($_SESSION['error']) ?>',
                    confirmButtonColor: '#d33',
                });
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            // Status filter change
            $('#reportStatusFilter').change(function() {
                $('#statusFilterForm').submit();
            });
        });
    </script>

    

<script>
// Profile image upload validation
        $('#profileImageInput').change(function(event) {
            const file = event.target.files[0];
            const maxSize = 25 * 1024 * 1024; // 25MB

            if (file) {
                if (file.size > maxSize) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Too Large',
                        text: 'The selected image exceeds 25MB. Please choose a smaller file.',
                        confirmButtonColor: '#d33',
                    });
                    event.target.value = '';
                    return;
                }

                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid File Type',
                        text: 'Only JPG, JPEG, PNG, and WEBP are allowed.',
                        confirmButtonColor: '#d33',
                    });
                    event.target.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImagee').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });



  // Toggle show/hide for password fields
 function togglePassword(fieldId, icon) {
    const field = document.getElementById(fieldId);
    const isHidden = field.type === 'password';
    
    field.type = isHidden ? 'text' : 'password';

    // Toggle icons
    icon.classList.toggle('fa-eye', !isHidden);
    icon.classList.toggle('fa-eye-slash', isHidden);
  }

  $('#profileForm').on('submit', function() {
    $('#saveProfileBtn').prop('disabled', true);
    $('#saveProfileBtn .btn-text').hide();
    $('#saveProfileBtn').append('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
});
</script>
    <script>
    (function($){
        const $student = $('#absentReportForm select[name="student_lrn"]');
        const $from = $('#absentReportForm input[name="date_range_start"]');
        const $to = $('#absentReportForm input[name="date_range_end"]');
        const $sessions = $('#absent_sessions_input');
        const $hint = $('#absentSessionsHint');
        let debounceTimer = null;
        const debounceMS = 450;

        function showHint(msg, isError=false){
            $hint.text(msg);
            $hint.toggleClass('text-danger', isError);
        }

        function computeAbsentSessions(){
            const lrn = $student.val();
            const d1 = $from.val();
            const d2 = $to.val();
            if (!lrn || !d1 || !d2) {
                return;
            }
            // validate date order
            let from = new Date(d1);
            let to = new Date(d2);
            if (from > to) {
                // swap client-side for UX convenience
                const tmp = d1;
                $from.val(d2);
                $to.val(tmp);
                from = new Date($from.val());
                to = new Date($to.val());
            }
            // No client-side limit — allow any range. Server will handle performance using the DB index.

            showHint('Calculating...');
            // POST to AJAX endpoint
            $.post('ajax/get_absent_sessions.php', {
                student_lrn: lrn,
                date_from: $from.val(),
                date_to: $to.val()
            }).done(function(res){
                if (res && res.success) {
                    $sessions.val(res.absent_sessions);
                    showHint('Auto-filled: ' + res.absent_sessions + ' session(s)');
                } else {
                    showHint(res && res.message ? res.message : 'Could not calculate absent sessions', true);
                }
            }).fail(function(){
                showHint('Server error while calculating', true);
            });
        }

        function scheduleCompute(){
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(computeAbsentSessions, debounceMS);
        }

        $student.on('change', scheduleCompute);
        $from.on('change', scheduleCompute);
        $to.on('change', scheduleCompute);

        // Trigger initial compute if values prefilled
        $(function(){
            if ($student.val() && $from.val() && $to.val()) {
                scheduleCompute();
            }
        });
    })(jQuery);
    </script>

 <?php include 'search/Search_Teacher.php'; ?>

</body>

</html> 