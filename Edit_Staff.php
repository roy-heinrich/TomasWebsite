<?php
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
// $conn is already a PDO PostgreSQL connection from config.php

$updateSuccess = false;
$deleteSuccess = false;
$restoreSuccess = false;
$addSuccess = false;

// Handle Archive Faculty
if (isset($_POST['faculty_id']) && isset($_POST['archive_faculty'])) {
    $facultyId = (int)$_POST['faculty_id'];
    try {
        $stmt = $conn->prepare("UPDATE faculty SET status = 'Archived' WHERE faculty_id = :faculty_id");
        $stmt->execute([':faculty_id' => $facultyId]);
        reindexDisplayOrder($conn);
        $_SESSION['success'] = "Faculty member archived successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error archiving faculty: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Delete in Archived Table
if (isset($_POST['delete_faculty']) && isset($_POST['faculty_id'])) {
    $facultyId = (int)$_POST['faculty_id'];
    
    try {
        // Get current image path
        $stmt = $conn->prepare("SELECT image_path FROM faculty WHERE faculty_id = :faculty_id");
        $stmt->execute([':faculty_id' => $facultyId]);
        $currentImagePath = $stmt->fetchColumn();
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM faculty WHERE faculty_id = :faculty_id");
        $stmt->execute([':faculty_id' => $facultyId]);
        
        // Delete image from Supabase
        if (!empty($currentImagePath)) {
            deleteFromSupabase($currentImagePath, 'faculty_prof');
        }
        
        $_SESSION['success'] = "Faculty member deleted successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting faculty: " . $e->getMessage();
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Restore in Archived Table
if (isset($_POST['restore_faculty']) && isset($_POST['faculty_id'])) {
    $facultyId = (int)$_POST['faculty_id'];
    
    try {
        $stmt = $conn->prepare("UPDATE faculty SET status = 'Active' WHERE faculty_id = :faculty_id");
        $stmt->execute([':faculty_id' => $facultyId]);
        $_SESSION['success'] = "Faculty member restored successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error restoring faculty: " . $e->getMessage();
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Update Faculty
if (isset($_POST['update_faculty'])) {
    $facultyId = (int)$_POST['faculty_id'];
    $fullname = $_POST['fullname'];
    $position = $_POST['position'];
    $advisory = $_POST['advisory'];
    $visible = isset($_POST['visible']) ? 'Yes' : 'No';

    try {
        // Get current image path from DB
        $stmt = $conn->prepare("SELECT image_path FROM faculty WHERE faculty_id = :faculty_id");
        $stmt->execute([':faculty_id' => $facultyId]);
        $currentImagePath = $stmt->fetchColumn();

        // File upload handling
        if (!empty($_FILES['faculty_image']['name'])) {
            $imageName = $_FILES['faculty_image']['name'];
            $tmpName = $_FILES['faculty_image']['tmp_name'];

            // Check file size
            if ($_FILES['faculty_image']['size'] > 25 * 1024 * 1024) { // 25MB
                $_SESSION['error'] = "The selected image exceeds 25MB. Please choose a smaller file.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }

            // Generate unique filename for Supabase
            $objectName = generateObjectName($imageName);
            
            // Upload to Supabase
            if (uploadToSupabase($tmpName, $objectName, 'faculty_prof')) {
                // Delete old image from Supabase if it exists
                if (!empty($currentImagePath)) {
                    deleteFromSupabase($currentImagePath, 'faculty_prof');
                }

                // Update with new image path (object name)
                $stmt = $conn->prepare("UPDATE faculty SET fullname=:fullname, position=:position, advisory=:advisory, image_path=:image_path, visible=:visible WHERE faculty_id=:faculty_id");
                $stmt->execute([
                    ':fullname' => $fullname,
                    ':position' => $position,
                    ':advisory' => $advisory,
                    ':image_path' => $objectName,
                    ':visible' => $visible,
                    ':faculty_id' => $facultyId
                ]);
            } else {
                $_SESSION['error'] = "Image upload failed!";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        } else {
            // Update without changing image
            $stmt = $conn->prepare("UPDATE faculty SET fullname=:fullname, position=:position, advisory=:advisory, visible=:visible WHERE faculty_id=:faculty_id");
            $stmt->execute([
                ':fullname' => $fullname,
                ':position' => $position,
                ':advisory' => $advisory,
                ':visible' => $visible,
                ':faculty_id' => $facultyId
            ]);
        }

        $_SESSION['success'] = "Faculty member updated successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating faculty: " . $e->getMessage();
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle move up or down
if (isset($_POST['move_action']) && isset($_POST['faculty_id'])) {
    $facultyId = (int)$_POST['faculty_id'];
    $direction = $_POST['move_action'];

    try {
        // Get current display_order
        $stmt = $conn->prepare("SELECT display_order FROM faculty WHERE faculty_id = :faculty_id");
        $stmt->execute([':faculty_id' => $facultyId]);
        $currentOrder = (int)$stmt->fetchColumn();

        if ($direction === 'up') {
            // Find the faculty with display_order just less than current
            $stmt = $conn->prepare("SELECT faculty_id, display_order FROM faculty WHERE display_order < :current_order ORDER BY display_order DESC LIMIT 1");
            $stmt->execute([':current_order' => $currentOrder]);
        } else {
            // down
            $stmt = $conn->prepare("SELECT faculty_id, display_order FROM faculty WHERE display_order > :current_order ORDER BY display_order ASC LIMIT 1");
            $stmt->execute([':current_order' => $currentOrder]);
        }

        $swapRow = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($swapRow) {
            $swapId = $swapRow['faculty_id'];
            $swapOrder = $swapRow['display_order'];

            // Swap the display_order values
            $stmt = $conn->prepare("UPDATE faculty SET display_order = :swap_order WHERE faculty_id = :faculty_id");
            $stmt->execute([':swap_order' => $swapOrder, ':faculty_id' => $facultyId]);
            
            $stmt = $conn->prepare("UPDATE faculty SET display_order = :current_order WHERE faculty_id = :swap_id");
            $stmt->execute([':current_order' => $currentOrder, ':swap_id' => $swapId]);
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error moving faculty: " . $e->getMessage();
    }
    
    reindexDisplayOrder($conn);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle Faculty Add Form Submission
if (isset($_POST['add_faculty'])) {
    $fullname = $_POST['fullname'];
    $position = $_POST['position'];
    $advisory = $_POST['advisory'];
    $visible = isset($_POST['visible']) ? 'Yes' : 'No';

    // Handle image upload
    if (empty($_FILES['faculty_image']['name'])) {
        $_SESSION['error'] = "Please upload an image for the faculty member";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $imageName = $_FILES['faculty_image']['name'];
    $tmpName = $_FILES['faculty_image']['tmp_name'];

    // Check file size
    if ($_FILES['faculty_image']['size'] > 25 * 1024 * 1024) { // 25MB
        $_SESSION['error'] = "The selected image exceeds 25MB. Please choose a smaller file.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Generate unique filename for Supabase
    $objectName = generateObjectName($imageName);
    
    // Upload to Supabase
    if (uploadToSupabase($tmpName, $objectName, 'faculty_prof')) {
        try {
            $stmt = $conn->prepare("INSERT INTO faculty (fullname, position, advisory, image_path, visible, status) VALUES (:fullname, :position, :advisory, :image_path, :visible, 'Active')");
            $stmt->execute([
                ':fullname' => $fullname,
                ':position' => $position,
                ':advisory' => $advisory,
                ':image_path' => $objectName,
                ':visible' => $visible
            ]);
            
            reindexDisplayOrder($conn);
            $_SESSION['success'] = "Faculty member added successfully!";
        } catch (PDOException $e) {
            // Delete the uploaded image if DB insert fails
            deleteFromSupabase($objectName, 'faculty_prof');
            $_SESSION['error'] = "Error adding faculty: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Image upload failed!";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

function reindexDisplayOrder($conn) {
    try {
        $stmt = $conn->query("SELECT faculty_id FROM faculty WHERE status = 'Active' ORDER BY display_order ASC, faculty_id DESC");
        $order = 1;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = $row['faculty_id'];
            $updateStmt = $conn->prepare("UPDATE faculty SET display_order = :order WHERE faculty_id = :id");
            $updateStmt->execute([':order' => $order, ':id' => $id]);
            $order++;
        }
    } catch (PDOException $e) {
        // Handle error if needed
    }
}

// Helper function to generate unique object name for Supabase
function generateObjectName($originalName) {
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    try {
        $rand = bin2hex(random_bytes(6));
    } catch (Exception $e) {
        $rand = substr(md5(uniqid('', true)), 0, 12);
    }
    return time() . '_' . $rand . ($ext ? '.' . $ext : '');
}

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

    <title>Edit Staff</title>

    <!-- Custom fonts for this template-->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    
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

    .add-faculty-btn,
    .toggle-archive-btn {
        border-radius: 0.5rem;
        min-width: 180px;
        padding: 0.6rem 1.2rem;
        font-size: 1rem;
    }

    @media (max-width: 576px) {
        .add-faculty-btn,
        .toggle-archive-btn {
            min-width: 130px;
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }
    }

    /* DataTables custom styling */
    .dataTables_wrapper .dataTables_filter {
        float: right !important;
        text-align: right !important;
    }
    
    .dataTables_wrapper .dataTables_length {
        float: left !important;
    }
    
    .dataTables_wrapper .dataTables_info {
        float: left !important;
    }
    
    .dataTables_wrapper .dataTables_paginate {
        float: right !important;
    }
    
    /* No data found styling */
    .dataTables_empty {
        text-align: center;
        padding: 20px !important;
        color: #6c757d;
        font-style: italic;
    }
    
    /* Card header styling */
    .card-header-custom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    
    /* Table header styling */
    .table thead th {
        vertical-align: middle;
    }
    
    /* Action buttons styling */
    .btn-action {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Preview modal styling */
    #previewImage {
        max-height: 300px;
        object-fit: cover;
        width: 100%;
    }
    
    /* Search bar styling */
    .dataTables_filter label {
        display: flex;
        align-items: center;
        margin-bottom: 0;
    }
    
    .dataTables_filter input {
        margin-left: 10px;
        height: 34px;
        padding: 6px 12px;
    }
    
    @media (max-width: 767.98px) {
        /* DataTables responsive adjustments */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            float: none !important;
            text-align: left !important;
            margin-bottom: 10px;
        }
        
        .dataTables_wrapper .dataTables_length select {
            width: auto;
            display: inline-block;
        }
        
        .dataTables_wrapper .dataTables_filter input {
            width: 100% !important;
            margin-left: 0 !important;
            margin-top: 5px;
        }
        
        /* Stack the controls vertically */
        .dataTables_wrapper .top {
            display: flex;
            flex-direction: column;
        }
        
        /* Adjust spacing for mobile */
        .dataTables_wrapper .top > div {
            margin-bottom: 10px;
        }
        
        /* Make sure the search label doesn't wrap awkwardly */
        .dataTables_filter label {
            display: flex;
            flex-direction: column;
        }
        
        /* Adjust table cells for mobile */
        .table td, .table th {
            padding: 0.5rem;
            font-size: 0.85rem;
        }
        
        /* Make action buttons smaller on mobile */
        .btn-action {
            width: 28px;
            height: 28px;
            font-size: 0.8rem;
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
            <li class="nav-item active"> 
                <a class="nav-link" href="#" data-toggle="collapse" data-target="#collapsePages" aria-expanded="true" aria-controls="collapsePages"> 
                    <i class="fas fa-copy"></i> 
                    <span>Pages</span>
                </a>
                <div id="collapsePages" class="collapse show" aria-labelledby="headingPages" data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded">
                        <a class="collapse-item" href="message.php">Home Page</a>
                        <a class="collapse-item" href="News_Eve.php">News & Events</a>
                        <a class="collapse-item active" href="Edit_Staff.php">Faculty & Staff</a>
                        <a class="collapse-item" href="Edit_Achieve.php">Achievements</a> 
                        <a class="collapse-item" href="chart_edit.php">Organizational Chart</a>
                        <a class="collapse-item" href="Edit_Contact.php">Contact</a>
                        <a class="collapse-item" href="Edit_Gallery.php">Gallery</a>
                        <a class="collapse-item" href="Edit_History.php">History</a> 
                    </div>
                </div>
            </li>
            
            <!-- Nav Item - User Management --> 
            <?php if ($user['id'] == 29 && $user['is_superadmin'] == 1): ?>
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
                                <img class="img-profile rounded-circle" src="<?= htmlspecialchars($profileImageUrl) ?>">
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

                <!-- Success/Error Messages -->
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

                <!-- Page Heading -->
                <div class="container mt-5">
                    <div class="d-flex align-items-center justify-content-center">
                        <i class="fas fa-user-tie" style="color: rgb(11, 104, 245); font-size: 1.4rem; margin-right: 0.8rem;"></i>
                        <h2 class="fw-bolder mb-0" style="color: rgb(11, 104, 245); font-size: 1.50rem; font-weight: 800;">Faculty & Staff</h2>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="container mt-4" style="max-width: 95%;">
                    <div class="d-flex flex-wrap justify-content-start mb-0">
                        <!-- Add Faculty Button -->
                        <button class="btn btn-primary fw-bold add-faculty-btn"
                                data-toggle="modal" data-target="#addFacultyModal">
                            <i class="fas fa-user-plus mr-2"></i>Add Faculty
                        </button>

                        <!-- Toggle Archive Button -->
                        <button id="toggleArchiveBtn" class="fw-bold toggle-archive-btn ml-2 btn" style="background-color: #17a2b8; color: white; border: none;">
                            <i class="fas fa-archive mr-2"></i> Show Archive
                        </button>
                    </div>
                </div>

             <!-- Active Faculty Table -->
                <section id="activeFacultySection" class="active-faculty-list py-4">
                    <div class="container" style="max-width: 95%;">
                        <div class="card shadow" style="border-top: 8px solid #4169E1; border-radius: 1rem;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                                    <h3 class="text-start mb-2" style="color: #4169E1; font-weight: 900;">Faculty & Staff</h3>
                                </div>

                                <!-- Active Table -->
                                <div class="table-responsive">
                                    <table id="activeFacultyTable" class="table table-bordered table-hover align-middle text-center" style="width:100%">
                                        <thead style="background-color: #4169E1; color: white;">
                                            <tr>
                                                <th>Photo</th>
                                                <th>Fullname</th>
                                                <th>Position</th>
                                                <th>Visible</th>
                                                <th>Advisory</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            try {
                                                $stmt = $conn->query("SELECT * FROM faculty WHERE status = 'Active' ORDER BY display_order ASC, faculty_id DESC");
                                                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                
                                                if (count($result) > 0) {
                                                    foreach ($result as $row) {
                                                        $facultyId = $row['faculty_id'];
                                                        $photo = getSupabaseUrl($row['image_path'], 'faculty_prof');
                                                        $fullname = $row['fullname'];
                                                        $position = $row['position'];
                                                        $advisory = $row['advisory'];
                                                        $visible = $row['visible'];
                                                        $visibilityBadge = $visible === 'Yes' ? "<span class='badge bg-success text-white'>Yes</span>" : "<span class='badge bg-danger text-white'>No</span>";
                                                        ?>
                                                        <tr>
                                                            <td><img src="<?= htmlspecialchars($photo) ?>" alt="Profile" width="50" height="40" style="object-fit: cover;"></td>
                                                            <td><?= htmlspecialchars($fullname) ?></td>
                                                            <td><?= htmlspecialchars($position) ?></td>
                                                            <td><?= $visibilityBadge ?></td>
                                                            <td><?= htmlspecialchars($advisory) ?></td>
                                                            <td>
                                                                
                                                            <!-- Move Up -->
                                                   <form method="POST" class="d-inline move-up-form">
                                                   <input type="hidden" name="faculty_id" value="<?= $facultyId ?>">
                                                   <input type="hidden" name="move_action" value="up">
                                                   <button type="submit" class="btn btn-sm btn-secondary me-1 move-up-btn" title="Move Up">
                                                   <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                                   <i class="fas fa-arrow-up"></i>
                                                  </button>
                                                   </form>

                                                 <!-- Move Down -->
                                                <form method="POST" class="d-inline move-down-form">
                                                <input type="hidden" name="faculty_id" value="<?= $facultyId ?>">
                                                <input type="hidden" name="move_action" value="down">
                                                <button type="submit" class="btn btn-sm btn-secondary me-1 move-down-btn" title="Move Down">
                                                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                               <i class="fas fa-arrow-down"></i>
                                                </button>
                                                 </form>

                                                                <button class="btn btn-sm btn-success me-1 preview-btn" 
                                                                    data-image="<?= htmlspecialchars($photo) ?>" 
                                                                    data-name="<?= htmlspecialchars($fullname) ?>" 
                                                                    data-position="<?= htmlspecialchars($position) ?>" 
                                                                    data-advisory="<?= htmlspecialchars($advisory) ?>">
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-primary me-1 edit-btn" 
                                                                    data-id="<?= $facultyId ?>" 
                                                                    data-name="<?= htmlspecialchars($fullname) ?>" 
                                                                    data-position="<?= htmlspecialchars($position) ?>" 
                                                                    data-advisory="<?= htmlspecialchars($advisory) ?>" 
                                                                    data-visible="<?= $visible ?>" 
                                                                    data-image="<?= htmlspecialchars($photo) ?>">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-warning archive-btn" data-id="<?= $facultyId ?>">
                                                                    <i class="fas fa-archive"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                    }
                                                }
                                            } catch (PDOException $e) {
                                                echo "<tr><td colspan='6'>Error fetching faculty: " . $e->getMessage() . "</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Archive Faculty Table -->
                <section id="archiveFacultySection" class="archive-faculty-list py-4" style="display: none;">
                    <div class="container" style="max-width: 95%;">
                        <div class="card shadow" style="border-top: 8px solid #17a2b8; border-radius: 1rem;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                                    <h3 class="text-start mb-2" style="color: #17a2b8; font-weight: 900;">Archived Faculty & Staff</h3>
                                </div>

                                <!-- Archive Table -->
                                <div class="table-responsive">
                                    <table id="archivedFacultyTable" class="table table-bordered table-hover align-middle text-center" style="width:100%">
                                        <thead style="background-color: #17a2b8; color: white;">
                                            <tr>
                                                <th>Photo</th>
                                                <th>Fullname</th>
                                                <th>Position</th>
                                                <th>Advisory</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            try {
                                                $stmt = $conn->query("SELECT * FROM faculty WHERE status = 'Archived' ORDER BY display_order ASC, faculty_id DESC");
                                                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                
                                                if (count($result) > 0) {
                                                    foreach ($result as $row) {
                                                        $facultyId = $row['faculty_id'];
                                                        $photo = getSupabaseUrl($row['image_path'], 'faculty_prof');
                                                        $fullname = htmlspecialchars($row['fullname']);
                                                        $position = htmlspecialchars($row['position']);
                                                        $advisory = htmlspecialchars($row['advisory']);
                                                        ?>
                                                        <tr>
                                                            <td><img src="<?= $photo ?>" width="50" height="40" style="object-fit:cover;"></td>
                                                            <td><?= $fullname ?></td>
                                                            <td><?= $position ?></td>
                                                            <td><?= $advisory ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-success me-1 preview-btn" 
                                                                    data-image="<?= $photo ?>" 
                                                                    data-name="<?= $fullname ?>" 
                                                                    data-position="<?= $position ?>" 
                                                                    data-advisory="<?= $advisory ?>">
                                                                    <i class="fas fa-eye"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-primary me-1 restore-btn" data-id="<?= $facultyId ?>">
                                                                    <i class="fas fa-undo"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-danger delete-btn" data-id="<?= $facultyId ?>">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                    }
                                                }
                                            } catch (PDOException $e) {
                                                echo "<tr><td colspan='5'>Error fetching archived faculty: " . $e->getMessage() . "</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
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
            <!-- End of Content Wrapper -->
        </div>
        <!-- End of Page Wrapper -->

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

        <!-- Add Faculty Modal -->
        <div class="modal fade" id="addFacultyModal" tabindex="-1" role="dialog" aria-labelledby="addFacultyModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header" style="background-color:rgba(42, 8, 213, 0.77);">
                        <h5 class="modal-title text-white" id="addFacultyModalLabel">Add New Faculty</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <form id="addFacultyForm" action="" method="POST" enctype="multipart/form-data" class="modal-form-fix">
                            <!-- Full Name -->
                            <div class="form-group">
                                <label>Fullname</label>
                                <input type="text" name="fullname" class="form-control" placeholder="Enter full name" required>
                            </div>

                            <!-- Position & Advisory -->
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Position</label>
                                    <input type="text" name="position" class="form-control" placeholder="e.g., Head Teacher - I" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Advisory (Optional)</label>
                                    <input type="text" name="advisory" class="form-control" placeholder="e.g., Grade 1 - John">
                                </div>
                            </div>

                            <!-- Upload Image & Visibility in Same Row -->
                            <div class="form-row align-items-center">
                                <!-- Image Upload + Preview -->
                                <div class="form-group col-md-6">
                                    <label>Upload Image</label>
                                    <div class="d-flex align-items-center">
                                        <img id="imagePreview" src="https://via.placeholder.com/100x100?text=Preview" alt="Preview" class="mr-3 border rounded" style="width: 100px; height: 100px; object-fit: cover;">
                                        <div>
                                            <input type="file" name="faculty_image" class="form-control-file" accept=".jpg, .jpeg, .png, .webp" onchange="previewImage(event)" required>
                                            <small class="form-text text-muted">Only JPG, PNG, JPEG, WEBP files allowed.</small>
                                            <small class="form-text text-muted">
                                                <strong>Tip:</strong> For best appearance, upload an image with a slightly wider than tall aspect ratio (e.g., 4:3).
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Visibility Toggle -->
                                <div class="form-group col-md-6">
                                    <label>Visibility</label>
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="facultyVisibility" name="visible" checked>
                                        <label class="custom-control-label" for="facultyVisibility">Visible to public</label>
                                    </div>
                                    <small class="form-text text-muted">When disabled, this won't be visible on the website</small>
                                </div>
                            </div>

                            <!-- Hidden input for form identification -->
                            <input type="hidden" name="add_faculty" value="1">

                            <!-- Footer -->
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary" id="saveFacultyBtn">
                                    <span class="btn-text">Save Faculty</span>
                                    <span class="btn-loading d-none">
                                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                        Saving...
                                    </span>
                                </button>
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Faculty Modal -->
        <div class="modal fade" id="editFacultyModal" tabindex="-1" role="dialog" aria-labelledby="editFacultyModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header" style="background-color:rgba(42, 8, 213, 0.77);">
                        <h5 class="modal-title text-white" id="editFacultyModalLabel">Edit Faculty</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <form id="editFacultyForm" action="" method="POST" enctype="multipart/form-data" class="modal-form-fix">
                            <!-- Hidden ID -->
                            <input type="hidden" name="faculty_id" id="editFacultyId">
                            <input type="hidden" name="update_faculty" value="1">

                            <!-- Full Name -->
                            <div class="form-group">
                                <label>Fullname</label>
                                <input type="text" name="fullname" id="editFullname" class="form-control" required>
                            </div>

                            <!-- Position & Advisory -->
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Position</label>
                                    <input type="text" name="position" id="editPosition" class="form-control" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Advisory</label>
                                    <input type="text" name="advisory" id="editAdvisory" class="form-control">
                                </div>
                            </div>

                            <!-- Image & Visibility -->
                            <div class="form-row align-items-center">
                                <div class="form-group col-md-6">
                                    <label>Upload Image</label>
                                    <div class="d-flex align-items-center">
                                        <img id="editImagePreview" src="https://via.placeholder.com/100x100?text=Preview" class="mr-3 border rounded" style="width: 100px; height: 100px; object-fit: cover;">
                                        <div>
                                            <input type="file" name="faculty_image" class="form-control-file" accept=".jpg, .jpeg, .png, .webp" onchange="previewEditImage(event)">
                                            <small class="form-text text-muted">Only JPG, PNG, JPEG, WEBP files allowed.</small>
                                            <small class="form-text text-muted"><strong>Tip:</strong> Use a 4:3 image ratio for best display.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group col-md-6">
                                    <label>Visibility</label>
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="editFacultyVisibility" name="visible">
                                        <label class="custom-control-label" for="editFacultyVisibility">Visible to public</label>
                                    </div>
                                    <small class="form-text text-muted">When disabled, this won't be visible on the website</small>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary" id="saveChangesBtn">
                                    <span class="btn-text">Save Changes</span>
                                    <span class="btn-loading d-none">
                                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                        Saving...
                                    </span>
                                </button>
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Faculty Preview Modal -->
        <div class="modal fade" id="facultyPreviewModal" tabindex="-1" role="dialog" aria-labelledby="facultyPreviewLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 400px;">
                <div class="modal-content border-0 bg-transparent">
                    <div class="modal-body p-0">
                        <div class="card text-center border-0 shadow-lg h-100" style="border-radius: 20px; overflow: hidden;">
                            <img id="previewImage" src="images/placeholder.jpg" class="card-img-top" alt="Teacher" style="height: 300px; object-fit: cover;">
                            <div class="card-body bg-light">
                                <h5 class="card-title font-weight-bold text-dark" id="previewName" style="color: #212529;">[Full Name]</h5>
                                <p class="mb-1" id="previewPosition" style="color: #495057;">[Position]</p>
                                <p class="mb-0" id="previewAdvisory" style="color: #495057;">[Advisory]</p>
                            </div>
                        </div>
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
        <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
        
       <script>
            $(document).ready(function() {
                // Initialize DataTables with no sorting
                $('#activeFacultyTable').DataTable({
                    "dom": '<"top"<"d-flex flex-column flex-md-row justify-content-between"<"mb-2 mb-md-0"l><"mb-2 mb-md-0"f>>>rt<"bottom"ip>',
                    "responsive": true,
                    "language": {
                        "emptyTable": "No active faculty members found. Add some faculty to get started!",
                        "search": "Search:",
                        "searchPlaceholder": "Search active faculty...",
                        "lengthMenu": "Show _MENU_ entries",
                        "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                        "paginate": {
                            "first": "First",
                            "last": "Last",
                            "next": "Next",
                            "previous": "Previous"
                        }
                    },
                    "columnDefs": [
                        { 
                            "orderable": false, 
                            "targets": '_all',
                            "className": "no-sort"
                        }
                    ],
                    "lengthMenu": [5, 10, 25, 50],
                    "pageLength": 10,
                    "initComplete": function() {
                        $('.dataTables_filter input').addClass('form-control form-control-sm');
                        $('.dataTables_length select').addClass('form-control form-control-sm');
                        $('.no-sort').removeClass('sorting sorting_asc sorting_desc');
                    }
                });

                $('#archivedFacultyTable').DataTable({
                    "dom": '<"top"<"d-flex flex-column flex-md-row justify-content-between"<"mb-2 mb-md-0"l><"mb-2 mb-md-0"f>>>rt<"bottom"ip>',
                    "responsive": true,
                    "language": {
                        "emptyTable": "No archived faculty members found. Archive some faculty to see them here!",
                        "search": "Search:",
                        "searchPlaceholder": "Search archived faculty...",
                        "lengthMenu": "Show _MENU_ entries",
                        "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                        "paginate": {
                            "first": "First",
                            "last": "Last",
                            "next": "Next",
                            "previous": "Previous"
                        }
                    },
                    "columnDefs": [
                        { 
                            "orderable": false, 
                            "targets": '_all',
                            "className": "no-sort"
                        }
                    ],
                    "lengthMenu": [5, 10, 25, 50],
                    "pageLength": 10,
                    "initComplete": function() {
                        $('.dataTables_filter input').addClass('form-control form-control-sm');
                        $('.dataTables_length select').addClass('form-control form-control-sm');
                        $('.no-sort').removeClass('sorting sorting_asc sorting_desc');
                    }
                });
                
                // Toggle archive section
                $('#toggleArchiveBtn').click(function() {
                    const activeSection = $('#activeFacultySection');
                    const archiveSection = $('#archiveFacultySection');
                    const toggleBtn = $('#toggleArchiveBtn');
                    
                    if (archiveSection.is(':hidden')) {
                        archiveSection.show();
                        activeSection.hide();
                        toggleBtn.html('<i class="fas fa-user-tie mr-2"></i> Show Active');
                        toggleBtn.removeClass('btn-info').addClass('btn-primary');
                        toggleBtn.css('background-color', '#4169E1');
                    } else {
                        archiveSection.hide();
                        activeSection.show();
                        toggleBtn.html('<i class="fas fa-archive mr-2"></i> Show Archive');
                        toggleBtn.removeClass('btn-primary');
                        toggleBtn.css('background-color', '#17a2b8');
                    }
                });
                
                // Preview faculty
                $(document).on('click', '.preview-btn', function() {
                    const image = $(this).data('image');
                    const name = $(this).data('name');
                    const position = $(this).data('position');
                    const advisory = $(this).data('advisory');
                    
                    $('#previewImage').attr('src', image);
                    $('#previewName').text(name);
                    $('#previewPosition').text(position);
                    $('#previewAdvisory').text(advisory);
                    
                    $('#facultyPreviewModal').modal('show');
                });
                
                // Edit faculty modal
                $(document).on('click', '.edit-btn', function() {
                    const id = $(this).data('id');
                    const name = $(this).data('name');
                    const position = $(this).data('position');
                    const advisory = $(this).data('advisory');
                    const visible = $(this).data('visible');
                    const image = $(this).data('image');
                    
                    $('#editFacultyId').val(id);
                    $('#editFullname').val(name);
                    $('#editPosition').val(position);
                    $('#editAdvisory').val(advisory);
                    $('#editFacultyVisibility').prop('checked', (visible === 'Yes'));
                    $('#editImagePreview').attr('src', image);
                    
                    $('#editFacultyModal').modal('show');
                });
                
                // Archive faculty with SweetAlert
                $(document).on('click', '.archive-btn', function() {
                    const facultyId = $(this).data('id');
                    
                    Swal.fire({
                        title: 'Archive Faculty Member?',
                        text: "Are you sure you want to archive this faculty member?",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#ffc107',
                        confirmButtonText: 'Yes, archive it!',
                        cancelButtonText: 'Cancel',
                        cancelButtonColor: '#6c757d'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Create a form and submit it
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '';
                            
                            const inputId = document.createElement('input');
                            inputId.type = 'hidden';
                            inputId.name = 'faculty_id';
                            inputId.value = facultyId;
                            
                            const inputAction = document.createElement('input');
                            inputAction.type = 'hidden';
                            inputAction.name = 'archive_faculty';
                            inputAction.value = '1';
                            
                            form.appendChild(inputId);
                            form.appendChild(inputAction);
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                });
                
                // Restore faculty with SweetAlert
                $(document).on('click', '.restore-btn', function() {
                    const facultyId = $(this).data('id');
                    
                    Swal.fire({
                        title: 'Restore Faculty Member?',
                        text: "Are you sure you want to restore this faculty member?",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#28a745',
                        confirmButtonText: 'Yes, restore it!',
                        cancelButtonText: 'Cancel',
                        cancelButtonColor: '#6c757d'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Create a form and submit it
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '';
                            
                            const inputId = document.createElement('input');
                            inputId.type = 'hidden';
                            inputId.name = 'faculty_id';
                            inputId.value = facultyId;
                            
                            const inputAction = document.createElement('input');
                            inputAction.type = 'hidden';
                            inputAction.name = 'restore_faculty';
                            inputAction.value = '1';
                            
                            form.appendChild(inputId);
                            form.appendChild(inputAction);
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                });
                
                // Delete faculty with SweetAlert
                $(document).on('click', '.delete-btn', function() {
                    const facultyId = $(this).data('id');
                    
                    Swal.fire({
                        title: 'Delete Faculty Member?',
                        text: "Are you sure you want to permanently delete this faculty member? This cannot be undone.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        confirmButtonText: 'Yes, delete it!',
                        cancelButtonText: 'Cancel',
                        cancelButtonColor: '#6c757d'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Create a form and submit it
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '';
                            
                            const inputId = document.createElement('input');
                            inputId.type = 'hidden';
                            inputId.name = 'faculty_id';
                            inputId.value = facultyId;
                            
                            const inputAction = document.createElement('input');
                            inputAction.type = 'hidden';
                            inputAction.name = 'delete_faculty';
                            inputAction.value = '1';
                            
                            form.appendChild(inputId);
                            form.appendChild(inputAction);
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                });
                
                // Image preview and validation for add faculty modal
                function previewImage(event) {
                    const input = event.target;
                    const reader = new FileReader();
                    
                    reader.onload = function() {
                        const preview = document.getElementById('imagePreview');
                        preview.src = reader.result;
                    };
                    
                    if (input.files && input.files[0]) {
                        // Check file size (25MB limit)
                        if (input.files[0].size > 25 * 1024 * 1024) {
                            Swal.fire({
                                icon: 'error',
                                title: 'File too large',
                                text: 'The selected image exceeds 25MB. Please choose a smaller file.',
                                confirmButtonColor: '#3085d6',
                            });
                            input.value = '';
                            return;
                        }
                        
                        // Check file type
                        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                        if (!validTypes.includes(input.files[0].type)) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Invalid file type',
                                text: 'Only JPG, JPEG, PNG, and WEBP files are allowed.',
                                confirmButtonColor: '#3085d6',
                            });
                            input.value = '';
                            return;
                        }
                        
                        reader.readAsDataURL(input.files[0]);
                    }
                }
                
                // Image preview and validation for edit faculty modal
                function previewEditImage(event) {
                    const input = event.target;
                    const reader = new FileReader();
                    
                    reader.onload = function() {
                        const preview = document.getElementById('editImagePreview');
                        preview.src = reader.result;
                    };
                    
                    if (input.files && input.files[0]) {
                        // Check file size (25MB limit)
                        if (input.files[0].size > 25 * 1024 * 1024) {
                            Swal.fire({
                                icon: 'error',
                                title: 'File too large',
                                text: 'The selected image exceeds 25MB. Please choose a smaller file.',
                                confirmButtonColor: '#3085d6',
                            });
                            input.value = '';
                            return;
                        }
                        
                        // Check file type
                        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                        if (!validTypes.includes(input.files[0].type)) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Invalid file type',
                                text: 'Only JPG, JPEG, PNG, and WEBP files are allowed.',
                                confirmButtonColor: '#3085d6',
                            });
                            input.value = '';
                            return;
                        }
                        
                        reader.readAsDataURL(input.files[0]);
                    }
                }


                 // Move Up/Down loading state
    $(document).on('submit', 'form.move-up-form, form.move-down-form', function(e) {
        // Disable all move buttons and show spinner
        $('.move-up-btn, .move-down-btn').prop('disabled', true);
        $('.move-up-btn .fa-arrow-up, .move-down-btn .fa-arrow-down').addClass('d-none');
        $('.move-up-btn .spinner-border, .move-down-btn .spinner-border').removeClass('d-none');
    });

                
                // Reset add faculty modal on close
                $('#addFacultyModal').on('hidden.bs.modal', function() {
                    $(this).find('form')[0].reset();
                    $('#imagePreview').attr('src', 'https://via.placeholder.com/100x100?text=Preview');
                });
                
                // Reset edit faculty modal on close
                $('#editFacultyModal').on('hidden.bs.modal', function() {
                    $(this).find('form')[0].reset();
                });
                
                // Profile image preview
                $('#profileImageInput').change(function(event) {
                    const file = event.target.files[0];
                    const maxSize = 25 * 1024 * 1024; // 25MB

                    if (file) {
                        if (file.size > maxSize) {
                            Swal.fire({
                                icon: 'error',
                                title: 'File too large',
                                text: 'The selected image exceeds 25MB. Please choose a smaller file.',
                                confirmButtonColor: '#3085d6',
                            });
                            event.target.value = ''; // Clear the input
                            return;
                        }

                        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                        if (!allowedTypes.includes(file.type)) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Invalid file type',
                                text: 'Only JPG, JPEG, PNG, and WEBP are allowed.',
                                confirmButtonColor: '#3085d6',
                            });
                            event.target.value = '';
                            return;
                        }

                        const reader = new FileReader();
                        reader.onload = function(e) {
                            $('#previewImagee').attr('src', e.target.result);
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
                    $(icon).toggleClass('fa-eye', !isHidden);
                    $(icon).toggleClass('fa-eye-slash', isHidden);
                }

                // Add event listeners for image inputs
                document.querySelector('input[name="faculty_image"]').addEventListener('change', previewImage);
                document.querySelector('#editFacultyModal input[name="faculty_image"]').addEventListener('change', previewEditImage);
                
                // Loading state for add faculty form
                $('#addFacultyForm').submit(function() {
                    $('#saveFacultyBtn').prop('disabled', true);
                    $('#saveFacultyBtn .btn-text').addClass('d-none');
                    $('#saveFacultyBtn .btn-loading').removeClass('d-none');
                    return true;
                });
                
                // Loading state for edit faculty form
                $('#editFacultyForm').submit(function() {
                    $('#saveChangesBtn').prop('disabled', true);
                    $('#saveChangesBtn .btn-text').addClass('d-none');
                    $('#saveChangesBtn .btn-loading').removeClass('d-none');
                    return true;
                });
                
                // Fix for form submission issues - ensure forms are properly submitted
                $(document).on('submit', '#addFacultyForm, #editFacultyForm', function(e) {
                    // Let the form submit normally - the fixes are in the PHP code
                });



                 // Toggle show/hide for password fields
    window.togglePassword = function(fieldId, icon) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        const isHidden = field.type === 'password';
        field.type = isHidden ? 'text' : 'password';
        $(icon).toggleClass('fa-eye', !isHidden);
        $(icon).toggleClass('fa-eye-slash', isHidden);
    };


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