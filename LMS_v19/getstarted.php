<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TechGiants – Library Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body class="gs-body">

  <!-- NAVBAR -->
  <nav class="navbar">
    <div class="nav-logo">
      📚 TechGiants
      <span class="nav-logo-sub">Library System</span>
    </div>
    <div class="nav-links">
      <a href="login.php" class="btn-outline">Log In</a>
      <a href="signup.php" class="btn-filled">Sign Up</a>
    </div>
  </nav>

  <!-- HERO -->
  <section class="hero">
    <div class="hero-left">
      <div class="hero-tag">📖 TechGiants Library System</div>
      <h1 class="hero-title">
        Your Library,<br>
        <span>Beautifully</span><br>
        Managed.
      </h1>
      <p class="hero-sub">
        A modern library management system built by TechGiants.
        Browse books, manage your reading list, and keep track
        of your library — all in one place.
      </p>
      <div class="hero-btns">
        <a href="signup.php" class="btn-hero btn-hero-primary">Get Started →</a>
        <a href="login.php" class="btn-hero btn-hero-secondary">Log In</a>
      </div>
    </div>

    <div class="hero-right">
      <div class="book-illustration">
        <div class="book-rows">
          <div class="book-row-illus">
            <div class="bk-illus color-3 bk-h90 bk-w30"></div>
            <div class="bk-illus color-1 bk-h110 bk-w36"></div>
            <div class="bk-illus color-5 bk-h95 bk-w28"></div>
            <div class="bk-illus color-2 bk-h105 bk-w34"></div>
            <div class="bk-illus color-7 bk-h85 bk-w30"></div>
          </div>
          <div class="book-row-illus">
            <div class="bk-illus color-4 bk-h100 bk-w34"></div>
            <div class="bk-illus color-6 bk-h92 bk-w28"></div>
            <div class="bk-illus color-8 bk-h108 bk-w36"></div>
            <div class="bk-illus color-1 bk-h88 bk-w30"></div>
            <div class="bk-illus color-3 bk-h96 bk-w32"></div>
          </div>
        </div>
        <div class="illus-title">TechGiants Library</div>
        <div class="illus-sub">Thousands of books at your fingertips</div>
      </div>
    </div>
  </section>

  <!-- FEATURES -->
  <section class="features">
    <div class="feature-card">
      <div class="feature-icon">📚</div>
      <div class="feature-title">Browse Books</div>
      <div class="feature-desc">Explore our full collection with easy search and filtering by genre, author, or title.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon">🔖</div>
      <div class="feature-title">Track Reading</div>
      <div class="feature-desc">Keep your reading list organised — currently reading, next up, and finished books.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon">⚙️</div>
      <div class="feature-title">Admin Control</div>
      <div class="feature-desc">Admins can add, edit, and manage the entire book catalogue and user base.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon">🔒</div>
      <div class="feature-title">Secure Access</div>
      <div class="feature-desc">Role-based access control keeps admin and user areas completely separate.</div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="footer">
    &copy; <?php echo date('Y'); ?> TechGiants. All rights reserved. &nbsp;|&nbsp; Library Management System
  </footer>

</body>
</html>
