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

// Redirect if user is not a Teacher
if ($_SESSION['user']['user_role'] !== 'Teacher') {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$advisory_section = $user['advisory_section'];

require_once 'config.php';

// Export functionality
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $filters = $_GET;
    $filters['year_section'] = $advisory_section;

    // Build query and params
    $query = "SELECT a.date AS log_date, s.lrn, s.stud_name, s.gender, s.year_section, 
            a.morning_status, a.am_login_time, a.am_logout_time, a.afternoon_status, a.pm_login_time, a.pm_logout_time
        FROM attendance_tbl a
        INNER JOIN student_tbl s ON a.student_lrn = s.lrn
        WHERE s.year_section = :year_section";
    $params = [':year_section' => $advisory_section];

    // Date range filter
    if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
        $query .= " AND a.date BETWEEN :from_date AND :to_date";
        $params[':from_date'] = $filters['from_date'];
        $params[':to_date'] = $filters['to_date'];
    }

    // Gender filter
    if (!empty($filters['gender'])) {
        $query .= " AND s.gender = :gender";
        $params[':gender'] = $filters['gender'];
    }

    // AM status filter
    if (!empty($filters['am_status'])) {
        $query .= " AND a.morning_status = :am_status";
        $params[':am_status'] = $filters['am_status'];
    }

    // PM status filter
    if (!empty($filters['pm_status'])) {
        $query .= " AND a.afternoon_status = :pm_status";
        $params[':pm_status'] = $filters['pm_status'];
    }

    // Keyword search
    if (!empty($filters['keyword'])) {
        $query .= " AND (s.lrn ILIKE :keyword OR s.stud_name ILIKE :keyword)";
        $params[':keyword'] = '%' . $filters['keyword'] . '%';
    }

    $query .= " ORDER BY a.date DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $attendance_data = $stmt->fetchAll();

    if ($export_type === 'excel') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="Section_Logs.csv"');
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        $headers = [
            'Date', 'LRN', 'Student Name', 'Gender', 'Year & Section',
            'AM Status', 'AM Login', 'AM Logout', 'PM Status', 'PM Login', 'PM Logout'
        ];
        fputcsv($output, $headers);
        foreach ($attendance_data as $row) {
            $am_login = $row['am_login_time'] ? date("g:i A", strtotime($row['am_login_time'])) : '';
            $am_logout = $row['am_logout_time'] ? date("g:i A", strtotime($row['am_logout_time'])) : '';
            $pm_login = $row['pm_login_time'] ? date("g:i A", strtotime($row['pm_login_time'])) : '';
            $pm_logout = $row['pm_logout_time'] ? date("g:i A", strtotime($row['pm_logout_time'])) : '';
            fputcsv($output, [
                "\t" . $row['log_date'],
                "\t" . $row['lrn'],
                $row['stud_name'],
                $row['gender'],
                $row['year_section'],
                $row['morning_status'],
                $am_login,
                $am_logout,
                $row['afternoon_status'],
                $pm_login,
                $pm_logout
            ]);
        }
        fclose($output);
        exit;
    } elseif ($export_type === 'pdf') {
        require_once('tcpdf/tcpdf.php');
        class MYPDF extends TCPDF {
            public function Header() {
                $image_file = 'images/hdd1.jpg';
                $this->Image($image_file, 70, 10, 18, 18, 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
                $this->SetFont('helvetica', '', 10);
                $this->SetXY(5, 10);
                $this->Cell(0, 5, 'Department of Education', 0, 1, 'C');
                $this->SetX(5);
                $this->Cell(0, 5, 'Region VI Western Visayas', 0, 1, 'C');
                $this->SetX(5);
                $this->Cell(0, 5, 'Division of Aklan', 0, 1, 'C');
                $this->SetX(5);
                $this->Cell(0, 5, 'District of New Washington', 0, 1, 'C');
                $this->SetFont('helvetica', 'B', 10);
                $this->Cell(0, 6, 'TOMAS SM. BAUTISTA ELEMENTARY SCHOOL', 0, 1, 'C');
                $this->Ln(2);
                $this->SetFont('helvetica', 'B', 11);
                $this->Cell(0, 6, 'Attendance Logs', 0, 1, 'C');
                if (!empty($GLOBALS['filters']['from_date']) && !empty($GLOBALS['filters']['to_date'])) {
                    $dateRange = 'Date: ' . date('M d, Y', strtotime($GLOBALS['filters']['from_date'])) .
                        ' to ' . date('M d, Y', strtotime($GLOBALS['filters']['to_date']));
                    $this->SetFont('helvetica', 'I', 9);
                    $this->Cell(0, 5, $dateRange, 0, 1, 'C');
                }
                $this->SetY(47);
            }
            public function Footer() {
                $this->SetY(-15);
                $this->SetFont('helvetica', 'I', 8);
                $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
            }
        }
        $pdf = new MYPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Tomas SM. Bautista ES');
        $pdf->SetAuthor('Teacher');
        $pdf->SetTitle('Attendance Logs');
        $pdf->SetSubject('Attendance Report');
        $pdf->SetMargins(10, 50, 10);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 25);
        $pdf->AddPage();
    $header = ['Date', 'LRN', 'Student Name', 'Gender', 'Year & Section', 'AM Status', 'AM Login', 'AM Logout', 'PM Status', 'PM Login', 'PM Logout'];
    $pdf->SetFillColor(65, 105, 225);
    $pdf->SetTextColor(255);
    $pdf->SetDrawColor(65, 105, 225);
    $pdf->SetLineWidth(0.3);
    $pdf->SetFont('helvetica', 'B', 10);
    $w = [20, 26, 46, 16, 34, 20, 20, 20, 20, 20, 20];
        $totalWidth = array_sum($w);
        $pageWidth = $pdf->getPageWidth();
        $margin = ($pageWidth - $totalWidth) / 2;
        $pdf->SetLeftMargin($margin);
        $pdf->SetX($margin);
        for ($i = 0; $i < count($header); $i++) {
            $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0);
        $pdf->SetFont('helvetica', '', 10);
        $fill = false;
        foreach ($attendance_data as $row) {
            $am_login = $row['am_login_time'] ? date("g:i A", strtotime($row['am_login_time'])) : '';
            $am_logout = $row['am_logout_time'] ? date("g:i A", strtotime($row['am_logout_time'])) : '';
            $pm_login = $row['pm_login_time'] ? date("g:i A", strtotime($row['pm_login_time'])) : '';
            $pm_logout = $row['pm_logout_time'] ? date("g:i A", strtotime($row['pm_logout_time'])) : '';
            if ($pdf->GetY() > $pdf->getPageHeight() - 30) {
                $pdf->AddPage();
                $pdf->SetX($margin);
                $pdf->SetFillColor(65, 105, 225);
                $pdf->SetTextColor(255);
                for ($i = 0; $i < count($header); $i++) {
                    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
                }
                $pdf->Ln();
                $pdf->SetFillColor(255, 255, 255);
                $pdf->SetTextColor(0);
                $fill = false;
            }
            $pdf->SetX($margin);
            $pdf->Cell($w[0], 6, $row['log_date'], 'LR', 0, 'C', $fill);
            $pdf->Cell($w[1], 6, $row['lrn'], 'LR', 0, 'C', $fill);
            $pdf->Cell($w[2], 6, $row['stud_name'], 'LR', 0, 'L', $fill);
            $pdf->Cell($w[3], 6, $row['gender'], 'LR', 0, 'C', $fill);
            $pdf->Cell($w[4], 6, $row['year_section'], 'LR', 0, 'C', $fill);
            $pdf->Cell($w[5], 6, $row['morning_status'], 'LR', 0, 'C', $fill);
            $pdf->Cell($w[6], 6, $am_login, 'LR', 0, 'C', $fill);
            $pdf->Cell($w[7], 6, $am_logout, 'LR', 0, 'C', $fill);
            $pdf->Cell($w[8], 6, $row['afternoon_status'], 'LR', 0, 'C', $fill);
            $pdf->Cell($w[9], 6, $pm_login, 'LR', 0, 'C', $fill);
            $pdf->Cell($w[10], 6, $pm_logout, 'LR', 0, 'C', $fill);
            $pdf->Ln();
            $fill = !$fill;
        }
        $pdf->SetX($margin);
        $pdf->Cell(array_sum($w), 0, '', 'T');
        $pdf->Output('Section_Logs.pdf', 'D');
        exit;
    }
}

// Filters
$filters = $_GET;
$where = "WHERE s.year_section = :year_section";
$params = [':year_section' => $advisory_section];

if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
    $where .= " AND a.date BETWEEN :from_date AND :to_date";
    $params[':from_date'] = $filters['from_date'];
    $params[':to_date'] = $filters['to_date'];
}
if (!empty($filters['gender'])) {
    $where .= " AND s.gender = :gender";
    $params[':gender'] = $filters['gender'];
}
if (!empty($filters['am_status'])) {
    $where .= " AND a.morning_status = :am_status";
    $params[':am_status'] = $filters['am_status'];
}
if (!empty($filters['pm_status'])) {
    $where .= " AND a.afternoon_status = :pm_status";
    $params[':pm_status'] = $filters['pm_status'];
}
if (!empty($filters['keyword'])) {
    $where .= " AND (s.lrn ILIKE :keyword OR s.stud_name ILIKE :keyword)";
    $params[':keyword'] = '%' . $filters['keyword'] . '%';
}

// Pagination
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($per_page, [5, 10, 15, 20, 25])) {
    $per_page = 10;
}
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Get total records for pagination
$count_query = "SELECT COUNT(*) AS total 
               FROM attendance_tbl a
               INNER JOIN student_tbl s ON a.student_lrn = s.lrn 
               $where";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_row = $count_stmt->fetch();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $per_page);

// Main query
$query = "SELECT a.date AS log_date, s.lrn, s.stud_name, s.gender, s.year_section, 
           a.morning_status, a.am_login_time, a.am_logout_time, a.afternoon_status, a.pm_login_time, a.pm_logout_time
       FROM attendance_tbl a
       INNER JOIN student_tbl s ON a.student_lrn = s.lrn 
       $where
       ORDER BY a.date DESC
       LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$attendance_logs = $stmt->fetchAll();


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

    <title>Attendance Logs</title>

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

   /* Filter container styling */
        .filter-container {
            background-color: #f8f9fc;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e3e6f0;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 0.5rem;
        }
        
        .filter-group {
            margin-right: 1rem;
            margin-bottom: 0.5rem;
            min-width: 180px;
        }
        
        .filter-label {
            font-weight: bold;
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
            color: #4e73df;
        }
        
        .export-btn-group {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
        }
        
        .badge-present {
            background-color: #28a745;
            padding: 0.4em 0.6em;
        }
        
        .badge-absent {
            background-color: #dc3545;
            padding: 0.4em 0.6em;
        }
        
          .badge-secondary {
        background-color: #6c757d;
        padding: 0.4em 0.6em;
    }
    
    .badge-present, .badge-absent, .badge-secondary {
        color: white !important;
    }

       
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }
        
        .entries-select {
            width: auto;
            display: inline-block;
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
                <li class="nav-item"> 
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
                                                           <li class="nav-item active"> 
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

                   <!-- Attendance Log -->
                <section id="attendancelog" class="attendance-log-list py-4">
                    <div class="container" style="max-width: 95%;">
                        <div class="card shadow" style="border-top: 8px solid #4169E1; border-radius: 1rem;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                                    <h3 class="text-start mb-2" style="color: #4169E1; font-weight: 900;">Attendance Logs</h3>
                                    <div class="d-flex">
                                        <form method="GET" action="Section_Logs.php" id="exportForm">
                                             <input type="hidden" name="year_section" value="<?= htmlspecialchars($advisory_section) ?>">
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <i class="fas fa-download"></i> Export
                                                </button>
                                                <div class="dropdown-menu">
                                                    <button type="submit" name="export" value="excel" class="dropdown-item">
                                                        <i class="fas fa-file-excel text-success"></i> Excel
                                                    </button>
                                                    <button type="submit" name="export" value="pdf" class="dropdown-item">
                                                        <i class="fas fa-file-pdf text-danger"></i> PDF
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Filter Section -->
                                <div class="filter-container">
                                    <form method="GET" action="Section_Logs.php" id="filterForm">
                                         <!-- Hidden field for teacher's section -->
                            <input type="hidden" name="year_section" value="<?= htmlspecialchars($advisory_section) ?>">
                                        <!-- First row of filters -->
                                        <div class="filter-row">
                                            <!-- Date Range -->
                                            <div class="filter-group">
                                                <div class="filter-label">Date Range</div>
                                                <div class="d-flex">
                                                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>">
                                                    <span class="mx-1 align-self-center">to</span>
                                                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>">
                                                </div>
                                            </div>
                                            
                                            <!-- Year & Section -->
                                              <div class="filter-group">
                                    <div class="filter-label">Year & Section</div>
                                    <div class="form-control form-control-sm" style="background-color: #e9ecef; opacity: 1; cursor: not-allowed;">
                                        <?= htmlspecialchars($advisory_section) ?>
                                    </div>
                                </div>
                                            
                                            <!-- Gender -->
                                            <div class="filter-group">
                                                <div class="filter-label">Gender</div>
                                                <select name="gender" class="form-control form-control-sm">
                                                    <option value="">All</option>
                                                    <option value="Male" <?= isset($_GET['gender']) && $_GET['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                                    <option value="Female" <?= isset($_GET['gender']) && $_GET['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                                </select>
                                            </div>

                                             <!-- Keyword Search -->
                                            <div class="filter-group" style="flex-grow: 1;">
                                                <div class="filter-label">Search (LRN or Name)</div>
                                                <div class="input-group">
                                                    <input type="text" name="keyword" class="form-control form-control-sm" placeholder="Search..." value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>">
                                                    <div class="input-group-append">
                                                        <button class="btn btn-primary btn-sm" type="submit">
                                                            <i class="fas fa-search"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                        
                                        <!-- Second row of filters -->
                                        <div class="filter-row ">
                                            <!-- AM Status -->
                                            <div class="filter-group">
                                                <div class="filter-label">AM Status</div>
                                                <select name="am_status" class="form-control form-control-sm">
                                                    <option value="">All</option>
                                                    <option value="Present" <?= isset($_GET['am_status']) && $_GET['am_status'] === 'Present' ? 'selected' : '' ?>>Present</option>
                                                    <option value="Absent" <?= isset($_GET['am_status']) && $_GET['am_status'] === 'Absent' ? 'selected' : '' ?>>Absent</option>
                                                </select>
                                            </div>
                                            
                                            <!-- PM Status -->
                                            <div class="filter-group">
                                                <div class="filter-label">PM Status</div>
                                                <select name="pm_status" class="form-control form-control-sm">
                                                    <option value="">All</option>
                                                    <option value="Present" <?= isset($_GET['pm_status']) && $_GET['pm_status'] === 'Present' ? 'selected' : '' ?>>Present</option>
                                                    <option value="Absent" <?= isset($_GET['pm_status']) && $_GET['pm_status'] === 'Absent' ? 'selected' : '' ?>>Absent</option>
                                                </select>
                                            </div>
                                                  

                                        </div>
                                        
                                        <!-- Submit and Reset buttons -->
                                    <div class="filter-row justify-content-end">
    <button type="submit" class="btn btn-primary btn-sm mr-2" id="applyFiltersBtn">
        <span id="applyFiltersText"><i class="fas fa-filter"></i> Apply Filters</span>
        <span id="applyFiltersLoading" style="display:none;">
            <span class="spinner-border spinner-border-sm"></span> Loading...
        </span>
    </button>
    <a href="Section_Logs.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-sync-alt"></i> Reset
    </a>
</div>
                                    </form>
                                </div>

                        <!-- Filter Note -->
                       <p style="background-color: rgba(0, 123, 255, 0.1); 
                       color: #0056b3; 
                       padding: 10px 15px; 
                       border-radius: 6px; 
                       font-weight: 500;
                       font-size: 13px;">
                    💡 Tip: Apply filters first to view specific attendance records before exporting data.
                    </p>


                                <!-- Show entries and pagination -->
                                <div class="pagination-container">
                                    <div class="d-flex align-items-center">
                                        <span class="mr-2">Show</span>
                                        <form method="GET" class="d-inline">
                                            <?php foreach ($_GET as $key => $value): ?>
                                                <?php if ($key !== 'per_page' && $key !== 'page'): ?>
                                                    <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <select name="per_page" class="form-control form-control-sm entries-select" onchange="this.form.submit()">
                                                <option value="5" <?= $per_page == 5 ? 'selected' : '' ?>>5</option>
                                                <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10</option>
                                                <option value="15" <?= $per_page == 15 ? 'selected' : '' ?>>15</option>
                                                <option value="20" <?= $per_page == 20 ? 'selected' : '' ?>>20</option>
                                                <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25</option>
                                            </select>
                                            <span class="ml-2">entries</span>
                                        </form>
                                    </div>
                                    
                                    <nav>
                                        <ul class="pagination pagination-sm">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">
                                                        <span>&laquo;</span>
                                                    </a>
                                                </li>
                                                <li class="page-item">
                                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                                        <span>&lsaquo;</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php 
                                            $start_page = max(1, min($page - 2, $total_pages - 4));
                                            $end_page = min($total_pages, $start_page + 4);
                                            
                                            for ($i = $start_page; $i <= $end_page; $i++): 
                                            ?>
                                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                                        <span>&rsaquo;</span>
                                                    </a>
                                                </li>
                                                <li class="page-item">
                                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>">
                                                        <span>&raquo;</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>

                                <!-- Log Table -->
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle text-center">
                                        <thead style="background-color: #4169E1; color: white;">
                                            <tr>
                                                <th>Date</th>
                                                <th>LRN</th>
                                                <th>Student Name</th>
                                                <th>Gender</th>
                                                <th>Year & Section</th>
                                                <th>AM Status</th>
                                                <th>AM Login</th>
                                                <th>AM Logout</th>
                                                <th>PM Status</th>
                                                <th>PM Login</th>
                                                <th>PM Logout</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                             <?php if (count($attendance_logs) > 0): ?>
        <?php foreach ($attendance_logs as $log): ?>
            <tr>
                <td><?= htmlspecialchars($log['log_date']) ?></td>
                <td><?= htmlspecialchars($log['lrn']) ?></td>
                <td><?= htmlspecialchars($log['stud_name']) ?></td>
                <td><?= htmlspecialchars($log['gender']) ?></td>
                <td><?= htmlspecialchars($log['year_section']) ?></td>
                <td>
                    <?php if ($log['morning_status'] === null): ?>
                        <span class="badge badge-secondary">N/A</span>
                    <?php else: ?>
                        <span class="badge <?= $log['morning_status'] === 'Present' ? 'badge-present' : 'badge-absent' ?>">
                            <?= htmlspecialchars($log['morning_status']) ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($log['am_login_time'])): ?>
                        <?= date("g:i A", strtotime($log['am_login_time'])) ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($log['am_logout_time'])): ?>
                        <?= date("g:i A", strtotime($log['am_logout_time'])) ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($log['afternoon_status'] === null): ?>
                        <span class="badge badge-secondary">N/A</span>
                    <?php else: ?>
                        <span class="badge <?= $log['afternoon_status'] === 'Present' ? 'badge-present' : 'badge-absent' ?>">
                            <?= htmlspecialchars($log['afternoon_status']) ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($log['pm_login_time'])): ?>
                        <?= date("g:i A", strtotime($log['pm_login_time'])) ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($log['pm_logout_time'])): ?>
                        <?= date("g:i A", strtotime($log['pm_logout_time'])) ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="11" class="text-center">No attendance records found</td>
        </tr>
    <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination info -->
                                <div class="text-center mt-3">
                                    Showing <?= min($offset + 1, $total_records) ?> to <?= min($offset + $per_page, $total_records) ?> of <?= $total_records ?> entries
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
        // Set export form values from current filters
        document.getElementById('exportForm').addEventListener('submit', function(e) {
            // Copy all current filter values to export form
            const currentParams = new URLSearchParams(window.location.search);
            for (const [key, value] of currentParams) {
                if (key !== 'export' && key !== 'page') {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    this.appendChild(input);
                }
            }
        });


      document.getElementById('filterForm').addEventListener('submit', function(e) {
    document.getElementById('applyFiltersBtn').disabled = true;
    document.getElementById('applyFiltersText').style.display = 'none';
    document.getElementById('applyFiltersLoading').style.display = '';
});  
    </script>

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
 <?php include 'search/Search_Teacher.php'; ?>

</body>

</html>     