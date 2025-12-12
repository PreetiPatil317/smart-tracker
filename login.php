<?php
session_start();

/*
  login.php
  Login handler:
  - Connects to MySQL (XAMPP defaults)
  - Looks up user by email (includes last_seen)
  - Accepts either:
      1) hashed password (password_verify)
      2) plain-text password (for demo users we inserted)
  - Sets session and redirects based on role.
  - For citizens: prepares a login popup message stored in $_SESSION['login_popup']
*/

$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'smart_tracker';

// DB connection
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
}

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $login_error = 'Please enter both email and password.';
    } else {
        // Select user including last_seen
        $stmt = $mysqli->prepare("SELECT id, name, email, password, role, last_seen FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows === 1) {
            $user = $res->fetch_assoc();
            $stored = $user['password'];

            $ok = false;
            if (password_verify($password, $stored)) {
                $ok = true;
            } elseif ($password === $stored) {
                // demo fallback (plain password)
                $ok = true;
            }

            if ($ok) {
                // set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];

                // ---------------------
                // Citizen Login Handling
                // ---------------------
                if ($user['role'] === 'citizen') {

                    $uid = (int)$user['id'];

                    // Fetch the latest update timestamp from user's requests
                    $stmt2 = $mysqli->prepare("
                        SELECT 
                            request_type,
                            status,
                            COALESCE(updated_at, created_at) AS latest_time
                        FROM requests
                        WHERE user_id = ?
                        ORDER BY latest_time DESC
                        LIMIT 1
                    ");
                    $stmt2->bind_param("i", $uid);
                    $stmt2->execute();
                    $result = $stmt2->get_result();

                    $popup_message = "";

                    if ($result && $result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $latest = $row['latest_time'];

                        // Compare with last_seen (may be NULL)
                        $last_seen = $user['last_seen']; // may be null string or null

                        if ($last_seen === null || $last_seen === '' || strtotime($latest) > strtotime($last_seen)) {
                            $popup_message = "Latest update on your request <strong>" . 
                                              htmlspecialchars($row['request_type']) . 
                                              "</strong> is now <strong>" . 
                                              htmlspecialchars($row['status']) . 
                                              "</strong>.";
                        } else {
                            $popup_message = "No new updates since your last login.";
                        }
                    } else {
                        // No requests created yet
                        $popup_message = "Welcome! You have not created any requests yet.";
                    }

                    // Store popup message in session for dashboard to show
                    $_SESSION['login_popup'] = $popup_message;

                    // Update last_seen
                    $updateSeen = $mysqli->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
                    $updateSeen->bind_param("i", $uid);
                    $updateSeen->execute();
                    $updateSeen->close();
                    if (isset($stmt2)) $stmt2->close();

                    header("Location: dashboard.php");
                    exit;
                }

                // Officer or admin redirect
                if ($user['role'] === 'officer') {
                    header("Location: officer.php");
                    exit;
                } elseif ($user['role'] === 'admin') {
                    header("Location: admin.php");
                    exit;
                } else {
                    header("Location: dashboard.php");
                    exit;
                }

            } else {
                $login_error = 'Invalid credentials.';
            }
        } else {
            $login_error = 'No account found for that email.';
        }

        if (isset($stmt)) $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Smart Service Tracker - Login</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Poppins', sans-serif;
        }
        .glass {
            width: 360px;
            padding: 28px;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(8px);
            border-radius: 18px;
            color: white;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }
        input.form-control {
            background: rgba(255,255,255,0.18) !important;
            color: white !important;
            border-radius: 10px !important;
            border: none;
        }
        .small-muted { font-size: 13px; opacity: 0.9; }
    </style>
</head>
<body>
    <div class="glass">
        <h3 class="text-center mb-3">Smart Service Tracker</h3>

        <?php if ($login_error): ?>
            <div class="alert alert-danger py-2" role="alert"><?=htmlspecialchars($login_error)?></div>
        <?php endif; ?>

        <form action="login.php" method="POST" autocomplete="off">
            <div class="mb-2">
                <input type="email" name="email" class="form-control" placeholder="Email" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <button class="btn btn-light w-100 mb-2">Login</button>

            <p class="text-center mt-3">
                New User? <a href="register.php" class="text-warning">Create an Account</a>
            </p>
        </form>

        
    </div>
</body>
</html>
