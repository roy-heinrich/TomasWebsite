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
require_once 'config.php'; // $conn is PDO

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) === false) {
    // Registration flow
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
   $email = strtolower(trim($_POST['email'] ?? ''));  // Convert to lowercase
    $confirm_email = strtolower(trim($_POST['confirm_email'] ?? ''));  // Convert to lowercase
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_role = $_POST['user_role'] ?? 'Teacher';
    $advisory = isset($_POST['advisory']) ? trim($_POST['advisory']) : null;

    $errors = [];

    if (strlen($username) < 8) {
        $errors[] = "Username must be at least 8 characters.";
    }

    if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}$/', $password)) {
        $errors[] = "Password must be at least 10 characters, include 1 uppercase, 1 number, and 1 special character.";
    }

    if ($email !== $confirm_email) {
        $errors[] = "Emails do not match.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

     // Unique username/email check - use LOWER() for case-insensitive comparison
    $stmt = $conn->prepare("SELECT id FROM staff_tbl WHERE username = :username OR LOWER(email) = LOWER(:email)");
    $stmt->execute([':username' => $username, ':email' => $email]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Username or Email already exists.";
    }

    if ($user_role === 'Teacher' && !empty($advisory)) {
        $stmt = $conn->prepare("SELECT id FROM staff_tbl 
                               WHERE advisory_section = :advisory 
                               AND user_role = 'Teacher' 
                               AND status <> 'Archived'");
        $stmt->execute([':advisory' => $advisory]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Advisory section is already assigned to another teacher.";
        }
    }

    $profile_image_name = null;

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['profile_image']['tmp_name'];
        $fileName = $_FILES['profile_image']['name'];
        $fileSize = $_FILES['profile_image']['size'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpeg', 'jpg', 'png', 'webp'];
        $allowedMime = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $fileType = $_FILES['profile_image']['type'];

        if (!in_array($fileExtension, $allowedExtensions) || !in_array($fileType, $allowedMime)) {
            $errors[] = "Only JPEG, JPG, PNG, and WEBP files are allowed.";
        } elseif ($fileSize > 25 * 1024 * 1024) { // 25MB
            $errors[] = "Profile image size must not exceed 25MB.";
        } else {
            $safeFileName = uniqid() . '.' . $fileExtension;

            // Upload to Supabase
            if (!uploadToSupabase($fileTmpPath, $safeFileName)) {
                $errors[] = "Failed to upload profile image to storage.";
            } else {
                $profile_image_name = $safeFileName;
            }
        }
    } else {
        // Use ui-avatars if no file
        $encodedName = urlencode($fullname);
        $profile_image_name = "https://ui-avatars.com/api/?name=$encodedName&background=random&color=fff";
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = $conn->prepare("INSERT INTO staff_tbl (fullname, username, email, password, user_role, advisory_section, profile_image) VALUES (:fullname, :username, :email, :password, :user_role, :advisory, :profile_image)");
            $insert->execute([
                ':fullname' => $fullname,
                ':username' => $username,
                ':email' => $email,
                ':password' => $hashed_password,
                ':user_role' => $user_role,
                ':advisory' => $advisory,
                ':profile_image' => $profile_image_name
            ]);

            // Send email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = MAIL_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = MAIL_USERNAME;
                $mail->Password = MAIL_PASSWORD;
                $mail->SMTPSecure = MAIL_ENCRYPTION;
                $mail->Port = MAIL_PORT;

                $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
                $mail->addAddress($email, $fullname);

                $mail->isHTML(true);
                $mail->Subject = 'Staff Registration Successful';
                $mail->Body = "Hi <strong>$fullname</strong>,<br><br>You have been successfully registered as <strong>$user_role</strong> for Tomas SM Bautista Elementary School Website.";
                $mail->send();

                $_SESSION['success'] = "Staff registered successfully. A confirmation email has been sent to <strong>$email</strong>.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } catch (Exception $e) {
                $_SESSION['success'] = "Staff registered but email failed. Mailer Error: {$mail->ErrorInfo}";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Something went wrong while saving the staff information: " . $e->getMessage();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
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

    <title>Staff Account Management</title>

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

    .username-wrapper small {
        display: none;
        font-size: 0.75rem;
        color: rgb(39, 79, 239);
        margin-top: 4px;
    }

    .username-wrapper:focus-within small {
        display: block;
    }

    .password-wrapper small {
        display: none;
        font-size: 0.75rem;
        color:rgb(39, 79, 239);
        margin-top: 4px;
    }

    .password-wrapper:focus-within small {
        display: block;
    }

    .modal {
        padding-right: 0 !important;
    }
    .modal-open {
        overflow: auto;
        padding-right: 0 !important;
    }
    .modal-dialog {
        margin: 1.75rem auto;
    }
    .modal-content {
        max-height: 90vh;
        overflow-y: auto;
    } 

    .toggle-archive-btn {
        border-radius: 0.5rem;
        min-width: 180px;
        padding: 0.6rem 1.2rem;
        font-size: 1rem;
    }

    @media (max-width: 576px) {
        .toggle-archive-btn {
            min-width: 130px;
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }
    }

    #profileModal .modal-content {
        max-height: none !important;
        overflow-y: visible !important;
    }

    #registerButton {
        position: relative;
    }

    #buttonSpinner {
        margin-left: 8px;
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
   
<?php if (isset($_SESSION['success'])): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        html: "<?= addslashes($_SESSION['success']) ?>",
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
        html: "<?= str_replace(["\n", "\r"], '<br>', addslashes($_SESSION['error'])) ?>",
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
            <li class="nav-item active">
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

            <div class="container mt-5">
                <div class="d-flex align-items-center">
                    <i class="fas fa-user-circle" style="color: rgb(11, 104, 245); font-size: 1.4rem; margin-right: 0.8rem;"></i>
                    <h2 class="fw-bolder mb-0" style="color: rgb(11, 104, 245); font-size: 1.50rem; font-weight: 800;">Staff Management</h2>
                </div>
            </div>   

            <section class="admin-register-staff bg-light py-5">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-lg-11">
                            <div class="card shadow p-4" style="border-top: 8px solid #8A2BE2; border-radius: 1rem;">
                                <h3 class="text-start mb-2" style="color: #8A2BE2; font-weight: 900;">Register New Staff</h3>
                                <hr class="mt-2 mb-4">

                                <form action="" method="POST" id="staffForm" style="max-width: 100%;" enctype="multipart/form-data">
                                    <div class="row g-3">
                                        <!-- Full Name -->
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">Full Name</label>
                                            <input type="text" name="fullname" class="form-control" placeholder="ex.Juan Dela Cruz" required>
                                        </div>

                                        <!-- Username -->
                                        <div class="col-md-6 mb-3 username-wrapper">
                                            <label class="form-label fw-semibold">Username</label>
                                            <input type="text" name="username" class="form-control" placeholder="ex.jdelacruz" required>
                                            <small>Must be at least 8 characters</small>
                                        </div>

                                        <!-- Email -->
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">Email Address</label>
                                            <input type="email" name="email" class="form-control" placeholder="email@example.com" required>
                                        </div>

                                        <!-- Confirm Email -->
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">Confirm Email</label>
                                            <input type="email" name="confirm_email" class="form-control" placeholder="email@example.com" required>
                                        </div>

                                        <!-- Password -->
                                        <div class="col-md-6 mb-3 password-wrapper">
                                            <label class="form-label fw-semibold">Password</label>
                                            <div class="input-group">
                                                <input type="password" name="password" id="password" class="form-control" required>
                                                <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                                    <i class="fas fa-eye-slash" id="togglePasswordIcon"></i>
                                                </button>
                                            </div>
                                            <small>Must be at least 10 characters/1 Uppercase letter/1 number/1 special character</small>
                                        </div>

                                        <!-- Confirm Password -->
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">Confirm Password</label>
                                            <div class="input-group">
                                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                                <button type="button" class="btn btn-outline-secondary" id="toggleConfirmPassword">
                                                    <i class="fas fa-eye-slash" id="toggleConfirmPasswordIcon"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- User Role -->
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">Staff Role</label>
                                            <select name="user_role" class="form-control" id="roleSelect" required onchange="toggleAdvisory()">
                                                <option value="" disabled selected>Select Role</option>
                                                <option value="Admin">Admin</option>
                                                <option value="Teacher">Teacher</option>
                                            </select>
                                        </div>

                                        <!-- Profile Image (Optional) -->
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-semibold">Profile Image (optional)</label>
                                            <input type="file" name="profile_image" class="form-control" accept=".jpeg,.jpg,.png,.webp" id="profileImageInput">
                                            <small class="text-muted">Allowed types: jpeg, jpg, png, webp | Max size: 25MB</small>
                                        </div>

                                        <!-- Advisory Section -->
                                        <div class="col-md-6 mb-3" id="advisorySection" style="display: none;">
                                            <label class="form-label fw-semibold">Advisory Section</label>
                                            <input type="text" name="advisory" id="advisory" class="form-control" placeholder="e.g., Grade 1 - Timothy">
                                        </div>

                                        <!-- Buttons -->
                                        <div class="col-md-12 text-center mt-3">
                                            <button type="submit" class="btn btn-primary px-4 fw-bold" id="registerButton">
                                                <span id="buttonText">Register Staff</span>
                                                <span id="buttonSpinner" class="spinner-border spinner-border-sm d-none"></span>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary px-4 fw-bold ms-2" id="cancelButton" style="display: none;">Cancel</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="container mt-4" style="max-width: 95%;">
                <div class="d-flex flex-wrap justify-content-start mb-2">
                    <!-- Right: Toggle Archive -->
                    <button id="toggleArchiveBtn" class="btn btn-info fw-bold toggle-archive-btn">
                        <i class="fas fa-archive mr-2"></i>Show Archive
                    </button>
                </div>
            </div>

            <!-- Staff Table -->
            <section id="registeredStaffSection" class="registered-staff-list py-4">
                <div class="container" style="max-width: 95%;">
                    <div class="card shadow" style="border-top: 8px solid #8A2BE2; border-radius: 1rem;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                                <h3 class="text-start mb-2" style="color: #8A2BE2; font-weight: 900;">Registered Staff</h3>
                            </div>

                            <!-- Table -->
                            <div class="table-responsive">
                                <table id="registeredStaffTable" class="table table-bordered table-hover align-middle text-center" style="width:100%">
                                    <thead style="background-color: #8A2BE2; color: white;">
                                        <tr>
                                            <th>Profile Image</th>
                                            <th>Full Name</th>
                                            <th>Username</th>
                                            <th>Status</th>
                                            <th>Role</th>
                                            <th>Email</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                         <?php
                                        try {
                                            $stmt = $conn->prepare("SELECT * FROM staff_tbl WHERE status <> 'Archived' AND (is_superadmin IS NOT TRUE) ORDER BY id DESC");
                                            $stmt->execute();
                                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                            foreach ($rows as $row) {
                                                $profile_image = $row['profile_image'] ?? '';

                                                if (!empty($profile_image) && filter_var($profile_image, FILTER_VALIDATE_URL)) {
                                                    $img_src = $profile_image;
                                                } elseif (!empty($profile_image)) {
                                                    // file stored in Supabase bucket
                                                    $img_src = getSupabaseUrl($profile_image);
                                                } else {
                                                    $img_src = "https://ui-avatars.com/api/?name=" . urlencode($row['fullname']) . "&background=random&color=fff&size=200";
                                                }

                                                $status = $row['status'] ?? 'Active';
                                                $display_status = ($status === 'Disable') ? 'Disabled' : $status;
                                                $badge_class = (strtolower($status) === 'active') ? 'bg-success' : 'bg-danger';

                                                echo "<tr>";
                                                echo "<td><img src='" . htmlspecialchars($img_src) . "' alt='Profile' width='36' height='36' style='object-fit: cover; border-radius: 50%;'></td>";
                                                echo "<td>" . htmlspecialchars($row['fullname']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                                                echo "<td><span class='badge $badge_class text-white'>" . htmlspecialchars($display_status) . "</span></td>";
                                                echo "<td>" . htmlspecialchars($row['user_role']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                                                echo "<td>
                                                        <button class='btn btn-sm btn-primary me-1 edit-btn' data-id='" . htmlspecialchars($row['id']) . "'><i class='fas fa-edit'></i></button>
                                                        <button class='btn btn-sm btn-warning me-1 archive-btn' data-id='" . htmlspecialchars($row['id']) . "'>
                                                            <i class='fas fa-archive'></i>
                                                        </button>
                                                      </td>";
                                                echo "</tr>";
                                            }
                                        } catch (Exception $e) {
                                            // If you want to debug uncomment next line (don't expose in production)
                                            // echo "<tr><td colspan='7'>Error fetching staff: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Archived Staff Table -->
            <section id="archivedStaffSection" class="archived-staff-list py-4" style="display: none;">
                <div class="container" style="max-width: 95%;">
                    <div class="card shadow" style="border-top: 8px solid #17a2b8; border-radius: 1rem;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                                <h3 class="text-start mb-2" style="color: #17a2b8; font-weight: 900;">Archived Staff</h3>
                            </div>

                            <!-- Archived Table -->
                            <div class="table-responsive">
                                <table id="archivedStaffTable" class="table table-bordered table-hover align-middle text-center" style="width:100%">
                                    <thead style="background-color: #17a2b8; color: white;">
                                        <tr>
                                            <th>Profile Image</th>
                                            <th>Full Name</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        try {
                                            $stmt = $conn->prepare("SELECT * FROM staff_tbl WHERE status = 'Archived' AND (is_superadmin IS NOT TRUE) ORDER BY id DESC");
                                            $stmt->execute();
                                            $archived = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                            foreach ($archived as $row) {
                                                $profile_image = $row['profile_image'] ?? '';

                                                if (!empty($profile_image) && filter_var($profile_image, FILTER_VALIDATE_URL)) {
                                                    $img_src = $profile_image;
                                                } elseif (!empty($profile_image)) {
                                                    $img_src = getSupabaseUrl($profile_image);
                                                } else {
                                                    $img_src = "https://ui-avatars.com/api/?name=" . urlencode($row['fullname']) . "&background=random&color=fff&size=200";
                                                }

                                                echo "<tr>";
                                                echo "<td><img src='" . htmlspecialchars($img_src) . "' alt='Profile' width='36' height='36' style='object-fit: cover; border-radius: 50%;'></td>";
                                                echo "<td>" . htmlspecialchars($row['fullname']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['user_role']) . "</td>";
                                                echo "<td>             
                                                    <button class='btn btn-sm btn-success restore-btn' data-id='" . htmlspecialchars($row['id']) . "'>
                                                        <i class='fas fa-undo-alt'></i>
                                                    </button>
                                                    <button class='btn btn-sm btn-danger delete-btn' data-id='" . htmlspecialchars($row['id']) . "'>
                                                        <i class='fas fa-trash'></i>
                                                    </button>
                                                </td>";
                                                echo "</tr>";
                                            }
                                        } catch (Exception $e) {
                                            // debug if needed
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

          <!-- Edit Staff Modal -->
<div class="modal fade" id="editStaffModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Edit Staff</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editStaffForm" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="staff_id" id="editStaffId">
                    <div class="row">
                        <!-- Full Name -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Full Name</label>
                            <input type="text" name="fullname" id="editFullName" class="form-control" required>
                        </div>
                        
                        <!-- Username -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" id="editUsername" class="form-control" required>
                        </div>
                        
                        <!-- Email -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" id="editEmail" class="form-control" required>
                        </div>
                        
                        <!-- Status -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="editStatus" class="form-control" required>
                                <option value="Active">Active</option>
                                <option value="Disable">Disabled</option>
                            </select>
                        </div>
                        
                        <!-- Profile Image -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Profile Image</label>
                            <input type="file" name="profile_image" class="form-control" id="editProfileImageInput" accept=".jpeg,.jpg,.png,.webp">
                            <div class="mt-2">
                                <img id="currentProfileImage" src="" alt="Current Profile" class="img-thumbnail" width="100">
                            </div>
                            <small class="text-muted">Max: 25MB | Types: JPEG, JPG, PNG, WEBP</small>
                        </div>
                        
                        <!-- Advisory Section -->
                        <div class="col-md-6 mb-3" id="editAdvisorySection" style="display: none;">
                            <label class="form-label fw-semibold">Advisory Section</label>
                            <input type="text" name="advisory" id="editAdvisory" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
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
                                <input type="file" name="profile_image" class="form-control-file" id="profileeImageInput" accept=".jpg,.jpeg,.png,.webp">
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
        // Initialize DataTables for both tables
        $('#registeredStaffTable').DataTable({
            "dom": '<"top"<"d-flex flex-column flex-md-row justify-content-between"<"mb-2 mb-md-0"l><"mb-2 mb-md-0"f>>>rt<"bottom"ip>',
            "responsive": true,
            "language": {
                "emptyTable": "No staff registered yet.",
                "search": "Search:",
                "searchPlaceholder": "Search staff...",
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
                    "targets": [0, 6] // Disable sorting for image and action columns
                }
            ],
            "lengthMenu": [5, 10, 25, 50],
            "pageLength": 10,
            "initComplete": function() {
                $('.dataTables_filter input').addClass('form-control form-control-sm');
                $('.dataTables_length select').addClass('form-control form-control-sm');
            }
        });

        $('#archivedStaffTable').DataTable({
            "dom": '<"top"<"d-flex flex-column flex-md-row justify-content-between"<"mb-2 mb-md-0"l><"mb-2 mb-md-0"f>>>rt<"bottom"ip>',
            "responsive": true,
            "language": {
                "emptyTable": "No staff archived yet.",
                "search": "Search:",
                "searchPlaceholder": "Search archived staff...",
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
                    "targets": [0, 5] // Disable sorting for image and action columns
                }
            ],
            "lengthMenu": [5, 10, 25, 50],
            "pageLength": 10,
            "initComplete": function() {
                $('.dataTables_filter input').addClass('form-control form-control-sm');
                $('.dataTables_length select').addClass('form-control form-control-sm');
            }
        });

        // Toggle between active and archived staff tables
        $('#toggleArchiveBtn').click(function() {
            const registeredSection = $('#registeredStaffSection');
            const archivedSection = $('#archivedStaffSection');
            const toggleBtn = $('#toggleArchiveBtn');
            
            if (archivedSection.is(':hidden')) {
                archivedSection.show();
                registeredSection.hide();
                toggleBtn.html('<i class="fas fa-users mr-2"></i>Show Active');
                toggleBtn.removeClass('btn-info').addClass('btn-primary');
            } else {
                archivedSection.hide();
                registeredSection.show();
                toggleBtn.html('<i class="fas fa-archive mr-2"></i>Show Archive');
                toggleBtn.removeClass('btn-primary').addClass('btn-info');
            }
        });

        // Form handling
        const form = document.getElementById("staffForm");
        const cancelButton = document.getElementById("cancelButton");
        const roleSelect = document.getElementById("roleSelect");
        const advisoryField = document.getElementById("advisorySection");
        const advisoryInput = document.getElementById("advisory");

        // Handle input to show cancel button
        form.addEventListener("input", function () {
            cancelButton.style.display = "inline-block";
        });

        // Cancel button resets everything
        cancelButton.addEventListener("click", function () {
            form.reset();
            cancelButton.style.display = "none";
            toggleAdvisory(); // Reset advisory section visibility
        });

        // Trigger toggleAdvisory on page load and role change
        roleSelect.addEventListener("change", toggleAdvisory);
        toggleAdvisory();

        function toggleAdvisory() {
            const role = roleSelect.value;
            if (role === 'Teacher') {
                advisoryField.style.display = 'block';
                advisoryInput.setAttribute('required', 'required');
            } else {
                advisoryField.style.display = 'none';
                advisoryInput.removeAttribute('required');
            }
        }

        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('togglePasswordIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        });

        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const confirmPasswordInput = document.getElementById('confirm_password');
            const icon = document.getElementById('toggleConfirmPasswordIcon');
            
            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                confirmPasswordInput.type = 'password';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        });

        document.getElementById('staffForm').addEventListener('submit', function(e) {
            const button = document.getElementById('registerButton');
            const buttonText = document.getElementById('buttonText');
            const spinner = document.getElementById('buttonSpinner');
            
            // Show spinner and change button text
            button.disabled = true;
            buttonText.textContent = 'Registering...';
            spinner.classList.remove('d-none');
        });

        // Image validation for registration form
        $('#profileImageInput').on('change', function() {
            validateImageSize(this);
        });

        // Image validation for edit form
        $('#editProfileImageInput').on('change', function() {
            validateImageSize(this);
        });

        // Profile image validation
        $('#profileeImageInput').on('change', function() {
            validateImageSize(this);
        });

        // Image validation for edit form
$('#editProfileImageInput').on('change', function() {
    const file = this.files[0];
    const maxSize = 25 * 1024 * 1024; // 25MB
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    
    if (file) {
        // First validate the file
        if (!allowedTypes.includes(file.type)) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid File Type',
                text: 'Only JPEG, JPG, PNG, and WEBP files are allowed.',
                confirmButtonColor: '#d33'
            });
            this.value = '';
            return;
        }
        
        if (file.size > maxSize) {
            Swal.fire({
                icon: 'error',
                title: 'File Too Large',
                html: 'The selected image exceeds the maximum allowed size of <strong>25MB</strong>. Please choose a smaller file.',
                confirmButtonColor: '#d33'
            });
            this.value = '';
            return;
        }
        
        // If validation passes, show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            $('#currentProfileImage').attr('src', e.target.result);
        };
        reader.readAsDataURL(file);
    }
});

// Edit button click handler
$(document).on('click', '.edit-btn', function() {
    const staffId = $(this).data('id');
    currentStaffId = staffId;

    // Show loading state
    $(this).html('<i class="fas fa-spinner fa-spin"></i>');
    $(this).prop('disabled', true);

    $.ajax({
        url: 'fetch_staff.php',
        type: 'GET',
        data: { id: staffId },
        dataType: 'json',
        success: function(staff) {
            if (staff.error) {
                Swal.fire({
                    title: 'Error!',
                    text: staff.error,
                    icon: 'error'
                });
                return;
            }

            $('#editStaffId').val(staff.id);
            $('#editFullName').val(staff.fullname);
            $('#editUsername').val(staff.username);
            $('#editEmail').val(staff.email);
            $('#editStatus').val(staff.status);

            // Set profile image
            if (staff.profile_image.startsWith('http')) {
                $('#currentProfileImage').attr('src', staff.profile_image);
            } else {
                $('#currentProfileImage').attr('src', 'images/profile_images/' + staff.profile_image);
            }

            // Clear the file input when modal opens
            $('#editProfileImageInput').val('');

            // Handle advisory section
            if (staff.user_role === 'Teacher') {
                $('#editAdvisorySection').show();
                $('#editAdvisory').val(staff.advisory_section || '');
            } else {
                $('#editAdvisorySection').hide();
            }

            $('#editStaffModal').modal('show');
        },
        error: function(xhr, status, error) {
            Swal.fire({
                title: 'Error!',
                text: 'Failed to fetch staff data: ' + error,
                icon: 'error'
            });
        },
        complete: function() {
            $('.edit-btn').html('<i class="fas fa-edit"></i>');
            $('.edit-btn').prop('disabled', false);
        }
    });
});

        // Delete button click handler
        $(document).on('click', '.delete-btn', function() {
            const staffId = $(this).data('id');
            
            Swal.fire({
                title: 'Permanently Delete?',
                html: `<div class="text-left">
                    <p>This action will <strong class="text-danger">permanently delete</strong> the staff account and cannot be undone.</p>
                </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete permanently',
                cancelButtonText: 'Cancel',
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // show processing/loading modal
                    Swal.fire({
                        title: 'Processing...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: 'delete_staff.php',
                        type: 'POST',
                        data: { staff_id: staffId },
                        dataType: 'json',
                        success: function(response) {
                            Swal.close();
                            if (response.success) {
                                Swal.fire({
                                    title: 'Deleted!',
                                    text: response.message,
                                    icon: 'success'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error!',
                                    text: response.message,
                                    icon: 'error'
                                });
                            }
                        },
                        error: function() {
                            Swal.close();
                            Swal.fire({
                                title: 'Error!',
                                text: 'Failed to communicate with server',
                                icon: 'error'
                            });
                        }
                    });
                }
            });
        });

        // Archive button click handler
        $(document).on('click', '.archive-btn', function() {
            const staffId = $(this).data('id');
            
            Swal.fire({
                title: 'Archive Account?',
                html: `<div class="text-left">
                    <p>This account will be moved to the archived staff section.</p>
                    <p class="text-danger"><strong>Note:</strong> If you're archiving a teacher account, you need to reassign their students to another teacher first.</p>
                </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                confirmButtonText: 'Yes, archive it',
                cancelButtonColor: '#6c757d',
                cancelButtonText: 'Cancel',
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // show processing/loading modal
                    Swal.fire({
                        title: 'Processing...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: 'archive_staff.php',
                        type: 'POST',
                        data: { staff_id: staffId },
                        dataType: 'json',
                        success: function(response) {
                            Swal.close();
                            if (response.success) {
                                Swal.fire({
                                    title: 'Archived!',
                                    text: response.message,
                                    icon: 'success',
                                    confirmButtonColor: '#28a745'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error!',
                                    text: response.message,
                                    icon: 'error',
                                    confirmButtonColor: '#dc3545'
                                });
                            }
                        },
                        error: function() {
                            Swal.close();
                            Swal.fire({
                                title: 'Error!',
                                text: 'Failed to communicate with server',
                                icon: 'error',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    });
                }
            });
        });

        // Restore button click handler
        $(document).on('click', '.restore-btn', function() {
            const staffId = $(this).data('id');
            
            Swal.fire({
                title: 'Restore Account?',
                text: "This account will be restored to active status.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, restore it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // show processing/loading modal
                    Swal.fire({
                        title: 'Processing...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: 'restore_staff.php',
                        type: 'POST',
                        data: { 
                            staff_id: staffId,
                            action: 'restore' 
                        },
                        success: function(response) {
                            Swal.close();
                            if (response === 'success') {
                                Swal.fire(
                                    'Restored!',
                                    'Staff account has been restored.',
                                    'success'
                                ).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire(
                                    'Error!',
                                    'There was an issue restoring the staff.',
                                    'error'
                                );
                            }
                        },
                        error: function() {
                            Swal.close();
                            Swal.fire(
                                'Error!',
                                'There was an issue restoring the staff.',
                                'error'
                            );
                        }
                    });
                }
            });
        });

        // Edit form submission
        $('#editStaffForm').submit(function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const saveButton = $(this).find('button[type="submit"]');
            const originalButtonText = saveButton.html();
            
            // Show loading state
            saveButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

            $.ajax({
                url: 'update_staff.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonColor: '#3085d6',
                        }).then(() => {
                            $('#editStaffModal').modal('hide');
                            location.reload();
                        });
                    } else {
                        saveButton.prop('disabled', false).html(originalButtonText);
                        Swal.fire({
                            title: 'Error!',
                            text: response.message,
                            icon: 'error',
                            confirmButtonColor: '#d33',
                        });
                    }
                },
                error: function() {
                    saveButton.prop('disabled', false).html(originalButtonText);
                    Swal.fire({
                        title: 'Error!',
                        text: 'An error occurred. Please try again.',
                        icon: 'error',
                        confirmButtonColor: '#d33',
                    });
                }
            });
        });

        function validateImageSize(input) {
            const file = input.files[0];
            if (file) {
                const maxSize = 25 * 1024 * 1024; // 25MB
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                
                if (!allowedTypes.includes(file.type)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid File Type',
                        text: 'Only JPEG, JPG, PNG, and WEBP files are allowed.',
                        confirmButtonColor: '#d33'
                    });
                    input.value = '';
                } else if (file.size > maxSize) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Too Large',
                        html: 'The selected image exceeds the maximum allowed size of <strong>25MB</strong>. Please choose a smaller file.',
                        confirmButtonColor: '#d33'
                    });
                    input.value = '';
                }
            }
        }

        // Profile image upload validation
        $('#profileeImageInput').change(function(event) {
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
                    $('#previewImagee').attr('src', e.target.result);
                };
                reader.readAsDataURL(file);
            }
        });

      
 // expose globally so inline onclick handlers can call it
       window.togglePassword = function(fieldId, icon) {
           const field = document.getElementById(fieldId);
           if (!field) return;
           const isHidden = field.type === 'password';
           field.type = isHidden ? 'text' : 'password';
           // toggle classes on the <i> element (icon)
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