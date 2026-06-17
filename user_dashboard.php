<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$reports = $stmt->fetchAll();

// Fetch tasks assigned to this resident by admin
$assigned_stmt = $pdo->prepare("
    SELECT reports.*, users.username AS submitter_name
    FROM reports
    LEFT JOIN users ON reports.user_id = users.id
    WHERE reports.assigned_to = ?
    ORDER BY reports.created_at DESC
");
$assigned_stmt->execute([$_SESSION['user_id']]);
$assigned_tasks = $assigned_stmt->fetchAll();

// Fetch action notes for assigned tasks
$task_actions = [];
if (!empty($assigned_tasks)) {
    $task_ids = array_column($assigned_tasks, 'id');
    $placeholders = implode(',', array_fill(0, count($task_ids), '?'));
    $actions_stmt = $pdo->prepare("
        SELECT report_actions.*, users.username
        FROM report_actions
        JOIN users ON report_actions.user_id = users.id
        WHERE report_actions.report_id IN ($placeholders)
        ORDER BY report_actions.created_at DESC
    ");
    $actions_stmt->execute($task_ids);
    foreach ($actions_stmt->fetchAll() as $action) {
        $task_actions[$action['report_id']][] = $action;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard | HelpDesk</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.maptiler.com/maptiler-sdk-js/v2.0.3/maptiler-sdk.css" rel="stylesheet" />
</head>
<body>

<nav class="navbar admin-topbar">
    <div class="container">
        <a class="navbar-brand admin-brand" href="#"><i class="bi bi-person-lines-fill me-2"></i>Resident Dashboard</a>
        <div class="ms-auto">
            <span class="me-3 admin-user-chip">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a href="logout.php" class="btn btn-fb-primary btn-compact">Logout</a>
        </div>
    </div>
</nav>

<div class="hero-section admin-hero-section">
    <div class="container">
        <div class="admin-heading">
            <h1 class="fw-bold mb-2"><i class="bi bi-clipboard-check me-2"></i>My Complaint Dashboard</h1>
            <p>Submit concerns, handle admin-assigned tasks, and track your report progress.</p>
        </div>
        <div class="mb-4">
            <button class="btn btn-fb-primary fw-bold px-4" onclick="toggleModal('assignedTasksModal')"><i class="bi bi-list-task me-2"></i>Manage Assigned Tasks (<?php echo count($assigned_tasks); ?>)</button>
        </div>
        <div class="row admin-stat-grid admin-stat-grid-wide">
            <div class="col-lg-4 col-md-6">
                <div class="card-fb text-center p-4 admin-stat-card admin-stat-total">
                    <div class="fs-3 fw-bold text-primary mb-1"><?php echo count($reports); ?></div>
                    <p class="text-muted mb-0 small">My Submissions</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card-fb text-center p-4 admin-stat-card admin-stat-pending">
                    <div class="fs-3 fw-bold text-primary mb-1"><?php echo count(array_filter($assigned_tasks, fn($r) => $r['status'] !== 'Resolved')); ?></div>
                    <p class="text-muted mb-0 small">Tasks In Progress</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card-fb text-center p-4 admin-stat-card admin-stat-resolved">
                    <div class="fs-3 fw-bold text-dark mb-1"><?php echo count(array_filter($assigned_tasks, fn($r) => $r['status'] === 'Resolved')); ?></div>
                    <p class="text-muted mb-0 small">Tasks Resolved</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container user-dashboard-content">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success py-2 small mb-3"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger py-2 small mb-3"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Assigned Tasks from Admin (disabled; use Tasks Assigned to Me only) -->
    <div class="row mb-4" style="display:none;">

        <div class="col-12">
            <div class="card p-4 admin-table-card">
                <div class="admin-table-toolbar mb-3">
                    <div>
                        <h5 class="fw-bold mb-1"><i class="bi bi-briefcase me-2"></i>Tasks From Admin</h5>
                        <p class="text-muted mb-0">Update status and save progress for tasks assigned by the admin.</p>
                    </div>
                </div>


                <?php if (empty($assigned_tasks)): ?>
                    <div class="text-center text-muted admin-empty-cell py-4">
                        <i class="bi bi-inbox admin-empty-icon"></i>
                        <p class="mb-0 mt-2">No tasks assigned yet. The admin will assign complaints here for you to handle.</p>
                    </div>
                <?php else: ?>
                    <div class="assigned-tasks-list">
                        <?php foreach ($assigned_tasks as $task): ?>
                            <?php $actions = $task_actions[$task['id']] ?? []; ?>
                            <div class="assigned-task-card">
                                <div class="assigned-task-header">
                                    <div>
                                        <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($task['title']); ?></h6>
                                        <div class="small text-muted">
                                            <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($task['category']); ?></span>
                                            Submitted by <?php echo htmlspecialchars($task['submitter_name'] ?? 'Anonymous Guest'); ?>
                                            &middot; <?php echo date('M d, Y', strtotime($task['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($task['ai_summary'])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" style="font-size: 0.7rem; border-radius: 6px;" 
                                                    onclick="openAiSummaryModal(this)"
                                                    data-title="<?php echo htmlspecialchars($task['title']); ?>"
                                                    data-summary="<?php echo htmlspecialchars($task['ai_summary']); ?>">
                                                <i class="bi bi-translate me-1"></i>AI Summary
                                            </button>
                                        <?php endif; ?>
                                        <span class="badge status-<?php echo strtolower($task['status']); ?>">
                                            <?php echo htmlspecialchars($task['status']); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="assigned-task-description">
                                    <span class="meta-label">Complaint Details</span>
                                    <p><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                                </div>

                                <div class="assigned-task-actions-row">
                                    <form action="resident_update_status.php" method="POST" class="resident-status-form">
                                        <input type="hidden" name="id" value="<?php echo (int) $task['id']; ?>">
                                        <label class="form-label small fw-bold mb-1">Update Status</label>
                                        <div class="resident-status-controls">
                                            <select name="status" class="dashboard-form-select admin-status-select">
                                                <option value="Pending" <?php echo $task['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="Ongoing" <?php echo $task['status'] === 'Ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                                <option value="Resolved" <?php echo $task['status'] === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                            </select>
                                            <button type="submit" class="btn btn-fb-primary admin-update-btn">Update Status</button>
                                        </div>
                                    </form>

                                    <form action="add_report_action.php" method="POST" class="resident-action-form">
                                        <input type="hidden" name="id" value="<?php echo (int) $task['id']; ?>">
                                        <label class="form-label small fw-bold mb-1">Actions Taken</label>
                                        <textarea
                                            name="note"
                                            class="form-control dashboard-form-control"
                                            rows="3"
                                            placeholder="Describe what you did to address this complaint (e.g. inspected the area, contacted maintenance, scheduled repair)..."
                                            required
                                        ></textarea>
                                        <button type="submit" class="btn btn-fb-primary mt-2">
                                            <i class="bi bi-journal-text me-1"></i>Save Action Note
                                        </button>
                                    </form>
                                </div>

                                <?php if (!empty($actions)): ?>
                                    <div class="action-notes-timeline">
                                        <span class="meta-label">Previous Actions</span>
                                        <?php foreach ($actions as $action): ?>
                                            <div class="action-note-item">
                                                <div class="action-note-meta">
                                                    <strong><?php echo htmlspecialchars($action['username']); ?></strong>
                                                    <span><?php echo date('M d, Y h:i A', strtotime($action['created_at'])); ?></span>
                                                </div>
                                                <p><?php echo nl2br(htmlspecialchars($action['note'])); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Submission Form -->
        <div class="col-lg-4">
            <div class="card p-4 admin-table-card">
                <h5 class="fw-bold mb-3">Submit a New Request</h5>
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success py-2 small"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <form action="submit_report.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Title</label>
                        <input type="text" name="title" class="form-control dashboard-form-control" placeholder="Brief summary" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Category</label>
                        <select name="category" class="form-select dashboard-form-select" required>
                            <option value="Complaint">Complaint</option>
                            <option value="Concern">Concern</option>
                            <option value="Service Request">Service Request</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Description</label>
                        <textarea name="description" class="form-control dashboard-form-control" rows="4" placeholder="Provide more details..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pin Location (Optional)</label>
                        <div id="map" class="user-map"></div>
                        <input type="hidden" name="latitude" id="lat">
                        <input type="hidden" name="longitude" id="lng">
                        <small class="text-muted">Click on the map to mark the location of the issue.</small>
                    </div>
                    <button type="submit" class="btn btn-fb-primary w-100 fw-bold">Submit Report</button>
                </form>
            </div>
        </div>

        <!-- Tracking List -->
        <div class="col-lg-8">
            <div class="card p-4 admin-table-card">
            <div class="card p-4 admin-table-card mb-4">
                <div class="admin-table-toolbar">
                    <div>
                        <h5 class="fw-bold mb-1">Track My Reports</h5>
                        <p class="text-muted mb-0">Filter by status or search by title/category.</p>
                        <h5 class="fw-bold mb-1"><i class="bi bi-send-check me-2"></i>My Reported Issues</h5>
                        <p class="text-muted mb-0">Monitor the progress of reports you created.</p>
                    </div>
                    <div class="user-report-toolbar">
                        <input
                            type="text"
                            id="reportSearch"
                            class="form-control dashboard-form-control user-report-search"
                            placeholder="Search reports..."
                        >
                        <select id="userStatusFilter" class="dashboard-form-select user-report-filter">
                            <option value="">All Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Resolved">Resolved</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="userReportsBody">
                            <?php if (empty($reports)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted admin-empty-cell">No reports submitted yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reports as $report): ?>
                                <tr
                                    data-title="<?php echo htmlspecialchars(strtolower($report['title']), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-category="<?php echo htmlspecialchars(strtolower($report['category']), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-status="<?php echo htmlspecialchars(strtolower($report['status']), ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <td class="small"><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($report['title']); ?></td>
                                    <td><span class="small text-muted"><?php echo $report['category']; ?></span></td>
                                    <td>
                                        <span class="badge status-<?php echo strtolower($report['status']); ?>">
                                            <?php echo $report['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr id="userReportsEmptyFiltered" style="display: none;">
                                    <td colspan="4" class="text-center text-muted admin-empty-cell">No reports match your filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tasks Assigned to Me Section -->
            <div class="card p-4 admin-table-card">
                <div class="admin-table-toolbar">
                    <h5 class="fw-bold mb-1"><i class="bi bi-briefcase me-2"></i>Tasks Assigned to Me</h5>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Task Details</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($assigned_tasks)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-4">No tasks currently assigned to you.</td></tr>
                            <?php else: ?>
                                <?php foreach ($assigned_tasks as $task): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($task['title']); ?></div>
                                        <p class="small text-muted mb-0"><?php echo htmlspecialchars($task['description']); ?></p>
                                    </td>
                                    <td>
                                        <span class="badge status-<?php echo strtolower($task['status']); ?>">
                                            <?php echo $task['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button class="btn btn-sm btn-fb-primary py-1 px-3" type="button" onclick="openResidentTaskStatusModal(<?php echo (int)$task['id']; ?>, '<?php echo addslashes($task['status']); ?>')">Update</button>
                                            <?php if (!empty($task['ai_summary'])): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success py-1 px-2" 
                                                        onclick="openAiSummaryModal(this)"
                                                        data-title="<?php echo htmlspecialchars($task['title']); ?>"
                                                        data-summary="<?php echo htmlspecialchars($task['ai_summary']); ?>">
                                                    <i class="bi bi-translate"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        
                                      
                                                
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resident Task Status Update Modal -->
<div id="residentTaskStatusModal" class="modal">
    <div class="modal-content" style="max-width: 520px; margin: 10% auto; border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
        <div class="modal-header" style="background: #1877f2; color: white; border-top-left-radius: 12px; border-top-right-radius: 12px;">
            <h5 class="fw-bold mb-0"><i class="bi bi-pencil-square me-2"></i>Update Task</h5>
            <button type="button" class="btn-close btn-close-white" onclick="closeResidentTaskStatusModal()">&times;</button>
        </div>
        <div class="modal-body p-4">
            <form id="residentTaskStatusUpdateForm" action="resident_update_task.php" method="POST">
                <input type="hidden" name="id" id="residentTaskStatusReportId" value="">

                <div class="mb-3">
                    <label class="form-label small fw-bold">Status</label>
                    <select name="status" id="residentTaskStatusSelect" class="dashboard-form-select admin-status-select" required>
                        <option value="Pending">Pending</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Resolved">Resolved</option>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-bold">Small note / action taken</label>
                    <textarea name="action_taken" id="residentTaskStatusActionTaken" class="form-control dashboard-form-control" rows="4" placeholder="Small note that will reflect on the admin dashboard timeline" required></textarea>
                </div>

                <button type="submit" class="btn btn-fb-primary w-100 mt-3">
                    <i class="bi bi-check2-circle me-2"></i>Save Update
                </button>
            </form>
        </div>
    </div>
</div>

<!-- AI Summary Modal -->
<div id="summaryOnlyModal" class="modal">
    <div class="modal-content" style="max-width: 500px; margin: 10% auto; border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
        <div class="modal-header" style="background: #27ae60; color: white; border-top-left-radius: 12px; border-top-right-radius: 12px;">
            <h5 class="fw-bold mb-0"><i class="bi bi-translate me-2"></i>English AI Summary</h5>
            <button type="button" class="btn-close btn-close-white" onclick="closeSummaryOnlyModal()">&times;</button>
        </div>
        <div class="modal-body p-4">
            <h6 id="summaryModalTitle" class="fw-bold text-muted mb-3" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;"></h6>
            <div id="summaryModalContent" class="small" style="line-height: 1.6; color: #2c3e50;"></div>
        </div>
    </div>
</div>

<script src="https://cdn.maptiler.com/maptiler-sdk-js/v2.0.3/maptiler-sdk.umd.js"></script>
<script>
    maptilersdk.config.apiKey = '2KCcsKR95Ru4UnqIGZjd';
    
    const map = new maptilersdk.Map({
        container: 'map',
        style: maptilersdk.MapStyle.STREETS,
        center: [120.9842, 14.5995], // [lng, lat] - Manila
        zoom: 12
    });

    // Fix for "Image could not be loaded" console error
    map.on('styleimagemissing', (e) => {
        // Provide a 1x1 transparent pixel for any missing images
        const emptyImage = { width: 1, height: 1, data: new Uint8Array(4) };
        map.addImage(e.id, emptyImage);
    });

    let marker = null;

    map.on('click', (e) => {
        if (marker) marker.remove();
        marker = new maptilersdk.Marker({ color: "#1877f2" })
            .setLngLat(e.lngLat)
            .addTo(map);
        
        document.getElementById("lat").value = e.lngLat.lat.toFixed(8);
        document.getElementById("lng").value = e.lngLat.lng.toFixed(8);
    });

    function filterUserReports() {
        const search = (document.getElementById('reportSearch')?.value || '').trim().toLowerCase();
        const status = (document.getElementById('userStatusFilter')?.value || '').toLowerCase();
        const rows = document.querySelectorAll('#userReportsBody tr[data-status]');
        let visibleCount = 0;

        rows.forEach((row) => {
            const title = row.getAttribute('data-title') || '';
            const category = row.getAttribute('data-category') || '';
            const rowStatus = row.getAttribute('data-status') || '';

            const matchSearch = search === '' || title.includes(search) || category.includes(search);
            const matchStatus = status === '' || rowStatus === status;
            const show = matchSearch && matchStatus;
            row.style.display = show ? '' : 'none';

            if (show) visibleCount += 1;
        });

        const filteredEmpty = document.getElementById('userReportsEmptyFiltered');
        if (filteredEmpty) {
            filteredEmpty.style.display = visibleCount === 0 ? '' : 'none';
        }
    }

    document.getElementById('reportSearch')?.addEventListener('input', filterUserReports);
    document.getElementById('userStatusFilter')?.addEventListener('change', filterUserReports);

    function toggleModal(id) {
        document.getElementById(id).classList.toggle('show');
    }

    function toggleTaskModal(id) {
        document.getElementById('taskModal' + id).classList.toggle('show');
    }

    function openResidentTaskStatusModal(reportId, currentStatus) {
        const modal = document.getElementById('residentTaskStatusModal');
        const idInput = document.getElementById('residentTaskStatusReportId');
        const statusSelect = document.getElementById('residentTaskStatusSelect');

        idInput.value = reportId;
        if (currentStatus && ['Pending','Ongoing','Resolved'].includes(currentStatus)) {
            statusSelect.value = currentStatus;
        }

        const actionTaken = document.getElementById('residentTaskStatusActionTaken');
        if (actionTaken) actionTaken.value = '';

        modal.classList.add('show');
    }

    function closeResidentTaskStatusModal() {
        document.getElementById('residentTaskStatusModal').classList.remove('show');
    }


    function openAiSummaryModal(element) {
        const title = element.getAttribute('data-title');
        const summary = element.getAttribute('data-summary');
        document.getElementById('summaryModalTitle').innerText = title;
        document.getElementById('summaryModalContent').innerHTML = summary ? summary.split('\n').map(line => `<div class="mb-2">${line}</div>`).join('') : 'No summary available.';
        document.getElementById('summaryOnlyModal').classList.add('show');
    }

    function closeSummaryOnlyModal() {
        document.getElementById('summaryOnlyModal').classList.remove('show');
    }

    // Close modals on window click
    window.onclick = function(event) {
        const summaryModal = document.getElementById('summaryOnlyModal');
        const assignedModal = document.getElementById('assignedTasksModal');
        if (event.target == summaryModal) closeSummaryOnlyModal();
        if (event.target == assignedModal) toggleModal('assignedTasksModal');
        
        // Handle task modals if clicked outside
        if (event.target.classList.contains('modal') && event.target.id.startsWith('taskModal')) {
            event.target.classList.remove('show');
        }
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
