<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'vendor/autoload.php';

function sendOTPEmail($toEmail, $otp) {
    $mail = new PHPMailer(true);
    try {
        // Log debug output to PHP error log for troubleshooting
        $mail->SMTPDebug   = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer [$level]: $str");
        };

        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'c9088b9ce0f403';
        $mail->Password   = '9ed6d53be2fd3f';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 10; // fail fast instead of hanging

        $mail->setFrom('noreply@lms.com', 'LMS System');
        $mail->addAddress($toEmail);

        $mail->Subject = 'Your Password Reset OTP — TechGiants Library';
        $mail->Body    =
            "Hello,\n\n" .
            "Your OTP code for password reset is: $otp\n\n" .
            "This code expires in 10 minutes.\n\n" .
            "If you did not request this, please ignore this email.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer error: " . $mail->ErrorInfo);
        return false;
    }
}

// Builds the digest email body — returns [html, plaintext].
// Called by both sendDigestEmail() and the bulk sender in weekly_digest.php.
function _buildDigestBody($firstName, $walletBalance, $borrowings, $newBooks = []) {
    $balanceFmt   = 'Rs ' . number_format($walletBalance, 2);
    $balanceColor = $walletBalance < 500 ? '#c0392b' : '#27ae60';
    $weekOf       = date('F j, Y');

    // Borrowings table
    $borrowRows = '';
    foreach ($borrowings as $b) {
        $daysLeft = (int)$b['days_left'];
        $dueDate  = date('M j, Y', strtotime($b['due_date']));
        if ($daysLeft < 0) {
            $st = '<span style="color:#c0392b;font-weight:700;">Overdue by ' . abs($daysLeft) . ' day(s)</span>';
        } elseif ($daysLeft <= 2) {
            $st = '<span style="color:#dd6b20;font-weight:700;">Due in ' . $daysLeft . ' day(s)</span>';
        } else {
            $st = '<span style="color:#27ae60;">' . $daysLeft . ' days left</span>';
        }
        $borrowRows .=
            '<tr style="border-bottom:1px solid #e8e5df;">' .
            '<td style="padding:8px 6px;"><strong>' . htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8') . '</strong></td>' .
            '<td style="padding:8px 6px;color:#666;">' . htmlspecialchars($b['author'], ENT_QUOTES, 'UTF-8') . '</td>' .
            '<td style="padding:8px 6px;white-space:nowrap;">' . $dueDate . '</td>' .
            '<td style="padding:8px 6px;">' . $st . '</td>' .
            '</tr>';
    }
    $borrowSection = count($borrowings) > 0
        ? '<h3 style="color:#5c3d1e;margin:24px 0 8px;">Currently Borrowed</h3>' .
          '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:0.92rem;">' .
          '<tr style="background:#f5f0e8;"><th align="left" style="padding:8px 6px;">Book</th><th align="left" style="padding:8px 6px;">Author</th><th align="left" style="padding:8px 6px;">Due Date</th><th align="left" style="padding:8px 6px;">Status</th></tr>' .
          $borrowRows . '</table>'
        : '<p style="color:#888;margin-top:16px;">You have no books currently borrowed. Visit the library to pick one up!</p>';

    // New books section
    $newBooksSection = '';
    if (count($newBooks) > 0) {
        $items = '';
        foreach ($newBooks as $nb) {
            $items .= '<li style="margin-bottom:4px;"><strong>' . htmlspecialchars($nb['title'], ENT_QUOTES, 'UTF-8') . '</strong> by ' .
                htmlspecialchars($nb['author'], ENT_QUOTES, 'UTF-8') .
                ' <span style="color:#888;font-size:0.88em;">(' . htmlspecialchars($nb['genre'], ENT_QUOTES, 'UTF-8') . ')</span></li>';
        }
        $newBooksSection = '<h3 style="color:#5c3d1e;margin:24px 0 8px;">New Arrivals This Week</h3>' .
            '<ul style="padding-left:18px;color:#444;margin:0;">' . $items . '</ul>';
    }

    $html = '<!DOCTYPE html><html><body style="font-family:Georgia,serif;background:#faf8f5;margin:0;padding:20px 0;">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.08);">
  <div style="background:#5c3d1e;padding:28px 32px;">
    <div style="font-size:1.5rem;color:#fff;font-weight:700;">&#128218; TechGiants Library</div>
    <div style="color:#d9c9b0;margin-top:4px;font-size:0.9rem;">Weekly Summary &mdash; ' . $weekOf . '</div>
  </div>
  <div style="padding:28px 32px;">
    <p style="color:#444;font-size:1rem;margin-top:0;">Hello <strong>' . htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
    <p style="color:#666;margin-top:0;">Here&rsquo;s your weekly library update.</p>
    <div style="display:inline-block;background:#f5f0e8;border-radius:10px;padding:16px 24px;margin-bottom:8px;">
      <div style="font-size:0.82rem;color:#888;margin-bottom:4px;">Wallet Balance</div>
      <div style="font-size:1.7rem;font-weight:700;color:' . $balanceColor . ';">' . $balanceFmt . '</div>
    </div>
    ' . $borrowSection . '
    ' . $newBooksSection . '
    <p style="margin-top:32px;color:#aaa;font-size:0.85rem;">&copy; TechGiants Library &mdash; You&rsquo;re receiving this because you have an account.</p>
  </div>
</div>
</body></html>';

    $altLines = ["Hello {$firstName},", "", "Weekly Library Summary — {$weekOf}", "", "Wallet Balance: {$balanceFmt}", ""];
    if (count($borrowings) > 0) {
        $altLines[] = "Active Borrowings:";
        foreach ($borrowings as $b) {
            $altLines[] = "  - {$b['title']} (due {$b['due_date']}, {$b['days_left']} days left)";
        }
    } else {
        $altLines[] = "No active borrowings.";
    }
    if (count($newBooks) > 0) {
        $altLines[] = "";
        $altLines[] = "New This Week:";
        foreach ($newBooks as $nb) { $altLines[] = "  - {$nb['title']} by {$nb['author']}"; }
    }
    $altLines[] = "";
    $altLines[] = "Visit TechGiants Library to manage your account.";

    return [$html, implode("\n", $altLines)];
}

function sendDigestEmail($toEmail, $firstName, $walletBalance, $borrowings, $newBooks = []) {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug   = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'c9088b9ce0f403';
        $mail->Password   = '9ed6d53be2fd3f';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 15;

        $mail->setFrom('noreply@lms.com', 'TechGiants Library');
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Your Weekly Library Summary — TechGiants Library';

        [$mail->Body, $mail->AltBody] = _buildDigestBody($firstName, $walletBalance, $borrowings, $newBooks);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Digest mailer error for {$toEmail}: " . $mail->ErrorInfo);
        return false;
    }
}

function sendSignupOTP($toEmail, $otp, $firstName = 'there') {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug   = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str, $level) { error_log("PHPMailer [$level]: $str"); };
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'c9088b9ce0f403';
        $mail->Password   = '9ed6d53be2fd3f';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 10;

        $mail->setFrom('noreply@lms.com', 'TechGiants Library');
        $mail->addAddress($toEmail);

        $mail->Subject = 'Verify your TechGiants Library account';
        $mail->Body    =
            "Hello $firstName,\n\n" .
            "Welcome to TechGiants Library!\n\n" .
            "Please verify your email address to complete your registration.\n\n" .
            "Your verification code: $otp\n\n" .
            "This code expires in 10 minutes.\n\n" .
            "If you did not sign up for TechGiants Library, please ignore this email.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer signup error: " . $mail->ErrorInfo);
        return false;
    }
}
