<?php

session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['user_role'] !== 'Teacher') exit;

$user = $_SESSION['user'];
$teacher_id = $user['id'];

require_once 'config.php'; 
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$per_page_reports = 10;
$page_reports = isset($_GET['page_reports']) ? (int)$_GET['page_reports'] : 1;
$offset_reports = ($page_reports - 1) * $per_page_reports;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query
$reports_query = "SELECT r.*, s.stud_name 
                  FROM absent_reports_tbl r
                  JOIN student_tbl s ON r.student_lrn = s.lrn
                  WHERE r.teacher_id = $teacher_id";
if ($status_filter !== 'all') {
    $reports_query .= " AND r.status = '$status_filter'";
}
$count_reports_query = "SELECT COUNT(*) as total FROM absent_reports_tbl WHERE teacher_id = $teacher_id";
if ($status_filter !== 'all') {
    $count_reports_query .= " AND status = '$status_filter'";
}
$count_reports_result = $conn->query($count_reports_query);
$total_reports = $count_reports_result->fetch_assoc()['total'];

$reports_query .= " ORDER BY r.submitted_at DESC LIMIT $per_page_reports OFFSET $offset_reports";
$reports_result = $conn->query($reports_query);
$reports = [];
while ($row = $reports_result->fetch_assoc()) {
    $reports[] = $row;
}

// Output only the table and pagination HTML (copy from your main file)
?>
<div class="table-responsive">
    <table class="table table-hover">
        <thead class="thead-light">
            <tr>
                <th>Report ID</th>
                <th>Student</th>
                <th>Date Range</th>
                <th>Sessions</th>
                <th>Status</th>
                <th>Admin Remarks</th>
                <th>Submitted</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($reports)): ?>
                <?php foreach ($reports as $report): ?>
                    <tr>
                        <td>#<?= $report['report_id'] ?></td>
                        <td><?= $report['stud_name'] ?></td>
                        <td>
                            <?= date('M d', strtotime($report['date_range_start'])) ?> - 
                            <?= date('M d, Y', strtotime($report['date_range_end'])) ?>
                        </td>
                        <td><?= $report['absent_sessions'] ?></td>
                        <td>
                            <?php 
                            $badge_class = '';
                            if ($report['status'] === 'approved') $badge_class = 'badge-approved';
                            elseif ($report['status'] === 'pending') $badge_class = 'badge-pending';
                            elseif ($report['status'] === 'rejected') $badge_class = 'badge-rejected';
                            ?>
                            <span class="status-badge <?= $badge_class ?>">
                                <?= ucfirst($report['status']) ?>
                            </span>
                        </td>
                        <td><?= $report['admin_remarks'] ?: '--' ?></td>
                        <td><?= date('M d, Y h:i A', strtotime($report['submitted_at'])) ?></td>
                        <td>
                            <?php if ($report['status'] === 'pending'): ?>
                                <button class="btn btn-action btn-outline-primary edit-btn" 
                                        data-id="<?= $report['report_id'] ?>"
                                        data-student="<?= $report['student_lrn'] ?>"
                                        data-start="<?= $report['date_range_start'] ?>"
                                        data-end="<?= $report['date_range_end'] ?>"
                                        data-sessions="<?= $report['absent_sessions'] ?>"
                                        data-details="<?= htmlspecialchars($report['details']) ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" class="delete-form">
                                <input type="hidden" name="delete_report" value="1">
                                <input type="hidden" name="report_id" value="<?= $report['report_id'] ?>">
                                <button type="button" class="btn btn-action btn-outline-danger delete-btn">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center">No reports submitted yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php if ($total_reports > 0): ?>
    <div class="pagination-container mt-4">
        <div class="pagination-info">
            Showing <?= min($per_page_reports, $total_reports - $offset_reports) ?> of <?= $total_reports ?> reports
        </div>
        <div class="pagination-links">
            <ul class="pagination">
                <?php if ($page_reports > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?status=<?= $status_filter ?>&page_reports=<?= $page_reports - 1 ?>">Previous</a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <a class="page-link" href="#">Previous</a>
                    </li>
                <?php endif; ?>
                <?php 
                $total_pages_reports = ceil($total_reports / $per_page_reports);
                $start_page = max(1, $page_reports - 2);
                $end_page = min($total_pages_reports, $start_page + 4);
                if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?status=' . $status_filter . '&page_reports=1">1</a></li>';
                    if ($start_page > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }
                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?= $i == $page_reports ? 'active' : '' ?>">
                        <a class="page-link" href="?status=<?= $status_filter ?>&page_reports=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; 
                if ($end_page < $total_pages_reports) {
                    if ($end_page < $total_pages_reports - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?status=' . $status_filter . '&page_reports=' . $total_pages_reports . '">' . $total_pages_reports . '</a></li>';
                }
                ?>
                <?php if ($page_reports < $total_pages_reports): ?>
                    <li class="page-item">
                        <a class="page-link" href="?status=<?= $status_filter ?>&page_reports=<?= $page_reports + 1 ?>">Next</a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <a class="page-link" href="#">Next</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>