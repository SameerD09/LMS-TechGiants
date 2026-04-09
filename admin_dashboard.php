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
  <title>TechGiants – Admin Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
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
    <div class="nav-item" onclick="window.location.href='login.php?logout=1'">
      <span class="nav-icon">🚪</span> Logout
    </div>

    <div class="sidebar-bottom">
      <div class="user-pill">
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

      <!-- Dashboard Panels (Student panel removed) -->
      <div class="dash-panels">
        <div class="dash-panel panel-red" onclick="switchView('Admin')">
          <div class="dash-panel-icon">📖</div>
          <div class="dash-panel-title">Assign Books</div>
          <div class="dash-panel-sub">Manage</div>
          <div class="dash-panel-link">More info ➜</div>
        </div>
        <div class="dash-panel panel-yellow" onclick="switchView('Library')">
          <div class="dash-panel-icon">🏷️</div>
          <div class="dash-panel-title">Book Category</div>
          <div class="dash-panel-sub">Browse</div>
          <div class="dash-panel-link">More info ➜</div>
        </div>
        <div class="dash-panel panel-green" onclick="switchView('Library')">
          <div class="dash-panel-icon">📚</div>
          <div class="dash-panel-title">Books</div>
          <div class="dash-panel-sub">All Books</div>
          <div class="dash-panel-link">More info ➜</div>
        </div>
        <div class="dash-panel panel-purple" onclick="switchView('Library')">
          <div class="dash-panel-icon">🏛️</div>
          <div class="dash-panel-title">Library</div>
          <div class="dash-panel-sub">Explore</div>
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

<div class="toast" id="toast"></div>
<script src="admin_dashboard.js"></script>

</body>
</html>
