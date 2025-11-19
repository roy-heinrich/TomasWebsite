<?php
require_once 'session.php';
require_once 'config.php';

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
    header('Location: login.php'); // or show an error page if you prefer
    exit;
}

$user = $_SESSION['user']; // Now safe to use

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    
    
    try {    
        
        $telephone = $_POST['telephone_primary'];
        $email = $_POST['email_general'];
        $fb_page = $_POST['fb_page'];
        $fb_link = $_POST['fb_link'];
        $address = $_POST['address'];

        // Check if record exists
        $stmt = $conn->query("SELECT id FROM contact_info LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Record exists, so update it
            $id = $row['id'];
            $sql = "UPDATE contact_info 
                    SET telephone_primary = :telephone, 
                        email_general = :email, 
                        fb_page = :fb_page, 
                        fb_link = :fb_link, 
                        address = :address 
                    WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                ':telephone' => $telephone,
                ':email' => $email,
                ':fb_page' => $fb_page,
                ':fb_link' => $fb_link,
                ':address' => $address,
                ':id' => $id
            ]);
        } else {
            // No record exists, insert a new one
            $sql = "INSERT INTO contact_info (telephone_primary, email_general, fb_page, fb_link, address)
                    VALUES (:telephone, :email, :fb_page, :fb_link, :address)";
            
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                ':telephone' => $telephone,
                ':email' => $email,
                ':fb_page' => $fb_page,
                ':fb_link' => $fb_link,
                ':address' => $address
            ]);
        }

        if ($result) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database operation failed']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Unable to connect to the database. Please try again later.']);
    }
    exit;
}


// Fetch the latest contact info using PostgreSQL
require_once 'config.php';
$telephone = $email = $fb_page = $fb_link = $address = '';

try {
    $stmt = $conn->query("SELECT * FROM contact_info ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $telephone = htmlspecialchars($row['telephone_primary']);
        $email = htmlspecialchars($row['email_general']);
        $fb_page = htmlspecialchars($row['fb_page']);
        $fb_link = htmlspecialchars($row['fb_link']);
        $address = htmlspecialchars($row['address']);
    }
} catch (PDOException $e) {
    // Handle error if needed
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

    <title>Contact Management</title>

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



/* Contact Information Form Styles */
        .contact-form-card {
            border-top: 8px solid #0d6efd;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2d3748;
        }
        
        .form-control, .form-select {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem 0 0 0.5rem;
        }
        
        .contact-icon {
            background-color: #e9ecef;
            border-radius: 0.5rem;
            padding: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0d6efd;
            font-size: 1.25rem;
        }
        
        .form-section-title {
            font-weight: 700;
            color: #0d6efd;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
        }  
        
        
        .preview-box {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.5rem;
            border: 1px dashed #dee2e6;
            margin-top: 1rem;
        }
        
        .preview-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .preview-item i {
            width: 40px;
            height: 40px;
            background-color: #e9ecef;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: #0d6efd;
        }
        
        .social-input-group {
            position: relative;
        }
        
        .social-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 10;
        }
        
        .social-input {
            padding-left: 45px;
        }
        .form-section-title i {
    padding-right: 1rem; /* Added padding */
}

@media (max-width: 576px) {
    .preview-box {
        padding: 1rem;
    }

    .preview-item {
        flex-direction: column;
        align-items: flex-start;
        font-size: 0.85rem;
    }

    .preview-item i {
        font-size: 1rem;
        margin-bottom: 0.5rem;
    }

    .preview-item div {
        width: 100%;
    }
}

/* Cancel button styles */
.cancel-btn {
    display: none;
    margin-left: 10px;
}

/* Make buttons inline */
.form-buttons {
    display: flex;
    justify-content: center;
    align-items: center;
}

@media (max-width: 576px) {
    .form-buttons {
        flex-direction: column;
        gap: 10px;
    }
    
    .form-buttons .btn {
        width: 100%;
    }
    
    .cancel-btn {
        margin-left: 0 !important;
        margin-top: 10px;
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
                                           <a class="collapse-item active" href="Edit_Contact.php">Contact</a>
                                        <a class="collapse-item" href="Edit_Gallery.php">Gallery</a>
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

                   
               <!-- Contact Information Section -->
            <section class="contact-information bg-light py-5">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-lg-10">
                            <div class="card contact-form-card shadow p-4">
                                <h2 class="text-center text-primary mb-2" style="font-weight: 900;">
                                    Contact Information
                                </h2>
                                <hr class="mt-1 mb-5">
                                
                                <form id="contactForm" style="max-width: 100%;">
                                    <!-- Phone and Email Section -->
                                    <div class="form-section">
                                        <div class="row ">
                                            <!-- Telephone Section -->
                                            <div class="col-12 col-md-6 mb-4 mb-md-2">
                                                <h4 class="form-section-title">
                                                    <i class="fas fa-phone me-2"></i>Telephone
                                                </h4>
                                                <label class="form-label fw-semibold">School Number</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                                    <input type="tel" name="telephone_primary" class="form-control" placeholder="(123) 456-7890" pattern="[0-9() -]+" required value="<?= $telephone ?>">
                                                </div>
                                            </div>

                                            <!-- Email Section -->
                                            <div class="col-12 col-md-6 mb-4 mb-md-2">
                                                <h4 class="form-section-title">
                                                    <i class="fas fa-envelope me-2"></i>Email
                                                </h4>
                                                <label class="form-label fw-semibold">School Email</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">@</span>
                                                    <input type="email" name="email_general" class="form-control" placeholder="info@school.edu.ph" required value="<?= $email ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Facebook Section -->
                                    <div class="form-section">
                                        <h4 class="form-section-title">
                                            <i class="fab fa-facebook me-2"></i>Facebook
                                        </h4>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Facebook Page Name</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fab fa-facebook-f"></i></span>
                                                    <input type="text" name="fb_page" class="form-control" placeholder="Tomas Bautista Elementary" required value="<?= $fb_page ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold">Facebook Link</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-link"></i></span>
                                                    <input type="url" name="fb_link" class="form-control" placeholder="https://facebook.com/yourpage" required value="<?= $fb_link ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Address Section -->
                                    <div class="form-section">
                                        <h4 class="form-section-title">
                                            <i class="fas fa-map-marker-alt me-2"></i>Address
                                        </h4>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">School Address</label>
                                            <textarea name="address" class="form-control" rows="3" placeholder="Enter full school address" required><?= $address ?></textarea>
                                        </div>
                                    </div>                                                                              
                                    
                                    <!-- Submit Button -->
                                    <div class="col-md-12 text-center form-buttons">       
                                        <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">  
                                             <button type="submit" class="btn btn-primary px-4 fw-bold" id="saveChangesButton">
    <span id="saveText">Save Changes</span>
    <span class="spinner-border spinner-border-sm d-none" id="saveSpinner" role="status" aria-hidden="true"></span>
</button>                    
                                            <button type="button" class="btn btn-secondary px-4 fw-bold cancel-btn">Cancel</button>                 
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

           
                <!-- End of Contact Information Section -->
               



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

    <!-- jQuery must be included before this -->

     <script>
        $(document).ready(function() {
            // Store initial form values
            const initialValues = {
                telephone_primary: $('[name="telephone_primary"]').val(),
                email_general: $('[name="email_general"]').val(),
                fb_page: $('[name="fb_page"]').val(),
                fb_link: $('[name="fb_link"]').val(),
                address: $('[name="address"]').val()
            };

            // Function to check for changes
            function checkForChanges() {
                let hasChanges = false;
                
                // Check each field against its initial value
                if ($('[name="telephone_primary"]').val() !== initialValues.telephone_primary) hasChanges = true;
                if ($('[name="email_general"]').val() !== initialValues.email_general) hasChanges = true;
                if ($('[name="fb_page"]').val() !== initialValues.fb_page) hasChanges = true;
                if ($('[name="fb_link"]').val() !== initialValues.fb_link) hasChanges = true;
                if ($('[name="address"]').val() !== initialValues.address) hasChanges = true;
                
                // Toggle cancel button visibility
                if (hasChanges) {
                    $('.cancel-btn').show();
                } else {
                    $('.cancel-btn').hide();
                }
            }

            // Check for changes on any form input
            $('#contactForm').on('input change', 'input, textarea', checkForChanges);

            // Cancel button click handler
            $('.cancel-btn').click(function() {
                // Reset form to initial values
                $('[name="telephone_primary"]').val(initialValues.telephone_primary);
                $('[name="email_general"]').val(initialValues.email_general);
                $('[name="fb_page"]').val(initialValues.fb_page);
                $('[name="fb_link"]').val(initialValues.fb_link);
                $('[name="address"]').val(initialValues.address);
                
                // Hide cancel button
                $(this).hide();
                
                // Optional: Show a message that changes were cancelled
                Swal.fire({
                    icon: 'info',
                    title: 'Changes Cancelled',
                    text: 'Your changes have been reverted.',
                    confirmButtonColor: '#3085d6',
                });
            });

            // AJAX form submission (your existing code)
            $('#contactForm').on('submit', function(e) {
                e.preventDefault();
                
              // Show loading state
            $('#saveText').text('Saving...');
            $('#saveSpinner').removeClass('d-none');
            $('#saveChangesButton').prop('disabled', true);

                $.ajax({
                    type: 'POST',
                    url: '',
                    data: $(this).serialize() + '&ajax=true',
                    dataType: 'json',
                    success: function(response) {

                            // Reset button state
                    $('#saveText').text('Save Changes');
                    $('#saveSpinner').addClass('d-none');
                    $('#saveChangesButton').prop('disabled', false);

                        if (response.status === 'success') {
                            // Update initial values after successful save
                            initialValues.telephone_primary = $('[name="telephone_primary"]').val();
                            initialValues.email_general = $('[name="email_general"]').val();
                            initialValues.fb_page = $('[name="fb_page"]').val();
                            initialValues.fb_link = $('[name="fb_link"]').val();
                            initialValues.address = $('[name="address"]').val();
                            
                            // Hide cancel button
                            $('.cancel-btn').hide();
                            
                            // Show SweetAlert success message
                            Swal.fire({
                                icon: 'success',
                                title: 'Updated Successfully',
                                text: 'Your changes have been saved successfully!',
                                confirmButtonColor: '#3085d6',
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'An error occurred while saving your changes.',
                                confirmButtonColor: '#d33',
                            });
                        }
                    },
                    error: function() {

                      // Reset button state on error too
                    $('#saveText').text('Save Changes');
                    $('#saveSpinner').addClass('d-none');
                    $('#saveChangesButton').prop('disabled', false);

                        alert('AJAX error occurred.');
                    }
                });
            });
        });

        // Profile image upload validation
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
<?php include 'search/Search_Admin.php'; ?>
</body>

</html>