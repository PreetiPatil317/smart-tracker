<?php
// Public request tracking page (no auth)
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'smart_tracker';

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo "Database error.";
    exit;
}

// Get token from query
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if ($token === '') {
    // show a simple entry form when token not provided
    ?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Track Request - Smart Service Tracker</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: linear-gradient(135deg,#6a11cb,#2575fc); font-family:Poppins, sans-serif; min-height:100vh; color:#fff; display:flex; align-items:center; justify-content:center; }
    .glass { background: rgba(255,255,255,0.08); padding:28px; border-radius:16px; width: 400px; box-shadow:0 8px 32px rgba(0,0,0,0.25); }
    input.form-control { background: rgba(255,255,255,0.12); border:none; color:#fff; }
    a.text-warning { color:#ffd166; }
  </style>
</head>
<body>
  <div class="glass text-center">
    <h4 class="mb-3">Track your request</h4>
    <p class="text-white-50">Enter the token you received (or scan QR).</p>
    <form method="GET" action="track.php">
      <input name="token" class="form-control mb-3" placeholder="Enter token (e.g., A7B9K2X1)" required>
      <button class="btn btn-light w-100">Track</button>
    </form>
    <p class="small text-white-50 mt-3">If you don't have a token, contact the issuing office.</p>
  </div>
</body>
</html>

    <?php
    exit;
}

// Lookup request and join users
$stmt = $mysqli->prepare("
  SELECT r.*, u.name AS citizen_name, u.email AS citizen_email, o.name AS officer_name, o.email AS officer_email
  FROM requests r
  LEFT JOIN users u ON r.user_id = u.id
  LEFT JOIN users o ON r.officer_id = o.id
  WHERE r.token = ? LIMIT 1
");
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    // Not found
    ?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Track Request - Not found</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style> body { background: linear-gradient(135deg,#6a11cb,#2575fc); font-family:Poppins, sans-serif; color:#fff; min-height:100vh; display:flex; align-items:center; justify-content:center; } .glass{background:rgba(255,255,255,0.08);padding:28px;border-radius:16px; width:420px; text-align:center;} </style>
</head>
<body>
  <div class="glass">
    <h4>Request not found</h4>
    <p class="text-white-50">We couldn't find a request with token <strong><?= htmlspecialchars($token) ?></strong>.</p>
    <a href="track.php" class="btn btn-light">Try another token</a>
  </div>
</body>
</html>

    <?php
    exit;
}

$r = $res->fetch_assoc();
$stmt->close();

// Build a simple event timeline (we have only current status + created_at + optional remark)
$events = [];

// Event: Created
$events[] = [
    'time' => $r['created_at'],
    'title' => 'Request submitted',
    'desc' => 'Request created by ' . $r['citizen_name'] . ' (' . $r['citizen_email'] . ')'
];

// If status progressed to In Progress or beyond, show In Progress event when status != Received
if ($r['status'] !== 'Received') {
    $events[] = [
        'time' => $r['created_at'], // we don't have real status timestamps; use created_at as approximate
        'title' => 'Processing started',
        'desc' => 'Officer assigned: ' . ($r['officer_name'] ? $r['officer_name'] . ' (' . $r['officer_email'] . ')' : 'Not yet assigned')
    ];
}

// If remarks exist, add as event
if (!empty($r['remarks'])) {
    $events[] = [
        'time' => $r['created_at'],
        'title' => 'Officer remark',
        'desc' => $r['remarks']
    ];
}

// Final status events (Approved / Completed / Rejected)
if (in_array($r['status'], ['Approved','Completed','Rejected'])) {
    $events[] = [
        'time' => $r['created_at'],
        'title' => 'Status: ' . $r['status'],
        'desc' => 'Final status set by ' . ($r['officer_name'] ? $r['officer_name'] : 'Officer')
    ];
}

// For display ordering, keep as is (created first)
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Track: <?= htmlspecialchars($r['request_type']) ?> — <?= htmlspecialchars($r['token']) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- qrcodejs for client-side QR generation -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <style>
    body { background: linear-gradient(135deg,#6a11cb,#2575fc); font-family:Poppins, sans-serif; min-height:100vh; color:#fff; padding:30px; }
    .wrap { max-width:980px; margin: 0 auto; }
    .glass { background: rgba(255,255,255,0.08); backdrop-filter: blur(8px); border-radius:14px; padding:22px; box-shadow:0 8px 32px rgba(0,0,0,0.25); color:#fff; }
    .timeline { margin-top:18px; }
    .timeline-item { padding:12px; border-left:3px solid rgba(255,255,255,0.08); margin-bottom:12px; }
    .small-muted { color: #e6e6e6; opacity:0.8; }
    .meta { font-size:13px; color:#dfe7ff; opacity:0.85; }
    a.text-info { color:#bde0fe; }
    .qr-box { width:120px; height:120px; background:rgba(255,255,255,0.03); display:flex; align-items:center; justify-content:center; border-radius:10px; }
    .badge-status { padding:6px 10px; border-radius:12px; font-weight:600; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="glass d-flex justify-content-between align-items-start gap-3">
      <div style="flex:1">
        <h4 class="mb-1"><?= htmlspecialchars($r['request_type']) ?></h4>
        <div class="meta">Token: <strong><?= htmlspecialchars($r['token']) ?></strong> &nbsp; | &nbsp; Submitted: <?= htmlspecialchars($r['created_at']) ?></div>
        <p class="small-muted mt-2 mb-0"><?= nl2br(htmlspecialchars($r['remarks'] ?? '')) ?></p>
        <div class="mt-3">
          <strong>Citizen:</strong> <?= htmlspecialchars($r['citizen_name']) ?> (<?= htmlspecialchars($r['citizen_email']) ?>) <br>
          <strong>Officer:</strong> <?= $r['officer_name'] ? htmlspecialchars($r['officer_name']) . ' (' . htmlspecialchars($r['officer_email']) . ')' : '<em class="text-white-50">Not yet assigned</em>' ?>
        </div>
        <div class="mt-2">
    <a href="dashboard.php" class="btn btn-sm btn-outline-light">Back to Dashboard</a>
</div>

      </div>

      <div style="width:150px; text-align:center;">
        <div id="qrcode" class="qr-box mx-auto mb-2"></div>
        <div class="small-muted mb-2">Scan to open</div>

        <!-- status badge -->
        <?php
          $status = $r['status'];
          $cls = 'bg-secondary';
          if ($status === 'In Progress') $cls = 'bg-warning text-dark';
          elseif ($status === 'Approved') $cls = 'bg-info text-dark';
          elseif ($status === 'Completed') $cls = 'bg-success';
          elseif ($status === 'Rejected') $cls = 'bg-danger';
        ?>
        <div class="badge-status <?= $cls ?>" style="display:inline-block;"><?= htmlspecialchars($status) ?></div>
      </div>
    </div>

    <div class="glass timeline mt-4">
      <h6 class="mb-3">Timeline</h6>

      <?php foreach ($events as $ev): ?>
        <div class="timeline-item">
          <div class="d-flex justify-content-between">
            <div>
              <strong><?= htmlspecialchars($ev['title']) ?></strong>
              <div class="meta"><?= htmlspecialchars($ev['time']) ?></div>
            </div>
          </div>
          <?php if (!empty($ev['desc'])): ?>
            <div class="mt-2 small text-white-50"><?= nl2br(htmlspecialchars($ev['desc'])) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <!-- If there are additional details -->
      <?php if (!empty($r['doc_path'])): ?>
        <div class="mt-3">
          <strong>Documents:</strong> <a href="<?= htmlspecialchars($r['doc_path']) ?>" class="text-info" target="_blank">View uploaded file</a>
        </div>
      <?php endif; ?>

      <div class="mt-3">
        <a href="track.php" class="btn btn-sm btn-light">Track another token</a>
      </div>
    </div>
  </div>

<script>
  // generate QR code for the full tracking URL
  (function(){
    var token = <?= json_encode($r['token']) ?>;
    var url = location.origin + location.pathname + '?token=' + encodeURIComponent(token);
    var q = new QRCode(document.getElementById("qrcode"), {
      text: url,
      width: 110,
      height: 110,
      colorDark : "#000000",
      colorLight : "#ffffff",
      correctLevel : QRCode.CorrectLevel.H
    });
  })();
</script>

</body>
</html>
