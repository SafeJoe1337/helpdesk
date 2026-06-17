<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Help Desk | SDG 11</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css"> 
    <link href="https://cdn.maptiler.com/maptiler-sdk-js/v2.0.3/maptiler-sdk.css" rel="stylesheet" />
</head>
<body class="landing-page">

<nav class="navbar landing-topbar">
    <div class="container">
        <a class="navbar-brand landing-brand" href="#"><i class="bi bi-megaphone me-2"></i>HELPDESK HUB</a>
        <div class="ms-auto">
            <button class="btn btn-fb-primary" onclick="toggleModal('quickReportModal')">Quick Report</button>
            <button class="btn btn-fb-primary" onclick="toggleModal('loginModal')">Log In</button>
            <button class="btn btn-fb-success ms-2" onclick="toggleModal('registerModal')">Sign Up</button>
        </div>
    </div>
</nav>

<div class="hero-section">
    <div class="container">
        <div class="row align-items-center hero-grid landing-hero-grid">
            <div class="col-lg-12">
                <h1 class="fw-bold mb-3">Community barangay HelpDesk Management System</h1>
                <p class="mb-4">Report concerns, monitor complaint progress, and improve your community support system with one platform.</p>
            </div>
        </div>
    </div>
</div>

<section class="info-section">
    <div class="container">
        <div class="landing-section-heading">
            <h2>System Features</h2>
            <p>Our platform offers practical tools for report tracking and community response.</p>
        </div>
        <div class="row info-cards-row">
            <div class="col-lg-4 col-md-6">
                <div class="card info-card">
                    <div class="info-icon"><i class="bi bi-clipboard-data"></i></div>
                    <h5>Structured Reporting</h5>
                    <p>Capture title, category, issue details, and map location for every report submitted.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card info-card">
                    <div class="info-icon"><i class="bi bi-geo-alt"></i></div>
                    <h5>Location-based Records</h5>
                    <p>Pin exact incident points to give administrators better context for response planning.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card info-card">
                    <div class="info-icon"><i class="bi bi-shield-check"></i></div>
                    <h5>Safe Community Channel</h5>
                    <p>Submit community concerns quickly while protecting resident identity when needed.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card info-card">
                    <div class="info-icon"><i class="bi bi-activity"></i></div>
                    <h5>Status Monitoring</h5>
                    <p>Track whether complaints are pending, ongoing, or resolved through the admin dashboard.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card info-card">
                    <div class="info-icon"><i class="bi bi-people"></i></div>
                    <h5>Resident Access</h5>
                    <p>Allow residents and admins to connect through a centralized complaint and service portal.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card info-card">
                    <div class="info-icon"><i class="bi bi-bell"></i></div>
                    <h5>Quick Submission</h5>
                    <p>Use Quick Report modal to submit urgent concerns immediately with minimal steps.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container my-5">
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger text-center"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success text-center"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
</div>

<!-- Quick Report Modal -->
<div class="modal" id="quickReportModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quick Report</h5>
                <button type="button" class="btn-close" onclick="toggleModal('quickReportModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form action="submit_report.php" method="POST">
                    <div class="mb-3">
                        <input type="text" name="title" class="form-control" placeholder="What's the issue?" required>
                    </div>
                    <div class="mb-3">
                        <select name="category" class="form-select" required>
                            <option value="" disabled selected>Select Category</option>
                            <option value="Complaint">Complaint</option>
                            <option value="Concern">Concern</option>
                            <option value="Service Request">Service Request</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <textarea name="description" class="form-control" rows="3" placeholder="Describe the details..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <div id="quickReportMap" style="height: 200px; width: 100%; border-radius: 8px; border: 1px solid #dddfe2; position: relative;"></div>
                        <input type="hidden" name="latitude" id="lat">
                        <input type="hidden" name="longitude" id="lng">
                    </div>
                    <button type="submit" class="btn btn-fb-primary w-100 fw-bold">Submit Anonymously</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Login Modal -->
<div class="modal" id="loginModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Welcome Back</h5>
                <button type="button" class="btn-close" onclick="toggleModal('loginModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form action="login.php" method="POST">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="loginPassword" class="form-control" style="margin-bottom: 0;" placeholder="••••••••" required>
                        <button class="btn btn-outline-secondary" type="button" id="toggleLoginPassword" style="border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <button type="submit" class="btn btn-fb-primary w-100 mt-3">Log In</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Register Modal -->
<div class="modal" id="registerModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Admin Registration</h5>
                <button type="button" class="btn-close" onclick="toggleModal('registerModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form action="register.php" method="POST">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="username" class="form-control" placeholder="Your Name" required>
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="resident@example.com" required>
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select dashboard-form-control mb-3" required>
                        <option value="resident">Resident</option>
                 
                    </select>
                    <label class="form-label">Password</label>
                    <div class="input-group">
                          <input type="password" name="password" id="registerPassword" class="form-control" style="margin-bottom: 0;" placeholder="Create a strong password" required>
                        <button class="btn btn-outline-secondary" type="button" id="toggleRegisterPassword" style="border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <button type="submit" class="btn btn-fb-success w-100 mt-3">Sign Up</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.maptiler.com/maptiler-sdk-js/v2.0.3/maptiler-sdk.umd.js"></script>
<script>
maptilersdk.config.apiKey = '2KCcsKR95Ru4UnqIGZjd';

let map = null;
let marker = null;

function initializeQuickReportMap() {
    if (map) {
        setTimeout(() => map.resize(), 50);
        return;
    }

    map = new maptilersdk.Map({
        container: 'quickReportMap',
        style: maptilersdk.MapStyle.STREETS,
        center: [120.9842, 14.5995],
        zoom: 11
    });

    map.on('styleimagemissing', (e) => {
        const emptyImage = { width: 1, height: 1, data: new Uint8Array(4) };
        map.addImage(e.id, emptyImage);
    });

    map.on('click', (e) => {
        if (marker) marker.remove();
        marker = new maptilersdk.Marker({ color: "#1877f2" })
            .setLngLat(e.lngLat)
            .addTo(map);
        
        document.getElementById("lat").value = e.lngLat.lat.toFixed(8);
        document.getElementById("lng").value = e.lngLat.lng.toFixed(8);
    });
}

function toggleModal(id) {
    const modal = document.getElementById(id);
    modal.classList.toggle('show');

    if (id === 'quickReportModal' && modal.classList.contains('show')) {
        initializeQuickReportMap();
    }
}

document.getElementById('toggleLoginPassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('loginPassword');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});

document.getElementById('toggleRegisterPassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('registerPassword');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});
</script>
</body>
</html>