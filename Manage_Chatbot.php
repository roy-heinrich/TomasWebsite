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

require_once 'config.php'; // provides PDO $conn and Supabase helper functions

// Helper to generate a unique filename/object name for storage
function generateObjectName($originalName) {
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    try {
        $rand = bin2hex(random_bytes(6));
    } catch (Exception $e) {
        $rand = substr(md5(uniqid('', true)), 0, 12);
    }
    return time() . '_' . $rand . ($ext ? '.' . $ext : '');
}

// Allowed file types for knowledge base
$allowedExtensions = ['txt', 'doc', 'docx', 'pdf', 'xls', 'xlsx', 'ppt', 'pptx'];
$allowedMimeTypes = [
    'text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/pdf',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation'
];

// Handle form submissions (PDO)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Add new chatbot prompt
    if (isset($_POST['add_prompt'])) {
        $keywords = trim($_POST['keywords'] ?? '');
        $response = trim($_POST['response'] ?? '');

        try {
            $stmt = $chatbot_conn->prepare("INSERT INTO chatbot_prompts (keywords, response, created_at, updated_at) VALUES (:keywords, :response, NOW(), NOW())");
            $stmt->execute([
                ':keywords' => $keywords,
                ':response' => $response
            ]);
            $_SESSION['success'] = "Prompt added successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding prompt: " . $e->getMessage();
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "#prompts");
        exit();
    }

    // Delete prompt
    if (isset($_POST['delete_prompt'])) {
        $id = (int)($_POST['prompt_id'] ?? 0);
        try {
            $stmt = $chatbot_conn->prepare("DELETE FROM chatbot_prompts WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $_SESSION['success'] = "Prompt deleted successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error deleting prompt: " . $e->getMessage();
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "#existing");
        exit();
    }

    // Update prompt
    if (isset($_POST['update_prompt'])) {
        $id = (int)($_POST['prompt_id'] ?? 0);
        $keywords = trim($_POST['keywords'] ?? '');
        $response = trim($_POST['response'] ?? '');

        try {
            $stmt = $chatbot_conn->prepare("UPDATE chatbot_prompts SET keywords = :keywords, response = :response, updated_at = NOW() WHERE id = :id");
            $stmt->execute([
                ':keywords' => $keywords,
                ':response' => $response,
                ':id' => $id
            ]);
            $_SESSION['success'] = "Prompt updated successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating prompt: " . $e->getMessage();
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "#existing");
        exit();
    }

    // Knowledge base (file upload) feature removed per request. No file upload handling here.

    // Knowledge base (file delete) feature removed per request.
}

// Fetch all prompts (most recent first)
try {
    $promptsStmt = $chatbot_conn->query("SELECT * FROM chatbot_prompts ORDER BY id DESC");
    $prompts_result = $promptsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching prompts: " . $e->getMessage();
    $prompts_result = [];
}

// Knowledge base (uploaded_files) removed — we no longer fetch uploaded files here.

// Format file size function
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return sprintf("%.2f", $bytes / pow($k, $i)) . ' ' . $sizes[$i];
}

// Compute profile image URL (uses getSupabaseUrl from config.php)
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

    <title>Chatbot Management</title>

    <!-- Custom fonts for this template-->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.dataTables.min.css">
    
    <style>
        /* Fix for overlapping sidebar collapse items */
        @media (min-width: 992px) {
            body.sidebar-toggled .sidebar {
                z-index: 1050 !important;
            }
            #collapsePages, #collapseAttendance {
                z-index: 1051 !important;
            }
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
                width: 224px;
            }
            #content-wrapper {
                margin-left: 224px;
            }
            body.sidebar-toggled #content-wrapper {
                margin-left: 105px;
            }
        }

        .sidebar.toggled #adminProfile .d-md-block,
        .sidebar.toggled #sidebarTitle,
        .sidebar.toggled hr.sidebar-divider {
            display: none !important;
        }
        #adminProfile .text-white {
            color: white !important;
        }
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
        .sidebar.toggled #adminProfile {
            padding-top: 2rem !important;
        }
        @media (max-width: 767.98px) {
            #adminProfile {
                padding-top: 1.5rem !important;
            }
            #adminProfile img {
                width: 50px;
                height: 50px;
            }
        }
        .nav-item:not(.no-arrow):hover,
        .nav-item:not(.no-arrow):hover .nav-link {
            background-color:rgba(68, 48, 248, 0.28) !important;
            color: white !important;
            border-radius: 0.35rem;
        }
        .nav-item:not(.no-arrow):hover .nav-link i,
        .nav-item:not(.no-arrow):hover .nav-link span {
            color: white !important;
        } 
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 20px;
            font-weight: 600;
            font-size: 1.2rem;
            border-radius: 10px 10px 0 0 !important;
            color: #4f46e5;
        }
        .section-title {
            border-left: 4px solid #4f46e5;
            padding-left: 15px;
            margin-bottom: 25px;
            font-weight: 600;
            color: #1f2937;
        }
        .stats-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background: white;
        /*    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05); */
            box-shadow: 0 5px 12px rgba(0, 0, 0, 0.10);
        }
        .stats-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #4f46e5;
        }
        .stats-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
        }
        .stats-card .label {
            color: #6b7280;
            font-size: 0.9rem;
        }
        .tab-content {
            padding: 0;
            border: none;
        }
        .nav-tabs .nav-link {
            border: none;
            color: #6b7280;
            padding: 15px 25px;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            color: #4f46e5;
            border-bottom: 3px solid #4f46e5;
            background: transparent;
        }
        .nav-tabs {
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 25px;
        }
        .tab-pane {
            padding: 25px;
            background: white;
            border-radius: 0 0 10px 10px;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #cbd5e1;
        }
        .reload-btn {
            background: white;
            border-radius: 30px;
            padding: 8px 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: none;
            outline: none;
        }
        .reload-btn:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .reload-btn .reload-icon {
            font-size: 18px;
            color: #4f46e5;
        }
        .form-control:focus, .custom-select:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.25);
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.3em 0.8em;
            margin-left: 2px;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #4f46e5;
            color: white !important;
            border: 1px solid #4f46e5;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #e9ecef;
            border: 1px solid #dee2e6;
        }
        
        /* Enhanced table styling for mobile responsiveness */
        .table-container {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Center action buttons */
        .action-buttons {
            text-align: center;
            white-space: nowrap;
        }
        
        .action-buttons .btn {
            padding: 5px 10px;
            margin: 0 3px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Adjust column widths for prompts table */
        #promptsTable th:nth-child(1), /* Keywords column */
        #promptsTable td:nth-child(1) {
            width: 20%;
            min-width: 120px;
        }
        
        #promptsTable th:nth-child(2), /* Response column */
        #promptsTable td:nth-child(2) {
            width: 60%;
            min-width: 250px;
        }
        
        #promptsTable th:nth-child(3), /* Actions column */
        #promptsTable td:nth-child(3) {
            width: 20%;
            min-width: 100px;
        }
        
        /* Adjust column widths for files table */
        #filesTable th:nth-child(1), /* File Name column */
        #filesTable td:nth-child(1) {
            width: 30%;
            min-width: 150px;
        }
        
        #filesTable th:nth-child(2), /* Type column */
        #filesTable td:nth-child(2) {
            width: 10%;
            min-width: 80px;
        }
        
        #filesTable th:nth-child(3), /* Size column */
        #filesTable td:nth-child(3) {
            width: 10%;
            min-width: 80px;
        }
        
        #filesTable th:nth-child(4), /* Uploaded At column */
        #filesTable td:nth-child(4) {
            width: 30%;
            min-width: 150px;
        }
        
        #filesTable th:nth-child(5), /* Actions column */
        #filesTable td:nth-child(5) {
            width: 20%;
            min-width: 100px;
        }
        
        /* Ensure tables are properly responsive */
        @media (max-width: 768px) {
            .table-container {
                border: 1px solid #e3e6f0;
                border-radius: 0.35rem;
            }
            
            /* Make sure action buttons remain visible */
            .action-buttons {
                display: flex;
                justify-content: center;
            }
            
            /* Adjust column widths on smaller screens */
            #promptsTable th:nth-child(1),
            #promptsTable td:nth-child(1) {
                width: 25%;
                min-width: 100px;
            }
            
            #promptsTable th:nth-child(2),
            #promptsTable td:nth-child(2) {
                width: 50%;
                min-width: 200px;
            }
            
            #promptsTable th:nth-child(3),
            #promptsTable td:nth-child(3) {
                width: 25%;
                min-width: 90px;
            }
            
            #filesTable th:nth-child(1),
            #filesTable td:nth-child(1) {
                width: 35%;
                min-width: 120px;
            }
            
            #filesTable th:nth-child(4),
            #filesTable td:nth-child(4) {
                width: 25%;
                min-width: 120px;
            }
            
            #filesTable th:nth-child(5),
            #filesTable td:nth-child(5) {
                width: 20%;
                min-width: 80px;
            }
        }
        
        /* Style for DataTables elements */
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #d1d3e2;
            border-radius: 0.35rem;
            padding: 0.375rem 0.75rem;
        }
        
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #d1d3e2;
            border-radius: 0.35rem;
            padding: 0.375rem 1.75rem 0.375rem 0.75rem;
        }
        
        /* Ensure proper spacing for DataTables info */
        .dataTables_info {
            padding-top: 0.85em;
            white-space: nowrap;
        }

         /* Small adjustments for spinners */
        .btn .spinner-border { margin-right: 6px; }
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
                                     <div class="bg-white py-2 collapse-inner rounded"> <a class="collapse-item " href="message.php">Home Page</a>
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
                                               <li class="nav-item active">
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
                            <i class="fas fa-robot" style="color: #0b68f5; font-size: 1.3rem; margin-right: 0.8rem;"></i>
                            <h2 class="fw-bolder mb-0" style="color: #0b68f5; font-size: 1.5rem; font-weight: 800;">Chatbot Management</h2>
                        </div>
                    </div>

                    <!-- Reload sources removed -->

                    <!-- Stats Overview -->
                    <div class="row mb-4 justify-content-center">
                        <div class="col-md-5 mb-4">
                            <div class="stats-card">
                                <i class="fas fa-key"></i>
                                <div class="number">
                                    <?php 
                                        try {
                                            $r = $chatbot_conn->query("SELECT COUNT(*) as count FROM chatbot_prompts");
                                            echo $r->fetchColumn();
                                        } catch (Exception $e) {
                                            echo "0";
                                        }
                                    ?>
                                </div>
                                <div class="label">Keyword Prompts</div>
                            </div>
                        </div>
                        <!-- Uploaded files stat removed -->                 

                    </div>

                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs" id="managementTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="prompts-tab" data-toggle="tab" href="#prompts" role="tab">
                                <i class="fas fa-plus-circle mr-2"></i>Add New Prompt
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="existing-tab" data-toggle="tab" href="#existing" role="tab">
                                <i class="fas fa-list mr-2"></i>Existing Prompts
                            </a>
                        </li>
                        <!-- Knowledge Base tab removed -->
                    </ul>

                    <div class="tab-content" id="managementTabsContent">
                       <!-- Add New Prompt Tab -->
<div class="tab-pane fade shadow-lg mb-4 show active" id="prompts" role="tabpanel" aria-labelledby="prompts-tab">
    <h3 class="section-title">Add New Chatbot Prompt</h3>

 <!-- Chatbot Instructions -->
    <div class="card mb-4 border-left-primary">
        <div class="card-body">
            <h5 class="card-title text-primary"><i class="fas fa-info-circle mr-2"></i>Chatbot Prompt Guidelines</h5>
            
            <div class="row">
                <div class="col-md-6">
                    <h6 class="font-weight-bold mt-3">GENERAL RULE:</h6>
                    <p class="small">Think like a user, not like an administrator. Add keywords based on how people actually ask questions, not how you think they should ask.</p>
                    
                    <h6 class="font-weight-bold">KEYWORD RULES:</h6>
                    <ul class="small pl-3">
                        <li><strong>Include all variations</strong> - English, Tagalog, common misspellings</li>
                        <li><strong>Add question patterns</strong> - How people actually ask (sino, saan, ano, ilan, etc.)</li>
                        <li><strong>Include synonyms</strong> - Different words for the same thing</li>
                        <li><strong>Think cross-language</strong> - If someone asks in Tagalog, they should find English database entries</li>
                        <li><strong>Keep it comma-separated</strong> - Easy to read and manage</li>
                        <li><strong>Test both languages</strong> - Make sure Tagalog and English queries both work</li>
                    </ul>
                </div>
                
                <div class="col-md-6">
                    <h6 class="font-weight-bold mt-3">RESPONSE RULES:</h6>
                    <ul class="small pl-3">
                        <li><strong>Be direct and factual</strong> - Answer the question clearly</li>
                        <li><strong>Keep it concise</strong> - 1-3 sentences maximum</li>
                        <li><strong>No filler</strong> - Don't add "feel free to ask" or unnecessary politeness</li>
                        <li><strong>Use exact information</strong> - Names, numbers, locations must be accurate</li>
                        <li><strong>No assumptions</strong> - If you don't know, say "Information not available"</li>
                        <li><strong>Format lists with numbers</strong> - If response contains a list, use numbered format</li>
                    </ul>
                    
                    <h6 class="font-weight-bold mt-3">EXAMPLE TEMPLATE:</h6>
                    <div class="bg-light p-3 rounded small">
                        <strong>Keywords:</strong> [main term], [English variations], [Tagalog variations], [common misspellings], [question patterns]<br>
                        <strong>Response:</strong> [Direct answer. 1-3 sentences. Facts only.]
                    </div>
                </div>
            </div>
            
            <div class="mt-3 p-3 bg-success text-white rounded">
                <h6 class="font-weight-bold mb-2"><i class="fas fa-lightbulb mr-2"></i>EXAMPLE:</h6>
                <p class="mb-1"><strong>Keywords:</strong> librarian, school librarian, sino ang librarian, who is the librarian, library staff, sino sa library, may librarian ba, librarian sino, library personnel</p>
                <p class="mb-0"><strong>Response:</strong> The school librarian is Ms. Maria Santos.</p>
            </div>
        </div>
    </div>

    <form method="POST" id="addPromptForm">
        <input type="hidden" name="add_prompt" value="1"> <!-- added -->
        <div class="form-group">
             <label for="keywords"><strong>Keywords</strong> <small class="text-muted">(Comma-separated, include variations)</small></label>
            <input type="text" class="form-control" id="keywords" name="keywords" placeholder="e.g. librarian, school librarian, who is the librarian, sino sa library, may librarian ba" required>
           
        </div>
        <div class="form-group">
           <!-- <label for="response">Chatbot Response</label> -->
             <label for="response"><strong>Chatbot Response</strong> <small class="text-muted">(1-3 sentences, factual only)</small></label>
            <textarea class="form-control" id="response" name="response" rows="5" placeholder="Enter the chatbot response..." required></textarea>
        </div>
        <div class="d-flex justify-content-end mt-4">
            <button type="reset" class="btn btn-outline-secondary mr-2">Clear</button>
            <button type="submit" name="add_prompt_btn" id="addPromptBtn" class="btn btn-primary">
                <span class="spinner-border spinner-border-sm d-none" role="status" id="addPromptSpinner" aria-hidden="true"></span>
                <i class="fas fa-plus mr-2"></i><span id="addPromptText">Add Prompt</span>
            </button>
        </div>
    </form>
</div>

                        <!-- Existing Prompts Tab -->
                        <div class="tab-pane fade shadow-lg mb-4" id="existing" role="tabpanel" aria-labelledby="existing-tab">
                            <h3 class="section-title">Existing Keyword Prompts</h3>
                            <div class="table-container">
                                <table id="promptsTable" class="table table-hover" style="width:100%">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Keywords</th>
                                            <th>Response</th>
                                            <th class="action-buttons">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($prompts_result as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['keywords']); ?></td>
                                            <td><?php echo htmlspecialchars($row['response']); ?></td>
                                            <td class="action-buttons">
                                                <button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#editPromptModal" 
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-keywords="<?php echo htmlspecialchars($row['keywords']); ?>"
                                                    data-response="<?php echo htmlspecialchars($row['response']); ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger delete-prompt" data-id="<?php echo $row['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Knowledge base removed -->
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


       <!-- Edit Prompt Modal -->
    <div class="modal fade" id="editPromptModal" tabindex="-1" role="dialog" aria-labelledby="editPromptModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editPromptModalLabel">Edit Chatbot Prompt</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true" style="color: white;">&times;</span>
                    </button>
                </div>
              <form method="POST" id="updatePromptForm">
    <input type="hidden" name="update_prompt" value="1"> <!-- added -->
    <div class="modal-body">
        <input type="hidden" name="prompt_id" id="editPromptId">
        <div class="form-group">
            <label for="editKeywords">Keywords</label>
            <input type="text" class="form-control" id="editKeywords" name="keywords" required>

        </div>
        <div class="form-group">
            <label for="editResponse">Chatbot Response</label>
            <textarea class="form-control" id="editResponse" name="response" rows="5" required></textarea>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" id="updatePromptBtn" class="btn btn-primary">
            <span class="spinner-border spinner-border-sm d-none" role="status" id="updatePromptSpinner" aria-hidden="true"></span>
            <span id="updatePromptText">Update Prompt</span>
        </button>
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
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>


<script>
  // Profile image preview kept from previous file (IDs exist on page)
 document.getElementById('profileImageInput')?.addEventListener('change', function (event) {
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
            event.target.value = ''; // Clear the input
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
        reader.onload = function (e) {
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
    $(document).ready(function() {
        // Initialize DataTables
        $('#promptsTable').DataTable({
            responsive: true,
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
            language: {
                search: "Search prompts:",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            },
            autoWidth: false,
            columnDefs: [
                { width: '20%', targets: 0 },
                { width: '60%', targets: 1 },
                { width: '20%', targets: 2, className: 'dt-center' }
            ]
        });

        // Files DataTable removed

        // Remember active tab after page reload
        if (window.location.hash) {
            $('.nav-tabs a[href="' + window.location.hash + '"]').tab('show');
        }

        // Update URL hash when tab is changed
        $('.nav-tabs a').on('shown.bs.tab', function(e) {
            window.location.hash = $(e.target).attr('href');
        });

        // Edit Prompt Modal populate
        $('#editPromptModal').on('show.bs.modal', function(event) {
            const button = $(event.relatedTarget);
            const id = button.data('id');
            const keywords = button.data('keywords');
            const response = button.data('response');

            const modal = $(this);
            modal.find('#editPromptId').val(id);
            modal.find('#editKeywords').val(keywords);
            modal.find('#editResponse').val(response);
        });

        // Delete Prompt Confirmation
        $('body').on('click', '.delete-prompt', function() {
            const promptId = $(this).data('id');
            Swal.fire({
                title: 'Are you sure?',
                text: "This action cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = $('<form>').attr({
                        method: 'POST',
                        action: ''
                    }).append(
                        $('<input>').attr({ type: 'hidden', name: 'prompt_id', value: promptId }),
                        $('<input>').attr({ type: 'hidden', name: 'delete_prompt', value: '1' })
                    );
                    $('body').append(form);
                    form.submit();
                }
            });
        });

        // Add prompt loading state
        $('#addPromptForm').on('submit', function() {
            $('#addPromptSpinner').removeClass('d-none');
            $('#addPromptText').text('Adding...');
            $('#addPromptBtn').prop('disabled', true);
            return true;
        });

        // Update prompt loading state
        $('#updatePromptForm').on('submit', function() {
            $('#updatePromptSpinner').removeClass('d-none');
            $('#updatePromptText').text('Updating...');
            $('#updatePromptBtn').prop('disabled', true);
            return true;
        });

        // Reset UI on modal close to ensure repeated use works
        $('#editPromptModal').on('hidden.bs.modal', function() {
            $('#updatePromptSpinner').addClass('d-none');
            $('#updatePromptText').text('Update Prompt');
            $('#updatePromptBtn').prop('disabled', false);
        });

        $('#prompts-tab').on('shown.bs.tab', function() {
            $('#addPromptSpinner').addClass('d-none');
            $('#addPromptText').text('Add Prompt');
            $('#addPromptBtn').prop('disabled', false);
        });

        // files tab removed
    });
</script>

<script>
    // Fetch Logs Button 
    function fetchLogs() {
        fetch('/admin/logs')
            .then(response => response.text())
            .then(data => {
                document.getElementById('logContainer').textContent = data || 'No logs available.';
            })
            .catch(error => {
                console.error('Error fetching logs:', error);
                document.getElementById('logContainer').textContent = 'Failed to load logs.';
            });
    }

    document.getElementById('refreshLog').addEventListener('click', fetchLogs);
    window.addEventListener('DOMContentLoaded', fetchLogs);
</script>
<?php include 'search/Search_Admin.php'; ?>
</body>

</html>   