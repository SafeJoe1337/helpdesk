<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Fetch stats
$total_stmt = $pdo->query("SELECT COUNT(*) as total FROM reports");
$total = $total_stmt->fetch()['total'];

$pending_stmt = $pdo->query("SELECT COUNT(*) as pending FROM reports WHERE status = 'Pending'");
$pending = $pending_stmt->fetch()['pending'];

$ongoing_stmt = $pdo->query("SELECT COUNT(*) as ongoing FROM reports WHERE status = 'Ongoing'");
$ongoing = $ongoing_stmt->fetch()['ongoing'];

$resolved_stmt = $pdo->query("SELECT COUNT(*) as resolved FROM reports WHERE status = 'Resolved'");
$resolved = $resolved_stmt->fetch()['resolved'];

// Fetch residents for assignment dropdown
$residents_stmt = $pdo->query("SELECT id, username FROM users WHERE role = 'resident' ORDER BY username ASC");
$residents = $residents_stmt->fetchAll();

// Fetch all reports with submitter and assignee names
$all_reports_stmt = $pdo->query("
    SELECT reports.*,
           users.username,
           assigned_user.username AS assigned_username
    FROM reports
    LEFT JOIN users ON reports.user_id = users.id
    LEFT JOIN users AS assigned_user ON reports.assigned_to = assigned_user.id
    ORDER BY created_at DESC
");
$reports = $all_reports_stmt->fetchAll();

// Fetch all resident action notes for the timeline
$actions_stmt = $pdo->query("
    SELECT ra.*, u.username 
    FROM report_actions ra 
    JOIN users u ON ra.user_id = u.id 
    ORDER BY ra.created_at ASC
");
$all_actions = [];
while ($row = $actions_stmt->fetch()) {
    $all_actions[$row['report_id']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | HelpDesk</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.maptiler.com/maptiler-sdk-js/v2.0.3/maptiler-sdk.css" rel="stylesheet" />
    <link rel="stylesheet" href="style.css">
    <style>
        /* ── AI Insights Panel ── */
        .insights-panel {
            background: #ffffff;
            border: 1px solid #dde4ec;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(17, 35, 58, 0.07);
            margin-bottom: 28px;
            overflow: hidden;
        }
        .insights-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 28px;
            background: linear-gradient(135deg, #0f4c81 0%, #1a6fc4 100%);
            cursor: pointer;
            user-select: none;
        }
        .insights-header h5 {
            color: #ffffff;
            font-weight: 700;
            margin: 0;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .insights-header .insights-badge {
            background: rgba(255,255,255,0.2);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 999px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .insights-header .insights-toggle-btn {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            padding: 8px 18px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }
        .insights-header .insights-toggle-btn:hover { background: rgba(255,255,255,0.28); }
        .insights-body { padding: 24px 28px; display: none; }
        .insights-body.open { display: block; }

        /* Loading state */
        .insights-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            gap: 14px;
        }
        .insights-spinner {
            width: 40px; height: 40px;
            border: 4px solid #e4edf6;
            border-top-color: #1f74d8;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .insights-loading p { color: #6b87a3; font-size: 0.9rem; margin: 0; }

        /* Error state */
        .insights-error {
            background: #fff0f0; border: 1px solid #ffd0d0; border-radius: 10px;
            padding: 16px 20px; color: #c0392b; font-size: 0.88rem; display: flex;
            align-items: center; gap: 10px;
        }

        /* Section title inside insights */
        .insight-section-title {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #8fa9c4;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .insight-section-title i { font-size: 0.88rem; color: #1f74d8; }

        /* Grid layout for insight cards */
        .insights-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 18px;
            margin-bottom: 20px;
        }
        @media (max-width: 991px) {
            .insights-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 640px) {
            .insights-grid { grid-template-columns: 1fr; }
        }

        .insight-card {
            background: #f7fbff;
            border: 1px solid #dce8f4;
            border-radius: 12px;
            padding: 18px;
        }
        .insight-card.full-width { grid-column: 1 / -1; }

        /* Polarity meter */
        .polarity-meter {
            position: relative;
            height: 10px;
            background: linear-gradient(to right, #e74c3c, #f39c12, #2ecc71);
            border-radius: 999px;
            margin: 10px 0;
        }
        .polarity-needle {
            position: absolute;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 18px; height: 18px;
            background: #fff;
            border: 3px solid #1f74d8;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(31,116,216,0.3);
            transition: left 1s cubic-bezier(.34,1.56,.64,1);
        }
        .polarity-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.72rem;
            color: #8fa9c4;
            margin-top: 4px;
        }
        .polarity-score {
            font-size: 2rem;
            font-weight: 700;
            color: #103a5f;
            line-height: 1;
        }
        .polarity-score span { font-size: 0.8rem; font-weight: 400; color: #8fa9c4; }

        /* Bar chart for distributions */
        .dist-bar-list { display: flex; flex-direction: column; gap: 8px; }
        .dist-bar-row { display: flex; align-items: center; gap: 10px; font-size: 0.82rem; }
        .dist-bar-label { width: 90px; color: #486684; font-weight: 600; flex-shrink: 0; }
        .dist-bar-track {
            flex: 1; height: 8px; background: #e4edf6; border-radius: 999px; overflow: hidden;
        }
        .dist-bar-fill {
            height: 100%; border-radius: 999px;
            transition: width 1.2s cubic-bezier(.34,1.2,.64,1);
        }
        .dist-bar-count { width: 24px; text-align: right; color: #8fa9c4; font-size: 0.78rem; }

        /* Emotion colors */
        .emo-Frustration { background: #e74c3c; }
        .emo-Urgency     { background: #e67e22; }
        .emo-Distress    { background: #c0392b; }
        .emo-Neutral     { background: #95a5a6; }
        .emo-Concern     { background: #3498db; }
        .emo-Satisfaction{ background: #2ecc71; }
        .emo-Gratitude   { background: #27ae60; }
        .emo-Positive    { background: #2ecc71; }
        .emo-Negative    { background: #e74c3c; }
        .emo-default     { background: #1f74d8; }

        /* Dominant emotion badge */
        .dominant-emotion-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.88rem;
            color: #fff;
            margin-top: 10px;
        }

        /* Risk flag */
        .risk-flag-active {
            background: #fff5f5; border: 1.5px solid #e74c3c; border-radius: 10px;
            padding: 14px 16px; display: flex; align-items: flex-start; gap: 12px;
        }
        .risk-flag-clear {
            background: #f0fdf4; border: 1.5px solid #2ecc71; border-radius: 10px;
            padding: 14px 16px; display: flex; align-items: flex-start; gap: 12px;
        }
        .risk-flag-icon { font-size: 1.4rem; flex-shrink: 0; margin-top: 1px; }
        .risk-flag-text { font-size: 0.86rem; color: #333; line-height: 1.5; }
        .risk-flag-text strong { display: block; margin-bottom: 2px; font-size: 0.88rem; }

        /* Themes tags */
        .theme-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
        .theme-tag {
            background: #e8f2fb; color: #0d4f86; border: 1px solid #c4ddf3;
            border-radius: 999px; padding: 6px 14px; font-size: 0.82rem; font-weight: 600;
        }

        /* Recommendations */
        .rec-list { display: flex; flex-direction: column; gap: 10px; margin-top: 8px; }
        .rec-item {
            display: flex; align-items: flex-start; gap: 10px;
            background: #f7fbff; border: 1px solid #dce8f4; border-radius: 10px;
            padding: 12px 14px; font-size: 0.86rem; color: #2d4a66; line-height: 1.5;
        }
        .rec-num, .timeline-dot {
            background: #1f74d8; color: #fff; border-radius: 999px;
            width: 22px; height: 22px; display: flex; align-items: center; justify-content: center;
            font-size: 0.72rem; font-weight: 700; flex-shrink: 0; margin-top: 1px;
        }

        /* Timeline Styles */
        .resident-timeline { border-left: 2px solid #e4edf6; margin-left: 10px; padding-left: 20px; }
        .timeline-item { position: relative; margin-bottom: 15px; }
        .timeline-item::before { content: ""; position: absolute; left: -26px; top: 5px; width: 10px; height: 10px; background: #1f74d8; border-radius: 50%; border: 2px solid #fff; }
        .timeline-date { font-size: 0.7rem; color: #8fa9c4; font-weight: 600; }
        .timeline-note { font-size: 0.85rem; color: #333; background: #f8f9fa; padding: 8px 12px; border-radius: 8px; margin-top: 4px; border: 1px solid #edf2f7; }

        /* Sentiment summary */
        .sentiment-summary-box {
            background: linear-gradient(135deg, #f0f7ff, #e8f2fb);
            border: 1px solid #c4ddf3;
            border-radius: 10px;
            padding: 14px 18px;
            font-size: 0.9rem;
            color: #1a3a57;
            line-height: 1.65;
            font-style: italic;
            margin-top: 8px;
        }

        /* Divider */
        .insight-divider { border: none; border-top: 1px solid #e4edf6; margin: 18px 0; }

        /* Per-report table inside insights */
        .per-report-scroll { max-height: 220px; overflow-y: auto; margin-top: 10px; }
        .per-report-table { width: 100%; font-size: 0.8rem; border-collapse: collapse; }
        .per-report-table th {
            background: #f2f7fc; border-bottom: 1px solid #dce8f4;
            font-weight: 700; color: #486684; padding: 8px 10px; text-align: left;
            position: sticky; top: 0;
        }
        .per-report-table td { padding: 7px 10px; border-bottom: 1px solid #edf3fa; vertical-align: middle; }
        .per-report-table tr:last-child td { border-bottom: none; }
        .mini-badge {
            display: inline-block; padding: 2px 10px; border-radius: 999px;
            font-size: 0.72rem; font-weight: 700; color: #fff;
        }
    </style>
</head>
<body>
<nav class="navbar admin-topbar">
    <div class="container">
        <a class="navbar-brand admin-brand" href="admin_dashboard.php">
            <i class="bi bi-tools me-2"></i>Admin Dashboard
        </a>
        <div class="ms-auto">
            <span class="me-3 admin-user-chip">
                <i class="bi bi-person-circle me-2"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
            </span>
            <a href="logout.php" class="btn btn-fb-primary btn-compact">Logout</a>
        </div>
    </div>
</nav>

<div class="hero-section admin-hero-section">
    <div class="container">
        <div class="admin-heading">
            <h1 class="fw-bold mb-2"><i class="bi bi-clipboard-data me-2"></i>Complaint Records</h1>
            <p>View and manage all community complaint submissions.</p>
        </div>
        <div class="row admin-stat-grid admin-stat-grid-wide">
            <div class="col-lg-3 col-md-6">
                <div class="admin-stat-card admin-stat-total">
                    <div class="stat-icon"><i class="bi bi-clipboard2-data-fill"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $total; ?></div>
                        <div class="stat-label">Total Reports</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="admin-stat-card admin-stat-pending">
                    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $pending; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="admin-stat-card admin-stat-ongoing">
                    <div class="stat-icon"><i class="bi bi-arrow-repeat"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $ongoing; ?></div>
                        <div class="stat-label">Ongoing</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="admin-stat-card admin-stat-resolved">
                    <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $resolved; ?></div>
                        <div class="stat-label">Resolved</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container my-5">

    <!-- ══════════ AI INSIGHTS PANEL ══════════ -->
    <div class="insights-panel" id="insightsPanel">
        <div class="insights-header" onclick="toggleInsights()">
            <h5>
                <i class="bi bi-stars"></i>
                AI Sentiment Insights
                <span class="insights-badge">TextBlob · RoBERTa · NLP</span>
            </h5>
            <button class="insights-toggle-btn" id="insightsToggleBtn" type="button">
                <i class="bi bi-chevron-down" id="insightsChevron"></i>
                Analyze Reports
            </button>
        </div>
        <div class="insights-body" id="insightsBody">
            <!-- Content injected by JS -->
        </div>
    </div>
    <!-- ════════════════════════════════════════ -->

    <div class="row">
        <div class="col-12">
            <div class="card p-5 admin-table-card">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success py-2 small mb-3"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger py-2 small mb-3"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                <div class="admin-table-toolbar">
                    <div>
                        <h5 class="mb-1">Submitted Complaints</h5>
                        <p class="text-muted mb-0">Assign complaints to residents and track their progress.</p>
                    </div>
                    <div class="admin-filter">
                        <label class="form-label fw-bold mb-2">Filter by Status:</label>
                        <select id="statusFilter" class="dashboard-form-select admin-filter-select">
                            <option value="">All Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Resolved">Resolved</option>
                        </select>
                    </div>
                </div>
                <div class="bulk-action-bar">
                    <span id="selectedCount" class="selected-count">0 selected</span>
                    <button type="button" class="btn btn-danger btn-compact" onclick="submitSelectedDeletes()"><i class="bi bi-trash me-2"></i>Delete Selected</button>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 60px;">
                                    <input type="checkbox" id="selectAllReports" title="Select all visible reports">
                                </th>
                                <th style="width: 200px;">Resident</th>
                                <th>Details</th>
                                <th style="width: 150px;">AI Insight</th>
                                <th style="width: 120px;">Status</th>
                                <th style="width: 220px;">Assign To</th>
                                <th style="width: 250px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reports)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-8 admin-empty-cell">
                                        <i class="bi bi-inbox admin-empty-icon"></i>
                                        <h5 class="text-muted mb-2">No reports found</h5>
                                        <p class="text-muted mb-0">Community concerns will appear here when submitted.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reports as $report): ?>
                                <tr data-status="<?php echo strtolower($report['status']); ?>" data-id="<?php echo (int)$report['id']; ?>">
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="report-checkbox"
                                            value="<?php echo (int)$report['id']; ?>"
                                            onchange="updateSelectedCount()"
                                            title="Select this complaint"
                                        >
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($report['username'] ?? 'Anonymous Guest'); ?></div>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($report['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($report['title']); ?></div>
                                        <div style="max-width: 400px;">
                                            <span class="badge bg-secondary mb-1"><?php echo htmlspecialchars($report['category']); ?></span>
                                            <?php $description = (string)($report['description'] ?? ''); ?>
                                            <button
                                                type="button"
                                                class="complaint-link"
                                                data-ai-emotion="<?php echo htmlspecialchars($report['ai_emotion'] ?? ''); ?>"
                                                data-ai-polarity="<?php echo htmlspecialchars($report['ai_polarity'] ?? ''); ?>"
                                                data-ai-summary="<?php echo htmlspecialchars($report['ai_summary'] ?? ''); ?>"
                                                data-ai-severity="<?php echo htmlspecialchars($report['ai_severity'] ?? ''); ?>"
                                                data-title="<?php echo htmlspecialchars($report['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-description="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-category="<?php echo htmlspecialchars($report['category'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-resident="<?php echo htmlspecialchars($report['username'] ?? 'Anonymous Guest', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-date="<?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($report['created_at'])), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-status="<?php echo htmlspecialchars($report['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-actions='<?php echo htmlspecialchars(json_encode($all_actions[$report['id']] ?? []), ENT_QUOTES, 'UTF-8'); ?>'
                                                onclick="openComplaintModalFromElement(this)"
                                            >
                                                <i class="bi bi-file-earmark-text me-2"></i>View Complaint
                                            </button>
                                            <?php if ($report['latitude'] && $report['longitude']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary mt-2 admin-map-btn"
                                                        onclick="viewOnMap(<?php echo $report['latitude']; ?>, <?php echo $report['longitude']; ?>, '<?php echo addslashes($report['title']); ?>')"
                                                >
                                                    <i class="bi bi-geo-alt"></i> View Location
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-success mt-2 ms-1 admin-summary-btn" 
                                                    onclick="openAiSummaryModal(this)"
                                                    data-title="<?php echo htmlspecialchars($report['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-summary="<?php echo htmlspecialchars($report['ai_summary'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    <?php echo empty($report['ai_summary']) ? 'disabled title="Run AI Analysis first"' : ''; ?>>
                                                <i class="bi bi-translate me-1"></i>English Summary
                                            </button>
                                        </div>
                                    </td>
                                    <td class="ai-insight-cell" data-report-id="<?php echo (int)$report['id']; ?>">
                                        <?php if (!empty($report['ai_emotion'])): ?>
                                            <?php 
                                                $emo = $report['ai_emotion'];
                                                $pol = $report['ai_polarity'];
                                                $sev = $report['ai_severity'];
                                                $polColor = $pol === 'Positive' ? '#2ecc71' : ($pol === 'Negative' ? '#e74c3c' : '#95a5a6');
                                            ?>
                                            <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                                <div class="d-flex gap-1">
                                                    <span class="mini-badge" style="background: var(--emo-<?php echo $emo; ?>, #1f74d8);"><?php echo $emo; ?></span>
                                                    <span class="mini-badge" style="background:<?php echo $polColor; ?>;"><?php echo $pol; ?></span>
                                                </div>
                                                <div class="small text-muted mt-1" style="font-size: 0.65rem; line-height: 1.2; font-style: italic; max-width: 130px;">
                                                    <?php echo htmlspecialchars(explode("\n", $report['ai_summary'])[0]); ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 ai-analyze-btn" style="font-size: 0.7rem; border-radius: 6px;" onclick="if(!insightsLoaded) toggleInsights();">
                                            <i class="bi bi-cpu me-1"></i>Analyze
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge status-<?php echo strtolower($report['status']); ?>" id="row-status-badge-<?php echo $report['id']; ?>">
                                            <?php echo $report['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form action="assign_report.php" method="POST" class="admin-assign-form">
                                            <input type="hidden" name="id" value="<?php echo (int) $report['id']; ?>">
                                            <select name="assigned_to" class="dashboard-form-select admin-assign-select">
                                                <option value="">Unassigned</option>
                                                <?php foreach ($residents as $resident): ?>
                                                    <option value="<?php echo (int) $resident['id']; ?>" <?php echo (int) ($report['assigned_to'] ?? 0) === (int) $resident['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($resident['username']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-fb-primary admin-update-btn">Assign</button>
                                        </form>
                                        <?php if (!empty($report['assigned_username'])): ?>
                                            <small class="text-muted d-block mt-1">Assigned: <?php echo htmlspecialchars($report['assigned_username']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2 align-items-center">
                                            <select class="dashboard-form-select admin-status-select" style="width: 140px;" id="statusSelect-<?php echo (int)$report['id']; ?>">
                                                <option value="Pending" <?php echo $report['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="Ongoing" <?php echo $report['status'] == 'Ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                                <option value="Resolved" <?php echo $report['status'] == 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                            </select>
                                            <button type="button" class="btn btn-fb-primary admin-update-btn"
                                                onclick="openStatusModal(<?php echo (int)$report['id']; ?>, this)">
                                                Update
                                            </button>
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

<form id="bulkDeleteForm" action="delete_reports.php" method="POST" style="display: none;"></form>

<!-- Admin Status Update Modal -->
<div id="statusUpdateModal" class="modal">
    <div class="modal-content" style="max-width: 520px; margin: 10% auto; border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
        <div class="modal-header" style="background: #1f74d8; color: white; border-top-left-radius: 12px; border-top-right-radius: 12px;">
            <h5 class="fw-bold mb-0"><i class="bi bi-pencil-square me-2"></i>Update Task Status</h5>
            <button type="button" class="btn-close btn-close-white" onclick="closeStatusUpdateModal()">&times;</button>
        </div>
        <div class="modal-body p-4">
            <form id="statusUpdateForm" action="update_status.php" method="POST">
                <input type="hidden" name="id" id="statusUpdateReportId" value="">

                <div class="mb-3">
                    <label class="form-label small fw-bold">Status</label>
                    <select name="status" id="statusUpdateStatusSelect" class="dashboard-form-select admin-status-select" required>
                        <option value="Pending">Pending</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Resolved">Resolved</option>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-bold">Action taken / notes</label>
                    <textarea name="action_taken" id="statusUpdateActionTaken" class="form-control dashboard-form-control" rows="4" placeholder="Small note that will reflect on the admin dashboard timeline" required></textarea>
                </div>

                <button type="submit" class="btn btn-fb-primary w-100 mt-3">
                    <i class="bi bi-check2-circle me-2"></i>Save Update
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Complaint Details Modal -->
<div id="complaintModal" class="modal">
    <div class="modal-content complaint-modal-content">
        <div class="modal-header">
            <h5 id="complaintModalTitle" class="fw-bold">Complaint Details</h5>
            <button type="button" class="btn-close" onclick="closeComplaintModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="complaint-meta-grid">
                <div><span class="meta-label">Category</span><strong id="complaintCategory"></strong></div>
                <div><span class="meta-label">Submitted By</span><strong id="complaintResident"></strong></div>
                <div><span class="meta-label">Status</span><strong id="complaintStatus"></strong></div>
                <div><span class="meta-label">Date Reported</span><strong id="complaintDate"></strong></div>
            </div>
            <div id="complaintAiInsight" class="mt-4 p-3 rounded" style="display: none; background: #f0f7ff; border: 1px solid #c4ddf3;">
                <div class="small fw-bold text-uppercase text-muted mb-2" style="font-size: 0.65rem; letter-spacing: 0.5px;">AI Analysis & Summary</div>
                <div id="complaintAiBadges" class="d-flex gap-2"></div>
                <div id="complaintAiSummaryText" class="mt-2 small italic text-primary" style="font-style: italic; line-height: 1.4;"></div>
            </div>
            <div class="complaint-full-text" id="complaintDescription"></div>
            
            <div class="mt-4 pt-3 border-top">
                <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2"></i>Resident Activity Timeline</h6>
                <div id="complaintTimeline" class="resident-timeline"></div>
            </div>
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
            <div id="summaryModalContent" class="small" style="line-height: 1.6; color: #2c3e50;">
            </div>
        </div>
    </div>
</div>

<!-- Map View Modal -->
<div id="mapModal" class="modal">
    <div class="modal-content admin-map-modal-content">
        <div class="modal-header">
            <h5 id="mapModalTitle" class="fw-bold">Location View</h5>
            <button type="button" class="btn-close" onclick="closeMapModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="adminMap" style="height: 400px; width: 100%; border-radius: 8px; position: relative;"></div>
        </div>
    </div>
</div>

<script src="https://cdn.maptiler.com/maptiler-sdk-js/v2.0.3/maptiler-sdk.umd.js"></script>
<script>
let adminMap = null;
let adminMarker = null;
maptilersdk.config.apiKey = '2KCcsKR95Ru4UnqIGZjd';

// ────────────────────────────────────────────────
// TABLE FILTER & BULK SELECT
// ────────────────────────────────────────────────
function filterTable() {
    const filter = document.getElementById('statusFilter').value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr[data-status]');
    rows.forEach(row => {
        const status = row.getAttribute('data-status');
        row.style.display = (filter === '' || status === filter) ? '' : 'none';
    });
    syncSelectAllState();
    updateSelectedCount();
}

if (document.getElementById('statusFilter')) {
    document.getElementById('statusFilter').addEventListener('change', filterTable);
}

if (document.getElementById('selectAllReports')) {
    document.getElementById('selectAllReports').addEventListener('change', function() {
        const rows = document.querySelectorAll('tbody tr[data-status]');
        rows.forEach(row => {
            if (row.style.display === 'none') return;
            const checkbox = row.querySelector('.report-checkbox');
            if (checkbox) checkbox.checked = this.checked;
        });
        updateSelectedCount();
    });
}

function viewOnMap(lat, lng, title) {
    document.getElementById('mapModal').classList.add('show');
    document.getElementById('mapModalTitle').innerText = "Location: " + title;
    setTimeout(() => {
        if (!adminMap) {
            adminMap = new maptilersdk.Map({
                container: 'adminMap',
                style: maptilersdk.MapStyle.STREETS,
                center: [lng, lat],
                zoom: 15
            });
            adminMarker = new maptilersdk.Marker({ color: "#1877f2" })
                .setLngLat([lng, lat])
                .addTo(adminMap);
        } else {
            adminMap.setCenter([lng, lat]);
            adminMarker.setLngLat([lng, lat]);
        }
    }, 100);
}

function closeMapModal() { document.getElementById('mapModal').classList.remove('show'); }

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

function openComplaintModal(title, description, category, resident, dateReported, status, aiEmotion, aiPolarity, aiSummary, aiSeverity, actionsJson) {
    document.getElementById('complaintModalTitle').innerText = title || 'Complaint Details';
    document.getElementById('complaintCategory').innerText = category || '-';
    document.getElementById('complaintResident').innerText = resident || 'Anonymous Guest';
    document.getElementById('complaintStatus').innerText = status || '-';
    document.getElementById('complaintDate').innerText = dateReported || '-';
    document.getElementById('complaintDescription').innerText = description || 'No description provided.';
    
    const aiBox = document.getElementById('complaintAiInsight');
    const aiBadges = document.getElementById('complaintAiBadges');
    const aiSummaryText = document.getElementById('complaintAiSummaryText');
    const timeline = document.getElementById('complaintTimeline');

    // Render Timeline
    const actions = JSON.parse(actionsJson || '[]');
    timeline.innerHTML = actions.length > 0 ? '' : '<p class="text-muted small">No updates from resident recorded yet.</p>';
    actions.forEach(act => {
        timeline.innerHTML += `
            <div class="timeline-item">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold small">${act.username}</span>
                    <span class="timeline-date">${act.created_at}</span>
                </div>
                <div class="timeline-note">${act.note}</div>
            </div>`;
    });

    if (aiEmotion && aiPolarity) {
        const polColor = aiPolarity === 'Positive' ? '#2ecc71' : aiPolarity === 'Negative' ? '#e74c3c' : '#95a5a6';
        const sevColor = aiSeverity === 'Critical' ? '#c0392b' : aiSeverity === 'High' ? '#e67e22' : aiSeverity === 'Medium' ? '#f1c40f' : '#2ecc71';
        
        aiBox.style.display = 'block';
        aiBadges.innerHTML = `
            <span class="mini-badge" style="background:${sevColor}; padding: 4px 12px; font-size: 0.8rem;">Severity: ${aiSeverity}</span>
            <span class="mini-badge" style="background:${emoColor(aiEmotion)}; padding: 2px 10px; font-size: 0.75rem;">${aiEmotion}</span>
            <span class="mini-badge" style="background:${polColor}; padding: 2px 10px; font-size: 0.75rem;">${aiPolarity}</span>
        `;
        aiSummaryText.innerHTML = aiSummary ? aiSummary.split('\n').map(line => `<div class="mb-1">${line}</div>`).join('') : '';
    } else {
        aiBox.style.display = 'none';
    }

    document.getElementById('complaintModal').classList.add('show');
}

function openComplaintModalFromElement(element) {
    openComplaintModal(
        element.getAttribute('data-title'),
        element.getAttribute('data-description'),
        element.getAttribute('data-category'),
        element.getAttribute('data-resident'),
        element.getAttribute('data-date'),
        element.getAttribute('data-status'),
        element.getAttribute('data-ai-emotion'),
        element.getAttribute('data-ai-polarity'),
        element.getAttribute('data-ai-summary'),
        element.getAttribute('data-ai-severity'),
        element.getAttribute('data-actions')
    );
}

function closeComplaintModal() { document.getElementById('complaintModal').classList.remove('show'); }

function openStatusModal(reportId, triggerEl) {
    const modal = document.getElementById('statusUpdateModal');
    const idInput = document.getElementById('statusUpdateReportId');
    const statusSelect = document.getElementById('statusUpdateStatusSelect');

    idInput.value = reportId;

    // Read the selected status from the row select
    const rowStatusSelect = document.getElementById('statusSelect-' + reportId);
    if (rowStatusSelect && rowStatusSelect.value) {
        statusSelect.value = rowStatusSelect.value;
    }

    // reset textarea
    const actionTaken = document.getElementById('statusUpdateActionTaken');
    if (actionTaken) actionTaken.value = '';

    modal.classList.add('show');
}

function closeStatusUpdateModal() {
    document.getElementById('statusUpdateModal').classList.remove('show');
}


function getVisibleCheckedBoxes() {
    const rows = document.querySelectorAll('tbody tr[data-status]');
    const checked = [];
    rows.forEach(row => {
        if (row.style.display === 'none') return;
        const checkbox = row.querySelector('.report-checkbox');
        if (checkbox && checkbox.checked) checked.push(checkbox);
    });
    return checked;
}

function updateSelectedCount() {
    const checked = getVisibleCheckedBoxes();
    document.getElementById('selectedCount').innerText = checked.length + ' selected';
    syncSelectAllState();
}

function syncSelectAllState() {
    const selectAll = document.getElementById('selectAllReports');
    if (!selectAll) return;
    const visibleRows = Array.from(document.querySelectorAll('tbody tr[data-status]'))
        .filter(row => row.style.display !== 'none');
    if (visibleRows.length === 0) { selectAll.checked = false; selectAll.indeterminate = false; return; }
    const checkedCount = visibleRows.filter(row => {
        const cb = row.querySelector('.report-checkbox');
        return cb && cb.checked;
    }).length;
    selectAll.checked = checkedCount === visibleRows.length;
    selectAll.indeterminate = checkedCount > 0 && checkedCount < visibleRows.length;
}

function submitSelectedDeletes() {
    const checked = getVisibleCheckedBoxes();
    if (checked.length === 0) { alert('Please select at least one complaint to delete.'); return; }
    if (!confirm('Delete ' + checked.length + ' selected complaint(s)? This action cannot be undone.')) return;
    const form = document.getElementById('bulkDeleteForm');
    form.innerHTML = '';
    checked.forEach(checkbox => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'report_ids[]';
        input.value = checkbox.value;
        form.appendChild(input);
    });
    form.submit();
}

updateSelectedCount();

window.onclick = function(event) {
    const modal = document.getElementById('mapModal');
    const complaintModal = document.getElementById('complaintModal');
    const summaryModal = document.getElementById('summaryOnlyModal');
    const statusModal = document.getElementById('statusUpdateModal');
    if (event.target == modal) closeMapModal();
    if (event.target == complaintModal) closeComplaintModal();
    if (event.target == summaryModal) closeSummaryOnlyModal();
    if (event.target == statusModal) closeStatusUpdateModal();
};


// ────────────────────────────────────────────────
// AI INSIGHTS
// ────────────────────────────────────────────────
let insightsOpen = false;
let insightsLoaded = false;

function toggleInsights() {
    const body = document.getElementById('insightsBody');
    const chevron = document.getElementById('insightsChevron');
    const btn = document.getElementById('insightsToggleBtn');

    insightsOpen = !insightsOpen;
    body.classList.toggle('open', insightsOpen);
    chevron.className = insightsOpen ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
    btn.innerHTML = insightsOpen
        ? '<i class="bi bi-chevron-up"></i> Hide Insights'
        : '<i class="bi bi-chevron-down"></i> Analyze Reports';

    if (insightsOpen && !insightsLoaded) {
        loadInsights();
    }
}

function loadInsights() {
    const body = document.getElementById('insightsBody');
    body.innerHTML = `
        <div class="insights-loading">
            <div class="insights-spinner"></div>
            <p><strong>Analyzing community reports…</strong></p>
            <p style="font-size:0.8rem;color:#a0b4c8;">Running TextBlob polarity · RoBERTa emotion classification · Thematic NLP</p>
        </div>`;

    // Provide feedback on the table buttons
    document.querySelectorAll('.ai-analyze-btn').forEach(btn => {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width: 8px; height: 8px;"></span>';
    });

    fetch('insights.php')
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                body.innerHTML = `<div class="insights-error"><i class="bi bi-exclamation-triangle-fill" style="font-size:1.3rem;flex-shrink:0;"></i><span><strong>Analysis failed:</strong> ${data.error}</span></div>`;
                // Reset buttons on error
                document.querySelectorAll('.ai-analyze-btn').forEach(btn => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-cpu me-1"></i>Analyze';
                });
                return;
            }
            renderInsights(body, data);
            insightsLoaded = true;
        })
        .catch(err => {
            body.innerHTML = `<div class="insights-error"><i class="bi bi-exclamation-triangle-fill" style="font-size:1.3rem;flex-shrink:0;"></i><span><strong>Network error:</strong> ${err.message}</span></div>`;
            // Reset buttons on error
                document.querySelectorAll('.ai-analyze-btn').forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-cpu me-1"></i>Analyze';
            });
        });
}

function emoColor(label) {
    const map = {
        Frustration: '#e74c3c', Urgency: '#e67e22', Distress: '#c0392b',
        Neutral: '#95a5a6', Concern: '#3498db', Satisfaction: '#2ecc71',
        Gratitude: '#27ae60', Positive: '#2ecc71', Negative: '#e74c3c'
    };
    return map[label] || '#1f74d8';
}

function renderInsights(container, data) {
    const tb = data.textblob || {};
    const rb = data.roberta || {};
    const ins = data.insights || {};

    const polarity = parseFloat(tb.corpus_polarity || 0);
    const subjectivity = parseFloat(tb.corpus_subjectivity || 0);
    const polarityDist = tb.polarity_distribution || {};
    const emotionDist = rb.emotion_distribution || {};
    const perReport = rb.per_report || [];

    // Polarity needle position (polarity: -1 to 1 → 0% to 100%)
    const needlePct = ((polarity + 1) / 2 * 100).toFixed(1);

    // Max values for bar scaling
    const maxEmo = Math.max(1, ...Object.values(emotionDist));
    const totalPol = (polarityDist.Positive || 0) + (polarityDist.Neutral || 0) + (polarityDist.Negative || 0);
    const maxPol = Math.max(1, totalPol);

    // Polarity color
    const polarityColor = polarity > 0.1 ? '#2ecc71' : polarity < -0.1 ? '#e74c3c' : '#e67e22';
    const polarityLabel = polarity > 0.1 ? 'Positive' : polarity < -0.1 ? 'Negative' : 'Neutral';

    // Build polarity dist bars
    function distBar(label, count, max, colorClass) {
        const pct = max > 0 ? (count / max * 100).toFixed(1) : 0;
        return `<div class="dist-bar-row">
            <div class="dist-bar-label">${label}</div>
            <div class="dist-bar-track"><div class="dist-bar-fill ${colorClass}" style="width:${pct}%"></div></div>
            <div class="dist-bar-count">${count}</div>
        </div>`;
    }

    // Build per-report table rows
    let perReportRows = perReport.slice(0, 20).map(r => {
        const polBg = r.polarity === 'Positive' ? '#2ecc71' : r.polarity === 'Negative' ? '#e74c3c' : '#95a5a6';
        const emoBg = emoColor(r.emotion);
        return `<tr>
            <td style="color:#6b87a3;font-size:0.78rem;">#${r.id}</td>
            <td><span class="mini-badge" style="background:${emoBg}">${r.emotion}</span></td>
            <td><span class="mini-badge" style="background:${polBg}">${r.polarity}</span></td>
        </tr>`;
    }).join('');

    // Risk flag
    const riskFlag = ins.risk_flag || {};
    const riskHtml = riskFlag.active
        ? `<div class="risk-flag-active">
            <div class="risk-flag-icon" style="color:#e74c3c;"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="risk-flag-text"><strong>⚠️ Risk Flag Active</strong>${riskFlag.reason || 'Critical language detected in one or more reports.'}</div>
          </div>`
        : `<div class="risk-flag-clear">
            <div class="risk-flag-icon" style="color:#2ecc71;"><i class="bi bi-shield-check-fill"></i></div>
            <div class="risk-flag-text"><strong>No Critical Flags</strong>No urgent or critical safety language detected across reports.</div>
          </div>`;

    // Themes
    const themes = (ins.top_themes || []).map(t => `<span class="theme-tag"><i class="bi bi-tag-fill me-1"></i>${t}</span>`).join('');

    // Recommendations
    const recs = (ins.recommendations || []).map((r, i) =>
        `<div class="rec-item"><div class="rec-num">${i+1}</div><div>${r}</div></div>`
    ).join('');

    // Emotion dist bars
    const emoBarColors = {
        Frustration: 'emo-Frustration', Urgency: 'emo-Urgency', Distress: 'emo-Distress',
        Neutral: 'emo-Neutral', Concern: 'emo-Concern', Satisfaction: 'emo-Satisfaction', Gratitude: 'emo-Gratitude'
    };
    const emoBars = Object.entries(emotionDist)
        .sort((a, b) => b[1] - a[1])
        .map(([label, count]) => distBar(label, count, maxEmo, emoBarColors[label] || 'emo-default'))
        .join('');

    // Update individual table rows with AI insights
    if (perReport && perReport.length > 0) {
        perReport.forEach(r => {
            const cell = document.querySelector(`.ai-insight-cell[data-report-id="${r.id}"]`);
            const viewBtn = document.querySelector(`tr[data-id="${r.id}"] .complaint-link`);
            if (cell) {
                const polBg = r.polarity === 'Positive' ? '#2ecc71' : r.polarity === 'Negative' ? '#e74c3c' : '#95a5a6';
                const emoBg = emoColor(r.emotion);
                const firstLine = r.summary ? r.summary.split('\n')[0] : '';
                cell.innerHTML = `
                    <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                        <div class="d-flex gap-1">
                            <span class="mini-badge" style="background:${emoBg};">${r.emotion}</span>
                            <span class="mini-badge" style="background:${polBg};">${r.polarity}</span>
                        </div>
                        <div class="small text-muted mt-1" style="font-size: 0.65rem; line-height: 1.2; font-style: italic; max-width: 130px;">
                            ${firstLine}
                        </div>
                    </div>
                `;
            }

            // Pass AI data to the "View Complaint" button for the modal
            if (viewBtn) {
                viewBtn.setAttribute('data-ai-emotion', r.emotion);
                viewBtn.setAttribute('data-ai-polarity', r.polarity);
                viewBtn.setAttribute('data-ai-summary', r.summary || '');
                viewBtn.setAttribute('data-ai-severity', r.severity || 'Low');
            }

            // Update the new English Summary button
            const summaryBtn = document.querySelector(`tr[data-id="${r.id}"] .admin-summary-btn`);
            if (summaryBtn) {
                summaryBtn.disabled = false;
                summaryBtn.setAttribute('data-summary', r.summary || '');
            }
        });
    }

    container.innerHTML = `
        <div class="insights-grid">

            <!-- TextBlob Polarity Score -->
            <div class="insight-card">
                <div class="insight-section-title"><i class="bi bi-graph-up-arrow"></i> TextBlob · Corpus Polarity</div>
                <div class="polarity-score" style="color:${polarityColor}">${polarity.toFixed(3)} <span>/ 1.0</span></div>
                <div style="font-size:0.78rem;color:#8fa9c4;margin-top:2px;margin-bottom:8px;">Overall tone: <strong style="color:${polarityColor}">${polarityLabel}</strong></div>
                <div class="polarity-meter">
                    <div class="polarity-needle" id="polarityNeedle" style="left:${needlePct}%"></div>
                </div>
                <div class="polarity-labels"><span>Negative</span><span>Neutral</span><span>Positive</span></div>
                <hr class="insight-divider">
                <div style="font-size:0.78rem;color:#8fa9c4;margin-bottom:6px;">Subjectivity: <strong style="color:#103a5f;">${(subjectivity*100).toFixed(0)}%</strong> subjective</div>
                <div style="background:#e4edf6;border-radius:999px;height:7px;overflow:hidden;">
                    <div style="height:100%;background:#1f74d8;border-radius:999px;width:${(subjectivity*100).toFixed(0)}%;transition:width 1.2s ease;"></div>
                </div>
            </div>

            <!-- TextBlob Polarity Distribution -->
            <div class="insight-card">
                <div class="insight-section-title"><i class="bi bi-bar-chart-fill"></i> TextBlob · Polarity Distribution</div>
                <div class="dist-bar-list">
                    ${distBar('Positive', polarityDist.Positive||0, maxPol, 'emo-Positive')}
                    ${distBar('Neutral',  polarityDist.Neutral||0,  maxPol, 'emo-Neutral')}
                    ${distBar('Negative', polarityDist.Negative||0, maxPol, 'emo-Negative')}
                </div>
                <hr class="insight-divider">
                <div class="insight-section-title" style="margin-bottom:8px;"><i class="bi bi-pie-chart-fill"></i> Subjectivity Split</div>
                <div class="dist-bar-list">
                    ${distBar('Objective',   tb.subjectivity_distribution?.Objective||0,  Math.max(1,(tb.subjectivity_distribution?.Objective||0)+(tb.subjectivity_distribution?.Subjective||0)), 'emo-Concern')}
                    ${distBar('Subjective',  tb.subjectivity_distribution?.Subjective||0, Math.max(1,(tb.subjectivity_distribution?.Objective||0)+(tb.subjectivity_distribution?.Subjective||0)), 'emo-Urgency')}
                </div>
            </div>

            <!-- RoBERTa Dominant Emotion -->
            <div class="insight-card">
                <div class="insight-section-title"><i class="bi bi-emoji-expressionless"></i> RoBERTa · Dominant Emotion</div>
                <div style="font-size:0.85rem;color:#6b87a3;margin-bottom:6px;">Most prevalent emotion across all reports:</div>
                <div class="dominant-emotion-badge" style="background:${emoColor(rb.dominant_emotion)};">
                    <i class="bi bi-activity"></i> ${rb.dominant_emotion || 'Unknown'}
                </div>
                <hr class="insight-divider">
                <div class="insight-section-title" style="margin-top:0;"><i class="bi bi-shield-exclamation"></i> Risk Assessment</div>
                ${riskHtml}
            </div>

            <!-- RoBERTa Emotion Distribution -->
            <div class="insight-card">
                <div class="insight-section-title"><i class="bi bi-bar-chart-steps"></i> RoBERTa · Emotion Distribution</div>
                <div class="dist-bar-list">${emoBars}</div>
            </div>

            <!-- Per-Report Breakdown -->
            <div class="insight-card">
                <div class="insight-section-title"><i class="bi bi-list-columns-reverse"></i> Per-Report Classification</div>
                <div class="per-report-scroll">
                    <table class="per-report-table">
                        <thead>
                            <tr>
                                <th>Report</th>
                                <th>Emotion</th>
                                <th>Polarity</th>
                            </tr>
                        </thead>
                        <tbody>${perReportRows || '<tr><td colspan="3" style="color:#8fa9c4;text-align:center;padding:12px;">No per-report data</td></tr>'}</tbody>
                    </table>
                </div>
            </div>

            <!-- Insights Summary -->
            <div class="insight-card">
                <div class="insight-section-title"><i class="bi bi-lightbulb-fill"></i> Community Sentiment Summary</div>
                <div class="sentiment-summary-box">${ins.sentiment_summary || 'No summary available.'}</div>
                <hr class="insight-divider">
                <div class="insight-section-title"><i class="bi bi-tags-fill"></i> Top Reported Themes</div>
                <div class="theme-tags">${themes || '<span style="color:#8fa9c4;font-size:0.82rem;">No themes identified.</span>'}</div>
            </div>

            <!-- Recommendations -->
            <div class="insight-card full-width">
                <div class="insight-section-title"><i class="bi bi-clipboard2-check-fill"></i> Admin Recommendations</div>
                <div class="rec-list">${recs || '<div style="color:#8fa9c4;font-size:0.85rem;">No recommendations available.</div>'}</div>
            </div>

        </div>
        <div style="text-align:right;font-size:0.75rem;color:#b0bec5;margin-top:-8px;padding-bottom:4px;">
            <i class="bi bi-info-circle me-1"></i>Analysis powered by Claude AI · TextBlob-style polarity · RoBERTa-style emotion classification
        </div>`;

    // Animate needle after render
    setTimeout(() => {
        const needle = document.getElementById('polarityNeedle');
        if (needle) needle.style.left = needlePct + '%';
    }, 100);
}
</script>

</body>
</html>
