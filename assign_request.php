<?php
session_start();

// only officers can assign
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'officer') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['request_id'])) {
    header('Location: officer.php?msg=' . urlencode('Invalid request.'));
    exit;
}

$reqId = (int)$_POST['request_id'];
$officerId = (int)$_SESSION['user_id'];

// DB connection
$db = new mysqli('127.0.0.1','root','','smart_tracker');
if ($db->connect_errno) {
    header('Location: officer.php?msg=' . urlencode('DB connection error.'));
    exit;
}

// Start transaction to avoid race conditions
$db->begin_transaction();

try {
    // Check current officer_id (ensure it's still unassigned)
    $check = $db->prepare("SELECT officer_id FROM requests WHERE id = ? FOR UPDATE");
    $check->bind_param('i', $reqId);
    $check->execute();
    $res = $check->get_result();

    if (!$res || $res->num_rows === 0) {
        $check->close();
        $db->rollback();
        header('Location: officer.php?msg=' . urlencode('Request not found.'));
        exit;
    }

    $row = $res->fetch_assoc();
    $check->close();

    if (!is_null($row['officer_id'])) {
        // already assigned
        $db->rollback();
        header('Location: officer.php?msg=' . urlencode('Request already assigned.'));
        exit;
    }

    // Assign to current officer
    $upd = $db->prepare("UPDATE requests SET officer_id = ? WHERE id = ?");
    $upd->bind_param('ii', $officerId, $reqId);
    $ok = $upd->execute();
    $upd->close();

    if (!$ok) {
        $db->rollback();
        header('Location: officer.php?msg=' . urlencode('Failed to assign request.'));
        exit;
    }

    $db->commit();
    header('Location: officer.php?msg=' . urlencode('Assigned to you successfully.'));
    exit;

} catch (Exception $e) {
    $db->rollback();
    header('Location: officer.php?msg=' . urlencode('Error: ' . $e->getMessage()));
    exit;
}
