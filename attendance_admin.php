<?php
require_once 'session.php';

require_once 'config.php'; // provides $conn (PDO) and getSupabaseUrl()

// Handle logout request
if (isset($_POST['logout'])) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
    exit;
}

// Redirect to login if user not logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Only Admin allowed
if ($_SESSION['user']['user_role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user']; // safe to use

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save School Year settings
    if (isset($_POST['save_school_year'])) {
        $year_label = trim($_POST['year_label'] ?? '');
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;
        // Normalize attendance_enabled to boolean
        $attendance_enabled = (isset($_POST['attendance_enabled']) && $_POST['attendance_enabled'] === '1') ? true : false;
        $is_active = true;

        // Basic validation
        if (!$year_label) {
            $_SESSION['error'] = 'School year label is required.';
            header("Location: attendance_admin.php");
            exit;
        }

        if (!$start_date || !$end_date) {
            $_SESSION['error'] = 'Start date and end date are required.';
            header("Location: attendance_admin.php");
            exit;
        }

        // Use DateTime to validate ordering
        try {
            $start_dt = new DateTime($start_date);
            $end_dt = new DateTime($end_date);
        } catch (Exception $e) {
            $_SESSION['error'] = 'Invalid date format.';
            header("Location: attendance_admin.php");
            exit;
        }

        if ($start_dt > $end_dt) {
            $_SESSION['error'] = 'Start date cannot be after end date!';
            header("Location: attendance_admin.php");
            exit;
        }

        if ($start_dt->getTimestamp() === $end_dt->getTimestamp()) {
            $_SESSION['error'] = 'Start date and end date cannot be the same!';
            header("Location: attendance_admin.php");
            exit;
        }

        try {
            // Use transaction to ensure consistent state
            $conn->beginTransaction();

            // Lock any active row (if exists) for update
            $stmt = $conn->prepare("SELECT sy_id FROM school_years WHERE is_active = true LIMIT 1 FOR UPDATE");
            $stmt->execute();
            $row = $stmt->fetch();

            if ($row) {
                // Update existing active school year (bind types explicitly)
                $sy_id = $row['sy_id'];
                $update = $conn->prepare("
                    UPDATE school_years SET
                        year_label = :year_label,
                        start_date = :start_date,
                        end_date = :end_date,
                        attendance_enabled = :attendance_enabled,
                        is_active = :is_active
                    WHERE sy_id = :sy_id
                ");
                $update->bindValue(':year_label', $year_label, PDO::PARAM_STR);
                $update->bindValue(':start_date', $start_date, PDO::PARAM_STR);
                $update->bindValue(':end_date', $end_date, PDO::PARAM_STR);
                $update->bindValue(':attendance_enabled', $attendance_enabled, PDO::PARAM_BOOL);
                $update->bindValue(':is_active', $is_active, PDO::PARAM_BOOL);
                $update->bindValue(':sy_id', $sy_id, PDO::PARAM_INT);
                $update->execute();
            } else {
                // Deactivate all others then insert new active
                $conn->exec("UPDATE school_years SET is_active = false");

                $insert = $conn->prepare("
                    INSERT INTO school_years (year_label, start_date, end_date, is_active, attendance_enabled)
                    VALUES (:year_label, :start_date, :end_date, :is_active, :attendance_enabled)
                ");
                $insert->bindValue(':year_label', $year_label, PDO::PARAM_STR);
                $insert->bindValue(':start_date', $start_date, PDO::PARAM_STR);
                $insert->bindValue(':end_date', $end_date, PDO::PARAM_STR);
                $insert->bindValue(':is_active', $is_active, PDO::PARAM_BOOL);
                $insert->bindValue(':attendance_enabled', $attendance_enabled, PDO::PARAM_BOOL);
                $insert->execute();
            }

            $conn->commit();
            $_SESSION['success'] = 'School year settings saved successfully!';
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $_SESSION['error'] = 'Error saving school year: ' . $e->getMessage();
        }

        header("Location: attendance_admin.php");
        exit;
    }

    // Delete event
    if (isset($_POST['delete_event'])) {
        $calendar_id = intval($_POST['calendar_id'] ?? 0);
        if ($calendar_id > 0) {
            try {
                $del = $conn->prepare("DELETE FROM school_calendar WHERE calendar_id = :id");
                $del->bindValue(':id', $calendar_id, PDO::PARAM_INT);
                $del->execute();
                $_SESSION['success'] = 'Event deleted successfully!';
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error deleting event: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = 'Invalid event id.';
        }
        header("Location: attendance_admin.php");
        exit;
    }

    // Add new event to calendar
    if (isset($_POST['add_event'])) {
        $date = $_POST['event_date'] ?? null;
        $type = $_POST['event_type'] ?? 'RegularClass';
        $description = trim($_POST['event_description'] ?? '');
        $duration = $_POST['event_duration'] ?? 'full';
        $half_day = ($duration !== 'full') ? $duration : null;

        if (!$date || !$description) {
            $_SESSION['error'] = 'Date and description are required.';
            header("Location: attendance_admin.php");
            exit;
        }

        try {
            $insert = $conn->prepare("
                INSERT INTO school_calendar (date, type, description, half_day)
                VALUES (:date, :type, :description, :half_day)
            ");
            $insert->bindValue(':date', $date, PDO::PARAM_STR);
            $insert->bindValue(':type', $type, PDO::PARAM_STR);
            $insert->bindValue(':description', $description, PDO::PARAM_STR);
            if ($half_day === null) {
                $insert->bindValue(':half_day', null, PDO::PARAM_NULL);
            } else {
                $insert->bindValue(':half_day', $half_day, PDO::PARAM_STR);
            }
            $insert->execute();
            $_SESSION['success'] = 'Event added to calendar successfully!';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error adding event: ' . $e->getMessage();
        }

        header("Location: attendance_admin.php");
        exit;
    }
}

// Default current school year fallback
$current_sy = [
    'year_label' => '2024-2025',
    'start_date' => '2024-06-01',
    'end_date' => '2025-03-31',
    'attendance_enabled' => true
];

// Fetch active school year (Postgres uses true/false)
try {
    $stmt = $conn->prepare("SELECT * FROM school_years WHERE is_active = true LIMIT 1");
    $stmt->execute();
    $sy = $stmt->fetch();
    if ($sy) {
        // PDO fetch returns strings for booleans; convert to bool
        $sy['attendance_enabled'] = (bool)$sy['attendance_enabled'];
        $current_sy = $sy;
    }
} catch (Exception $e) {
    // leave default if error
}

// Get upcoming events (next 6)
$upcoming_events = [];
try {
    $stmt = $conn->prepare("SELECT calendar_id, date, type, description, half_day FROM school_calendar WHERE date >= CURRENT_DATE ORDER BY date ASC LIMIT 6");
    $stmt->execute();
    $upcoming_events = $stmt->fetchAll();
} catch (Exception $e) {
    $upcoming_events = [];
}

// Get all events for FullCalendar
$calendar_events = [];
try {
    $stmt = $conn->prepare("SELECT calendar_id, date, type, description, half_day FROM school_calendar");
    $stmt->execute();
    $all = $stmt->fetchAll();

    foreach ($all as $row) {
        $color = '';
        switch ($row['type']) {
            case 'Holiday': $color = '#e74a3b'; break;
            case 'Suspension': $color = '#f6c23e'; break;
            case 'School Event': $color = '#36b9cc'; break;
            case 'RegularClass': $color = '#4e73df'; break;
            default: $color = '#4e73df'; break;
        }

        $title = $row['description'];
        if (!empty($row['half_day'])) {
            $title .= " ({$row['half_day']})";
        }

        $calendar_events[] = [
            'id' => $row['calendar_id'],
            'title' => $title,
            'start' => $row['date'],
            'color' => $color,
            'description' => $row['description']
        ];
    }
} catch (Exception $e) {
    $calendar_events = [];
}

// Calculate school year progress
$start = new DateTime($current_sy['start_date']);
$end = new DateTime($current_sy['end_date']);
$today = new DateTime();

$total_days = $start->diff($end)->days + 1;

if ($today < $start) {
    $days_passed = 0;
} elseif ($today > $end) {
    $days_passed = $total_days;
} else {
    $days_passed = $start->diff($today)->days + 1;
}

$progress = min(100, max(0, ($days_passed / $total_days) * 100));

// Compute profile image URL
$profileImage = $user['profile_image'] ?? '';
if (strpos($profileImage, 'http') === 0) {
    $profileImageUrl = $profileImage;
} else if (!empty($profileImage)) {
    $profileImageUrl = getSupabaseUrl($profileImage);
} else {
    $profileImageUrl = "https://ui-avatars.com/api/?name=" . urlencode($user['fullname']) . "&size=200&background=random";
}

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Attendance Calendar</title>

    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">
     <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
     <!-- FullCalendar CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">

<!-- Add this if you want tooltips -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.11.6/umd/popper.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tooltip.js/1.3.3/tooltip.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tooltip.js/1.3.3/tooltip.min.css" />
     
    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
     <script>
        // Pass PHP events to JavaScript
        const calendarEvents = <?php echo json_encode($calendar_events); ?>;
    </script>
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



     :root {
            --primary: #4e73df;
            --secondary: #858796;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #5a5c69;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', sans-serif;
        }
        
        .dashboard-section {
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: none;
            transition: transform 0.3s ease;
            margin-bottom: 25px;
            overflow: hidden;
            height: 100%;
        }
        
        .dashboard-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.12);
        }
        
        .section-header {
            border-radius: 10px 10px 0 0 !important;
            padding: 1.25rem 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
        }
        
        .section-header i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .form-container {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .active-status {
            background-color: rgba(28, 200, 138, 0.15);
            color: var(--success);
        }
        
        .inactive-status {
            background-color: rgba(231, 74, 59, 0.15);
            color: var(--danger);
        }
        
        .event-type {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .holiday-type {
            background-color: rgba(231, 74, 59, 0.15);
            color: var(--danger);
        }

        
       /* Add specific background for School Event */
.schoolevent-type {
    background-color: rgba(54, 185, 204, 0.15); /* Light blue background */
    color: var(--info); /* Blue text */
}
        
        .suspension-type {
            background-color: rgba(244, 195, 72, 0.15);
            color: var(--warning);
        }
        
        .regular-type {
            background-color: rgba(78, 115, 223, 0.15);
            color: var(--primary);
        }
        
        .event-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .event-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #eaecf4;
            transition: background-color 0.2s;
        }
        
        .event-item:hover {
            background-color: #f8f9fc;
        }
        
        .event-date {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-success {
            background-color: var(--success);
            border-color: var(--success);
        }
        
        .btn-primary:hover, .btn-success:hover {
            opacity: 0.9;
        }
        
        .progress {
            height: 8px;
        }
        
        .legend-item {
            display: inline-flex;
            align-items: center;
            margin-right: 1rem;
            font-size: 0.85rem;
        }
        
        .legend-color {
            width: 15px;
            height: 15px;
            border-radius: 3px;
            margin-right: 0.5rem;
        }
        
        .holiday-legend {
            background-color: #e74a3b;
        }
        
        .event-legend {
            background-color: #36b9cc;
        }
        
        .suspension-legend {
            background-color: #f6c23e;
        }
        
        .regular-legend {
            background-color: #4e73df;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 15px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--secondary);
        }
        
        .progress-container {
            margin-top: 15px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            padding: 1.5rem;
            border-radius: 10px;
            color: white;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .top-row {
            margin-bottom: 1.5rem;
        }
        
        .card-content {
            padding: 20px;
        }
        
        .calendar-container {
            margin-top: 2rem;
            overflow-x: auto;
        }

        /* FullCalendar Responsive Adjustments */
.fc-toolbar.fc-header-toolbar {
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
}

.fc .fc-button {
    background-color: #4e73df !important; /* Blue buttons */
    border-color: #4e73df !important;
    color: #fff !important;
    padding: 5px 10px;
    font-size: 0.85rem;
    border-radius: 5px;
    transition: all 0.3s ease;
}

.fc .fc-button:hover {
    opacity: 0.9;
}

.fc .fc-button-primary:not(:disabled):active, 
.fc .fc-button-primary:not(:disabled).fc-button-active {
    background-color: #224abe !important;
    border-color: #224abe !important;
}

@media (max-width: 768px) {
    #schoolCalendar {
        font-size: 0.8rem;
    }

    .fc .fc-toolbar-title {
        font-size: 1rem;
    }

    .fc .fc-button {
        padding: 4px 8px;
        font-size: 0.75rem;
    }

    .fc-daygrid-day-number {
        font-size: 0.7rem;
    }

    .fc-event-title {
        font-size: 0.7rem;
    }

    .fc .fc-toolbar.fc-header-toolbar {
        flex-direction: column;
        align-items: center;
    }
}

  .badge-info {
    background-color: #17a2b8;
    padding: 0.25em 0.4em;
    font-size: 0.75em;
    border-radius: 0.25rem;
}

  .delete-event-btn {
    margin-left: 10px;
    padding: 0.15rem 0.5rem;
    font-size: 0.75rem;
}
    </style>
</head>

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
                         <span>Dashboard</span></a>
                         </li>

                   <!-- Nav Item - Pages -->
                            <li class="nav-item "> 
                                <a class="nav-link" href="#" data-toggle="collapse" data-target="#collapsePages" aria-expanded="true" aria-controls="collapsePages"> 
                                    <i class="fas fa-copy"></i> 
                                    <span>Pages</span>
                                 </a>
                                  <div id="collapsePages" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
                                     <div class="bg-white py-2 collapse-inner rounded"> <a class="collapse-item" href="message.php">Home Page</a>
                                      <a class="collapse-item" href="News_Eve.php">News & Events</a> <a class="collapse-item" href="Edit_Staff.php">Faculty & Staff</a>
                                       <a class="collapse-item" href="Edit_Achieve.php">Achievements</a> <a class="collapse-item" href="chart_edit.php">Organizational Chart</a>
                                           <a class="collapse-item" href="Edit_Contact.php">Contact</a>
                                        <a class="collapse-item" href="Edit_Gallery.php">Gallery</a> 
                                        <a class="collapse-item" href="Edit_History.php">History</a> 

                                    </div> </div>
                                     </li>
                                    <?php if ($user['id'] == 29 && $user['is_superadmin'] == 1): ?>
                                      <!-- Nav Item - User Management --> 
                                       <li class="nav-item">
                                         <a class="nav-link" href="Staff_Man.php">
                                             <i class="fas fa-users-cog">
                                             </i> 
                                             <span>Staff Management</span></a> 
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
                                                         <span>Manage Students</span></a>
                                                         </li>

                                                         <!-- Nav Item - Student Attendance -->
                            <li class="nav-item active"> 
                                <a class="nav-link" href="#" data-toggle="collapse" data-target="#collapseAttendance" aria-expanded="true" aria-controls="collapsePages"> 
                                    <i class="fas fa-calendar-week"></i> 
                                    <span>Student Attendance</span>
                                 </a>
                                  <div id="collapseAttendance" class="collapse show" aria-labelledby="headingPages" data-parent="#accordionSidebar">
                                     <div class="bg-white py-2 collapse-inner rounded">
                                      <a class="collapse-item " href="Attendance_logs.php">Attendance Logs</a> 
                                       <a class="collapse-item active" href="attendance_admin.php">Attendance Calendar</a> 
                                           <a class="collapse-item" href="Admin_AbsentReport.php">Verify Absent Reports</a>  
                                           <a class="collapse-item" href="barcode_scanner.php">Scan Attendance</a>

                                                                           
                                    </div> </div>
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
             
        
    <!-- Main Content -->
  <div class="container-fluid mt-4">
                    <div class="container-fluid px-1 px-sm-3 mt-2">
                        <div class="d-sm-flex align-items-center justify-content-between mb-4">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-calendar-week" style="color: rgb(11, 104, 245); font-size: 1.3rem; margin-right: 0.8rem;"></i>
                                <h2 class="fw-bolder mb-0" style="color: rgb(11, 104, 245); font-size: 1.5rem; font-weight: 800;">Attendance Calendar</h2>
                            </div>
                        </div>
                    </div>

                    <!-- First Row: Set School Year + Add School Event/Holiday -->
                    <div class="row top-row">
                        <!-- Set School Year Form -->
                        <div class="col-lg-6 mb-3">
                            <div class="dashboard-section">
                                <div class="section-header bg-primary text-white">
                                    <i class="fas fa-edit"></i> Set Attendance School Year
                                </div>
                                <div class="card-content">
                                    <form method="POST" id="saveSchoolForm">
                                        <input type="hidden" name="save_school_year" value="1">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">School Year</label>
                                            <input type="text" name="year_label" class="form-control" placeholder="e.g., 2024-2025" value="<?= htmlspecialchars($current_sy['year_label']) ?>" required>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <div class="mb-0">
                                                    <label class="form-label fw-bold">Start Date</label>
                                                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($current_sy['start_date']) ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-0">
                                                    <label class="form-label fw-bold">End Date</label>
                                                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($current_sy['end_date']) ?>" required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label fw-bold">Barcode Attendance System</label>
                                            <select name="attendance_enabled" class="form-control">
                                                <option value="1" <?= $current_sy['attendance_enabled'] ? 'selected' : '' ?>>Enabled</option>
                                                <option value="0" <?= !$current_sy['attendance_enabled'] ? 'selected' : '' ?>>Disabled</option>
                                            </select>
                                        </div>

                                        <button type="submit" id="saveSchoolYearBtn" class="btn btn-primary w-100 py-2 fw-bold">
                                            <i class="fas fa-save mr-2"></i><span class="btn-text">Save School Year</span>
                                            <span class="spinner-border spinner-border-sm d-none ml-2" role="status" aria-hidden="true"></span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Add Event Form -->
                        <div class="col-lg-6 mb-3">
                            <div class="dashboard-section">
                                <div class="section-header bg-success text-white">
                                    <i class="fas fa-calendar-plus"></i> Add Events / Holiday
                                </div>
                                <div class="card-content">
                                    <form method="POST" id="eventForm">
                                        <input type="hidden" name="add_event" value="1">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Date</label>
                                            <input type="date" name="event_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <div class="mb-0">
                                                    <label class="form-label fw-bold">Type</label>
                                                    <select name="event_type" class="form-control" required>
                                                        <option value="Holiday">Holiday</option>
                                                        <option value="Suspension">Class Suspension</option>                                                 
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="mb-0">
                                                    <label class="form-label fw-bold">Duration</label>
                                                    <select name="event_duration" class="form-control" required>
                                                        <option value="full">Full Day</option>
                                                        <option value="AM">Half Day (AM)</option>
                                                        <option value="PM">Half Day (PM)</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label fw-bold">Description</label>
                                            <input type="text" name="event_description" class="form-control" placeholder="e.g., National Heroes Day" required>
                                        </div>

                                        <button type="submit" id="addToCalendarBtn" class="btn btn-success w-100 py-2 fw-bold">
                                            <i class="fas fa-plus-circle mr-2"></i><span class="btn-text">Add to Calendar</span>
                                            <span class="spinner-border spinner-border-sm d-none ml-2" role="status" aria-hidden="true"></span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Second Row: Current School Year + Upcoming Events -->
                    <div class="row">
                        <div class="col-lg-6 mb-4">
                            <div class="dashboard-section">
                                <div class="section-header bg-info text-white">
                                    <i class="fas fa-info-circle"></i> Current School Year
                                </div>
                                <div class="card-content">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h4 class="mb-0"><?= htmlspecialchars($current_sy['year_label']) ?></h4>
                                        <span class="status-badge <?= $current_sy['attendance_enabled'] ? 'active-status' : 'inactive-status' ?>">
                                            <?= $current_sy['attendance_enabled'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>

                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <div class="stat-card">
                                                <div class="stat-label">Start Date</div>
                                                <div class="stat-value"><?= date('M j', strtotime($current_sy['start_date'])) ?></div>
                                                <div class="stat-label"><?= date('Y', strtotime($current_sy['start_date'])) ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="stat-card">
                                                <div class="stat-label">End Date</div>
                                                <div class="stat-value"><?= date('M j', strtotime($current_sy['end_date'])) ?></div>
                                                <div class="stat-label"><?= date('Y', strtotime($current_sy['end_date'])) ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="progress-container">
                                        <div class="d-flex justify-content-between mb-1">
                                            <small class="fw-medium">Days Completed</small>
                                            <small><strong><?= round($progress) ?>%</strong></small>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress ?>%" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <p class="small text-muted mt-2 mb-0"><?= $days_passed ?> of <?= $total_days ?> days completed</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6 mb-4">
                            <div class="dashboard-section">
                                <div class="section-header bg-primary text-white">
                                    <i class="fas fa-list"></i> Upcoming Events & Holidays
                                </div>
                                <div class="card-content">
                                    <div class="event-list">
                                        <?php if (!empty($upcoming_events)): ?>
                                            <?php foreach ($upcoming_events as $event): ?>
                                                <div class="event-item">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="event-date"><?= date('M j, Y', strtotime($event['date'])) ?></span>
                                                        <div>
                                                            <span class="event-type <?= str_replace(' ', '', strtolower($event['type'])) ?>-type"><?= $event['type'] ?></span>
                                                            <?php if ($event['half_day']): ?>
                                                                <span class="badge badge-info ml-1"><?= $event['half_day'] ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                                        <p class="mb-0"><?= htmlspecialchars($event['description']) ?></p>
                                                        <form method="POST" class="delete-event-form" data-id="<?= $event['calendar_id'] ?>">
                                                            <input type="hidden" name="calendar_id" value="<?= $event['calendar_id'] ?>">
                                                            <button type="button" class="btn btn-sm btn-danger delete-event-btn">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-center py-5">
                                                <i class="fas fa-calendar-times fa-4x text-muted mb-2"></i>
                                                <p>No upcoming Holidays</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <button class="btn btn-outline-secondary w-100 mt-3">
                                        <i class="fas fa-calendar-alt mr-2"></i>View Full Calendar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                   
                    <!-- System Status (unchanged) -->
<div class="dashboard-section">
    <div class="section-header bg-primary text-white">
        <i class="fas fa-info-circle"></i> Attendance System Status
    </div>
    <div class="card-content">
        <div class="row">
            <div class="col-lg-6 mb-3 mb-lg-0">
                <div class="alert alert-success h-100">
                    <h5 class="alert-heading">Current Status</h5>
                    <p>The attendance system is currently <strong><?= $current_sy['attendance_enabled'] ? 'ACTIVE' : 'INACTIVE' ?></strong> for the current academic term.</p>
                    <hr>
                    <p class="mb-0">
                        <?= $current_sy['attendance_enabled']
                            ? 'Admin/Teachers can manually mark absences after 12:30 PM for the AM session and after 5:30 PM for the PM session.'
                            : 'Attendance recording is currently disabled.' ?>
                    </p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="alert alert-warning h-100">
                    <h5 class="alert-heading">Important Notes</h5>
                    <ul class="mb-0">
                        <li>Attendance recording is only active during the academic school year</li>
                        <li>No attendance is recorded on weekends or non-school days</li>
                        <li>Students are marked absent if they have not scanned in by the specified time.</li>
                        <li>AM Login: 6:00 AM - 8:30 AM | AM Logout: 10:00 AM - 12:30 PM</li>
                        <li>PM Login: 12:30 PM - 1:30 PM | PM Logout: 3:00 PM - 5:30 PM</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

                    <!-- School Calendar -->
                    <div class="dashboard-section calendar-container" id="calendarSection">
                        <div class="section-header bg-primary text-white">
                            <i class="fas fa-calendar-times"></i> Official No-Class Day Calendar
                        </div>
                        <div class="card-content">
                            <div class="d-flex flex-wrap mb-3">
                                <div class="legend-item"><div class="legend-color holiday-legend"></div><span>Holiday</span></div>
                                <div class="legend-item"><div class="legend-color suspension-legend"></div><span>Class Suspension</span></div>
                            </div>
                            <div id="schoolCalendar"></div>
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

    <!-- Page level custom scripts -->
    <script src="js/demo/chart-area-demo.js"></script>
    <script src="js/demo/chart-pie-demo.js"></script>
<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>


 <script>
    // Profile image upload validation
    $('#profileImageInput').change(function(event) {
        const file = event.target.files[0];
        const maxSize = 25 * 1024 * 1024; // 25MB
        if (file) {
            if (file.size > maxSize) {
                Swal.fire({ icon: 'error', title: 'File Too Large', text: 'The selected image exceeds 25MB. Please choose a smaller file.', confirmButtonColor: '#d33' });
                event.target.value = '';
                return;
            }
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                Swal.fire({ icon: 'error', title: 'Invalid File Type', text: 'Only JPG, JPEG, PNG, and WEBP are allowed.', confirmButtonColor: '#d33' });
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

    // Toggle password visibility
    function togglePassword(fieldId, icon) {
        const field = document.getElementById(fieldId);
        const isHidden = field.type === 'password';
        field.type = isHidden ? 'text' : 'password';
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
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize FullCalendar
        var calendarEl = document.getElementById('schoolCalendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: { left: 'prev,next', center: 'title', right: 'today' },
            events: calendarEvents,
            eventClick: function(info) {
                Swal.fire({
                    title: info.event.title,
                    html: `<p>${info.event.extendedProps.description || ''}</p><p><strong>Date:</strong> ${info.event.start.toLocaleDateString()}</p>`,
                    icon: 'info',
                    confirmButtonColor: '#3085d6',
                });
            }
        });
        calendar.render();
    });
    </script>

    <script>
    // Add event confirmation with loading state
    document.getElementById('eventForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const date = form.querySelector('[name="event_date"]').value;
        const type = form.querySelector('[name="event_type"]').value;
        const duration = form.querySelector('[name="event_duration"]').value;
        const btn = document.getElementById('addToCalendarBtn');
        Swal.fire({
            title: 'Confirm Event Addition',
            html: `Are you sure you want to add this ${duration.toLowerCase()} event?<br><b>${type}</b> on <b>${date}</b><br>This day will not be recorded in attendance recording.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, add it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // show loading state
                btn.querySelector('.btn-text').textContent = 'Adding...';
                btn.querySelector('.spinner-border').classList.remove('d-none');
                btn.disabled = true;
                form.submit();
            }
        });
    });

    // School year form validation + loading state
    document.getElementById('saveSchoolForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const startVal = form.querySelector('[name="start_date"]').value;
        const endVal = form.querySelector('[name="end_date"]').value;
        const btn = document.getElementById('saveSchoolYearBtn');

        if (!startVal || !endVal) {
            Swal.fire({ icon: 'error', title: 'Invalid Dates', text: 'Start date and end date are required.' });
            return;
        }

        const startDate = new Date(startVal);
        const endDate = new Date(endVal);

        if (startDate > endDate) {
            Swal.fire({ icon: 'error', title: 'Invalid Dates', text: 'Start date cannot be after end date!' });
            return;
        }
        if (startDate.getTime() === endDate.getTime()) {
            Swal.fire({ icon: 'error', title: 'Invalid Dates', text: 'Start date and end date cannot be the same!' });
            return;
        }

        // show loading state and submit
        btn.querySelector('.btn-text').textContent = 'Saving...';
        btn.querySelector('.spinner-border').classList.remove('d-none');
        btn.disabled = true;
        form.submit();
    });

    // Delete event handlers
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.delete-event-btn').forEach(button => {
            button.addEventListener('click', function() {
                const form = this.closest('form');
                const eventId = form.dataset.id;
                const eventDesc = form.closest('.event-item').querySelector('p').textContent.trim();
                Swal.fire({
                    title: 'Delete Event?',
                    html: `Are you sure you want to delete <b>"${eventDesc}"</b>?<br>This action cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const deleteForm = document.createElement('form');
                        deleteForm.method = 'POST';
                        deleteForm.action = 'attendance_admin.php';

                        const deleteInput = document.createElement('input');
                        deleteInput.type = 'hidden';
                        deleteInput.name = 'delete_event';
                        deleteInput.value = '1';

                        const idInput = document.createElement('input');
                        idInput.type = 'hidden';
                        idInput.name = 'calendar_id';
                        idInput.value = eventId;

                        deleteForm.appendChild(deleteInput);
                        deleteForm.appendChild(idInput);
                        document.body.appendChild(deleteForm);
                        deleteForm.submit();
                    }
                });
            });
        });
    });

    // View full calendar button scroll
    document.addEventListener('DOMContentLoaded', function() {
        const viewCalendarBtn = document.querySelector('.btn-outline-secondary');
        if (viewCalendarBtn) {
            viewCalendarBtn.addEventListener('click', function() {
                const calendarSection = document.getElementById('calendarSection');
                if (calendarSection) calendarSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
    });
    </script>

<?php include 'search/Search_Admin.php'; ?>
</body>

</html>