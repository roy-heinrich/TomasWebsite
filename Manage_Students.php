<?php
require_once 'session.php';

if (isset($_POST['logout'])) {
    $_SESSION = [];
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

require_once 'config.php'; // provides $conn (PDO)

// Compute profile image URL
$profileImage = $user['profile_image'] ?? '';
if (strpos($profileImage, 'http') === 0) {
    $profileImageUrl = $profileImage;
} else if (!empty($profileImage)) {
    $profileImageUrl = getSupabaseUrl($profileImage);
} else {
    $profileImageUrl = "https://ui-avatars.com/api/?name=" . urlencode($user['fullname']) . "&size=200&background=random";
}

/*
 * Handle Add Student
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addStudent'])) {
    $lrn = trim($_POST['lrn'] ?? '');
    $name = trim($_POST['stud_name'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $year_section = trim($_POST['year_section'] ?? '');
    $parent_number = trim($_POST['parent_number'] ?? '');
    $teacher_id = isset($_POST['assigned_teacher_id']) ? (int)$_POST['assigned_teacher_id'] : null;
    $date_enrolled = $_POST['date_enrolled'] ?? null;

    try {
        // Check if LRN already exists
        $check = $conn->prepare("SELECT COUNT(*) FROM student_tbl WHERE lrn = :lrn");
        $check->execute([':lrn' => $lrn]);
        if ((int)$check->fetchColumn() > 0) {
            $_SESSION['error'] = "Student with LRN $lrn already exists.";
        } else {
            $insert = $conn->prepare("INSERT INTO student_tbl (lrn, stud_name, gender, year_section, parent_number, assigned_teacher_id, date_enrolled) VALUES (:lrn, :stud_name, :gender, :year_section, :parent_number, :assigned_teacher_id, :date_enrolled)");
            $insert->execute([
                ':lrn' => $lrn,
                ':stud_name' => $name,
                ':gender' => $gender,
                ':year_section' => $year_section,
                ':parent_number' => $parent_number,
                ':assigned_teacher_id' => $teacher_id ?: null,
                ':date_enrolled' => $date_enrolled
            ]);

            $_SESSION['success'] = "Student successfully added!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to add student. " . $e->getMessage();
    }

    header("Location: Manage_Students.php");
    exit;
}

/*
 * Handle Edit Student
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editStudent'])) {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $lrn = trim($_POST['edit_lrn'] ?? '');
    $name = trim($_POST['edit_stud_name'] ?? '');
    $gender = trim($_POST['edit_gender'] ?? '');
    $year_section = trim($_POST['edit_year_section'] ?? '');
    $parent_number = trim($_POST['edit_parent_number'] ?? '');
    $teacher_id = isset($_POST['edit_assigned_teacher_id']) ? (int)$_POST['edit_assigned_teacher_id'] : null;
    $date_enrolled = $_POST['edit_date_enrolled'] ?? null;

    try {
        $update = $conn->prepare("UPDATE student_tbl SET lrn = :lrn, stud_name = :stud_name, gender = :gender, year_section = :year_section, parent_number = :parent_number, assigned_teacher_id = :assigned_teacher_id, date_enrolled = :date_enrolled WHERE student_id = :student_id");
        $update->execute([
            ':lrn' => $lrn,
            ':stud_name' => $name,
            ':gender' => $gender,
            ':year_section' => $year_section,
            ':parent_number' => $parent_number,
            ':assigned_teacher_id' => $teacher_id ?: null,
            ':date_enrolled' => $date_enrolled,
            ':student_id' => $student_id
        ]);

        if ($update->rowCount() > 0) {
            $_SESSION['success'] = "Student successfully updated!";
        } else {
            $_SESSION['error'] = "No changes were made.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update student. " . $e->getMessage();
    }

    header("Location: Manage_Students.php");
    exit;
}

/*
 * Archive / Restore Student
 */
if (isset($_GET['archive'])) {
    $student_id = (int)$_GET['archive'];
    $status = $_GET['status'] ?? 'Archived';

    try {
        $stmt = $conn->prepare("UPDATE student_tbl SET status = :status WHERE student_id = :student_id");
        $stmt->execute([':status' => $status, ':student_id' => $student_id]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Student status updated!";
        } else {
            $_SESSION['error'] = "Failed to update student status.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update student status. " . $e->getMessage();
    }

    header("Location: Manage_Students.php");
    exit;
}

/*
 * Delete Student
 */
if (isset($_GET['delete'])) {
    $student_id = (int)$_GET['delete'];

    try {
        $stmt = $conn->prepare("DELETE FROM student_tbl WHERE student_id = :student_id");
        $stmt->execute([':student_id' => $student_id]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Student permanently deleted!";
        } else {
            $_SESSION['error'] = "Failed to delete student.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to delete student. " . $e->getMessage();
    }

    header("Location: Manage_Students.php");
    exit;
}

/*
 * Bulk Transfer
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_transfer'])) {
    $from_section = trim($_POST['from_section'] ?? '');
    $to_section = trim($_POST['to_section'] ?? '');
    $new_teacher_id = isset($_POST['new_teacher_id']) ? (int)$_POST['new_teacher_id'] : null;

    if ($from_section === $to_section) {
        $_SESSION['error'] = "Cannot transfer students to the same section!";
        header("Location: Manage_Students.php");
        exit;
    }

    // Helper to extract grade number
    function getGradeLevelLocal($section) {
        if (preg_match('/Grade\s*(\d+)/i', $section, $m)) return (int)$m[1];
        return 0;
    }

    $from_grade = getGradeLevelLocal($from_section);
    $to_grade = getGradeLevelLocal($to_section);

    if ($to_grade < $from_grade) {
        $_SESSION['error'] = "Cannot transfer students to a lower grade level!";
        header("Location: Manage_Students.php");
        exit;
    }

    if ($to_grade !== $from_grade + 1) {
        $_SESSION['error'] = "Can only transfer students to the next grade level (one year higher)!";
        header("Location: Manage_Students.php");
        exit;
    }

    try {
        // Get teacher name
        $teacher_name = 'Unknown Teacher';
        if ($new_teacher_id) {
            $t = $conn->prepare("SELECT fullname FROM staff_tbl WHERE id = :id");
            $t->execute([':id' => $new_teacher_id]);
            $found = $t->fetch();
            if ($found) $teacher_name = $found['fullname'];
        }

        $u = $conn->prepare("UPDATE student_tbl SET year_section = :to_section, assigned_teacher_id = :teacher_id WHERE year_section = :from_section AND status = 'Active'");
        $u->execute([
            ':to_section' => $to_section,
            ':teacher_id' => $new_teacher_id ?: null,
            ':from_section' => $from_section
        ]);

        $affected_rows = $u->rowCount();
        $_SESSION['success'] = "Successfully transferred $affected_rows students from $from_section to $to_section (Teacher: $teacher_name)!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to transfer students. " . $e->getMessage();
    }

    header("Location: Manage_Students.php");
    exit;
}

/*
 * Fetch lists for display
 */

// Active students
$activeStudents = [];
try {
    $stmt = $conn->query("SELECT * FROM student_tbl WHERE status = 'Active' ORDER BY student_id DESC");
    $activeStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $activeStudents = [];
}

// Archived students
$archivedStudents = [];
try {
    $stmt = $conn->query("SELECT * FROM student_tbl WHERE status = 'Archived' ORDER BY student_id DESC");
    $archivedStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $archivedStudents = [];
}

// Teachers
$teachers = [];
try {
    $tstmt = $conn->query("SELECT id, fullname FROM staff_tbl WHERE user_role = 'Teacher' AND status != 'Archived' ORDER BY fullname ASC");
    $teacherRows = $tstmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($teacherRows as $r) $teachers[$r['id']] = $r['fullname'];
} catch (Exception $e) {
    $teachers = [];
}

// Distinct sections (advisory_section)
$sections = [];
try {
    $sstmt = $conn->query("SELECT DISTINCT advisory_section FROM staff_tbl WHERE advisory_section IS NOT NULL AND user_role = 'Teacher' AND status != 'Archived'");
    $sectionRows = $sstmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sectionRows as $r) {
        if (!empty($r['advisory_section'])) $sections[] = $r['advisory_section'];
    }
    sort($sections);
} catch (Exception $e) {
    $sections = [];
}

/*
 * Export to CSV (Excel)
 */
if (isset($_POST['export_excel'])) {
    $section = $_POST['section'] ?? '';
    $status = $_POST['status'] ?? 'Active';

    $query = "SELECT lrn, stud_name, gender, year_section, parent_number FROM student_tbl";
    $where = [];
    $params = [];

    if ($section) {
        $where[] = "year_section = :section";
        $params[':section'] = $section;
    }
    if ($status !== 'All') {
        $where[] = "status = :status";
        $params[':status'] = $status;
    }

    if (!empty($where)) $query .= " WHERE " . implode(' AND ', $where);

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=students_export.csv');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'LRN (12 digits)',
        'Full Name',
        'Gender',
        'Year & Section',
        'Parent Contact Number'
    ]);

    foreach ($rows as $row) {
        $formattedRow = [
            "\t" . $row['lrn'],
            $row['stud_name'],
            $row['gender'],
            $row['year_section'],
            "\t" . $row['parent_number']
        ];
        fputcsv($output, $formattedRow);
    }

    fclose($output);
    exit;
}

/*
 * Export Barcodes (HTML)
 */
if (isset($_POST['export_barcode'])) {
    $section = $_POST['section'] ?? '';
    $status = $_POST['status'] ?? 'Active';
    $per_page = (int)($_POST['per_page'] ?? 8);

    $query = "SELECT lrn, stud_name FROM student_tbl";
    $where = [];
    $params = [];

    if ($section) {
        $where[] = "year_section = :section";
        $params[':section'] = $section;
    }
    if ($status !== 'All') {
        $where[] = "status = :status";
        $params[':status'] = $status;
    }

    if (!empty($where)) $query .= " WHERE " . implode(' AND ', $where);

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Barcode Export</title>
        <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
        <style>
            body { font-family: Arial, sans-serif; }
            .barcode-print-container { display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; padding: 20px; }
            .barcode-item { width: 200px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; text-align: center; page-break-inside: avoid; }
            @media print { body { margin: 0; padding: 0; } .barcode-print-container { padding: 0; } .barcode-item { border: none; } }
        </style>
    </head>
    <body>
        <div class="barcode-print-container">
            <?php foreach ($rows as $student): ?>
                <div class="barcode-item">
                    <h5><?= htmlspecialchars($student['stud_name']) ?></h5>
                    <svg class="barcode"
                        jsbarcode-value="<?= htmlspecialchars($student['lrn']) ?>"
                        jsbarcode-format="CODE128"
                        jsbarcode-height="40"
                        jsbarcode-width="1.9"
                        jsbarcode-fontSize="12"
                        jsbarcode-displayValue="true"></svg>
                </div>
            <?php endforeach; ?>
        </div>
        <script>
            JsBarcode(".barcode").init();
            window.onload = function() { window.print(); };
        </script>
    </body>
    </html>
    <?php
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

    <title>Student Management</title>

    <!-- Custom fonts for this template-->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">
     <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
     <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
     <!-- DataTables CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap4.min.css"/>

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


 @media (max-width: 576px) {
        .export-label {
            font-size: 0.80rem !important;
        }
    }


    
.add-student-btn,
  .toggle-archive-btn,
   #bulkTransferBtn{
    border-radius: 0.5rem;
    min-width: 180px;
    padding: 0.6rem 1.2rem;
    font-size: 1rem;
    
  }

  @media (max-width: 576px) {
    .add-student-btn,
    .toggle-archive-btn,
    #bulkTransferBtn {
      min-width: 130px;
      padding: 0.4rem 0.8rem;
      font-size: 0.85rem;
        margin-right: 0;
        margin-left: 0;
        margin-bottom: 10px; 
    }

     
  }

   .barcode-container {
        background: white;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 5px;
        text-align: center;
    }
    .barcode-preview {
        min-height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .barcode-placeholder {
        color: #6c757d;
        font-style: italic;
    }
    .btn-purple {
        background-color: #8A2BE2;
        border-color: #8A2BE2;
        color: white;
    }
    .btn-purple:hover {
        background-color: #6a1fc1;
        border-color: #6a1fc1;
        color: white;
    }
    
    /* Pagination styles */
    .pagination-container {
        display: flex;
        justify-content: center;
        margin-top: 20px;
    }
    .pagination {
        display: flex;
        padding-left: 0;
        list-style: none;
        border-radius: .25rem;
    }
    .page-item {
        margin: 0 2px;
    }
    .page-link {
        position: relative;
        display: block;
        padding: .5rem .75rem;
        margin-left: -1px;
        line-height: 1.25;
        color: #007bff;
        background-color: #fff;
        border: 1px solid #dee2e6;
    }
    .page-item.active .page-link {
        z-index: 1;
        color: #fff;
        background-color: #8A2BE2;
        border-color: #8A2BE2;
    }
    .page-item.disabled .page-link {
        color: #6c757d;
        pointer-events: none;
        cursor: auto;
        background-color: #fff;
        border-color: #dee2e6;
    }
    
    /* Search and filter styles */
    .table-controls {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 15px;
    }
    .search-container {
        flex: 1;
        min-width: 250px;
    }
    .filter-container {
        min-width: 200px;
    }
    
    /* Barcode print styles */
    .barcode-card {
        width: 100%;
        max-width: 300px;
        margin: 0 auto;
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 15px;
        text-align: center;
    }
    .barcode-print-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        justify-content: center;
        padding: 20px;
    }
    .barcode-item {
        width: 200px;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 5px;
        text-align: center;
    }
    .print-hidden {
        display: none;
    }
    @media print {
        body * {
            visibility: hidden;
        }
        .print-section, .print-section * {
            visibility: visible;
        }
        .print-section {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
        .no-print {
            display: none !important;
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
                            <li class="nav-item"> 
                                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapsePages" aria-expanded="true" aria-controls="collapsePages"> 
                                    <i class="fas fa-copy"></i> 
                                    <span>Pages</span>
                                 </a>
                                  <div id="collapsePages" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
                                     <div class="bg-white py-2 collapse-inner rounded"> <a class="collapse-item" href="message.php">Home Page</a>
                                      <a class="collapse-item" href="News_Eve.php">News & Events</a> <a class="collapse-item" href="Edit_Staff.php">Faculty & Staff</a>
                                       <a class="collapse-item" href="Edit_Achieve.php">Achievements</a> <a class="collapse-item" href="chart_edit.php">Organizational Chart</a>
                                       <a class="collapse-item" href="Edit_Contact.php">Contact</a>
                                        <a class="collapse-item" href="Edit_Gallery.php">Gallery</a>
                                       <a class="collapse-item" href="Edit_History.php">History</a>

                                     </div>
                                     </div>
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
                                                  <li class="nav-item active"> 
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

                <!-- Begin Page Content --> 
                <div class="container-fluid">                
                              
              <div class="container-fluid px-1 px-sm-3 mt-2">        
           <div class="d-sm-flex align-items-center justify-content-between mb-4">
  <div class="d-flex align-items-center">
    <i class="fas fa-user-graduate" style="color: rgb(11, 104, 245); font-size: 1.3rem; margin-right: 0.8rem;"></i>
    <h2 class="fw-bolder mb-0" style="color: rgb(11, 104, 245); font-size: 1.5rem; font-weight: 800;">Manage Students</h2>
  </div>
</div>
                                     </div>

  <!-- Add & Archive Buttons (above the student table) -->

    <div class="container-fluid px-1 px-sm-3 mt-2">
  <div class="d-flex flex-wrap justify-content-start mb-2">

    <!-- Add Student Button -->
    <button class="btn btn-primary fw-bold add-student-btn"
            data-toggle="modal" data-target="#addStudentModal">
      <i class="fas fa-user-plus mr-2"></i>Add Student
    </button>

    <!-- Show Archive Button -->
    <button id="toggleArchiveBtn" class="btn btn-info fw-bold toggle-archive-btn ml-2">
      <i class="fas fa-archive mr-2"></i>Show Archive
    </button>

 <!-- Bulk Transfer Button -->
    <button class="btn btn-warning fw-bold toggle-archive-btn ml-2" id="bulkTransferBtn" data-toggle="modal" data-target="#bulkTransferModal">
      <i class="fas fa-exchange-alt mr-2"></i>Bulk Transfer
    </button>

  </div>
</div>

               <!-- Student table -->
                    <section id="registeredStudentSection" class="registered-student-list py-4">
                        <div class="container-fluid px-1 px-sm-3">
                            <div class="card shadow" style="border-top: 8px solid #8A2BE2; border-radius: 1rem;">
                                <div class="card-body">
                                    <!-- Header Row: Title + Export Buttons/Filter -->
                                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                                        <!-- Left: Title -->
                                        <div>
                                            <h3 class="text-start mb-2" style="color: #8A2BE2; font-weight: 900;">Registered Students</h3>
                                        </div>

                                        <!-- Right: Export Buttons -->
                                        <div class="d-flex align-items-center mt-2" style="gap: 10px;">
                                            <label class="fw-bold mb-0 export-label" style="font-size: 1rem;">Export:</label>
                                            <button class="btn btn-sm text-white"
                                                data-toggle="modal" data-target="#exportExcelModal"
                                                style="background-color: rgb(18, 182, 61); border-color: rgb(19, 231, 3);">
                                                <i class="fas fa-file-excel mr-1"></i> Excel
                                            </button>

                                            <button class="btn btn-sm text-white"
                                                data-toggle="modal" data-target="#exportBarcodeModal"
                                                style="background-color: rgb(45, 66, 222); border-color: rgb(66, 74, 227);">
                                                <i class="fas fa-barcode mr-1"></i> Barcode
                                            </button>
                                        </div>
                                    </div>

                                  

                                    <!-- Table -->
                                    <div class="table-responsive">
                                        <table id="activeStudentTable" class="table table-bordered table-hover align-middle text-center">
                                            <thead style="background-color: #8A2BE2; color: white;">
                                                <tr>
                                                    <th>LRN</th>
                                                    <th>Fullname</th>
                                                    <th>Gender</th>
                                                    <th>Year & Section</th>
                                                    <th>Parents Number</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($activeStudents as $student): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($student['lrn']) ?></td>
                                                    <td><?= htmlspecialchars($student['stud_name']) ?></td>
                                                    <td><?= htmlspecialchars($student['gender']) ?></td>
                                                    <td><?= htmlspecialchars($student['year_section']) ?></td>
                                                    <td><?= htmlspecialchars($student['parent_number']) ?></td>
                                                    <td>
                                                        <button class="btn btn-info btn-sm edit-btn" 
                                                            data-id="<?= $student['student_id'] ?>" 
                                                            data-lrn="<?= htmlspecialchars($student['lrn']) ?>"
                                                            data-name="<?= htmlspecialchars($student['stud_name']) ?>"
                                                            data-gender="<?= htmlspecialchars($student['gender']) ?>"
                                                            data-section="<?= htmlspecialchars($student['year_section']) ?>"
                                                            data-parent="<?= htmlspecialchars($student['parent_number']) ?>"
                                                            data-teacher="<?= $student['assigned_teacher_id'] ?>"
                                                            data-date="<?= htmlspecialchars($student['date_enrolled']) ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-success btn-sm barcode-btn" 
                                                            data-id="<?= $student['student_id'] ?>" 
                                                            data-lrn="<?= htmlspecialchars($student['lrn']) ?>"
                                                            data-name="<?= htmlspecialchars($student['stud_name']) ?>">
                                                            <i class="fas fa-barcode"></i>
                                                        </button>
                                                        <button class="btn btn-warning btn-sm archive-btn" 
                                                            data-id="<?= $student['student_id'] ?>">
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
                  

                                <!-- Archived table -->
                    <section id="archivedStudentSection" class="archived-student-list py-4" style="display: none;">
                        <div class="container-fluid px-1 px-sm-3">
                            <div class="card shadow" style="border-top: 8px solid #17a2b8; border-radius: 1rem;">
                                <div class="card-body">
                                    <!-- Header Row: Title -->
                                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                                        <div>
                                            <h3 class="text-start mb-2" style="color: #17a2b8; font-weight: 900;">Archived Students</h3>
                                        </div>
                                    </div>
                                    
                               

                                    <!-- Table -->
                                    <div class="table-responsive">
                                        <table id="archivedStudentTable" class="table table-bordered table-hover align-middle text-center">
                                            <thead style="background-color: #17a2b8; color: white;">
                                                <tr>
                                                    <th>LRN</th>
                                                    <th>Fullname</th>
                                                    <th>Gender</th>
                                                    <th>Year & Section</th>
                                                    <th>Parents Number</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($archivedStudents as $student): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($student['lrn']) ?></td>
                                                    <td><?= htmlspecialchars($student['stud_name']) ?></td>
                                                    <td><?= htmlspecialchars($student['gender']) ?></td>
                                                    <td><?= htmlspecialchars($student['year_section']) ?></td>
                                                    <td><?= htmlspecialchars($student['parent_number']) ?></td>
                                                    <td>
                                                        <button class="btn btn-success btn-sm restore-btn" 
                                                            data-id="<?= $student['student_id'] ?>">
                                                            <i class="fas fa-undo"></i>
                                                        </button>
                                                        <button class="btn btn-danger btn-sm delete-btn" 
                                                            data-id="<?= $student['student_id'] ?>">
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
                </div>
                <!-- /.container-fluid -->

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

              <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1" role="dialog" aria-labelledby="addStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <!-- Modal Header -->
                <div class="modal-header" style = "background: linear-gradient(135deg, #8A2BE2, #5A1A9E);color: white;  border-radius: 0;">
                    <h5 class="modal-title" id="addStudentModalLabel">
                        <i class="fas fa-user-plus mr-2"></i>Add New Student
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true" style="color: white;">&times;</span>
                    </button>
                </div>
                
                <!-- Modal Body -->
                <div class="modal-body" >
                    <form id="studentForm" method="POST" action="Manage_Students.php">
                       <input type="hidden" name="addStudent" value="1">
                        <div class="form-row">
                            <!-- LRN Number -->
                            <div class="form-group col-md-6">
                                <label for="lrnInput" class="font-weight-bold required">LRN Number</label>
                                <input type="text" class="form-control" id="lrnInput" name="lrn" placeholder="Enter 12-digit LRN" maxlength="12" required>
                                <small class="form-text text-muted">Learner Reference Number (12 digits)</small>
                            </div>
                            
                            <!-- Full Name -->
                            <div class="form-group col-md-6">
                                <label for="fullNameInput" class="font-weight-bold required">Full Name</label>
                                <input type="text" class="form-control" id="fullNameInput" name="stud_name" placeholder="First Name Middle Initial. Last Name" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <!-- Gender -->
                            <div class="form-group col-md-6">
                                <label for="genderSelect" class="font-weight-bold required">Gender</label>
                                <select class="custom-select" id="genderSelect" name="gender" required>
                                    <option value="" disabled selected>Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            
                            <!-- Year & Section -->
                            <div class="form-group col-md-6">
                                <label for="sectionSelect" class="font-weight-bold required">Year & Section</label>
                                <select class="custom-select" id="sectionSelect" name="year_section" required>
                                    <option value="" disabled selected>Select Year & Section</option>
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?= htmlspecialchars($section) ?>"><?= htmlspecialchars($section) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <!-- Parent's Contact Number -->
                            <div class="form-group col-md-6">
                                <label for="parentNumberInput" class="font-weight-bold required">Parent's Contact Number</label>
                                <input type="tel" class="form-control" id="parentNumberInput" name="parent_number" placeholder="09XXXXXXXXX">
                                <small class="form-text text-muted">Format: 09XXXXXXXXX (11 digits)</small>
                            </div>

                            <!-- Assign to Teacher -->
                            <div class="form-group col-md-6">
                                <label for="teacherSelect" class="font-weight-bold required">Assign to Teacher</label>
                                <select class="custom-select" id="teacherSelect" name="assigned_teacher_id" required>
                                    <option value="" disabled selected>Select Teacher</option>
                                    <?php foreach ($teachers as $id => $name): ?>
                                        <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                       

                         <!-- Add Student Modal - After the teacher selection field -->
<div class="form-group col-md-6">
    <label for="dateEnrolledInput" class="font-weight-bold required">Date Enrolled</label>
    <input type="date" class="form-control" id="dateEnrolledInput" name="date_enrolled" required>
</div>
                           </div>

                        <!-- Barcode Preview -->
                        <div class="form-group">
                            <label class="font-weight-bold">Barcode Preview</label>
                            <div class="barcode-container">
                                <div id="barcodePreview" class="barcode-preview">
                                    <div class="barcode-placeholder">
                                        Enter LRN to generate barcode
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">This barcode will be used for student identification and attendance tracking.</small>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                 <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </button>
                   <button id="saveStudentBtn" type="submit" class="btn btn-purple" form="studentForm">
                        <i class="fas fa-save mr-1"></i> Save Student
                    </button>
                </div>
            </div>
        </div>
    </div>


    

                     <!-- Edit Student Modal -->
    <div class="modal fade" id="editStudentModal" tabindex="-1" role="dialog" aria-labelledby="editStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <!-- Modal Header -->
                <div class="modal-header" style="background: linear-gradient(135deg, #6a1fc1, #4b168f); color: white;">
                    <h5 class="modal-title" id="editStudentModalLabel">
                        <i class="fas fa-user-edit mr-2"></i>Edit Student
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true" style="color: white;">&times;</span>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body">
                    <form id="editStudentForm" method="POST" action="Manage_Students.php">
                        <input type="hidden" name="editStudent" value="1">
                        <input type="hidden" id="editStudentId" name="student_id">
                        <div class="form-row">
                            <!-- LRN Number -->
                            <div class="form-group col-md-6">
                                <label for="editLrnInput" class="font-weight-bold required">LRN Number</label>
                                <input type="text" class="form-control" id="editLrnInput" name="edit_lrn" maxlength="12" required>
                            </div>

                            <!-- Full Name -->
                            <div class="form-group col-md-6">
                                <label for="editFullNameInput" class="font-weight-bold required">Full Name</label>
                                <input type="text" class="form-control" id="editFullNameInput" name="edit_stud_name" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <!-- Gender -->
                            <div class="form-group col-md-6">
                                <label for="editGenderSelect" class="font-weight-bold required">Gender</label>
                                <select class="custom-select" id="editGenderSelect" name="edit_gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>

                            <!-- Year & Section -->
                            <div class="form-group col-md-6">
                                <label for="editSectionSelect" class="font-weight-bold required">Year & Section</label>
                                <select class="custom-select" id="editSectionSelect" name="edit_year_section" required>
                                    <option value="">Select Section</option>
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?= htmlspecialchars($section) ?>"><?= htmlspecialchars($section) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <!-- Parent's Contact Number -->
                            <div class="form-group col-md-6">
                                <label for="editParentNumberInput" class="font-weight-bold required">Parent's Contact Number</label>
                                <input type="tel" class="form-control" id="editParentNumberInput" name="edit_parent_number">
                            </div>

                            <!-- Assign to Teacher -->
                            <div class="form-group col-md-6">
                                <label for="editTeacherSelect" class="font-weight-bold required">Assign to Teacher</label>
                                <select class="custom-select" id="editTeacherSelect" name="edit_assigned_teacher_id" required>
                                    <option value="" disabled selected>Select Teacher</option>
                                    <?php foreach ($teachers as $id => $name): ?>
                                        <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Edit Student Modal - After the teacher selection field -->
                            <div class="form-group col-md-6">
                            <label for="editDateEnrolledInput" class="font-weight-bold required">Date Enrolled</label>
                             <input type="date" class="form-control" id="editDateEnrolledInput" name="edit_date_enrolled" required>
                            </div>

                        </div>

                        <!-- Barcode Preview -->
                        <div class="form-group">
                            <label class="font-weight-bold">Barcode Preview</label>
                            <div class="barcode-container">
                                <div id="editBarcodePreview" class="barcode-preview">
                                    <div class="barcode-placeholder">
                                        Barcode will appear here
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">This barcode is generated based on the student's LRN number.</small>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

               <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </button>                   
                   <button id="updateStudentBtn" type="submit" class="btn btn-purple" form="editStudentForm">
                        <i class="fas fa-save mr-1"></i> Update Student
                    </button>
                </div>
            </div>
        </div>
    </div>
                

                       <!-- Export Excel Modal -->
    <div class="modal fade" id="exportExcelModal" tabindex="-1" role="dialog" aria-labelledby="exportExcelModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="exportExcelModalLabel"><i class="fas fa-file-excel mr-2"></i>Export to Excel</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true" style="color: white;">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="excelExportForm" method="POST">
                         <input type="hidden" name="export_excel" value="1">
                        <div class="form-group">
                            <label for="excelSectionFilter">Filter by Section</label>
                            <select class="form-control" id="excelSectionFilter" name="section">
                                <option value="">All Sections</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= htmlspecialchars($section) ?>"><?= htmlspecialchars($section) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="excelStatusFilter">Filter by Status</label>
                            <select class="form-control" id="excelStatusFilter" name="status">
                                <option value="Active">Active Students</option>
                                <option value="Archived">Archived Students</option>
                                <option value="All">All Students</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" form="excelExportForm" class="btn btn-success">
                        <i class="fas fa-download mr-1"></i> Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Barcode Modal -->
    <div class="modal fade" id="exportBarcodeModal" tabindex="-1" role="dialog" aria-labelledby="exportBarcodeModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="exportBarcodeModalLabel"><i class="fas fa-barcode mr-2"></i>Export Barcodes</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true" style="color: white;">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="barcodeExportForm" method="POST">
                        <input type="hidden" name="export_barcode" value="1">
                        <div class="form-group">
                            <label for="barcodeSectionFilter">Filter by Section</label>
                            <select class="form-control" id="barcodeSectionFilter" name="section">
                                <option value="">All Sections</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= htmlspecialchars($section) ?>"><?= htmlspecialchars($section) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="barcodeStatusFilter">Filter by Status</label>
                            <select class="form-control" id="barcodeStatusFilter" name="status">
                                <option value="Active">Active Students</option>
                                <option value="Archived">Archived Students</option>
                                <option value="All">All Students</option>
                            </select>
                        </div>
                        
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" form="barcodeExportForm" class="btn btn-primary">
                        <i class="fas fa-print mr-1"></i> Generate
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Individual Barcode Modal -->
    <div class="modal fade" id="barcodeModal" tabindex="-1" role="dialog" aria-labelledby="barcodeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="barcodeModalLabel"><i class="fas fa-barcode mr-2"></i>Student Barcode</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true" style="color: white;">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="barcode-card">
                        <h5 id="barcodeStudentName" class="mb-3"></h5>
                        <div id="individualBarcodePreview" class="barcode-preview">
                            <div class="barcode-placeholder">Barcode will appear here</div>
                        </div>
                        <p class="mt-2 mb-0"><small>LRN: <span id="barcodeStudentLrn"></span></small></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-info" id="printBarcodeBtn">
                        <i class="fas fa-print mr-1"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>


    <!-- Bulk Transfer Modal -->
<div class="modal fade" id="bulkTransferModal" tabindex="-1" role="dialog" aria-labelledby="bulkTransferModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="bulkTransferModalLabel">
                    <i class="fas fa-exchange-alt mr-2"></i>Bulk Transfer Students
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" style="color: white;">&times;</span>
                </button>
            </div>
            <form id="bulkTransferForm" method="POST" action="Manage_Students.php">
                <input type="hidden" name="bulk_transfer" value="1">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold">From Section</label>
                        <select class="form-control" name="from_section" id="fromSectionSelect" required>
                            <option value="" disabled selected>Select Current Section</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?= htmlspecialchars($section) ?>"><?= htmlspecialchars($section) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">To Section</label>
                        <select class="form-control" name="to_section" id="toSectionSelect" required>
                            <option value="" disabled selected>Select New Section</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?= htmlspecialchars($section) ?>"><?= htmlspecialchars($section) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Assign to Teacher</label>
                        <select class="form-control" name="new_teacher_id" id="newTeacherSelect" required>
                            <option value="" disabled selected>Select New Teacher</option>
                            <?php foreach ($teachers as $id => $name): ?>
                                <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>This will transfer all active students from the selected section to the new section and assign them to the selected teacher.
                    </div>
                </div>
                 <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                  <button id="bulkTransferSubmitBtn" type="submit" class="btn btn-warning">
                        <i class="fas fa-exchange-alt mr-1"></i> Transfer Students
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
<script type="text/javascript" src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap4.min.js"></script>
  

    <script>
    // Handle success/error messages
    <?php if (isset($_SESSION['success'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: "<?= addslashes($_SESSION['success']) ?>",
                confirmButtonColor: '#3085d6',
            });
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: "<?= addslashes($_SESSION['error']) ?>",
                confirmButtonColor: '#d33',
            });
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    // Toggle between active and archived students
    $(document).ready(function() {
        let archiveVisible = false;
        $('#archivedStudentSection').hide();

        $('#toggleArchiveBtn').click(function() {
            archiveVisible = !archiveVisible;
            
            if (archiveVisible) {
                $('#registeredStudentSection').hide();
                $('#archivedStudentSection').show();
                $(this)
                    .removeClass('btn-info')
                    .addClass('btn-purple')
                    .html('<i class="fas fa-users mr-2"></i>Show Active');
            } else {
                $('#registeredStudentSection').show();
                $('#archivedStudentSection').hide();
                $(this)
                    .removeClass('btn-purple')
                    .addClass('btn-info')
                    .html('<i class="fas fa-archive mr-2"></i>Show Archive');
            }
        });


        // For active students table
    var activeTable = $('#activeStudentTable').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "lengthMenu": [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        "pageLength": 10,
        "columnDefs": [{
            "targets": [5], // Actions column (last column)
            "orderable": false,
            "searchable": false
        }]
    });

    // For archived students table
    var archivedTable = $('#archivedStudentTable').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "lengthMenu": [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        "pageLength": 10,
        "columnDefs": [{
            "targets": [5], // Actions column (last column)
            "orderable": false,
            "searchable": false
        }]
    });

    // Add section filter functionality using DataTables API
    $('#sectionFilter').on('change', function() {
        activeTable.column(3).search(this.value).draw();
    });

    $('#sectionArchiveFilter').on('change', function() {
        archivedTable.column(3).search(this.value).draw();
    });
        
        // Reset modals when closed
        $('.modal').on('hidden.bs.modal', function() {
            $(this).find('form').trigger('reset');
            $(this).find('.barcode-preview').html('<div class="barcode-placeholder">Enter LRN to generate barcode</div>');

            // Reset save/update/bulk buttons if present
            $('#saveStudentBtn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Student');
            $('#updateStudentBtn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Update Student');
            $('#bulkTransferSubmitBtn').prop('disabled', false).html('<i class="fas fa-exchange-alt mr-1"></i> Transfer Students');

            // Re-enable teacher selects if they were disabled during loading
            $('#teacherSelect, #editTeacherSelect, #newTeacherSelect').prop('disabled', false);
        });
        
        // Generate barcode when LRN changes in Add modal
        $('#lrnInput').on('input', function() {
            generateBarcode($(this).val(), '#barcodePreview');
        });
        
        // Generate barcode when LRN changes in Edit modal
        $('#editLrnInput').on('input', function() {
            generateBarcode($(this).val(), '#editBarcodePreview');
        });
        
        // Initialize barcodes when modals are shown
        $('#editStudentModal').on('shown.bs.modal', function() {
            const lrn = $('#editLrnInput').val();
            if (lrn) generateBarcode(lrn, '#editBarcodePreview');
        });

        $('#barcodeModal').on('shown.bs.modal', function() {
            const lrn = $('#barcodeStudentLrn').text();
            if (lrn) generateBarcode(lrn, '#individualBarcodePreview');
        });
        

// Update teacher dropdown when section changes (for Bulk Transfer) - existing handler
$('#toSectionSelect').change(function() {
    const section = $(this).val();
    
    // Clear and disable the teacher dropdown while loading
    const teacherSelect = $('#newTeacherSelect');
    teacherSelect.empty().append('<option value="" disabled selected>Loading teachers...</option>').prop('disabled', true);
    
    // Fetch teachers for the selected section
    $.ajax({
        url: 'get_teachers_by_section.php',
        method: 'POST',
        data: { section: section },
        dataType: 'json',
        success: function(response) {
            teacherSelect.empty();
            if (response.length > 0) {
                teacherSelect.append('<option value="" disabled selected>Select Teacher</option>');
                $.each(response, function(index, teacher) {
                    teacherSelect.append(`<option value="${teacher.id}">${teacher.name}</option>`);
                });
            } else {
                teacherSelect.append('<option value="" disabled selected>No teachers found for this section</option>');
            }
            teacherSelect.prop('disabled', false);
        },
        error: function() {
            teacherSelect.empty().append('<option value="" disabled selected>Error loading teachers</option>');
            teacherSelect.prop('disabled', false);
        }
    });
});

// New: Update teacher dropdown when section changes in Add modal
$('#sectionSelect').change(function() {
    const section = $(this).val();
    const teacherSelect = $('#teacherSelect');
    teacherSelect.empty().append('<option value="" disabled selected>Loading teachers...</option>').prop('disabled', true);

    $.ajax({
        url: 'get_teachers_by_section.php',
        method: 'POST',
        data: { section: section },
        dataType: 'json',
        success: function(response) {
            teacherSelect.empty();
            if (response.length > 0) {
                $.each(response, function(index, teacher) {
                    teacherSelect.append(`<option value="${teacher.id}">${teacher.name}</option>`);
                });
                // auto-select first teacher to ease registration
                teacherSelect.val(response[0].id);
            } else {
                teacherSelect.append('<option value="" disabled selected>No teachers found for this section</option>');
            }
            teacherSelect.prop('disabled', false);
        },
        error: function() {
            teacherSelect.empty().append('<option value="" disabled selected>Error loading teachers</option>');
            teacherSelect.prop('disabled', false);
        }
    });
});

// New: Update teacher dropdown when section changes in Edit modal
// We'll preserve the current assignment by using a stored variable
$('#editSectionSelect').change(function() {
    const section = $(this).val();
    const teacherSelect = $('#editTeacherSelect');
    // preserve currently selected teacher id (if any)
    const currentTeacher = teacherSelect.val();

    teacherSelect.empty().append('<option value="" disabled selected>Loading teachers...</option>').prop('disabled', true);

    $.ajax({
        url: 'get_teachers_by_section.php',
        method: 'POST',
        data: { section: section },
        dataType: 'json',
        success: function(response) {
            teacherSelect.empty();
            if (response.length > 0) {
                $.each(response, function(index, teacher) {
                    teacherSelect.append(`<option value="${teacher.id}">${teacher.name}</option>`);
                });
                // If currentTeacher exists in new list keep it, otherwise select first
                const found = response.find(t => t.id == currentTeacher);
                if (found) {
                    teacherSelect.val(currentTeacher);
                } else {
                    teacherSelect.val(response[0].id);
                }
            } else {
                teacherSelect.append('<option value="" disabled selected>No teachers found for this section</option>');
            }
            teacherSelect.prop('disabled', false);
        },
        error: function() {
            teacherSelect.empty().append('<option value="" disabled selected>Error loading teachers</option>');
            teacherSelect.prop('disabled', false);
        }
    });
});


// Function to extract grade level from section string
function getGradeLevel(section) {
    const match = section.match(/Grade\s*(\d+)/i);
    return match ? parseInt(match[1]) : 0;
}

// Real-time grade level validation in bulk transfer modal
$('#fromSectionSelect, #toSectionSelect').on('change', function() {
    const fromSection = $('#fromSectionSelect').val();
    const toSection = $('#toSectionSelect').val();
    
    if (fromSection && toSection) {
        const fromGrade = getGradeLevel(fromSection);
        const toGrade = getGradeLevel(toSection);
        
        const warningElement = $('#gradeWarning');
        
        if (toGrade < fromGrade) {
            if (warningElement.length === 0) {
                $('#toSectionSelect').after('<div id="gradeWarning" class="alert alert-danger mt-2"><i class="fas fa-exclamation-triangle mr-2"></i>Cannot transfer to a lower grade level!</div>');
            }
        } else if (toGrade !== fromGrade + 1) {
            if (warningElement.length === 0) {
                $('#toSectionSelect').after('<div id="gradeWarning" class="alert alert-danger mt-2"><i class="fas fa-exclamation-triangle mr-2"></i>Can only transfer to the next grade level (one year higher)!</div>');
            }
        } else {
            $('#gradeWarning').remove();
        }
    } else {
        $('#gradeWarning').remove();
    }
});

// Clear warning when modal is hidden
$('#bulkTransferModal').on('hidden.bs.modal', function() {
    $('#gradeWarning').remove();
    
    // Also reset the form if desired
    $('#bulkTransferForm')[0].reset();
});

// Validate bulk transfer form submission
$('#bulkTransferForm').on('submit', function(e) {
    const fromSection = $('#fromSectionSelect').val();
    const toSection = $('#toSectionSelect').val();
    
    if (fromSection && toSection) {
        const fromGrade = getGradeLevel(fromSection);
        const toGrade = getGradeLevel(toSection);
        
        if (toGrade < fromGrade) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Invalid Transfer',
                text: 'Cannot transfer students to a lower grade level!',
                confirmButtonColor: '#d33',
            });
            return false;
        }
        
        if (toGrade !== fromGrade + 1) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Invalid Transfer',
                text: 'Can only transfer students to the next grade level (one year higher)!',
                confirmButtonColor: '#d33',
            });
            return false;
        }
    }

    // Show loading state on bulk transfer button
    $('#bulkTransferSubmitBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span>Transferring...');
    
    return true;
});


// Edit button handler - using event delegation
$(document).on('click', '.edit-btn', function() {
    const studentId = $(this).data('id');
    const lrn = $(this).data('lrn');
    const name = $(this).data('name');
    const gender = $(this).data('gender');
    const section = $(this).data('section');
    const parent = $(this).data('parent');
    const teacher = $(this).data('teacher');
    const dateEnrolled = $(this).data('date');
    
    $('#editStudentId').val(studentId);
    $('#editLrnInput').val(lrn);
    $('#editFullNameInput').val(name);
    $('#editGenderSelect').val(gender);
    $('#editSectionSelect').val(section);
    $('#editParentNumberInput').val(parent);
    // set teacher after loading teachers for section
    $('#editTeacherSelect').val(teacher);
    $('#editDateEnrolledInput').val(dateEnrolled);
    
    // Trigger change on editSectionSelect to load teachers for that section
    // After teachers load, the handler will try to keep current teacher if present
    $('#editSectionSelect').trigger('change');

    generateBarcode(lrn, '#editBarcodePreview');
    
    $('#editStudentModal').modal('show');
});

// Barcode button handler - using event delegation
$(document).on('click', '.barcode-btn', function() {
    const lrn = $(this).data('lrn');
    const name = $(this).data('name');
    
    $('#barcodeStudentName').text(name);
    $('#barcodeStudentLrn').text(lrn);
    generateBarcode(lrn, '#individualBarcodePreview');
    $('#barcodeModal').modal('show');
});

// Archive button handler - using event delegation
$(document).on('click', '.archive-btn', function() {
    const studentId = $(this).data('id');
    
    Swal.fire({
        title: 'Archive Student?',
        text: "This student will be moved to the archive section.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, archive it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `Manage_Students.php?archive=${studentId}&status=Archived`;
        }
    });
});

// Restore button handler - using event delegation
$(document).on('click', '.restore-btn', function() {
    const studentId = $(this).data('id');
    
    Swal.fire({
        title: 'Restore Student?',
        text: "This student will be moved back to active students.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, restore it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `Manage_Students.php?archive=${studentId}&status=Active`;
        }
    });
});

// Delete button handler - using event delegation
$(document).on('click', '.delete-btn', function() {
    const studentId = $(this).data('id');
    
    Swal.fire({
        title: 'Permanently Delete?',
        text: "This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `Manage_Students.php?delete=${studentId}`;
        }
    });
});
        
        
          $('#printBarcodeBtn').click(function() {
        const lrn = $('#barcodeStudentLrn').text();
        const name = $('#barcodeStudentName').text();
        
        const printContent = `
            <div class="barcode-card">
                <h5 class="mb-3">${name}</h5>
                <svg class="barcode"
                    jsbarcode-value="${lrn}"
                    jsbarcode-format="CODE128"
                    jsbarcode-height="40"
                    jsbarcode-width="1.9"
                    jsbarcode-fontSize="12"
                    jsbarcode-displayValue="true"
                    style="width: 100%"></svg>
            
            </div>
        `;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>Student Barcode</title>
                <style>
                    body {
                        margin: 0;
                        padding: 0;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                    }
                    .barcode-card {
                        width: 100%;
                        max-width: 300px;
                        text-align: center;
                        padding: 15px;
                    }
                    .barcode-card h5,
                    .barcode-card p {
                        width: 100%;
                        text-align: center;
                        margin: 10px 0;
                    }
                </style>
            </head>
            <body>
                ${printContent}
                <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"><\/script>
                <script>
                    // Generate barcode
                    JsBarcode(".barcode").init();
                    
                    // Automatically print
                    window.onload = function() {
                        window.print();
                    };
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    });
    
        
        // Show loading state when Add Student form is submitted
        $('#studentForm').on('submit', function() {
            $('#saveStudentBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span>Saving...');
            // allow normal form submission to proceed
        });

        // Show loading state when Edit Student form is submitted
        $('#editStudentForm').on('submit', function() {
            $('#updateStudentBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span>Updating...');
            // allow normal form submission to proceed
        });
        
        // Handle Excel export form submission
        $('#excelExportForm').submit(function(e) {
            e.preventDefault();
            const form = $(this);
            const section = form.find('#excelSectionFilter').val();
            const status = form.find('#excelStatusFilter').val();
            
            $.ajax({
                type: 'POST',
                url: 'Manage_Students.php',
                data: {
                    export_excel: true,
                    section: section,
                    status: status
                },
                success: function(response) {
                    // Create a temporary link to download the file
                    const blob = new Blob([response], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = 'students_export.csv';
                    link.click();
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Export Failed',
                        text: 'Could not generate Excel file',
                        confirmButtonColor: '#d33',
                    });
                }
            });
        });
        
        // Handle Barcode export form submission
        $('#barcodeExportForm').submit(function(e) {
            e.preventDefault();
            const form = $(this);
            const section = form.find('#barcodeSectionFilter').val();
            const status = form.find('#barcodeStatusFilter').val();
            const perPage = form.find('#barcodePerPage').val();
            
            // Open in new tab for printing
            const newWindow = window.open('', '_blank');
            newWindow.document.write('<div>Loading barcodes...</div>');
            
            $.ajax({
                type: 'POST',
                url: 'Manage_Students.php',
                data: {
                    export_barcode: true,
                    section: section,
                    status: status,
                    per_page: perPage
                },
                success: function(response) {
                    newWindow.document.open();
                    newWindow.document.write(response);
                    newWindow.document.close();
                },
                error: function() {
                    newWindow.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Export Failed',
                        text: 'Could not generate barcodes',
                        confirmButtonColor: '#d33',
                    });
                }
            });
        });
        
       
    });
    
    function generateBarcode(lrn, selector) {
        const container = $(selector);
        container.html('');
        
        if (lrn && lrn.length >= 12) { // More flexible validation
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('class', 'barcode');
            svg.setAttribute('jsbarcode-value', lrn);
            svg.setAttribute('jsbarcode-format', 'CODE128');
            svg.setAttribute('jsbarcode-displayValue', 'true');
            svg.setAttribute('jsbarcode-height', '40');
            
            container.html(svg);
            
            try {
                JsBarcode(svg, lrn, {
                    format: "CODE128",
                    displayValue: true,
                    height: 40,
                    fontSize: 12
                });
            } catch (e) {
                container.html('<div class="text-danger">Invalid LRN format</div>');
            }
        } else if (!lrn) {
            container.html('<div class="barcode-placeholder">Enter LRN to generate barcode</div>');
        } else {
            container.html('<div class="text-danger">LRN must be at least 12 characters</div>');
        }
    }
    
    // Toggle show/hide for password fields
    function togglePassword(fieldId, icon) {
        const field = document.getElementById(fieldId);
        const isHidden = field.type === 'password';
        
        field.type = isHidden ? 'text' : 'password';
        
        // Toggle icons
        icon.classList.toggle('fa-eye', !isHidden);
        icon.classList.toggle('fa-eye-slash', isHidden);
    }
    
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

$('#profileForm').on('submit', function() {
    $('#saveProfileBtn').prop('disabled', true);
    $('#saveProfileBtn .btn-text').hide();
    $('#saveProfileBtn').append('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
});
</script>
<?php include 'search/Search_Admin.php'; ?>
</body>

</html>