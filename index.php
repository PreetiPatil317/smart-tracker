<?php
session_start();

// If user already logged in, redirect automatically
if (isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'citizen') {
        header("Location: dashboard.php");
        exit;
    }
    if ($_SESSION['user_role'] === 'officer') {
        header("Location: officer.php");
        exit;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Smart Service Tracker</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: Poppins, sans-serif;
            color: white;
        }
        .glass {
            width: 420px;
            padding: 30px;
            background: rgba(255,255,255,0.12);
            border-radius: 18px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            text-align: center;
        }
        .btn-custom {
            background: white;
            color: #2575fc;
            font-weight: bold;
            border-radius: 12px;
        }
    </style>
</head>

<body>

<div class="glass">
    <h2 class="mb-3">Smart Service Tracker</h2>
    <p>Your gateway to transparent public service request tracking</p>

    <div class="d-grid gap-3 mt-4">
        <a href="login.php" class="btn btn-custom btn-lg">Login</a>
        <a href="register.php" class="btn btn-outline-light btn-lg">New User? Register</a>
    </div>

    <p class="text-white-50 mt-3 small">
        Track your request anytime with your unique token
    </p>
</div>

</body>
</html>
