<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    $_SESSION['error'] = 'Please login as resident to submit concern.';
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Concern | HelpDesk</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css"> 
    <link href="https://cdn.maptiler.com/maptiler-sdk-js/v2.0.3/maptiler-sdk.css" rel="stylesheet" />
</head>
<body>

<nav class="navbar">
    <div class="container">
        <a class="navbar-brand" href="user_dashboard.php">HelpDesk</a>
        <div class="ms-auto">
            <span class="me-3 text-muted">Resident: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
            <a href="index.php" class="btn btn-fb-primary">Dashboard</a>
            <a href="logout.php" class="btn btn-outline-primary ms-2">Logout</a>
        </div>
    </div>
</nav>

<div class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="fw-bold mb-3">Submit New Concern</h1>
                <p class="fs-4">Report community issues to barangay officials. Your concern will be reviewed promptly.</p>
            </div>
            <div class="col-lg-4 text-center">
                <div class="card-fb p-4">
                    <i class="bi bi-chat-square-text fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold mb-2">Status</h5>
                    <p class="text-muted small mb-0">Pending</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card p-5">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <form action="submit_report.php" method="POST">
                    <label class="form-label fw-bold mb-2">Title</label>
                    <input type="text" name="title" class="form-control" placeholder="Brief title of your concern (e.g., Street light broken)" required style="font-size: 16px;">
                    
                    <label class="form-label fw-bold mt-4 mb-2">Category</label>
                    <select name="category" class="form-select dashboard-form-control" required>
                        <option value="">Select category</option>
                        <option value="Complaint">Complaint</option>
                        <option value="Concern">Concern</option>
                        <option value="Service Request">Service Request</option>
                    </select>
                    
                    <label class="form-label fw-bold mt-4 mb-2">Description</label>
                    <textarea name="description" class="form-control dashboard-form-control" rows="4" placeholder="Provide detailed description, location, when noticed..." required style="font-size: 16px; resize: vertical;"></textarea>
                    
                    <label class="form-label fw-bold mt-4 mb-2">Pin Location (Optional)</label>
                    <div id="map" style="height: 300px; width: 100%; border-radius: 8px; margin-bottom: 15px; border: 1px solid #dddfe2; position: relative; background-color: #f8f9fa;"></div>
                    <input type="hidden" name="latitude" id="lat">
                    <input type="hidden" name="longitude" id="lng">
                    <small class="text-muted">Click on the map to mark the exact location of the issue.</small>

                    <div class="mt-4 d-flex gap-3">
                        <a href="user_dashboard.php" class="btn btn-outline-primary flex-fill">Back to Dashboard</a>
                        <button type="submit" class="btn btn-fb-success flex-fill fw-bold fs-5">Submit Concern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.maptiler.com/maptiler-sdk-js/v2.0.3/maptiler-sdk.umd.js"></script>
<script>
    maptilersdk.config.apiKey = '2KCcsKR95Ru4UnqIGZjd';
    
    const map = new maptilersdk.Map({
        container: 'map',
        style: maptilersdk.MapStyle.STREETS,
        center: [120.9842, 14.5995], // [lng, lat]
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
        marker = new maptilersdk.Marker({ color: "#42b72a" })
            .setLngLat(e.lngLat)
            .addTo(map);
        
        document.getElementById("lat").value = e.lngLat.lat.toFixed(8);
        document.getElementById("lng").value = e.lngLat.lng.toFixed(8);
    });
</script>

</body>
</html>
