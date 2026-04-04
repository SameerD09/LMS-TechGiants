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
      <div class="u-logout-btn" onclick="window.location.href='login.php?logout=1'">
        🚪 Logout
      </div>
      <div class="u-user-pill">
        <div class="u-sidebar-avatar">
          <?php echo strtoupper($user['firstName'][0]); ?>
        </div>
        <div class="u-sidebar-user-info">
          <div class="u-sidebar-name"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName'], ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="u-sidebar-role">Member</div>
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

<!-- DOWNLOAD SUCCESS POPUP -->
<div class="modal-overlay" id="uDownloadPopup">
  <div class="modal modal-sm">
    <div class="modal-icon">✅</div>
    <div class="modal-title" id="uDownloadPopupTitle"></div>
    <p class="modal-warning" style="color:#555;margin-top:8px;">Your digital copy has been downloaded successfully. Enjoy reading!</p>
    <div class="modal-actions">
      <button class="btn-primary" onclick="closeModal('uDownloadPopup')">Okay</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>
<script src="user_dashboard.js"></script>

</body>
</html>
