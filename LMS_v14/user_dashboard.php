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
      <!-- Logout ABOVE user info -->
      <div class="u-logout-btn" onclick="openLogoutConfirm()">
        🚪 Logout
      </div>
      <div class="u-user-pill" onclick="openProfileModal()" title="Edit your profile">
        <div class="u-sidebar-avatar" id="uSidebarAvatar">
          <?php echo strtoupper($user['firstName'][0]); ?>
        </div>
        <div class="u-sidebar-user-info">
          <div class="u-sidebar-name" id="uSidebarName"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName'], ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="u-sidebar-role">Member ✏️</div>
        </div>
      </div>
    </div>
  </div>

  <!-- USER MAIN -->
  <div class="user-main">

    <!-- TOP BAR (avatar and menu removed) -->
    <div class="user-topbar">
      <div class="user-search-wrap">
        <span class="u-search-icon">🔍</span>
        <input type="text" id="uSearchInput" placeholder="Search Book / Author / ISBN" oninput="uHandleSearch()">
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
        </div>
        <div class="detail-desc" id="uDetailDesc"></div>
        <div class="detail-actions" style="margin-top:16px;">
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
        <div class="detail-actions" style="margin-top:16px;">
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

    <div class="detail-actions" style="margin-top:12px;">
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
    <div id="uBorrowModalBookInfo" style="background:var(--bg);border-radius:10px;padding:12px 16px;margin-bottom:18px;font-family:var(--font-body);font-size:0.9rem;color:var(--text-secondary);"></div>
    <div class="form-group">
      <label>Your Username <span style="color:#e53e3e;">*</span></label>
      <input type="text" id="uBorrowUsername" placeholder="Enter your username to confirm">
    </div>
    <div class="form-group">
      <label>Borrow Duration <span style="color:#e53e3e;">*</span> <span style="font-weight:400;color:#888;">(max 7 days)</span></label>
      <select id="uBorrowDaysSelect" style="width:100%;padding:10px 14px;border-radius:10px;border:1.5px solid var(--border);background:var(--surface);color:var(--text-primary);font-family:var(--font-body);font-size:0.9rem;cursor:pointer;appearance:auto;">
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
      <label>Note <span style="font-weight:400;color:#888;">(optional)</span></label>
      <textarea id="uBorrowNote" placeholder="Any note for the admin? (optional)" rows="2"></textarea>
    </div>
    <div id="uBorrowError" style="color:#e53e3e;font-family:var(--font-body);font-size:0.87rem;margin-bottom:10px;display:none;"></div>
    <div class="detail-actions">
      <button class="btn-primary" onclick="submitBorrowRequest()">Submit Request</button>
      <button class="btn-sec" onclick="closeModal('uBorrowModal')">Cancel</button>
    </div>
  </div>
</div>

<!-- DOWNLOAD SUCCESS POPUP — kept for compatibility, now handled by PDF print -->
<div class="modal-overlay" id="uDownloadPopup" style="display:none;">
  <div class="modal modal-sm">
    <div class="modal-icon">📄</div>
    <div class="modal-title" id="uDownloadPopupTitle"></div>
    <p class="modal-warning" style="color:#555;margin-top:8px;">Your digital copy is ready. In the print dialog, choose <strong>Save as PDF</strong> to download.</p>
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

    <!-- Tabs -->
    <div class="u-profile-tabs">
      <button class="u-profile-tab active" id="uTabInfo" onclick="switchProfileTab('info')">Edit Profile</button>
      <button class="u-profile-tab" id="uTabPassword" onclick="switchProfileTab('password')">Change Password</button>
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
      <div class="detail-actions" style="margin-top:8px;">
        <button class="btn-primary" onclick="saveProfile()">Save Changes</button>
        <button class="btn-sec" onclick="closeModal('uProfileModal')">Cancel</button>
      </div>
    </div>

    <!-- Change Password Tab -->
    <div id="uProfileTabPassword" class="u-profile-tab-content" style="display:none;">
      <div class="form-group">
        <label>Current Password <span style="color:#e53e3e;">*</span></label>
        <div class="u-pw-wrap">
          <input type="password" id="uProfOldPw" placeholder="Enter your current password">
          <span class="u-pw-toggle" onclick="togglePw('uProfOldPw', this)">👁</span>
        </div>
      </div>
      <div class="form-group">
        <label>New Password <span style="color:#e53e3e;">*</span></label>
        <div class="u-pw-wrap">
          <input type="password" id="uProfNewPw" placeholder="At least 6 characters">
          <span class="u-pw-toggle" onclick="togglePw('uProfNewPw', this)">👁</span>
        </div>
      </div>
      <div class="form-group">
        <label>Confirm New Password <span style="color:#e53e3e;">*</span></label>
        <div class="u-pw-wrap">
          <input type="password" id="uProfConfirmPw" placeholder="Repeat new password">
          <span class="u-pw-toggle" onclick="togglePw('uProfConfirmPw', this)">👁</span>
        </div>
      </div>
      <div class="u-profile-msg" id="uProfilePwMsg"></div>
      <div class="detail-actions" style="margin-top:8px;">
        <button class="btn-primary" onclick="savePassword()">Update Password</button>
        <button class="btn-sec" onclick="closeModal('uProfileModal')">Cancel</button>
      </div>
    </div>

  </div>
</div>

<!-- WELCOME MODAL -->
<div class="modal-overlay" id="uWelcomeModal">
  <div class="modal modal-sm">
    <div class="modal-icon">🎉</div>
    <div class="modal-title" id="uWelcomeName"></div>
    <p class="modal-warning" style="color:var(--text-secondary);margin-top:8px;font-size:0.95rem;">You have successfully logged in. Welcome to TechGiants Library!</p>
    <div class="modal-actions">
      <button class="btn-primary" onclick="closeModal('uWelcomeModal')">Let's Go!</button>
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
    <div id="uReturnModalBookInfo" style="background:var(--bg);border-radius:10px;padding:12px 16px;margin-bottom:18px;font-family:var(--font-body);font-size:0.9rem;color:var(--text-secondary);"></div>
    <div class="form-group">
      <label>Book Condition <span style="color:#e53e3e;">*</span></label>
      <select id="uReturnCondition" onchange="handleConditionChange()" style="width:100%;padding:10px 14px;border-radius:10px;border:1.5px solid var(--border);background:var(--surface);color:var(--text-primary);font-family:var(--font-body);font-size:0.9rem;cursor:pointer;appearance:auto;">
        <option value="">— Select condition —</option>
        <option value="excellent">⭐ Excellent — Like new</option>
        <option value="good">👍 Good — Minor wear</option>
        <option value="fair">😐 Fair — Some wear but readable</option>
        <option value="bad">⚠️ Bad — Noticeable damage</option>
        <option value="damaged">❌ Damaged — Significant damage</option>
      </select>
    </div>
    <div class="form-group" id="uReturnDescGroup">
      <label id="uReturnDescLabel">Description <span style="color:#888;font-weight:400;">(optional)</span></label>
      <textarea id="uReturnDescription" placeholder="Describe the condition of the book..." rows="3" style="width:100%;resize:vertical;"></textarea>
    </div>
    <div id="uReturnError" style="color:#e53e3e;font-family:var(--font-body);font-size:0.87rem;margin-bottom:10px;display:none;"></div>
    <div class="detail-actions">
      <button class="btn-primary" onclick="submitReturn()">Confirm Return</button>
      <button class="btn-sec" onclick="closeModal('uReturnModal')">Cancel</button>
    </div>
  </div>
</div>

<!-- EXTEND BORROW MODAL -->
<div class="modal-overlay" id="uExtendModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Extend Borrow Period</div>
      <button class="modal-close" onclick="closeModal('uExtendModal')">✕</button>
    </div>
    <div id="uExtendModalBookInfo" style="background:var(--bg);border-radius:10px;padding:12px 16px;margin-bottom:12px;font-family:var(--font-body);font-size:0.9rem;color:var(--text-secondary);"></div>
    <div id="uExtendCurrentDue" style="font-size:0.88rem;color:var(--text-secondary);margin-bottom:6px;padding:0 2px;"></div>
    <div id="uExtendAllowance" style="font-size:0.88rem;background:#ebf8ff;color:#2b6cb0;border-radius:8px;padding:9px 14px;margin-bottom:18px;"></div>
    <div class="form-group">
      <label>How many extra days?</label>
      <select id="uExtendDaysSelect" style="width:100%;padding:10px 14px;border-radius:10px;border:1.5px solid var(--border);background:var(--surface);color:var(--text-primary);font-family:var(--font-body);font-size:0.9rem;cursor:pointer;appearance:auto;">
        <option value="">Select days...</option>
      </select>
    </div>
    <div class="form-group">
      <label>Reason for extending <span style="color:#e53e3e;">*</span></label>
      <textarea id="uExtendReason" placeholder="Why do you need more time? (e.g. Haven't finished yet, travelling, etc.)" rows="3" style="width:100%;resize:vertical;"></textarea>
    </div>
    <div id="uExtendError"   style="color:#e53e3e;font-family:var(--font-body);font-size:0.87rem;margin-bottom:10px;display:none;"></div>
    <div id="uExtendSuccess" style="color:#27ae60;font-family:var(--font-body);font-size:0.87rem;margin-bottom:10px;display:none;font-weight:600;"></div>
    <div class="detail-actions">
      <button class="btn-primary" id="uExtendSubmitBtn" onclick="submitExtend()">Confirm Extension</button>
      <button class="btn-sec" onclick="closeModal('uExtendModal')">Cancel</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>
<script>
var _showWelcome = <?php echo $showWelcome; ?>;
var _welcomeName = <?php echo json_encode($user['firstName']); ?>;
</script>
<script src="user_dashboard.js"></script>

</body>
</html>
