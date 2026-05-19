<?php
require 'session.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['user']['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

$user = $_SESSION['user'];
$showWelcome = isset($_GET['welcome']) ? 'true' : 'false';

// ── Overdue reminder check (fires once per day per borrowing) ──
require 'db.php';
require 'notifications_helper.php';

$overdueUserId = intval($user['id']);
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Find borrowings due today or tomorrow where we haven't sent a reminder yet
$remStmt = $conn->prepare(
    "SELECT bw.id AS borrowing_id, bw.book_id, bw.due_date, bk.title
     FROM borrowings bw
     JOIN books bk ON bk.id = bw.book_id
     WHERE bw.user_id = ? AND bw.status = 'borrowed'
       AND bw.due_date IN (?, ?)
       AND NOT EXISTS (
         SELECT 1 FROM notifications n
         WHERE n.user_id = bw.user_id
           AND n.type = 'due_reminder'
           AND n.message LIKE CONCAT('%', bk.title, '%')
           AND DATE(n.created_at) = ?
       )"
);
$remStmt->bind_param("isss", $overdueUserId, $today, $tomorrow, $today);
$remStmt->execute();
$remResult = $remStmt->get_result();
while ($rem = $remResult->fetch_assoc()) {
    $dueDate = $rem['due_date'];
    if ($dueDate === $today) {
        pushNotification($conn, $overdueUserId, 'due_reminder',
            '⏰ Due Today!',
            "Reminder: \"{$rem['title']}\" is due back today. Please return it to avoid overdue fines."
        );
    } else {
        pushNotification($conn, $overdueUserId, 'due_reminder',
            '📅 Due Tomorrow',
            "Reminder: \"{$rem['title']}\" is due tomorrow. Return it on time to avoid fines."
        );
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TechGiants – My Library</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body class="user-body">

<div class="user-app-wrapper">

  <!-- USER SIDEBAR -->
  <div class="user-sidebar">
    <div class="user-sidebar-logo">📚 TechGiants</div>

    <nav class="user-nav">
      <div id="uNavBookstore" class="u-nav-item active" onclick="uSwitchView('Bookstore')">
        <span class="u-nav-icon">🏪</span> Bookstore
      </div>
      <div id="uNavReading" class="u-nav-item" onclick="uSwitchView('Reading')">
        <span class="u-nav-icon">📖</span> Now Reading
      </div>
      <div id="uNavFavorites" class="u-nav-item" onclick="uSwitchView('Favorites')">
        <span class="u-nav-icon">❤️</span> Favorites
      </div>
      <div id="uNavAuthors" class="u-nav-item" onclick="uSwitchView('Authors')">
        <span class="u-nav-icon">✍️</span> Authors
      </div>
    </nav>

    <div class="user-sidebar-bottom">
      <div class="u-user-pill" onclick="openProfileModal()" title="My Profile">
        <div class="u-sidebar-avatar" id="uSidebarAvatar">
          <?php echo strtoupper($user['firstName'][0]); ?>
        </div>
        <div class="u-sidebar-user-info">
          <div class="u-sidebar-name" id="uSidebarName"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName'], ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="u-sidebar-username" id="uSidebarUsername">@<?php echo htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="u-sidebar-role">Member &nbsp;✏️</div>
        </div>
      </div>
    </div>
  </div>

  <!-- USER MAIN -->
  <div class="user-main">

    <!-- LOW BALANCE WARNING BANNER -->
    <div id="uLowBalanceWarn" class="u-low-balance-warn" style="display:none;">
      <span class="u-low-balance-icon">⚠️</span>
      <span>Your wallet balance is below <strong>Rs 200</strong>. Top up to avoid issues with fines or extensions.</span>
    </div>

    <!-- TOP BAR (avatar and menu removed) -->
    <div class="user-topbar">
      <div class="user-search-wrap">
        <span class="u-search-icon">🔍</span>
        <input type="text" id="uSearchInput" placeholder="Search Book / Author / ISBN" oninput="uHandleSearch()">
      </div>
      <button class="notif-bell" id="notifBell" onclick="toggleNotifPanel()" aria-label="Notifications">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <span class="notif-badge" id="notifBadge"></span>
      </button>
    </div>

    <!-- NOTIFICATION PANEL -->
    <div class="notif-overlay" id="notifOverlay" onclick="closeNotifPanel()"></div>
    <div class="notif-panel" id="notifPanel">
      <div class="notif-panel-header">
        <span class="notif-panel-title">Notifications</span>
        <button class="notif-mark-all" id="notifMarkAll" onclick="markAllRead()">Mark all read</button>
      </div>
      <div class="notif-list" id="notifList">
        <div class="notif-empty">No notifications yet.</div>
      </div>
    </div>

    <!-- Notification detail popup (overlaps panel) -->
    <div class="notif-popup" id="notifPopup">
      <div class="notif-popup-header">
        <button class="notif-popup-back" onclick="closeNotifDetail()">← Back</button>
        <button class="notif-popup-close" onclick="closeNotifDetail()" aria-label="Close">✕</button>
      </div>
      <div class="notif-popup-body">
        <span class="notif-popup-icon" id="ndIcon"></span>
        <div class="notif-popup-title" id="ndTitle"></div>
        <div class="notif-popup-msg" id="ndMsg"></div>
        <div class="notif-popup-time" id="ndTime"></div>
      </div>
    </div>

    <!-- BOOKSTORE VIEW -->
    <div id="uViewBookstore" class="u-view active">
      <div class="u-bookstore-header">
        <div>
          <div class="u-bookstore-title">Browse Books</div>
          <div class="u-bookstore-sub">Explore our collection — click any book to view details and borrow</div>
        </div>
        <div class="u-books-count-badge" id="uBooksCountBadge"></div>
      </div>
      <div class="u-genre-tabs-row">
        <div class="u-genre-tabs" id="uGenreTabs"></div>
      </div>
      <div class="u-books-grid" id="uBooksGrid"></div>
    </div>

    <!-- NOW READING VIEW -->
    <div id="uViewReading" class="u-view">
      <div class="u-view-title">Now Reading</div>
      <div class="u-reading-list" id="uReadingList"></div>
    </div>

    <!-- FAVORITES VIEW -->
    <div id="uViewFavorites" class="u-view">
      <div class="u-view-title">❤️ Favorites</div>
      <div class="u-books-grid" id="uFavoritesGrid"></div>
    </div>

    <!-- AUTHORS VIEW -->
    <div id="uViewAuthors" class="u-view">
      <div class="u-view-title">Authors</div>
      <div class="u-authors-grid" id="uAuthorsGrid"></div>
    </div>

  </div>
</div>

<!-- BOOK DETAIL MODAL -->
<div class="modal-overlay" id="uDetailModal">
  <div class="modal u-book-modal u-book-modal-wide">
    <div class="modal-header">
      <div class="modal-title" id="uDetailTitle"></div>
      <button class="modal-close" onclick="closeModal('uDetailModal')">✕</button>
    </div>

    <!-- Two-column: Physical + Digital -->
    <div class="u-detail-columns">

      <!-- Physical Copy -->
      <div class="u-detail-col">
        <div class="u-col-label">📦 Physical Copy</div>
        <div id="uDetailCover" class="detail-cover"></div>
        <div class="detail-info">
          <p><strong>Author:</strong> <span id="uDetailAuthor"></span></p>
          <p><strong>Genre:</strong> <span id="uDetailGenre"></span></p>
          <p><strong>Year:</strong> <span id="uDetailYear"></span></p>
          <p><strong>ISBN:</strong> <span id="uDetailIsbn"></span></p>
          <p><strong>Status:</strong> <span id="uDetailStatus"></span></p>
          <p><strong>Copies:</strong> <span id="uDetailCopies"></span></p>
          <p><strong>Rating:</strong> <span id="uDetailRatingRow" style="display:none;"></span></p>
        </div>
        <div class="detail-desc" id="uDetailDesc"></div>
        <div class="detail-actions detail-actions--mt">
          <button class="btn-primary" id="uBorrowBtn" onclick="borrowBook()">Borrow This Book</button>
        </div>
      </div>

      <!-- Digital Copy -->
      <div class="u-detail-col u-detail-digital">
        <div class="u-col-label">💻 Digital Copy</div>
        <div class="u-digital-cover" id="uDigitalCover"></div>
        <div class="detail-info">
          <p><strong>Format:</strong> PDF / eBook</p>
          <p><strong>Access:</strong> Instant Download</p>
          <p><strong>DRM:</strong> Library Edition</p>
          <p><strong>Size:</strong> ~2–5 MB</p>
        </div>
        <div class="detail-desc">Read this book on any device. Download and enjoy offline at any time.</div>
        <div class="detail-actions detail-actions--mt14">
          <button class="btn-primary" onclick="downloadBook()">⬇ Download</button>
        </div>
      </div>
    </div>

    <!-- Favorite heart -->
    <div class="u-fav-row">
      <button class="u-heart-btn" id="uHeartBtn" onclick="toggleFavorite()">🤍</button>
      <span class="u-fav-label" id="uFavLabel">Add to Favorites</span>
    </div>

    <!-- Comments Section -->
    <div class="u-comments-section">
      <div class="u-comments-title">💬 Reviews &amp; Comments</div>
      <div class="u-comments-list" id="uCommentsList"></div>
      <div class="u-comment-form">
        <textarea id="uCommentInput" placeholder="Write your review..." rows="3"></textarea>
        <button class="btn-primary u-submit-comment" onclick="submitComment()">Post Review</button>
      </div>
    </div>

    <div class="detail-actions detail-actions--mt12">
      <button class="btn-sec" onclick="closeModal('uDetailModal')">Close</button>
    </div>
  </div>
</div>

<!-- BORROW REQUEST MODAL -->
<div class="modal-overlay" id="uBorrowModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">📖 Borrow This Book</div>
      <button class="modal-close" onclick="closeModal('uBorrowModal')">✕</button>
    </div>
    <div id="uBorrowModalBookInfo" class="modal-book-info"></div>
    <div id="uBorrowFeeInfo" class="borrow-fee-info"></div>
    <div class="form-group">
      <label>Your Username <span class="form-required">*</span></label>
      <input type="text" id="uBorrowUsername" placeholder="Enter your username to confirm">
    </div>
    <div class="form-group">
      <label>Borrow Duration <span class="form-required">*</span> <span class="form-hint">(max 7 days)</span></label>
      <select id="uBorrowDaysSelect" class="extend-select">
        <option value="0">— Select duration —</option>
        <option value="1">1 day</option>
        <option value="2">2 days</option>
        <option value="3">3 days</option>
        <option value="4">4 days</option>
        <option value="5">5 days</option>
        <option value="6">6 days</option>
        <option value="7">7 days</option>
      </select>
      <input type="hidden" id="uBorrowDays" value="0">
    </div>
    <div class="form-group">
      <label>Book Language <span class="form-required">*</span></label>
      <div class="lang-selector">
        <label class="lang-option" id="uBorrowLangEnLabel">
          <input type="radio" name="uBorrowLang" id="uBorrowLangEn" value="english" checked>
          <span class="lang-option-text">🇬🇧 English</span>
        </label>
        <label class="lang-option" id="uBorrowLangNpLabel">
          <input type="radio" name="uBorrowLang" id="uBorrowLangNp" value="nepali">
          <span class="lang-option-text">🇳🇵 नेपाली (Nepali)</span>
        </label>
      </div>
      <p class="lang-help">Select the language version of the physical book you'd like to borrow.</p>
    </div>
    <div class="form-group">
      <label>Note <span class="form-hint">(optional)</span></label>
      <textarea id="uBorrowNote" placeholder="Any note for the admin? (optional)" rows="2"></textarea>
    </div>
    <div id="uBorrowError" class="u-form-error" style="display:none;"></div>
    <div id="uBorrowConfirmStep" class="borrow-confirm-box" style="display:none;"></div>
    <div class="detail-actions" id="uBorrowFormActions">
      <button class="btn-primary" onclick="submitBorrowRequest()">Submit Request</button>
      <button class="btn-sec" onclick="closeModal('uBorrowModal')">Cancel</button>
    </div>
    <div class="detail-actions" id="uBorrowConfirmActions" style="display:none;">
      <button class="btn-primary" onclick="confirmBorrowRequest()">
        <span id="uBorrowConfirmLabel">Confirm Borrow</span>
        <span id="uBorrowConfirmSpinner" style="display:none;">Submitting…</span>
      </button>
      <button class="btn-sec" onclick="cancelBorrowConfirm()">Go Back</button>
    </div>
  </div>
</div>

<!-- PDF PURCHASE CONFIRM MODAL -->
<div class="modal-overlay" id="uPdfPurchaseModal">
  <div class="modal modal-sm">
    <button class="modal-close-corner" onclick="closeModal('uPdfPurchaseModal')">✕</button>
    <div class="modal-icon">📄</div>
    <div class="modal-title">Buy Digital Book</div>
    <p class="pdf-intro-text">
      <strong id="uPdfBookTitle"></strong><br>
      This is a permanent digital download.
    </p>
    <!-- Purchase mode: price + balance -->
    <div id="uPdfFeeSection" class="pdf-fee-section">
      <div class="pdf-fee-row">
        <span class="pdf-fee-label">Price</span>
        <strong id="uPdfFee">Rs 400</strong>
      </div>
      <div class="pdf-fee-row">
        <span class="pdf-fee-label">Current balance</span>
        <strong id="uPdfBalance" class="pdf-balance"></strong>
      </div>
      <div class="pdf-fee-divider"></div>
      <div class="pdf-fee-row">
        <span class="pdf-fee-label">Balance after purchase</span>
        <strong id="uPdfBalanceAfter" class="pdf-balance-after"></strong>
      </div>
    </div>
    <!-- Re-download mode: already owned notice -->
    <div id="uPdfOwnedNotice" class="pdf-owned-notice" style="display:none;">
      ✅ You already own this digital book — re-download is free.
    </div>
    <div id="uPdfAffordWarn" class="pdf-afford-warn" style="display:none;">⚠️ Insufficient balance to purchase this book.</div>
    <div class="pdf-lang-section">
      <p class="pdf-lang-title">PDF Language</p>
      <div class="pdf-lang-options">
        <label class="pdf-lang-label" id="uPdfLangEnLabel">
          <input type="radio" name="uPdfLang" id="uPdfLangEn" value="english" checked class="pdf-lang-radio">
          🇬🇧 English
        </label>
        <label class="pdf-lang-label" id="uPdfLangNpLabel">
          <input type="radio" name="uPdfLang" id="uPdfLangNp" value="nepali" class="pdf-lang-radio">
          🇳🇵 नेपाली
        </label>
      </div>
    </div>
    <div id="uPdfActionsArea">
      <div class="modal-actions" id="uPdfDefaultActions">
        <button class="btn-primary" id="uPdfConfirmBtn" onclick="showPdfConfirmStep()">Buy &amp; Download</button>
        <button class="btn-primary" id="uPdfRedownloadBtn" onclick="confirmPdfRedownload()" style="display:none;">⬇ Download</button>
        <button class="btn-sec" onclick="closeModal('uPdfPurchaseModal')">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- FINE BREAKDOWN MODAL -->
<div class="modal-overlay" id="uFineBreakdownModal">
  <div class="modal modal-sm">
    <button class="modal-close-corner" onclick="closeModal('uFineBreakdownModal')">✕</button>
    <div class="modal-icon">⚠️</div>
    <div class="modal-title">Fines Charged</div>
    <p class="modal-desc-sm">The following amounts have been deducted from your wallet.</p>
    <div id="uFineBreakdownList"></div>
    <div class="modal-actions modal-actions--mt16">
      <button class="btn-primary" onclick="closeModal('uFineBreakdownModal')">OK</button>
    </div>
  </div>
</div>

<!-- DOWNLOAD SUCCESS POPUP — kept for compatibility, now handled by PDF print -->
<div class="modal-overlay" id="uDownloadPopup" style="display:none;">
  <div class="modal modal-sm">
    <div class="modal-icon">📄</div>
    <div class="modal-title" id="uDownloadPopupTitle"></div>
    <p class="modal-warning modal-warning--dark">Your digital copy is ready. In the print dialog, choose <strong>Save as PDF</strong> to download.</p>
    <div class="modal-actions">
      <button class="btn-primary" onclick="closeModal('uDownloadPopup')">Okay</button>
    </div>
  </div>
</div>

<!-- PROFILE MODAL -->
<div class="modal-overlay" id="uProfileModal">
  <div class="modal u-profile-modal">
    <div class="modal-header">
      <div class="modal-title">👤 My Profile</div>
      <button class="modal-close" onclick="closeModal('uProfileModal')">✕</button>
    </div>

    <!-- Profile avatar -->
    <div class="u-profile-avatar-wrap">
      <div class="u-profile-big-avatar" id="uProfileBigAvatar"></div>
      <div class="u-profile-avatar-name" id="uProfileAvatarName"></div>
      <div class="u-profile-avatar-email" id="uProfileAvatarEmail"></div>
    </div>

    <!-- Balance + Logout row -->
    <div class="u-profile-meta-row">
      <div class="u-profile-balance-card">
        <span class="u-profile-balance-label">💰 Wallet Balance</span>
        <div class="u-balance-row">
          <span class="u-profile-balance-amount" id="uSidebarBalance">Rs —</span>
          <button class="u-topup-trigger" onclick="toggleTopUpForm()" id="uTopupTrigger">+ Top Up</button>
        </div>
      </div>
      <button class="u-profile-logout-btn" onclick="closeModal('uProfileModal'); openLogoutConfirm();">
        🚪 Logout
      </button>
    </div>

    <!-- Inline Top-Up Form -->
    <div id="uTopupForm" style="display:none;">
      <div class="u-topup-presets">
        <button class="u-topup-preset" onclick="setTopupAmount(500)">Rs 500</button>
        <button class="u-topup-preset" onclick="setTopupAmount(1000)">Rs 1,000</button>
        <button class="u-topup-preset" onclick="setTopupAmount(2000)">Rs 2,000</button>
        <button class="u-topup-preset" onclick="setTopupAmount(5000)">Rs 5,000</button>
      </div>
      <div class="u-topup-input-row">
        <input type="number" id="uTopupAmount" class="u-topup-input" placeholder="Custom amount" min="100" max="100000" step="100">
        <button class="u-topup-confirm" onclick="submitTopUp()">Add to Wallet</button>
      </div>
      <div id="uTopupMsg" class="u-topup-msg" style="display:none;"></div>
    </div>

    <!-- Tabs -->
    <div class="u-profile-tabs">
      <button class="u-profile-tab active" id="uTabInfo" onclick="switchProfileTab('info')">Edit Profile</button>
      <button class="u-profile-tab" id="uTabPassword" onclick="switchProfileTab('password')">Change Password</button>
      <button class="u-profile-tab" id="uTabHistory" onclick="switchProfileTab('history')">📖 History</button>
    </div>

    <!-- Edit Profile Tab -->
    <div id="uProfileTabInfo" class="u-profile-tab-content">
      <div class="form-group">
        <label>First Name</label>
        <input type="text" id="uProfFirstName" placeholder="First name">
      </div>
      <div class="form-group">
        <label>Last Name</label>
        <input type="text" id="uProfLastName" placeholder="Last name">
      </div>
      <div class="form-group">
        <label>Username</label>
        <input type="text" id="uProfUsername" placeholder="Username">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" id="uProfEmail" placeholder="Email address">
      </div>
      <div class="u-profile-msg" id="uProfileInfoMsg"></div>
      <div class="detail-actions detail-actions--mt8">
        <button class="btn-primary" onclick="saveProfile()">Save Changes</button>
        <button class="btn-sec" onclick="closeModal('uProfileModal')">Cancel</button>
      </div>
    </div>

    <!-- Change Password Tab -->
    <div id="uProfileTabPassword" class="u-profile-tab-content" style="display:none;">
      <div class="form-group">
        <label>Current Password <span class="form-required">*</span></label>
        <div class="u-pw-wrap">
          <input type="password" id="uProfOldPw" placeholder="Enter your current password">
          <span class="u-pw-toggle" onclick="togglePw('uProfOldPw', this)">👁</span>
        </div>
      </div>
      <div class="form-group">
        <label>New Password <span class="form-required">*</span></label>
        <div class="u-pw-wrap">
          <input type="password" id="uProfNewPw" placeholder="At least 6 characters">
          <span class="u-pw-toggle" onclick="togglePw('uProfNewPw', this)">👁</span>
        </div>
      </div>
      <div class="form-group">
        <label>Confirm New Password <span class="form-required">*</span></label>
        <div class="u-pw-wrap">
          <input type="password" id="uProfConfirmPw" placeholder="Repeat new password">
          <span class="u-pw-toggle" onclick="togglePw('uProfConfirmPw', this)">👁</span>
        </div>
      </div>
      <div class="u-profile-msg" id="uProfilePwMsg"></div>
      <div class="detail-actions detail-actions--mt8">
        <button class="btn-primary" onclick="savePassword()">Update Password</button>
        <button class="btn-sec" onclick="closeModal('uProfileModal')">Cancel</button>
      </div>
    </div>

    <!-- Reading History Tab -->
    <div id="uProfileTabHistory" class="u-profile-tab-content" style="display:none;">
      <div id="uHistoryList" class="profile-history-list"></div>
    </div>

  </div>
</div>

<!-- RATING MODAL -->
<div class="modal-overlay" id="uRatingModal">
  <div class="modal modal-sm">
    <button class="modal-close-corner" onclick="closeModal('uRatingModal')">✕</button>
    <div class="modal-icon">⭐</div>
    <div class="modal-title">Rate This Book</div>
    <div id="uRatingBookTitle" class="rating-title"></div>
    <div id="uStarPicker" class="star-picker"></div>
    <div class="form-group rating-form-group">
      <label class="rating-label">Leave a review <span class="rating-hint">(optional)</span></label>
      <textarea id="uRatingReview" placeholder="What did you think of this book?" rows="3" class="rating-textarea"></textarea>
    </div>
    <div id="uRatingMsg" class="rating-msg" style="display:none;"></div>
    <div class="modal-actions">
      <button class="btn-primary" onclick="submitRating()">Submit Rating</button>
      <button class="btn-sec" onclick="closeModal('uRatingModal')">Skip</button>
    </div>
  </div>
</div>

<!-- WELCOME MODAL -->
<div class="modal-overlay" id="uWelcomeModal">
  <div class="modal modal-sm">
    <div class="modal-icon">🎉</div>
    <div class="modal-title" id="uWelcomeName"></div>
    <p class="modal-warning modal-warning--muted">You have successfully logged in. Welcome to TechGiants Library!</p>
    <div class="modal-actions">
      <button class="btn-primary" onclick="closeModal('uWelcomeModal')">Let's Go!</button>
    </div>
  </div>
</div>

<!-- BORROW REQUEST DENIED NOTIFICATION MODAL -->
<div class="modal-overlay" id="uRejectedModal">
  <div class="modal modal-sm">
    <button class="modal-close-corner" onclick="closeModal('uRejectedModal')">✕</button>
    <div class="modal-icon">❌</div>
    <div class="modal-title">Borrow Request Denied</div>
    <div id="uRejectedList" class="rejected-list"></div>
    <div class="modal-actions">
      <button class="btn-primary" onclick="closeModal('uRejectedModal')">OK, Got It</button>
    </div>
  </div>
</div>

<!-- LOGOUT CONFIRM MODAL -->
<div class="modal-overlay" id="uLogoutModal">
  <div class="modal modal-sm">
    <button class="modal-close-corner" onclick="closeModal('uLogoutModal')">✕</button>
    <div class="modal-icon">🚪</div>
    <div class="modal-title">Confirm Logout</div>
    <p class="modal-warning">Are you sure you want to log out of your account?</p>
    <div class="modal-actions">
      <button class="btn-danger" onclick="window.location.href='login.php?logout=1'">Yes, Logout</button>
      <button class="btn-sec" onclick="closeModal('uLogoutModal')">Cancel</button>
    </div>
  </div>
</div>

<!-- RETURN CONDITION MODAL -->
<div class="modal-overlay" id="uReturnModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">📬 Return Book</div>
      <button class="modal-close" onclick="closeModal('uReturnModal')">✕</button>
    </div>
    <div id="uReturnModalBookInfo" class="return-book-info"></div>
    <div id="uReturnFinePreview" class="return-fine-preview" style="display:none;"></div>
    <div class="form-group">
      <label>Book Condition <span class="form-required">*</span></label>
      <select id="uReturnCondition" onchange="handleConditionChange()" class="return-select">
        <option value="">— Select condition —</option>
        <option value="excellent">⭐ Excellent — Like new</option>
        <option value="good">👍 Good — Minor wear</option>
        <option value="fair">😐 Fair — Some wear but readable</option>
        <option value="bad">⚠️ Bad — Noticeable damage</option>
        <option value="damaged">❌ Damaged — Significant damage</option>
      </select>
    </div>
    <div id="uReturnDamageFine" class="return-damage-fine" style="display:none;"></div>
    <div class="form-group" id="uReturnDescGroup">
      <label id="uReturnDescLabel">Description <span class="form-hint">(optional)</span></label>
      <textarea id="uReturnDescription" placeholder="Describe the condition of the book..." rows="3" class="return-textarea"></textarea>
    </div>
    <div id="uReturnError" class="u-form-error" style="display:none;"></div>
    <div class="detail-actions">
      <button class="btn-primary" onclick="submitReturn()">Confirm Return</button>
      <button class="btn-danger" onclick="openReportLostModal()">🚨 Report as Lost</button>
      <button class="btn-sec" onclick="closeModal('uReturnModal')">Cancel</button>
    </div>
  </div>
</div>

<!-- REPORT LOST MODAL -->
<div class="modal-overlay" id="uLostModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">🚨 Report Book as Lost</div>
      <button class="modal-close" onclick="closeModal('uLostModal')">✕</button>
    </div>
    <div id="uLostModalBookInfo" class="lost-book-info"></div>
    <div id="uLostCostInfo" class="lost-cost-info"></div>
    <div id="uLostError" class="u-form-error" style="display:none;"></div>
    <div class="detail-actions">
      <button class="btn-danger" onclick="confirmReportLost()">🚨 Report to Admin</button>
      <button class="btn-sec" onclick="closeModal('uLostModal')">Cancel</button>
    </div>
  </div>
</div>

<?php require 'chatbot_widget.php'; ?>

<!-- EXTEND BORROW MODAL -->
<div class="modal-overlay" id="uExtendModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Extend Borrow Period</div>
      <button class="modal-close" onclick="closeModal('uExtendModal')">✕</button>
    </div>
    <div id="uExtendModalBookInfo" class="extend-book-info"></div>
    <div id="uExtendCurrentDue" class="extend-current-due"></div>
    <div id="uExtendAllowance" class="extend-allowance"></div>
    <div class="form-group">
      <label>How many extra days?</label>
      <select id="uExtendDaysSelect" class="extend-select">
        <option value="">Select days...</option>
      </select>
    </div>
    <div class="form-group">
      <label>Reason for extending <span class="form-required">*</span></label>
      <textarea id="uExtendReason" placeholder="Why do you need more time? (e.g. Haven't finished yet, travelling, etc.)" rows="3" class="extend-textarea"></textarea>
    </div>
    <div id="uExtendError"   class="u-form-error" style="display:none;"></div>
    <div id="uExtendSuccess" class="u-form-success" style="display:none;"></div>
    <div id="uExtendActionsArea">
      <div class="detail-actions">
        <button class="btn-primary" id="uExtendSubmitBtn" onclick="submitExtend()">Confirm Extension</button>
        <button class="btn-sec" onclick="closeModal('uExtendModal')">Cancel</button>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>
<script>
var _showWelcome = <?php echo $showWelcome; ?>;
var _welcomeName = <?php echo json_encode($user['firstName']); ?>;
var _csrfToken   = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
</script>
<script src="user_dashboard.js?v=<?php echo filemtime('user_dashboard.js'); ?>"></script>

<script>
// ── Notification System ───────────────────────────────────────────────────────
(function () {
  var _panelOpen = false;
  var _allNotifs = [];

  var ICONS = {
    borrow_approved: '✅',
    borrow_rejected: '❌',
    book_returned:   '📚',
    borrow_extended: '📅',
    wallet_topup:    '💰',
  };

  function fetchNotifications() {
    fetch('/LMS_v19/notifications_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'fetch' })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.notifications) return;
      _allNotifs = data.notifications;
      updateBadge(data.unread_count);
      if (_panelOpen) renderList(_allNotifs);
    })
    .catch(function () {});
  }

  function updateBadge(count) {
    var badge = document.getElementById('notifBadge');
    if (!badge) return;
    if (count > 0) {
      badge.textContent = count > 99 ? '99+' : count;
      badge.style.display = 'flex';
    } else {
      badge.style.display = 'none';
    }
  }

  function renderList(notifs) {
    var list = document.getElementById('notifList');
    if (!list) return;
    if (!notifs || notifs.length === 0) {
      list.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
      return;
    }
    var html = '';
    notifs.forEach(function (n) {
      var icon = ICONS[n.type] || '🔔';
      var cls  = n.is_read ? 'notif-item' : 'notif-item notif-item--unread';
      var time = formatTime(n.created_at);
      html += '<div class="' + cls + '" data-id="' + n.id + '" onclick="notifItemClick(this)">' +
        '<span class="notif-icon">' + icon + '</span>' +
        '<div class="notif-body">' +
          '<div class="notif-title">' + esc(n.title) + '</div>' +
          '<div class="notif-msg">'   + esc(n.message) + '</div>' +
          '<div class="notif-time">'  + time + '</div>' +
        '</div>' +
      '</div>';
    });
    list.innerHTML = html;
  }

  function formatTime(dt) {
    if (!dt) return '';
    var d = new Date(dt.replace(' ', 'T'));
    if (isNaN(d)) return dt;
    var now  = new Date();
    var diff = Math.floor((now - d) / 1000);
    if (diff < 60)   return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
  }

  function esc(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  window.notifItemClick = function (el) {
    var id = parseInt(el.dataset.id, 10);
    var notif = _allNotifs.find(function (n) { return n.id === id; });
    if (!notif) return;

    // Populate and show the overlapping popup
    var icon = ICONS[notif.type] || '🔔';
    document.getElementById('ndIcon').textContent  = icon;
    document.getElementById('ndTitle').textContent = notif.title;
    document.getElementById('ndMsg').textContent   = notif.message;
    document.getElementById('ndTime').textContent  = formatTime(notif.created_at);
    document.getElementById('notifPopup').classList.add('open');

    // Mark as read
    if (!notif.is_read) {
      el.classList.remove('notif-item--unread');
      notif.is_read = true;
      fetch('/LMS_v19/notifications_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'markRead', ids: [id] })
      });
      var still = _allNotifs.filter(function (n) { return !n.is_read; }).length;
      updateBadge(still);
    }
  };

  window.closeNotifDetail = function () {
    document.getElementById('notifPopup').classList.remove('open');
  };

  window.markAllRead = function () {
    fetch('/LMS_v19/notifications_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'markAllRead' })
    }).then(function () {
      _allNotifs.forEach(function (n) { n.is_read = true; });
      updateBadge(0);
      renderList(_allNotifs);
    });
  };

  window.toggleNotifPanel = function () {
    _panelOpen ? closeNotifPanel() : openNotifPanel();
  };

  window.openNotifPanel = function () {
    _panelOpen = true;
    document.getElementById('notifPanel').classList.add('notif-panel--open');
    document.getElementById('notifOverlay').classList.add('notif-overlay--visible');
    renderList(_allNotifs);
    // Mark all as read when panel opens
    var unreadIds = _allNotifs.filter(function (n) { return !n.is_read; }).map(function (n) { return n.id; });
    if (unreadIds.length > 0) {
      fetch('/LMS_v19/notifications_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'markRead', ids: unreadIds })
      }).then(function () {
        _allNotifs.forEach(function (n) { n.is_read = true; });
        updateBadge(0);
        renderList(_allNotifs);
      });
    }
  };

  window.closeNotifPanel = function () {
    _panelOpen = false;
    var p = document.getElementById('notifPanel');
    var o = document.getElementById('notifOverlay');
    if (p) p.classList.remove('notif-panel--open');
    if (o) o.classList.remove('notif-overlay--visible');
  };

  // Poll every 5 seconds
  fetchNotifications();
  setInterval(fetchNotifications, 5000);
})();
</script>

</body>
</html>
