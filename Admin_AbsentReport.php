<?php
date_default_timezone_set('Asia/Manila');
require_once 'session.php';

// Handle logout request
if (isset($_POST['logout'])) {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header("Location: login.php");
    exit;
}

// Redirect to login if user is not logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Redirect if user is not an Admin
if ($_SESSION['user']['user_role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle approval with admin remarks
    if (isset($_POST['approve_report']) || (isset($_POST['form_action']) && $_POST['form_action'] === 'approve')) {
        $report_id = $_POST['report_id'];
        $admin_remarks = $_POST['admin_remarks'] ?? '';
        
        $stmt = $conn->prepare("UPDATE absent_reports_tbl SET status = 'approved', admin_remarks = :admin_remarks WHERE report_id = :report_id");
        $stmt->bindParam(':admin_remarks', $admin_remarks);
        $stmt->bindParam(':report_id', $report_id);
        
        if ($stmt->execute()) {
            // Fetch report details to generate SMS
            $report_sql = "SELECT ar.*, s.stud_name, s.parent_number, st.fullname AS teacher_name
                           FROM absent_reports_tbl ar
                           JOIN student_tbl s ON ar.student_lrn = s.lrn
                           JOIN staff_tbl st ON ar.teacher_id = st.id
                           WHERE ar.report_id = :report_id";
            $report_stmt = $conn->prepare($report_sql);
            $report_stmt->bindParam(':report_id', $report_id);
            $report_stmt->execute();
            $report_data = $report_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($report_data) {
                // Generate SMS message
                $days = $report_data['absent_sessions'] / 2;
                $days_display = ($days == floor($days)) ? (int)$days : number_format($days, 1);
                
                $message = "Dear Parent, this is Tomas S.M. Bautista Elementary School. We would like to inform you that your child, " . 
                    htmlspecialchars($report_data['stud_name']) . ", has been absent for " . 
                    $report_data['absent_sessions'] . " session(s) equivalent to " . $days_display . " day(s) from " . 
                    date('M j', strtotime($report_data['date_range_start'])) . " to " . 
                    date('M j, Y', strtotime($report_data['date_range_end'])) . ". " . 
                    "Please follow up with the student's adviser for more details. Thank you for your attention.";

                // Insert into SMS queue
                $sms_stmt = $conn->prepare("INSERT INTO sms_queue (report_id, student_lrn, parent_number, message, status) 
                                           VALUES (:report_id, :student_lrn, :parent_number, :message, 'pending')");
                $sms_stmt->bindParam(':report_id', $report_id);
                $sms_stmt->bindParam(':student_lrn', $report_data['student_lrn']);
                $sms_stmt->bindParam(':parent_number', $report_data['parent_number']);
                $sms_stmt->bindParam(':message', $message);
                $sms_stmt->execute();
            }
            
            $_SESSION['success'] = "Report approved and SMS queued for sending!";
        } else {
            $_SESSION['error'] = "Error approving report: " . $stmt->errorInfo()[2];
        }
        header("Location: Admin_AbsentReport.php");
        exit;
    }
    
    // Handle rejection with admin remarks
    if (isset($_POST['reject_report']) || (isset($_POST['form_action']) && $_POST['form_action'] === 'reject')) {
        $report_id = $_POST['report_id'];
        $admin_remarks = $_POST['admin_remarks'] ?? '';
        
        $stmt = $conn->prepare("UPDATE absent_reports_tbl SET status = 'rejected', admin_remarks = :admin_remarks WHERE report_id = :report_id");
        $stmt->bindParam(':admin_remarks', $admin_remarks);
        $stmt->bindParam(':report_id', $report_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Report rejected successfully!";
        } else {
            $_SESSION['error'] = "Error rejecting report: " . $stmt->errorInfo()[2];
        }
        header("Location: Admin_AbsentReport.php");
        exit;
    }
    
    // Handle updating admin remarks for processed reports
    if (isset($_POST['update_remarks'])) {
        $report_id = $_POST['report_id'];
        $admin_remarks = $_POST['admin_remarks'] ?? '';
        
        $stmt = $conn->prepare("UPDATE absent_reports_tbl SET admin_remarks = :admin_remarks WHERE report_id = :report_id");
        $stmt->bindParam(':admin_remarks', $admin_remarks);
        $stmt->bindParam(':report_id', $report_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Admin remarks updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating remarks: " . $stmt->errorInfo()[2];
        }
        header("Location: Admin_AbsentReport.php");
        exit;
    }
    
    // Handle archive/unarchive
    if (isset($_POST['archive_report']) || isset($_POST['unarchive_report'])) {
        $report_id = $_POST['report_id'];
        $archive_status = isset($_POST['archive_report']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE absent_reports_tbl SET admin_archived = :admin_archived WHERE report_id = :report_id");
        $stmt->bindParam(':admin_archived', $archive_status);
        $stmt->bindParam(':report_id', $report_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Report " . ($archive_status ? "archived" : "unarchived") . " successfully!";
        } else {
            $_SESSION['error'] = "Error updating report: " . $stmt->errorInfo()[2];
        }
        header("Location: Admin_AbsentReport.php");
        exit;
    }
}

// Pagination variables - separate for pending, processed and archived reports
$page_pending = isset($_GET['page_pending']) ? (int)$_GET['page_pending'] : 1;
$page_processed = isset($_GET['page_processed']) ? (int)$_GET['page_processed'] : 1;
$page_archived = isset($_GET['page_archived']) ? (int)$_GET['page_archived'] : 1;
$perPage = 10;
$offset_pending = ($page_pending - 1) * $perPage;
$offset_processed = ($page_processed - 1) * $perPage;
$offset_archived = ($page_archived - 1) * $perPage;

// Fetch pending reports for verification
$verify_sql = "SELECT ar.report_id, ar.student_lrn, ar.date_range_start, ar.date_range_end, ar.absent_sessions, ar.details,
                      s.stud_name, s.year_section, s.parent_number,
                      t.fullname AS teacher_name
               FROM absent_reports_tbl ar
               JOIN student_tbl s ON ar.student_lrn = s.lrn
               JOIN staff_tbl t ON ar.teacher_id = t.id
               WHERE ar.status = 'pending'
                ORDER BY ar.submitted_at ASC
               LIMIT :perPage OFFSET :offset";
               
$verify_stmt = $conn->prepare($verify_sql);
$verify_stmt->bindParam(':perPage', $perPage, PDO::PARAM_INT);
$verify_stmt->bindParam(':offset', $offset_pending, PDO::PARAM_INT);
$verify_stmt->execute();
$verify_reports = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($verify_reports as &$row) {
    // Calculate actual absent sessions (count each AM/PM as separate sessions)
    $actual_sql = "SELECT 
                   SUM(CASE WHEN morning_status = 'Absent' THEN 1 ELSE 0 END) +
                   SUM(CASE WHEN afternoon_status = 'Absent' THEN 1 ELSE 0 END) AS absent_count
               FROM attendance_tbl
               WHERE student_lrn = :student_lrn 
               AND date BETWEEN :date_start AND :date_end";
    $actual_stmt = $conn->prepare($actual_sql);
    $actual_stmt->bindParam(':student_lrn', $row['student_lrn']);
    $actual_stmt->bindParam(':date_start', $row['date_range_start']);
    $actual_stmt->bindParam(':date_end', $row['date_range_end']);
    $actual_stmt->execute();
    $actual_data = $actual_stmt->fetch(PDO::FETCH_ASSOC);
    $row['actual_sessions'] = $actual_data['absent_count'] ?? 0;
}

// Count total pending reports for pagination
$total_sql = "SELECT COUNT(*) AS total FROM absent_reports_tbl WHERE status = 'pending'";
$total_result = $conn->query($total_sql);
$total_row = $total_result->fetch(PDO::FETCH_ASSOC);
$total_pending = $total_row['total'];
$totalPagesPending = ceil($total_pending / $perPage);

// Fetch SMS queue
$pending_sms_stmt = $conn->query("SELECT sq.*, s.stud_name 
                             FROM sms_queue sq
                             JOIN student_tbl s ON sq.student_lrn = s.lrn
                             WHERE sq.status = 'pending'
                             ORDER BY sq.id DESC");
$pending_sms = $pending_sms_stmt->fetchAll(PDO::FETCH_ASSOC);
                             
$sent_sms_stmt = $conn->query("SELECT sq.*, s.stud_name 
                          FROM sms_queue sq
                          JOIN student_tbl s ON sq.student_lrn = s.lrn
                          WHERE sq.status = 'sent'
                          ORDER BY sq.sent_at DESC");
$sent_sms = $sent_sms_stmt->fetchAll(PDO::FETCH_ASSOC);
                          
$deleted_sms_stmt = $conn->query("SELECT sq.*, s.stud_name 
                              FROM sms_queue sq
                             JOIN student_tbl s ON sq.student_lrn = s.lrn
                             WHERE sq.status = 'deleted'
                              ORDER BY sq.id DESC");
$deleted_sms = $deleted_sms_stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine which reports to show (active or archived)
$show_archived = isset($_GET['show']) && $_GET['show'] === 'archived';
$archive_filter = $show_archived ? "AND ar.admin_archived = TRUE" : "AND ar.admin_archived = FALSE";

// Prepare search parameters for processed reports
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Prepare search parameters for archived reports
$archived_search = isset($_GET['archived_search']) ? $_GET['archived_search'] : '';
$archived_status_filter = isset($_GET['archived_status']) ? $_GET['archived_status'] : '';

// Build query for processed reports
$management_where = "WHERE ar.status IN ('approved', 'rejected') $archive_filter ";
$params = [];

// For active reports section
if (!$show_archived) {
    if (!empty($search)) {
        $management_where .= "AND (s.stud_name ILIKE :search OR ar.report_id::text ILIKE :search_id) ";
        $params[':search'] = "%$search%";
        $params[':search_id'] = "%$search%";
    }

    if (!empty($status_filter)) {
        $management_where .= "AND ar.status = :status ";
        $params[':status'] = $status_filter;
    }
} 
// For archived reports section
else {
    if (!empty($archived_search)) {
        $management_where .= "AND (s.stud_name ILIKE :archived_search OR ar.report_id::text ILIKE :archived_search_id) ";
        $params[':archived_search'] = "%$archived_search%";
        $params[':archived_search_id'] = "%$archived_search%";
    }

    if (!empty($archived_status_filter)) {
        $management_where .= "AND ar.status = :archived_status ";
        $params[':archived_status'] = $archived_status_filter;
    }
}

// Use appropriate offset based on section
if ($show_archived) {
    $offset = $offset_archived;
    $current_page = $page_archived;
} else {
    $offset = $offset_processed;
    $current_page = $page_processed;
}

$management_sql = "SELECT ar.report_id, ar.student_lrn, ar.date_range_start, ar.date_range_end, ar.absent_sessions, ar.status, ar.admin_remarks,
                          s.stud_name, s.year_section, s.parent_number,
                          t.fullname AS teacher_name,
                          (SELECT status FROM sms_queue WHERE report_id = ar.report_id ORDER BY id DESC LIMIT 1) AS sms_status
                   FROM absent_reports_tbl ar
                   JOIN student_tbl s ON ar.student_lrn = s.lrn
                   JOIN staff_tbl t ON ar.teacher_id = t.id
                   $management_where
                   ORDER BY ar.submitted_at DESC
                   LIMIT :perPage OFFSET :offset";
                   
// Prepare and execute the query with parameters
$management_stmt = $conn->prepare($management_sql);
$management_stmt->bindParam(':perPage', $perPage, PDO::PARAM_INT);
$management_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

foreach ($params as $key => $value) {
    $management_stmt->bindValue($key, $value);
}

$management_stmt->execute();
$management_reports = $management_stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total reports for management pagination
$total_management_sql = "SELECT COUNT(*) AS total 
                         FROM absent_reports_tbl ar 
                         JOIN student_tbl s ON ar.student_lrn = s.lrn
                         $management_where";
$total_management_stmt = $conn->prepare($total_management_sql);

foreach ($params as $key => $value) {
    $total_management_stmt->bindValue($key, $value);
}

$total_management_stmt->execute();
$total_management_row = $total_management_stmt->fetch(PDO::FETCH_ASSOC);
$total_reports = $total_management_row['total'];
$totalPagesProcessed = ceil($total_reports / $perPage);
$totalPagesArchived = ceil($total_reports / $perPage);
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

    <title>Absent Report</title>

    <!-- Custom fonts for this template-->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">
     <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
     <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
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

        .badge-mismatch {
            background-color:rgb(230, 184, 0);
            color: white;
        }
        
        .badge-match {
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

        .sms-header-compact {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .sms-content-compact {
            background-color: white;
            border-radius: 6px;
            padding: 10px;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .sms-content-compact p {
            margin-bottom: 5px;
        }
        
        .sms-timestamp-compact {
            font-size: 0.8rem;
            color: #6c757d;
            text-align: right;
        }
        
        .badge-sent-compact {
            padding: 3px 8px;
            font-size: 0.8rem;
        }

        /* Add new styles for pending SMS section */
        .pending-sms-container {
            background-color: #e8f4fc;
            border-left: 4px solid #0dcaf0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .sms-status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            margin-right: 5px;
        }
        
        .badge-pending { background-color: #ffc107; color: #000; }
        .badge-sending { background-color: #0dcaf0; color: #000; }
        .badge-sent { background-color: #198754; color: #fff; }
        .badge-failed { background-color: #dc3545; color: #fff; }
        
        .action-buttons {
            margin-top: 10px;
            display: flex;
            justify-content: flex-end; /* Align all buttons to the right */
            gap: 8px; /* Space between buttons */
        }

        .sms-actions {
            display: flex;
            justify-content: flex-end;  /* This pushes all children to the right */
            gap: 10px;                  /* Optional: space between buttons */
            margin-top: 10px;
        }
        
        .pending-sms-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .sms-content {
            background-color: white;
            border-radius: 8px;
            padding: 10px;
            margin: 10px 0;
        }
        
        /* New tab navigation for SMS */
        .sms-tabs {
            display: flex;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .sms-tab {
            padding: 10px 15px;
            cursor: pointer;
            border: 1px solid transparent;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
            font-size: 0.85rem;
        }
        
        .sms-tab.active {
            background-color: #fff;
            border-color: #dee2e6 #dee2e6 #fff;
            color: #198754;
            font-weight: 600;
        }
        
        .sms-tab-content {
            display: none;
        }
        
        .sms-tab-content.active {
            display: block;
        }
        
        /* Status history styling */
        .status-history {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
        }
        
        /* Additional button styling */
        .btn-resend {
            background-color: #ffc107;
            color: #000;
        }
        
        .btn-resend:hover {
            background-color: #e0a800;
            color: #000;
        }
        
        .alert-warning-custom {
            background-color: #fff8e6;
            border-radius: 0 8px 8px 0;
        }


         .sms-container {
    background: #e0ecf8ff;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    margin-bottom: 18px;
    padding: 18px 18px 12px 18px;
    border-left: 5px solid #05d2f2ff;
    position: relative;
    transition: box-shadow 0.2s;
}
.sms-container:last-child {
    margin-bottom: 0;
}
.sms-actions-row {
    display: flex;
    gap: 10px;
    margin-top: 10px;
    justify-content: flex-end;
}

.btn-delete {
    background-color: #dc3545;
    color: #fff;
}
.btn-delete:hover {
    background-color: #b52a37;
    color: #fff;
}
.btn-permanent-delete {
    background-color: #dc3545;
    color: #fff;
}
.btn-permanent-delete:hover {
    background-color: #b52a37;
    color: #fff;
}




        /* Tablet and below */
        @media (max-width: 768px) {
            .info-card {
                font-size: 0.9rem;
            }

            .alert-warning-custom {
                padding: 8px 10px;
                font-size: 0.8rem;
                border-radius: 6px;
            }

            .info-card .card-header h5 {
                font-size: 1rem;
            }

            .info-card h6 {
                font-size: 0.9rem;
            }

            .info-card ol,
            .info-card ul {
                padding-left: 1rem;
            }
            
            /* Adjust SMS tabs for tablet */
            .sms-tabs {
                flex-direction: column;
            }
            
            .sms-tab {
                margin-bottom: 5px;
                border-radius: 5px;
            }
        }

        /* Mobile and below */
        @media (max-width: 576px) {
            .info-card {
                font-size: 0.85rem;
            }

            .alert-warning-custom {
                padding: 8px 12px;
                font-size: 0.8rem;
            }

            .info-card .card-header h5 {
                font-size: 0.95rem;
            }
            .info-card h6 {
                font-size: 0.85rem;
            }
            
            /* Adjust page titles */
            h2.fw-bolder {
                font-size: 1.3rem !important;
            }
            
            .card-header h3, .card-header h5 {
                font-size: 1.1rem !important;
            }
            
            /* Adjust SMS content */
            .sms-content p, .sms-content-compact p {
                font-size: 0.8rem;
            }
            
            .pending-sms-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .sms-status-badge {
                margin-top: 5px;
            }
            
            .sms-actions .btn {
                padding: 5px 10px;
                font-size: 0.8rem;
            }
        }
        
        /* SMS Section - Scrollable */
        .sms-card .card-body {
            max-height: 500px;
            overflow-y: auto;
        }
        
        /* SMS Section - Make same width as absence information */
        .sms-row .col-md-8 {
            width: 100%;
            max-width: 100%;
        }
        
        /* SMS Section Header - Blue */
        .sms-card .card-header {
            background-color: #4169E1 !important;
            color: white !important;
        }
        
        /* Fix table header hover issue */
        #verify-absences-table thead tr:hover {
            background-color: #4169E1 !important;
            color: white !important;
        }

        /* Absence Report Management Section */
    .management-card {
     
        margin-top: 30px;
    }

    /* Status badges - smaller */
   .status-badge {
    padding: 3px 6px; /* Reduced padding */
    font-size: 0.7rem; /* Smaller font size */
    border-radius: 10px;
    font-weight: 600;
    white-space: nowrap;
}

    .badge-pending { background-color: #ffc107; color: #000; }
    .badge-approved { background-color: #198754; color: #fff; }
    .badge-rejected { background-color: #dc3545; color: #fff; }
    .badge-sent { background-color: #0d6efd; color: #fff; }
    .badge-notsent { background-color: #6c757d; color: #fff; }

    .action-btn {
        margin: 2px;
    }

    /* Column widths and table spacing */
    .management-card td {
        word-wrap: break-word;
        white-space: nowrap;
        padding: 0.5rem;
        vertical-align: middle !important;
    }

    .table th, .table td {
        vertical-align: middle !important;
    }

    /* Make tables responsive */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    /* Better spacing for SMS section */
    .sms-tab-content {
        max-height: 380px;
        overflow-y: auto;
        padding: 10px;
        border: 1px solid #eaeaea;
        border-radius: 8px;
    }

    /* Mobile-specific table fixes */
    @media (max-width: 767.98px) {
        /* Reduce font size for tables */
        .table th, .table td {
            font-size: 0.7rem;
            padding: 0.3rem;
        }

        /* Stack cards vertically on mobile */
        .sms-row .col-md-8, .sms-row .col-md-4 {
            flex: 0 0 100%;
            max-width: 100%;
        }

        /* Add spacing between stacked cards */
        .sms-row > div {
            margin-bottom: 20px;
        }

        /* Adjust filter form layout */
        .filter-form .row > div {
            flex: 0 0 100%;
            max-width: 100%;
            margin-bottom: 10px;
        }

        /* Adjust management card header */
        .management-card .card-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .management-card .card-header h5 {
           
              font-size: 0.95rem !important;
        }

        .management-card .card-header > * {
            width: 100%;
            margin-bottom: 10px;
        }

        .management-card .input-group,
        .management-card select {
            width: 100% !important;
            margin-top: 10px;
        }

        /* Adjust action buttons */
        .action-buttons .btn,
        .action-btn {
            font-size: 0.7rem;
            padding: 0.2rem 0.3rem;
        }

        /* Smaller status badges */
        .status-badge {
            font-size: 0.7rem;
        }

        /* Make pagination more compact */
        .pagination .page-link {
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
        }
    }

    /* Extra small screens */
    @media (max-width: 375px) {
        .table th, .table td {
            font-size: 0.7rem;
        }

        .card-header h3, .card-header h5 {
            font-size: 1rem !important;
        }

        .sms-tab {
            padding: 8px 10px;
            font-size: 0.8rem;
        }

        .btn {
            padding: 5px 8px;
            font-size: 0.8rem;
        }

          .status-badge {
    padding: 2px 4px; /* Reduced padding */
    font-size: 0.5rem; /* Smaller font size */
    border-radius: 9px;
    font-weight: 500;
    white-space: nowrap;
}
    }
        
        /* Fix for details modal */
        .modal-icon {
            min-width: 30px;
            text-align: center;
            font-size: 1.5rem;
            color: #4169E1;
            margin-right: 15px;
        }
        
        .modal-section {
            padding-bottom: 15px;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-section:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }



          /* New styles for editable admin remarks */
        .editable-remarks {
            background-color: #f8f9fa;
            border: 1px solid #eaeaea;
            border-radius: 4px;
            padding: 8px;
            min-height: 100px;
            margin-top: 10px;
        }
        
        .editable-remarks:focus {
            outline: none;
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        
        .save-remarks-btn {
            margin-top: 3px;
        }
        
        /* Status filter styling */
        .status-filter-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-filter-label {
            margin-bottom: 0;
            font-weight: 500;
        }
        
        /* Search bar styling */
        .search-container {
            position: relative;
            margin-bottom: 15px;
        }
        
        .search-container .form-control {
            padding-right: 40px;
        }
        
        .search-container .search-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

          .btn-reset {
            background-color: #6c757d;
            color: white;
            margin-left: 10px;
        }
        .btn-reset:hover {
            background-color: #5a6268;
            color: white;
        }

        @media (max-width: 767.98px) {
     .absences-title {
        font-size: 1.1rem !important;
    }

    #verify-absences-table th {
        min-width: 100px; /* Adjust this value as needed */
        white-space: nowrap;
    }
    
    #verify-absences-table td {
        min-width: 80px; /* Adjust this value as needed */
    }
    
    /* Specific column adjustments */
    #verify-absences-table th:nth-child(1) { /* Date Range */
        min-width: 120px;
    }
    
    #verify-absences-table th:nth-child(2) { /* Student Name */
        min-width: 120px;
    }
    
    #verify-absences-table th:nth-child(5), /* Reported Sessions */
    #verify-absences-table th:nth-child(6) { /* Actual Sessions */
        min-width: 60px;
    }
}


/* Mobile-specific SMS notification styles */
@media (max-width: 767.98px) {
    /* Card header adjustments */
    .sms-card .card-header {
        padding: 0.75rem 0.5rem; /* Reduce padding */
    }
    
    .sms-card .card-header h5 {
        font-size: 0.9rem !important; /* Smaller title */
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 120px;
    }
    
    /* Header buttons and status */
    .sms-card .card-header > div {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    #refreshSmsBtn {
        padding: 0.25rem 0.5rem;
        font-size: 0.7rem;
    }
    
    #wsStatus {
        font-size: 0.7rem !important;
        padding: 0.25rem 0.5rem;
        margin-left: 5px !important;
    }
    
    #wsSettingsBtn {
        padding: 0.25rem 0.5rem;
        font-size: 0.7rem;
        margin-left: 5px;
    }
    
    
}

/* Extra small screens (phones) */
@media (max-width: 575.98px) {
    .sms-card .card-header h5 {
        max-width: 90px;
    }
    
}

/* Mobile view styles for processed reports section */
@media (max-width: 767.98px) {
    /* Active/Archive button group */
    #processedReportsSection .btn-group {
        padding-top: 10px; /* Add top padding */
        margin-bottom: 5px;
    }
    
    #processedReportsSection .btn-group .btn {
        padding: 0.25rem 0.5rem; /* Make buttons smaller */
        font-size: 0.8rem; /* Smaller font */
    }
    
    /* Search bar adjustments */
    #processedReportsSection .search-container {
        margin-top: 5px !important; /* Decrease top padding */
    }
    
    #processedReportsSection .input-group-sm {
        height: 35px !important; /* Slightly smaller height */
    }
    
    /* Reset button adjustments */
    #processedReportsSection .btn-reset {
        height: 35px !important; /* Match search bar height */
        font-size: 0.8rem; /* Smaller font */
        padding: 0.25rem 0.5rem;
    }
    
    /* Status filter adjustments */
    #processedReportsSection .status-filter-container {
        height: 35px !important;
    }
    
    #processedReportsSection .status-filter-label {
        font-size: 0.75rem !important;
    }
    
    #processedReportsSection .form-control-sm {
        font-size: 0.8rem;
    }
    
    /* Table column width adjustments */
    #processedReportsSection table th, 
    #processedReportsSection table td {
        min-width: 100px !important; /* Minimum width for all columns */
        font-size: 0.75rem; /* Smaller font for table content */
        padding: 0.3rem !important; /* Tighter padding */
    }
    
    /* Specific wider columns */
    #processedReportsSection table th:nth-child(2), /* Student column */
    #processedReportsSection table td:nth-child(2) {
        min-width: 120px !important; /* Wider student column */
    }
    
    /* Action buttons in table */
    #processedReportsSection .action-btn {
        padding: 0.2rem 0.3rem;
        font-size: 0.7rem;
    }
    
    /* Status badges in table */
    #processedReportsSection .status-badge {
        padding: 2px 4px;
        font-size: 0.65rem;
    }
}

/* Extra small devices (phones, 575px and down) */
@media (max-width: 575.98px) {
    #processedReportsSection table th, 
    #processedReportsSection table td {
        min-width: 80px !important;
    }
    
    #processedReportsSection table th:nth-child(2), /* Student column */
    #processedReportsSection table td:nth-child(2) {
        min-width: 100px !important;
    }
    
    /* Stack the filter controls vertically on very small screens */
    #processedReportsSection .col-md-4 {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    #processedReportsSection .status-filter-container {
        margin-top: 5px;
        margin-bottom: 5px;
    }
    
    #processedReportsSection .btn-reset {
        margin-left: 0 !important;
        margin-top: 5px;
    }
}

.btn-loading {
    position: relative;
    color: transparent !important;
    pointer-events: none;
}

.btn-loading::after {
    content: '';
    position: absolute;
    left: 50%;
    top: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #fff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
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
                Administrator
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
                    <small class="admin-role">Administrator</small>
                </div>
            </div>

            <hr class="sidebar-divider">
            <!-- Nav Item - Dashboard -->
            <li class="nav-item"> 
                <a class="nav-link" href="Admin_Dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> 
                    <span>Dashboard</span>
                </a>
            </li>
            <!-- Nav Item - Pages -->
            <li class="nav-item"> 
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapsePages" aria-expanded="true" aria-controls="collapsePages"> 
                    <i class="fas fa-copy"></i> 
                    <span>Pages</span>
                </a>
                <div id="collapsePages" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded"> 
                        <a class="collapse-item" href="message.php">Home Page</a>
                        <a class="collapse-item" href="News_Eve.php">News & Events</a> 
                        <a class="collapse-item" href="Edit_Staff.php">Faculty & Staff</a>
                        <a class="collapse-item" href="Edit_Achieve.php">Achievements</a> 
                        <a class="collapse-item" href="chart_edit.php">Organizational Chart</a>
                        <a class="collapse-item" href="Edit_Contact.php">Contact</a>
                        <a class="collapse-item" href="Edit_Gallery.php">Gallery</a>
                        <a class="collapse-item" href="Edit_History.php">History</a>
                    </div>
                </div>
            </li>
            <?php if ($user['id'] == 29 && $user['is_superadmin'] == 1): ?>
                <!-- Nav Item - User Management --> 
                <li class="nav-item">
                    <a class="nav-link" href="Staff_Man.php">
                        <i class="fas fa-users-cog"></i> 
                        <span>Staff Management</span>
                    </a> 
                </li>
            <?php endif; ?>
            <!-- Nav Item - Edit Chatbot -->
            <li class="nav-item">
                <a class="nav-link" href="Manage_Chatbot.php">
                    <i class="fas fa-robot"></i>
                    <span>Manage Chatbot</span>
                </a> 
            </li> 
            <!-- Nav Item - Student Records -->
            <li class="nav-item"> 
                <a class="nav-link" href="Manage_Students.php">
                    <i class="fas fa-user-graduate"></i> 
                    <span>Manage Students</span>
                </a>
            </li>
            <!-- Nav Item - Student Attendance -->
            <li class="nav-item active"> 
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseAttendance" aria-expanded="true" aria-controls="collapsePages"> 
                    <i class="fas fa-calendar-week"></i> 
                    <span>Student Attendance</span>
                </a>
                <div id="collapseAttendance" class="collapse show" aria-labelledby="headingPages" data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded">
                        <a class="collapse-item" href="Attendance_logs.php">Attendance Logs</a> 
                        <a class="collapse-item" href="attendance_admin.php">Attendance Calendar</a> 
                        <a class="collapse-item active" href="Admin_AbsentReport.php">Verify Absent Reports</a>
                        <a class="collapse-item" href="barcode_scanner.php">Scan Attendance</a>

                    </div> 
                </div>
            </li>
            <!-- Divider -->
            <hr class="sidebar-divider" style="margin-top: 20px;">
            <!-- Nav Item - Logout -->
            <li class="nav-item">
                <a class="nav-link" href="#" data-toggle="modal" data-target="#logoutModal">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Log Out</span>
                </a>
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
                    <form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search">
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

                <!-- Begin Page Content --> 
                <div class="container-fluid">                
                   <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center mb-2">
        <i class="fas fa-table" style="color: rgb(11, 104, 245); font-size: 1.3rem; margin-right: 0.8rem;"></i>
        <h2 class="fw-bolder mb-0" style="color: rgb(11, 104, 245); font-size: 1.5rem; font-weight: 800;">Absent Reports</h2>
    </div>
    <button id="refreshAbsentReportsBtn" class="btn btn-primary btn-sm d-flex align-items-center" style="min-width: 80px;">
        <span id="refreshAbsentReportsText"><i class="fas fa-sync-alt mr-2"></i>Refresh</span>
        <span id="refreshAbsentReportsLoading" style="display:none;">
            <span class="spinner-border spinner-border-sm"></span> Loading...
        </span>
    </button>
</div>

                    <!-- Content Row -->
                    <div class="row g-4">
                        <!-- Pending Card -->
                        <div class="col-xl-4 col-md-6 mb-4">                          
                            <div class="card shadow h-100 py-3 px-2 position-relative" style="border-left: 8px solid #36b9cc;">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col mr-3">
                                            <div class="text-sm text-info text-uppercase mb-2" style="font-size: 1.2rem; font-weight: 800;">
                                                Pending Reports
                                            </div>
                                            <div class="mb-0 text-gray-800" style="font-size: 2rem; font-weight: 700;">
                                                <?= $total_pending ?>
                                            </div>
                                        </div>
                                        <div class="col-auto pe-3">
                                            <i class="fas fa-file-alt fa-4x text-info"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SMS Card -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card shadow h-100 py-3 px-2" style="border-left: 8px solid #007bff;">                            
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col mr-3">
                                            <div class="text-sm text-primary text-uppercase mb-2" style="font-size: 1.2rem; font-weight: 800;">
                                                SMS Sent
                                            </div>
                                            <div class="mb-0 text-gray-800" style="font-size: 2rem; font-weight: 700;">
                                                <?= count($sent_sms) ?>
                                            </div>
                                        </div>
                                        <div class="col-auto pe-3">
                                            <i class="fas fa-sms fa-4x text-primary"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>   

                        <!-- Approved Card -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card shadow h-100 py-3 px-2" style="border-left: 8px solid #1cc88a;">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col mr-3">
                                            <div class="text-sm text-success text-uppercase mb-2" style="font-size: 1.2rem; font-weight: 800;">
                                                Approved Reports
                                            </div>
                                            <div class="mb-0 text-gray-800" style="font-size: 2rem; font-weight: 700;">
                                                <?php 
                                                    $approved_count = $conn->query("SELECT COUNT(*) FROM absent_reports_tbl WHERE status = 'approved'")->fetchColumn();
                                                    echo $approved_count;
                                                ?>
                                            </div>
                                        </div>
                                        <div class="col-auto pe-3">
                                            <i class="fas fa-check-circle fa-4x text-success"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>      
                    </div>
                  
                <!-- View Absences & Records -->
<section class="py-4" id="verify-absences-section">
    <div class="container-fluid px-1 px-sm-3">
        <div class="card shadow" style="border-top: 8px solid #4169E1; border-radius: 1rem;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                    <h3 class="text-start mb-2 fw-bold text-primary absences-title" style="font-weight: 900;">
                        <i class="fas fa-clipboard-check"></i> Verify Pending Reports
                    </h3>
                    <span class="badge summary-badge">
                        <i class="fas fa-info-circle mr-1"></i> Showing <?= min($perPage, count($verify_reports)) ?> of <?= $total_pending ?> records
                    </span>
                </div>

                <!-- Absences Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle text-center" id="verify-absences-table">
                        <thead style="background-color: #4169E1; color: white;">
                            <tr>
                                <th>Date Range</th>
                                <th>Student Name</th>
                                <th>Year & Section</th>
                                <th>Teacher</th>
                                <th style="width: 80px;">Reported Sessions</th>
                                <th style="width: 80px;">Actual Sessions</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($verify_reports) > 0): ?>
                                <?php foreach ($verify_reports as $report): ?>
                                    <tr>
                                        <td class="date-range-cell">
                                            <?= date('M j', strtotime($report['date_range_start'])) ?> - 
                                            <?= date('M j, Y', strtotime($report['date_range_end'])) ?>
                                        </td>
                                        <td><?= htmlspecialchars($report['stud_name']) ?></td>
                                        <td><?= htmlspecialchars($report['year_section']) ?></td>
                                        <td><?= htmlspecialchars($report['teacher_name']) ?></td>
                                        <td><?= $report['absent_sessions'] ?></td>
                                        <td><?= $report['actual_sessions'] ?></td>
                                        <td>
                                            <?php if ($report['absent_sessions'] == $report['actual_sessions']): ?>
                                                <span class="badge badge-match">Matched</span>
                                            <?php else: ?>
                                                <span class="badge badge-mismatch">Mismatch</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($report['absent_sessions'] == $report['actual_sessions']): ?>
                                                <button class="btn btn-sm btn-outline-primary approve-modal-btn" 
                                                  data-toggle="modal" data-target="#approveModal"
                                                   data-report-id="<?= $report['report_id'] ?>"
                                                   data-student-name="<?= htmlspecialchars($report['stud_name']) ?>"
                                                 data-details="<?= htmlspecialchars($report['details']) ?>">
                                               <i class="fas fa-check"></i> Approve
                                                  </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-warning review-btn" data-toggle="modal" data-target="#reviewModal"
                                                        data-report-id="<?= $report['report_id'] ?>"
                                                        data-student-name="<?= htmlspecialchars($report['stud_name']) ?>"
                                                        data-teacher-name="<?= htmlspecialchars($report['teacher_name']) ?>"
                                                        data-date-range="<?= date('M j', strtotime($report['date_range_start'])) ?> to <?= date('M j, Y', strtotime($report['date_range_end'])) ?>"
                                                        data-reported="<?= $report['absent_sessions'] ?>"
                                                        data-actual="<?= $report['actual_sessions'] ?>"
                                                        data-details="<?= htmlspecialchars($report['details']) ?>">
                                                    <i class="fas fa-exclamation-circle"></i> Review
                                                </button>
                                            <?php endif; ?>
                                        </td>                         
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                     <td colspan="8" class="text-center py-4">
                        <i class="fas fa-copy fa-4x text-muted mb-3"></i>
                        <p class="text-muted mb-0">No pending reports found</p>
                    </td>   
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav>
                    <ul class="pagination justify-content-center mt-3">
                        <?php if ($page_pending > 1): ?>
                            <li class="page-item">
                                <?php
                                $params = $_GET;
                                $params['page_pending'] = $page_pending - 1;
                                unset($params['page_processed']);
                                ?>
                                <a class="page-link" href="?<?= http_build_query($params) ?>#verify-absences-section" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPagesPending; $i++): ?>
                            <li class="page-item <?= $i == $page_pending ? 'active' : '' ?>">
                                <?php
                                $params = $_GET;
                                $params['page_pending'] = $i;
                                unset($params['page_processed']);
                                ?>
                                <a class="page-link" href="?<?= http_build_query($params) ?>#verify-absences-section"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page_pending < $totalPagesPending): ?>
                            <li class="page-item">
                                <?php
                                $params = $_GET;
                                $params['page_pending'] = $page_pending + 1;
                                unset($params['page_processed']);
                                ?>
                                <a class="page-link" href="?<?= http_build_query($params) ?>#verify-absences-section" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</section>

                    <div class="row sms-row">
                        <div class="col-md-8">
                            <div class="card sms-card shadow">
                               <div class="card-header text-white d-flex justify-content-between align-items-center" style="background-color: #4169E1;">
    <h5 class="mb-0"><i class="fas fa-sms mr-2"></i>SMS Notifications</h5>
    <div>
        <button class="btn btn-sm btn-light" id="refreshSmsBtn">
            <i class="fas fa-sync-alt mr-1"></i> Refresh
        </button>
        <span id="wsStatus" class="badge badge-secondary ml-2" style="font-size:0.9rem;">Connecting...</span>
        <!-- Settings button -->
        <button class="btn btn-sm btn-primary ml-2" id="wsSettingsBtn" data-toggle="modal" data-target="#wsSettingsModal">
            <i class="fas fa-cog"></i>
        </button>
    </div>
</div>
                                <div class="card-body">
                                    <!-- SMS Tabs -->
                                    <div class="sms-tabs">
                                        <div class="sms-tab active" data-tab="pending">Pending to Send</div>
                                        <div class="sms-tab" data-tab="sent">Sent Messages</div>
                                        <div class="sms-tab" data-tab="failed">Deleted Messages</div>
                                    </div>
                                    
                                    <!-- Pending SMS Tab -->
                                    <div class="sms-tab-content active" id="pending-sms">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            <strong>Approved reports ready to send:</strong> Select messages to send to parents
                                        </div>
                                        
                                        <?php if (count($pending_sms) > 0): ?>
                                            <?php foreach ($pending_sms as $sms): ?>
                                                <div class="pending-sms-container">
                                                    <div class="pending-sms-header">
                                                        <div>
                                                            <strong><?= htmlspecialchars($sms['stud_name']) ?></strong> | 
                                                            <strong>Parents Number:</strong> <span><?= htmlspecialchars($sms['parent_number']) ?></span>
                                                        </div>
                                                        <div>
                                                            <span class="sms-status-badge badge-pending">Pending</span>
                                                        </div>
                                                    </div>
                                                    <div class="sms-content">
                                                        <p><strong>Message:</strong></p>
                                                        <p class="mb-1"><?= htmlspecialchars($sms['message']) ?></p>
                                                    </div>
                                                    <div class="sms-actions">
                                                        <input type="hidden" name="sms_id" value="<?= $sms['id'] ?>">
                                                         <button type="button" class="btn btn-sm btn-primary send-sms-btn"
                                                       data-phone="<?= htmlspecialchars($sms['parent_number']) ?>"
                                                      data-message="<?= htmlspecialchars($sms['message']) ?>">
                                                     <i class="fas fa-paper-plane mr-1"></i> Send Now
                                                       </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="alert alert-warning">
                                                No pending SMS messages
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Sent SMS Tab -->
                                    <div class="sms-tab-content" id="sent-sms">
    <?php if (count($sent_sms) > 0): ?>
        <?php foreach ($sent_sms as $sms): ?>
            <div class="sms-container">
                <div class="sms-header-compact">
                    <div>
                        <strong><?= htmlspecialchars($sms['stud_name']) ?></strong> | 
                        <strong>Parents Number:</strong> <span><?= htmlspecialchars($sms['parent_number']) ?></span>
                    </div>
                    <div>
                        <span class="sms-status-badge badge-sent">Sent</span>
                    </div>
                </div>
                <div class="sms-content-compact">
                    <p><strong>Message:</strong></p>
                    <p class="mb-1"><?= htmlspecialchars($sms['message']) ?></p>
                </div>
                <div class="status-history">
                    <div class="status-item">
                        <span>SMS sent</span>
                        <span><?php
if (!empty($sms['sent_at'])) {
    $dt = new DateTime($sms['sent_at'], new DateTimeZone('Asia/Manila'));
    echo $dt->format('M j, Y g:i A');
} else {
    echo 'N/A';
}
?></span>
                    </div>
                </div>
                <div class="sms-actions-row">
                    <button class="btn btn-sm btn-resend resend-sms-btn" 
                        data-id="<?= $sms['id'] ?>"
                        data-phone="<?= htmlspecialchars($sms['parent_number']) ?>"
                        data-message="<?= htmlspecialchars($sms['message']) ?>">
                        <i class="fas fa-redo"></i> Resend
                    </button>
                    <button class="btn btn-sm btn-delete delete-sms-btn" 
                        data-id="<?= $sms['id'] ?>">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-warning">
            No sent SMS messages
        </div>
    <?php endif; ?>
</div>
                                     <!--deleted sms-->
                                    <div class="sms-tab-content" id="failed-sms">
    <?php if (count($deleted_sms) > 0): ?>
        <?php foreach ($deleted_sms as $sms): ?>
            <div class="sms-container">
                <div class="sms-header-compact">
                    <div>
                        <strong><?= htmlspecialchars($sms['stud_name']) ?></strong> | 
                        <strong>Parents Number:</strong> <span><?= htmlspecialchars($sms['parent_number']) ?></span>
                    </div>
                </div>
                <div class="sms-content-compact">
                    <p><strong>Message:</strong></p>
                    <p class="mb-1"><?= htmlspecialchars($sms['message']) ?></p>
                </div>
                <div class="status-history">
                    <div class="status-item">
                        <span>SMS Sent Date/Time:</span>
                        <span><?php
if (!empty($sms['sent_at'])) {
    $dt = new DateTime($sms['sent_at'], new DateTimeZone('Asia/Manila'));
    echo $dt->format('M j, Y g:i A');
} else {
    echo 'N/A';
}
?></span>
                    </div>                              
                </div>
                <div class="sms-actions-row">
                    <button class="btn btn-sm btn-permanent-delete permanent-delete-sms-btn" 
                        data-id="<?= $sms['id'] ?>">
                        <i class="fas fa-trash-alt"></i> Permanent Delete
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-warning">
            No deleted SMS messages
        </div>
    <?php endif; ?>
</div>

                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card info-card shadow" style="min-height: 420px;">
                                <div class="card-header text-white" style="background-color: #4169E1;">
                                    <h5 class="mb-0"><i class="fas fa-info-circle mr-2"></i>Report Guidelines</h5>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-warning alert-warning-custom">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        <strong>Note:</strong> 2 missed sessions = 1 day absent
                                    </div>
                                                                                                                       
                                    <h6 class="mt-3"><i class="fas fa-bullhorn mr-2 text-primary"></i>Reporting Process</h6>
                                    <ol class="pl-4 small">
                                        <li>Teacher submits absent report</li>
                                        <li>Admin verifies the report</li>
                                        <li>Admin sends SMS to parents</li>
                                        <li>Status updated to "SMS Sent"</li>
                                    </ol>

                                    <h6 class="mt-3"><i class="fas fa-lightbulb mr-2 text-warning"></i>Tips for Admin</h6>
                                    <ul class="pl-4 small">
                                        <li>Verify attendance records thoroughly before approval</li>                                
                                        <li>Review mismatched reports with teachers</li>                               
                                    </ul>

                                    <h6 class="mt-3"><i class="fas fa-sms mr-2 text-success"></i>SMS Sending Guide</h6>
                                  <ul class="pl-4 small">
                                  <li>Open the SMS application</li>
                                  <li>Connect to WebSocket first to enable SMS</li>
                                  <li>Ensure your device and PC are on the same Wi-Fi</li>
                                  <li>On Android, set a default SIM for SMS (if dual SIM)</li>
                                  <li>Make sure your SIM has enough load before sending</li>
                                  </ul>
                                </div>
                            </div>
                        </div>
                    </div>

               
       <!-- Processed Reports Section -->
<div class="card management-card shadow mt-4" id="processedReportsSection">
    <div class="card-header text-white d-flex justify-content-between align-items-center" style="background-color: #4169E1;">
        <h5 class="mb-0"><i class="fas fa-tasks text-white mr-2"></i>
            <?= $show_archived ? 'Archived Reports' : 'Processed Reports' ?>
        </h5>
        
        <!-- Archive Toggle Button -->
        <div class="btn-group btn-group-sm" role="group" aria-label="Report view toggle">
            <a href="?<?= http_build_query(array_merge($_GET, ['show' => 'active'])) ?>#processedReportsSection" 
               class="btn <?= !$show_archived ? 'btn-light active' : 'btn-outline-light' ?>">
                <i class="fas fa-list mr-1"></i> Active
            </a>
            <a href="?<?= http_build_query(array_merge($_GET, ['show' => 'archived'])) ?>#processedReportsSection" 
               class="btn <?= $show_archived ? 'btn-light active' : 'btn-outline-light' ?>">
                <i class="fas fa-archive mr-1"></i> Archived
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if ($show_archived): ?>
        <!-- ARCHIVED REPORTS FILTER FORM -->
        <form method="GET" id="archivedFilterForm">
            <div class="row mb-3 align-items-center">
                <div class="col-md-8">
                    <div class="search-container" style="max-width: 400px; margin-top: 20px;">
                        <div class="input-group input-group-sm" style="height: 40px;">
                            <input type="text" name="archived_search" class="form-control form-control-sm"  style="height: 40px;"
                                placeholder="Search by student name or report ID..." 
                                value="<?= htmlspecialchars($archived_search) ?>">
                            <div class="input-group-append">
                                <button class="btn btn-outline-primary btn-sm" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 d-flex justify-content-md-end">
                    <div class="status-filter-container d-flex align-items-center" style="height: 38px;">
                        <span class="status-filter-label" style="font-size: 0.85rem;">Filter by Status:</span>
                        <select class="form-control form-control-sm ml-2" id="archivedReportStatusFilter" name="archived_status" style="max-width: 120px;">
                            <option value="" <?= empty($archived_status_filter) ? 'selected' : '' ?>>All</option>
                            <option value="approved" <?= $archived_status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="rejected" <?= $archived_status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <!-- Reset Button -->
                    <button type="button" id="archivedResetFilterBtn" class="btn btn-sm btn-reset ml-2" style="height: 38px;">
                        <i class="fas fa-sync-alt"></i> Reset
                    </button>
                </div>
            </div>
            <!-- Hidden fields to preserve state -->
            <input type="hidden" name="show" value="archived">
            <input type="hidden" name="page_pending" value="<?= $page_pending ?>">
            <input type="hidden" name="page_processed" value="1">
        </form>
        <?php else: ?>
        <!-- ACTIVE REPORTS FILTER FORM -->
        <form method="GET" id="filterForm">
            <div class="row mb-3 align-items-center">
                <div class="col-md-8">
                    <div class="search-container" style="max-width: 400px; margin-top: 20px;">
                        <div class="input-group input-group-sm" style="height: 40px;">
                            <input type="text" name="search" class="form-control form-control-sm"  style="height: 40px;"
                                placeholder="Search by student name or report ID..." 
                                value="<?= htmlspecialchars($search) ?>">
                            <div class="input-group-append">
                                <button class="btn btn-outline-primary btn-sm" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 d-flex justify-content-md-end">
                    <div class="status-filter-container d-flex align-items-center" style="height: 38px;">
                        <span class="status-filter-label" style="font-size: 0.85rem;">Filter by Status:</span>
                        <select class="form-control form-control-sm ml-2" id="reportStatusFilter" name="status" style="max-width: 120px;">
                            <option value="" <?= empty($status_filter) ? 'selected' : '' ?>>All</option>
                            <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <!-- Reset Button -->
                    <button type="button" id="resetFilterBtn" class="btn btn-sm btn-reset ml-2" style="height: 38px;">
                        <i class="fas fa-sync-alt"></i> Reset
                    </button>
                </div>
            </div>
            <!-- Hidden fields to preserve state -->
            <input type="hidden"
            <input type="hidden" name="page_pending" value="<?= $page_pending ?>">
            <input type="hidden" name="page_processed" value="1">
        </form>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="thead-light">
                    <tr>
                        <th class="text-center">Report ID</th>
                        <th class="text-center">Student</th>
                        <th class="text-center">Year & Section</th>
                        <th class="text-center">Parent Number</th>
                        <th class="text-center">Teacher</th>
                        <th class="text-center">Date Range</th>
                        <th class="text-center">Sessions</th>
                        <th class="text-center">Report Status</th>
                        <th class="text-center">SMS Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($management_reports) > 0): ?>
                        <?php foreach ($management_reports as $report): ?>
                            <tr>
                                <td class="text-center">#REP-<?= $report['report_id'] ?></td>
                                <td class="text-center"><?= htmlspecialchars($report['stud_name']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($report['year_section']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($report['parent_number']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($report['teacher_name']) ?></td>
                                <td class="text-center date-range-cell">
                                    <?= date('M j', strtotime($report['date_range_start'])) ?> - 
                                    <?= date('M j, Y', strtotime($report['date_range_end'])) ?>
                                </td>
                                <td class="text-center"><?= $report['absent_sessions'] ?></td>
                                <td class="text-center">
                                    <?php if ($report['status'] == 'approved'): ?>
                                        <span class="status-badge badge-approved">Approved</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-rejected">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($report['sms_status'] == 'sent'): ?>
                                        <span class="status-badge badge-sent">Sent</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-notsent">Not Sent</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <!-- View button -->
                                    <button class="btn btn-sm btn-primary action-btn" 
                                            data-toggle="modal" 
                                            data-target="#detailsModal"
                                            data-report-id="<?= $report['report_id'] ?>"
                                             data-student-name="<?= htmlspecialchars($report['stud_name']) ?>"
                                            data-year-section="<?= htmlspecialchars($report['year_section']) ?>"
                                            data-parent-number="<?= htmlspecialchars($report['parent_number']) ?>"
                                            data-teacher-name="<?= htmlspecialchars($report['teacher_name']) ?>"
                                            data-date-range="<?= date('M j', strtotime($report['date_range_start'])) ?> to <?= date('M j, Y', strtotime($report['date_range_end'])) ?>"
                                            data-absent-sessions="<?= $report['absent_sessions'] ?>"
                                            data-admin-remarks="<?= htmlspecialchars($report['admin_remarks']) ?>"
                                            data-report-status="<?= $report['status'] ?>"
                                            data-is-archived="<?= $show_archived ? '1' : '0' ?>">
                                            <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <!-- Archive/Unarchive button -->
                                    <?php if (!$show_archived): ?>
                                                <button type="button" class="btn btn-sm btn-warning action-btn archive-btn"
                                                    data-report-id="<?= $report['report_id'] ?>">
                                                    <i class="fas fa-archive"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-success action-btn unarchive-btn"
                                                    data-report-id="<?= $report['report_id'] ?>">
                                                    <i class="fas fa-trash-restore"></i>
                                                </button>
                                            <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                              <td colspan="10" class="text-center py-4">
                                No <?= $show_archived ? 'archived' : 'processed' ?> reports found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
    <nav>
            <ul class="pagination justify-content-end mt-3">
                <?php if ($current_page > 1): ?>
                    <li class="page-item">
                        <?php
                        $params = $_GET;
                        if ($show_archived) {
                            $params['page_archived'] = $current_page - 1;
                        } else {
                            $params['page_processed'] = $current_page - 1;
                        }
                        // PRESERVE pending pagination
                        if (isset($params['page_pending'])) unset($params['page_pending']);
                        ?>
                        <a class="page-link" href="?<?= http_build_query($params) ?>#processedReportsSection" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php 
                $totalPages = $show_archived ? $totalPagesArchived : $totalPagesProcessed;
                for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                        <?php
                        $params = $_GET;
                        if ($show_archived) {
                            $params['page_archived'] = $i;
                        } else {
                            $params['page_processed'] = $i;
                        }
                        // PRESERVE pending pagination
                        if (isset($params['page_pending'])) unset($params['page_pending']);
                        ?>
                        <a class="page-link" href="?<?= http_build_query($params) ?>#processedReportsSection"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($current_page < $totalPages): ?>
                    <li class="page-item">
                        <?php
                        $params = $_GET;
                        if ($show_archived) {
                            $params['page_archived'] = $current_page + 1;
                        } else {
                            $params['page_processed'] = $current_page + 1;
                        }
                        // PRESERVE pending pagination
                        if (isset($params['page_pending'])) unset($params['page_pending']);
                        ?>
                        <a class="page-link" href="?<?= http_build_query($params) ?>#processedReportsSection" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</div>

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
        </div>
    </div>

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
                        <div class="form-group d-flex align-items-center">
                            <img id="previewImage" src="<?= htmlspecialchars($profileImageUrl) ?>" alt="Preview" style="width: 80px; height: 80px; border-radius: 10px; border: 1px solid #ccc; object-fit: cover; margin-right: 15px;">
                            <div style="flex: 1;">
                                <label><strong>Profile Image</strong></label>
                                <input type="file" name="profile_image" class="form-control-file" id="profileImageInput" accept=".jpg,.jpeg,.png,.webp">
                            </div>
                        </div>
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


       <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-info-circle mr-2"></i>Report Details</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="POST" id="remarksForm">
                    <input type="hidden" name="report_id" id="detailsReportId">
                    <input type="hidden" name="update_remarks" value="1">
                    <div class="modal-body" id="detailsModalBody">
                        <!-- Content will be loaded dynamically -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary save-remarks-btn">Save Remarks</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Review Modal -->
    <div class="modal fade review-modal" id="reviewModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-2"></i>Review Report</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
         <form method="POST" id="reviewForm">
        <input type="hidden" name="report_id" id="reviewReportId">
        <input type="hidden" name="form_action" id="reviewFormAction" value="">
        <div class="modal-body" id="reviewModalBody">
            <!-- Content will be loaded dynamically -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" name="reject_report" class="btn btn-danger reject-report-btn">Reject Report</button>
            <button type="submit" name="approve_report" class="btn btn-success approve-report-btn">Approve</button>
        </div>
    </form>
            </div>
        </div>
    </div>
   
    <!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle mr-2"></i>Approve Report</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST" id="approveForm">
    <input type="hidden" name="report_id" id="approveReportId">
    <input type="hidden" name="form_action" id="approveFormAction" value="">
    <div class="modal-body" id="approveModalBody" style="max-height: 70vh; overflow-y: auto;">
        <!-- Content will be loaded dynamically -->
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" name="approve_report" class="btn btn-success confirm-approval-btn">Confirm Approval</button>
    </div>
</form>
        </div>
    </div>
</div>


  <!-- Replace the settings modal with this version -->
<div class="modal fade" id="wsSettingsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plug mr-2"></i>WebSocket Connection Settings</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="wsUrlInput"><strong>WebSocket URL:</strong></label>
                    <input type="text" class="form-control" id="wsUrlInput" 
                           placeholder="ws://your-server:port">
                    <small class="form-text text-muted">
                        Enter the WebSocket URL for SMS gateway connection
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary save-settings-btn" id="saveWsSettings">Save Settings</button>
            </div>
        </div>
    </div>
</div>

<!-- Use this instead -->
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

    <!-- Bootstrap core JavaScript-->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    
    
       <script>
        $('#refreshAbsentReportsBtn').on('click', function() {
    var $btn = $(this);
    $btn.prop('disabled', true);
    $('#refreshAbsentReportsText').hide();
    $('#refreshAbsentReportsLoading').show();
    setTimeout(function() {
        window.location.href = 'Admin_AbsentReport.php';
    }, 100); // Small delay so spinner is visible
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
                    document.getElementById('previewImage').src = e.target.result;
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

       // Helper: Add/remove loading spinner to a button
    function setButtonLoading($btn, isLoading) {
        if (isLoading) {
            $btn.addClass('btn-loading').prop('disabled', true);
        } else {
            $btn.removeClass('btn-loading').prop('disabled', false);
        }
    }

        // Form submission handlers with loading states
        $('#reviewForm').on('submit', function() {
            if ($(this).find('button[name="reject_report"]').is(':focus')) {
                setButtonLoading($('.reject-report-btn'), true);
            } else if ($(this).find('button[name="approve_report"]').is(':focus')) {
                setButtonLoading($('.approve-report-btn'), true);
            }
        });

        $('#approveForm').on('submit', function() {
            setButtonLoading($('.confirm-approval-btn'), true);
        });

        $('#remarksForm').on('submit', function() {
            setButtonLoading($('.save-remarks-btn'), true);
        });

        $('#saveWsSettings').on('click', function() {
            setButtonLoading($(this), true);
            
            // Your WebSocket settings saving logic here
            setTimeout(function() {
                setButtonLoading($('#saveWsSettings'), false);
                $('#wsSettingsModal').modal('hide');
            }, 1000);
        });

   

       

       

        // SweetAlert for approval actions
        $('.approve-btn').click(function() {
            Swal.fire({
                title: 'Approve Report?',
                text: "This report will be approved and approved reports can be send in SMS section to send SMS to Parents.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, approve report',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Report Approved!',
                        text: 'The absent report has been approved.',
                        icon: 'success',
                        confirmButtonColor: '#198754'
                    });
                }
            });
        });
        
        // SweetAlert for rejection actions
        $('.reject-btn').click(function() {
            Swal.fire({
                title: 'Reject Report?',
                text: "This report will be rejected and no SMS will be sent.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, reject report',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Report Rejected!',
                        text: 'The absent report has been rejected.',
                        icon: 'success',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        });
        
        // View button handler
        $('.view-btn').click(function() {
            // This is handled by Bootstrap's data attributes
        });
        
        // Review button handler
        $('.review-btn').click(function() {
            // This is handled by Bootstrap's data attributes
        });
        
        // Add hover effect to table rows
        $('tr').hover(function() {
            $(this).css('background-color', '#f8f9fa');
        }, function() {
            $(this).css('background-color', '');
        });
        
        // SMS Tab Navigation
        $('.sms-tab').click(function() {
            const tabId = $(this).data('tab');
            $('.sms-tab').removeClass('active');
            $(this).addClass('active');
            $('.sms-tab-content').removeClass('active');
            $(`#${tabId}-sms`).addClass('active');
        });
        
        // Review button handler - shows teacher's note
        $('.review-btn').click(function() {
            const reportId = $(this).data('report-id');
            const studentName = $(this).data('student-name');
            const teacherName = $(this).data('teacher-name');
            const dateRange = $(this).data('date-range');
            const reportedSessions = $(this).data('reported');
            const actualSessions = $(this).data('actual');
            const details = $(this).data('details');
            
            $('#reviewReportId').val(reportId);
            
            const content = `
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Mismatch Detected:</strong> Reported sessions (${reportedSessions}) do not match actual sessions (${actualSessions})
                </div>
                
                <div class="mb-3">
                    <strong>Student:</strong> ${studentName}
                </div>
                <div class="mb-3">
                    <strong>Teacher:</strong> ${teacherName}
                </div>
                <div class="mb-3">
                    <strong>Date Range:</strong> ${dateRange}
                </div>
                <div class="mb-3">
                    <strong>Reported Absences:</strong> ${reportedSessions} sessions
                </div>
                <div class="mb-3">
                    <strong>Actual Absences:</strong> ${actualSessions} sessions
                </div>
                
               <div class="form-group">
        <label><strong>Teacher's Note:</strong></label>
        <div class="card p-3 border rounded">
            ${details || 'No additional details provided'}
        </div>
    </div>
                
                <div class="form-group">
                    <label><strong>Admin Remarks:</strong></label>
                    <textarea class="form-control" name="admin_remarks" rows="3" placeholder="Enter your decision notes..." required></textarea>
                </div>
            `;
            
            $('#reviewModalBody').html(content);
        });
        
        // Approve button handler (for reports with matching sessions)
        $('.approve-modal-btn').click(function() {
            const reportId = $(this).data('report-id');
            const studentName = $(this).data('student-name');
            const details = $(this).data('details');
            
            $('#approveReportId').val(reportId);
            
            const content = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>No discrepancies found.</strong> This report matches the attendance records.
                </div>
                
                <div class="mb-3">
                    <strong>Student:</strong> ${studentName}
                </div>
                
                <div class="form-group">
                    <label><strong>Teacher's Note:</strong></label>
                    <div class="card p-3 border rounded">
                        ${details || 'No additional details provided'}
                    </div>
                </div>
                
                <div class="form-group">
                    <label><strong>Admin Remarks:</strong></label>
                    <textarea class="form-control" name="admin_remarks" rows="3" placeholder="Enter any additional notes..." required></textarea>
                </div>
            `;
            
            $('#approveModalBody').html(content);
        }); 
        
        // Details button handler for processed reports - shows admin remarks
        $('.action-btn[data-target="#detailsModal"]').click(function() {
            const reportId = $(this).data('report-id');
            const studentName = $(this).data('student-name');
            const yearSection = $(this).data('year-section');
            const parentNumber = $(this).data('parent-number');
            const teacherName = $(this).data('teacher-name');
            const dateRange = $(this).data('date-range');
            const absentSessions = $(this).data('absent-sessions');
            const adminRemarks = $(this).data('admin-remarks');
            const reportStatus = $(this).data('report-status');
            const isArchived = $(this).data('is-archived');
            
            $('#detailsReportId').val(reportId);
            
            const statusBadge = reportStatus === 'approved' ? 
                '<span class="badge badge-approved">Approved</span>' : 
                '<span class="badge badge-rejected">Rejected</span>';
            
            const archiveStatus = isArchived == 1 ? 
                '<span class="badge badge-warning ml-2">Archived</span>' : 
                '';
            
            const content = `
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Report Details</h5>
                ${archiveStatus}
              </div>
              
              <div class="modal-section">
                <div class="d-flex align-items-start">
                    <i class="fas fa-user modal-icon"></i>
                    <div>
                        <h6 class="font-weight-bold">Student Information</h6>
                        
                        <!-- Row 1: Name + Grade -->
                        <div class="d-flex flex-wrap mb-2">
                            <div class="mr-4">
                                <strong>Name:</strong> ${studentName}
                            </div>
                            <div>
                                <strong>Grade:</strong> ${yearSection}
                            </div>
                        </div>
                        
                        <!-- Row 2: Parent Number + Status -->
                        <div class="d-flex flex-wrap">
                            <div class="mr-4 mb-2">
                                <strong>Parent Number:</strong> ${parentNumber}
                            </div>
                            <div class="mb-2">
                                <strong>Status:</strong> ${statusBadge}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-section">
                <div class="d-flex align-items-start">
                    <i class="fas fa-calendar-alt modal-icon"></i>
                    <div>
                        <h6 class="font-weight-bold">Absence Period</h6>
                        <div class="d-flex flex-wrap">
                            <div class="mr-4">
                                <strong>From:</strong> ${dateRange.split(' to ')[0]}
                            </div>
                            <div>
                                <strong>To:</strong> ${dateRange.split(' to ')[1]}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
           <div class="modal-section">
                <div class="d-flex align-items-start">
                    <i class="fas fa-chart-bar modal-icon"></i>
                    <div>
                        <h6 class="font-weight-bold">Attendance Summary</h6>
                        <div class="d-flex flex-wrap">
                            <div class="mr-4">
                                <strong>Missed Sessions:</strong> ${absentSessions}
                            </div>
                            <div>
                                <strong>Equivalent Days:</strong> 
                                ${Number.isInteger(absentSessions / 2) 
                                    ? (absentSessions / 2) 
                                    : (absentSessions / 2).toFixed(1)} days
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-section">
                <div class="d-flex align-items-start">
                    <i class="fas fa-sticky-note modal-icon"></i>
                    <div style="width: 100%;">
                        <h6 class="font-weight-bold">Admin Remarks</h6>
                        <textarea class="form-control editable-remarks" name="admin_remarks" rows="4">${adminRemarks || ''}</textarea>
                    </div>
                </div>
            </div>
            `;
            
            $('#detailsModalBody').html(content);
        });
    
       $('#refreshSmsBtn').click(function() {
    // add spinning class and immediately trigger reload on next tick
    $(this).find('i').addClass('fa-spin');

    // ensure the class is painted before navigation, then reload
    // small delay (e.g. 20-50ms) is enough and keeps the spinner visible until the page unloads
    setTimeout(function() {
        location.reload();
    }, 50);
});
        
        // Report status filter
        $('#reportStatusFilter').change(function() {
            const status = $(this).val();
            if (status) {
                $('table tbody tr').hide();
                $(`table tbody tr td:nth-child(8) .badge-${status}`).closest('tr').show();
            } else {
                $('table tbody tr').show();
            }
        });
        
        let websocket = null;
        let wsReady = false;

        function initWebSocket() {
            // Load saved WebSocket URL
            const wsUri = localStorage.getItem('websocketUri');
            const wsStatus = document.getElementById('wsStatus');
            
            if (!wsUri) {
                wsStatus.textContent = 'Not Configured';
                wsStatus.className = 'badge badge-warning ml-2';
                wsReady = false;
                return;
            }
            
            // Update input field
            $('#wsUrlInput').val(wsUri);
            
            // Create WebSocket connection
            websocket = new WebSocket(wsUri);

            websocket.onopen = function(evt) {
                wsStatus.textContent = 'Connected';
                wsStatus.className = 'badge badge-success ml-2';
                wsReady = true;
            };

            websocket.onclose = function(evt) {
                wsStatus.textContent = 'Disconnected';
                wsStatus.className = 'badge badge-danger ml-2';
                wsReady = false;
            };

            websocket.onerror = function(evt) {
                wsStatus.textContent = 'Error';
                wsStatus.className = 'badge badge-warning ml-2';
                wsReady = false;
            };

            wsStatus.textContent = 'Connecting...';
            wsStatus.className = 'badge badge-secondary ml-2';
        }

        // Save WebSocket settings
        $('#saveWsSettings').click(function() {
            const newUri = $('#wsUrlInput').val().trim();
            
            if (!newUri) {
                Swal.fire({
                    icon: 'error',
                    title: 'URL Required',
                    text: 'Please enter a WebSocket URL',
                    confirmButtonColor: '#d33'
                });
                return;
            }
            
            try {
                // Test if URL is valid
                new URL(newUri);
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid URL',
                    text: 'Please enter a valid WebSocket URL (format: ws://host:port)',
                    confirmButtonColor: '#d33'
                });
                return;
            }
            
            // Save to localStorage
            localStorage.setItem('websocketUri', newUri);
            
            // Close modal
            $('#wsSettingsModal').modal('hide');
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Settings Saved!',
                text: 'WebSocket connection will be established',
                confirmButtonColor: '#198754'
            });
            
            // Reinitialize WebSocket
            if (websocket) {
                websocket.close();
            }
            initWebSocket();
        });

        // Send SMS via WebSocket (wait until ready)
        function sendSMS(phoneNumber, message) {
            if (websocket && wsReady) {
                const data = {
                    receiver: phoneNumber,
                    message: message
                };
                websocket.send(JSON.stringify(data));
                console.log('SMS sent via WebSocket');
            } else {
                alert('WebSocket not connected. Please wait until status is "Connected".');
            }
        }

        // Only initialize WebSocket once, on page load
        $(document).ready(function() {
            initWebSocket();

            // Show configuration prompt if no WebSocket is configured
            setTimeout(function() {
                const wsStatus = document.getElementById('wsStatus');
                if (wsStatus.textContent === 'Not Configured') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'WebSocket Not Configured',
                        text: 'Please configure WebSocket connection to enable SMS sending',
                        confirmButtonColor: '#4169E1',
                        confirmButtonText: 'Configure Now'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $('#wsSettingsModal').modal('show');
                        }
                    });
                }
            }, 1500);
        });

       $(document).on('click', '.send-sms-btn', function(e) {
    e.preventDefault();
    let $btn = $(this);
    let $container = $btn.closest('.pending-sms-container');
    let $badge = $container.find('.sms-status-badge');
    let phoneNumber = ($btn.data('phone') || '').toString().trim();
    let message = ($btn.data('message') || '').toString().trim();
    let smsId = $container.find('input[name="sms_id"]').val();

    if (!phoneNumber || !message) {
        Swal.fire({
            icon: 'error',
            title: 'Missing Data',
            text: 'Phone number or message is missing!',
            confirmButtonColor: '#d33'
        });
        return;
    }

    if (!wsReady) {
        Swal.fire({
            icon: 'error',
            title: 'WebSocket Disconnected',
            text: 'Cannot send SMS. Please wait until the WebSocket status is "Connected".',
            confirmButtonColor: '#d33'
        });
        return;
    }

    // Change badge to "Sending..." and disable button immediately
    $badge.removeClass('badge-pending').addClass('badge-sending').text('Sending...');
    $btn.prop('disabled', true);

    // Send the SMS via WebSocket
    sendSMS(phoneNumber, message);

    // Show SweetAlert "Sending SMS..." for the same duration as the DB update
    Swal.fire({
        title: 'Sending SMS...',
        text: 'Please wait while the message is being sent',
        icon: 'info',
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            // After 9 seconds, update DB and show success
            setTimeout(function() {
                $.post('update_sms_status.php', { sms_id: smsId }, function(response) {
                    Swal.close(); // Close the "Sending..." dialog
                    if (response.trim() === "ok") {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sent successfully!',
                            text: 'The SMS has been sent to the parent.',
                            confirmButtonColor: '#198754'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        $badge.removeClass('badge-sending').addClass('badge-pending').text('Pending');
                        $btn.prop('disabled', false);
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: 'Could not update SMS status in the database.',
                            confirmButtonColor: '#d33'
                        });
                    }
                }).fail(function() {
                    $badge.removeClass('badge-sending').addClass('badge-pending').text('Pending');
                    $btn.prop('disabled', false);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while updating the SMS status.',
                        confirmButtonColor: '#d33'
                    });
                });
            }, 9000);
        }
    });
});

 // RESEND SMS BUTTON
$(document).on('click', '.resend-sms-btn', function () {
    const $btn = $(this);
    const smsId = $btn.data('id');
    const phone = $btn.data('phone');
    const message = $btn.data('message');

    if (!wsReady) {
        Swal.fire({
            icon: 'error',
            title: 'Cannot Resend',
            text: 'WebSocket connection is not established. Please wait until the status shows "Connected".',
            confirmButtonColor: '#d33'
        });
        return;
    }

    Swal.fire({
        title: 'Resend SMS?',
        text: 'This will resend the SMS to the parent.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Resend',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#ffc107'
    }).then((result) => {
        if (result.isConfirmed) {
            setButtonLoading($btn, true);
            sendSMS(phone, message);

            setTimeout(function () {
                $.post('update_sms_status.php', { sms_id: smsId }, function (response) {
                    if (response.trim() === "ok") {
                        Swal.fire({
                            icon: 'success',
                            title: 'SMS Resent!',
                            text: 'The SMS has been resent to the parent.',
                            confirmButtonColor: '#198754'
                        }).then(() => location.reload());
                    } else {
                        setButtonLoading($btn, false);
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: 'Could not update SMS status in the database.',
                            confirmButtonColor: '#d33'
                        });
                    }
                }).fail(function () {
                    setButtonLoading($btn, false);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while updating the SMS status.',
                        confirmButtonColor: '#d33'
                    });
                });
            }, 9000);
        }
    });
});

      // DELETE SMS BUTTON
$(document).on('click', '.delete-sms-btn', function () {
    const $btn = $(this);
    const smsId = $btn.data('id');

    Swal.fire({
        title: 'Delete SMS?',
        text: 'This will move the SMS to deleted messages.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            setButtonLoading($btn, true);
            $.post('delete_sms.php', { sms_id: smsId }, function (response) {
                if (response.trim() === "ok") {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: 'The SMS has been moved to deleted messages.',
                        confirmButtonColor: '#198754'
                    }).then(() => location.reload());
                } else {
                    setButtonLoading($btn, false);
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed',
                        text: 'Could not delete the SMS.',
                        confirmButtonColor: '#d33'
                    });
                }
            }).fail(function () {
                setButtonLoading($btn, false);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while deleting the SMS.',
                    confirmButtonColor: '#d33'
                });
            });
        }
    });
});

// PERMANENT DELETE SMS BUTTON
$(document).on('click', '.permanent-delete-sms-btn', function () {
    const $btn = $(this);
    const smsId = $btn.data('id');

    Swal.fire({
        title: 'Permanently Delete SMS?',
        text: 'This will permanently remove the SMS from the database.',
        icon: 'error',
        showCancelButton: true,
        confirmButtonText: 'Delete Permanently',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d33'
    }).then((result) => {
        if (result.isConfirmed) {
            setButtonLoading($btn, true);
            $.post('permanent_delete_sms.php', { sms_id: smsId }, function (response) {
                if (response.trim() === "ok") {
                    Swal.fire({
                        icon: 'success',
                        title: 'Permanently Deleted!',
                        text: 'The SMS has been permanently deleted.',
                        confirmButtonColor: '#198754'
                    }).then(() => location.reload());
                } else {
                    setButtonLoading($btn, false);
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed',
                        text: 'Could not permanently delete the SMS.',
                        confirmButtonColor: '#d33'
                    });
                }
            }).fail(function () {
                setButtonLoading($btn, false);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while permanently deleting the SMS.',
                    confirmButtonColor: '#d33'
                });
            });
        }
    });
});
        

         // Ensure the clicked button is recorded for server-side when browser may submit programmatically
        $(document).on('click', '#reviewForm button[name="approve_report"]', function() {
            $('#reviewFormAction').val('approve');
        });
        $(document).on('click', '#reviewForm button[name="reject_report"]', function() {
            $('#reviewFormAction').val('reject');
        });
        $(document).on('click', '#approveForm button[name="approve_report"]', function() {
            $('#approveFormAction').val('approve');
        });


        // Reset button handler
     // Improved reset button handler for active reports
$('#resetFilterBtn').on('click', function(e) {
    e.preventDefault();
    setButtonLoading($(this), true);

    // Clear search and status filter
    $('input[name="search"]').val('');
    $('#reportStatusFilter').val('');

    // Submit the form with anchor to processedReportsSection
    const $form = $('#filterForm');
    let action = $form.attr('action') || 'Admin_AbsentReport.php';
    if (action.indexOf('#processedReportsSection') === -1) {
        action += '#processedReportsSection';
    }
    $form.attr('action', action);

    // Submit the form (spinner stays until reload)
    $form.submit();
});
        
        // Automatically submit form when status filter changes
        $('#reportStatusFilter').change(function() {
            $('#filterForm').submit();
        });
        
        // Add anchor to form submission
        $('#filterForm').submit(function(e) {
            // Add anchor to form action
            const form = $(this);
            let action = form.attr('action') || '';
            if (action.indexOf('#') === -1) {
                form.attr('action', action + '#processedReportsSection');
            }
        });
        
        // On page load, scroll to anchor if present
        if (window.location.hash === '#processedReportsSection') {
            $('html, body').animate({
                scrollTop: $('#processedReportsSection').offset().top
            }, 100);
        }
        
        // Reset button handler for archived reports
      $('#archivedResetFilterBtn').on('click', function(e) {
    e.preventDefault();
    setButtonLoading($(this), true);

    // Clear search and status filter
    $('input[name="archived_search"]').val('');
    $('#archivedReportStatusFilter').val('');

    // Submit the form with anchor to processedReportsSection
    const $form = $('#archivedFilterForm');
    let action = $form.attr('action') || 'Admin_AbsentReport.php';
    if (action.indexOf('#processedReportsSection') === -1) {
        action += '#processedReportsSection';
    }
    $form.attr('action', action);

    // Submit the form (spinner stays until reload)
    $form.submit();
});
        
        // Automatically submit form when status filter changes (archived)
        $('#archivedReportStatusFilter').change(function() {
            $('#archivedFilterForm').submit();
        });
        
// Archive report with SweetAlert loading state and localStorage flag
$(document).on('click', '.archive-btn', function() {
    const $btn = $(this);
    const reportId = $btn.data('report-id');

    Swal.fire({
        title: 'Archive Report?',
        text: "This report will be moved to the archived section.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, archive it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $btn.addClass('btn-loading').prop('disabled', true);
            Swal.fire({
                title: 'Archiving...',
                text: 'Please wait while the report is being archived.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            $.ajax({
                url: 'Admin_AbsentReport.php',
                type: 'POST',
                data: {
                    report_id: reportId,
                    archive_report: 1
                },
                success: function(response) {
                    localStorage.setItem('archiveSuccess', '1');
                    location.reload();
                },
                error: function() {
                    $btn.removeClass('btn-loading').prop('disabled', false);
                    Swal.close();
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to archive report',
                        icon: 'error'
                    });
                }
            });
        }
    });
});

// Unarchive report with SweetAlert loading state and localStorage flag
$(document).on('click', '.unarchive-btn', function() {
    const $btn = $(this);
    const reportId = $btn.data('report-id');

    Swal.fire({
        title: 'Restore Report?',
        text: "This report will be moved back to the active section.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, restore it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $btn.addClass('btn-loading').prop('disabled', true);
            Swal.fire({
                title: 'Restoring...',
                text: 'Please wait while the report is being restored.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            $.ajax({
                url: 'Admin_AbsentReport.php',
                type: 'POST',
                data: {
                    report_id: reportId,
                    unarchive_report: 1
                },
                success: function(response) {
                    localStorage.setItem('unarchiveSuccess', '1');
                    location.reload();
                },
                error: function() {
                    $btn.removeClass('btn-loading').prop('disabled', false);
                    Swal.close();
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to restore report',
                        icon: 'error'
                    });
                }
            });
        }
    });
});

// Show SweetAlert after reload if archive/unarchive was successful
$(document).ready(function() {
    if (localStorage.getItem('archiveSuccess') === '1') {
        localStorage.removeItem('archiveSuccess');
        Swal.fire({
            icon: 'success',
            title: 'Archived!',
            text: 'Report archived successfully.',
            confirmButtonColor: '#3085d6'
        });
    }
    if (localStorage.getItem('unarchiveSuccess') === '1') {
        localStorage.removeItem('unarchiveSuccess');
        Swal.fire({
            icon: 'success',
            title: 'Restored!',
            text: 'Report restored successfully.',
            confirmButtonColor: '#3085d6'
        });
    }
});


// Show loading overlay
function showLoadingOverlay() {
    $('#loadingOverlay').fadeIn(100);
}

// Hide loading overlay (optional, for AJAX, not needed for navigation)
function hideLoadingOverlay() {
    $('#loadingOverlay').fadeOut(100);
}

// Search submit (active)
$('#filterForm').on('submit', function() {
    showLoadingOverlay();
});

// Search submit (archived)
$('#archivedFilterForm').on('submit', function() {
    showLoadingOverlay();
});

// Status filter change (active)
$('#reportStatusFilter').on('change', function() {
    showLoadingOverlay();
    $('#filterForm').submit();
});

// Status filter change (archived)
$('#archivedReportStatusFilter').on('change', function() {
    showLoadingOverlay();
    $('#archivedFilterForm').submit();
});

// Pagination links
$(document).on('click', '#processedReportsSection .pagination .page-link', function(e) {
    showLoadingOverlay();
    // Let the browser follow the link
});

// Toggle active/archived buttons
$(document).on('click', '#processedReportsSection .btn-group .btn', function(e) {
    showLoadingOverlay();
    // Let the browser follow the link
});

// Show loading overlay for pagination in Verify Pending Reports section
$(document).on('click', '#verify-absences-section .pagination .page-link', function(e) {
    showLoadingOverlay();
});



$('#profileForm').on('submit', function() {
    $('#saveProfileBtn').prop('disabled', true);
    $('#saveProfileBtn .btn-text').hide();
    $('#saveProfileBtn').append('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
});
    </script>



    <?php include 'search/Search_Admin.php'; ?>
</body>
</html>    