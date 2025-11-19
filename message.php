<?php
require_once 'session.php';

require_once 'config.php';

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

// Redirect if user is not an Admin
if ($_SESSION['user']['user_role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

// Fetch current data (welcome message)
$stmt = $conn->prepare("SELECT * FROM welcome_message WHERE id = 1");
$stmt->execute();
$row = $stmt->fetch();
if (!$row) {
    // ensure at least one row exists
    $conn->prepare("INSERT INTO welcome_message (id, teacher_name, teacher_title, paragraph1, paragraph2, paragraph3, teacher_image) VALUES (1, '', '', '', '', '', '')")->execute();
    $stmt->execute();
    $row = $stmt->fetch();
}

// Fetch calendar events
$calendar_events = [];
$stmt = $conn->query("SELECT * FROM calendar_events ORDER BY start_date DESC");
if ($stmt) {
    $calendar_events = $stmt->fetchAll();
}

// Fetch downloadables
$downloadables = [];
$stmt = $conn->query("SELECT * FROM downloadables ORDER BY upload_date DESC");
if ($stmt) {
    $downloadables = $stmt->fetchAll();
}

// Helper: determine preview URL for teacher image
$teacherImageUrl = '';
if (!empty($row['teacher_image'])) {
    // if it's an absolute URL keep it, else assume it's object name in welc_profile bucket
    if (strpos($row['teacher_image'], 'http') === 0) {
        $teacherImageUrl = $row['teacher_image'];
    } else {
        $teacherImageUrl = getSupabaseUrl($row['teacher_image'], SUPABASE_BUCKET_WELC);
    }
}

// Compute profile image URL (profile images remain in profile_images bucket)
$profileImage = $user['profile_image'] ?? '';
if (strpos($profileImage, 'http') === 0) {
    $profileImageUrl = $profileImage;
} else if (!empty($profileImage)) {
    $profileImageUrl = getSupabaseUrl($profileImage, SUPABASE_BUCKET);
} else {
    $profileImageUrl = "https://ui-avatars.com/api/?name=" . urlencode($user['fullname']) . "&size=200&background=random";
}


// FORM PROCESSING

// 1. Handle calendar events (add/edit)
if (isset($_POST['event_action'])) {
    $title = trim($_POST['title'] ?? '');
    $short_desc = trim($_POST['short_desc'] ?? '');
    $start_date = $_POST['start_date'] ?? null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

    try {
        if ($_POST['event_action'] === 'add') {
            $stmt = $conn->prepare("INSERT INTO calendar_events (title, short_desc, start_date, end_date) VALUES (:title, :short_desc, :start_date, :end_date)");
            $stmt->execute([
                ':title' => $title,
                ':short_desc' => $short_desc,
                ':start_date' => $start_date,
                ':end_date' => $end_date
            ]);
            $_SESSION['success'] = "Event added successfully!";
        } else if ($_POST['event_action'] === 'edit') {
            $event_id = (int)$_POST['event_id'];
            $stmt = $conn->prepare("UPDATE calendar_events SET title = :title, short_desc = :short_desc, start_date = :start_date, end_date = :end_date WHERE id = :id");
            $stmt->execute([
                ':title' => $title,
                ':short_desc' => $short_desc,
                ':start_date' => $start_date,
                ':end_date' => $end_date,
                ':id' => $event_id
            ]);
            $_SESSION['success'] = "Event updated successfully!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header("Location: message.php");
    exit;
}

// 2. Handle downloadables (add/edit)
if (isset($_POST['download_action'])) {
    $title_label = trim($_POST['title_label'] ?? '');
    $file_url = trim($_POST['file_url'] ?? '');
    $file_size = !empty($_POST['file_size']) ? trim($_POST['file_size']) : null;

    try {
        if ($_POST['download_action'] === 'add') {
            $stmt = $conn->prepare("INSERT INTO downloadables (title_label, file_url, file_size) VALUES (:title_label, :file_url, :file_size)");
            $stmt->execute([
                ':title_label' => $title_label,
                ':file_url' => $file_url,
                ':file_size' => $file_size
            ]);
            $_SESSION['success'] = "Downloadable added successfully!";
        } else if ($_POST['download_action'] === 'edit') {
            $download_id = (int)$_POST['download_id'];
            $stmt = $conn->prepare("UPDATE downloadables SET title_label = :title_label, file_url = :file_url, file_size = :file_size WHERE id = :id");
            $stmt->execute([
                ':title_label' => $title_label,
                ':file_url' => $file_url,
                ':file_size' => $file_size,
                ':id' => $download_id
            ]);
            $_SESSION['success'] = "Downloadable updated successfully!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    header("Location: message.php");
    exit;
}

// 3. Handle deletions
if (isset($_POST['delete_event'])) {
    $event_id = (int)$_POST['event_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM calendar_events WHERE id = :id");
        $stmt->execute([':id' => $event_id]);
        $_SESSION['success'] = "Event deleted successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting event: " . $e->getMessage();
    }
    header("Location: message.php");
    exit;
}

if (isset($_POST['delete_download'])) {
    $download_id = (int)$_POST['download_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM downloadables WHERE id = :id");
        $stmt->execute([':id' => $download_id]);
        $_SESSION['success'] = "Downloadable deleted successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting downloadable: " . $e->getMessage();
    }
    header("Location: message.php");
    exit;
}

// 4. Handle welcome message form (only process if form was submitted)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teacher_name'])) {
    $teacher_name = trim($_POST['teacher_name'] ?? '');
    $teacher_title = trim($_POST['teacher_title'] ?? '');
    $paragraph1 = trim($_POST['paragraph1'] ?? '');
    $paragraph2 = trim($_POST['paragraph2'] ?? '');
    $paragraph3 = trim($_POST['paragraph3'] ?? '');

    $newObjectName = $row['teacher_image']; // default keep old

    // Image upload handling (store in Supabase bucket 'welc_profile')
    if (!empty($_FILES['teacher_image']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $image_name = $_FILES['teacher_image']['name'];
        $ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $_SESSION['error'] = "Only JPG, JPEG, PNG, and WEBP files are allowed";
            header("Location: message.php");
            exit;
        }

        // Check file size (25MB max)
        if ($_FILES['teacher_image']['size'] > 25 * 1024 * 1024) {
            $_SESSION['error'] = "Image size must not exceed 25MB";
            header("Location: message.php");
            exit;
        }

        // Validate square dimensions server-side
        $tmpPath = $_FILES['teacher_image']['tmp_name'];
        $imgInfo = getimagesize($tmpPath);
        if ($imgInfo === false) {
            $_SESSION['error'] = "Invalid image file.";
            header("Location: message.php");
            exit;
        }
        if ($imgInfo[0] !== $imgInfo[1]) {
            $_SESSION['error'] = "Please upload a square image (equal width and height).";
            header("Location: message.php");
            exit;
        }

        // Generate a unique object name
        $newObjectName = uniqid('welc_') . '.' . $ext;

        // Upload to supabase welc_profile bucket
        $uploaded = uploadToSupabase($tmpPath, $newObjectName, SUPABASE_BUCKET_WELC);
        if (!$uploaded) {
            $_SESSION['error'] = "Failed to upload image to storage.";
            header("Location: message.php");
            exit;
        }

        // Delete old image from bucket if it looks like an object name (not an external URL and not local path)
        if (!empty($row['teacher_image'])) {
            $old = $row['teacher_image'];
            // If old value looks like an object name (no http and not starting with 'images/')
            if (strpos($old, 'http') !== 0 && strpos($old, 'images/') !== 0 && !file_exists($old)) {
                // assume it's object name in welc_profile bucket
                try {
                    deleteFromSupabase($old, SUPABASE_BUCKET_WELC);
                } catch (\Exception $e) {
                    // non-fatal - ignore
                }
            } elseif (strpos($old, 'images/welc_profile/') === 0 || file_exists($old)) {
                // legacy local file - attempt delete
                @unlink($old);
            }
        }
    }

    // Update database (store object name for teacher_image when uploaded to supabase)
    try {
        $stmt = $conn->prepare("UPDATE welcome_message SET teacher_name = :teacher_name, teacher_title = :teacher_title, paragraph1 = :p1, paragraph2 = :p2, paragraph3 = :p3, teacher_image = :timg WHERE id = 1");
        $stmt->execute([
            ':teacher_name' => $teacher_name,
            ':teacher_title' => $teacher_title,
            ':p1' => $paragraph1,
            ':p2' => $paragraph2,
            ':p3' => $paragraph3,
            ':timg' => $newObjectName
        ]);
        $_SESSION['success'] = "Welcome message updated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update welcome message: " . $e->getMessage();
    }

    header("Location: message.php");
    exit;
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

    <title>Edit Homepage</title>

    <!-- Custom fonts for this template-->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs4/dt-1.11.5/datatables.min.css"/>
     
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
            max-width: 140px;
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
            background-color:rgba(68, 48, 248, 0.28) !important;
            color: white !important;
            border-radius: 0.35rem;
        }

        /* Ensure icons and text inside stay white on hover */
        .nav-item:not(.no-arrow):hover .nav-link i,
        .nav-item:not(.no-arrow):hover .nav-link span {
            color: white !important;
        }

        /* Cancel button style */
        #cancelChanges {
            display: none;
            margin-left: 10px;
        }

        /* Form field change detection */
        .form-control.changed {
            border-color: #17a2b8;
            box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25);
        }



      
          /* New styles for tables */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        .add-btn {
            background: linear-gradient(135deg, #0d6efd, #6610f2);
            border: none;
            border-radius: 50px;
            padding: 8px 20px;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(13, 110, 253, 0.25);
            transition: all 0.3s ease;
        }
        
        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(13, 110, 253, 0.35);
        }
        
        .data-table-container {
            margin-bottom: 3rem;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.17);
            padding: 20px;
        }
        
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .dataTables_wrapper {
            padding: 15px;
        }
        
        .dataTables_length, .dataTables_filter {
            margin-bottom: 15px;
        }
        
        .dataTables_paginate {
            margin-top: 15px;
        }
        
        table.dataTable {
            border-collapse: collapse !important;
        }
        
        .dataTable th {
            background: linear-gradient(135deg, #0d6efd, #6610f2);
            color: white;
            font-weight: 600;
            border: none !important;
        }
        
        .dataTable tr {
            transition: background-color 0.2s;
        }
        
        .dataTable tr:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        
        .dataTable td {
            vertical-align: middle;
            padding: 12px 15px !important;
            border-bottom: 1px solid #dee2e6 !important;
        }
        
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        
        .btn-edit {
            background: #1c50f8ff;
            border: none;
        }
        
        .btn-delete {
            background: #dc3545;
            border: none;
        }
        
        .btn-download {
            background: #198754;
            border: none;
        }
        
        .btn-edit:hover {
            background: #0a1fc0ff;
        }
        
        .btn-delete:hover {
            background: #bb2d3b;
        }
        
        .btn-download:hover {
            background: #146c43;
        }
        
        .date-badge {
            background: #e9ecef;
            border-radius: 8px;
            padding: 5px 12px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            margin: 3px 0;
        }
        
        .file-link {
            word-break: break-all;
        }
        
        .action-cell {
            white-space: nowrap;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .add-btn {
                width: 100%;
            }
            
            .dataTables_wrapper .dataTables_length, 
            .dataTables_wrapper .dataTables_filter {
                float: none;
                text-align: left;
            }
            
            .action-cell {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .action-cell .btn {
                width: 100%;
            }
        }
        
        /* Modal styles */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #0d6efd, #6610f2);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            border: none;
        }

        .modal-title {
            font-weight: 700;
        }
        
        .close {
            color: white;
            text-shadow: none;
            opacity: 0.8;
        }
        
        .close:hover {
            color: white;
            opacity: 1;
        }


         /* Force horizontal scrolling on small screens */
    @media (max-width: 767px) {
        .table-responsive {
            display: block;
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Increase column widths for better mobile viewing */
        #eventsTable th:nth-child(1),
        #eventsTable td:nth-child(1) {
            min-width: 120px;
        }
        #eventsTable th:nth-child(2),
        #eventsTable td:nth-child(2) {
            min-width: 150px;
        }
        #eventsTable th:nth-child(3),
        #eventsTable td:nth-child(3) {
            min-width: 130px;
        }
        #eventsTable th:nth-child(4),
        #eventsTable td:nth-child(4) {
            min-width: 150px;
        }
        
        #downloadsTable th:nth-child(1),
        #downloadsTable td:nth-child(1) {
            min-width: 120px;
        }
        #downloadsTable th:nth-child(2),
        #downloadsTable td:nth-child(2) {
            min-width: 200px;
        }
        #downloadsTable th:nth-child(3),
        #downloadsTable td:nth-child(3) {
            min-width: 100px;
        }
        #downloadsTable th:nth-child(4),
        #downloadsTable td:nth-child(4) {
            min-width: 120px;
        }
        #downloadsTable th:nth-child(5),
        #downloadsTable td:nth-child(5) {
            min-width: 150px;
        }
        
        /* Make buttons more touch-friendly */
        .action-cell .btn {
            padding: 6px 10px;
            font-size: 14px;
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
                Administrator
            </div>

            <!-- White Divider Below Title -->
            <hr class="sidebar-divider d-none d-md-block" style="border-top: 1px solid white; margin-top: 1px; margin-bottom: 8px;">

            <!-- Admin Info Block -->
            <div class="d-flex align-items-center justify-content-center flex-column flex-md-row text-center text-md-left px-3 mb-3 pt-2" id="adminProfile">
                <img src="<?= htmlspecialchars($profileImageUrl) ?>" class="rounded-circle mb-2 mb-md-0" width="45" height="45" alt="Admin Image" style="border: 1.5px solid gray;">
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
            <li class="nav-item active"> 
                <a class="nav-link" href="#" data-toggle="collapse" data-target="#collapsePages" aria-expanded="true" aria-controls="collapsePages"> 
                    <i class="fas fa-copy"></i> 
                    <span>Pages</span>
                </a>
                <div id="collapsePages" class="collapse show" aria-labelledby="headingPages" data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded"> 
                        <a class="collapse-item active" href="message.php">Home Page</a>
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
            <li class="nav-item "> 
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseAttendance" aria-expanded="true" aria-controls="collapsePages"> 
                    <i class="fas fa-calendar-week"></i> 
                    <span>Student Attendance</span>
                </a>
                <div id="collapseAttendance" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded">
                        <a class="collapse-item" href="Attendance_logs.php">Attendance Logs</a> 
                        <a class="collapse-item" href="attendance_admin.php">Attendance Calendar</a> 
                        <a class="collapse-item" href="Admin_AbsentReport.php">Verify Absent Reports</a>
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
            <div class="text-center d-none d-md-inline" style="margin-top: 20px;">
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
                            <input type="text" class="form-control bg-light border-0 small" placeholder="Search for..." aria-label="Search" aria-describedby="basic-addon2">
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
                            <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-search fa-fw"></i>
                            </a>
                            <!-- Dropdown - Messages -->
                            <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in" aria-labelledby="searchDropdown">
                                <form class="form-inline mr-auto w-100 navbar-search">
                                    <div class="input-group">
                                        <input type="text" class="form-control bg-light border-0 small" placeholder="Search for..." aria-label="Search" aria-describedby="basic-addon2">
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
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($user['fullname']) ?></span>
                                <img class="img-profile rounded-circle" src="<?= htmlspecialchars($profileImageUrl) ?>">
                            </a>
                            <!-- Dropdown - User Information -->
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
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

              <section class="admin-edit-welcome bg-light py-5">
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-11">
                                <div class="card shadow p-4" style="border-top: 8px solid #0d6efd; border-radius: 1rem;">
                                    <h2 class="text-center text-primary mb-2" style="font-weight: 900;">Welcome Message</h2>
                                    <hr class="mb-4">
                                    <form id="welcomeForm" action="" method="POST" enctype="multipart/form-data">
                                        <div class="row g-3">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-semibold">Head Teacher / Principal Name:</label>
                                                <input type="text" name="teacher_name" class="form-control" value="<?= htmlspecialchars($row['teacher_name']) ?>" required>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-semibold">Position</label>
                                                <input type="text" name="teacher_title" class="form-control" value="<?= htmlspecialchars($row['teacher_title']) ?>" required>
                                            </div>

                                            <div class="col-md-12 mb-3">
                                                <label class="form-label fw-semibold">Paragraph 1</label>
                                                <textarea name="paragraph1" class="form-control" rows="3" required><?= htmlspecialchars($row['paragraph1']) ?></textarea>
                                            </div>

                                            <div class="col-md-12 mb-3">
                                                <label class="form-label fw-semibold">Paragraph 2</label>
                                                <textarea name="paragraph2" class="form-control" rows="3" required><?= htmlspecialchars($row['paragraph2']) ?></textarea>
                                            </div>

                                            <div class="col-md-12 mb-3">
                                                <label class="form-label fw-semibold">Paragraph 3</label>
                                                <textarea name="paragraph3" class="form-control" rows="3"><?= htmlspecialchars($row['paragraph3']) ?></textarea>
                                            </div>

                                            <div class="col-md-12 mb-3">
                                                <label class="form-label fw-semibold">Upload Image:</label>
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($teacherImageUrl)): ?>
                                                        <div class="mr-3">
                                                            <img id="teacherPreviewImage" src="<?= htmlspecialchars($teacherImageUrl) ?>" alt="Teacher Image" class="rounded" style="width: 100px; height: 100px; object-fit: cover;">
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="mr-3">
                                                            <img id="teacherPreviewImage" src="https://via.placeholder.com/100" alt="Teacher Image" class="rounded" style="width: 100px; height: 100px; object-fit: cover;">
                                                        </div>
                                                    <?php endif; ?>

                                                    <div style="flex: 1;">
                                                        <input type="file" name="teacher_image" id="teacherImage" class="form-control w-75" accept=".jpg,.jpeg,.png,.webp">
                                                        <small class="text-muted"> - Only JPG, PNG, JPEG, WEBP files allowed.</small><br>
                                                        <small class="text-muted"> - Upload a square image (equal width and height).</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-12 text-center">
                                                <button type="submit" id="saveWelcomeBtn" class="btn btn-primary px-4 fw-bold mb-2">
                                                    <span class="btn-text">Save Changes</span>
                                                </button>
                                                <button type="button" id="cancelChanges" class="btn btn-secondary px-4 fw-bold mb-2">Cancel Changes</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>



 <!-- Calendar Events Section -->
                <section class="calendar-events bg-white py-5">
                    <div class="container">
                        <div class="section-header">
                            <h2 class="text-primary" style="font-weight: 800;">Calendar Events</h2>
                            <button type="button" class="btn btn-primary add-btn" data-toggle="modal" data-target="#eventModal" data-action="add">
                                <i class="fas fa-plus mr-2"></i>Add Event
                            </button>
                        </div>

                        <div class="data-table-container">
                            <div class="table-responsive">
                                <table id="eventsTable" class="table table-striped table-bordered" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Description</th>
                                            <th>Dates</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($calendar_events as $event): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($event['title']) ?></td>
                                            <td><?= htmlspecialchars($event['short_desc']) ?></td>
                                            <td>
                                                <div class="date-badge">
                                                    <i class="fas fa-play-circle mr-1"></i>
                                                    <?= date('M d, Y', strtotime($event['start_date'])) ?>
                                                </div>
                                                <?php if ($event['end_date']): ?>
                                                <div class="date-badge">
                                                    <i class="fas fa-flag-checkered mr-1"></i>
                                                    <?= date('M d, Y', strtotime($event['end_date'])) ?>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-cell">
                                                <button type="button" class="btn btn-sm btn-edit text-white edit-event"
                                                    data-toggle="modal" data-target="#eventModal"
                                                    data-action="edit"
                                                    data-id="<?= $event['id'] ?>"
                                                    data-title="<?= htmlspecialchars($event['title']) ?>"
                                                    data-short_desc="<?= htmlspecialchars($event['short_desc']) ?>"
                                                    data-start_date="<?= $event['start_date'] ?>"
                                                    data-end_date="<?= $event['end_date'] ?>">
                                                    <i class="fas fa-edit mr-1"></i> Edit
                                                </button>
                                                <form method="POST" class="d-inline delete-form">
                                                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                                    <button type="button" name="delete_event" class="btn btn-sm btn-delete text-white delete-event-btn">
                                                        <i class="fas fa-trash-alt mr-1"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
     

           <!-- Downloadables Section -->
                <section class="downloadables bg-light py-5">
                    <div class="container">
                        <div class="section-header">
                            <h2 class="text-primary" style="font-weight: 800;">Downloadables</h2>
                            <button type="button" class="btn btn-success add-btn" data-toggle="modal" data-target="#downloadModal" data-action="add">
                                <i class="fas fa-plus mr-2"></i>Add Downloadable
                            </button>
                        </div>

                        <div class="data-table-container">
                            <div class="table-responsive">
                                <table id="downloadsTable" class="table table-striped table-bordered" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th style="width: 130px;">Title</th>
                                            <th style="width: 300px;">File URL</th>
                                            <th>File Size</th>
                                            <th>Upload Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($downloadables as $download): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($download['title_label']) ?></td>
                                            <td><a href="<?= htmlspecialchars($download['file_url']) ?>" target="_blank" class="file-link"><?= htmlspecialchars($download['file_url']) ?></a></td>
                                            <td><?= $download['file_size'] ? htmlspecialchars($download['file_size']) : 'N/A' ?></td>
                                            <td><?= date('M d, Y', strtotime($download['upload_date'])) ?></td>
                                            <td class="action-cell">
                                                <a href="<?= htmlspecialchars($download['file_url']) ?>" target="_blank" class="btn btn-sm btn-download text-white">
                                                    <i class="fas fa-eye mr-1"></i> View
                                                </a>
                                                <button type="button" class="btn btn-sm btn-edit text-white edit-download"
                                                    data-toggle="modal" data-target="#downloadModal"
                                                    data-action="edit"
                                                    data-id="<?= $download['id'] ?>"
                                                    data-title_label="<?= htmlspecialchars($download['title_label']) ?>"
                                                    data-file_url="<?= htmlspecialchars($download['file_url']) ?>"
                                                    data-file_size="<?= htmlspecialchars($download['file_size']) ?>">
                                                    <i class="fas fa-edit mr-1"></i> Edit
                                                </button>
                                                <form method="POST" class="d-inline delete-form">
                                                    <input type="hidden" name="download_id" value="<?= $download['id'] ?>">
                                                    <button type="button" name="delete_download" class="btn btn-sm btn-delete text-white delete-download-btn">
                                                        <i class="fas fa-trash-alt mr-1"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Footer -->
                <footer class="sticky-footer bg-white">
                    <div class="container my-auto">
                        <div class="copyright text-center my-auto">
                            <span>Copyright &copy; 2025 Tomas SM. Bautista Elementary School.<br> All rights reserved.</span>
                        </div>
                    </div>
                </footer>
                <!-- End of Footer -->
            </div>
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

                        <!-- Profile Image Upload + Preview -->
                        <div class="form-group d-flex align-items-center">
                            <img id="previewImage" src="<?= htmlspecialchars($profileImageUrl) ?>" alt="Preview" style="width: 80px; height: 80px; border-radius: 10px; border: 1px solid #ccc; object-fit: cover; margin-right: 15px;">
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


    <!-- Event Modal -->
    <div class="modal fade" id="eventModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalLabel">Add Calendar Event</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                </div>
                <form id="eventForm" method="POST" action="message.php">
                    <div class="modal-body">
                        <input type="hidden" name="event_action" id="eventAction" value="add">
                        <input type="hidden" name="event_id" id="eventId">
                        <div class="form-group">
                            <label for="title">Event Title</label>
                            <input type="text" class="form-control" id="title" name="title" required placeholder="e.g. Buwan ng Wika Celebration">
                        </div>
                        <div class="form-group">
                            <label for="short_desc">Short Description</label>
                            <textarea class="form-control" id="short_desc" name="short_desc" rows="3" required placeholder="Briefly describe the event..."></textarea>
                        </div>
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date (Optional)</label>
                            <input type="date" class="form-control" id="end_date" name="end_date">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" id="saveEventBtn" class="btn btn-primary"><span class="btn-text">Save Event</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Downloadable Modal -->
    <div class="modal fade" id="downloadModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="downloadModalLabel">Add Downloadable</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                </div>
                <form id="downloadForm" method="POST" action="message.php">
                    <div class="modal-body">
                        <input type="hidden" name="download_action" id="downloadAction" value="add">
                        <input type="hidden" name="download_id" id="downloadId">
                        <div class="form-group">
                            <label for="title_label">Title</label>
                            <input type="text" class="form-control" id="title_label" name="title_label" placeholder="e.g., Student Handbook 2025" required>
                        </div>
                        <div class="form-group">
                            <label for="file_url">Google Drive Link</label>
                            <input type="url" class="form-control" id="file_url" name="file_url" required placeholder="https://drive.google.com/...">
                            <small class="form-text text-muted">Use restricted access for downloadable documents/forms that require permission. Set to “Anyone with the link” for public documents.</small>
                        </div>
                        <div class="form-group">
                            <label for="file_size">File Size (Optional)</label>
                            <input type="text" class="form-control" id="file_size" name="file_size" placeholder="e.g., 5.2 MB">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" id="saveDownloadBtn" class="btn btn-primary"><span class="btn-text">Save Downloadable</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>  



    <!-- Bootstrap core JavaScript-->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="js/sb-admin-2.min.js"></script>

    <!-- Page level plugins -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>

    <!-- Page level custom scripts -->
    <script src="js/demo/chart-area-demo.js"></script>
    <script src="js/demo/chart-pie-demo.js"></script>
      <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/v/bs4/dt-1.11.5/datatables.min.js"></script>

    
 <script>
    // Show success/error messages with SweetAlert
    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({ icon: 'success', title: 'Success!', text: "<?= addslashes($_SESSION['success']) ?>", confirmButtonColor: '#3085d6' });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({ icon: 'error', title: 'Error!', text: "<?= addslashes($_SESSION['error']) ?>", confirmButtonColor: '#d33' });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    $(document).ready(function() {
        // DataTables init
        $('#eventsTable').DataTable({ responsive:true, scrollX:true, pagingType: "full_numbers", language: { search: "_INPUT_", searchPlaceholder: "Search events..." } });
        $('#downloadsTable').DataTable({ responsive:true, scrollX:true, pagingType: "full_numbers", language: { search: "_INPUT_", searchPlaceholder: "Search downloadables..." } });

        // Form change detection (welcome)
        const initialFormData = $('#welcomeForm').serialize();
        let initialImageState = $('#teacherImage').val();
        let hasImageChanged = false;

        function checkForChanges() {
            const currentFormData = $('#welcomeForm').serialize();
            const currentImageState = $('#teacherImage').val();
            if (currentFormData !== initialFormData || hasImageChanged || currentImageState !== initialImageState) {
                $('#cancelChanges').show();
            } else {
                $('#cancelChanges').hide();
            }
        }

        $('#welcomeForm').on('input change', 'input, textarea, select', function() {
            $(this).addClass('changed');
            checkForChanges();
        });

        $('#teacherImage').change(function(e) {
            hasImageChanged = true;
            $(this).addClass('changed');
            checkForChanges();
        });

        $('#cancelChanges').click(function() {
            Swal.fire({
                title: 'Discard changes?',
                text: "Are you sure you want to discard all changes?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, discard changes!'
            }).then((result) => {
                if (result.isConfirmed) {
                    location.reload();
                }
            });
        });

        // Teacher image validation & preview
        $('#teacherImage').change(function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const allowedTypes = ['image/jpeg','image/jpg','image/png','image/webp'];
            const maxSize = 25 * 1024 * 1024;

            if (!allowedTypes.includes(file.type)) {
                Swal.fire({ icon: 'error', title: 'Invalid File Type', text: 'Only JPEG, JPG, PNG, and WEBP files are allowed.', confirmButtonColor: '#d33' });
                e.target.value = '';
                hasImageChanged = false;
                checkForChanges();
                return;
            }
            if (file.size > maxSize) {
                Swal.fire({ icon: 'error', title: 'File Too Large', text: 'Image size must not exceed 25MB.', confirmButtonColor: '#d33' });
                e.target.value = '';
                hasImageChanged = false;
                checkForChanges();
                return;
            }

            const reader = new FileReader();
            reader.onload = function(event) {
                const img = new Image();
                img.onload = function() {
                    if (img.width !== img.height) {
                        Swal.fire({ icon: 'error', title: 'Invalid Image Dimensions', text: 'Please upload a square image (equal width and height).', confirmButtonColor: '#d33' });
                        e.target.value = "";
                        hasImageChanged = false;
                        checkForChanges();
                    } else {
                        const preview = document.getElementById('teacherPreviewImage');
                        if (preview) {
                            preview.src = event.target.result;
                            preview.style.opacity = 0;
                            setTimeout(() => preview.style.opacity = 1, 100);
                        }
                        checkForChanges();
                    }
                };
                img.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });

        // Profile image preview
        $('#profileImageInput').change(function(event) {
            const file = event.target.files[0];
            const maxSize = 25 * 1024 * 1024;
            if (file) {
                if (file.size > maxSize) {
                    Swal.fire({ icon: 'error', title: 'File Too Large', text: 'The selected image exceeds 25MB. Please choose a smaller file.', confirmButtonColor: '#d33' });
                    event.target.value = '';
                    return;
                }
                const allowedTypes = ['image/jpeg','image/jpg','image/png','image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    Swal.fire({ icon: 'error', title: 'Invalid File Type', text: 'Only JPG, JPEG, PNG, and WEBP are allowed.', confirmButtonColor: '#d33' });
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

        // Toggle password visibility
        function togglePassword(fieldId, icon) {
            const field = document.getElementById(fieldId);
            const isHidden = field.type === 'password';
            field.type = isHidden ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !isHidden);
            icon.classList.toggle('fa-eye-slash', isHidden);
        }
        window.togglePassword = togglePassword;


       

        // Delete event/download handlers with confirm
        $(document).on('click', '.delete-event-btn', function() {
            const form = $(this).closest('form');
            Swal.fire({
                title: 'Delete Event?',
                text: "Are you sure you want to delete this event? This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.append('<input type="hidden" name="delete_event" value="1">');
                    form.submit();
                }
            });
        });

        $(document).on('click', '.delete-download-btn', function() {
            const form = $(this).closest('form');
            Swal.fire({
                title: 'Delete Downloadable?',
                text: "Are you sure you want to delete this downloadable? This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.append('<input type="hidden" name="delete_download" value="1">');
                    form.submit();
                }
            });
        });

        // Event modal setup
        $('#eventModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var action = button.data('action');
            var modal = $(this);
            if (action === 'edit') {
                modal.find('.modal-title').text('Edit Calendar Event');
                modal.find('#eventAction').val('edit');
                modal.find('#eventId').val(button.data('id'));
                modal.find('#title').val(button.data('title'));
                modal.find('#short_desc').val(button.data('short_desc'));
                modal.find('#start_date').val(button.data('start_date'));
                modal.find('#end_date').val(button.data('end_date'));
            } else {
                modal.find('.modal-title').text('Add Calendar Event');
                modal.find('#eventAction').val('add');
                modal.find('form')[0].reset();
                modal.find('#eventId').val('');
            }
        });

        // Download modal setup
        $('#downloadModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var action = button.data('action');
            var modal = $(this);
            if (action === 'edit') {
                modal.find('.modal-title').text('Edit Downloadable');
                modal.find('#downloadAction').val('edit');
                modal.find('#downloadId').val(button.data('id'));
                modal.find('#title_label').val(button.data('title_label'));
                modal.find('#file_url').val(button.data('file_url'));
                modal.find('#file_size').val(button.data('file_size'));
            } else {
                modal.find('.modal-title').text('Add Downloadable');
                modal.find('#downloadAction').val('add');
                modal.find('form')[0].reset();
                modal.find('#downloadId').val('');
            }
        });

        // Loading states for buttons on submit
        function setLoading(button, enable, text) {
            if (enable) {
                button.prop('disabled', true);
                button.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + text);
            } else {
                button.prop('disabled', false);
                button.html('<span class="btn-text">' + text + '</span>');
            }
        }

        $('#welcomeForm').on('submit', function() {
            setLoading($('#saveWelcomeBtn'), true, 'Saving...');
        });

        $('#eventForm').on('submit', function() {
            setLoading($('#saveEventBtn'), true, 'Saving...');
        });

        $('#downloadForm').on('submit', function() {
            setLoading($('#saveDownloadBtn'), true, 'Saving...');
        });



       $('#profileForm').on('submit', function() {
    $('#saveProfileBtn').prop('disabled', true);
    $('#saveProfileBtn .btn-text').hide();
    $('#saveProfileBtn').append('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
}); 
    });
    </script>

<?php include 'search/Search_Admin.php'; ?>
</body>
</html>