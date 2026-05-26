// ── DATA (fetched from DB) ──
var books = [];
var editingBookId  = null;
var deletingBookId = null;

function loadBooks(callback) {
  fetch('get_books.php')
    .then(function(res) { return res.json(); })
    .then(function(data) {
      books = data.map(function(b) {
        return {
          id:     parseInt(b.id),
          title:  b.title,
          author: b.author,
          genre:  b.genre,
          year:   parseInt(b.year),
          isbn:   b.isbn,
          desc:   b.description,
          color:  'color-' + ((parseInt(b.id) % 8) + 1)
        };
      });
      if (callback) callback();
    })
    .catch(function() { console.error('Failed to load books from DB.'); });
}

// ── LOAD LIVE STATS FROM DB ──
function loadStats(callback) {
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getStats' })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    document.getElementById('statTotal').textContent    = data.totalBooks;
    document.getElementById('statAvail').textContent    = data.available;
    document.getElementById('statBorrowed').textContent = data.borrowed;
    document.getElementById('statUsers').textContent    = data.totalUsers;
    if (callback) callback();
  })
  .catch(function() { console.error('Failed to load stats.'); });
}

// ── SET DATE ──
(function() {
  var el = document.getElementById('adminDate');
  if (el) {
    var now = new Date();
    el.textContent = now.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
  }
})();

// ── NAVIGATION ──
function switchView(name) {
  var views = ['Dashboard', 'Library', 'Admin', 'ReturnedBooks', 'BorrowedBooks'];
  for (var i = 0; i < views.length; i++) {
    var v = document.getElementById('view' + views[i]);
    var n = document.getElementById('nav' + views[i]);
    if (v) v.classList.remove('active');
    if (n) n.classList.remove('active');
  }
  var activeView = document.getElementById('view' + name);
  var activeNav  = document.getElementById('nav' + name);
  if (activeView) activeView.classList.add('active');
  if (activeNav)  activeNav.classList.add('active');

  if (name === 'ReturnedBooks') {
    loadReturnedBooks();
  }
  if (name === 'BorrowedBooks') {
    loadBorrowedBooks();
  }
}

// ── RENDER LIBRARY ──
function renderLibrary() {
  var q = document.getElementById('librarySearch') ? document.getElementById('librarySearch').value.toLowerCase() : '';
  var filtered = books.filter(function(b) {
    return !q || b.title.toLowerCase().indexOf(q) !== -1 || b.author.toLowerCase().indexOf(q) !== -1 || b.genre.toLowerCase().indexOf(q) !== -1;
  });

  var grid = document.getElementById('booksGrid');
  grid.innerHTML = '';

  for (var i = 0; i < filtered.length; i++) {
    var book = filtered[i];
    var card = document.createElement('div');
    card.className = 'book-grid-card';
    card.onclick = (function(id) { return function() { openDetail(id); }; })(book.id);
    card.innerHTML =
      '<div class="book-grid-cover ' + book.color + '">' + book.title + '</div>' +
      '<div class="book-grid-title">' + book.title + '</div>' +
      '<div class="book-grid-author">' + book.author + '</div>' +
      '<span class="badge badge-genre">' + book.genre + '</span>';
    grid.appendChild(card);
  }
}

// ── BOOK DETAIL ──
function openDetail(id) {
  var book = null;
  for (var i = 0; i < books.length; i++) {
    if (books[i].id === id) { book = books[i]; break; }
  }
  if (!book) return;
  document.getElementById('detailTitle').textContent  = book.title;
  document.getElementById('detailCover').className    = 'detail-cover ' + book.color;
  document.getElementById('detailCover').textContent  = book.title;
  document.getElementById('detailAuthor').textContent = book.author;
  document.getElementById('detailGenre').textContent  = book.genre;
  document.getElementById('detailYear').textContent   = book.year;
  document.getElementById('detailIsbn').textContent   = book.isbn || '—';
  document.getElementById('detailDesc').textContent   = book.desc || '';
  document.getElementById('detailModal').classList.add('open');
}

// ── RENDER ADMIN TABLE ──
function renderAdmin() {
  // Load live stats first
  loadStats();

  var tbody = document.getElementById('adminTableBody');
  tbody.innerHTML = '';

  for (var i = 0; i < books.length; i++) {
    var book = books[i];
    var tr   = document.createElement('tr');
    tr.innerHTML =
      '<td><div class="mini-cover ' + book.color + '"></div></td>' +
      '<td><strong>' + book.title + '</strong></td>' +
      '<td>' + book.author + '</td>' +
      '<td><span class="badge badge-genre">' + book.genre + '</span></td>' +
      '<td>' + book.year + '</td>' +
      '<td><div class="action-btns">' +
        '<button class="btn-edit" onclick="openEditBook(' + book.id + ')">✏ Edit</button>' +
        '<button class="btn-del"  onclick="openDeleteBook(' + book.id + ')">🗑 Delete</button>' +
      '</div></td>';
    tbody.appendChild(tr);
  }
}

// ── ADD / EDIT BOOK ──
function openAddBook() {
  editingBookId = null;
  document.getElementById('bookFormTitle').textContent = 'Add New Book';
  document.getElementById('fTitle').value  = '';
  document.getElementById('fAuthor').value = '';
  document.getElementById('fIsbn').value   = '';
  document.getElementById('fDesc').value   = '';
  document.getElementById('fYear').value   = new Date().getFullYear();
  document.getElementById('fGenre').value  = 'Fiction';
  document.getElementById('bookFormModal').classList.add('open');
}

function openEditBook(id) {
  editingBookId = id;
  var book = null;
  for (var i = 0; i < books.length; i++) {
    if (books[i].id === id) { book = books[i]; break; }
  }
  document.getElementById('bookFormTitle').textContent = 'Edit Book';
  document.getElementById('fTitle').value  = book.title;
  document.getElementById('fAuthor').value = book.author;
  document.getElementById('fGenre').value  = book.genre;
  document.getElementById('fYear').value   = book.year;
  document.getElementById('fIsbn').value   = book.isbn;
  document.getElementById('fDesc').value   = book.desc || '';
  document.getElementById('bookFormModal').classList.add('open');
}

function saveBook() {
  var title  = document.getElementById('fTitle').value.trim();
  var author = document.getElementById('fAuthor').value.trim();
  if (!title || !author) { showToast('Title and Author are required.'); return; }

  var payload = {
    action: editingBookId ? 'edit' : 'add',
    title:  title,
    author: author,
    genre:  document.getElementById('fGenre').value,
    year:   parseInt(document.getElementById('fYear').value) || 2024,
    isbn:   document.getElementById('fIsbn').value.trim(),
    desc:   document.getElementById('fDesc').value.trim(),
  };
  if (editingBookId) payload.id = editingBookId;

  fetch('book_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) {
      showToast(editingBookId ? 'Book updated!' : 'Book added!');
      closeModal('bookFormModal');
      loadBooks(function() { renderAdmin(); renderLibrary(); });
    } else {
      showToast('Something went wrong.');
    }
  })
  .catch(function() { showToast('Request failed.'); });
}

// ── DELETE BOOK ──
function openDeleteBook(id) {
  deletingBookId = id;
  document.getElementById('deleteModal').classList.add('open');
}

function confirmDelete() {
  fetch('book_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'delete', id: deletingBookId })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) {
      deletingBookId = null;
      closeModal('deleteModal');
      loadBooks(function() { renderAdmin(); renderLibrary(); });
      showToast('Book deleted.');
    }
  })
  .catch(function() { showToast('Delete failed.'); });
}

// ── UTILS ──
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

function showToast(msg) {
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(function() { t.classList.remove('show'); }, 3000);
}

var overlays = document.querySelectorAll('.modal-overlay');
for (var i = 0; i < overlays.length; i++) {
  overlays[i].addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
  });
}

// ── RETURNED BOOKS ──
function loadReturnedBooks() {
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getReturnedBooks' })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) { renderReturnedBooks(data); })
  .catch(function() {
    var tbody = document.getElementById('returnedBooksBody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-secondary);padding:32px;">Failed to load returned books.</td></tr>';
  });
}

var conditionColors = {
  excellent: '#27ae60',
  good:      '#2980b9',
  fair:      '#f39c12',
  bad:       '#e67e22',
  damaged:   '#c0392b'
};

function renderReturnedBooks(returns) {
  var tbody = document.getElementById('returnedBooksBody');
  if (!tbody) return;
  tbody.innerHTML = '';

  if (!returns || returns.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-secondary);padding:32px;">No books have been returned yet.</td></tr>';
    return;
  }

  for (var i = 0; i < returns.length; i++) {
    var r = returns[i];
    var color = conditionColors[r.condition_status] || '#888';
    var condLabel = r.condition_status.charAt(0).toUpperCase() + r.condition_status.slice(1);
    var returnedAt = r.returned_at ? new Date(r.returned_at).toLocaleString('en-US', {
      year: 'numeric', month: 'short', day: 'numeric',
      hour: '2-digit', minute: '2-digit'
    }) : '—';
    var desc = r.description ? r.description : '<span style="color:var(--text-secondary);font-style:italic;">—</span>';
    var fullName = (r.first_name || '') + ' ' + (r.last_name || '');
    if (fullName.trim() === '') fullName = r.username || '—';

    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td><strong>' + escHtml(r.book_title) + '</strong></td>' +
      '<td>' + escHtml(r.book_author) + '</td>' +
      '<td>' + escHtml(fullName) + '</td>' +
      '<td><span style="display:inline-block;padding:3px 12px;border-radius:20px;font-size:0.8rem;font-weight:600;color:#fff;background:' + color + ';">' + condLabel + '</span></td>' +
      '<td style="max-width:220px;word-break:break-word;">' + (r.description ? escHtml(r.description) : '<span style="color:var(--text-secondary);font-style:italic;">—</span>') + '</td>' +
      '<td style="white-space:nowrap;">' + returnedAt + '</td>';
    tbody.appendChild(tr);
  }
}

function escHtml(str) {
  if (!str) return '';
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ── INIT ──
loadBooks(function() {
  renderLibrary();
  renderAdmin();
});

// ── SHOW PENDING BORROW REQUESTS COUNT ON DASHBOARD ──
function loadPendingCount() {
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getStats' })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.pendingCount && data.pendingCount > 0) {
      var card = document.querySelector('.dash-panel.panel-red');
      if (card) {
        var existing = card.querySelector('.dash-pending-badge');
        if (!existing) {
          var badge = document.createElement('div');
          badge.className = 'dash-pending-badge';
          badge.style.cssText = 'position:absolute;top:14px;right:14px;background:#fff;color:#c0392b;border-radius:12px;padding:2px 9px;font-size:0.78rem;font-weight:700;font-family:var(--font-body);';
          badge.textContent = data.pendingCount + ' pending';
          card.style.position = 'relative';
          card.appendChild(badge);
        } else {
          existing.textContent = data.pendingCount + ' pending';
        }
      }
    }
  })
  .catch(function() {});
}

loadPendingCount();


// ── BORROWED BOOKS (ADMIN) ──
var borrowedBooksData = [];

function loadBorrowedBooks() {
  var tbody = document.getElementById('borrowedBooksBody');
  if (tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-secondary);padding:32px;">Loading...</td></tr>';

  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getAllBorrowedBooks' })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    borrowedBooksData = data;
    renderBorrowedBooks(data);
  })
  .catch(function() {
    if (tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-secondary);padding:32px;">Failed to load borrowed books.</td></tr>';
  });
}

function renderBorrowedBooks(list) {
  var tbody = document.getElementById('borrowedBooksBody');
  if (!tbody) return;
  tbody.innerHTML = '';

  if (!list || list.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-secondary);padding:32px;">No books are currently borrowed.</td></tr>';
    return;
  }

  for (var i = 0; i < list.length; i++) {
    var r = list[i];

    // Days left calculation
    var today = new Date();
    today.setHours(0, 0, 0, 0);
    var due = r.due_date ? new Date(r.due_date + 'T00:00:00') : null;
    var daysLeft = due ? Math.round((due - today) / (1000 * 60 * 60 * 24)) : null;

    var daysLeftHtml;
    if (daysLeft === null) {
      daysLeftHtml = '<span style="color:var(--text-secondary);">—</span>';
    } else if (daysLeft < 0) {
      daysLeftHtml = '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:0.8rem;font-weight:600;color:#fff;background:#c0392b;">⚠ Overdue ' + Math.abs(daysLeft) + 'd</span>';
    } else if (daysLeft === 0) {
      daysLeftHtml = '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:0.8rem;font-weight:600;color:#fff;background:#e53e3e;">Due Today</span>';
    } else if (daysLeft <= 2) {
      daysLeftHtml = '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:0.8rem;font-weight:600;color:#fff;background:#dd6b20;">' + daysLeft + ' day' + (daysLeft > 1 ? 's' : '') + ' left</span>';
    } else {
      daysLeftHtml = '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:0.8rem;font-weight:600;color:#fff;background:#38a169;">' + daysLeft + ' days left</span>';
    }

    // Extension badge
    var extCount = parseInt(r.extension_count) || 0;
    var extDays  = parseInt(r.total_extended_days) || 0;
    var extBadge;
    if (extCount === 0) {
      extBadge = '<span style="color:var(--text-secondary);font-style:italic;font-size:0.88rem;">None</span>';
    } else {
      extBadge = '<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:0.8rem;font-weight:600;background:#ebf4ff;color:#2b6cb0;cursor:pointer;" onclick="openExtensionDetail(' + r.borrowing_id + ')">+' + extDays + 'd (' + extCount + 'x) 🔍</span>';
    }

    var borrowDate  = r.borrow_date  ? new Date(r.borrow_date  + 'T00:00:00').toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '—';
    var dueDateFmt  = r.due_date     ? new Date(r.due_date      + 'T00:00:00').toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '—';
    var fullName    = escHtml((r.first_name || '') + ' ' + (r.last_name || '')).trim() || escHtml(r.username);

    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td><strong>' + escHtml(r.book_title) + '</strong></td>' +
      '<td>' + escHtml(r.book_author) + '</td>' +
      '<td>' +
        '<div style="font-weight:600;">' + fullName + '</div>' +
        '<div style="font-size:0.8rem;color:var(--text-secondary);">@' + escHtml(r.username) + '</div>' +
      '</td>' +
      '<td style="white-space:nowrap;">' + borrowDate + '</td>' +
      '<td style="white-space:nowrap;">' + dueDateFmt + '</td>' +
      '<td>' + daysLeftHtml + '</td>' +
      '<td>' + extBadge + '</td>' +
      '<td>' +
        (extCount > 0
          ? '<button class="btn-edit" style="font-size:0.8rem;padding:4px 12px;" onclick="openExtensionDetail(' + r.borrowing_id + ')">View</button>'
          : '<span style="color:var(--text-secondary);font-size:0.85rem;">—</span>') +
      '</td>';
    tbody.appendChild(tr);
  }
}

function openExtensionDetail(borrowingId) {
  var record = null;
  for (var i = 0; i < borrowedBooksData.length; i++) {
    if (parseInt(borrowedBooksData[i].borrowing_id) === borrowingId) {
      record = borrowedBooksData[i];
      break;
    }
  }
  if (!record) return;

  var fullName = ((record.first_name || '') + ' ' + (record.last_name || '')).trim() || record.username;
  document.getElementById('extDetailTitle').textContent = 'Extension History — ' + record.book_title;

  var borrowDate = record.borrow_date ? new Date(record.borrow_date + 'T00:00:00').toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '—';
  var dueDate    = record.due_date    ? new Date(record.due_date    + 'T00:00:00').toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '—';

  document.getElementById('extDetailBorrowInfo').innerHTML =
    '<strong>' + escHtml(record.book_title) + '</strong> &nbsp;·&nbsp; ' +
    'Borrowed by <strong>' + escHtml(fullName) + '</strong> (@' + escHtml(record.username) + ')' +
    '<br>Borrowed: <strong>' + borrowDate + '</strong> &nbsp;·&nbsp; Current Due: <strong>' + dueDate + '</strong>' +
    '&nbsp;·&nbsp; Total extended: <strong>+' + (record.total_extended_days || 0) + ' day(s)</strong>';

  var listEl = document.getElementById('extDetailList');
  listEl.innerHTML = '';

  var exts = record.extensions || [];
  if (exts.length === 0) {
    listEl.innerHTML = '<p style="color:var(--text-secondary);text-align:center;padding:18px;">No extensions recorded.</p>';
  } else {
    for (var i = 0; i < exts.length; i++) {
      var e = exts[i];
      var reqAt = e.requested_at ? new Date(e.requested_at).toLocaleString('en-US', {month:'short', day:'numeric', year:'numeric', hour:'2-digit', minute:'2-digit'}) : '—';
      var oldDue = e.old_due_date ? new Date(e.old_due_date + 'T00:00:00').toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '—';
      var newDue = e.new_due_date ? new Date(e.new_due_date + 'T00:00:00').toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '—';

      var item = document.createElement('div');
      item.style.cssText = 'border:1px solid var(--border,#e8e5df);border-radius:10px;padding:14px 18px;margin-bottom:12px;';
      item.innerHTML =
        '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">' +
          '<span style="font-weight:700;color:var(--text-primary);font-size:0.95rem;">Extension #' + (i + 1) + ' &nbsp;·&nbsp; <span style="color:#2b6cb0;">+' + e.extend_days + ' day' + (parseInt(e.extend_days) > 1 ? 's' : '') + '</span></span>' +
          '<span style="font-size:0.8rem;color:var(--text-secondary);">' + reqAt + '</span>' +
        '</div>' +
        '<div style="font-size:0.88rem;color:var(--text-secondary);margin-bottom:6px;">' +
          '<span style="margin-right:16px;">Old due: <strong style="color:var(--text-primary);">' + oldDue + '</strong></span>' +
          '<span>New due: <strong style="color:#27ae60;">' + newDue + '</strong></span>' +
        '</div>' +
        '<div style="background:var(--bg-subtle,#f8f8f6);border-radius:7px;padding:8px 12px;font-size:0.9rem;margin-top:6px;">' +
          '<span style="color:var(--text-secondary);font-style:italic;">Reason: </span>' +
          '<span style="color:var(--text-primary);">' + escHtml(e.reason) + '</span>' +
        '</div>';
      listEl.appendChild(item);
    }
  }

  document.getElementById('extensionDetailModal').classList.add('open');
}
