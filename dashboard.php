<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'citizen') {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Citizen Dashboard - Smart Service Tracker</title>
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
    .req-card {
      background: rgba(255,255,255,0.12);
      border-radius:10px;
      padding:12px;
      margin-bottom:12px;
    }
    input.form-control, .form-control {
      background: rgba(255,255,255,0.12);
      border:none;
      color:#fff;
    }
    .small-muted { font-size:13px; opacity:0.9; }
    @media (min-width: 992px) {
      .right-col { position: sticky; top: 20px; height: fit-content; }
    }
  </style>
</head>
<body>
  <div class="container py-4">
    <div class="topbar mb-4">
      <h3 class="m-0">Smart Service Tracker</h3>
      <div>
        <span class="me-3">Hello, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong> (<?= htmlspecialchars($_SESSION['user_role']) ?>)</span>
        <a href="logout.php" class="btn btn-light btn-sm">Logout</a>
      </div>
    </div>

    <!-- Grid: left list, right form -->
    <div class="row g-4">
      <!-- Left: Requests list -->
      <div class="col-12 col-lg-8">
        <div class="glass">
          <h5 class="mb-3">Your Requests</h5>

          <?php
            // DB connection
            $db = new mysqli('127.0.0.1','root','','smart_tracker');
            if ($db->connect_errno) {
              echo '<div class="alert alert-danger">DB connection error.</div>';
            } else {
              $uid = (int)$_SESSION['user_id'];
              $q = $db->prepare("SELECT * FROM requests WHERE user_id = ? ORDER BY id DESC");
              $q->bind_param('i', $uid);
              $q->execute();
              $res = $q->get_result();

              if ($res->num_rows == 0):
          ?>
                <div class="req-card">
                  <p class="mb-0 text-white-50">You have no requests yet.</p>
                </div>
          <?php
              else:
                while($r = $res->fetch_assoc()):
          ?>
                  <div class="req-card">
                    <div class="d-flex justify-content-between align-items-start">
                      <div>
                        <h6 class="mb-1"><?= htmlspecialchars($r['request_type']) ?></h6>
                        <div class="mb-1">
  <small class="text-white-50">Token: <strong id="token-<?= htmlspecialchars($r['token']) ?>"><?= htmlspecialchars($r['token']) ?></strong></small>
</div>

<div class="d-flex gap-2 align-items-center mt-2">
  <!-- Copy token button -->
  <button type="button" class="btn btn-sm btn-outline-light" onclick="copyToken('<?= htmlspecialchars($r['token']) ?>', this)">
    Copy
  </button>

  <!-- QR modal trigger -->
  <button type="button" class="btn btn-sm btn-outline-light" onclick="openQrModal('<?= htmlspecialchars($r['token']) ?>')">
    QR
  </button>
</div>

                        <?php if (!empty($r['deadline'])): ?>
                          <small class="text-white-50">Deadline: <?= htmlspecialchars($r['deadline']) ?></small><br>
                        <?php endif; ?>
                        <?php if (!empty($r['doc_path'])): ?>
                          <small><a href="<?= htmlspecialchars($r['doc_path']) ?>" target="_blank" class="text-info">View Document</a></small><br>
                        <?php endif; ?>
                      </div>

                      <div class="text-end">
                        <?php
                          $status = $r['status'];
                          $badgeClass = 'bg-secondary';
                          if ($status === 'In Progress') $badgeClass = 'bg-warning text-dark';
                          elseif ($status === 'Approved') $badgeClass = 'bg-info text-dark';
                          elseif ($status === 'Completed') $badgeClass = 'bg-success';
                          elseif ($status === 'Rejected') $badgeClass = 'bg-danger';
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                        <div class="mt-2">
                          <a href="track.php?token=<?= htmlspecialchars($r['token']) ?>" class="btn btn-sm btn-outline-light">Track</a>
                        </div>
                      </div>
                    </div>
                  </div>
          <?php
                endwhile;
              endif;
              $q->close();
              $db->close();
            }
          ?>

        </div>
      </div>

      <!-- Right: Create request form -->
      <div class="col-12 col-lg-4">
        <div class="glass right-col">
          <h6>Create a new request</h6>

          <?php if (!empty($_GET['msg'])): ?>
            <div class="alert alert-success py-2"><?= htmlspecialchars($_GET['msg']) ?></div>
          <?php endif; ?>

          <form action="create_request.php" method="POST" enctype="multipart/form-data">
            <div class="mb-2">
              <label class="form-label small text-white-50">Request type</label>
              <input type="text" name="request_type" class="form-control" placeholder="e.g., Address Change" required>
            </div>

            <div class="mb-2">
              <label class="form-label small text-white-50">Expected deadline (optional)</label>
              <input type="date" name="deadline" class="form-control">
            </div>

            <div class="mb-2">
              <label class="form-label small text-white-50">Upload supporting doc (optional)</label>
              <input type="file" name="doc" class="form-control">
            </div>

            <button class="btn btn-light w-100">Create Request</button>
          </form>

          <p class="small text-muted mt-2">After creating, you will receive a token/QR to track status.</p>
        </div>
      </div>
    </div> <!-- row -->
  </div> <!-- container -->

  <!-- Optional: Bootstrap JS (only if you need components that use JS) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- QR Modal -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header border-0">
        <h5 class="modal-title">Scan to Track</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <div id="qrContainer" style="display:inline-block; padding:12px; background:#fff; border-radius:8px;"></div>
        <div class="mt-3 small text-white-50" id="qrTokenText"></div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<!-- qrcodejs (CDN) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
  // Copy token to clipboard and show a small feedback
  function copyToken(token, btn) {
    navigator.clipboard.writeText(token).then(() => {
      const original = btn.innerHTML;
      btn.innerHTML = 'Copied';
      setTimeout(() => btn.innerHTML = original, 1200);
    }).catch(() => {
      alert('Copy failed — select and copy manually: ' + token);
    });
  }

  // Open QR modal and generate QR for the token tracking URL
  function openQrModal(token) {
    // clear previous
    const container = document.getElementById('qrContainer');
    container.innerHTML = '';
    // build track URL
    const url = location.origin + '/smart-tracker/track.php?token=' + encodeURIComponent(token);
    // generate QR (use light bg)
    new QRCode(container, { text: url, width: 160, height: 160, colorDark: "#000000", colorLight: "#ffffff", correctLevel: QRCode.CorrectLevel.H });
    // show token text
    document.getElementById('qrTokenText').textContent = token;
    // show modal (Bootstrap 5)
    const qrModalEl = document.getElementById('qrModal');
    const modal = new bootstrap.Modal(qrModalEl);
    modal.show();
  }
</script>

<!-- Bootstrap bundle (if not already included) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
