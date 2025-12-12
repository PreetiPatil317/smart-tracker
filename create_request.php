<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'citizen') {
    header('Location: login.php');
    exit;
}

// DB config
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'smart_tracker';
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    die("DB connect error: " . $mysqli->connect_error);
}

// Helper: generate token
function genToken($len = 8) {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $t = '';
    for ($i=0;$i<$len;$i++) {
        $t .= $chars[random_int(0, strlen($chars)-1)];
    }
    return $t;
}

// Process POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $request_type = trim($_POST['request_type'] ?? '');
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

    if ($request_type === '') {
        header('Location: dashboard.php?msg=' . urlencode('Please provide request type.'));
        exit;
    }

    // Handle file upload (optional)
    $doc_path = null;
    if (isset($_FILES['doc']) && $_FILES['doc']['error'] !== UPLOAD_ERR_NO_FILE) {
        $u = $_FILES['doc'];
        if ($u['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($u['name'], PATHINFO_EXTENSION);
            $newName = uniqid('doc_') . ($ext ? '.' . $ext : '');
            $target = __DIR__ . '/assets/uploads/' . $newName;

            if (!is_dir(__DIR__ . '/assets/uploads')) {
                mkdir(__DIR__ . '/assets/uploads', 0755, true);
            }

            if (move_uploaded_file($u['tmp_name'], $target)) {
                $doc_path = 'assets/uploads/' . $newName;
            }
        }
    }

    // Generate unique token (ensure uniqueness)
    $token = genToken(8);
    // ensure unique in DB (simple loop)
    $check = $mysqli->prepare("SELECT id FROM requests WHERE token = ? LIMIT 1");
    $tries = 0;
    while ($tries < 5) {
        $check->bind_param('s', $token);
        $check->execute();
        $res = $check->get_result();
        if ($res && $res->num_rows === 0) break;
        $token = genToken(8);
        $tries++;
    }
    $check->close();

    // Insert request
    $stmt = $mysqli->prepare("INSERT INTO requests (user_id, request_type, status, officer_id, token, doc_path, deadline) VALUES (?, ?, 'Received', NULL, ?, ?, ?)");
    // deadline may be null
    $stmt->bind_param('issss', $user_id, $request_type, $token, $doc_path, $deadline);
    $ok = $stmt->execute();
    if ($ok) {
        $stmt->close();
        header('Location: dashboard.php?msg=' . urlencode('Request created. Token: ' . $token));
        exit;
    } else {
        $err = $mysqli->error;
        $stmt->close();
        header('Location: dashboard.php?msg=' . urlencode('DB error: ' . $err));
        exit;
    }
}
header('Location: dashboard.php');
exit;
