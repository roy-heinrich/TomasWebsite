<?php
require_once 'session.php';

// Handle logout request
if (isset($_POST['logout'])) {
    $_SESSION = array();
    session_destroy();
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['user']['user_role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

require_once 'config.php'; // config.php provides PDO $conn and Supabase helper functions

// Helper to generate a unique filename
function generateObjectName($originalName) {
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    try {
        $rand = bin2hex(random_bytes(6));
    } catch (Exception $e) {
        $rand = substr(md5(uniqid('', true)), 0, 12);
    }
    return time() . '_' . $rand . ($ext ? '.' . $ext : '');
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add News
    if (isset($_POST['news_title'])) {
        $title = trim($_POST['news_title']);
        $category = trim($_POST['category']);
        $event_date = trim($_POST['news_date']);
        $short_info = trim($_POST['short_info']);
        $full_desc = trim($_POST['full_content']);
        $visibility = isset($_POST['visible']) ? 'Yes' : 'No';

        // Handle image upload to Supabase bucket 'news_pic'
        $image_name = '';
        if (isset($_FILES['news_image']) && $_FILES['news_image']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['news_image'];

            if ($file['size'] > 25 * 1024 * 1024) {
                $_SESSION['error'] = "File size exceeds 25MB limit. Please choose a smaller file.";
                header("Location: News_Eve.php");
                exit;
            }

            // Validate mime types optionally
            $allowed = ['image/jpeg','image/jpg','image/png','image/webp'];
            if (!in_array($file['type'], $allowed)) {
                $_SESSION['error'] = "Invalid file type. Only JPG, PNG, JPEG, WEBP are allowed.";
                header("Location: News_Eve.php");
                exit;
            }

            $objectName = generateObjectName($file['name']);
            $uploaded = uploadToSupabase($file['tmp_name'], $objectName, 'news_pic');

            if ($uploaded) {
                $image_name = $objectName;
            } else {
                $_SESSION['error'] = "Error uploading image to storage.";
                header("Location: News_Eve.php");
                exit;
            }
        } else {
            // If no file uploaded, keep an empty string (DB column is NOT NULL; empty string used)
            $image_name = '';
        }

        // Insert into PostgreSQL via PDO
        try {
            $stmt = $conn->prepare("INSERT INTO news_tbl (title, category, event_date, image, short_info, full_desc, visibility, status, created_at)
                                    VALUES (:title, :category, :event_date, :image, :short_info, :full_desc, :visibility, 'Active', NOW())");
            $stmt->execute([
                ':title' => $title,
                ':category' => $category,
                ':event_date' => $event_date,
                ':image' => $image_name,
                ':short_info' => $short_info,
                ':full_desc' => $full_desc,
                ':visibility' => $visibility
            ]);
            $_SESSION['success'] = "News added successfully!";
        } catch (PDOException $e) {
            // If image was uploaded but DB failed, try to delete the uploaded object to avoid orphaned files
            if (!empty($image_name)) {
                deleteFromSupabase($image_name, 'news_pic');
            }
            $_SESSION['error'] = "Error adding news: " . $e->getMessage();
        }

        header("Location: News_Eve.php");
        exit;
    }

    // Edit News
    if (isset($_POST['edit_news_title'])) {
        $id = (int)$_POST['edit_id'];
        $title = trim($_POST['edit_news_title']);
        $category = trim($_POST['edit_category']);
        $event_date = trim($_POST['edit_news_date']);
        $short_info = trim($_POST['edit_short_info']);
        $full_desc = trim($_POST['edit_full_content']);
        $visibility = isset($_POST['edit_visible']) ? 'Yes' : 'No';
        $image_removed = isset($_POST['image_removed']) && $_POST['image_removed'] == '1';

        // Get current image
        $current_image = '';
        $stmt = $conn->prepare("SELECT image FROM news_tbl WHERE news_id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row) {
            $current_image = $row['image'];
        }

        $image_name = $current_image;

        // Handle new image upload
        if (isset($_FILES['edit_news_image']) && $_FILES['edit_news_image']['error'] == UPLOAD_ERR_OK) {
            $file = $_FILES['edit_news_image'];

            if ($file['size'] > 25 * 1024 * 1024) {
                $_SESSION['error'] = "File size exceeds 25MB limit. Please choose a smaller file.";
                header("Location: News_Eve.php");
                exit;
            }

            $allowed = ['image/jpeg','image/jpg','image/png','image/webp'];
            if (!in_array($file['type'], $allowed)) {
                $_SESSION['error'] = "Invalid file type. Only JPG, PNG, JPEG, WEBP are allowed.";
                header("Location: News_Eve.php");
                exit;
            }

            $objectName = generateObjectName($file['name']);
            $uploaded = uploadToSupabase($file['tmp_name'], $objectName, 'news_pic');

            if ($uploaded) {
                // delete old image if exists
                if (!empty($current_image)) {
                    deleteFromSupabase($current_image, 'news_pic');
                }
                $image_name = $objectName;
            } else {
                $_SESSION['error'] = "Error uploading new image to storage.";
                header("Location: News_Eve.php");
                exit;
            }
        } elseif ($image_removed) {
            // delete old image if exists
            if (!empty($current_image)) {
                deleteFromSupabase($current_image, 'news_pic');
            }
            $image_name = '';
        }

        // Update database
        try {
            $stmt = $conn->prepare("UPDATE news_tbl SET 
                                        title = :title, 
                                        category = :category, 
                                        event_date = :event_date, 
                                        image = :image, 
                                        short_info = :short_info, 
                                        full_desc = :full_desc, 
                                        visibility = :visibility
                                    WHERE news_id = :id");
            $stmt->execute([
                ':title' => $title,
                ':category' => $category,
                ':event_date' => $event_date,
                ':image' => $image_name,
                ':short_info' => $short_info,
                ':full_desc' => $full_desc,
                ':visibility' => $visibility,
                ':id' => $id
            ]);
            $_SESSION['success'] = "News updated successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating news: " . $e->getMessage();
        }

        header("Location: News_Eve.php");
        exit;
    }

    // Archive News
    if (isset($_POST['archive_id'])) {
        $id = (int)$_POST['archive_id'];
        try {
            $stmt = $conn->prepare("UPDATE news_tbl SET status = 'Archived' WHERE news_id = :id");
            $stmt->execute([':id' => $id]);
            $_SESSION['success'] = "News archived successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error archiving news: " . $e->getMessage();
        }
        header("Location: News_Eve.php");
        exit;
    }

    // Delete News
    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];

        // Get image to delete
        try {
            $stmt = $conn->prepare("SELECT image FROM news_tbl WHERE news_id = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if ($row) {
                $image = $row['image'];
                if (!empty($image)) {
                    deleteFromSupabase($image, 'news_pic');
                }
            }

            $stmt = $conn->prepare("DELETE FROM news_tbl WHERE news_id = :id");
            $stmt->execute([':id' => $id]);
            $_SESSION['success'] = "News deleted successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error deleting news: " . $e->getMessage();
        }

        header("Location: News_Eve.php");
        exit;
    }

    // Restore News
    if (isset($_POST['restore_id'])) {
        $id = (int)$_POST['restore_id'];
        try {
            $stmt = $conn->prepare("UPDATE news_tbl SET status = 'Active' WHERE news_id = :id");
            $stmt->execute([':id' => $id]);
            $_SESSION['success'] = "News restored successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error restoring news: " . $e->getMessage();
        }
        header("Location: News_Eve.php");
        exit;
    }
}

// Fetch active news
$active_news = [];
try {
    $stmt = $conn->prepare("SELECT * FROM news_tbl WHERE status = 'Active' ORDER BY created_at DESC");
    $stmt->execute();
    $active_news = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching active news: " . $e->getMessage();
}

// Fetch archived news
$archived_news = [];
try {
    $stmt = $conn->prepare("SELECT * FROM news_tbl WHERE status = 'Archived' ORDER BY created_at DESC");
    $stmt->execute();
    $archived_news = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching archived news: " . $e->getMessage();
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

    <title>News & Event Management</title>

    <!-- Custom fonts for this template-->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap4.min.css">
    
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

    .add-news-btn,
    .toggle-archive-btn {
        border-radius: 0.5rem;
        min-width: 180px;
        padding: 0.6rem 1.2rem;
        font-size: 1rem;
    }

    @media (max-width: 576px) {
        .add-news-btn,
        .toggle-archive-btn {
            min-width: 130px;
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }
    }

    .image-preview-container {
        position: relative;
        display: inline-block;
    }

    .remove-image {
        position: absolute;
        top: -10px;
        right: -10px;
        background: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        z-index: 10;
    }

    .preview-image {
        max-width: 100px;
        max-height: 100px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .status-badge {
        padding: 5px 10px;
        border-radius: 4px;
        font-weight: 500;
    }

    .status-archived {
        background-color: #ffc107;
        color: black;
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
    
    #previewCategoryBadge {
        font-size: 0.8rem;
        padding: 5px 10px;
    }
    
    /* Search bar styling */
    .dataTables_filter label {
        display: flex;
        align-items: center;
        margin-bottom: 0;
    }
    
    .dataTables_filter input {
        margin-left: 10px;
        height: 34px; /* Increased height */
        padding: 6px 12px;
    }
    
   /* Add this to your existing CSS */
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
#previewShortInfo, #previewFullContent {
    white-space: pre-line; /* Preserve line breaks */
    line-height: 1.5; /* Better readability */
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
                        <a class="collapse-item active" href="News_Eve.php">News & Events</a>
                        <a class="collapse-item" href="Edit_Staff.php">Faculty & Staff</a>
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
                        <i class="fas fa-calendar-week" style="color: rgb(11, 104, 245); font-size: 1.4rem; margin-right: 0.8rem;"></i>
                        <h2 class="fw-bolder mb-0" style="color: rgb(11, 104, 245); font-size: 1.50rem; font-weight: 800;">News Management</h2>
                    </div>
                </div>

                <!-- Add News Modal -->
                 <div class="modal fade" id="addNewsModal" tabindex="-1" role="dialog" aria-labelledby="addNewsModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="addNewsModalLabel">Add News</h5>
                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <form id="newsForm" method="POST" enctype="multipart/form-data">
                                <div class="modal-body">
                                    <div class="row g-3">
                                        <!-- News Title -->
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">News Title</label>
                                            <input type="text" name="news_title" class="form-control" required>
                                        </div>

                                        <!-- Category Dropdown -->
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">Category</label>
                                            <select name="category" class="form-control" required>
                                                <option value="" disabled selected>Select Category</option>
                                                <option value="School Event">School Event</option>
                                                <option value="Announcement">Announcement</option>
                                                <option value="Academic">Academic</option>
                                                <option value="Sports">Sports</option>
                                                <option value="Community">Community</option>
                                            </select>
                                        </div>

                                        <!-- News Date -->
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">News Date</label>
                                            <input type="date" name="news_date" class="form-control" required>
                                        </div>

                                        <!-- Image Upload with Preview -->
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">Upload Image</label>
                                            <div class="d-flex align-items-start">
                                                <div class="image-preview-container">
                                                    <img id="imagePreview" src="https://via.placeholder.com/100x100?text=Preview" class="mr-3 border preview-image" alt="Image Preview">
                                                    <div class="remove-image" id="removeImageBtn" style="display: none;">
                                                        <i class="fas fa-times text-danger"></i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <input type="file" name="news_image" id="newsImage" class="form-control-file" accept=".jpg,.jpeg,.png,.webp" required>
                                                    <small class="text-muted">JPG,PNG,JPEG,webp files allowed. (Max 25MB)</small>                                                          
                                                    <small class="text-muted">Tip: Use a 4:3 image ratio for best display.</small>       
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Short Info -->
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label fw-semibold">Short Summary</label>                                         
                                            <textarea name="short_info" id="shortInfo" class="form-control" rows="2" maxlength="1000" required></textarea>
                                            <small class="text-muted">Tip: Don't make this section too long.</small>
                                        </div>

                                        <!-- Full Content -->
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label fw-semibold">Full Content</label>
                                            <textarea name="full_content" class="form-control" rows="4" required></textarea>
                                        </div>

                                        <!-- Visibility Toggle -->
                                        <div class="form-group col-md-6">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="facultyVisibility" name="visible" checked>
                                                <label class="custom-control-label" for="facultyVisibility">Visible to public</label>
                                            </div>
                                            <small class="form-text text-muted">When disabled, this won't be visible on the website</small>
                                        </div>
                                    </div>
                                </div>
                               <div class="modal-footer">
                                    <button type="submit" id="addNewsSubmit" class="btn btn-primary fw-bold">
                                        <span id="addNewsSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                        <span id="addNewsText">Add News</span>
                                    </button>
                                    <button type="button" class="btn btn-secondary fw-bold" data-dismiss="modal" id="cancelBtn">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="container mt-4" style="max-width: 95%;">
                    <div class="d-flex flex-wrap justify-content-start mb-2"> 
                        <!-- Add News Button -->
                        <button class="btn btn-primary fw-bold add-news-btn" data-toggle="modal" data-target="#addNewsModal">
                            <i class="fas fa-plus-circle mr-2"></i>Add News
                        </button>

                        <!-- Toggle Archive Button -->
                        <button id="toggleArchiveBtn" class="btn btn-info fw-bold toggle-archive-btn ml-2">
                            <i class="fas fa-archive mr-2"></i>Show Archive
                        </button>
                    </div>
                </div>

                <!-- Active News Table -->
                <section id="activeNewsSection" class="active-news-list py-4">
                    <div class="container" style="max-width: 95%;"> 
                        <div class="card shadow" style="border-top: 8px solid #4169E1; border-radius: 1rem;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                                    <h3 class="text-start mb-2" style="color: #4169E1; font-weight: 900;">Active News</h3>
                                </div>

                                <!-- Active Table -->
                                <div class="table-responsive">
                                    <table id="activeNewsTable" class="table table-bordered table-hover align-middle text-center" style="width:100%">
                                        <thead style="background-color: #4169E1; color: white;">
                                            <tr>
                                                <th style="width: 15%">Image</th>
                                                <th style="width: 30%">Title</th>
                                                <th style="width: 15%">Category</th>
                                                <th style="width: 10%">Visibility</th>
                                                <th style="width: 15%">Event Date</th>
                                                <th style="width: 15%">Actions</th>
                                            </tr>
                                        </thead>
                                      <tbody>
                                            <?php foreach ($active_news as $news): ?>
                                                <tr>
                                                    <td style="width: 15%">
                                                        <?php if (!empty($news['image'])): ?>
                                                            <img src="<?= htmlspecialchars(getSupabaseUrl($news['image'], 'news_pic')) ?>" alt="News Image" width="50" height="40" style="object-fit: cover; border-radius: 0%;">
                                                        <?php else: ?>
                                                            <img src="https://via.placeholder.com/50x40?text=No+Image" alt="No Image" style="object-fit: cover; border-radius: 0%;">
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="width: 30%"><?= htmlspecialchars($news['title']) ?></td>
                                                    <td style="width: 15%"><?= htmlspecialchars($news['category']) ?></td>
                                                    <td style="width: 10%">
                                                        <?php if ($news['visibility'] == 'Yes'): ?>
                                                            <span class="badge badge-success">Visible</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-secondary">Hidden</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="width: 15%"><?= date('M d, Y', strtotime($news['event_date'])) ?></td>
                                                    <td style="width: 15%">
                                                        <button class="btn btn-sm btn-success me-1 preview-btn" 
                                                            data-title="<?= htmlspecialchars($news['title']) ?>" 
                                                            data-category="<?= htmlspecialchars($news['category']) ?>" 
                                                            data-date="<?= date('M d, Y', strtotime($news['event_date'])) ?>" 
                                                            data-short="<?= htmlspecialchars($news['short_info']) ?>" 
                                                            data-full="<?= htmlspecialchars($news['full_desc']) ?>"
                                                            data-image="<?= !empty($news['image']) ? htmlspecialchars(getSupabaseUrl($news['image'], 'news_pic')) : '' ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-primary me-1 edit-btn" 
                                                            data-id="<?= $news['news_id'] ?>" 
                                                            data-title="<?= htmlspecialchars($news['title']) ?>" 
                                                            data-category="<?= htmlspecialchars($news['category']) ?>" 
                                                            data-date="<?= $news['event_date'] ?>" 
                                                            data-short="<?= htmlspecialchars($news['short_info']) ?>" 
                                                            data-full="<?= htmlspecialchars($news['full_desc']) ?>" 
                                                            data-visibility="<?= $news['visibility'] ?>"
                                                            data-image="<?= !empty($news['image']) ? htmlspecialchars(getSupabaseUrl($news['image'], 'news_pic')) : '' ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-warning me-1 archive-btn" data-id="<?= $news['news_id'] ?>">
                                                            <i class="fas fa-archive"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Archive News Table -->
                <section id="archiveNewsSection" class="archive-news-list py-4" style="display: none;">
                    <div class="container" style="max-width: 95%;">
                        <div class="card shadow" style="border-top: 8px solid #17a2b8; border-radius: 1rem;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                                    <h3 class="text-start mb-2" style="color: #17a2b8; font-weight: 900;">Archived News</h3>
                                </div>

                                <!-- Archived Table -->
                                <div class="table-responsive">
                                    <table id="archivedNewsTable" class="table table-bordered table-hover align-middle text-center" style="width:100%">
                                        <thead style="background-color: #17a2b8; color: white;">
                                            <tr>
                                                <th style="width: 15%">Image</th>
                                                <th style="width: 30%">Title</th>
                                                <th style="width: 15%">Category</th>
                                                <th style="width: 20%">Event Date</th>
                                                <th style="width: 15%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($archived_news as $news): ?>
                                                <tr>
                                                   <td style="width: 15%">
                                                        <?php if (!empty($news['image'])): ?>
                                                            <img src="<?= htmlspecialchars(getSupabaseUrl($news['image'], 'news_pic')) ?>" alt="News Image" width="50" height="40" style="object-fit: cover; border-radius: 0%;">
                                                        <?php else: ?>
                                                            <img src="https://via.placeholder.com/50x40?text=No+Image" alt="No Image" style="object-fit: cover; border-radius: 0%;">
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="width: 30%"><?= htmlspecialchars($news['title']) ?></td>
                                                    <td style="width: 15%"><?= htmlspecialchars($news['category']) ?></td>
                                                    <td style="width: 20%"><?= date('M d, Y', strtotime($news['event_date'])) ?></td>
                                                    <td style="width: 15%">
                                                         <button class="btn btn-sm btn-success me-1 preview-btn" 
                                                            data-title="<?= htmlspecialchars($news['title']) ?>" 
                                                            data-category="<?= htmlspecialchars($news['category']) ?>" 
                                                            data-date="<?= date('M d, Y', strtotime($news['event_date'])) ?>" 
                                                            data-short="<?= htmlspecialchars($news['short_info']) ?>" 
                                                            data-full="<?= htmlspecialchars($news['full_desc']) ?>"
                                                            data-image="<?= !empty($news['image']) ? htmlspecialchars(getSupabaseUrl($news['image'], 'news_pic')) : '' ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-primary me-1 restore-btn" data-id="<?php echo $news['news_id']; ?>">
                                                            <i class="fas fa-undo-alt"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger delete-btn" data-id="<?php echo $news['news_id']; ?>">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Preview Modal -->
                <div class="modal fade" id="previewModal" tabindex="-1" role="dialog" aria-labelledby="previewModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 650px;">
                        <div class="modal-content shadow-lg" style="border-radius: 1rem; overflow: hidden;">
                            <!-- Event Image -->
                            <div class="position-relative">
                                <img id="previewImage" src="" class="img-fluid w-100" alt="Event Image" style="max-height: 400px; object-fit: cover;">
                                <span id="previewCategoryBadge" class="badge badge-primary position-absolute" style="top: 10px; left: 10px; font-size: 0.65rem; padding: 4px 10px;"></span>
                            </div>

                            <!-- Content -->
                            <div class="p-4" style="font-size: 0.92rem; color: #444;">
                                <h5 class="font-weight-bold mb-1" style="color: #222;" id="previewTitle"></h5>
                                <p class="mb-2 text-muted" style="font-size: 0.85rem;">
                                    <i class="far fa-calendar-alt"></i> <span id="previewDate"></span>
                                </p>
                                <h6 class="font-weight-bold mb-1" style="color: #222;">Summary:</h6>
                                <p class="text-justify mb-4" style="line-height: 1.5;" id="previewShortInfo"></p>
                                <h6 class="font-weight-bold mb-1" style="color: #222;">Full Content:</h6>
                                <p class="text-justify mb-0" style="line-height: 1.5;" id="previewFullContent"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit News Modal -->
                <div class="modal fade" id="editNewsModal" tabindex="-1" role="dialog" aria-labelledby="editNewsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editNewsModalLabel">Edit News</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editNewsForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="edit_id" id="editId">
                <input type="hidden" id="imageRemovedFlag" name="image_removed" value="0">
                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">News Title</label>
                                            <input type="text" name="edit_news_title" id="editNewsTitle" class="form-control" required>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">Category</label>
                                            <select name="edit_category" id="editCategory" class="form-control" required>
                                                <option value="" disabled selected>Select Category</option>
                                                <option value="School Event">School Event</option>
                                                <option value="Announcement">Announcement</option>
                                                <option value="Academic">Academic</option>
                                                <option value="Sports">Sports</option>
                                                <option value="Community">Community</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">News Date</label>
                                            <input type="date" name="edit_news_date" id="editNewsDate" class="form-control" required>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">Upload Image</label>
                                            <div class="d-flex align-items-start">
                                                <div class="image-preview-container">
                                                    <img id="editImagePreview" src="" class="mr-3 border preview-image" alt="Image Preview">
                                                    <div class="remove-image" id="editRemoveImageBtn" style="display: none;">
                                                        <i class="fas fa-times text-danger"></i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <input type="file" name="edit_news_image" id="editNewsImage" class="form-control-file" accept=".jpg,.jpeg,.png,.webp">
                                                    <small class="text-muted">JPG,PNG,JPEG,webp files allowed. (Max 25MB)</small>
                                                    <small class="text-muted">Tip: Use a 4:3 image ratio for best display.</small>       
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-12 mb-3">
                                            <label class="form-label fw-semibold">Short Summary</label>
                                            <textarea name="edit_short_info" id="editShortInfo" class="form-control" rows="2" maxlength="1000" required></textarea>
                                            <small class="text-muted">Tip: Don't make this section too long.</small>
                                        </div>

                                        <div class="col-md-12 mb-3">
                                            <label class="form-label fw-semibold">Full Content</label>
                                            <textarea name="edit_full_content" id="editFullContent" class="form-control" rows="4" required></textarea>
                                        </div>

                                        <div class="form-group col-md-6">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="editFacultyVisibility" name="edit_visible">
                                                <label class="custom-control-label" for="editFacultyVisibility">Visible to public</label>
                                            </div>
                                            <small class="form-text text-muted">When disabled, this won't be visible on the website</small>
                                        </div>
                                    </div>
                                </div>
                               <div class="modal-footer">
                    <button type="submit" id="updateNewsSubmit" class="btn btn-primary fw-bold">
                        <span id="updateNewsSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        <span id="updateNewsText">Update News</span>
                    </button>
                    <button type="button" class="btn btn-secondary fw-bold" data-dismiss="modal">Cancel</button>
                </div>
            </form>
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
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        <!-- Core plugin JavaScript-->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"></script>

        <!-- Custom scripts for all pages-->
        <script src="js/sb-admin-2.min.js"></script>

        <!-- Page level plugins -->
        <script src="vendor/chart.js/Chart.min.js"></script>

        <!-- Page level custom scripts -->
        <script src="js/demo/chart-area-demo.js"></script>
        <script src="js/demo/chart-pie-demo.js"></script>
        
        <!-- DataTables JS -->
        <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
        
      <script>
$(document).ready(function() {
    // Initialize DataTables for active and archived
    $('#activeNewsTable').DataTable({
        "dom": '<"top"<"d-flex flex-column flex-md-row justify-content-between"<"mb-2 mb-md-0"l><"mb-2 mb-md-0"f>>>rt<"bottom"ip>',
        "responsive": true,
        "language": {
            "emptyTable": "No active news found. Add some news to get started!",
            "search": "Search:",
            "searchPlaceholder": "Search active news..."
        },
        "columnDefs": [{ "orderable": false, "targets": [0,5] }],
        "lengthMenu": [5,10,25,50],
        "pageLength": 10,
        "initComplete": function() {
            $('.dataTables_filter input').addClass('form-control form-control-sm');
            $('.dataTables_length select').addClass('form-control form-control-sm');
        }
    });

    $('#archivedNewsTable').DataTable({
        "dom": '<"top"<"d-flex flex-column flex-md-row justify-content-between"<"mb-2 mb-md-0"l><"mb-2 mb-md-0"f>>>rt<"bottom"ip>',
        "responsive": true,
        "language": {
            "emptyTable": "No archived news found. Archive some news to see them here!",
            "search": "Search:",
            "searchPlaceholder": "Search archived news..."
        },
        "columnDefs": [{ "orderable": false, "targets": [0,4] }],
        "lengthMenu": [5,10,25,50],
        "pageLength": 10,
        "initComplete": function() {
            $('.dataTables_filter input').addClass('form-control form-control-sm');
            $('.dataTables_length select').addClass('form-control form-control-sm');
        }
    });

    // Toggle archive section
    $('#toggleArchiveBtn').click(function() {
        const activeSection = $('#activeNewsSection');
        const archiveSection = $('#archiveNewsSection');
        const toggleBtn = $('#toggleArchiveBtn');

        if (archiveSection.is(':hidden')) {
            archiveSection.show();
            activeSection.hide();
            toggleBtn.html('<i class="fas fa-file-alt mr-2"></i>Show Active');
            toggleBtn.removeClass('btn-info').addClass('btn-primary');
        } else {
            archiveSection.hide();
            activeSection.show();
            toggleBtn.html('<i class="fas fa-archive mr-2"></i>Show Archive');
            toggleBtn.removeClass('btn-primary').addClass('btn-info');
        }
    });

    // Add News image preview (Add modal)
    function previewImage(event) {
        const input = event.target;
        const reader = new FileReader();
        reader.onload = function() {
            const preview = document.getElementById('imagePreview');
            if (preview) preview.src = reader.result;
            $('#removeImageBtn').show();
        };
        if (input.files && input.files[0]) {
            if (input.files[0].size > 25 * 1024 * 1024) {
                Swal.fire({ icon: 'error', title: 'File too large', text: 'File size exceeds 25MB limit. Please choose a smaller file.', confirmButtonColor: '#3085d6' });
                input.value = '';
                return;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    $('#newsImage').on('change', previewImage);
    $('#removeImageBtn').click(function() {
        $('#imagePreview').attr('src', 'https://via.placeholder.com/100x100?text=Preview');
        $('#newsImage').val('');
        $(this).hide();
        $('#newsImage').attr('type','file');
    });

    // Add validation and loading state for add form
    $('#newsForm').submit(function(e) {
        const imageInput = document.getElementById('newsImage');
        const preview = document.getElementById('imagePreview');

        if (imageInput.files.length === 0 && preview && preview.src.includes('placeholder')) {
            e.preventDefault();
            Swal.fire({ icon: 'error', title: 'Image Required', text: 'Please upload an image for the news item', confirmButtonColor: '#3085d6' });
            return false;
        }

        // Show loading state
        $('#addNewsSpinner').removeClass('d-none');
        $('#addNewsText').text('Adding...');
        $('#addNewsSubmit').prop('disabled', true);
        return true; // allow form submit
    });

    // Edit image preview
    function previewEditImage(event) {
        const input = event.target;
        const reader = new FileReader();
        reader.onload = function() {
            const preview = document.getElementById('editImagePreview');
            if (preview) preview.src = reader.result;
            $('#editRemoveImageBtn').show();
        };
        if (input.files && input.files[0]) {
            if (input.files[0].size > 25 * 1024 * 1024) {
                Swal.fire({ icon: 'error', title: 'File too large', text: 'File size exceeds 25MB limit. Please choose a smaller file.', confirmButtonColor: '#3085d6' });
                input.value = '';
                return;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    $('#editNewsImage').on('change', previewEditImage);
    $('#editRemoveImageBtn').click(function() {
        $('#editImagePreview').attr('src', 'https://via.placeholder.com/100x100?text=Preview');
        $('#editNewsImage').val('');
        $('#imageRemovedFlag').val("1");
        $(this).hide();
    });

    // Edit form validation and loading state
    $('#editNewsForm').submit(function(e) {
        const imageRemoved = $('#imageRemovedFlag').val() === "1";
        const newImageSelected = $('#editNewsImage').get(0).files.length > 0;
        const hasImage = $('#editImagePreview').attr('src').includes('placeholder') === false;

        if (!hasImage && !newImageSelected && imageRemoved) {
            e.preventDefault();
            Swal.fire({ icon: 'error', title: 'Image Required', text: 'Please upload an image or keep the existing one', confirmButtonColor: '#3085d6' });
            return false;
        }

        // Show loading state
        $('#updateNewsSpinner').removeClass('d-none');
        $('#updateNewsText').text('Updating...');
        $('#updateNewsSubmit').prop('disabled', true);
        return true;
    });

    // Preview news
    $(document).on('click', '.preview-btn', function() {
        const title = $(this).data('title');
        const category = $(this).data('category');
        const date = $(this).data('date');
        const short = $(this).data('short');
        const full = $(this).data('full');
        const image = $(this).data('image');

        $('#previewTitle').text(title);
        $('#previewCategoryBadge').text(category);
        $('#previewDate').text(date);

        $('#previewShortInfo').text(short);
        $('#previewFullContent').text(full);

        if (image) {
            $('#previewImage').attr('src', image);
        } else {
            $('#previewImage').attr('src', 'https://via.placeholder.com/600x400?text=No+Image');
        }

        $('#previewModal').modal('show');
    });

    // Edit news: fill form fields
    $(document).on('click', '.edit-btn', function() {
        const id = $(this).data('id');
        const title = $(this).data('title');
        const category = $(this).data('category');
        const date = $(this).data('date');
        const short = $(this).data('short');
        const full = $(this).data('full');
        const visibility = $(this).data('visibility');
        const image = $(this).data('image');

        $('#editId').val(id);
        $('#editNewsTitle').val(title);
        $('#editCategory').val(category);
        $('#editNewsDate').val(date);
        $('#editShortInfo').val(short);
        $('#editFullContent').val(full);
        $('#imageRemovedFlag').val("0");

        if (image) {
            $('#editImagePreview').attr('src', image);
            $('#editRemoveImageBtn').show();
        } else {
            $('#editImagePreview').attr('src', 'https://via.placeholder.com/100x100?text=Preview');
            $('#editRemoveImageBtn').hide();
        }

        $('#editFacultyVisibility').prop('checked', (visibility === 'Yes'));
        $('#editNewsModal').modal('show');
    });

    // Archive, Delete, Restore actions with confirmation and form submit
    function postAction(fieldName, id, title, confirmOptions) {
        Swal.fire(confirmOptions).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = fieldName;
                input.value = id;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    $(document).on('click', '.archive-btn', function() {
        const id = $(this).data('id');
        postAction('archive_id', id, 'Archive News', {
            title: 'Archive News?',
            text: "Are you sure you want to archive this news? It will be moved to the archive section.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, archive it!'
        });
    });

    $(document).on('click', '.delete-btn', function() {
        const id = $(this).data('id');
        postAction('delete_id', id, 'Delete News', {
            title: 'Delete News?',
            text: "Are you sure you want to permanently delete this news? This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        });
    });

    $(document).on('click', '.restore-btn', function() {
        const id = $(this).data('id');
        postAction('restore_id', id, 'Restore News', {
            title: 'Restore News?',
            text: "Are you sure you want to restore this news? It will be moved back to the active section.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#007bff',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, restore it!'
        });
    });

    // Reset add modal on close
    $('#addNewsModal').on('hidden.bs.modal', function() {
        $('#newsForm')[0].reset();
        $('#imagePreview').attr('src', 'https://via.placeholder.com/100x100?text=Preview');
        $('#removeImageBtn').hide();
        $('#addNewsSpinner').addClass('d-none');
        $('#addNewsText').text('Add News');
        $('#addNewsSubmit').prop('disabled', false);
    });

    // Reset edit modal on close
    $('#editNewsModal').on('hidden.bs.modal', function() {
        $('#editNewsForm')[0].reset();
        $('#editImagePreview').attr('src', 'https://via.placeholder.com/100x100?text=Preview');
        $('#editRemoveImageBtn').hide();
        $('#updateNewsSpinner').addClass('d-none');
        $('#updateNewsText').text('Update News');
        $('#updateNewsSubmit').prop('disabled', false);
    });

    // Profile image preview (keep behavior; note: original file had id collisions; ensure preview element ids exist)
    $('#profileImageInput').change(function(event) {
        const file = event.target.files[0];
        const maxSize = 25 * 1024 * 1024;
        if (file) {
            if (file.size > maxSize) {
                Swal.fire({ icon: 'error', title: 'File too large', text: 'The selected image exceeds 25MB. Please choose a smaller file.', confirmButtonColor: '#3085d6' });
                event.target.value = '';
                return;
            }
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                Swal.fire({ icon: 'error', title: 'Invalid file type', text: 'Only JPG, JPEG, PNG, and WEBP are allowed.', confirmButtonColor: '#3085d6' });
                event.target.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                // update correct preview image id (profile preview id in markup: previewImage)
                $('#previewImagee').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);
        }
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