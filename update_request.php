<?php
session_start();

// Only officers allowed
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'officer') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['request_id']) || empty($_POST['status'])) {
    header('Location: officer.php?msg=' . urlencode('Invalid update request.'));
    exit;
}

$reqId   = (int)$_POST['request_id'];
$status  = $_POST['status'];
$remarks = trim($_POST['remarks'] ?? '');
$officer = (int)$_SESSION['user_id'];

// DB connection
$db = new mysqli('127.0.0.1','root','','smart_tracker');
if ($db->connect_errno) {
    header('Location: officer.php?msg=' . urlencode('DB connection error.'));
    exit;
}

// Check officer owns this request
$check = $db->prepare("SELECT officer_id FROM requests WHERE id = ? LIMIT 1");
$check->bind_param('i', $reqId);
$check->execute();
$res = $check->get_result();

if (!$res || $res->num_rows == 0) {
    header('Location: officer.php?msg=' . urlencode('Request not found.'));
    exit;
}

$row = $res->fetch_assoc();
if ((int)$row['officer_id'] !== $officer) {
    header('Location: officer.php?msg=' . urlencode('You are not assigned to this request.'));
    exit;
}

// Update request
$update = $db->prepare("UPDATE requests SET status = ?, remarks = ?, updated_at = NOW() WHERE id = ?");

$update->bind_param('ssi', $status, $remarks, $reqId);
$ok = $update->execute();

if ($ok) {
    header('Location: officer.php?msg=' . urlencode('Request updated successfully.'));
    exit;
} else {
    header('Location: officer.php?msg=' . urlencode('Failed to update request.'));
    exit;
}
