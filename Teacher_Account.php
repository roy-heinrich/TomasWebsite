<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ERROR | E_PARSE);

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

// Redirect if user is not a Teacher
if ($_SESSION['user']['user_role'] !== 'Teacher') {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
require_once 'config.php';

// Include and call the auto-absent script (converted for PDO)
include 'mark_absent_students.php';


// Get teacher's advisory section
$advisory_section = $user['advisory_section'];

// Count total students in teacher's advisory section
$total_students_stmt = $conn->prepare("SELECT COUNT(*) FROM student_tbl WHERE year_section = :advisory_section AND status = 'Active'");
$total_students_stmt->execute([':advisory_section' => $advisory_section]);
$total_students = $total_students_stmt->fetchColumn();

// Get today's date
$today = date('Y-m-d');

// Count today's absent students (AM session)
$am_absent_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT student_lrn) as am_absent_count
    FROM attendance_tbl
    WHERE date = :today
    AND morning_status = 'Absent'
    AND student_lrn IN (
        SELECT lrn FROM student_tbl WHERE year_section = :advisory_section
    )
");
$am_absent_stmt->execute([':today' => $today, ':advisory_section' => $advisory_section]);
$am_absent_count = $am_absent_stmt->fetchColumn();

// Count today's absent students (PM session)
$pm_absent_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT student_lrn) as pm_absent_count
    FROM attendance_tbl
    WHERE date = :today
    AND afternoon_status = 'Absent'
    AND student_lrn IN (
        SELECT lrn FROM student_tbl WHERE year_section = :advisory_section
    )
");
$pm_absent_stmt->execute([':today' => $today, ':advisory_section' => $advisory_section]);
$pm_absent_count = $pm_absent_stmt->fetchColumn();

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


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Dashboard</title>

    <!-- Custom fonts for this template-->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">
     <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

       <!-- Chart.js and Custom Chart Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
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

 /* New styles for loading indicators */
        .chart-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 10;
        }
        
        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

       /* Add this to your existing styles */
#todaysLogTable {
    overflow-x: hidden; /* Hide horizontal scrollbar */
}

#todaysLogTable th, 
#todaysLogTable td {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Adjust the table responsive container if needed */
.table-responsive {
    overflow-x: hidden;
}

/* Add this to your existing styles */
@media (max-width: 767.98px) {
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch; /* For smooth scrolling on iOS */
    }
    
    #todaysLogTable {
        min-width: 600px; /* Minimum width to ensure all columns are visible when scrolling */
    }
    
    /* Optional: Add a shadow to indicate scrollable area */
    .table-responsive {
        position: relative;
    }
    
    .table-responsive::after {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        width: 30px;
        height: 100%;
        background: linear-gradient(to right, rgba(255,255,255,0), rgba(255,255,255,1));
        pointer-events: none;
    }
}

@media (max-width: 576px) {
    .alert {
        padding: 0.75rem;
    }
    
    .alert .btn {
        white-space: nowrap;
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
    TEACHER
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
        <small class="admin-role"><?= htmlspecialchars($user['advisory_section']) ?></small>
    </div>
</div>

             <hr class="sidebar-divider">
              <!-- Nav Item - Dashboard -->
                <li class="nav-item active"> 
                    <a class="nav-link" href="Teacher_Account.php">
                         <i class="fas fa-tachometer-alt"></i> 
                         <span>Dashboard</span></a>
                         </li>
                                                                           
                                                <!-- Nav Item - Student Records -->
                                                  <li class="nav-item"> 
                                                    <a class="nav-link" href="My_Students.php">
                                                         <i class="fas fa-user-graduate"></i> 
                                                         <span>My Students</span></a>
                                                         </li>

                                                        <!-- Nav Item - Student Attendance -->
                                                           <li class="nav-item"> 
                                                    <a class="nav-link" href="Section_Logs.php">
                                                         <i class="fas fa-list-alt"></i> 
                                                         <span>Attendance Logs</span></a>
                                                         </li>

                                                          <li class="nav-item"> 
                                                    <a class="nav-link" href="AbsentReports.php">
                                                         <i class="fas fa-edit"></i> 
                                                         <span>Absent Reports</span></a>
                                                         </li>

                                                            <li class="nav-item"> 
                                                    <a class="nav-link" href="barcode_scanner.php">
                                                         <i class="fas fa-calendar-week"></i> 
                                                         <span>Scan Attendance</span></a>
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

                <!-- Begin Page Content --> 
                <div class="container-fluid">                
                              
                     
           <div class="d-sm-flex align-items-center justify-content-between mb-4">
  <div class="d-flex align-items-center">
    <i class="fas fa-chart-area" style="color: rgb(11, 104, 245); font-size: 1.3rem; margin-right: 0.8rem;"></i>
    <h2 class="fw-bolder mb-0" style="color: rgb(11, 104, 245); font-size: 1.5rem; font-weight: 800;">Dashboard</h2>
  </div>
</div>


<!-- Manual trigger for marking absences -->
<div class="alert alert-warning d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between mb-3" role="alert">
    <div class="mb-2 mb-md-0 flex-grow-1" style="font-size: 0.85rem;">
        <i class="fas fa-info-circle me-1"></i>
        Note: To ensure attendance records are up to date (12:30PM / 5:30PM auto-marking), click "Update Absences Now". Running this may take few seconds.
    </div>
    <div class="d-flex align-items-center ms-md-2">
        <button id="runMarkAbsentBtn" class="btn btn-sm btn-primary me-2">Update Absences Now</button>
    </div>
</div>

<!-- Content Row -->
        <div class="row g-4">
                     
                       <!-- Total Students Card -->
<div class="col-xl-4 col-md-6 mb-4">
    <div class="card shadow h-100 py-3 px-2" style="border-left: 8px solid #1cc88a;">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col mr-3">
                    <div class="text-sm text-success text-uppercase mb-2" style="font-size: 1.2rem; font-weight: 800;">
                        Total Students
                    </div>
                    <div class="mb-0 text-gray-800" style="font-size: 2rem; font-weight: 700;">
                        <?= $total_students ?>
                    </div>
                </div>
                <div class="col-auto pe-3">
                    <i class="fas fa-id-card fa-4x text-success"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Today's Absent Card -->
<div class="col-xl-4 col-md-6 mb-4">
    <div class="card shadow h-100 py-3 px-2" style="border-left: 8px solid #007bff;">
        <!-- AM/PM Toggle Button -->
        <div class="position-absolute" style="top: 10px; right: 10px;">
            <div class="btn-group btn-group-sm" role="group" aria-label="AM/PM Toggle">
                <button type="button" class="btn btn-outline-primary active py-1 px-2 session-toggle" data-session="am" style="font-size: 0.7rem;">AM</button>
                <button type="button" class="btn btn-outline-primary py-1 px-2 session-toggle" data-session="pm" style="font-size: 0.7rem;">PM</button>
            </div>
        </div>
        
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col mr-3">
                    <div class="text-sm text-primary text-uppercase mb-2" style="font-size: 1.2rem; font-weight: 800;">
                        Absent today
                    </div>
                    <div class="mb-0 text-gray-800" id="absentCount" style="font-size: 2rem; font-weight: 700;">
                        <?= $am_absent_count ?>
                    </div>
                </div>
                <div class="col-auto pe-3">
                    <i class="fas fa-user-clock fa-4x text-danger"></i>
                </div>
            </div>
        </div>
    </div>
</div>

                       <!-- Today's Attendance Card -->
<div class="col-xl-4 col-md-6 mb-4">
    <div class="card shadow h-100 py-3 px-2 position-relative" style="border-left: 8px solid #36b9cc;">
        <!-- AM/PM Toggle Button -->
        <div class="position-absolute" style="top: 10px; right: 10px;">
            <div class="btn-group btn-group-sm" role="group" aria-label="AM/PM Toggle">
                <button type="button" class="btn btn-outline-info active py-1 px-2 session-toggle" data-session="am" style="font-size: 0.7rem;">AM</button>
                <button type="button" class="btn btn-outline-info py-1 px-2 session-toggle" data-session="pm" style="font-size: 0.7rem;">PM</button>
            </div>
        </div>
        
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col mr-3">
                    <div class="text-sm text-info text-uppercase mb-2" style="font-size: 1.2rem; font-weight: 800;">
                        Today's Attendance
                    </div>
                    <div class="mb-0 text-gray-800" id="attendancePercentage" style="font-size: 2rem; font-weight: 700;">
                        Loading...
                    </div>
                </div>
                <div class="col-auto pe-3">
                    <i class="fas fa-check-circle fa-4x text-info"></i>
                </div>
            </div>
        </div>
    </div>
</div>
                    </div>

                    <!-- Content Row -->
                    <div class="row">
                        <!-- Attendance Trend (Line Chart) -->
                        <div class="col-xl-8 col-lg-6 mb-3">
                            <div class="card shadow h-100">
                                <div class="card-header py-3 d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between" style="background-color: #1cc88a;">
                                    <h6 class="m-0 font-weight-bold text-white">Attendance Trend (Past 7 Days)</h6>
                                    <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                                <span class="text-white"><?= htmlspecialchars($user['advisory_section']) ?></span>
                                </div>
                                </div>
                                <div class="card-body position-relative">
                                    <div class="chart-loading" id="trendChartLoading">
                                        <div class="loading-spinner"></div>
                                        <span>Loading data...</span>
                                    </div>
                                    <div class="chart-area">
                                        <canvas id="attendanceTrendChart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                      <!-- Attendance Status Today Chart -->
<div class="col-xl-4 col-lg-6 mb-3">
    <div class="card shadow h-100">
        <div class="card-header py-3 d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between" style="background-color: #4e73df;">
            <h6 class="m-0 font-weight-bold text-white">Attendance Status Today</h6>
            <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                <select id="todaySessionFilter" class="form-control form-control-sm" style="width: 65px;">
                    <option value="AM">AM</option>
                    <option value="PM">PM</option>
                </select>
            </div>
        </div>
        <div class="card-body position-relative">
            <div class="chart-loading" id="todayChartLoading">
                <div class="loading-spinner"></div>
                <span>Loading data...</span>
            </div>
            <div class="chart-pie pt-4 pb-2">
                <canvas id="attendanceTodayChart"></canvas>
            </div>
            <div class="mt-4 text-center small">
                <span class="mr-2">
                    <i class="fas fa-circle text-success"></i> Present
                </span>
                <span class="mr-2">
                    <i class="fas fa-circle text-danger"></i> Absent
                </span>
                <span class="mr-2">
                    <i class="fas fa-circle text-secondary"></i> Not Marked
                </span>
            </div>
        </div>
    </div>
</div>
                    </div>




  <!-- Row 3: Charts Section -->
                     <div class="d-flex justify-content-center">
                    <div class="row mt-4 w-100 justify-content-center">
                  
                
                      <!-- Attendance Status by Session (Dual Pie Charts) -->
<div class="col-xl-7 col-lg-6 mb-4">
    <div class="card shadow h-100">
        <div class="card-header py-3 d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between" style="background-color: #4e73df;">
            <h6 class="m-0 font-weight-bold text-white">Attendance by Session</h6>
            <!-- Removed filter dropdown, only show "Today" -->
            <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                <span class="text-white">Today</span>
            </div>
        </div>
        <div class="card-body position-relative">
            <div class="chart-loading" id="sessionChartLoading">
                <div class="loading-spinner"></div>
                <span>Loading data...</span>
            </div>
            <div class="row">
                <div class="col-6">
                    <h6 class="text-center text-info">AM Session</h6>
                    <div class="chart-pie pt-2">
                        <canvas id="amSessionChart" height="180"></canvas>
                    </div>
                </div>
                <div class="col-6">
                    <h6 class="text-center text-info">PM Session</h6>
                    <div class="chart-pie pt-2">
                        <canvas id="pmSessionChart" height="180"></canvas>
                    </div>
                </div>
            </div>
            <div class="mt-3 text-center small">
                <span class="mr-2">
                    <i class="fas fa-circle text-success"></i> Present
                </span>
                <span class="mr-2">
                    <i class="fas fa-circle text-danger"></i> Absent
                </span>
                <span class="mr-2">
                    <i class="fas fa-circle text-secondary"></i> Not Marked
                </span>
            </div>
        </div>
    </div>
</div>


                                           <!-- Gender Distribution (Pie Chart) -->
<div class="col-xl-5 col-lg-6 mb-4">
    <div class="card shadow h-100">
        <div class="card-header py-3 d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between" style="background-color: #1cc88a;">
            <h6 class="m-0 font-weight-bold text-white">Gender Distribution</h6>
            <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                <span class="text-white"><?= htmlspecialchars($user['advisory_section']) ?></span>
            </div>
        </div>
        <div class="card-body position-relative">
            <div class="chart-loading" id="genderChartLoading">
                <div class="loading-spinner"></div>
                <span>Loading data...</span>
            </div>
            <div class="chart-pie pt-4 pb-2">
                <canvas id="genderDistributionChart" height="250"></canvas>
            </div>
            <div class="mt-4 text-center small">
                <span class="mr-2">
                    <i class="fas fa-circle text-primary"></i> Male
                </span>
                <span class="mr-2">
                    <i class="fas fa-circle text-danger"></i> Female
                </span>
            </div>
        </div>
    </div>
</div>


                    </div>         
              </div>




             <!-- Today's Logged Container -->
<div class="row mt-4">
    <div class="col-xl-12 col-lg-12">
        <div class="card shadow mb-3">
            <!-- Card Header -->
            <div class="card-header py-3 d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between" style="background-color: #4e73df;">
                <h6 class="m-0 font-weight-bold text-white mb-2 mb-md-0"><i class="fas fa-calendar-check mr-2 text-white"></i>Today's Logged Attendance</h6>
                  <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
                                <span class="text-white"><?= htmlspecialchars($user['advisory_section']) ?></span>
                                </div>
            </div>

            <!-- Card Body -->
            <div class="card-body">
                <div class="table-responsive">
                    <!-- Loading indicator -->
                    <div id="tableLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-2">Loading attendance data...</p>
                    </div>
                    
                    <!-- Fallback Message -->
                    <div id="noDataMessage" class="text-center text-muted py-4" style="display: none;">
                        <i class="fas fa-info-circle fa-2x"></i><br>
                        <span>No attendance records logged today.</span>
                    </div>

                    <!-- Attendance Table -->
                    <table class="table table-bordered" id="todaysLogTable" style="display: none; width: 100%;">
                        <thead class="table-light">
                            <tr class="text-center align-middle">
                                <th style="width: 100px;">LRN</th>
                                <th style="width: 100px;">Date</th>
                                <th style="width: 200px;">Student Name</th>
                                <th style="width: 120px;">Year & Section</th>
                                <th style="width: 90px;">AM Login</th>
                                <th style="width: 90px;">AM Logout</th>
                                <th style="width: 90px;">PM Login</th>
                                <th style="width: 90px;">PM Logout</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Rows will be populated by AJAX -->
                        </tbody>
                    </table>
                </div>
                <div class="row mt-3" id="paginationContainer" style="display: none;">
                    <div class="col-md-6">
                        <div class="dataTables_info" id="showingEntries">Showing 0 to 0 of 0 entries</div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="mr-3">
                                <span>Show:</span>
                                <select id="entriesPerPage" class="form-control form-control-sm d-inline-block" style="width: 70px;">
                                    <option value="5">5</option>
                                    <option value="10" selected>10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                </select>
                                <span>entries</span>
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm mb-0" id="pagination">
                                    <!-- Pagination buttons will be added here by JavaScript -->
                                </ul>
                            </nav>
                        </div>
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="js/sb-admin-2.min.js"></script>

    
     
    <!-- Page level custom scripts -->
    <script src="js/demo/chart-area-demo.js"></script>


    <script>
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

// Handle AM/PM toggle for absent count
$('.session-toggle').click(function() {
    $('.session-toggle').removeClass('active');
    $(this).addClass('active');
    
    const session = $(this).data('session');
    if (session === 'am') {
        $('#absentCount').text('<?= $am_absent_count ?>');
    } else {
        $('#absentCount').text('<?= $pm_absent_count ?>');
    }
      // Also update the attendance percentage
    updateAttendancePercentage(session.toUpperCase());
});

// Handle AM/PM toggle for attendance percentage
$('.session-toggle[data-session="am"], .session-toggle[data-session="pm"]').click(function() {
    const session = $(this).data('session').toUpperCase();
    updateAttendancePercentage(session);
});

// Function to update attendance percentage
function updateAttendancePercentage(session) {
    $.ajax({
        url: 'ajax/get_teacher_attendance_percentage.php',
        type: 'GET',
        data: { session: session },
        success: function(response) {
            $('#attendancePercentage').text(response + '%');
        },
        error: function() {
            $('#attendancePercentage').text('Error');
        }
    });
}

// Initialize with AM session percentage
$(document).ready(function() {
    updateAttendancePercentage('AM');
});

// Global variable to store the trend chart instance
let attendanceTrendChart;

$(document).ready(function() {
    // Load the attendance trend chart
    loadAttendanceTrendChart();
    
    // Set up session toggle for Today's Attendance card
    $('.session-toggle').click(function() {
        $('.session-toggle').removeClass('active');
        $(this).addClass('active');
        updateAttendancePercentage($(this).data('session').toUpperCase());
    });
});

function loadAttendanceTrendChart() {
    showLoading('trendChartLoading');
    
    $.ajax({
        url: 'ajax/get_teacher_attendance_trend.php',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            updateTrendChart(data);
            hideLoading('trendChartLoading');
        },
        error: function() {
            hideLoading('trendChartLoading');
            alert('Error loading attendance trend data');
        }
    });
}

function updateTrendChart(data) {
    const ctx = document.getElementById('attendanceTrendChart').getContext('2d');
    
    if (attendanceTrendChart) {
        attendanceTrendChart.destroy();
    }
    
    attendanceTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Attendance Percentage',
                data: data.percentages,
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: false,
                    min: 10,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    },
                    title: {
                        display: true,
                        text: 'Attendance Percentage'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Past 7 Days'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Attendance: ${context.parsed.y}%`;
                        }
                    }
                }
            }
        }
    });
}

function showLoading(elementId) {
    $('#' + elementId).show();
}

function hideLoading(elementId) {
    $('#' + elementId).hide();
}

// Function to update attendance percentage 
function updateAttendancePercentage(session) {
    $.ajax({
        url: 'ajax/get_teacher_attendance_percentage.php',
        type: 'GET',
        data: { session: session },
        success: function(response) {
            $('#attendancePercentage').text(response + '%');
        },
        error: function() {
            $('#attendancePercentage').text('Error');
        }
    });
}



// Global variable to store the today chart instance
let attendanceTodayChart;

function loadAttendanceTodayChart() {
    // Clear any previous messages first
    clearPreviousMessages('todayChartLoading');
    
    // Show loading state
    showLoading('todayChartLoading');
    
    const session = $('#todaySessionFilter').val();
    
    $.ajax({
        url: 'ajax/get_teacher_attendance_today_distribution.php',
        type: 'GET',
        data: { session: session },
        dataType: 'json',
        success: function(data) {
            // Always hide loading first
            hideLoading('todayChartLoading');
            
            if (data.has_data || data.total_students === 0) {
                updateTodayChart(data);
            } else {
                showNoDataMessage('todayChartLoading', 'No attendance recorded yet for today');
            }
        },
        error: function() {
            hideLoading('todayChartLoading');
            showNoDataMessage('todayChartLoading', 'Error loading attendance data');
        }
    });
}

function clearPreviousMessages(elementId) {
    const container = document.getElementById(elementId);
    
    // Remove any existing no-data message
    const noDataDiv = container.querySelector('.no-data-message');
    if (noDataDiv) {
        noDataDiv.remove();
    }
    
    // Make sure chart canvas is visible (will be hidden later if needed)
    const chartCanvas = container.nextElementSibling.querySelector('canvas');
    if (chartCanvas) {
        chartCanvas.style.display = 'block';
    }
}

function showNoDataMessage(elementId, message) {
    const container = document.getElementById(elementId);
    
    // Create the no-data message
    const noDataDiv = document.createElement('div');
    noDataDiv.className = 'no-data-message text-center py-4';
    noDataDiv.innerHTML = `
        <i class="fas fa-info-circle fa-2x text-muted"></i>
        <p class="mt-2 text-muted">${message}</p>
    `;
    
    // Clear container and add message
    container.innerHTML = '';
    container.appendChild(noDataDiv);
    
    // Hide the chart canvas
    const chartCanvas = container.nextElementSibling.querySelector('canvas');
    if (chartCanvas) {
        chartCanvas.style.display = 'none';
    }
    
    // Make sure container is visible
    container.style.display = 'block';
}

function updateTodayChart(data) {
    const container = document.getElementById('todayChartLoading');
    const chartCanvas = container.nextElementSibling.querySelector('canvas');
    
    // Clear any messages
    container.innerHTML = '';
    container.style.display = 'none'; // Hide the loading container
    
    // Make sure canvas is visible
    if (chartCanvas) {
        chartCanvas.style.display = 'block';
    }
    
    const ctx = chartCanvas.getContext('2d');
    
    if (attendanceTodayChart) {
        attendanceTodayChart.destroy();
    }
    
    attendanceTodayChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Absent', 'Not Marked'],
            datasets: [{
                data: [data.present, data.absent, data.not_marked],
                backgroundColor: ['#1cc88a', '#e74a3b', '#858796'],
                hoverBackgroundColor: ['#17a673', '#be2617', '#60616f'],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            let count = 0;
                            
                            // Get the count based on the label
                            switch(label) {
                                case 'Present':
                                    count = data.present_count;
                                    break;
                                case 'Absent':
                                    count = data.absent_count;
                                    break;
                                case 'Not Marked':
                                    count = data.not_marked_count;
                                    break;
                            }
                            
                            return [
                                ` ${label}: ${value}%`,
                                `${count} students`
                            ];
                        }
                    }
                }
            }
        }
    });
}

// Add this to your document.ready function
$(document).ready(function() {
    // Initialize the chart
    loadAttendanceTodayChart();
    
    // Set up filter change listener
    $('#todaySessionFilter').change(loadAttendanceTodayChart);
});



// Global variables for pagination
let currentPage = 1;
let perPage = 10;
let totalRecords = 0;
let totalPages = 1;

function loadTodaysAttendanceTable(resetPage = true) {
    if (resetPage) {
        currentPage = 1; // Reset to first page when filter changes
    }
    
    $('#tableLoading').show();
    $('#noDataMessage').hide();
    $('#todaysLogTable').hide();
    $('#paginationContainer').hide();
    
    $.ajax({
        url: 'ajax/get_teacher_todays_logged_attendance.php',
        type: 'GET',
        data: { 
            page: currentPage,
            per_page: perPage
        },
        dataType: 'json',
        success: function(response) {
            populateAttendanceTable(response);
            $('#tableLoading').hide();
            
            // Update pagination info
            totalRecords = response.total;
            totalPages = response.total_pages;
            updatePaginationControls();
        },
        error: function() {
            $('#tableLoading').hide();
            $('#noDataMessage').show();
        }
    });
}

function populateAttendanceTable(response) {
    const tbody = $('#todaysLogTable tbody');
    tbody.empty();
    
    if (response.data.length === 0) {
        $('#noDataMessage').show();
        $('#paginationContainer').hide();
        return;
    }
    
    response.data.forEach(row => {
        const tr = $('<tr class="text-center align-middle">');
        tr.append(`<td>${row.lrn}</td>`);
        tr.append(`<td>${row.formatted_date}</td>`);
        tr.append(`<td>${row.name}</td>`);
        tr.append(`<td>${row.section}</td>`);
        tr.append(`<td>${row.am_login || '-'}</td>`);
        tr.append(`<td>${row.am_logout || '-'}</td>`);
        tr.append(`<td>${row.pm_login || '-'}</td>`);
        tr.append(`<td>${row.pm_logout || '-'}</td>`);
        tbody.append(tr);
    });
    
    $('#todaysLogTable').show();
    $('#paginationContainer').show();
}

function updatePaginationControls() {
    const pagination = $('#pagination');
    pagination.empty();
    
    // Show entries info
    const start = ((currentPage - 1) * perPage) + 1;
    const end = Math.min(currentPage * perPage, totalRecords);
    $('#showingEntries').text(`Showing ${start} to ${end} of ${totalRecords} entries`);
    
    // Previous button
    const prevBtn = $(`<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" aria-label="Previous" ${currentPage === 1 ? 'tabindex="-1"' : ''}>
            <span aria-hidden="true">&laquo;</span>
        </a>
    </li>`);
    prevBtn.click(function(e) {
        e.preventDefault();
        if (currentPage > 1) {
            currentPage--;
            loadTodaysAttendanceTable(false);
        }
    });
    pagination.append(prevBtn);
    
    // Page numbers
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    
    if (endPage - startPage + 1 < maxVisiblePages) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    if (startPage > 1) {
        pagination.append(`<li class="page-item"><a class="page-link" href="#">1</a></li>`);
        if (startPage > 2) {
            pagination.append(`<li class="page-item disabled"><a class="page-link" href="#">...</a></li>`);
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        const pageBtn = $(`<li class="page-item ${i === currentPage ? 'active' : ''}">
            <a class="page-link" href="#">${i}</a>
        </li>`);
        pageBtn.click(function(e) {
            e.preventDefault();
            currentPage = i;
            loadTodaysAttendanceTable(false);
        });
        pagination.append(pageBtn);
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            pagination.append(`<li class="page-item disabled"><a class="page-link" href="#">...</a></li>`);
        }
        pagination.append(`<li class="page-item"><a class="page-link" href="#">${totalPages}</a></li>`);
    }
    
    // Next button
    const nextBtn = $(`<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" aria-label="Next" ${currentPage === totalPages ? 'tabindex="-1"' : ''}>
            <span aria-hidden="true">&raquo;</span>
        </a>
    </li>`);
    nextBtn.click(function(e) {
        e.preventDefault();
        if (currentPage < totalPages) {
            currentPage++;
            loadTodaysAttendanceTable(false);
        }
    });
    pagination.append(nextBtn);
}

// Add this to your document.ready function
$(document).ready(function() {
    // Initialize the table
    loadTodaysAttendanceTable();
    
    // Handle entries per page change
    $('#entriesPerPage').change(function() {
        perPage = parseInt($(this).val());
        currentPage = 1;
        loadTodaysAttendanceTable(false);
    });
});

// Global variable to store the gender chart instance
let genderDistributionChart;

// Function to load gender distribution data
function loadGenderDistributionChart() {
    showLoading('genderChartLoading');
    
    $.ajax({
        url: 'ajax/get_teacher_gender_distribution.php',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            updateGenderChart(data);
            hideLoading('genderChartLoading');
        },
        error: function() {
            hideLoading('genderChartLoading');
            alert('Error loading gender distribution data');
        }
    });
}

// Function to update the gender chart (pie style)
function updateGenderChart(data) {
    const ctx = document.getElementById('genderDistributionChart').getContext('2d');
    const total = data.male + data.female;
    
    if (genderDistributionChart) {
        genderDistributionChart.destroy();
    }
    
    genderDistributionChart = new Chart(ctx, {
        type: 'pie', // Changed from 'doughnut' to 'pie'
        data: {
            labels: ['Male', 'Female'],
            datasets: [{
                data: [data.male, data.female],
                backgroundColor: ['#4e73df', '#e74a3b'],
                hoverBackgroundColor: ['#2e59d9', '#be2617'],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed;
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return `${label}: ${value} students (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

// Add this to your document.ready function to initialize the chart
$(document).ready(function() {
    loadGenderDistributionChart();
});

// Global variables to store chart instances
let amSessionChart, pmSessionChart;

// Initialize the charts when the page loads
$(document).ready(function() {
    loadSessionCharts();
    
});

function loadSessionCharts() {
    showLoading('sessionChartLoading');
    // Always use 'today' for period
    $.ajax({
        url: 'ajax/get_teacher_attendance_by_session.php',
        type: 'GET',
        data: { period: 'today' },
        dataType: 'json',
        success: function(data) {
            updateSessionCharts(data);
            hideLoading('sessionChartLoading');
        },
        error: function() {
            hideLoading('sessionChartLoading');
            const container = document.getElementById('sessionChartLoading');
            container.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-exclamation-triangle text-danger"></i>
                    <p class="mt-2 text-danger">Error loading session data</p>
                </div>
            `;
        }
    });
}

function updateSessionCharts(data) {
    const container = document.getElementById('sessionChartLoading');
    const chartContainer = container.nextElementSibling;
    
    // Clear any messages
    container.innerHTML = '';
    container.style.display = 'none';
    
    // Update AM Session Chart
    updateSingleSessionChart('amSessionChart', data.am, 'AM Session');
    
    // Update PM Session Chart
    updateSingleSessionChart('pmSessionChart', data.pm, 'PM Session');
}

function updateSingleSessionChart(chartId, data, title) {
    const ctx = document.getElementById(chartId).getContext('2d');
    
    // Destroy previous chart if it exists
    if (chartId === 'amSessionChart' && amSessionChart) {
        amSessionChart.destroy();
    } else if (chartId === 'pmSessionChart' && pmSessionChart) {
        pmSessionChart.destroy();
    }
    
    // Create new chart
    const newChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Absent', 'Not Marked'],
            datasets: [{
                data: [data.present, data.absent, data.not_marked],
                backgroundColor: ['#1cc88a', '#e74a3b', '#858796'],
                hoverBackgroundColor: ['#17a673', '#be2617', '#60616f'],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed;
                            let count = 0;
                            // Get the count based on the label
                            switch(label) {
                                case 'Present':
                                    count = data.present_count;
                                    break;
                                case 'Absent':
                                    count = data.absent_count;
                                    break;
                                case 'Not Marked':
                                    count = data.not_marked_count;
                                    break;
                            }
                            // Only show percentage and number of students (like Attendance Status Today)
                            return [
                                ` ${label}: ${value}%`,
                                `${count} students`
                            ];
                        }
                    }
                }
            }
        }
    });
    
    // Store the chart instance
    if (chartId === 'amSessionChart') {
        amSessionChart = newChart;
    } else {
        pmSessionChart = newChart;
    }
}

// Helper functions
function showLoading(elementId) {
    $('#' + elementId).show();
}

function hideLoading(elementId) {
    $('#' + elementId).hide();
}

$('#profileForm').on('submit', function() {
    $('#saveProfileBtn').prop('disabled', true);
    $('#saveProfileBtn .btn-text').hide();
    $('#saveProfileBtn').append('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
});

// Handler for manual run mark absent
$('#runMarkAbsentBtn').on('click', function() {
    Swal.fire({
        title: 'Confirm Update',
        text: "This operation may take a few seconds to complete. Proceed?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, update now!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading SweetAlert
            Swal.fire({
                title: 'Updating Absences',
                html: 'Please wait while we update the absences...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'run_mark_absent.php',
                type: 'POST',
                dataType: 'json',
                timeout: 120000, // 2 minutes
                success: function(resp) {
                    Swal.close();
                    if (resp.success) {
                        // Store the last run time in localStorage
                        const now = new Date().toLocaleString();
                        localStorage.setItem('lastMarkAbsentRun', now);
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: resp.message || 'Absences updated successfully.',
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            // Refresh the page after success
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: resp.message || 'An error occurred while updating absences.'
                        });
                    }
                },
                error: function(xhr, status, err) {
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Request failed or timed out. Please try again.'
                    });
                }
            });
        }
    });
});


</script>
 <?php include 'search/Search_Teacher.php'; ?>
</body>

</html>   