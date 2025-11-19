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
 * Helper: upload file to supabase gallery_pic bucket and return object name or false
 */
function uploadGalleryFile($tmpPath, $originalName) {
    // normalize name: timestamp + random + basename
    $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', basename($originalName));
    try {
        $objectName = time() . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
    } catch (Exception $e) {
        $objectName = time() . '_' . uniqid() . '_' . $safeName;
    }
    if (uploadToSupabase($tmpPath, $objectName, 'gallery_pic')) {
        return $objectName;
    }
    return false;
}

/* Consolidated flash handling (server side) */
$success_message = $_SESSION['success'] ?? '';
$error_message   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

/*
 * Create Gallery Event (stores objects in Supabase 'gallery_pic' bucket)
 * Use explicit hidden field 'form_action' => 'create' to reliably detect the form submission
 */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['form_action']) && $_POST['form_action'] === 'create') {
    $title = trim($_POST['title'] ?? '');
    $visibility = isset($_POST['visibility']) ? 'Yes' : 'No';
    $status = 'Active';
    $created_at = date("Y-m-d H:i:s");

    if ($title === '') {
        $_SESSION['error'] = "Title is required.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Process uploads
    $objectNames = [];
    $totalSize = 0;
    $maxFiles = 12;
    $allowedExt = ['jpeg','jpg','png','webp','mp4'];

    if (!empty($_FILES['mediaFiles']) && is_array($_FILES['mediaFiles']['tmp_name'])) {
        foreach ($_FILES['mediaFiles']['tmp_name'] as $key => $tmpName) {
            if (!is_uploaded_file($tmpName)) continue;
            if (count($objectNames) >= $maxFiles) break;

            $fileName = $_FILES['mediaFiles']['name'][$key];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($fileExt, $allowedExt)) continue;

            $fileSize = $_FILES['mediaFiles']['size'][$key];
            if (($totalSize + $fileSize) > 25 * 1024 * 1024) continue;

            $uploadedObject = uploadGalleryFile($tmpName, $fileName);
            if ($uploadedObject !== false) {
                $objectNames[] = $uploadedObject;
                $totalSize += $fileSize;
            }
        }
    }

    if (empty($objectNames)) {
        $_SESSION['error'] = "Please upload at least one valid media file (JPG, PNG, WEBP, MP4). Max total size 25MB, max 12 files.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $mediaStr = implode(',', $objectNames);

    try {
        $stmt = $conn->prepare("INSERT INTO gallery_tbl (title, visibility, mediafiles, status, created_at) VALUES (:title, :visibility, :mediafiles, :status, :created_at)");
        $stmt->execute([
            ':title' => $title,
            ':visibility' => $visibility,
            ':mediafiles' => $mediaStr,
            ':status' => $status,
            ':created_at' => $created_at
        ]);
        $_SESSION['success'] = "Gallery event created successfully!";
    } catch (Exception $e) {
        // cleanup uploaded objects
        foreach ($objectNames as $obj) {
            @deleteFromSupabase($obj, 'gallery_pic');
        }
        $_SESSION['error'] = "Failed to create gallery event: " . $e->getMessage();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/*
 * Archive Gallery Event
 */
if (isset($_GET['archive_id'])) {
    $id = (int)$_GET['archive_id'];
    try {
        $stmt = $conn->prepare("UPDATE gallery_tbl SET status = 'Archived' WHERE gallery_id = :id");
        $stmt->execute([':id' => $id]);
        $_SESSION['success'] = "Gallery event archived successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to archive gallery event: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/*
 * Restore Gallery Event
 */
if (isset($_GET['restore_id'])) {
    $id = (int)$_GET['restore_id'];
    try {
        $stmt = $conn->prepare("UPDATE gallery_tbl SET status = 'Active' WHERE gallery_id = :id");
        $stmt->execute([':id' => $id]);
        $_SESSION['success'] = "Gallery event restored successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to restore gallery event: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/*
 * Delete Gallery Event (permanent) - deletes objects from Supabase
 */
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    try {
        // Get media object names
        $stmt = $conn->prepare("SELECT mediafiles FROM gallery_tbl WHERE gallery_id = :id");
        $stmt->execute([':id' => $id]);
        $gallery = $stmt->fetch();
        if ($gallery && !empty($gallery['mediafiles'])) {
            $objects = array_filter(array_map('trim', explode(',', $gallery['mediafiles'])));
            foreach ($objects as $obj) {
                if (!empty($obj)) {
                    @deleteFromSupabase($obj, 'gallery_pic');
                }
            }
        }

        // Delete record
        $stmt = $conn->prepare("DELETE FROM gallery_tbl WHERE gallery_id = :id");
        $stmt->execute([':id' => $id]);
        $_SESSION['success'] = "Gallery event deleted permanently!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to delete gallery event: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/*
 * Get Gallery Data for Edit (only when explicitly requested via GET)
 */
$editData = null;
$showEditModal = false;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit_id'])) {
    $id = (int)$_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM gallery_tbl WHERE gallery_id = :id");
    $stmt->execute([':id' => $id]);
    $editData = $stmt->fetch();
    if ($editData) $showEditModal = true;
}

/*
 * Update Gallery Event
 * Use explicit hidden field 'form_action' => 'update'
 */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['form_action']) && $_POST['form_action'] === 'update') {
    $id = (int)($_POST['gallery_id'] ?? 0);
    $title = trim($_POST['edit_title'] ?? '');
    $visibility = isset($_POST['edit_visibility']) ? 'Yes' : 'No';

    if ($id <= 0 || $title === '') {
        $_SESSION['error'] = "Invalid input data.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    try {
        // Get existing object names
        $stmt = $conn->prepare("SELECT mediafiles FROM gallery_tbl WHERE gallery_id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        $existing = [];
        if ($row && !empty($row['mediafiles'])) {
            $existing = array_filter(array_map('trim', explode(',', $row['mediafiles'])));
        }

        // Remaining objects from hidden inputs
        $remaining = [];
        if (isset($_POST['existing_files']) && is_array($_POST['existing_files'])) {
            $remaining = array_map('trim', $_POST['existing_files']);
        }

        // Delete removed objects from Supabase
        $toDelete = array_diff($existing, $remaining);
        foreach ($toDelete as $obj) {
            if (!empty($obj)) {
                @deleteFromSupabase($obj, 'gallery_pic');
            }
        }

        // Handle new uploads
        $newObjects = [];
        $totalSize = 0;
        $maxFiles = 12;
        $allowedExt = ['jpeg','jpg','png','webp','mp4'];

        $currentCount = count($remaining);
        if (!empty($_FILES['edit_files']) && is_array($_FILES['edit_files']['tmp_name'])) {
            foreach ($_FILES['edit_files']['tmp_name'] as $key => $tmpName) {
                if (!is_uploaded_file($tmpName)) continue;
                if ($currentCount + count($newObjects) >= $maxFiles) break;

                $fileName = $_FILES['edit_files']['name'][$key];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (!in_array($fileExt, $allowedExt)) continue;

                $fileSize = $_FILES['edit_files']['size'][$key];
                if (($totalSize + $fileSize) > 25 * 1024 * 1024) continue;

                $uploadedObject = uploadGalleryFile($tmpName, $fileName);
                if ($uploadedObject !== false) {
                    $newObjects[] = $uploadedObject;
                    $totalSize += $fileSize;
                }
            }
        }

        // Combine remaining and new
        $all = array_merge($remaining, $newObjects);
        if (empty($all)) {
            // If validation failed and we want the edit modal open again, redirect with edit_id
            $_SESSION['error'] = "Gallery must contain at least one media file!";
            header("Location: " . $_SERVER['PHP_SELF'] . "?edit_id=" . $id);
            exit();
        }

        $mediaStr = implode(',', $all);
        $stmt = $conn->prepare("UPDATE gallery_tbl SET title = :title, visibility = :visibility, mediafiles = :mediafiles WHERE gallery_id = :id");
        $stmt->execute([
            ':title' => $title,
            ':visibility' => $visibility,
            ':mediafiles' => $mediaStr,
            ':id' => $id
        ]);

        $_SESSION['success'] = "Gallery event updated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update gallery event: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF'] . "?edit_id=" . $id);
        exit();
    }

    // Redirect back to clean page (no edit_id) after update success
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/*
 * Fetch lists
 */
$activeGalleries = [];
$stmt = $conn->prepare("SELECT * FROM gallery_tbl WHERE status = 'Active' ORDER BY created_at DESC");
$stmt->execute();
$activeGalleries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$archivedGalleries = [];
$stmt = $conn->prepare("SELECT * FROM gallery_tbl WHERE status = 'Archived' ORDER BY created_at DESC");
$stmt->execute();
$archivedGalleries = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * Gallery statistics
 */
$totalEvents = 0;
$imageCount = 0;
$videoCount = 0;

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM gallery_tbl WHERE status = 'Active'");
$stmt->execute();
$totalEvents = (int)$stmt->fetch()['total'];

$stmt = $conn->prepare("SELECT mediafiles FROM gallery_tbl WHERE status = 'Active'");
$stmt->execute();
while ($row = $stmt->fetch()) {
    if (!empty($row['mediafiles'])) {
        $files = explode(',', $row['mediafiles']);
        foreach ($files as $f) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpeg','jpg','png','webp'])) $imageCount++;
            elseif ($ext === 'mp4') $videoCount++;
        }
    }
}

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

// Supabase public base for gallery images (used by JS)
$supabaseGalleryPublicBase = SUPABASE_URL . '/storage/v1/object/public/gallery_pic/';
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Gallery Management</title>

    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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


.container-fluid {
    position: relative;
    z-index: 0;
}
#modalGalleryImages img {
  border-radius: 1rem;
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
  transition: transform 0.2s ease;
}
#modalGalleryImages img:hover {
  transform: scale(1.05);
}


.dropzone {
        cursor: pointer;
    }
    
    .media-preview-item {
        position: relative;
        display: inline-block;
        margin: 5px;
    }
    
    .remove-media {
        position: absolute;
        top: 5px;
        right: 5px;
        background: rgba(255,0,0,0.7);
        color: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        text-align: center;
        line-height: 24px;
        cursor: pointer;
    }
    
    .badge-visible {
        background-color: #28a745;
    }
    
    .badge-hidden {
        background-color: #6c757d;
    }
    
    .border-top-primary {
        border-top: 4px solid #007bff;
    }
    
    .border-left-warning {
        border-left: 4px solid #FFAE42;
    }


    .main-content-wrapper {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }
    
    .content-wrapper {
        flex: 1;
    }
    
    /* Fix for the footer */
    footer.sticky-footer {
        position: relative;
        bottom: 0;
        width: 100%;
    }
    
    /* Grid layout fixes */
    .gallery-container {
        display: flex;
        flex-wrap: wrap;
    }
    
    .gallery-main-content {
        flex: 0 0 66.666667%;
        max-width: 66.666667%;
        padding-right: 15px;
    }
    
    .gallery-sidebar {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
        padding-left: 15px;
    }
    
    @media (max-width: 991.98px) {
        .gallery-main-content,
        .gallery-sidebar {
            flex: 0 0 100%;
            max-width: 100%;
            padding: 0;
        }
    }

    .gallery-media-item {
  width: 100%;
  height: 150px;
  object-fit: cover;
  border-radius: 8px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

/* Smaller media height on mobile */
@media (max-width: 576px) {
  .gallery-media-item {
    height: 100px;
  }
}


/* Add this to your existing CSS */
.archived-gallery-title {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-right: 40px; /* Give space for the badge */
    margin-bottom: 0.5rem; /* Reduced bottom margin */
    line-height: 1.3; /* Tighter line spacing */
   
}

.archived-gallery-title h7 {
    margin-bottom: 0;
    flex: 1;
    min-width: 0; /* Allows text to truncate properly */
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.archived-badge {
    position: absolute;
    top: 10px;
    right: 10px;
}

.card-body.position-relative {
    padding-bottom: 0.75rem; /* Reduced padding at bottom */
}

@media (max-width: 576px) {
  .btn-mobile-sm {
    font-size: 0.75rem;
    padding: 0.3rem 0.4rem;
    flex: 1 1 auto; /* allows flexible shrinkage */
    white-space: nowrap; /* prevents text wrapping */
  }
}

/* Highlight for gallery titles */
.current-gallery-title {
    background: linear-gradient(90deg, rgba(42, 8, 213, 0.15) 0%, rgba(42, 8, 213, 0.1) 100%);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    display: inline-block;
     max-width: calc(100% - 80px); 
}

.current-gallery-title h5 {
    color: #333; /* Darker text color */
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Scrollable container for current gallery events */
.current-gallery-container {
    max-height: 100vh;
    overflow-y: auto;
    padding-right: 10px;
}

/* Custom scrollbar styling */
.current-gallery-container::-webkit-scrollbar {
    width: 5px;
}

.current-gallery-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.current-gallery-container::-webkit-scrollbar-thumb {
    background: rgba(42, 8, 213, 0.5);
    border-radius: 10px;
}

.current-gallery-container::-webkit-scrollbar-thumb:hover {
    background: rgba(42, 8, 213, 0.7);
}

/* Mobile date format */
@media (max-width: 576px) {
    .gallery-date {
        display: none; /* Hide full date on mobile */
    }
    
    .gallery-date-mobile {
        display: block;
        font-size: 0.75rem;
    }

    .current-gallery-title h5 {
        font-size: 0.95rem; /* Smaller font size on mobile */
         white-space: normal; /* Allow text to wrap */
        overflow: visible;
        text-overflow: clip;
    }

     .current-gallery-title {
        max-width: calc(100% - 70px); /* More space for date on mobile */
        padding: 0.4rem 0.8rem; /* Slightly smaller padding */
    }
}

@media (min-width: 577px) {
    .gallery-date-mobile {
        display: none; /* Hide mobile date on desktop */
    }
}
    </style>
</head>

  <?php if (!empty($success_message)): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: <?= json_encode($success_message) ?>,
        confirmButtonColor: '#3085d6',
      });
    });
  </script>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: <?= json_encode($error_message) ?>,
        confirmButtonColor: '#d33',
      });
    });
  </script>
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
                            <li class="nav-item active"> 
                                <a class="nav-link" href="#" data-toggle="collapse" data-target="#collapsePages" aria-expanded="true" aria-controls="collapsePages"> 
                                    <i class="fas fa-copy"></i> 
                                    <span>Pages</span>
                                 </a>
                                  <div id="collapsePages" class="collapse show" aria-labelledby="headingPages" data-parent="#accordionSidebar">
                                     <div class="bg-white py-2 collapse-inner rounded"> <a class="collapse-item" href="message.php">Home Page</a>
                                      <a class="collapse-item" href="News_Eve.php">News & Events</a> <a class="collapse-item" href="Edit_Staff.php">Faculty & Staff</a>
                                       <a class="collapse-item" href="Edit_Achieve.php">Achievements</a> <a class="collapse-item" href="chart_edit.php">Organizational Chart</a>
                                           <a class="collapse-item" href="Edit_Contact.php">Contact</a>
                                        <a class="collapse-item active" href="Edit_Gallery.php">Gallery</a> 
                                         <a class="collapse-item" href="Edit_History.php">History</a> 
                                    </div> </div>
                                     </li>
                                      <!-- Nav Item - User Management --> 
                                        <?php if ($user['id'] == 29 && $user['is_superadmin'] == 1): ?>
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
                                                <li class="nav-item"> 
                                                    <a class="nav-link" href="Manage_Students.php">
                                                         <i class="fas fa-user-graduate"></i> 
                                                         <span>Manage Students</span></a>
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

              <div class="container-fluid">
                <div class="d-flex justify-content-center mb-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-images" style="color: rgb(11, 104, 245); font-size: 1.3rem; margin-right: 0.8rem;"></i>
                        <h2 class="fw-bolder mb-0" style="color: rgb(11, 104, 245); font-size: 1.5rem; font-weight: 800;">Gallery Management</h2>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <!-- Create New Event -->
                        <div class="card mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center" style="background-color:rgba(42, 8, 213, 0.77);">
                                <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-camera mr-2"></i>Create New Gallery Event</h6>
                            </div>
                            <div class="card-body">
                                <form id="createGalleryForm" method="POST" enctype="multipart/form-data">
                                     <input type="hidden" name="form_action" value="create">
                                    <div class="form-group">
                                        <label>Event Title</label>
                                        <input type="text" name="title" class="form-control" placeholder="E.g., Nutrition Month 2024" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Event Visibility</label>
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" name="visibility" class="custom-control-input" id="visibilitySwitch" checked>
                                            <label class="custom-control-label" for="visibilitySwitch">Visible to public</label>
                                        </div>
                                        <small class="form-text text-muted">When disabled, this gallery won't be visible on the website</small>
                                    </div>

                                    <div class="form-group">
                                        <label>Upload Media</label>
                                        <input type="file" class="d-none" id="mediaInput" name="mediaFiles[]" multiple accept=".jpeg,.jpg,.png,.webp,.mp4">
                                        <div class="dropzone border rounded text-center d-flex flex-column justify-content-center align-items-center p-5" id="mediaDropzone" style="background-color:rgb(226, 228, 229); border-style: dashed !important; min-height: 420px;">
                                            <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                            <p class="mb-1">Click to browse images/videos here</p>
                                            <p class="small text-muted mb-1">Supports JPG,PNG,JPEG,WEBP,MP4 (Max: 25mb)</p>
                                            <p class="small text-muted m-0">Maximum of 12 files</p>
                                            <p class="small text-muted m-0"> Tip: Use a 4:3 image ratio for best display.</p>
                                            <div id="mediaPreview" class="d-flex flex-wrap mt-3"></div>
                                        </div>
                                    </div>

                                    <div class="form-group text-center">
                                        <button type="submit" name="submitGallery" id="createGallerySubmit" class="btn btn-primary px-4">
                                            <i class="fas fa-plus-circle" style="margin-right: 8px;"></i><span id="createGalleryText">Create Gallery Event</span>
                                        </button>
                                        <button type="button" class="btn btn-secondary px-4 ml-2 d-none" id="cancelButton"><i class="fas fa-times-circle mr-1"></i>Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Quick Stats -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3" style="background-color:rgba(97, 6, 200, 0.81);">
                                <h6 class="m-0 font-weight-bold text-white">Gallery Statistics</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <div class="text-center">
                                        <div style="background-color:rgba(238,237,237,0.85);border-radius:50%;width:50px;height:50px;display:flex;align-items:center;justify-content:center;margin:0 auto 8px auto;">
                                            <i class="fas fa-calendar-alt fa-lg mb-1" style="color:#ff8800;"></i>
                                        </div>
                                        <div class="h5 font-weight-bold"><?= $totalEvents ?></div>
                                        <div class="text-muted small">Total Events</div>
                                    </div>
                                    <div class="text-center">
                                        <div style="background-color:rgba(238,237,237,0.85);border-radius:50%;width:50px;height:50px;display:flex;align-items:center;justify-content:center;margin:0 auto 8px auto;">
                                            <i class="fas fa-image fa-lg mb-1" style="color:rgb(105,5,192);"></i>
                                        </div>
                                        <div class="h5 font-weight-bold"><?= $imageCount ?></div>
                                        <div class="text-muted small">Total Images</div>
                                    </div>
                                    <div class="text-center">
                                        <div style="background-color:rgba(238,237,237,0.85);border-radius:50%;width:50px;height:50px;display:flex;align-items:center;justify-content:center;margin:0 auto 8px auto;">
                                            <i class="fas fa-video fa-lg mb-1" style="color:rgb(218,29,29);"></i>
                                        </div>
                                        <div class="h5 font-weight-bold"><?= $videoCount ?></div>
                                        <div class="text-muted small">Total Videos</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Archived -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center" style="background-color:rgba(203,95,6,0.81);">
                                <h6 class="m-0 font-weight-bold text-white">Archived Gallery Events</h6>
                                <span class="badge badge-secondary"><?= count($archivedGalleries) ?> Events</span>
                            </div>
                            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                                <?php if (empty($archivedGalleries)): ?>
                                    <div class="text-center py-4"><i class="fas fa-archive fa-3x text-muted mb-3"></i><p class="text-muted">No archived gallery events</p></div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($archivedGalleries as $gallery):
                                            $created = new DateTime($gallery['created_at']);
                                            $objects = !empty($gallery['mediafiles']) ? array_filter(explode(',', $gallery['mediafiles'])) : [];
                                            $fileCount = count($objects);
                                        ?>
                                            <div class="list-group-item p-0 border-0 mb-3">
                                                <div class="card bg-light border-left-warning">
                                                    <div class="card-body position-relative">
                                                        <div class="archived-gallery-title">
                                                            <h7 class="font-weight-bold" title="<?= htmlspecialchars($gallery['title']) ?>"><?= htmlspecialchars($gallery['title']) ?></h7>
                                                            <span class="badge badge-warning archived-badge">Archived</span>
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="badge badge-info"><?= $fileCount ?> items</span>
                                                            <small class="text-muted">Created: <?= $created->format('M d, Y') ?></small>
                                                        </div>

                                                        <div class="d-flex justify-content-end mt-3 flex-wrap gap-2">
                                                            <button class="btn btn-sm btn-outline-success preview-btn mr-1 btn-mobile-sm"
                                                                data-title="<?= htmlspecialchars($gallery['title']) ?>"
                                                                data-files="<?= htmlspecialchars($gallery['mediafiles'] ?? '') ?>">
                                                                <i class="fas fa-eye mr-1"></i>Preview
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-primary restore-btn mr-1 btn-mobile-sm" data-id="<?= $gallery['gallery_id'] ?>">
                                                                <i class="fas fa-undo mr-1"></i>Restore
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger delete-btn btn-mobile-sm" data-id="<?= $gallery['gallery_id'] ?>">
                                                                <i class="fas fa-trash mr-1"></i>Delete
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Current Gallery Events -->
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center" style="background-color:rgba(42,8,213,0.77);">
                                <h6 class="m-0 font-weight-bold text-white">Current Gallery Events</h6>
                                <span class="badge badge-info"><?= count($activeGalleries) ?> Events</span>
                            </div>
                            <div class="card-body current-gallery-container">
                                <?php if (empty($activeGalleries)): ?>
                                    <div class="text-center py-4"><i class="fas fa-images fa-3x text-muted mb-3"></i><p class="text-muted">No active gallery events</p></div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($activeGalleries as $gallery):
                                            $objects = !empty($gallery['mediafiles']) ? array_filter(explode(',', $gallery['mediafiles'])) : [];
                                            $created = new DateTime($gallery['created_at']);
                                        ?>
                                            <div class="list-group-item p-0 border-0 mb-3">
                                                <div class="card border-top-primary shadow">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div class="current-gallery-title">
                                                                <h5 class="card-title mb-0"><?= htmlspecialchars($gallery['title']) ?></h5>
                                                            </div>
                                                            <div class="text-right">
                                                                <small class="text-muted gallery-date"><?= $created->format('M d, Y') ?></small>
                                                                <small class="text-muted gallery-date-mobile"><?= $created->format('m/d/y') ?></small>
                                                            </div>
                                                        </div>

                                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                                            <div>
                                                                <span class="badge badge-<?= $gallery['visibility'] == 'Yes' ? 'success' : 'secondary' ?> mr-2"><?= $gallery['visibility'] == 'Yes' ? 'Visible' : 'Hidden' ?></span>
                                                                <span class="badge badge-info"><?= count($objects) ?> items</span>
                                                            </div>
                                                        </div>

                                                        <?php if (!empty($objects)): ?>
                                                            <div class="row">
                                                                <?php foreach ($objects as $obj):
                                                                    $ext = strtolower(pathinfo($obj, PATHINFO_EXTENSION));
                                                                    $url = htmlspecialchars(getSupabaseUrl($obj, 'gallery_pic'));
                                                                ?>
                                                                    <div class="col-6 col-md-3 mb-3">
                                                                        <div class="position-relative">
                                                                            <?php if (in_array($ext, ['jpeg','jpg','png','webp'])): ?>
                                                                                <img src="<?= $url ?>" alt="Gallery image" class="gallery-media-item">
                                                                            <?php elseif ($ext === 'mp4'): ?>
                                                                                <video class="gallery-media-item" controls>
                                                                                    <source src="<?= $url ?>" type="video/mp4">
                                                                                    Your browser does not support the video tag.
                                                                                </video>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <div class="d-flex justify-content-end mt-3">
                                                            <a href="?edit_id=<?= $gallery['gallery_id'] ?>" class="btn btn-sm btn-outline-primary mr-2"><i class="fas fa-edit mr-1"></i>Edit</a>
                                                            <button class="btn btn-sm btn-outline-warning archive-btn" data-id="<?= $gallery['gallery_id'] ?>"><i class="fas fa-archive mr-1"></i>Archive</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div> <!-- container-fluid -->
        </div> <!-- content -->
               


<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 1rem;">
            <div class="modal-header" style="background-color: rgba(203,95,6,0.81); border-top-left-radius:1rem; border-top-right-radius:1rem;">
                <h5 id="modalGalleryTitle" class="modal-title font-weight-bold text-white w-100"></h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-3">
                <div id="modalGalleryImages" class="row"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>


<!-- Render edit modal server-side if $editData exists -->
<?php if ($editData): 
    $existingObjects = !empty($editData['mediafiles']) ? array_filter(explode(',', $editData['mediafiles'])) : [];
?>
    <div class="modal fade" id="editGalleryModal" tabindex="-1" role="dialog" aria-labelledby="editGalleryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background-color:rgba(42,8,213,0.77);">
                    <h5 class="modal-title text-white">Edit Gallery Event</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <form id="editGalleryForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="form_action" value="update">
                    <input type="hidden" name="gallery_id" value="<?= (int)$editData['gallery_id'] ?>">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="edit_title" class="form-control" value="<?= htmlspecialchars($editData['title']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Visibility</label>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" name="edit_visibility" class="custom-control-input" id="edit_visibilitySwitch" <?= $editData['visibility'] == 'Yes' ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="edit_visibilitySwitch">Visible to public</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Current Media</label>
                            <div id="existingMediaContainer" class="d-flex flex-wrap mb-3">
                                <?php foreach ($existingObjects as $obj):
                                    $ext = strtolower(pathinfo($obj, PATHINFO_EXTENSION));
                                    $url = htmlspecialchars(getSupabaseUrl($obj, 'gallery_pic'));
                                ?>
                                    <div class="media-preview-item">
                                        <?php if (in_array($ext, ['jpeg','jpg','png','webp'])): ?>
                                            <img src="<?= $url ?>" class="img-thumbnail" style="width:100px;height:100px;">
                                        <?php elseif ($ext === 'mp4'): ?>
                                            <video class="img-thumbnail" style="width:100px;height:100px;" poster="https://via.placeholder.com/100x100?text=Video"><source src="<?= $url ?>" type="video/mp4"></video>
                                        <?php endif; ?>
                                        <div class="remove-media" onclick="removeExistingMedia(this, '<?= htmlspecialchars($obj, ENT_QUOTES) ?>')">&times;</div>
                                        <input type="hidden" name="existing_files[]" value="<?= htmlspecialchars($obj, ENT_QUOTES) ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Upload New Media</label>
                            <input type="file" id="edit_mediaInput" name="edit_files[]" multiple accept=".jpeg,.jpg,.png,.webp,.mp4" class="form-control-file">
                            <div id="edit_mediaPreview" class="d-flex flex-wrap mt-3"></div>
                            <small class="form-text text-muted">Supports JPG,PNG,JPEG,WEBP,MP4 (Max: 25mb total)</small>
                            <small class="form-text text-muted">Maximum of 12 files total</small>
                            <small class="form-text text-danger" id="editSizeError"></small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="updateGallery" id="editGallerySubmit" class="btn btn-primary px-4">
                            <i class="fas fa-save mr-2"></i><span id="editGalleryText">Save Changes</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>


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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">


<script>
    const SUPABASE_GALLERY_PUBLIC_BASE = "<?= addslashes($supabaseGalleryPublicBase) ?>";
    const showEditModal = <?= $showEditModal ? 'true' : 'false' ?>;

    jQuery(function($){
        // Show edit modal only when requested explicitly via GET ?edit_id=...
        if (showEditModal) {
            $('#editGalleryModal').modal('show');
        }

        // Use event delegation so dynamically created elements (if any) are handled
        $(document).on('click', '.archive-btn', function(e) {
            e.preventDefault();
            const archiveId = $(this).data('id');
            Swal.fire({
                title: 'Archive Gallery Event?',
                html: '<div class="text-left"><p>This gallery event will be moved to the archived section.</p></div>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                confirmButtonText: 'Yes, archive it',
                cancelButtonColor: '#6c757d',
                cancelButtonText: 'Cancel',
                focusCancel: true
            }).then(result => {
                if (result.isConfirmed) {
                    window.location.href = '?archive_id=' + archiveId;
                }
            });
        });

        $(document).on('click', '.restore-btn', function(e) {
            e.preventDefault();
            const restoreId = $(this).data('id');
            Swal.fire({
                title: 'Restore Gallery Event?',
                text: 'This event will be moved back to the active section.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, restore it!'
            }).then(result => {
                if (result.isConfirmed) window.location.href = '?restore_id=' + restoreId;
            });
        });

        $(document).on('click', '.delete-btn', function(e) {
            e.preventDefault();
            const deleteId = $(this).data('id');
            Swal.fire({
                title: 'Permanently Delete?',
                html: '<div class="text-left"><p>This action will <strong class="text-danger">permanently delete</strong> the gallery event and cannot be undone.</p><p class="text-muted">All media associated with this event will be permanently deleted.</p></div>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete permanently',
                cancelButtonText: 'Cancel',
                focusCancel: true
            }).then(result => {
                if (result.isConfirmed) window.location.href = '?delete_id=' + deleteId;
            });
        });

        // Preview button
        $(document).on('click', '.preview-btn', function() {
            const title = $(this).data('title');
            const filesRaw = $(this).data('files') || '';
            const files = filesRaw ? filesRaw.split(',').map(s => s.trim()).filter(Boolean) : [];

            $('#modalGalleryTitle').text(title);
            const container = $('#modalGalleryImages');
            container.empty();

            files.forEach(obj => {
                const ext = obj.split('.').pop().toLowerCase();
                const url = SUPABASE_GALLERY_PUBLIC_BASE + encodeURIComponent(obj);
                const col = $('<div>').addClass('col-md-4 col-6 mb-3');

                if (['jpeg','jpg','png','webp'].includes(ext)) {
                    col.append($('<div>').addClass('card h-100 border-0').append(
                        $('<img>').attr('src', url).addClass('card-img-top img-fluid').css({'height':'150px','width':'100%','object-fit':'cover'})
                    ));
                } else if (ext === 'mp4') {
                    col.append($('<div>').addClass('card h-100 border-0').append(
                        $('<video>').attr('src', url).addClass('card-img-top img-fluid').attr('controls', true).css({'height':'150px','width':'100%','object-fit':'cover'})
                    ));
                }
                container.append(col);
            });

            $('#previewModal').modal('show');
        });

        // Create gallery upload handling (client-side previews)
        const mediaInput = document.getElementById('mediaInput');
        const mediaDropzone = document.getElementById('mediaDropzone');
        const mediaPreview = document.getElementById('mediaPreview');
        const cancelButton = document.getElementById('cancelButton');
        const createForm = document.getElementById('createGalleryForm');
        const titleInput = document.querySelector('input[name="title"]');

        let createSelectedFiles = [];
        let createTotalSize = 0;

        function checkForInput() {
            const hasTitle = titleInput && titleInput.value.trim().length > 0;
            const hasFiles = createSelectedFiles.length > 0;
            if (cancelButton) cancelButton.classList.toggle('d-none', !(hasTitle || hasFiles));
        }
        if (titleInput) titleInput.addEventListener('input', checkForInput);

        if (mediaDropzone) {
            mediaDropzone.addEventListener('click', (e) => {
                if (!e.target.closest('.remove-media') && mediaInput) mediaInput.click();
            });
        }

        if (mediaInput) {
            mediaInput.addEventListener('change', function(e) {
                const files = Array.from(e.target.files);
                let fileCount = createSelectedFiles.length;

                files.forEach(file => {
                    if (fileCount >= 12) {
                        Swal.fire({ icon: 'warning', title: 'Maximum Files Reached', text: 'You can only upload up to 12 files.', confirmButtonColor: '#3085d6' });
                        return;
                    }

                    const ext = file.name.split('.').pop().toLowerCase();
                    const allowed = ['jpeg','jpg','webp','png','mp4'];
                    if (!allowed.includes(ext)) {
                        Swal.fire({ icon: 'error', title: 'Invalid File Type', text: `File "${file.name}" is not a supported file type.`, confirmButtonColor: '#d33' });
                        return;
                    }

                    if ((createTotalSize + file.size) > 25 * 1024 * 1024) {
                        Swal.fire({ icon: 'error', title: 'File Too Large', text: `File "${file.name}" would exceed the 25MB total limit.`, confirmButtonColor: '#d33' });
                        return;
                    }

                    createTotalSize += file.size;
                    fileCount++;

                    const reader = new FileReader();
                    reader.onload = function(evt) {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'media-preview-item';

                        let mediaElement;
                        if (ext === 'mp4') {
                            const video = document.createElement('video');
                            video.src = evt.target.result;
                            video.muted = true;
                            video.classList.add('img-thumbnail');
                            video.style.width = '100px';
                            video.style.height = '100px';
                            mediaElement = video;
                        } else {
                            const img = document.createElement('img');
                            img.src = evt.target.result;
                            img.classList.add('img-thumbnail');
                            img.style.width = '100px';
                            img.style.height = '100px';
                            mediaElement = img;
                        }

                        const removeBtn = document.createElement('div');
                        removeBtn.className = 'remove-media';
                        removeBtn.innerHTML = '&times;';
                        removeBtn.onclick = function(ev) {
                            ev.stopPropagation();
                            createTotalSize -= file.size;
                            createSelectedFiles = createSelectedFiles.filter(f => !(f.name === file.name && f.size === file.size && f.lastModified === file.lastModified));
                            wrapper.remove();
                            updateCreateFileInput();
                            checkForInput();
                        };

                        wrapper.appendChild(mediaElement);
                        wrapper.appendChild(removeBtn);
                        mediaPreview.appendChild(wrapper);

                        createSelectedFiles.push(file);
                        updateCreateFileInput();
                        checkForInput();
                    };
                    reader.readAsDataURL(file);
                });

                this.value = '';
            });
        }

        function updateCreateFileInput() {
            if (!mediaInput) return;
            const dataTransfer = new DataTransfer();
            createSelectedFiles.forEach(f => dataTransfer.items.add(f));
            mediaInput.files = dataTransfer.files;
        }

        if (cancelButton) {
            cancelButton.addEventListener('click', function() {
                if (createForm) createForm.reset();
                createSelectedFiles = [];
                createTotalSize = 0;
                if (mediaPreview) mediaPreview.innerHTML = '';
                cancelButton.classList.add('d-none');
            });
        }

        if (createForm) {
            createForm.addEventListener('submit', function(e) {
                if (createSelectedFiles.length === 0) {
                    e.preventDefault();
                    Swal.fire({ icon: 'error', title: 'Media Required', text: 'You need to upload at least one media file!', confirmButtonColor: '#3085d6' });
                    return;
                }
                // loading state
                const btn = document.getElementById('createGallerySubmit');
                if (btn) {
                    btn.disabled = true;
                    const textSpan = document.getElementById('createGalleryText');
                    if (textSpan) textSpan.textContent = ' Creating...';
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating...';
                }
            });
        }

        // Edit upload handling
        const editMediaInput = document.getElementById('edit_mediaInput');
        const editMediaPreview = document.getElementById('edit_mediaPreview');
        let editSelectedFiles = [];
        let editTotalSize = 0;

        function updateEditFileInput() {
            if (!editMediaInput) return;
            const dataTransfer = new DataTransfer();
            editSelectedFiles.forEach(f => dataTransfer.items.add(f));
            editMediaInput.files = dataTransfer.files;
        }

        if (editMediaInput) {
            editMediaInput.addEventListener('change', function(e) {
                const files = Array.from(e.target.files);
                let existingCount = document.querySelectorAll('#existingMediaContainer .media-preview-item').length;
                existingCount = existingCount || 0;
                files.forEach(file => {
                    const ext = file.name.split('.').pop().toLowerCase();
                    const allowed = ['jpeg','jpg','webp','png','mp4'];
                    if (!allowed.includes(ext)) {
                        Swal.fire({ icon: 'error', title: 'Invalid File Type', text: `File "${file.name}" is not supported.`, confirmButtonColor: '#d33' });
                        return;
                    }

                    if ((editTotalSize + file.size) > 25 * 1024 * 1024) {
                        document.getElementById('editSizeError').textContent = `File "${file.name}" would exceed 25MB total limit.`;
                        return;
                    }

                    if ((existingCount + editSelectedFiles.length) >= 12) {
                        document.getElementById('editSizeError').textContent = 'Maximum of 12 files total is allowed.';
                        return;
                    }

                    editTotalSize += file.size;

                    const reader = new FileReader();
                    reader.onload = function(evt) {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'media-preview-item';

                        if (ext === 'mp4') {
                            const video = document.createElement('video');
                            video.src = evt.target.result;
                            video.muted = true;
                            video.classList.add('img-thumbnail');
                            video.style.width = '100px';
                            video.style.height = '100px';
                            wrapper.appendChild(video);
                        } else {
                            const img = document.createElement('img');
                            img.src = evt.target.result;
                            img.classList.add('img-thumbnail');
                            img.style.width = '100px';
                            img.style.height = '100px';
                            wrapper.appendChild(img);
                        }

                        const removeBtn = document.createElement('div');
                        removeBtn.className = 'remove-media';
                        removeBtn.innerHTML = '&times;';
                        removeBtn.onclick = function() {
                            editTotalSize -= file.size;
                            editSelectedFiles = editSelectedFiles.filter(f => !(f.name === file.name && f.size === file.size && f.lastModified === file.lastModified));
                            wrapper.remove();
                            updateEditFileInput();
                        };

                        wrapper.appendChild(removeBtn);
                        if (editMediaPreview) editMediaPreview.appendChild(wrapper);
                        editSelectedFiles.push(file);
                        updateEditFileInput();
                    };
                    reader.readAsDataURL(file);
                });

                this.value = '';
            });
        }

        const editForm = document.getElementById('editGalleryForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                const existingCount = document.querySelectorAll('#existingMediaContainer .media-preview-item').length;
                const newCount = editSelectedFiles.length;
                if (existingCount + newCount === 0) {
                    e.preventDefault();
                    Swal.fire({ icon: 'error', title: 'Media Required', text: 'Gallery must contain at least one media file!', confirmButtonColor: '#3085d6' });
                    return;
                }

                // loading state
                const btn = document.getElementById('editGallerySubmit');
                if (btn) {
                    btn.disabled = true;
                    const txt = document.getElementById('editGalleryText');
                    if (txt) txt.textContent = ' Saving...';
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
                }
            });
        }

        // remove existing media UI helper
        window.removeExistingMedia = function(element, objName) {
            const wrapper = element.closest('.media-preview-item');
            if (wrapper) wrapper.remove();
            // remove hidden input
            const inputs = document.querySelectorAll('input[name="existing_files[]"]');
            inputs.forEach(i => { if (i.value === objName) i.remove(); });
        };

        // Profile image preview and validation (kept same)
        $('#profileImageInput').change(function(event) {
            const file = event.target.files[0];
            const maxSize = 25 * 1024 * 1024;
            if (file) {
                if (file.size > maxSize) {
                    Swal.fire({ icon: 'error', title: 'File Too Large', text: 'The selected image exceeds 25MB. Please choose a smaller file.' });
                    event.target.value = '';
                    return;
                }
                const allowedTypes = ['image/jpeg','image/jpg','image/png','image/webp'];
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

        // toggle password visibility
        window.togglePassword = function(fieldId, icon) {
            const field = document.getElementById(fieldId);
            if (!field) return;
            const isHidden = field.type === 'password';
            field.type = isHidden ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !isHidden);
            icon.classList.toggle('fa-eye-slash', isHidden);            
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