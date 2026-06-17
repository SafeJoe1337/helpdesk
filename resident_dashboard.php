<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch reports assigned specifically to this resident
$stmt = $pdo->prepare("SELECT * FROM reports WHERE assigned_to = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$assigned_tasks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard | HelpDesk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar dashboard-navbar">
    <div class="container">
        <a class="navbar-brand" href="#">HelpDesk Resident Tasker</a>
        <div class="ms-auto">
            <span class="me-3 text-muted">User: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="card p-4">
        <h5 class="fw-bold mb-4">My Assigned Tasks</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Problem Details</th>
                        <th>Status</th>
                        <th>Action Taken (Paragraph)</th>
                        <th>Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assigned_tasks)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No tasks assigned to you yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($assigned_tasks as $task): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($task['title']); ?></div>
                                <small class="text-muted d-block mb-1"><?php echo $task['category']; ?> | <?php echo date('M d, Y', strtotime($task['created_at'])); ?></small>
                                <p class="small text-muted mb-0"><?php echo htmlspecialchars($task['description']); ?></p>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $task['status'] == 'Resolved' ? 'success' : ($task['status'] == 'Ongoing' ? 'info' : 'warning'); ?>">
                                    <?php echo $task['status']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="small italic text-muted" style="max-width: 250px;">
                                    <?php echo $task['action_taken'] ? htmlspecialchars($task['action_taken']) : 'No actions recorded yet.'; ?>
                                </div>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $task['id']; ?>">Update Progress</button>
                                
                                <!-- Update Modal -->
                                <div class="modal fade" id="updateModal<?php echo $task['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form action="resident_update_task.php" method="POST" class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Update Task Progress</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Update Status</label>
                                                    <select name="status" class="form-select">
                                                        <option value="Ongoing" <?php echo $task['status'] == 'Ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                                        <option value="Resolved" <?php echo $task['status'] == 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Actions Taken (Paragraph)</label>
                                                    <textarea name="action_taken" class="form-control" rows="5" required placeholder="Describe exactly what you did to handle this problem..."><?php echo htmlspecialchars($task['action_taken'] ?? ''); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
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

<!-- Resident AI Insights (English-like bullet list) -->
<div class="container mt-4 mb-5">
    <div class="card p-4 admin-table-card">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-lightbulb me-2"></i>AI Summary Insights</h5>
                <p class="text-muted mb-0">Auto-summarized from your reported issues.</p>
            </div>
            <button id="btn-resident-insights" class="btn btn-primary" type="button">Summarize</button>
        </div>

        <div id="resident-insights-container" style="display:none;">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card p-3 h-100 insight-card">
                        <h6 class="text-muted fw-bold">DOMINANT MOOD</h6>
                        <div class="mt-2 fs-4 fw-bold" id="resident-dominant-emotion">--</div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card p-3 h-100">
                        <h6 class="text-muted fw-bold">RECOMMENDATIONS</h6>
                        <ul id="resident-recommendations-list" class="mb-0"></ul>
                    </div>
                </div>
            </div>
        </div>

        <div id="resident-insights-error" class="text-danger mt-3" style="display:none;"></div>
    </div>
</div>

<script>
    async function generateResidentInsights() {
        const btn = document.getElementById('btn-resident-insights');
        const container = document.getElementById('resident-insights-container');
        const errorBox = document.getElementById('resident-insights-error');
        const list = document.getElementById('resident-recommendations-list');

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Summarizing...';
        errorBox.style.display = 'none';
        errorBox.textContent = '';

        try {
            const response = await fetch('resident_insights.php');
            const data = await response.json();
            if (data.error) throw new Error(data.error);

            // Our PHP endpoint returns either the same keys as insights.php or a similar structure.
            // bridge_analysis.py generally provides: { roberta: { dominant_emotion }, insights: { sentiment_summary, recommendations: [] } }
            document.getElementById('resident-dominant-emotion').innerText = data.roberta?.dominant_emotion ?? '--';

            list.innerHTML = '';
            const recs = data.insights?.recommendations ?? [];
            recs.forEach(r => {
                const li = document.createElement('li');
                li.className = 'small mb-2';
                li.innerText = r;
                list.appendChild(li);
            });

            container.style.display = 'block';
        } catch (err) {
            errorBox.textContent = 'AI Error: ' + err.message;
            errorBox.style.display = 'block';
            container.style.display = 'none';
        } finally {
            btn.disabled = false;
            btn.innerText = 'Summarize';
        }
    }

    document.getElementById('btn-resident-insights')?.addEventListener('click', generateResidentInsights);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
