<?php
require 'session.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TechGiants – Assign Books</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="app-wrapper">

  <!-- SIDEBAR -->
  <div class="sidebar">
    <div class="sidebar-logo">📚 TechGiants</div>

    <div class="nav-item active" onclick="window.location.href='admin_dashboard.php'">
      <span class="nav-icon">🏠</span> Dashboard
    </div>
    <div class="nav-item" onclick="window.location.href='admin_dashboard.php'">
      <span class="nav-icon">📚</span> My Library
    </div>
    <div class="nav-item" onclick="window.location.href='admin_dashboard.php?view=Admin'">
      <span class="nav-icon">⚙️</span> Admin Panel
    </div>
    <div class="nav-item" onclick="window.location.href='admin_dashboard.php?view=BorrowedBooks'">
      <span class="nav-icon">📖</span> Borrowed Books
    </div>
    <div class="nav-item" onclick="window.location.href='admin_dashboard.php?view=Reviews'">
      <span class="nav-icon">⭐</span> Reviews
    </div>
    <div class="nav-item" onclick="window.location.href='admin_dashboard.php?view=PendingReturns'">
      <span class="nav-icon">🔄</span> Pending Returns
    </div>
    <div class="nav-item" onclick="window.location.href='admin_dashboard.php'">
      <span class="nav-icon">💳</span> Transactions
    </div>
    <div class="nav-item" onclick="window.location.href='admin_dashboard.php?view=Analytics'">
      <span class="nav-icon">📊</span> Analytics
    </div>

    <div class="sidebar-bottom">
      <div class="user-pill cursor-pointer" onclick="openLogoutModal()" title="Account">
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

    <div class="assign-header">
      <div>
        <div class="page-title">Assign Books</div>
        <div class="assign-subheader">
          Review and approve or reject book borrow requests from users.
        </div>
      </div>
      <a href="admin_dashboard.php" class="assign-back-btn">← Back to Dashboard</a>
    </div>

    <!-- Stats -->
    <div class="assign-stats-row">
      <div class="assign-stat-card">
        <div class="assign-stat-icon">⏳</div>
        <div>
          <div class="assign-stat-label">Pending</div>
          <div class="assign-stat-val" id="aStatPending">0</div>
        </div>
      </div>
      <div class="assign-stat-card">
        <div class="assign-stat-icon">✅</div>
        <div>
          <div class="assign-stat-label">Approved</div>
          <div class="assign-stat-val" id="aStatApproved">0</div>
        </div>
      </div>
      <div class="assign-stat-card">
        <div class="assign-stat-icon">❌</div>
        <div>
          <div class="assign-stat-label">Rejected</div>
          <div class="assign-stat-val" id="aStatRejected">0</div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="assign-tabs">
      <button class="assign-tab active" id="tabPending" onclick="switchTab('pending')">
        Pending <span class="pending-badge" id="pendingBadge">0</span>
      </button>
      <button class="assign-tab" id="tabAll" onclick="switchTab('all')">All Requests</button>
    </div>

    <!-- Table -->
    <div class="assign-table-card">
      <table class="assign-table">
        <thead>
          <tr>
            <th>Book</th>
            <th>Requested By</th>
            <th>Duration</th>
            <th>Language</th>
            <th>Note</th>
            <th>Requested On</th>
            <th id="actionColHead">Actions</th>
          </tr>
        </thead>
        <tbody id="assignTableBody"></tbody>
      </table>
    </div>

  </div>
</div>

<!-- APPROVE CONFIRM MODAL -->
<div class="modal-overlay" id="approveModal">
  <div class="modal modal-sm">
    <button class="modal-close-corner" onclick="closeModal('approveModal')">✕</button>
    <div class="modal-icon">✅</div>
    <div class="modal-title">Approve Request?</div>
    <div class="confirm-modal-details" id="approveDetails"></div>
    <p class="modal-warning modal-warning--dark">
      The book will be marked as borrowed and a due date will be set based on the requested duration.
    </p>
    <div class="modal-actions">
      <button class="btn-approve btn-approve--lg" onclick="confirmApprove()">✅ Yes, Approve</button>
      <button class="btn-sec" onclick="closeModal('approveModal')">Cancel</button>
    </div>
  </div>
</div>

<!-- REJECT CONFIRM MODAL -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal modal-sm">
    <button class="modal-close-corner" onclick="closeModal('rejectModal')">✕</button>
    <div class="modal-icon">❌</div>
    <div class="modal-title">Reject Request?</div>
    <div class="confirm-modal-details" id="rejectDetails"></div>
    <p class="modal-warning">This will deny the user's borrow request for this book.</p>
    <div class="modal-actions">
      <button class="btn-danger" onclick="confirmReject()">Yes, Reject</button>
      <button class="btn-sec" onclick="closeModal('rejectModal')">Cancel</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<!-- LOGOUT CONFIRM MODAL -->
<div class="modal-overlay" id="logoutModal">
  <div class="modal modal-sm">
    <button class="modal-close-corner" onclick="closeModal('logoutModal')">✕</button>
    <div class="modal-icon">🚪</div>
    <div class="modal-title">Log Out?</div>
    <p class="modal-warning modal-warning--spaced">
      Are you sure you want to log out of your session?
    </p>
    <div class="modal-actions">
      <button class="btn-danger" onclick="window.location.href='login.php?logout=1'">Yes, Log Out</button>
      <button class="btn-sec" onclick="closeModal('logoutModal')">Cancel</button>
    </div>
  </div>
</div>

<script>
var allRequests    = [];
var currentTab     = 'pending';
var pendingReqId   = null;
var rejectingReqId = null;

var colorMap = {
  1:'#4a6fa5', 2:'#c0392b', 3:'#27ae60',
  4:'#8e44ad', 5:'#e67e22', 6:'#16a085',
  7:'#2c3e50', 8:'#f39c12'
};
function bookColor(bookId) {
  return colorMap[((parseInt(bookId) % 8) + 1)] || '#4a6fa5';
}

function switchTab(tab) {
  currentTab = tab;
  document.getElementById('tabPending').classList.toggle('active', tab === 'pending');
  document.getElementById('tabAll').classList.toggle('active', tab === 'all');
  var head = document.getElementById('actionColHead');
  head.textContent = tab === 'pending' ? 'Actions' : 'Status';
  renderTable();
}

function loadRequests() {
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getAllRequests' })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    allRequests = data;
    updateStats();
    renderTable();
  })
  .catch(function() { showToast('Failed to load requests.'); });
}

function updateStats() {
  var pending  = allRequests.filter(function(r) { return r.status === 'pending'; }).length;
  var approved = allRequests.filter(function(r) { return r.status === 'approved'; }).length;
  var rejected = allRequests.filter(function(r) { return r.status === 'rejected'; }).length;
  document.getElementById('aStatPending').textContent  = pending;
  document.getElementById('aStatApproved').textContent = approved;
  document.getElementById('aStatRejected').textContent = rejected;
  document.getElementById('pendingBadge').textContent  = pending;
}

function renderTable() {
  var filtered = currentTab === 'pending'
    ? allRequests.filter(function(r) { return r.status === 'pending'; })
    : allRequests;

  var tbody = document.getElementById('assignTableBody');
  tbody.innerHTML = '';

  if (!filtered.length) {
    tbody.innerHTML =
      '<tr><td colspan="6">' +
        '<div class="empty-requests">' +
          '<div class="empty-icon">' + (currentTab === 'pending' ? '🎉' : '📭') + '</div>' +
          '<p>' + (currentTab === 'pending'
            ? 'No pending requests. All caught up!'
            : 'No requests have been made yet.') + '</p>' +
        '</div>' +
      '</td></tr>';
    return;
  }

  for (var i = 0; i < filtered.length; i++) {
    var req = filtered[i];
    var color = bookColor(req.book_id);
    var dateStr = req.requested_at
      ? new Date(req.requested_at).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' })
      : '—';

    var actionCell;
    if (currentTab === 'pending') {
      actionCell =
        '<div class="action-btns-assign">' +
          '<button class="btn-approve" onclick="openApprove(' + req.id + ')">✅ Approve</button>' +
          '<button class="btn-reject"  onclick="openReject(' + req.id + ')">✕ Reject</button>' +
        '</div>';
    } else {
      var pill;
      if (req.status === 'pending')  pill = '<span class="status-pill status-pending">⏳ Pending</span>';
      if (req.status === 'approved') pill = '<span class="status-pill status-approved">✅ Approved</span>';
      if (req.status === 'rejected') pill = '<span class="status-pill status-rejected">❌ Rejected</span>';
      actionCell = pill;
    }

    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td>' +
        '<div class="req-book-cell">' +
          '<div class="req-mini-cover" style="background:' + color + '">' + escHtml(req.book_title) + '</div>' +
          '<div>' +
            '<div class="req-book-title">' + escHtml(req.book_title) + '</div>' +
            '<div class="req-book-author">by ' + escHtml(req.book_author) + '</div>' +
          '</div>' +
        '</div>' +
      '</td>' +
      '<td>' +
        '<div class="req-user-cell">' +
          '<div class="req-user-name">' + escHtml(req.first_name + ' ' + req.last_name) + '</div>' +
          '<div class="req-user-email">@' + escHtml(req.username) + ' · ' + escHtml(req.email) + '</div>' +
        '</div>' +
      '</td>' +
      '<td><span class="days-badge">📅 ' + req.borrow_days + ' day' + (req.borrow_days > 1 ? 's' : '') + '</span></td>' +
      '<td>' +
        '<span class="days-badge" style="background:' + (req.language === 'nepali' ? '#fef9c3' : '#e0f2fe') + ';color:' + (req.language === 'nepali' ? '#854d0e' : '#0369a1') + ';">' +
          (req.language === 'nepali' ? '🇳🇵 Nepali' : '🇬🇧 English') +
        '</span>' +
      '</td>' +
      '<td>' + (req.note ? '<span class="note-text" title="' + escHtml(req.note) + '">' + escHtml(req.note) + '</span>' : '<span class="no-note">No note</span>') + '</td>' +
      '<td><span class="req-date">' + dateStr + '</span></td>' +
      '<td>' + actionCell + '</td>';
    tbody.appendChild(tr);
  }
}

function openApprove(id) {
  var req = null;
  for (var i = 0; i < allRequests.length; i++) {
    if (parseInt(allRequests[i].id) === parseInt(id)) { req = allRequests[i]; break; }
  }
  if (!req) return;
  pendingReqId = id;
  var dueDate = new Date();
  dueDate.setDate(dueDate.getDate() + parseInt(req.borrow_days));
  var dueDateStr = dueDate.toLocaleDateString('en-US', { weekday:'long', month:'long', day:'numeric', year:'numeric' });
  document.getElementById('approveDetails').innerHTML =
    '<p><strong>Book:</strong> ' + escHtml(req.book_title) + '</p>' +
    '<p><strong>User:</strong> ' + escHtml(req.first_name + ' ' + req.last_name) + ' (@' + escHtml(req.username) + ')</p>' +
    '<p><strong>Duration:</strong> ' + req.borrow_days + ' day' + (req.borrow_days > 1 ? 's' : '') + '</p>' +
    '<p><strong>Language:</strong> ' + (req.language === 'nepali' ? '🇳🇵 नेपाली (Nepali)' : '🇬🇧 English') + '</p>' +
    '<p><strong>Due back by:</strong> ' + dueDateStr + '</p>' +
    (req.note ? '<p><strong>Note:</strong> ' + escHtml(req.note) + '</p>' : '');
  document.getElementById('approveModal').classList.add('open');
}

function confirmApprove() {
  if (!pendingReqId) return;
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'approveRequest', request_id: pendingReqId })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    closeModal('approveModal');
    if (data.success) {
      showToast('✅ Request approved! Book assigned to user.');
      loadRequests();
    } else {
      showToast(data.error || 'Failed to approve.');
    }
  })
  .catch(function() { showToast('Request failed.'); });
}

function openReject(id) {
  var req = null;
  for (var i = 0; i < allRequests.length; i++) {
    if (parseInt(allRequests[i].id) === parseInt(id)) { req = allRequests[i]; break; }
  }
  if (!req) return;
  rejectingReqId = id;
  document.getElementById('rejectDetails').innerHTML =
    '<p><strong>Book:</strong> ' + escHtml(req.book_title) + '</p>' +
    '<p><strong>User:</strong> ' + escHtml(req.first_name + ' ' + req.last_name) + ' (@' + escHtml(req.username) + ')</p>';
  document.getElementById('rejectModal').classList.add('open');
}

function confirmReject() {
  if (!rejectingReqId) return;
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'rejectRequest', request_id: rejectingReqId })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    closeModal('rejectModal');
    if (data.success) {
      showToast('Request rejected.');
      loadRequests();
    } else {
      showToast(data.error || 'Failed to reject.');
    }
  })
  .catch(function() { showToast('Request failed.'); });
}

function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openLogoutModal() { document.getElementById('logoutModal').classList.add('open'); }

function showToast(msg) {
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(function() { t.classList.remove('show'); }, 3000);
}

function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

var overlays = document.querySelectorAll('.modal-overlay');
for (var i = 0; i < overlays.length; i++) {
  overlays[i].addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
  });
}

loadRequests();</script>
</body>
</html>