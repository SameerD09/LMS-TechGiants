<?php
require 'session.php';
require 'db.php';

// Security headers (XSS, clickjacking, MIME sniffing protection)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:;");

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
            header('Location: admin_dashboard.php?welcome=1');
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
                    header('Location: admin_dashboard.php?welcome=1');
                } else {
                    header('Location: user_dashboard.php?welcome=1');
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

      <div class="auth-toggle" style="margin-top:10px;">
        <a href="#" id="forgotPasswordLink" style="font-size:13px;">Forgot password?</a>
      </div>

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

  <!-- ═══ FORGOT PASSWORD MODAL ═══════════════════════════════════════════ -->
  <div class="modal-overlay" id="forgotOverlay">
    <div class="modal" style="width:440px; max-width:96vw;">

      <!-- Step indicator (3 dots now) -->
      <div id="fpStepIndicator" style="display:flex;gap:8px;margin-bottom:20px;">
        <div id="fpDot1" style="flex:1;height:4px;border-radius:4px;background:var(--brown);transition:background .3s;"></div>
        <div id="fpDot2" style="flex:1;height:4px;border-radius:4px;background:#ddd;transition:background .3s;"></div>
        <div id="fpDot3" style="flex:1;height:4px;border-radius:4px;background:#ddd;transition:background .3s;"></div>
      </div>

      <!-- ── Step 1: Username + Email ── -->
      <div id="fpStep1">
        <div class="modal-header" style="margin-bottom:6px;">
          <div class="modal-title">Recover your account</div>
          <button class="modal-close" id="fpClose1">✕</button>
        </div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:22px;">
          Enter your registered username and email. We'll send an OTP to your email.
        </p>

        <div id="fpError1" style="display:none;color:#c0392b;font-size:13px;margin-bottom:12px;padding:10px 14px;background:#fdf0ef;border-radius:8px;"></div>

        <div class="form-group">
          <label>Username</label>
          <input type="text" id="fpUsername" placeholder="Your username">
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" id="fpEmail" placeholder="Your registered email">
        </div>

        <button class="btn-primary" id="fpNextBtn" style="margin-top:6px;">
          <span id="fpNextLabel">Send OTP →</span>
          <span id="fpNextSpinner" style="display:none;">Sending…</span>
        </button>
      </div>

      <!-- ── Step 2: OTP Verification ── -->
      <div id="fpStep2" style="display:none;">
        <div class="modal-header" style="margin-bottom:6px;">
          <div class="modal-title">Enter OTP</div>
          <button class="modal-close" id="fpClose2">✕</button>
        </div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:22px;">
          A 6-digit OTP has been sent to your email. Enter it below (expires in 10 minutes).
        </p>

        <div id="fpError2" style="display:none;color:#c0392b;font-size:13px;margin-bottom:12px;padding:10px 14px;background:#fdf0ef;border-radius:8px;"></div>

        <div class="form-group">
          <label>OTP Code</label>
          <input type="text" id="fpOtp" placeholder="Enter 6-digit OTP" maxlength="6" style="letter-spacing:4px;font-size:18px;">
        </div>

        <button class="btn-primary" id="fpVerifyOtpBtn" style="margin-top:6px;">
          <span id="fpVerifyOtpLabel">Verify OTP →</span>
          <span id="fpVerifyOtpSpinner" style="display:none;">Verifying…</span>
        </button>
      </div>

      <!-- ── Step 3: New Password ── -->
      <div id="fpStep3" style="display:none;">
        <div class="modal-header" style="margin-bottom:6px;">
          <div class="modal-title">Set new password</div>
          <button class="modal-close" id="fpClose3">✕</button>
        </div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:22px;">
          OTP verified ✓ — choose a new password (at least 6 characters).
        </p>

        <div id="fpError3" style="display:none;color:#c0392b;font-size:13px;margin-bottom:12px;padding:10px 14px;background:#fdf0ef;border-radius:8px;"></div>
        <div id="fpSuccess3" style="display:none;color:#27ae60;font-size:13px;margin-bottom:12px;padding:10px 14px;background:#edfaf1;border-radius:8px;"></div>

        <div class="form-group">
          <label>New Password</label>
          <input type="password" id="fpNewPassword" placeholder="New password">
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input type="password" id="fpConfirmPassword" placeholder="Repeat new password">
        </div>

        <button class="btn-primary" id="fpResetBtn" style="margin-top:6px;">
          <span id="fpResetLabel">Reset Password</span>
          <span id="fpResetSpinner" style="display:none;">Saving…</span>
        </button>
      </div>

    </div>
  </div>

  <script>
  (function () {
    const overlay = document.getElementById('forgotOverlay');
    const link    = document.getElementById('forgotPasswordLink');
    const step1   = document.getElementById('fpStep1');
    const step2   = document.getElementById('fpStep2');
    const step3   = document.getElementById('fpStep3');
    const dot1    = document.getElementById('fpDot1');
    const dot2    = document.getElementById('fpDot2');
    const dot3    = document.getElementById('fpDot3');
    const err1    = document.getElementById('fpError1');
    const err2    = document.getElementById('fpError2');
    const err3    = document.getElementById('fpError3');
    const succ3   = document.getElementById('fpSuccess3');
    const csrf    = <?php echo json_encode($csrf_token); ?>;

    function openModal() {
      overlay.classList.add('open');
      showStep(1);
    }

    function closeModal() {
      overlay.classList.remove('open');
      ['fpUsername','fpEmail','fpOtp','fpNewPassword','fpConfirmPassword'].forEach(id => {
        document.getElementById(id).value = '';
      });
      document.getElementById('fpResetBtn').style.display = '';
      hideMsg(err1); hideMsg(err2); hideMsg(err3); hideMsg(succ3);
    }

    function showStep(n) {
      step1.style.display = n === 1 ? '' : 'none';
      step2.style.display = n === 2 ? '' : 'none';
      step3.style.display = n === 3 ? '' : 'none';
      dot1.style.background = 'var(--brown)';
      dot2.style.background = n >= 2 ? 'var(--brown)' : '#ddd';
      dot3.style.background = n === 3 ? 'var(--brown)' : '#ddd';
    }

    function showMsg(el, msg) { el.textContent = msg; el.style.display = 'block'; }
    function hideMsg(el)      { el.style.display = 'none'; }

    link.addEventListener('click', function(e){ e.preventDefault(); openModal(); });
    document.getElementById('fpClose1').addEventListener('click', closeModal);
    document.getElementById('fpClose2').addEventListener('click', closeModal);
    document.getElementById('fpClose3').addEventListener('click', closeModal);
    overlay.addEventListener('click', function(e){ if (e.target === overlay) closeModal(); });

    // ── Step 1: Send OTP ────────────────────────────────────────────────────
    document.getElementById('fpNextBtn').addEventListener('click', async function () {
      hideMsg(err1);
      const username = document.getElementById('fpUsername').value.trim();
      const email    = document.getElementById('fpEmail').value.trim();

      if (!username || !email) {
        showMsg(err1, 'Please fill in all fields.'); return;
      }

      document.getElementById('fpNextLabel').style.display   = 'none';
      document.getElementById('fpNextSpinner').style.display = '';
      this.disabled = true;

      const fd = new FormData();
      fd.append('step', 'verify');
      fd.append('csrf_token', csrf);
      fd.append('username', username);
      fd.append('email', email);

      try {
        const res  = await fetch('forgot_password.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          showStep(2);
        } else {
          showMsg(err1, data.message);
        }
      } catch (e) {
        showMsg(err1, 'Network error. Please try again.');
      } finally {
        document.getElementById('fpNextLabel').style.display   = '';
        document.getElementById('fpNextSpinner').style.display = 'none';
        this.disabled = false;
      }
    });

    // ── Step 2: Verify OTP ──────────────────────────────────────────────────
    document.getElementById('fpVerifyOtpBtn').addEventListener('click', async function () {
      hideMsg(err2);
      const otp = document.getElementById('fpOtp').value.trim();

      if (!otp || otp.length !== 6) {
        showMsg(err2, 'Please enter the 6-digit OTP.'); return;
      }

      document.getElementById('fpVerifyOtpLabel').style.display   = 'none';
      document.getElementById('fpVerifyOtpSpinner').style.display = '';
      this.disabled = true;

      const fd = new FormData();
      fd.append('step', 'verify_otp');
      fd.append('csrf_token', csrf);
      fd.append('otp', otp);

      try {
        const res  = await fetch('forgot_password.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          showStep(3);
        } else {
          showMsg(err2, data.message);
        }
      } catch (e) {
        showMsg(err2, 'Network error. Please try again.');
      } finally {
        document.getElementById('fpVerifyOtpLabel').style.display   = '';
        document.getElementById('fpVerifyOtpSpinner').style.display = 'none';
        this.disabled = false;
      }
    });

    // ── Step 3: Reset Password ──────────────────────────────────────────────
    document.getElementById('fpResetBtn').addEventListener('click', async function () {
      hideMsg(err3); hideMsg(succ3);
      const newPwd  = document.getElementById('fpNewPassword').value;
      const confPwd = document.getElementById('fpConfirmPassword').value;

      if (!newPwd || !confPwd) {
        showMsg(err3, 'Please fill in both password fields.'); return;
      }
      if (newPwd !== confPwd) {
        showMsg(err3, 'Passwords do not match.'); return;
      }

      document.getElementById('fpResetLabel').style.display   = 'none';
      document.getElementById('fpResetSpinner').style.display = '';
      this.disabled = true;

      const fd = new FormData();
      fd.append('step', 'reset');
      fd.append('csrf_token', csrf);
      fd.append('new_password', newPwd);
      fd.append('confirm_password', confPwd);

      try {
        const res  = await fetch('forgot_password.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          hideMsg(err3);
          showMsg(succ3, data.message);
          document.getElementById('fpResetBtn').style.display = 'none';
          setTimeout(closeModal, 2500);
        } else {
          showMsg(err3, data.message);
        }
      } catch (e) {
        showMsg(err3, 'Network error. Please try again.');
      } finally {
        document.getElementById('fpResetLabel').style.display   = '';
        document.getElementById('fpResetSpinner').style.display = 'none';
        this.disabled = false;
      }
    });

  })();
  </script>

</body>
</html>