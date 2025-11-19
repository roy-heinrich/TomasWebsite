<?php
require_once 'session.php';

require_once 'config.php'; // provides PDO $conn, Supabase helpers and constants

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

/**
 * Helper: upload file to supabase achievement_pic bucket and return object name or false
 */
function uploadAchievementFile($tmpPath, $originalName) {
    // normalize name: timestamp + random + basename
    $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', basename($originalName));
    try {
        $objectName = time() . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
    } catch (Exception $e) {
        // fallback
        $objectName = time() . '_' . uniqid() . '_' . $safeName;
    }
    // upload
    if (uploadToSupabase($tmpPath, $objectName, 'achievement_pic')) {
        return $objectName;
    }
    return false;
}

/*
 * Create Achievement
 * Use explicit hidden form field 'form_action' => 'create' to reliably detect the form submission
 */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['form_action']) && $_POST['form_action'] === 'create') {
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['short_des'] ?? '');
    $visibility = isset($_POST['visibility']) ? 'Yes' : 'No';
    $status = 'Active';
    $created_at = date("Y-m-d H:i:s");

    // Validate basic
    if ($title === '' || $desc === '') {
        $_SESSION['error'] = "Title and Description are required.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Process uploads
    $imageNames = [];
    $totalSize = 0;
    if (!empty($_FILES['images']) && is_array($_FILES['images']['tmp_name'])) {
        foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
            if (!is_uploaded_file($tmpName)) continue;
            $totalSize += $_FILES['images']['size'][$key];
        }
    }

    if ($totalSize > 25 * 1024 * 1024) {
        $_SESSION['error'] = "Total file size exceeds 25MB limit.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    if (!empty($_FILES['images']) && is_array($_FILES['images']['tmp_name'])) {
        foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
            if (!is_uploaded_file($tmpName)) continue;
            $origName = $_FILES['images']['name'][$key];
            $uploadedObject = uploadAchievementFile($tmpName, $origName);
            if ($uploadedObject !== false) {
                $imageNames[] = $uploadedObject;
            }
        }
    }

    if (empty($imageNames)) {
        $_SESSION['error'] = "Please upload at least one valid image (Max total size 25MB).";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $imagesStr = implode(',', $imageNames);

    try {
        $stmt = $conn->prepare("INSERT INTO achieve_tbl (title, description, visibility, images, status, created_at) VALUES (:title, :description, :visibility, :images, :status, :created_at)");
        $stmt->execute([
            ':title' => $title,
            ':description' => $desc,
            ':visibility' => $visibility,
            ':images' => $imagesStr,
            ':status' => $status,
            ':created_at' => $created_at
        ]);
        $_SESSION['success'] = "New achievement added successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to add achievement: " . $e->getMessage();
        // Attempt to cleanup uploaded files on failure
        foreach ($imageNames as $obj) {
            @deleteFromSupabase($obj, 'achievement_pic');
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/*
 * Archive Achievement
 */
if (isset($_GET['archive_id'])) {
    $id = (int)$_GET['archive_id'];
    try {
        $stmt = $conn->prepare("UPDATE achieve_tbl SET status = 'Archived' WHERE ach_id = :id");
        $stmt->execute([':id' => $id]);
        $_SESSION['success'] = "Achievement archived successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to archive achievement: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/*
 * Restore Achievement
 */
if (isset($_GET['restore_id'])) {
    $id = (int)$_GET['restore_id'];
    try {
        $stmt = $conn->prepare("UPDATE achieve_tbl SET status = 'Active' WHERE ach_id = :id");
        $stmt->execute([':id' => $id]);
        $_SESSION['success'] = "Achievement restored successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to restore achievement: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/*
 * Delete Achievement (permanent)
 */
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    try {
        // Get images
        $stmt = $conn->prepare("SELECT images FROM achieve_tbl WHERE ach_id = :id");
        $stmt->execute([':id' => $id]);
        $achievement = $stmt->fetch();
        if ($achievement && !empty($achievement['images'])) {
            $images = explode(',', $achievement['images']);
            foreach ($images as $img) {
                if (!empty($img)) {
                    @deleteFromSupabase($img, 'achievement_pic');
                }
            }
        }

        // Delete record
        $stmt = $conn->prepare("DELETE FROM achieve_tbl WHERE ach_id = :id");
        $stmt->execute([':id' => $id]);
        $_SESSION['success'] = "Achievement deleted permanently!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to delete achievement: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/*
 * Get Achievement Data for Edit (optional server-side fetch)
 */
$editData = null;
if (isset($_GET['edit_id'])) {
    $id = (int)$_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM achieve_tbl WHERE ach_id = :id");
    $stmt->execute([':id' => $id]);
    $editData = $stmt->fetch();
}

/*
 * Update Achievement
 * Use explicit hidden form field 'form_action' => 'update' to reliably detect the form submission
 */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['form_action']) && $_POST['form_action'] === 'update') {
    $id = (int)($_POST['ach_id'] ?? 0);
    $title = trim($_POST['edit_title'] ?? '');
    $desc = trim($_POST['edit_description'] ?? '');
    $visibility = isset($_POST['edit_visibility']) ? 'Yes' : 'No';

    if ($id <= 0 || $title === '' || $desc === '') {
        $_SESSION['error'] = "Invalid input data.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    try {
        // Get existing images
        $stmt = $conn->prepare("SELECT images FROM achieve_tbl WHERE ach_id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        $existingImages = [];
        if ($row && !empty($row['images'])) {
            $existingImages = array_filter(array_map('trim', explode(',', $row['images'])));
        }

        // Remaining images from checkboxes
        $remainingImages = [];
        if (isset($_POST['existing_images']) && is_array($_POST['existing_images'])) {
            $remainingImages = array_map('trim', $_POST['existing_images']);
        }

        // Delete removed images from supabase
        $toDelete = array_diff($existingImages, $remainingImages);
        foreach ($toDelete as $obj) {
            if (!empty($obj)) {
                @deleteFromSupabase($obj, 'achievement_pic');
            }
        }

        // Handle new uploads
        $newImages = [];
        $totalSize = 0;
        if (!empty($_FILES['edit_images']) && is_array($_FILES['edit_images']['tmp_name'])) {
            foreach ($_FILES['edit_images']['tmp_name'] as $key => $tmpName) {
                if (!is_uploaded_file($tmpName)) continue;
                $totalSize += $_FILES['edit_images']['size'][$key];
            }
        }

        if ($totalSize > 25 * 1024 * 1024) {
            $_SESSION['error'] = "Total file size exceeds 25MB limit";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        if (!empty($_FILES['edit_images']) && is_array($_FILES['edit_images']['tmp_name'])) {
            foreach ($_FILES['edit_images']['tmp_name'] as $key => $tmpName) {
                if (!is_uploaded_file($tmpName)) continue;
                $origName = $_FILES['edit_images']['name'][$key];
                $uploadedObject = uploadAchievementFile($tmpName, $origName);
                if ($uploadedObject !== false) {
                    $newImages[] = $uploadedObject;
                }
            }
        }

        // Combine remaining and new
        $allImages = array_merge($remainingImages, $newImages);
        $imagesStr = implode(',', $allImages);

        // Update record
        $stmt = $conn->prepare("UPDATE achieve_tbl SET title = :title, description = :description, visibility = :visibility, images = :images WHERE ach_id = :id");
        $stmt->execute([
            ':title' => $title,
            ':description' => $desc,
            ':visibility' => $visibility,
            ':images' => $imagesStr,
            ':id' => $id
        ]);

        $_SESSION['success'] = "Achievement updated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update achievement: " . $e->getMessage();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/*
 * Fetch lists
 */
$activeAchievements = [];
$stmt = $conn->prepare("SELECT * FROM achieve_tbl WHERE status = 'Active' ORDER BY created_at DESC");
$stmt->execute();
$activeAchievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$archivedAchievements = [];
$stmt = $conn->prepare("SELECT * FROM achieve_tbl WHERE status = 'Archived' ORDER BY created_at DESC");
$stmt->execute();
$archivedAchievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * Profile image url
 */
$profileImage = $user['profile_image'] ?? '';
if (strpos($profileImage, 'http') === 0) {
    $profileImageUrl = $profileImage;
} else if (!empty($profileImage)) {
    $profileImageUrl = getSupabaseUrl($profileImage);
} else {
    $profileImageUrl = "https://ui-avatars.com/api/?name=" . urlencode($user['fullname']) . "&size=200&background=random";
}

// Supabase public base for achievement images (used by JS when building edit modal)
$supabaseAchievementPublicBase = SUPABASE_URL . '/storage/v1/object/public/achievement_pic/';
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Achievement Management</title>

    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
     
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

    .add-new-btn,
    .toggle-archive-btn {
        border-radius: 0.5rem;
        min-width: 180px;
        padding: 0.6rem 1.2rem;
        font-size: 1rem;
    }

    @media (max-width: 576px) {
        .add-new-btn,
        .toggle-archive-btn {
            min-width: 130px;
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }
    }

    .border-top-primary {
        border-top: 4px solid #007bff;
    }

    .border-top-warning {
        border-top: 4px solid #17a2b8;
    }
    
    /* Make images in archive section same as active section */
    .achievement-image {
        height: 120px;
        width: 100%;
        object-fit: cover;
    }


    /* Highlight achievement titles */
    .card-title {
        color: #4040ceff; /* Matching the header color */
        font-weight: 700;
        position: relative;
       
    }

    
    
       /* Make achievement sections scrollable */
    .achievement-section-container {
         max-height: 70vh;
        overflow-y: auto;
        padding: 0 11px 0 0; /* Right padding for scrollbar */
        margin-right: -20px; 
    }

      /* Mobile adjustments for achievement cards */
    @media (max-width: 767.98px) {
        .achievement-section-container {
            max-height: 60vh;
            margin-right: -20px; /* More aggressive pull for mobile */
        }
        
        .card-title {
            font-size: 1rem;
            margin-bottom: 0.25rem;
            max-width: 70%; /* Prevent overlap with date */
        }
        
        .achievement-date {
            font-size: 0.75rem;
            margin-left: auto; /* Push date to the right */
            padding-left: 10px; /* Space between title and date */
            white-space: nowrap; /* Prevent wrapping */
        }
        
        .achievement-image {
            height: 90px;
        }
        
        .btn-sm {
            padding: 0.2rem 0.4rem;
            font-size: 0.75rem;
        }
    }

    /* Custom scrollbar for achievement sections */
    .achievement-section-container::-webkit-scrollbar {
        width: 5px;
    }

    .achievement-section-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .achievement-section-container::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
    }

    .achievement-section-container::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

   @media (max-width: 576px) {
  .description-mobile {
    font-size: 0.75rem; /* smaller font on mobile */
  }
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
                        <a class="collapse-item" href="message.php">Home Page</a>
                        <a class="collapse-item" href="News_Eve.php">News & Events</a> 
                        <a class="collapse-item" href="Edit_Staff.php">Faculty & Staff</a>
                        <a class="collapse-item active" href="Edit_Achieve.php">Achievements</a> 
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
                    <div class="d-sm-flex align-items-center justify-content-center mb-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-trophy" style="color: rgb(11, 104, 245); font-size: 1.3rem; margin-right: 0.8rem;"></i>
                            <h2 class="fw-bolder mb-0" style="color: rgb(11, 104, 245); font-size: 1.5rem; font-weight: 800;">Achievement Page</h2>
                        </div>
                    </div>

                    <!-- Create Achievement Modal -->
                    <div class="modal fade" id="createAchievementModal" tabindex="-1" role="dialog" aria-labelledby="createAchievementModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header" style="background-color:rgba(42, 8, 213, 0.77);">
                                    <h5 class="modal-title text-white">Create New Achievement</h5>
                                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <form id="achievementForm" method="POST" enctype="multipart/form-data">
                                        <!-- action marker -->
                                        <input type="hidden" name="form_action" value="create">
                                        <div class="form-group">
                                            <label>Title</label>
                                            <input type="text" name="title" class="form-control" placeholder="E.g., Green School Program" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Short Description</label>
                                            <textarea name="short_des" id="short_des" class="form-control" rows="3" placeholder="Input text here" maxlength="1000" required></textarea>
                                            <small class="form-text text-muted"><span id="charCount">0</span>/1000 characters used</small>
                                        </div>
                                        <div class="form-group">
                                            <label>Visibility</label>
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" name="visibility" class="custom-control-input" id="visibilitySwitch" checked>
                                                <label class="custom-control-label" for="visibilitySwitch">Visible to public</label>
                                            </div>
                                            <small class="form-text text-muted">When disabled, this event won't be visible on the website</small>
                                        </div>
                                        <div class="form-group">
                                            <label>Upload Images</label>
                                            <input type="file" id="imageInput" name="images[]" multiple accept=".jpg,.jpeg,.png,.webp" class="form-control-file" required>
                                            <div id="previewContainer" class="d-flex flex-wrap justify-content-start mt-3"></div>
                                            <small class="form-text text-muted">Maximum total size: 25MB. Allowed: JPG, PNG, JPEG, WEBP</small>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="resetAchievementForm()">Cancel</button>
                                            <button type="submit" id="createSubmitBtn" class="btn btn-primary px-4">
                                                <i class="fas fa-plus-circle mr-2"></i><span id="createBtnText">Create</span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Achievement Modal -->
                    <div class="modal fade" id="editAchievementModal" tabindex="-1" role="dialog" aria-labelledby="editAchievementModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header" style="background-color:rgba(42, 8, 213, 0.77);">
                                    <h5 class="modal-title text-white">Edit Achievement</h5>
                                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <form id="editAchievementForm" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="form_action" value="update">
                                    <input type="hidden" name="ach_id" id="edit_ach_id">
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label>Title</label>
                                            <input type="text" name="edit_title" id="edit_title" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Short Description</label>
                                            <textarea name="edit_description" id="edit_description" class="form-control" rows="3" maxlength="1000" required></textarea>
                                            <small class="form-text text-muted"><span id="editCharCount">0</span>/1000 characters used</small>
                                        </div>
                                        <div class="form-group">
                                            <label>Visibility</label>
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" name="edit_visibility" class="custom-control-input" id="edit_visibilitySwitch">
                                                <label class="custom-control-label" for="edit_visibilitySwitch">Visible to public</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Current Images</label>
                                            <div id="existingImagesContainer" class="d-flex flex-wrap mb-3"></div>

                                            <label>Upload New Images</label>
                                            <input type="file" id="edit_imageInput" name="edit_images[]" multiple accept=".jpg,.jpeg,.png,.webp" class="form-control-file">
                                            <div id="edit_previewContainer" class="d-flex flex-wrap justify-content-start mt-3"></div>
                                            <small class="form-text text-muted">Allowed: JPG, PNG, JPEG, WEBP (Max 25MB total)</small>
                                            <small class="form-text text-danger" id="sizeError"></small>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="submit" id="editSubmitBtn" class="btn btn-primary px-4">
                                            <i class="fas fa-save mr-2"></i><span id="editBtnText">Save Changes</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Controls and lists (same rendering as before) -->
                    <div class="row justify-content-center mb-3">
                        <div class="col-lg-11 d-flex justify-content-between align-items-center">
                            <div>
                                <button class="btn btn-primary fw-bold add-new-btn" data-toggle="modal" data-target="#createAchievementModal">
                                    <i class="fas fa-plus-circle mr-2"></i>Add New
                                </button>

                                <button id="toggleButton" class="btn btn-info fw-bold toggle-archive-btn ml-2">
                                    <i class="fas fa-archive mr-2"></i><span id="toggleText">Show Archive</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Active and Archive sections (render as before) -->
                    <div id="activeSection">
                        <div class="row justify-content-center">
                            <div class="col-lg-11">
                                <div class="card shadow mb-4">
                                    <div class="card-header py-3 d-flex justify-content-between align-items-center" style="background-color:rgba(42, 8, 213, 0.77);">
                                        <h6 class="m-0 font-weight-bold text-white">Posted Achievements</h6>
                                        <span class="badge badge-info"><?= count($activeAchievements) ?> Events</span>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($activeAchievements)): ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No active achievements found</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="achievement-section-container">
                                                <div class="list-group">
                                                    <?php foreach ($activeAchievements as $achievement):
                                                        $images = !empty($achievement['images']) ? array_filter(explode(',', $achievement['images'])) : [];
                                                        $created = new DateTime($achievement['created_at']);
                                                    ?>
                                                        <div class="list-group-item p-0 border-0 mb-3">
                                                            <div class="card border-top-primary shadow">
                                                                <div class="card-body">
                                                                    <div class="d-flex justify-content-between align-items-start">
                                                                        <h5 class="card-title"><?= htmlspecialchars($achievement['title']) ?></h5>
                                                                        <small class="text-muted achievement-date">
                                                                            Created:
                                                                            <span class="d-none d-md-inline"><?= $created->format('M d, Y') ?></span>
                                                                            <span class="d-md-none"><?= $created->format('m/d/y') ?></span>
                                                                        </small>
                                                                    </div>
                                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                                        <div>
                                                                            <span class="badge badge-<?= $achievement['visibility'] == 'Yes' ? 'success' : 'secondary' ?> mr-2">
                                                                                <?= $achievement['visibility'] == 'Yes' ? 'Visible' : 'Hidden' ?>
                                                                            </span>
                                                                            <span class="badge badge-info"><?= count($images) ?> items</span>
                                                                        </div>
                                                                    </div>
                                                                    <?php if (!empty($images)): ?>
                                                                        <div class="row">
                                                                            <?php foreach ($images as $image):
                                                                                $imgUrl = htmlspecialchars(getSupabaseUrl($image, 'achievement_pic'));
                                                                            ?>
                                                                                <div class="col-6 col-sm-4 col-md-3 mb-3">
                                                                                    <div class="position-relative">
                                                                                        <img src="<?= $imgUrl ?>" alt="Achievement image" class="img-fluid rounded achievement-image">
                                                                                    </div>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <div class="mt-3 p-3 bg-light border rounded">
                                                                        <h6 class="font-weight-bold text-primary">Description</h6>
                                                                        <p class="mb-0 text-muted description-mobile"><?= nl2br(htmlspecialchars($achievement['description'])) ?></p>
                                                                    </div>
                                                                    <div class="d-flex justify-content-end mt-3">
                                                                        <button class="btn btn-sm btn-outline-primary mr-2 edit-btn" 
                                                                                data-id="<?= $achievement['ach_id'] ?>"
                                                                                data-title="<?= htmlspecialchars($achievement['title']) ?>"
                                                                                data-description="<?= htmlspecialchars($achievement['description']) ?>"
                                                                                data-visibility="<?= $achievement['visibility'] ?>"
                                                                                data-images="<?= htmlspecialchars($achievement['images']) ?>">
                                                                            <i class="fas fa-edit mr-1"></i>Edit
                                                                        </button>
                                                                        <button class="btn btn-sm btn-outline-warning archive-btn" data-id="<?= $achievement['ach_id'] ?>">
                                                                            <i class="fas fa-archive mr-1"></i>Archive
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="archiveSection" class="d-none">
                        <div class="row justify-content-center">
                            <div class="col-lg-11">
                                <div class="card shadow mb-4">
                                    <div class="card-header py-3 d-flex justify-content-between align-items-center" style="background-color:rgba(23, 162, 184, 1);">
                                        <h6 class="m-0 font-weight-bold text-white">Archived Achievements</h6>
                                        <span class="badge badge-info"><?= count($archivedAchievements) ?> Events</span>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($archivedAchievements)): ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-archive fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No archived achievements found</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="achievement-section-container">
                                                <div class="list-group">
                                                    <?php foreach ($archivedAchievements as $achievement):
                                                        $images = !empty($achievement['images']) ? array_filter(explode(',', $achievement['images'])) : [];
                                                        $created = new DateTime($achievement['created_at']);
                                                    ?>
                                                        <div class="list-group-item p-0 border-0 mb-3">
                                                            <div class="card border-top-warning shadow">
                                                                <div class="card-body">
                                                                    <div class="d-flex justify-content-between align-items-start">
                                                                        <h5 class="card-title"><?= htmlspecialchars($achievement['title']) ?></h5>
                                                                        <small class="text-muted achievement-date">
                                                                            Created:
                                                                            <span class="d-none d-md-inline"><?= $created->format('M d, Y') ?></span>
                                                                            <span class="d-md-none"><?= $created->format('m/d/y') ?></span>
                                                                        </small>
                                                                    </div>
                                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                                        <div>
                                                                            <span class="badge badge-<?= $achievement['visibility'] == 'Yes' ? 'success' : 'secondary' ?> mr-2">
                                                                                <?= $achievement['visibility'] == 'Yes' ? 'Visible' : 'Hidden' ?>
                                                                            </span>
                                                                            <span class="badge badge-info"><?= count($images) ?> items</span>
                                                                        </div>
                                                                    </div>
                                                                    <?php if (!empty($images)): ?>
                                                                        <div class="row">
                                                                            <?php foreach ($images as $image):
                                                                                $imgUrl = htmlspecialchars(getSupabaseUrl($image, 'achievement_pic'));
                                                                            ?>
                                                                                <div class="col-6 col-sm-4 col-md-3 mb-3">
                                                                                    <div class="position-relative">
                                                                                        <img src="<?= $imgUrl ?>" alt="Achievement image" class="img-fluid rounded achievement-image">
                                                                                    </div>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <div class="mt-3 p-3 bg-light border rounded">
                                                                        <h6 class="font-weight-bold text-primary">Description</h6>
                                                                        <p class="mb-0 text-muted description-mobile"><?= nl2br(htmlspecialchars($achievement['description'])) ?></p>
                                                                    </div>
                                                                    <div class="d-flex justify-content-end mt-3">
                                                                        <button class="btn btn-sm btn-outline-success mr-2 restore-btn" data-id="<?= $achievement['ach_id'] ?>">
                                                                            <i class="fas fa-undo mr-1"></i>Restore
                                                                        </button>
                                                                        <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?= $achievement['ach_id'] ?>">
                                                                            <i class="fas fa-trash mr-1"></i>Delete
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div> <!-- container-fluid -->
            </div> <!-- content -->


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

 
    <script>
        const SUPABASE_ACHIEVEMENT_PUBLIC_BASE = "<?= addslashes($supabaseAchievementPublicBase) ?>";

        // character counters
 document.getElementById('short_des').addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});
document.getElementById('edit_description').addEventListener('input', function() {
    document.getElementById('editCharCount').textContent = this.value.length;
});

        // Edit modal population
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const title = this.getAttribute('data-title');
                const description = this.getAttribute('data-description');
                const visibility = this.getAttribute('data-visibility');
                const images = this.getAttribute('data-images');

                document.getElementById('edit_ach_id').value = id;
                document.getElementById('edit_title').value = title;
                document.getElementById('edit_description').value = description;
                document.getElementById('editCharCount').textContent = description.length;
                document.getElementById('edit_visibilitySwitch').checked = (visibility === 'Yes');

                const existingContainer = document.getElementById('existingImagesContainer');
                existingContainer.innerHTML = '';

                if (images) {
                    const imageArray = images.split(',').map(i => i.trim()).filter(Boolean);
                    imageArray.forEach(img => {
                        const div = document.createElement('div');
                        div.className = 'position-relative mr-2 mb-2';
                        div.style.width = '100px';

                        const imgUrl = SUPABASE_ACHIEVEMENT_PUBLIC_BASE + encodeURIComponent(img);

                        div.innerHTML = `
                            <img src="${imgUrl}" class="img-fluid rounded" style="height: 80px; object-fit: cover;">
                            <div class="form-check position-absolute" style="top: 5px; right: 5px;">
                                <input type="checkbox" class="form-check-input" name="existing_images[]" value="${img}" checked>
                            </div>
                        `;
                        existingContainer.appendChild(div);
                    });
                }

                document.getElementById('edit_previewContainer').innerHTML = '';
                $('#editAchievementModal').modal('show');
            });
        });

        // create file input preview/limit
        const fileInput = document.getElementById('imageInput');
        const previewContainer = document.getElementById('previewContainer');
        const form = document.getElementById('achievementForm');
        const maxTotalSize = 25 * 1024 * 1024;
        let selectedFiles = [];

        fileInput.addEventListener('change', () => {
            const newFiles = Array.from(fileInput.files);
            const combinedSize = selectedFiles.reduce((acc, f) => acc + f.size, 0) + newFiles.reduce((acc, f) => acc + f.size, 0);

            if (combinedSize > maxTotalSize) {
                Swal.fire({
                    icon: 'error',
                    title: 'File Too Large',
                    text: 'Total file size exceeds 25MB limit.',
                    confirmButtonColor: '#d33',
                });
                fileInput.value = '';
                return;
            }

            newFiles.forEach(file => {
                if (file.type.startsWith('image/')) {
                    selectedFiles.push(file);
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const wrapper = document.createElement('div');
                        wrapper.classList.add('position-relative', 'm-2');
                        wrapper.style.width = '100px';
                        wrapper.style.height = '100px';

                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.width = '100%';
                        img.style.height = '100%';
                        img.style.objectFit = 'cover';
                        img.style.borderRadius = '10px';
                        wrapper.appendChild(img);

                        const removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.classList.add('btn', 'btn-sm', 'btn-danger', 'position-absolute');
                        removeBtn.style.top = '2px';
                        removeBtn.style.right = '2px';
                        removeBtn.innerHTML = '&times;';
                        removeBtn.onclick = () => {
                            selectedFiles = selectedFiles.filter(f => !(f.name === file.name && f.size === file.size && f.lastModified === file.lastModified));
                            wrapper.remove();
                            updateFileInput();
                        };
                        wrapper.appendChild(removeBtn);

                        previewContainer.appendChild(wrapper);
                    };
                    reader.readAsDataURL(file);
                }
            });

            fileInput.value = '';
            updateFileInput();
        });

        function updateFileInput() {
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            fileInput.files = dataTransfer.files;
        }

        function resetAchievementForm() {
            form.reset();
            fileInput.value = '';
            selectedFiles = [];
            previewContainer.innerHTML = '';
            document.getElementById('charCount').textContent = '0';
        }

        $('#createAchievementModal').on('hidden.bs.modal', resetAchievementForm);

        // Edit image input handling (keeps same behavior as before)
        let editSelectedFiles = [];
        document.getElementById('edit_imageInput').addEventListener('change', function(e) {
            const container = document.getElementById('edit_previewContainer');
            const newFiles = Array.from(e.target.files);
            const sizeError = document.getElementById('sizeError');
            sizeError.textContent = '';

            // Calculate total size of new files
            let totalSize = 0;
            newFiles.forEach(file => {
                totalSize += file.size;
            });

            if (totalSize > maxTotalSize) {
                sizeError.textContent = 'Total file size exceeds 25MB limit';
                this.value = '';
                return;
            }

            newFiles.forEach(file => {
                if (!file.type.match('image.*')) return;
                editSelectedFiles.push(file);
                const reader = new FileReader();
                reader.onload = function(evt) {
                    const div = document.createElement('div');
                    div.className = 'position-relative mr-2 mb-2';
                    div.style.width = '100px';
                    div.innerHTML = `
                        <img src="${evt.target.result}" class="img-fluid rounded" style="height: 80px; object-fit: cover;">
                        <button class="btn btn-sm btn-danger position-absolute" style="top: 5px; right: 5px;">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    const btn = div.querySelector('button');
                    btn.addEventListener('click', function() {
                        editSelectedFiles = editSelectedFiles.filter(f => !(f.name === file.name && f.size === file.size && f.lastModified === file.lastModified));
                        div.remove();
                        updateEditFileInput();
                    });
                    container.appendChild(div);
                };
                reader.readAsDataURL(file);
            });

            this.value = '';
            updateEditFileInput();
        });

        function updateEditFileInput() {
            const dataTransfer = new DataTransfer();
            editSelectedFiles.forEach(file => dataTransfer.items.add(file));
            document.getElementById('edit_imageInput').files = dataTransfer.files;
        }

        $('#editAchievementModal').on('hidden.bs.modal', function() {
            editSelectedFiles = [];
            document.getElementById('edit_previewContainer').innerHTML = '';
            document.getElementById('existingImagesContainer').innerHTML = '';
            document.getElementById('edit_imageInput').value = '';
        });

        // Archive / Delete / Restore handlers (same as before)
        document.querySelectorAll('.archive-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const archiveId = this.getAttribute('data-id');
                Swal.fire({
                    title: 'Archive Achievement?',
                    html: `<div class="text-left"><p>This achievement will be moved to the archive section.</p></div>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107',
                    confirmButtonText: 'Yes, archive it',
                }).then(result => {
                    if (result.isConfirmed) window.location.href = '?archive_id=' + archiveId;
                });
            });
        });

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const deleteId = this.getAttribute('data-id');
                Swal.fire({
                    title: 'Permanently Delete?',
                    html: `<div class="text-left"><p>This action will permanently delete the achievement and its images.</p></div>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Delete',
                }).then(result => {
                    if (result.isConfirmed) window.location.href = '?delete_id=' + deleteId;
                });
            });
        });

        document.querySelectorAll('.restore-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const restoreId = this.getAttribute('data-id');
                Swal.fire({
                    title: 'Restore Achievement?',
                    text: "This achievement will be moved back to the active section.",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    confirmButtonText: 'Yes, restore it'
                }).then(result => {
                    if (result.isConfirmed) window.location.href = '?restore_id=' + restoreId;
                });
            });
        });

        // Toggle active/archive view
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButton = document.getElementById('toggleButton');
            const toggleText = document.getElementById('toggleText');
            const activeSection = document.getElementById('activeSection');
            const archiveSection = document.getElementById('archiveSection');

            toggleButton.addEventListener('click', function() {
                if (toggleText.textContent === 'Show Archive') {
                    activeSection.classList.add('d-none');
                    archiveSection.classList.remove('d-none');
                    toggleText.textContent = 'Show Active';
                    toggleButton.classList.remove('btn-info');
                    toggleButton.classList.add('btn-primary');
                    toggleButton.querySelector('i').className = 'fas fa-list mr-2';
                } else {
                    activeSection.classList.remove('d-none');
                    archiveSection.classList.add('d-none');
                    toggleText.textContent = 'Show Archive';
                    toggleButton.classList.remove('btn-primary');
                    toggleButton.classList.add('btn-info');
                    toggleButton.querySelector('i').className = 'fas fa-archive mr-2';
                }
            });
        });

        // Loading states for create / edit submit
        document.getElementById('achievementForm').addEventListener('submit', function() {
            const btn = document.getElementById('createSubmitBtn');
            btn.disabled = true;
            document.getElementById('createBtnText').textContent = ' Creating...';
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating...';
        });

        document.getElementById('editAchievementForm').addEventListener('submit', function() {
            const btn = document.getElementById('editSubmitBtn');
            btn.disabled = true;
            document.getElementById('editBtnText').textContent = ' Saving...';
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
        });

        // Profile preview validation (same)
        $('#profileImageInput').change(function(event) {
            const file = event.target.files[0];
            const maxSize = 25 * 1024 * 1024;
            if (file) {
                if (file.size > maxSize) {
                    Swal.fire({ icon: 'error', title: 'File Too Large', text: 'The selected image exceeds 25MB.' });
                    event.target.value = '';
                    return;
                }
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    Swal.fire({ icon: 'error', title: 'Invalid File Type', text: 'Only JPG, JPEG, PNG, and WEBP are allowed.' });
                    event.target.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) { document.getElementById('previewImagee').src = e.target.result; };
                reader.readAsDataURL(file);
            }
        });

        // Toggle password show/hide
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
    <?php include 'search/Search_Admin.php'; ?>
</body>
</html> 