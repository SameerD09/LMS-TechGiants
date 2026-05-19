<?php
// auto_migrate.php — run idempotent table creations on every boot
// Included by db.php; all statements use IF NOT EXISTS.

// ── Borrow waitlist ────────────────────────────────────────────
$conn->query("
  CREATE TABLE IF NOT EXISTS borrow_waitlist (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    book_id     INT NOT NULL,
    user_id     INT NOT NULL,
    position    INT NOT NULL DEFAULT 1,
    status      ENUM('waiting','notified','expired','fulfilled') DEFAULT 'waiting',
    joined_at   DATETIME DEFAULT NOW(),
    notified_at DATETIME NULL,
    UNIQUE KEY unique_user_book (book_id, user_id),
    KEY idx_book_status (book_id, status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Book reviews / ratings ─────────────────────────────────────
$conn->query("
  CREATE TABLE IF NOT EXISTS book_reviews (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    book_id    INT NOT NULL,
    user_id    INT NOT NULL,
    rating     TINYINT NOT NULL,
    review     TEXT,
    created_at DATETIME DEFAULT NOW(),
    UNIQUE KEY unique_user_book (book_id, user_id),
    KEY idx_book (book_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Add lost_reported flag to borrowings (idempotent) ─────────
$conn->query("ALTER TABLE borrowings ADD COLUMN IF NOT EXISTS lost_reported TINYINT(1) NOT NULL DEFAULT 0");

// ── Track when books were added (used by analytics + digest) ──
$conn->query("ALTER TABLE books ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT NOW()");

// ── PDF download log ───────────────────────────────────────────
$conn->query("
  CREATE TABLE IF NOT EXISTS pdf_download_logs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    book_id       INT NOT NULL,
    downloaded_at DATETIME DEFAULT NOW(),
    KEY idx_user_book (user_id, book_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");