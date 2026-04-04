<?php
require 'session.php';
require 'db.php';

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['user']);
    header('Location: login.php');
    exit;
}

// If already logged in, redirect to the right dashboard
if (isset($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: user_dashboard.php');
    }
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Hardcoded admin credentials
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';

    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Check if admin
        if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
            $_SESSION['user'] = [
                'username'  => 'admin',
                'firstName' => 'Admin',
                'lastName'  => 'User',
                'role'      => 'admin'
            ];
            header('Location: admin_dashboard.php');
            exit;
        }

        // Check regular users from database
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION['user'] = [
                    'id'        => $user['id'],
                    'username'  => $user['username'],
                    'firstName' => $user['first_name'],
                    'lastName'  => $user['last_name'],
                    'email'     => $user['email'],
                    'role'      => $user['role']
                ];

                if ($user['role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: user_dashboard.php');
                }
                exit;
            } else {
                $error = 'Invalid username or password. Please try again.';
            }
        } else {
            $error = 'Invalid username or password. Please try again.';
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TechGiants – Log In</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">

  <div class="auth-card">

    <!-- LEFT: Login Form -->
    <div class="auth-left">
      <div class="auth-logo">📚 TechGiants</div>

      <div class="auth-title">Welcome back</div>
      <div class="auth-sub">Sign in to access your library</div>

      <?php if ($error): ?>
        <div class="auth-error visible">
          <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="form-group">
          <label>Username</label>
          <input type="text" name="username" placeholder="Enter your username"
            value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Enter your password">
        </div>

        <button type="submit" class="btn-primary">Log In</button>
      </form>

      <div class="auth-toggle">
        Don't have an account? <a href="signup.php">Sign Up</a>
      </div>
    </div>

    <!-- RIGHT: Decoration -->
    <div class="auth-right">
      <div class="book-deco">
        <div class="book-row-deco">
          <div class="bk-deco color-3 bk-h90"></div>
          <div class="bk-deco color-1 bk-h110 bk-w32"></div>
          <div class="bk-deco color-5 bk-h95"></div>
          <div class="bk-deco color-2 bk-h105 bk-w30"></div>
        </div>
        <div class="book-row-deco">
          <div class="bk-deco color-7 bk-h85"></div>
          <div class="bk-deco color-4 bk-h100 bk-w34"></div>
          <div class="bk-deco color-6 bk-h92"></div>
        </div>
        <div class="book-row-deco">
          <div class="bk-deco color-8 bk-h80 bk-w34"></div>
          <div class="bk-deco color-1 bk-h95"></div>
          <div class="bk-deco color-3 bk-h88 bk-w30"></div>
          <div class="bk-deco color-5 bk-h78"></div>
        </div>
        <div class="deco-stars">★★★★★</div>
        <p class="deco-quote">"Your personal library,<br>beautifully organised."</p>
        <div class="deco-plant">🌿</div>
      </div>
    </div>

  </div>

</body>
</html>