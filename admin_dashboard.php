<?php
require 'session.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

defined('DAMAGE_FINE_FAIR') || define('DAMAGE_FINE_FAIR', 200.00);
defined('DAMAGE_FINE_BAD')  || define('DAMAGE_FINE_BAD',  500.00);

$user = $_SESSION['user'];
$showWelcome = isset($_GET['welcome']) ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TechGiants – Admin Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>
<body>

<div class="app-wrapper">

  <!-- SIDEBAR -->
  <div class="sidebar">
    <div class="sidebar-logo">📚 TechGiants</div>

    <div id="navDashboard" class="nav-item active" onclick="switchView('Dashboard')">
      <span class="nav-icon">🏠</span> Dashboard
    </div>
    <div id="navLibrary" class="nav-item" onclick="switchView('Library')">
      <span class="nav-icon">📚</span> My Library
    </div>
    <div id="navAdmin" class="nav-item" onclick="switchView('Admin')">
      <span class="nav-icon">⚙️</span> Admin Panel
    </div>
    <div id="navBorrowedBooks" class="nav-item" onclick="switchView('BorrowedBooks')">
      <span class="nav-icon">📖</span> Borrowed Books
    </div>
    <div id="navReviews" class="nav-item" onclick="switchView('Reviews')">
      <span class="nav-icon">⭐</span> Reviews
    </div>
    <div id="navPendingReturns" class="nav-item" onclick="switchView('PendingReturns')">
      <span class="nav-icon">🔄</span> Pending Returns
    </div>
    <div id="navTransactions" class="nav-item" onclick="openTransactionsModal()">
      <span class="nav-icon">💳</span> Transactions
    </div>
    <div id="navAnalytics" class="nav-item" onclick="switchView('Analytics')">
      <span class="nav-icon">📊</span> Analytics
    </div>
    <div class="sidebar-bottom">
      <div class="user-pill cursor-pointer" onclick="openAdminProfileModal()" title="Admin Profile">
        <div class="user-avatar">
          <?php echo strtoupper($user['firstName'][0]); ?>
        </div>
        <div>
          <div class="user-name">
            <?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName'], ENT_QUOTES, 'UTF-8'); ?>
          </div>
          <div class="user-role">Administrator</div>
        </div>
        <span class="chevron">›</span>
      </div>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main-content">

    <!-- DASHBOARD VIEW -->
    <div id="viewDashboard" class="view active">
      <div class="admin-dash-header">
        <div>
          <div class="page-title">Library Dashboard</div>
          <div class="admin-dash-sub">Welcome back, <?php echo htmlspecialchars($user['firstName'], ENT_QUOTES, 'UTF-8'); ?>! Here's an overview of the library.</div>
        </div>
        <div class="admin-topbar-right">
          <span class="admin-date" id="adminDate"></span>
        </div>
      </div>

      <!-- Dashboard Panels -->
      <div class="dash-panels">
        <div class="dash-panel panel-red" onclick="window.location.href='assign_books.php'">
          <div class="dash-panel-icon">📖</div>
          <div class="dash-panel-title">Assign Books</div>
          <div class="dash-panel-sub">Manage</div>
          <div class="dash-panel-link">More info ➜</div>
        </div>
        <div class="dash-panel panel-green" onclick="switchView('ReturnedBooks')">
          <div class="dash-panel-icon">↩️</div>
          <div class="dash-panel-title">Returned Books</div>
          <div class="dash-panel-sub">View Returns</div>
          <div class="dash-panel-link">More info ➜</div>
        </div>
        <div class="dash-panel panel-blue" onclick="switchView('BorrowedBooks')">
          <div class="dash-panel-icon">📖</div>
          <div class="dash-panel-title">Borrowed Books</div>
          <div class="dash-panel-sub">Active Borrowings</div>
          <div class="dash-panel-link">More info ➜</div>
        </div>
        <div class="dash-panel panel-purple" onclick="switchView('Reviews')">
          <div class="dash-panel-icon">⭐</div>
          <div class="dash-panel-title">Book Reviews</div>
          <div class="dash-panel-sub">User Ratings</div>
          <div class="dash-panel-link">More info ➜</div>
        </div>
        <div class="dash-panel panel-orange" onclick="switchView('PendingReturns')">
          <div class="dash-panel-icon">🔄</div>
          <div class="dash-panel-title">Pending Returns</div>
          <div class="dash-panel-sub" id="pendingReturnsBadge">Awaiting Verification</div>
          <div class="dash-panel-link">More info ➜</div>
        </div>
      </div>
    </div>

    <!-- LIBRARY VIEW -->
    <div id="viewLibrary" class="view">
      <div class="topbar">
        <button class="tab-btn active" onclick="switchView('Library')">All Books</button>
        <div class="search-bar">
          <span>🔍</span>
          <input type="text" id="librarySearch" placeholder="Search books..." oninput="renderLibrary()">
        </div>
      </div>
      <div class="books-grid" id="booksGrid"></div>
    </div>

    <!-- ADMIN VIEW -->
    <div id="viewAdmin" class="view">
      <div class="admin-header">
        <div class="page-title">Admin Panel</div>
        <button class="btn-add" onclick="openAddBook()">＋ Add Book</button>
      </div>

      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-icon">📚</div>
          <div class="stat-label">Total Books</div>
          <div class="stat-val" id="statTotal">0</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">✅</div>
          <div class="stat-label">Available</div>
          <div class="stat-val" id="statAvail">0</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">📖</div>
          <div class="stat-label">Borrowed</div>
          <div class="stat-val" id="statBorrowed">0</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">👥</div>
          <div class="stat-label">Users</div>
          <div class="stat-val" id="statUsers">0</div>
        </div>
      </div>

      <div class="table-card">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Cover</th>
              <th>Title</th>
              <th>Author</th>
              <th>Genre</th>
              <th>Year</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="adminTableBody"></tbody>
        </table>
      </div>
    </div>

    <!-- RETURNED BOOKS VIEW -->
    <div id="viewReturnedBooks" class="view">
      <div class="admin-header">
        <div class="page-title">Returned Books</div>
        <button class="btn-sec btn-sec--sm" onclick="switchView('Dashboard')">← Back to Dashboard</button>
      </div>

      <div class="table-card">
        <table class="admin-table" id="returnedBooksTable">
          <thead>
            <tr>
              <th>Book Title</th>
              <th>Author</th>
              <th>Returned By</th>
              <th>Condition</th>
              <th>Description</th>
              <th>Returned At</th>
            </tr>
          </thead>
          <tbody id="returnedBooksBody">
            <tr><td colspan="6" class="table-loading-cell">Loading...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PENDING RETURNS VIEW -->
    <div id="viewPendingReturns" class="view">
      <div class="admin-header">
        <div class="page-title">🔄 Pending Returns</div>
        <button class="btn-sec btn-sec--sm" onclick="switchView('Dashboard')">← Back to Dashboard</button>
      </div>
      <div class="table-card">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Book</th>
              <th>Returned By</th>
              <th>User's Condition</th>
              <th>User's Description</th>
              <th>Submitted</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="pendingReturnsBody">
            <tr><td colspan="6" class="table-loading-cell">Loading...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- REVIEWS VIEW -->
    <div id="viewReviews" class="view">
      <div class="admin-header">
        <div class="page-title">⭐ Book Reviews</div>
        <button class="btn-sec btn-sec--sm" onclick="switchView('Dashboard')">← Back to Dashboard</button>
      </div>
      <div class="table-card">
        <table class="admin-table" id="reviewsTable">
          <thead>
            <tr>
              <th>Book</th>
              <th>Reviewed By</th>
              <th>Rating</th>
              <th>Review</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody id="reviewsBody">
            <tr><td colspan="5" class="table-loading-cell">Loading...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- BORROWED BOOKS VIEW -->
    <div id="viewBorrowedBooks" class="view">
      <div class="admin-header">
        <div class="page-title">Borrowed Books</div>
        <div class="admin-header-btns">
          <a href="export_report.php?type=borrowings" class="btn-sec btn-sec--export">📥 Export Borrowed Book CSV</a>
          <a href="export_report.php?type=fines" class="btn-sec btn-sec--export">📥 Export Fines CSV</a>
          <button class="btn-sec btn-sec--sm" onclick="switchView('Dashboard')">← Back</button>
        </div>
      </div>

      <div class="table-card">
        <table class="admin-table" id="borrowedBooksTable">
          <thead>
            <tr>
              <th>Book Title</th>
              <th>Author</th>
              <th>Borrowed By</th>
              <th>Borrow Date</th>
              <th>Due Date</th>
              <th>Days Left</th>
              <th>Extensions</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="borrowedBooksBody">
            <tr><td colspan="8" class="table-loading-cell">Loading...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ANALYTICS VIEW -->
    <div id="viewAnalytics" class="view">
      <div class="admin-header">
        <div>
          <div class="page-title">📊 Analytics</div>
          <div class="page-subtitle">Live library insights</div>
        </div>
        <button class="btn-sec btn-sec--sm" id="sendDigestBtn" onclick="sendWeeklyDigest()">
          📧 Send Weekly Digest
        </button>
      </div>

      <!-- Summary stat cards -->
      <div class="stats-row" id="analyticsStats">
        <div class="stat-card">
          <div class="stat-icon">💰</div>
          <div class="stat-label">Total Revenue</div>
          <div class="stat-val" id="aStatRevenue">—</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">📖</div>
          <div class="stat-label">Active Borrowers</div>
          <div class="stat-val" id="aStatBorrowers">—</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">⚠️</div>
          <div class="stat-label">Overdue Books</div>
          <div class="stat-val stat-val--danger" id="aStatOverdue">—</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">👥</div>
          <div class="stat-label">Total Users</div>
          <div class="stat-val" id="aStatUsers">—</div>
        </div>
      </div>

      <!-- Charts grid -->
      <div class="analytics-charts-grid">

        <!-- Monthly borrowings line chart -->
        <div class="table-card chart-card">
          <div class="chart-title">Monthly Borrowings <span class="chart-title-note">(last 6 months)</span></div>
          <canvas id="chartBorrowings" height="180"></canvas>
        </div>

        <!-- Revenue breakdown doughnut -->
        <div class="table-card chart-card">
          <div class="chart-title">Revenue Breakdown</div>
          <canvas id="chartRevenue" height="180"></canvas>
        </div>

      </div>

      <!-- Top books bar chart — full width -->
      <div class="table-card chart-card--mt">
        <div class="chart-title">Top Borrowed Books <span class="chart-title-note">(all time)</span></div>
        <canvas id="chartTopBooks" height="100"></canvas>
      </div>

    </div>

  </div>
</div>

<!-- APPROVE RETURN MODAL -->
<div class="modal-overlay modal-overlay--z1100" id="approveReturnModal">
  <div class="modal modal--500">
    <div class="modal-header">
      <div class="modal-title">✅ Approve Return</div>
      <button class="modal-close" onclick="closeModal('approveReturnModal')">✕</button>
    </div>
    <div class="ar-book-section">
      <div class="ar-book-label">Book</div>
      <div id="arBook" class="ar-book-title"></div>
    </div>
    <div class="ar-info-row">
      <div class="ar-info-box">
        <div class="ar-info-label">Returned By</div>
        <div id="arUser" class="ar-info-value"></div>
      </div>
      <div class="ar-info-box ar-info-box--warning">
        <div class="ar-info-label">User's Claimed Condition</div>
        <div id="arUserCondition" class="ar-info-value--bold"></div>
      </div>
    </div>
    <div id="arUserDesc" class="ar-user-desc" style="display:none;">
      <span class="ar-user-desc-label">User's description:</span>
      <span id="arUserDescText"></span>
    </div>
    <div class="ar-field-group">
      <label class="form-label-block">
        Real Condition <span class="form-required">*</span>
        <span class="form-label-note"> (your assessment after inspecting the book)</span>
      </label>
      <select id="arRealCondition" class="admin-select">
        <option value="">— Select condition —</option>
        <option value="excellent">⭐ Excellent — Like new (no fine)</option>
        <option value="good">👍 Good — Minor wear (no fine)</option>
        <option value="fair">😐 Fair — Some wear (Rs <?= DAMAGE_FINE_FAIR ?> fine)</option>
        <option value="bad">⚠️ Bad — Noticeable damage (Rs <?= DAMAGE_FINE_BAD ?> fine)</option>
        <option value="damaged">❌ Damaged — Significant damage (80% of book price)</option>
      </select>
    </div>
    <div class="ar-notes-group">
      <label class="form-label-block">Admin Notes <span class="form-label-note">(optional)</span></label>
      <textarea id="arAdminNotes" rows="2" placeholder="Any observations about the book's condition..." class="admin-textarea"></textarea>
    </div>
    <div id="arError" class="modal-err-inline" style="display:none;"></div>
    <div class="modal-actions">
      <button class="btn-primary" onclick="submitApproveReturn()">✅ Approve Return</button>
      <button class="btn-sec" onclick="closeModal('approveReturnModal')">Cancel</button>
    </div>
  </div>
</div>

<!-- TRANSACTIONS MODAL -->
<div class="modal-overlay modal-overlay--z1100" id="transactionsModal">
  <div class="modal modal--820">
    <div class="modal-header">
      <div class="modal-title">💳 Transactions</div>
      <button class="modal-close" onclick="closeModal('transactionsModal')">✕</button>
    </div>

    <!-- Admin revenue summary -->
    <div class="tx-summary-row">
      <div class="tx-summary-card">
        <div class="tx-card-label">Total Revenue</div>
        <div class="tx-card-val tx-card-val--accent" id="txAdminTotal">—</div>
      </div>
      <div class="tx-summary-card">
        <div class="tx-card-label">From Borrows</div>
        <div class="tx-card-val" id="txBorrowTotal">—</div>
      </div>
      <div class="tx-summary-card">
        <div class="tx-card-label">From Fines</div>
        <div class="tx-card-val tx-card-val--danger" id="txFineTotal">—</div>
      </div>
      <div class="tx-summary-card">
        <div class="tx-card-label">PDF Sales</div>
        <div class="tx-card-val tx-card-val--purple" id="txPdfTotal">—</div>
      </div>
    </div>

    <!-- Filter row -->
    <div class="tx-filter-row">
      <input type="text" id="txSearch" placeholder="🔍 Search user, book, type..." oninput="filterTransactions()"
        class="tx-search-input">
      <select id="txTypeFilter" onchange="filterTransactions()" class="tx-type-select">
        <option value="">All Types</option>
        <option value="borrow_fee">Borrow Fee</option>
        <option value="extension_fee">Extension Fee</option>
        <option value="pdf_purchase">PDF Purchase</option>
        <option value="overdue_fine">Overdue Fine</option>
        <option value="damage_fine">Damage Fine</option>
        <option value="top_up">Top Up</option>
        <option value="lost_book">Lost Book</option>
      </select>
    </div>

    <!-- Transactions table -->
    <div class="tx-table-wrap">
      <table class="admin-table" id="txTable">
        <thead>
          <tr>
            <th>Date</th>
            <th>User</th>
            <th>Type</th>
            <th>Book</th>
            <th>Amount</th>
          </tr>
        </thead>
        <tbody id="txTableBody">
          <tr><td colspan="5" class="tx-loading-cell">Loading...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- USER BALANCES MODAL (accessible from Transactions) -->
<div class="modal-overlay modal-overlay--z1200" id="userBalancesModal">
  <div class="modal modal--540">
    <div class="modal-header">
      <div class="modal-title">👥 User Balances</div>
      <button class="modal-close" onclick="closeModal('userBalancesModal')">✕</button>
    </div>
    <div id="userBalancesList" class="scrollable-list"></div>
  </div>
</div>

<!-- EXTENSION DETAIL MODAL -->
<div class="modal-overlay" id="extensionDetailModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="extDetailTitle">Extension History</div>
      <button class="modal-close" onclick="closeModal('extensionDetailModal')">✕</button>
    </div>
    <div id="extDetailBorrowInfo" class="borrow-info-box"></div>
    <div id="extDetailList"></div>
  </div>
</div>

<!-- BOOK DETAIL MODAL -->
<div class="modal-overlay" id="detailModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="detailTitle"></div>
      <button class="modal-close" onclick="closeModal('detailModal')">✕</button>
    </div>
    <div class="detail-cover" id="detailCover"></div>
    <div class="detail-info">
      <p><strong>Author:</strong> <span id="detailAuthor"></span></p>
      <p><strong>Genre:</strong> <span id="detailGenre"></span></p>
      <p><strong>Year:</strong> <span id="detailYear"></span></p>
      <p><strong>ISBN:</strong> <span id="detailIsbn"></span></p>
      <p><strong>Status:</strong> <span id="detailStatus"></span></p>
      <p><strong>Copies:</strong> <span id="detailCopies"></span></p>
    </div>
    <div class="detail-desc" id="detailDesc"></div>
  </div>
</div>

<!-- ADD / EDIT BOOK MODAL -->
<div class="modal-overlay" id="bookFormModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="bookFormTitle">Add Book</div>
      <button class="modal-close" onclick="closeModal('bookFormModal')">✕</button>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Title *</label>
        <input type="text" id="fTitle" placeholder="Book title">
      </div>
      <div class="form-group">
        <label>Author *</label>
        <input type="text" id="fAuthor" placeholder="Author name">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Genre</label>
        <select id="fGenre">
          <option>Fiction</option>
          <option>Non-Fiction</option>
          <option>Science</option>
          <option>History</option>
          <option>Biography</option>
          <option>Fantasy</option>
          <option>Mystery</option>
          <option>Self-Help</option>
          <option>Design</option>
          <option>Technology</option>
        </select>
      </div>
      <div class="form-group">
        <label>Year</label>
        <input type="number" id="fYear" placeholder="Year">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>ISBN</label>
        <input type="text" id="fIsbn" placeholder="ISBN">
      </div>
      <div class="form-group">
        <label>Copies</label>
        <input type="number" id="fCopies" placeholder="Number of copies" min="1">
      </div>
    </div>
    <div class="form-group">
      <label>Description</label>
      <textarea id="fDesc" placeholder="Brief description of the book..."></textarea>
    </div>
    <div class="form-group">
      <label>Color Theme</label>
      <select id="fColor">
        <option value="color-1">Blue</option>
        <option value="color-2">Red</option>
        <option value="color-3">Green</option>
        <option value="color-4">Purple</option>
        <option value="color-5">Orange</option>
        <option value="color-6">Teal</option>
        <option value="color-7">Dark</option>
        <option value="color-8">Amber</option>
      </select>
    </div>
    <div class="detail-actions">
      <button class="btn-primary" onclick="saveBook()">Save Book</button>
      <button class="btn-sec" onclick="closeModal('bookFormModal')">Cancel</button>
    </div>
  </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal modal-sm">
    <button class="modal-close-corner" onclick="closeModal('deleteModal')">✕</button>
    <div class="modal-icon">🗑️</div>
    <div class="modal-title">Delete Book?</div>
    <p class="modal-warning">This action cannot be undone.</p>
    <div class="modal-actions">
      <button class="btn-danger" onclick="confirmDelete()">Yes, Delete</button>
      <button class="btn-sec" onclick="closeModal('deleteModal')">Cancel</button>
    </div>
  </div>
</div>

<!-- ADMIN WELCOME MODAL -->
<div class="modal-overlay" id="adminWelcomeModal">
  <div class="modal modal-sm">
    <div class="modal-icon">🎉</div>
    <div class="modal-title" id="adminWelcomeName"></div>
    <p class="modal-warning modal-warning--muted">You have successfully logged in. Welcome to the TechGiants Admin Panel!</p>
    <div class="modal-actions">
      <button class="btn-primary" onclick="closeModal('adminWelcomeModal')">Let's Go!</button>
    </div>
  </div>
</div>

<!-- ADMIN PROFILE MODAL -->
<div class="modal-overlay" id="adminProfileModal">
  <div class="modal u-profile-modal">
    <button class="modal-close-corner" onclick="closeModal('adminProfileModal')">✕</button>
    <div class="u-profile-avatar-wrap">
      <div class="u-profile-big-avatar">
        <?php echo strtoupper($user['firstName'][0]); ?>
      </div>
      <div class="u-profile-avatar-name"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName'], ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="u-profile-avatar-email">Administrator</div>
    </div>
    <div class="u-profile-meta-row">
      <div class="u-profile-balance-card" id="adminBalancePill">
        <span class="u-profile-balance-label">💰 Library Revenue</span>
        <span class="u-profile-balance-amount" id="adminSidebarBalance">Rs —</span>
      </div>
      <button class="u-profile-logout-btn" onclick="closeModal('adminProfileModal'); openAdminLogoutConfirm();">
        🚪 Logout
      </button>
    </div>
  </div>
</div>

<!-- ADMIN LOGOUT CONFIRM MODAL -->
<div class="modal-overlay" id="adminLogoutModal">
  <div class="modal modal-sm">
    <button class="modal-close-corner" onclick="closeModal('adminLogoutModal')">✕</button>
    <div class="modal-icon">🚪</div>
    <div class="modal-title">Confirm Logout</div>
    <p class="modal-warning">Are you sure you want to log out of the admin panel?</p>
    <div class="modal-actions">
      <button class="btn-danger" onclick="window.location.href='login.php?logout=1'">Yes, Logout</button>
      <button class="btn-sec" onclick="closeModal('adminLogoutModal')">Cancel</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>
<script>
var _adminShowWelcome = <?php echo $showWelcome; ?>;
var _adminWelcomeName = <?php echo json_encode($user['firstName']); ?>;

function openAdminProfileModal() {
  document.getElementById('adminProfileModal').classList.add('open');
}

function openAdminLogoutConfirm() {
  document.getElementById('adminLogoutModal').classList.add('open');
}

window.addEventListener('load', function() {
  if (_adminShowWelcome) {
    document.getElementById('adminWelcomeName').textContent = 'Welcome back, ' + _adminWelcomeName + '! 👋';
    document.getElementById('adminWelcomeModal').classList.add('open');
  }
  // handle ?view= param
  var params = new URLSearchParams(window.location.search);
  var viewParam = params.get('view');
  if (viewParam) switchView(viewParam);
});
</script>
<script src="admin_dashboard.js?v=<?php echo filemtime('admin_dashboard.js'); ?>"></script>

</body>
</html>