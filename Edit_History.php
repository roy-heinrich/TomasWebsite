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

// Redirect if user is not an Admin
if ($_SESSION['user']['user_role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

require_once 'config.php'; // provides $conn (PDO) and Supabase helpers

// Helper: generate unique object name for Supabase (same approach as other pages)
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

// Initialize variables
$title = $par1 = $par2 = $par3 = '';
$existingImages = [];
$success = false;
$error = '';

// Fetch existing history page (history_id = 1) and its images using PDO (PostgreSQL)
try {
    $stmt = $conn->prepare("SELECT * FROM history_page WHERE history_id = 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $title = $row['title'];
        $par1 = $row['par1'];
        $par2 = $row['par2'];
        $par3 = $row['par3'];
    }

    $imgStmt = $conn->prepare("SELECT * FROM history_images WHERE history_id = 1 ORDER BY upload_date");
    $imgStmt->execute();
    $existingImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['history_title'] ?? '';
    $par1 = $_POST['paragraph1'] ?? '';
    $par2 = $_POST['paragraph2'] ?? '';
    $par3 = $_POST['paragraph3'] ?? '';

    try {
        // Begin transaction
        $conn->beginTransaction();

        // Upsert history_page row (ensure history_id = 1)
        $stmt = $conn->prepare("SELECT history_id FROM history_page WHERE history_id = 1");
        $stmt->execute();
        $exists = (bool)$stmt->fetchColumn();

        if ($exists) {
            $update = $conn->prepare("UPDATE history_page SET title = :title, par1 = :par1, par2 = :par2, par3 = :par3 WHERE history_id = 1");
            $update->execute([
                ':title' => $title,
                ':par1' => $par1,
                ':par2' => $par2,
                ':par3' => $par3
            ]);
        } else {
            $insert = $conn->prepare("INSERT INTO history_page (title, par1, par2, par3) VALUES (:title, :par1, :par2, :par3)");
            $insert->execute([
                ':title' => $title,
                ':par1' => $par1,
                ':par2' => $par2,
                ':par3' => $par3
            ]);
        }

        // Validate and prepare uploaded files (if any)
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $maxSingle = 25 * 1024 * 1024; // 25MB
        $maxTotal = 25 * 1024 * 1024; // 25MB total for new uploads

        $totalSize = 0;
        $uploadedFiles = []; // will hold arrays with tmp_name and original name

        if (isset($_FILES['history_images']) && is_array($_FILES['history_images']['tmp_name'])) {
            foreach ($_FILES['history_images']['tmp_name'] as $key => $tmpName) {
                if (!empty($tmpName) && is_uploaded_file($tmpName)) {
                    $fileSize = $_FILES['history_images']['size'][$key];
                    $fileType = $_FILES['history_images']['type'][$key];
                    $fileName = $_FILES['history_images']['name'][$key];

                    // Validate type
                    if (!in_array($fileType, $allowedTypes)) {
                        throw new Exception("Only JPG, JPEG, PNG, and WEBP files are allowed.");
                    }

                    // Validate individual size
                    if ($fileSize > $maxSingle) {
                        throw new Exception("One of the selected files exceeds the 25MB limit.");
                    }

                    $totalSize += $fileSize;
                    $uploadedFiles[] = [
                        'tmp_name' => $tmpName,
                        'name' => $fileName,
                        'size' => $fileSize,
                        'type' => $fileType
                    ];
                }
            }
        }

        // Validate total new upload size
        if ($totalSize > $maxTotal) {
            throw new Exception("Total size of uploaded images exceeds 25MB limit.");
        }

        // Process images marked for removal (remove_images is comma separated ids)
        if (!empty($_POST['remove_images'])) {
            $removeIds = array_filter(array_map('trim', explode(',', $_POST['remove_images'])));
            foreach ($removeIds as $id) {
                if (!is_numeric($id)) continue;
                // Get image_path
                $gStmt = $conn->prepare("SELECT image_path FROM history_images WHERE image_id = :id");
                $gStmt->execute([':id' => (int)$id]);
                $imgRow = $gStmt->fetch(PDO::FETCH_ASSOC);

                // Delete DB row
                $dStmt = $conn->prepare("DELETE FROM history_images WHERE image_id = :id");
                $dStmt->execute([':id' => (int)$id]);

                // Delete from Supabase bucket 'history_pic' if exists
                if (!empty($imgRow['image_path'])) {
                    deleteFromSupabase($imgRow['image_path'], 'history_pic');
                }
            }
        }

        // Upload new files to Supabase and insert rows
        foreach ($uploadedFiles as $file) {
            $objectName = generateObjectName($file['name']);
            // uploadToSupabase expects path to file (tmp_name) and bucket name
            $ok = uploadToSupabase($file['tmp_name'], $objectName, 'history_pic');
            if ($ok) {
                $ins = $conn->prepare("INSERT INTO history_images (history_id, image_path) VALUES (1, :path)");
                $ins->execute([':path' => $objectName]);
            } else {
                // If upload failed, rollback and throw
                throw new Exception("Failed to upload image: " . htmlspecialchars($file['name']));
            }
        }

        // Commit transaction
        $conn->commit();
        $success = true;
    } catch (Exception $e) {
        // Rollback if transaction active
        if ($conn->inTransaction()) $conn->rollBack();
        $error = "Error: " . $e->getMessage();
    }

    // Set session messages
    if ($success) {
        $_SESSION['success'] = "History page updated successfully!";
    } else {
        if (!empty($error)) $_SESSION['error'] = $error;
    }

    // Re-fetch existing images after update
    try {
        $imgStmt = $conn->prepare("SELECT * FROM history_images WHERE history_id = 1 ORDER BY upload_date");
        $imgStmt->execute();
        $existingImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // ignore, existingImages may remain as-is
    }

    // Redirect to avoid form re-submission
    header("Location: " . $_SERVER['PHP_SELF']);
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

    <title>Edit History</title>

    <!-- Custom fonts for this template-->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">
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
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --accent-color: #C4A484;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --border-radius: 0.35rem;
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f5f7fb;
            color: #333;
        }
        
        .card {
            border-radius: var(--border-radius);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border: none;
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: var(--light-color);
            border-bottom: 1px solid #e3e6f0;
            padding: 0.75rem 1.25rem;
            border-top-left-radius: var(--border-radius) !important;
            border-top-right-radius: var(--border-radius) !important;
        }
        
        .history-card {
            border-top: 8px solid var(--primary-color);
            border-radius: 1rem;
        }
        
        .history-title {
            font-weight: 900;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border-radius: var(--border-radius);
            border: 1px solid #d1d3e2;
            padding: 0.75rem;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .image-upload-section {
            background-color: #f8f9fc;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
       .image-preview-container {
    display: flex;
    gap: 15px;
    margin-top: 10px;
    flex-wrap: wrap;
    justify-content: center;
}
        
       .image-preview {
    position: relative;
    width: 120px;
    height: 120px;
    border: 2px dashed #d1d3e2;
    border-radius: var(--border-radius);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #fff;
    margin: 0 auto;
}
        
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        
        .preview-placeholder {
            color: #6e707e;
            font-size: 0.75rem;
            text-align: center;
            padding: 5px;
        }
        
        .remove-image {
            position: absolute;
            top: 3px;
            right: 3px;
            background: rgba(0,0,0,0.5);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            font-size: 0.7rem;
        }
        
      .file-input-wrapper {
    display: block;
    position: relative;
    width: 100%;
    max-width: 180px;
    margin-top: 10px;
}

.file-input-btn {
    display: block;
    width: 100%;
    text-align: center;
    background-color: var(--primary-color);
    color: white;
    padding: 6px 12px;
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: background-color 0.3s;
    font-size: 0.8rem;
}

.file-input-btn:hover {
    background-color: #2e59d9;
}

.file-input-wrapper input[type="file"] {
    position: absolute;
    left: 0;
    top: 0;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}
       .file-info {
    text-align: center;
    font-size: 0.75rem;
    margin-top: 5px;
    color: var(--secondary-color);
}
        
        .btn-save {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            font-weight: 700;
            padding: 8px 25px;
            border-radius: var(--border-radius);
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .btn-save:hover {
            background-color: #4161c4ff;
            border-color: #4161c4ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            color: white;
        }
        
        .validation-text {
            font-size: 0.8rem;
            color: var(--danger-color);
            margin-top: 5px;
        }
        
        .image-container {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 15px;
    justify-content: center;
}

.image-box {
    flex: 0 0 auto;
    min-width: 180px;
    max-width: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: #f8f9fc;
    padding: 10px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.image-box-header {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    width: 100%;
    justify-content: center;
}
        
        .image-box-header h5 {
            margin: 0;
            font-size: 0.9rem;
            color: var(--dark-color);
        }
        
        .image-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 25px;
            height: 25px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            margin-right: 8px;
            font-weight: bold;
            font-size: 0.8rem;
        }
        
        @media (max-width: 768px) {
    .image-container {
        flex-direction: column;
        align-items: center;
    }
    
    .image-box {
        width: 100%;
        max-width: 100%;
    }
}
       .btn-secondary {
    background-color: #6c757d;
    border-color: #6c757d;
    color: white;
    font-weight: 700;
    padding: 8px 25px;
    border-radius: var(--border-radius);
    transition: all 0.3s;
    font-size: 0.9rem;
}

.btn-secondary:hover {
    background-color: #5a6268;
    border-color: #545b62;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    color: white;
}

/* Button styles for mobile */
@media (max-width: 576px) {
    .d-md-flex.justify-content-md-center {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-save, .btn-secondary {
        width: 100%;
        margin-bottom: 8px;
    }
    
    .btn-secondary {
        margin-right: 0 !important;
    }
}

/* Smaller remove button for image boxes */
.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.7rem;
    line-height: 1.5;
    border-radius: 0.2rem;
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
                                        <a class="collapse-item" href="Edit_Gallery.php">Gallery</a>
                                        <a class="collapse-item active" href="Edit_History.php">History</a> 

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

              
       <div class="container py-5">
                    <div class="row justify-content-center">
                        <div class="col-lg-10">
                            <div class="card history-card">
                                <div class="card-body p-4">
                                    <h2 class="history-title">History Page</h2>

                                    <form id="historyForm" action="" method="POST" enctype="multipart/form-data">
                                        <div class="mb-4 mx-auto text-center" style="max-width:500px;">
                                            <label class="form-label w-100 text-center">Title</label>
                                            <input type="text" name="history_title" class="form-control text-center" placeholder="Enter title here" required value="<?= htmlspecialchars($title) ?>">
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label">Description</label>
                                            <textarea name="paragraph1" class="form-control" rows="15" required><?= htmlspecialchars($par1) ?></textarea>
                                        </div>

                                       

                                        <div class="mb-4">
                                            <label class="form-label">Upload Images</label>
                                            <div class="image-container" id="imageContainer">
                                                <?php foreach ($existingImages as $index => $image): ?>
                                                    <div class="image-box" data-image-id="<?= htmlspecialchars($image['image_id']) ?>">
                                                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                                                            <div class="image-number" style="background:var(--primary-color); color:#fff; width:28px; height:28px; display:flex; align-items:center; justify-content:center; border-radius:50%; font-weight:700;"><?= $index + 1 ?></div>
                                                            <h5 style="margin:0; font-size:0.95rem;">Image <?= $index + 1 ?></h5>
                                                        </div>
                                                        <div style="position:relative;">
                                                            <div class="image-preview">
                                                                <img src="<?= htmlspecialchars(getSupabaseUrl($image['image_path'], 'history_pic')) ?>" alt="History Image <?= $index + 1 ?>">
                                                            </div>
                                                            <div class="remove-image" onclick="removeExistingImage(this, <?= (int)$image['image_id'] ?>)" title="Remove"><i class="fas fa-times"></i></div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>

                                                <!-- Template for new image uploads -->
                                                <div class="image-box" id="newImageTemplate" style="display:none;">
                                                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                                                        <div class="image-number" style="background:var(--primary-color); color:#fff; width:28px; height:28px; display:flex; align-items:center; justify-content:center; border-radius:50%; font-weight:700;"></div>
                                                        <h5 style="margin:0; font-size:0.95rem;">New Image</h5>
                                                    </div>
                                                    <div class="image-preview" style="height:120px; width:160px;">
                                                        <div class="preview-placeholder" style="font-size:0.8rem; color:#6e707e; text-align:center; padding:8px;">No image selected</div>
                                                    </div>
                                                    <div class="d-flex justify-content-center mt-2" style="width:100%;">
                                                        <div class="file-input-wrapper">
                                                            <div class="file-input-btn"><i class="fas fa-upload mr-2"></i>Choose Image</div>
                                                            <input type="file" name="history_images[]" accept=".jpg,.jpeg,.png,.webp" onchange="previewNewImage(this)">
                                                        </div>
                                                    </div>
                                                    <div class="text-center mt-2">
                                                        <button type="button" class="btn btn-sm btn-danger" onclick="removeImageBox(this)"><i class="fas fa-trash mr-1"></i> Remove</button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="text-center mt-3">
                                                <button type="button" class="btn btn-primary" onclick="addImageBox()"><i class="fas fa-plus mr-1"></i> Add Another Image</button>
                                                <div class="file-info mt-2">
                                                    <div>Max size (total new uploads): 25MB</div>
                                                    <div>Formats: JPG, JPEG, PNG, WEBP</div>
                                                </div>
                                            </div>

                                            <div class="validation-text text-center" id="imageValidation"></div>
                                        </div>

                                        <input type="hidden" name="remove_images" id="removeImages" value="">

                                        <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                                            <button type="button" class="btn btn-secondary px-md-5 mr-md-3" onclick="resetForm()"><i class="fas fa-times mr-2"></i>Cancel</button>
                                            <button type="submit" id="saveHistoryBtn" class="btn btn-save px-md-5">
                                                <i class="fas fa-save mr-2"></i><span class="btn-text">Save Changes</span>
                                               <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                            </button>
                                        </div>
                                    </form>

                                </div>
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
                <button class="btn btn-dark" type="button" data-dismiss="modal">Cancel</button>
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
              <label class="text-muted mb-1"><strong>Profile Image</strong></label>
              <input type="file" name="profile_image" class="form-control-file" id="profileImageInput" accept=".jpg,.jpeg,.png,.webp">
            </div>
          </div>

          <!-- Profile Fields -->
          <div class="form-group">
            <label class="text-muted mb-1"><strong>Full Name</strong></label>
            <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname']) ?>" required>
          </div>

          <div class="form-group">
            <label class="text-muted mb-1"><strong>Username</strong></label>
            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
          </div>

          <div class="form-group">
            <label class="text-muted mb-1"><strong>Email</strong></label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
          </div>

          <hr>
          <h6 class="text-muted mb-4"><strong>Change Password (Optional)</strong></h6>

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
          <button type="button" class="btn btn-dark" data-dismiss="modal">Cancel</button>
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


<script>
        // Reusable helpers from page
        function showError(message) {
            Swal.fire({ icon: 'error', title: 'Validation Error', text: message, confirmButtonColor: '#d33' });
        }

        // Reset form (confirm)
        function resetForm() {
            Swal.fire({
                title: 'Are you sure?',
                text: "You'll lose all unsaved changes.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, reset changes',
                cancelButtonText: 'No, keep editing'
            }).then((result) => {
                if (result.isConfirmed) {
                    location.reload();
                }
            });
        }

        // Add new image upload box
        function addImageBox() {
            const container = document.getElementById('imageContainer');
            const template = document.getElementById('newImageTemplate');
            if (!template) return;
            const newBox = template.cloneNode(true);
            newBox.id = ''; // remove duplicate id
            newBox.style.display = 'flex';
            // update number — exclude the hidden template when counting
            const count = container.querySelectorAll('.image-box:not(#newImageTemplate)').length + 1;
            const numberEl = newBox.querySelector('.image-number');
            if (numberEl) numberEl.textContent = count;
            // Set header text
            const header = newBox.querySelector('h5');
            if (header) header.textContent = 'Image ' + count;
            // clear any previous preview placeholder and file input
            const preview = newBox.querySelector('.image-preview');
            if (preview) preview.innerHTML = '<div class="preview-placeholder" style="font-size:0.8rem; color:#6e707e; text-align:center; padding:8px;">No image selected</div>';
            const fileInput = newBox.querySelector('input[type="file"]');
            if (fileInput) {
                fileInput.value = '';
                // ensure onchange remains attached (cloned from template so it should)
            }
            container.appendChild(newBox);
            updateImageNumbers();
        }

        // Remove newly added image box
        function removeImageBox(button) {
            const box = button.closest('.image-box');
            if (box) box.remove();
            updateImageNumbers();
        }

        // Preview selected file for a new box
        function previewNewImage(input) {
            const file = input.files[0];
            const box = input.closest('.image-box');
            const previewContainer = box.querySelector('.image-preview');

            if (file) {
                // Validate size
                if (file.size > 25 * 1024 * 1024) {
                    showError("File size exceeds 25MB limit.");
                    input.value = '';
                    return;
                }
                // Validate type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    showError("Only JPG, JPEG, PNG, and WEBP files are allowed.");
                    input.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewContainer.innerHTML = '';
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = 'Preview';
                    previewContainer.appendChild(img);
                };
                reader.readAsDataURL(file);
                document.getElementById('imageValidation').textContent = '';
                updateImageNumbers();
            }
        }

        // Remove existing image (marks for deletion)
        function removeExistingImage(button, imageId) {
            const box = button.closest('.image-box');
            const removeInput = document.getElementById('removeImages');
            const current = removeInput.value ? removeInput.value.split(',').filter(Boolean) : [];
            current.push(imageId);
            removeInput.value = current.join(',');
            if (box) box.remove();
            updateImageNumbers();
        }

        // Update numbering of image boxes — exclude the hidden template
        function updateImageNumbers() {
            const boxes = Array.from(document.querySelectorAll('.image-box:not(#newImageTemplate)'));
            boxes.forEach((box, i) => {
                const num = box.querySelector('.image-number');
                if (num) num.textContent = i + 1;
                const header = box.querySelector('h5');
                if (header) header.textContent = 'Image ' + (i + 1);
            });
        }

        // Form validation before submit
        document.getElementById('historyForm').addEventListener('submit', function(e) {
            const fileInputs = Array.from(document.querySelectorAll('#imageContainer input[type="file"]'));
            const existingBoxes = document.querySelectorAll('.image-box[data-image-id]');
            const removeInput = document.getElementById('removeImages');
            const removed = removeInput.value ? removeInput.value.split(',').filter(Boolean) : [];
            const remainingExisting = Math.max(0, existingBoxes.length - removed.length);

            let hasFiles = false;
            let totalSize = 0;

            fileInputs.forEach(input => {
                if (input.files && input.files.length > 0) {
                    hasFiles = true;
                    totalSize += input.files[0].size;
                }
            });

            // Require at least one image (existing or new)
            if (remainingExisting === 0 && !hasFiles) {
                e.preventDefault();
                document.getElementById('imageValidation').textContent = "Error: At least one image is required for the history page.";
                showError("At least one image is required for the history page.");
                return false;
            }

            if (totalSize > 25 * 1024 * 1024) {
                e.preventDefault();
                document.getElementById('imageValidation').textContent = "Error: Total size of all new images exceeds 25MB limit.";
                showError("Total size of all new images exceeds 25MB limit.");
                return false;
            }

            // Show loading state for Save Changes button
            const btn = document.getElementById('saveHistoryBtn');
            const spinner = btn.querySelector('.spinner-border');
            const text = btn.querySelector('.btn-text');
            if (btn) {
                btn.disabled = true;
                if (text) text.style.display = 'none';
                if (spinner) spinner.style.display = 'inline-block';
            }
            return true;
        });

        // Profile image preview validations
        $('#profileImageInput').change(function(event) {
            const file = event.target.files[0];
            const maxSize = 25 * 1024 * 1024;
            if (!file) return;
            if (file.size > maxSize) {
                Swal.fire({ icon:'error', title:'File Too Large', text:'The selected image exceeds 25MB. Please choose a smaller file.', confirmButtonColor:'#d33' });
                event.target.value = '';
                return;
            }
            const allowed = ['image/jpeg','image/jpg','image/png','image/webp'];
            if (!allowed.includes(file.type)) {
                Swal.fire({ icon:'error', title:'Invalid File Type', text:'Only JPG, JPEG, PNG, and WEBP are allowed.', confirmButtonColor:'#d33' });
                event.target.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) { document.getElementById('previewImagee').src = e.target.result; };
            reader.readAsDataURL(file);
        });

        // Toggle password view
        function togglePassword(fieldId, icon) {
            const field = document.getElementById(fieldId);
            const isHidden = field.type === 'password';
            field.type = isHidden ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !isHidden);
            icon.classList.toggle('fa-eye-slash', isHidden);
        }

        // On DOM ready, ensure numbering is correct and hide spinner initially.
        document.addEventListener('DOMContentLoaded', function() {
            updateImageNumbers();
            const spinner = document.querySelector('#saveHistoryBtn .spinner-border');
            const text = document.querySelector('#saveHistoryBtn .btn-text');
            if (spinner) spinner.style.display = 'none';
            if (text) text.style.display = 'inline-block';
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