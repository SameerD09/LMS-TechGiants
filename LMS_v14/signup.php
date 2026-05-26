<?php
require 'session.php';
require 'db.php';

// Security headers (XSS, clickjacking, MIME sniffing protection)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:;");

// If already logged in, redirect
if (isset($_SESSION['user'])) {
    header('Location: user_dashboard.php');
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$error           = '';
$emailError      = '';
$usernameError   = '';
$passwordError   = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';

    } else {
        $first           = trim($_POST['first'] ?? '');
        $last            = trim($_POST['last'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $username        = trim($_POST['username'] ?? '');
        $password        = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validate fields
        if (!$first || !$last || !$email || !$username || !$password || !$confirmPassword) {
            $error = 'All fields are required.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';

        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';

        } elseif ($password !== $confirmPassword) {
            $passwordError = 'Passwords do not match. Please try again.';

        } else {
            // Check separately for email and username
            $checkStmt = $conn->prepare("SELECT email, username FROM users WHERE email = ? OR username = ?");
            $checkStmt->bind_param("ss", $email, $username);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            while ($row = $checkResult->fetch_assoc()) {
                if ($row['email'] === $email) {
                    $emailError = 'Email already exists';
                }
                if ($row['username'] === $username) {
                    $usernameError = 'Username already exists';
                }
            }

            $checkStmt->close();

            // Only insert if both are unique
            if (!$emailError && !$usernameError) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, username, password, role) VALUES (?, ?, ?, ?, ?, 'user')");
                $stmt->bind_param("sssss", $first, $last, $email, $username, $hashedPassword);

                if ($stmt->execute()) {
                    $_SESSION['user'] = [
                        'id'        => $stmt->insert_id,
                        'username'  => $username,
                        'firstName' => $first,
                        'lastName'  => $last,
                        'email'     => $email,
                        'role'      => 'user',
                    ];

                    header('Location: user_dashboard.php');
                    exit;
                } else {
                    $error = 'Something went wrong. Please try again.';
                }

                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TechGiants – Sign Up</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    .field-error {
      color: red;
      font-size: 0.85rem;
      margin-top: 4px;
    }
  </style>
</head>
<body class="auth-body">

  <div class="auth-card">

    <!-- LEFT: Signup Form -->
    <div class="auth-left">
      <div class="auth-logo">📚 TechGiants</div>

      <div class="auth-title">Create account</div>
      <div class="auth-sub">Join TechGiants today</div>

      <?php if ($error): ?>
        <div class="auth-error visible">
          <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="signup.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="form-row">
          <div class="form-group">
            <label>First Name</label>
            <input type="text" name="first" placeholder="First name"
              value="<?php echo htmlspecialchars($_POST['first'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </div>
          <div class="form-group">
            <label>Last Name</label>
            <input type="text" name="last" placeholder="Last name"
              value="<?php echo htmlspecialchars($_POST['last'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </div>
        </div>

        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" placeholder="Enter your email"
            value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <?php if (!empty($emailError)): ?>
            <div class="field-error">
              <?php echo htmlspecialchars($emailError, ENT_QUOTES, 'UTF-8'); ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label>Username</label>
          <input type="text" name="username" placeholder="Choose a username"
            value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <?php if (!empty($usernameError)): ?>
            <div class="field-error">
              <?php echo htmlspecialchars($usernameError, ENT_QUOTES, 'UTF-8'); ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Choose a password (min 6 characters)">
        </div>

        <div class="form-group">
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" placeholder="Re-enter your password">
          <?php if (!empty($passwordError)): ?>
            <div class="field-error">
              <?php echo htmlspecialchars($passwordError, ENT_QUOTES, 'UTF-8'); ?>
            </div>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn-primary">Create Account</button>
      </form>

      <div class="auth-toggle">
        Already have an account? <a href="login.php">Log In</a>
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