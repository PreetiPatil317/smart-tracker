<?php
session_start();

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// DB connection
$db = new mysqli('127.0.0.1', 'root', '', 'smart_tracker');
if ($db->connect_errno) {
    die("DB error: " . $db->connect_error);
}

$msg = "";

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if ($name === "" || $email === "" || $password === "") {
        $msg = "All fields are required!";
    } else {
        // Check if email already exists
        $check = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $msg = "Email already registered!";
        } else {
            // Hash password
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // Insert new citizen
            $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'citizen')");
            $stmt->bind_param("sss", $name, $email, $hashed);

            if ($stmt->execute()) {
                $msg = "Registration successful! Please login.";
            } else {
                $msg = "Registration failed!";
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Register - Smart Service Tracker</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body {
        background: linear-gradient(135deg,#6a11cb,#2575fc);
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        font-family: Poppins, sans-serif;
    }
    .glass {
        width: 380px;
        padding: 30px;
        background: rgba(255,255,255,0.12);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        color: #fff;
        box-shadow: 0 8px 32px rgba(0,0,0,0.25);
    }
    input {
        background: rgba(255,255,255,0.18) !important;
        color: white !important;
        border-radius: 10px !important;
    }
</style>
</head>
<body>

<div class="glass">
    <h3 class="text-center mb-3">Create Account</h3>

    <?php if ($msg): ?>
        <div class="alert alert-warning py-2"><?= $msg ?></div>
    <?php endif; ?>

    <form action="register.php" method="POST">
        <input type="text" name="name" class="form-control mb-3" placeholder="Your Name" required>
        <input type="email" name="email" class="form-control mb-3" placeholder="Email" required>
        <input type="password" name="password" class="form-control mb-3" placeholder="Password" required>

        <button class="btn btn-light w-100">Register</button>

        <p class="text-center mt-3">
            Already have an account?
            <a href="login.php" class="text-warning">Login</a>
        </p>
    </form>
</div>

</body>
</html>
