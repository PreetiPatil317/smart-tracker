<?php
session_start();
// officer-only page
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'officer') {
    header('Location: login.php');
    exit;
}

$me_id = (int)$_SESSION['user_id'];
$me_name = htmlspecialchars($_SESSION['user_name']);

// DB connect
$db = new mysqli('127.0.0.1','root','','smart_tracker');
if ($db->connect_errno) {
    die("DB connection failed: " . $db->connect_error);
}

// optional message
$msg = '';
if (!empty($_GET['msg'])) {
    $msg = htmlspecialchars($_GET['msg']);
}

// URGENCY threshold (days)
$urgency_days = 2; // change this number to adjust "near deadline" window
$urgent_date = (new DateTime())->modify("+{$urgency_days} days")->format('Y-m-d');

function isUrgent($deadline, $urgent_date) {
    if (empty($deadline)) return false;
    // compare as strings YYYY-MM-DD works lexicographically for date
    return ($deadline <= $urgent_date);
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Officer Panel - Smart Service Tracker</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg,#6a11cb,#2575fc);
      font-family: Poppins, sans-serif;
      min-height:100vh;
      color:#fff;
    }
    .topbar { padding:18px; display:flex; justify-content:space-between; align-items:center; }
    .glass {
      background: rgba(255,255,255,0.08);
      backdrop-filter: blur(8px);
      border-radius:14px;
      padding:18px;
      box-shadow:0 8px 32px rgba(0,0,0,0.25);
      color:#fff;
      width:100%;
    }
    .req-card { background: rgba(255,255,255,0.12); border-radius:10px; padding:12px; margin-bottom:12px; transition: all .25s ease; }
    .form-control, input.form-control { background: rgba(255,255,255,0.12); border:none; color:#fff; }
    .small-muted { font-size:13px; opacity:0.9; }
    .btn-small { padding:6px 10px; font-size:13px; }
    .status-inline { min-width:110px; display:inline-block; text-align:center; }
    /* Urgent styling */
    .urgent {
      animation: urgentBlink 1s infinite;
      border: 1px solid rgba(255,0,0,0.25);
      background: linear-gradient(90deg, rgba(255,80,80,0.12), rgba(255,30,30,0.06));
      box-shadow: 0 6px 20px rgba(255,50,50,0.06);
    }
    .urgent .urgent-badge {
      background: rgba(255,0,0,0.9);
      color: #fff;
      font-weight:700;
      padding:4px 8px;
      border-radius:8px;
      font-size:12px;
      display:inline-block;
    }
    @keyframes urgentBlink {
      0% { transform: translateY(0); opacity: 1; }
      50% { transform: translateY(-2px); opacity: 0.6; }
      100% { transform: translateY(0); opacity: 1; }
    }
  </style>
</head>
<body>
  <div class="container py-4">
    <div class="topbar mb-4">
      <h3 class="m-0">Officer Panel</h3>
      <div>
        <span class="me-3">Hello, <strong><?= $me_name ?></strong> (officer)</span>
        <a href="logout.php" class="btn btn-light btn-sm">Logout</a>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="mb-3"><div class="alert alert-success py-2"><?= $msg ?></div></div>
    <?php endif; ?>

    <div class="row g-4">
      <!-- Unassigned requests -->
      <div class="col-12 col-lg-6">
        <div class="glass">
          <h5 class="mb-3">Unassigned Requests</h5>

          <?php
            // We want urgent items (deadline <= $urgent_date) first, then nearest deadline, then older ones, NULL deadlines last.
            // Using MySQL expression to put urgent rows first.
            $sql_unassigned = "
              SELECT r.id, r.request_type, r.token, r.deadline, r.doc_path, r.created_at, u.name AS citizen_name, u.email AS citizen_email
              FROM requests r
              JOIN users u ON r.user_id = u.id
              WHERE r.officer_id IS NULL
              ORDER BY (r.deadline IS NOT NULL AND r.deadline <= ?) DESC,
                       (r.deadline IS NULL) ASC,
                       r.deadline ASC,
                       r.created_at DESC
              LIMIT 200
            ";
            $stmt = $db->prepare($sql_unassigned);
            $stmt->bind_param('s', $urgent_date);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 0):
          ?>
            <div class="req-card">
              <p class="mb-0 text-white-50">No unassigned requests right now.</p>
            </div>
          <?php
            else:
              while ($r = $res->fetch_assoc()):
                $urgent = isUrgent($r['deadline'], $urgent_date);
          ?>
            <div class="req-card <?= $urgent ? 'urgent' : '' ?>">
              <div class="d-flex justify-content-between">
                <div>
                  <h6 class="mb-1"><?= htmlspecialchars($r['request_type']) ?>
                    <?php if ($urgent): ?><span class="ms-2 urgent-badge">URGENT</span><?php endif; ?>
                  </h6>
                  <small class="text-white-50">From: <?= htmlspecialchars($r['citizen_name']) ?> &lt;<?= htmlspecialchars($r['citizen_email']) ?>&gt;</small><br>
                  <small class="text-white-50">Token: <?= htmlspecialchars($r['token']) ?></small><br>
                  <?php if (!empty($r['deadline'])): ?><small class="text-white-50">Deadline: <?= htmlspecialchars($r['deadline']) ?></small><br><?php endif; ?>
                  <?php if (!empty($r['doc_path'])): ?><small><a href="<?= htmlspecialchars($r['doc_path']) ?>" target="_blank" class="text-info">View Document</a></small><br><?php endif; ?>
                </div>

                <div class="text-end">
                  <form method="POST" action="assign_request.php" style="display:inline-block;">
                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-light btn-small">Assign to me</button>
                  </form>
                  <div class="mt-2 small text-white-50"><?= htmlspecialchars($r['created_at']) ?></div>
                </div>
              </div>
            </div>
          <?php
              endwhile;
            endif;
            $stmt->close();
          ?>
        </div>
      </div>

      <!-- My assigned requests -->
      <div class="col-12 col-lg-6">
        <div class="glass">
          <h5 class="mb-3">My Assigned Requests</h5>

          <?php
            $sql_assigned = "
              SELECT r.id, r.request_type, r.token, r.status, r.deadline, r.doc_path, r.remarks, r.created_at, u.name AS citizen_name, u.email AS citizen_email
              FROM requests r
              JOIN users u ON r.user_id = u.id
              WHERE r.officer_id = ?
              ORDER BY (r.deadline IS NOT NULL AND r.deadline <= ?) DESC,
                       (r.deadline IS NULL) ASC,
                       r.deadline ASC,
                       r.created_at DESC
              LIMIT 500
            ";
            $stmt2 = $db->prepare($sql_assigned);
            $stmt2->bind_param('is', $me_id, $urgent_date);
            $stmt2->execute();
            $res2 = $stmt2->get_result();

            if ($res2->num_rows === 0):
          ?>
            <div class="req-card">
              <p class="mb-0 text-white-50">You have no assigned requests yet.</p>
            </div>
          <?php
            else:
              while ($r = $res2->fetch_assoc()):
                $urgent = isUrgent($r['deadline'], $urgent_date);
          ?>
            <div class="req-card <?= $urgent ? 'urgent' : '' ?>">
              <div class="d-flex justify-content-between align-items-start">
                <div style="max-width:65%;">
                  <h6 class="mb-1"><?= htmlspecialchars($r['request_type']) ?>
                    <?php if ($urgent): ?><span class="ms-2 urgent-badge">URGENT</span><?php endif; ?>
                  </h6>
                  <small class="text-white-50">Token: <?= htmlspecialchars($r['token']) ?></small><br>
                  <small class="text-white-50">From: <?= htmlspecialchars($r['citizen_name']) ?> (<?= htmlspecialchars($r['citizen_email']) ?>)</small><br>
                  <?php if (!empty($r['deadline'])): ?><small class="text-white-50">Deadline: <?= htmlspecialchars($r['deadline']) ?></small><br><?php endif; ?>
                  <?php if (!empty($r['doc_path'])): ?><small><a href="<?= htmlspecialchars($r['doc_path']) ?>" target="_blank" class="text-info">View Document</a></small><br><?php endif; ?>
                  <?php if (!empty($r['remarks'])): ?><small class="text-white-50">Latest remark: <?= nl2br(htmlspecialchars($r['remarks'])) ?></small><br><?php endif; ?>
                </div>

                <div style="min-width:170px; text-align:right;">
                  <?php
                    $status = $r['status'];
                    $badgeClass = 'bg-secondary';
                    if ($status === 'In Progress') $badgeClass = 'bg-warning text-dark';
                    elseif ($status === 'Approved') $badgeClass = 'bg-info text-dark';
                    elseif ($status === 'Completed') $badgeClass = 'bg-success';
                    elseif ($status === 'Rejected') $badgeClass = 'bg-danger';
                  ?>
                  <div class="status-inline">
                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                  </div>

                  <!-- update form -->
                  <form method="POST" action="update_request.php" class="mt-2 text-end">
                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                    <div class="mb-1">
                      <select name="status" class="form-select form-select-sm" required>
                        <option value="">Set status</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Approved">Approved</option>
                        <option value="Completed">Completed</option>
                        <option value="Rejected">Rejected</option>
                      </select>
                    </div>
                    <div class="mb-1">
                      <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Add a short remark (optional)">
                    </div>
                    <button type="submit" class="btn btn-sm btn-light btn-small">Update</button>
                  </form>

                  <div class="mt-2 small text-white-50"><?= htmlspecialchars($r['created_at']) ?></div>
                </div>
              </div>
            </div>
          <?php
              endwhile;
            endif;
            $stmt2->close();
            $db->close();
          ?>
        </div>
      </div>
    </div>
  </div>

  <!-- bootstrap js -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
