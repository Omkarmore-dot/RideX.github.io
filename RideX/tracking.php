<?php
// tracking.php
require_once __DIR__ . '/config/db.php';
$pageTitle = "Real-Time Courier Tracking";

$db = getDB();
$trackingCode = isset($_GET['code']) ? trim($_GET['code']) : '';
$courier = null;
$milestones = [];
$error = '';

// The 6 official milestone steps required by specification
$allSteps = [
    'Booking Confirmed' => ['icon' => 'fa-clipboard-check', 'desc' => 'Order registered'],
    'Pickup Assigned'   => ['icon' => 'fa-user-check', 'desc' => 'Courier assigned'],
    'Picked Up'         => ['icon' => 'fa-boxes-packing', 'desc' => 'Parcel collected'],
    'In Transit'        => ['icon' => 'fa-truck-fast', 'desc' => 'On the way'],
    'Out for Delivery'  => ['icon' => 'fa-route', 'desc' => 'Arriving today'],
    'Delivered'         => ['icon' => 'fa-house-circle-check', 'desc' => 'Successfully signed']
];

if (!empty($trackingCode)) {
    $stmt = $db->prepare("SELECT * FROM courier_bookings WHERE tracking_code = ?");
    $stmt->bind_param("s", $trackingCode);
    $stmt->execute();
    $courier = $stmt->get_result()->fetch_assoc();

    if ($courier) {
        // Fetch tracking history events
        $stmtHistory = $db->prepare("SELECT * FROM courier_tracking WHERE courier_id = ? ORDER BY timestamp ASC, id ASC");
        $stmtHistory->bind_param("i", $courier['id']);
        $stmtHistory->execute();
        $milestones = $stmtHistory->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = "No shipment was found matching tracking code: " . htmlspecialchars($trackingCode);
    }
}

// Calculate stepper progress index
$currentStatus = $courier['current_status'] ?? '';
$stepKeys = array_keys($allSteps);
$currentStepIndex = array_search($currentStatus, $stepKeys);
if ($currentStepIndex === false) {
    $currentStepIndex = ($currentStatus === 'Cancelled') ? -1 : 0;
}

include __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 35px; padding-bottom: 70px;">
    <!-- Page Header & Search Bar -->
    <div style="max-width: 800px; margin: 0 auto 40px; text-align: center;">
        <span class="section-subtitle">Real-Time Logistics Telemetry</span>
        <h1 style="font-size: 32px; font-weight: 800; color: var(--dark); margin-bottom: 12px;">Track Your Shipment</h1>
        <p style="color: var(--text-muted); margin-bottom: 25px;">Enter your unique RideX Courier Tracking ID to view real-time location and delivery progress.</p>

        <form action="tracking.php" method="GET" style="display: flex; gap: 10px; max-width: 600px; margin: 0 auto;">
            <input type="text" name="code" class="form-control" style="padding: 14px 18px; font-size: 16px;" placeholder="Enter Tracking Code (e.g. RX-EXP-88901)" value="<?= htmlspecialchars($trackingCode) ?>" required>
            <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-magnifying-glass"></i> Track</button>
        </form>

        <div style="margin-top: 12px; font-size: 13px; color: var(--text-muted);">
            Sample tracking codes to test: 
            <a href="tracking.php?code=RX-EXP-88901" style="font-family: monospace; font-weight: 600;">RX-EXP-88901</a> &nbsp;|&nbsp; 
            <a href="tracking.php?code=RX-EXP-88902" style="font-family: monospace; font-weight: 600;">RX-EXP-88902</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error" style="max-width: 800px; margin: 0 auto 30px;"><i class="fa-solid fa-circle-exclamation"></i> <?= $error ?></div>
    <?php endif; ?>

    <?php if ($courier): ?>
        <div style="max-width: 960px; margin: 0 auto;">
            <!-- Top Status Overview Card -->
            <div class="card" style="margin-bottom: 30px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; border-bottom: 1px solid var(--border); padding-bottom: 20px;">
                    <div>
                        <span style="font-size: 13px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 1px;">Tracking Identifier</span>
                        <h2 style="font-size: 26px; font-weight: 800; color: var(--primary); font-family: monospace;"><?= htmlspecialchars($courier['tracking_code']) ?></h2>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 13px; color: var(--text-muted); display: block;">Current Status</span>
                        <span class="status-pill status-<?= strtolower(str_replace(' ', '-', $courier['current_status'])) ?>" style="font-size: 14px; padding: 6px 14px;">
                            <i class="fa-solid fa-circle-dot"></i> <?= htmlspecialchars($courier['current_status']) ?>
                        </span>
                    </div>
                </div>

                <!-- Visual 6-Step Milestone Stepper -->
                <div class="tracking-stepper">
                    <?php 
                    $pct = ($currentStepIndex >= 0) ? ($currentStepIndex / (count($allSteps) - 1)) * 100 : 0;
                    ?>
                    <div class="tracking-progress-bar" style="width: <?= $pct ?>%;"></div>

                    <?php 
                    $idx = 0;
                    foreach ($allSteps as $stepName => $stepInfo): 
                        $isCompleted = ($currentStepIndex > $idx);
                        $isActive = ($currentStepIndex === $idx);
                        $stateClass = $isCompleted ? 'completed' : ($isActive ? 'active' : '');
                        $idx++;
                    ?>
                        <div class="step-node <?= $stateClass ?>">
                            <div class="step-icon">
                                <i class="fa-solid <?= $isCompleted ? 'fa-check' : $stepInfo['icon'] ?>"></i>
                            </div>
                            <div class="step-label">
                                <div><?= htmlspecialchars($stepName) ?></div>
                                <small style="font-weight: 400; color: var(--text-light);"><?= $stepInfo['desc'] ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Shipment Key Information Summary -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; background: #f8fafc; padding: 18px; border-radius: var(--radius-md); border: 1px solid var(--border);">
                    <div>
                        <span style="font-size: 12px; color: var(--text-muted); display: block;">Sender & Origin</span>
                        <strong style="font-size: 14px; color: var(--dark);"><?= htmlspecialchars($courier['sender_name']) ?></strong>
                        <p style="font-size: 12px; color: var(--text-muted); margin: 0;"><?= htmlspecialchars($courier['pickup_address']) ?></p>
                    </div>
                    <div>
                        <span style="font-size: 12px; color: var(--text-muted); display: block;">Recipient & Destination</span>
                        <strong style="font-size: 14px; color: var(--dark);"><?= htmlspecialchars($courier['receiver_name']) ?></strong>
                        <p style="font-size: 12px; color: var(--text-muted); margin: 0;"><?= htmlspecialchars($courier['delivery_address']) ?></p>
                    </div>
                    <div>
                        <span style="font-size: 12px; color: var(--text-muted); display: block;">Parcel Details</span>
                        <strong style="font-size: 14px; color: var(--dark);"><?= htmlspecialchars($courier['parcel_type']) ?></strong>
                        <p style="font-size: 12px; color: var(--text-muted); margin: 0;"><?= number_format($courier['parcel_weight'], 2) ?> kg (<?= htmlspecialchars($courier['delivery_option']) ?>)</p>
                    </div>
                    <div>
                        <span style="font-size: 12px; color: var(--text-muted); display: block;">Booking Date</span>
                        <strong style="font-size: 14px; color: var(--dark);"><?= date('M d, Y', strtotime($courier['created_at'])) ?></strong>
                        <p style="font-size: 12px; color: var(--text-muted); margin: 0;"><?= date('h:i A', strtotime($courier['created_at'])) ?></p>
                    </div>
                </div>
            </div>

            <!-- Detailed Checkpoint Milestone Timeline -->
            <div class="card">
                <h3 class="card-title"><i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i> Shipment Activity History</h3>

                <?php if (empty($milestones)): ?>
                    <p style="color: var(--text-muted);">No activity has been logged yet for this parcel.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach (array_reverse($milestones) as $m): ?>
                            <div class="timeline-item">
                                <div class="timeline-point"></div>
                                <div class="timeline-content">
                                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                                        <h4><?= htmlspecialchars($m['status']) ?></h4>
                                        <span class="timeline-meta"><?= date('M d, Y - h:i A', strtotime($m['timestamp'])) ?></span>
                                    </div>
                                    <div style="font-size: 13px; color: var(--primary); font-weight: 600; margin-bottom: 4px;">
                                        <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($m['location']) ?>
                                    </div>
                                    <?php if (!empty($m['remarks'])): ?>
                                        <p style="font-size: 14px; color: var(--text-muted); margin: 0;"><?= htmlspecialchars($m['remarks']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
