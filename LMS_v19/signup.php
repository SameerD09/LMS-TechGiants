<?php
require 'session.php';
require 'db.php';

// Security headers
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
</head>
<body class="auth-body">

  <div class="auth-card">

    <!-- LEFT: Signup Form -->
    <div class="auth-left">
      <div class="auth-logo">📚 TechGiants</div>

      <div class="auth-title">Create account</div>
      <div class="auth-sub">Join TechGiants today</div>

      <div id="signupErrorGeneral" style="display:none;" class="auth-error visible"></div>

      <form id="signupForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="form-row">
          <div class="form-group">
            <label>First Name</label>
            <input type="text" id="signupFirst" name="first" placeholder="First name"
              pattern="[a-zA-Z\s'\-\.]+" title="Name must contain only letters">
          </div>
          <div class="form-group">
            <label>Last Name</label>
            <input type="text" id="signupLast" name="last" placeholder="Last name"
              pattern="[a-zA-Z\s'\-\.]+" title="Name must contain only letters">
          </div>
        </div>

        <div class="form-group">
          <label>Email</label>
          <input type="email" id="signupEmail" name="email" placeholder="Enter your email">
          <div id="signupEmailError" class="field-error" style="display:none;"></div>
        </div>

        <div class="form-group">
          <label>Username</label>
          <input type="text" id="signupUsername" name="username" placeholder="Choose a username">
          <div id="signupUsernameError" class="field-error" style="display:none;"></div>
        </div>

        <div class="form-group">
          <label>Password</label>
          <div class="pw-wrap">
            <input type="password" id="signupPassword" name="password" placeholder="Choose a password (min 6 characters)">
            <button type="button" class="pw-toggle" onclick="togglePw('signupPassword', this)" aria-label="Show password">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div id="signupPasswordError" class="field-error" style="display:none;"></div>
        </div>

        <div class="form-group">
          <label>Confirm Password</label>
          <div class="pw-wrap">
            <input type="password" id="signupConfirm" name="confirm_password" placeholder="Re-enter your password">
            <button type="button" class="pw-toggle" onclick="togglePw('signupConfirm', this)" aria-label="Show password">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div id="signupConfirmError" class="field-error" style="display:none;"></div>
        </div>

        <button type="submit" class="btn-primary" id="signupSubmitBtn">
          <span id="signupSubmitLabel">Create Account</span>
          <span id="signupSubmitSpinner" style="display:none;">Sending verification…</span>
        </button>

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

  <!-- ═══ OTP VERIFICATION MODAL ══════════════════════════════════════════════ -->
  <div class="modal-overlay" id="otpOverlay">
    <div class="modal modal--fp">

      <!-- Step indicator (2 dots) -->
      <div class="otp-step-dots">
        <div id="suDot1" class="otp-dot"></div>
        <div id="suDot2" class="otp-dot"></div>
      </div>

      <div class="modal-header modal-header--mb6">
        <div class="modal-title">Verify your email</div>
        <button class="modal-close" id="otpClose">✕</button>
      </div>
      <p id="otpSubtext" class="modal-subtitle">
        A 6-digit verification code has been sent to your email. Enter it below (expires in 10 minutes).
      </p>

      <div id="otpError" class="modal-err-msg" style="display:none;"></div>
      <div id="otpSuccess" class="modal-ok-msg" style="display:none;"></div>

      <div class="form-group">
        <label>Verification Code</label>
        <input type="text" id="otpInput" placeholder="Enter 6-digit code" maxlength="6" class="otp-input" inputmode="numeric">
      </div>

      <button class="btn-primary btn-primary--mt6" id="otpVerifyBtn">
        <span id="otpVerifyLabel">Verify &amp; Create Account →</span>
        <span id="otpVerifySpinner" style="display:none;">Creating account…</span>
      </button>

      <div class="otp-resend-row">
        Didn't receive the code?
        <a href="#" id="otpResendLink" class="otp-resend-link">Resend</a>
        <span id="otpResendTimer"></span>
      </div>

    </div>
  </div>

  <script>
  function togglePw(inputId, btn) {
    var inp  = document.getElementById(inputId);
    var show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.innerHTML = show
      ? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
      : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
  }

  (function () {
    const csrf        = <?php echo json_encode($csrf_token); ?>;
    const overlay     = document.getElementById('otpOverlay');
    const otpInput    = document.getElementById('otpInput');
    const otpError    = document.getElementById('otpError');
    const otpSuccess  = document.getElementById('otpSuccess');
    const dot1        = document.getElementById('suDot1');
    const dot2        = document.getElementById('suDot2');
    let resendTimer   = null;

    function showErr(msg) { otpError.textContent = msg; otpError.style.display = 'block'; otpSuccess.style.display = 'none'; }
    function showOk(msg)  { otpSuccess.textContent = msg; otpSuccess.style.display = 'block'; otpError.style.display = 'none'; }
    function hideAll()    { otpError.style.display = 'none'; otpSuccess.style.display = 'none'; }

    function clearFieldErrors() {
      ['signupErrorGeneral','signupEmailError','signupUsernameError','signupPasswordError','signupConfirmError'].forEach(id => {
        const el = document.getElementById(id);
        el.style.display = 'none';
        el.textContent = '';
      });
    }

    function startResendCountdown(secs) {
      const link  = document.getElementById('otpResendLink');
      const timer = document.getElementById('otpResendTimer');
      link.style.pointerEvents = 'none';
      link.style.opacity = '0.4';
      let left = secs;
      clearInterval(resendTimer);
      resendTimer = setInterval(function () {
        left--;
        timer.textContent = ' (' + left + 's)';
        if (left <= 0) {
          clearInterval(resendTimer);
          timer.textContent = '';
          link.style.pointerEvents = '';
          link.style.opacity = '';
        }
      }, 1000);
    }

    function openOtpModal(emailHint) {
      document.getElementById('otpSubtext').textContent =
        'A 6-digit verification code has been sent to ' + emailHint + '. Enter it below (expires in 10 minutes).';
      otpInput.value = '';
      hideAll();
      dot1.style.background = 'var(--brown)';
      dot2.style.background = '#ddd';
      overlay.classList.add('open');
      startResendCountdown(30);
      setTimeout(function () { otpInput.focus(); }, 100);
    }

    function closeOtpModal() {
      overlay.classList.remove('open');
      clearInterval(resendTimer);
    }

    document.getElementById('otpClose').addEventListener('click', closeOtpModal);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeOtpModal(); });

    // ── Form submit → send_otp ───────────────────────────────────────────────
    document.getElementById('signupForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      clearFieldErrors();

      const first    = document.getElementById('signupFirst').value.trim();
      const last     = document.getElementById('signupLast').value.trim();
      const email    = document.getElementById('signupEmail').value.trim();
      const username = document.getElementById('signupUsername').value.trim();
      const password = document.getElementById('signupPassword').value;
      const confirm  = document.getElementById('signupConfirm').value;

      const btn     = document.getElementById('signupSubmitBtn');
      const label   = document.getElementById('signupSubmitLabel');
      const spinner = document.getElementById('signupSubmitSpinner');

      label.style.display   = 'none';
      spinner.style.display = '';
      btn.disabled = true;

      try {
        const res  = await fetch('signup_api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ step: 'send_otp', csrf_token: csrf, first, last, email, username, password, confirm_password: confirm })
        });
        const data = await res.json();

        if (data.success) {
          openOtpModal(data.email_hint);
        } else {
          // Show field-specific or general error
          if (data.field === 'email') {
            const el = document.getElementById('signupEmailError');
            el.textContent = data.message; el.style.display = 'block';
          } else if (data.field === 'username') {
            const el = document.getElementById('signupUsernameError');
            el.textContent = data.message; el.style.display = 'block';
          } else if (data.field === 'password') {
            const el = document.getElementById('signupPasswordError');
            el.textContent = data.message; el.style.display = 'block';
          } else if (data.field === 'confirm_password') {
            const el = document.getElementById('signupConfirmError');
            el.textContent = data.message; el.style.display = 'block';
          } else {
            const el = document.getElementById('signupErrorGeneral');
            el.textContent = data.message; el.style.display = 'block';
          }
        }
      } catch (err) {
        const el = document.getElementById('signupErrorGeneral');
        el.textContent = 'Network error. Please try again.'; el.style.display = 'block';
      } finally {
        label.style.display   = '';
        spinner.style.display = 'none';
        btn.disabled = false;
      }
    });

    // ── Verify OTP → create account ──────────────────────────────────────────
    document.getElementById('otpVerifyBtn').addEventListener('click', async function () {
      hideAll();
      const otp = otpInput.value.trim();
      if (!otp || otp.length !== 6) { showErr('Please enter the 6-digit code.'); return; }

      const label   = document.getElementById('otpVerifyLabel');
      const spinner = document.getElementById('otpVerifySpinner');
      label.style.display   = 'none';
      spinner.style.display = '';
      this.disabled = true;

      try {
        const res  = await fetch('signup_api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ step: 'verify_and_create', csrf_token: csrf, otp })
        });
        const data = await res.json();

        if (data.success) {
          dot2.style.background = 'var(--brown)';
          showOk('Account created! Redirecting…');
          clearInterval(resendTimer);
          setTimeout(function () { window.location.href = data.redirect || 'user_dashboard.php'; }, 1200);
        } else {
          showErr(data.message);
          label.style.display   = '';
          spinner.style.display = 'none';
          this.disabled = false;
        }
      } catch (err) {
        showErr('Network error. Please try again.');
        label.style.display   = '';
        spinner.style.display = 'none';
        this.disabled = false;
      }
    });

    // Allow Enter key in OTP input
    otpInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') document.getElementById('otpVerifyBtn').click();
    });

    // ── Resend OTP ───────────────────────────────────────────────────────────
    document.getElementById('otpResendLink').addEventListener('click', async function (e) {
      e.preventDefault();
      hideAll();

      try {
        const res  = await fetch('signup_api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ step: 'resend_otp', csrf_token: csrf })
        });
        const data = await res.json();
        if (data.success) {
          showOk('A new code has been sent to your email.');
          startResendCountdown(60);
        } else {
          showErr(data.message || 'Failed to resend. Please try again.');
        }
      } catch (err) {
        showErr('Network error. Please try again.');
      }
    });

  })();
  </script>

<?php $_chatPage = 'signup'; require 'chatbot_auth_widget.php'; ?>
</body>
</html>
