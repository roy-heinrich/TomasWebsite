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

require_once 'config.php'; // Provides $conn (PDO) and Supabase helpers

// Helper to generate a unique filename (same as News_Eve.php)
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

// Handle organizational chart image upload (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['org_chart_image'])) {
    $file = $_FILES['org_chart_image'];

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        $_SESSION['error'] = 'Please select an image to update';
        header('Location: chart_edit.php');
        exit;
    }

    $allowed = ['image/jpeg','image/jpg','image/png','image/webp'];
    $maxSize = 25 * 1024 * 1024; // 25MB

    $fileName = $file['name'];
    $fileTmp = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    $fileType = $file['type'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Validate file type
    if (!in_array($fileType, $allowed)) {
        $_SESSION['error'] = 'Invalid file type. Only JPG, JPEG, PNG, and WEBP are allowed.';
        header('Location: chart_edit.php');
        exit;
    }

    // Validate file size
    if ($fileSize > $maxSize) {
        $_SESSION['error'] = 'File size exceeds 25MB.';
        header('Location: chart_edit.php');
        exit;
    }

    // Validate upload errors
    if ($fileError !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'File upload error.';
        header('Location: chart_edit.php');
        exit;
    }

    // Check if file is actually an image
    $check = getimagesize($fileTmp);
    if ($check === false) {
        $_SESSION['error'] = 'File is not an image.';
        header('Location: chart_edit.php');
        exit;
    }

    // Generate unique filename for Supabase
    $objectName = generateObjectName($fileName);

    // Upload to Supabase bucket 'organizational_chart'
    $uploaded = uploadToSupabase($fileTmp, $objectName, 'organizational_chart');

    if ($uploaded) {
        // Fetch current image from DB (PostgreSQL)
        try {
            $stmt = $conn->prepare("SELECT * FROM organizational_chart LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $oldImage = $row['image_path'];
                // Delete old image from Supabase if exists
                if (!empty($oldImage)) {
                    deleteFromSupabase($oldImage, 'organizational_chart');
                }
                // Update record
                $updateQuery = "UPDATE organizational_chart SET image_path = :image_path, uploaded_at = NOW() WHERE id = :id";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->execute([
                    ':image_path' => $objectName,
                    ':id' => $row['id']
                ]);
            } else {
                // Insert new record
                $insertQuery = "INSERT INTO organizational_chart (image_path) VALUES (:image_path)";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->execute([':image_path' => $objectName]);
            }
            $_SESSION['success'] = 'Organizational chart updated successfully!';
        } catch (PDOException $e) {
            // If DB fails, try to delete the uploaded image to avoid orphaned files
            deleteFromSupabase($objectName, 'organizational_chart');
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'Failed to upload image to storage.';
    }

    header('Location: chart_edit.php');
    exit;
}

// Fetch current organizational chart image (from Supabase)
$currentImage = null;
try {
    $stmt = $conn->prepare("SELECT image_path FROM organizational_chart LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['image_path'])) {
        $currentImage = getSupabaseUrl($row['image_path'], 'organizational_chart');
    }
} catch (PDOException $e) {
    $currentImage = null;
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

    <title>Organizational Chart Management</title>

    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
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

 /* Organizational Chart Styles */
    .chart-container {
        max-width: 800px;
        margin: 0 auto;
    }
    
    .chart-preview {
        width: 100%;
        height: 300px;
        overflow: hidden;
        border: 1px solid #ddd;
        border-radius: 5px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f9f9f9;
    }
    
    .chart-preview img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    
    .file-upload-wrapper {
        position: relative;
        margin-bottom: 20px;
    }
    
    .file-upload-label {
        display: block;
        padding: 10px 15px;
        background: #4e73df;
        color: white;
        border-radius: 5px;
        cursor: pointer;
        text-align: center;
        transition: background-color 0.3s;
    }
    
    .file-upload-label:hover {
        background-color: #2e59d9;
    }
    
    .file-info {
        margin-top: 10px;
        font-size: 0.9rem;
        color: #6c757d;
    }
    
    /* Mobile Responsiveness */
    @media (max-width: 576px) {
        .chart-preview {
            height: 200px;
        }
        
        .btn-upload {
            width: 100%;
        }
    }

     /* Add this for cancel button spacing */
        .button-group {
            display: flex;
            justify-content: center;
            gap: 10px;
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
                                       <a class="collapse-item" href="Edit_Achieve.php">Achievements</a> <a class="collapse-item active" href="chart_edit.php">Organizational Chart</a>
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
            <div class="d-sm-flex align-items-center justify-content-center mb-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-sitemap" style="color: rgb(11, 104, 245); font-size: 1.3rem; margin-right: 0.8rem;"></i>
                    <h2 class="fw-bolder mb-0" style="color: rgb(11, 104, 245); font-size: 1.5rem; font-weight: 800;">Organizational Chart</h2>
                </div>
            </div>

            <!-- Organizational Chart Section -->
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 text-white" style="background-color: #4231ddff;">
                            <h6 class="m-0 font-weight-bold">Organizational Chart Management</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <!-- Current Image Preview -->
                                <div class="chart-preview">
                                    <?php if ($currentImage): ?>
                                        <img id="currentChartImage" src="<?= htmlspecialchars($currentImage) ?>" alt="Current Organizational Chart">
                                    <?php else: ?>
                                        <p class="text-center text-muted">No organizational chart uploaded yet</p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Upload Form -->
                                <form method="POST" enctype="multipart/form-data" id="orgChartForm">
                                    <div class="file-upload-wrapper">
                                        <input type="file" name="org_chart_image" id="orgChartImage" accept=".jpg,.jpeg,.png,.webp" class="d-none">
                                        <label for="orgChartImage" class="file-upload-label">
                                            <i class="fas fa-upload mr-2"></i>Choose Image
                                        </label>
                                        <div class="file-info" id="fileInfo">
                                            Selected file: None (Max 25MB, JPG/JPEG/PNG/WEBP only)
                                        </div>
                                        <small class="text-muted d-block text-start mt-1">
                                            <i class="fas fa-lightbulb mr-1"></i> Tip: Use a wide image with a short height (ex. 4:3 ratio) for optimal display.
                                        </small>
                                    </div>
                                    <div class="button-group text-center mt-3">
                                        <button type="submit" class="btn btn-primary btn-upload" id="updateBtn">
                                            <span id="updateBtnSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                            <span id="updateBtnText"><i class="fas fa-sync-alt mr-2"></i>Update</span>
                                        </button>
                                        <button type="button" id="cancelBtn" class="btn btn-secondary btn-upload" style="display: none;">
                                            <i class="fas fa-times mr-2"></i>Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
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
    // Profile image upload validation (for profile modal)
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
        // Store original state
        const originalPreview = document.querySelector('.chart-preview').innerHTML;
        const originalFileInfo = document.getElementById('fileInfo').innerHTML;
        const fileInput = document.getElementById('orgChartImage');
        const cancelBtn = document.getElementById('cancelBtn');
        const updateBtn = document.getElementById('updateBtn');
        const updateBtnSpinner = document.getElementById('updateBtnSpinner');
        const updateBtnText = document.getElementById('updateBtnText');
        const orgChartForm = document.getElementById('orgChartForm');

        // Show cancel button when new file is selected
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                cancelBtn.style.display = 'inline-block';
            }
        });

        // Cancel button functionality
        cancelBtn.addEventListener('click', function() {
            // Reset file input
            fileInput.value = '';
            // Reset preview to original
            document.querySelector('.chart-preview').innerHTML = originalPreview;
            document.getElementById('fileInfo').innerHTML = originalFileInfo;
            // Hide cancel button
            this.style.display = 'none';
        });

        // Hide cancel button on form submit
        orgChartForm.addEventListener('submit', function() {
            cancelBtn.style.display = 'none';
        });

        // File input handling
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const maxSize = 25 * 1024 * 1024; // 25MB
            const fileInfo = document.getElementById('fileInfo');
            
            if (file) {
                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    fileInfo.innerHTML = '<span class="text-danger">Invalid file type. Only JPG, JPEG, PNG, and WEBP are allowed.</span>';
                    e.target.value = '';
                    return;
                }
                
                // Validate file size
                if (file.size > maxSize) {
                    fileInfo.innerHTML = '<span class="text-danger">File size exceeds 25MB.</span>';
                    e.target.value = '';
                    return;
                }
                
                // Update file info
                fileInfo.innerHTML = `Selected file: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)}MB)`;
                
                // Preview image
                const reader = new FileReader();
                reader.onload = function(event) {
                    const preview = document.querySelector('.chart-preview');
                    preview.innerHTML = `<img src="${event.target.result}" alt="Preview" class="img-fluid" style="max-height: 300px;">`;
                }
                reader.readAsDataURL(file);
            } else {
                fileInfo.innerHTML = 'Selected file: None (Max 25MB, JPG/JPEG/PNG/WEBP only)';
            }
        });

        // Loading state for update button
        orgChartForm.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('orgChartImage');
            if (!fileInput.files.length) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'Please select an image to update',
                    confirmButtonColor: '#d33',
                });
                return false;
            }
            // Show loading spinner and disable button
            updateBtnSpinner.classList.remove('d-none');
            updateBtnText.textContent = 'Updating...';
            updateBtn.disabled = true;
        });
    });
</script>


    <?php include 'search/Search_Admin.php'; ?>
</body>

</html>                                   