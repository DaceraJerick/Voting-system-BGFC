<?php
// filename: admin/candidate_applications.php

require_once '../config/database.php';
require_once '../includes/auth_check.php';

if($_SESSION['user_type'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$message = isset($_GET['msg']) ? $_GET['msg'] : '';
$error = isset($_GET['err']) ? $_GET['err'] : '';

$filter_status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$filter_election = isset($_GET['election_id']) ? $_GET['election_id'] : '';

$all_elections = $pdo->query("SELECT id, title FROM elections ORDER BY created_at DESC")->fetchAll();

$query = "
    SELECT a.*, 
           COALESCE(e.title, 'Unknown Election') as election_title, 
           COALESCE(s.full_name, 'Unknown Student') as full_name, 
           COALESCE(s.student_id, 'N/A') as s_id, 
           s.profile_photo as s_photo
    FROM candidate_applications a
    LEFT JOIN elections e ON a.election_id = e.id
    LEFT JOIN students s ON a.student_id = s.student_id
    WHERE 1=1
";
$params = [];

if($filter_status) {
    $query .= " AND a.status = ?";
    $params[] = $filter_status;
}
if($filter_election) {
    $query .= " AND a.election_id = ?";
    $params[] = $filter_election;
}

$query .= " ORDER BY a.submitted_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll();

include '../includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap');

body {
  font-family: 'Plus Jakarta Sans', sans-serif;
  min-height: 100vh;
  background: #f0f4f8;
  color: #1e293b;
}

[data-theme="dark"] body {
  background: #0d0f1a !important;
  color: #e2e8f0 !important;
}

.page-wrap { position:relative;z-index:1; max-width: 1400px; margin: 0 auto; padding: 1.5rem 1.75rem 4rem; }

.page-hdr { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem; padding: 0 0.5rem; }
.page-hdr h2 { font-family:'Sora',sans-serif; font-size:clamp(1.4rem,2.5vw,2rem);font-weight:800;letter-spacing:-.025em;margin:0; }
.page-hdr p  { color:#64748b;font-size:.9rem;margin:.3rem 0 0; }

.glass {
    background:rgba(255,255,255,0.7);
    backdrop-filter:blur(20px);
    border:1px solid rgba(0,0,0,0.05);
    border-radius:20px;
    box-shadow:0 4px 24px rgba(0,0,0,0.02);
}
[data-theme="dark"] .glass {
    background: rgba(20,22,35,0.85) !important;
    border-color: rgba(255,255,255,0.08) !important;
}

.filter-bar {
    padding:1.2rem 1.6rem;margin-bottom:2rem;
    display:flex;align-items:center;gap:1rem;
    flex-wrap:wrap;
}

.select-glass {
    padding:.7rem 1.2rem;
    background:rgba(255,255,255,.9)!important;
    border:1.5px solid rgba(0,0,0,0.08)!important;
    border-radius:14px!important;font-size:.875rem!important;
    color:#1e293b!important;cursor:pointer;
    transition:all .2s;outline:none;
}
[data-theme="dark"] .select-glass {
    background:rgba(15,17,27,0.9)!important;
    border-color:rgba(99,102,241,0.15)!important;
    color:#e2e8f0!important;
}

.table-responsive {
    border-radius: 20px;
    overflow: auto;
    max-height: 600px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: rgba(0,0,0,0.03);
    padding: 1rem 1.5rem;
    font-size: 0.8rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #475569;
    text-align: left;
}
[data-theme="dark"] th { background: rgba(255,255,255,0.03); color: #94a3b8; }

td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    font-size: 0.9rem;
}
[data-theme="dark"] td { border-bottom-color: rgba(255,255,255,0.05); }

.status-badge {
    padding: 0.35rem 0.85rem;
    border-radius: 99px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
}
.status-pending { background: rgba(251,191,36,0.15); color: #b45309; }
.status-approved { background: rgba(34,197,94,0.15); color: #15803d; }
.status-rejected { background: rgba(239,68,68,0.15); color: #b91c1c; }

[data-theme="dark"] .status-pending { color: #fcd34d; }
[data-theme="dark"] .status-approved { color: #86efac; }
[data-theme="dark"] .status-rejected { color: #fca5a5; }

.btn-view {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: rgba(99,102,241,0.1);
    color: #6366f1;
    border: none; border-radius: 12px;
    padding: 0.5rem 1rem;
    font-size: 0.85rem; font-weight: 700;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-view:hover { background: rgba(99,102,241,0.2); color: #4f46e5; }
[data-theme="dark"] .btn-view { color: #818cf8; }

[data-theme="dark"] .form-check-input {
    background-color: rgba(255,255,255,0.1) !important;
    border-color: rgba(255,255,255,0.4) !important;
}
[data-theme="dark"] .form-check-input:checked {
    background-color: #6366f1 !important;
    border-color: #6366f1 !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='M6 10l3 3l6-6'/%3e%3c/svg%3e") !important;
}

</style>

<div class="page-wrap">
    <div class="page-hdr">
        <div>
            <h2>📝 Candidate Applications</h2>
            <p>Review and approve students who want to run as candidates.</p>
        </div>
    </div>

    <?php if($message): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4 rounded-4 border-0 shadow-sm">
        <i class="bi bi-check-circle-fill"></i>
        <span><?php echo htmlspecialchars($message); ?></span>
    </div>
    <?php endif; ?>
    <?php if($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4 rounded-4 border-0 shadow-sm">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
    </div>
    <?php endif; ?>

    <div class="filter-bar glass">
        <form method="GET" style="display:flex; gap:1rem; flex-wrap:wrap; width:100%;">
            <select name="status" class="select-glass" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="approved" <?php echo $filter_status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="rejected" <?php echo $filter_status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
            <select name="election_id" class="select-glass" onchange="this.form.submit()">
                <option value="">All Elections</option>
                <?php foreach($all_elections as $e): ?>
                <option value="<?php echo $e['id']; ?>" <?php echo $filter_election == $e['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($e['title']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <form action="approve_application.php" method="POST" id="bulkForm">
        <input type="hidden" name="action" value="bulk_approve">
        <div style="margin-bottom: 1rem; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" onclick="return confirm('Approve selected applications?');">
                <i class="bi bi-check2-all"></i> Approve Selected
            </button>
        </div>
        <div class="glass table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">
                            <input type="checkbox" id="selectAll" class="form-check-input" style="cursor: pointer; width: 1.2em; height: 1.2em;">
                        </th>
                        <th>Student</th>
                    <th>Position Applied</th>
                    <th>Election</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($applications) > 0): ?>
                    <?php foreach($applications as $app): ?>
                    <tr>
                        <td style="text-align: center;">
                            <?php if($app['status'] == 'pending'): ?>
                            <input type="checkbox" name="app_ids[]" value="<?php echo $app['id']; ?>" class="form-check-input app-checkbox" style="cursor: pointer; width: 1.2em; height: 1.2em;">
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight:700;"><?php echo htmlspecialchars($app['full_name']); ?></div>
                            <div style="font-size:0.75rem; color:#64748b;"><?php echo htmlspecialchars($app['s_id']); ?></div>
                        </td>
                        <td><div style="font-weight:600; color:#6366f1;"><?php echo htmlspecialchars($app['position_applied']); ?></div></td>
                        <td><?php echo htmlspecialchars($app['election_title']); ?></td>
                        <td><?php echo date('M d, Y h:i A', strtotime($app['submitted_at'])); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $app['status']; ?>">
                                <?php echo ucfirst($app['status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="view_application.php?id=<?php echo $app['id']; ?>" class="btn-view">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding: 3rem;">
                            <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;">📭</div>
                            <div style="font-weight: 700; color: #475569;">No applications found</div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </form>
</div>

<script>
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.app-checkbox');
    for(let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = this.checked;
    }
});
</script>

<?php include '../includes/footer.php'; ?>
