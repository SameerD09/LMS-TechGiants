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
          color:  b.color || 'color-1'
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
  var views = ['Dashboard', 'Library', 'Admin', 'ReturnedBooks', 'BorrowedBooks', 'Reviews', 'PendingReturns', 'Analytics'];
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

  if (name === 'ReturnedBooks')   loadReturnedBooks();
  if (name === 'BorrowedBooks')   loadBorrowedBooks();
  if (name === 'Reviews')         loadReviews();
  if (name === 'PendingReturns')  loadPendingReturns();
  if (name === 'Analytics')       loadAnalytics();
}

// ── PENDING RETURNS ───────────────────────────────────────────
var _currentReturnData = null;
var _pendingReturnsCache = [];

function loadPendingReturns() {
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getPendingReturns' })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    var returns = data.returns || [];
    renderPendingReturns(returns);
    var badge = document.getElementById('pendingReturnsBadge');
    if (badge) badge.textContent = returns.length > 0 ? returns.length + ' awaiting verification' : 'Awaiting Verification';
  })
  .catch(function() {
    document.getElementById('pendingReturnsBody').innerHTML =
      '<tr><td colspan="6" style="text-align:center;color:#e53e3e;padding:32px;">Failed to load pending returns.</td></tr>';
  });
}

function renderPendingReturns(returns) {
  _pendingReturnsCache = returns;
  var tbody = document.getElementById('pendingReturnsBody');
  if (!returns.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-secondary);padding:36px;">No pending returns — all clear!</td></tr>';
    return;
  }
  var condColors = { excellent:'#27ae60', good:'#2980b9', fair:'#f39c12', bad:'#e67e22', damaged:'#c0392b' };
  var html = '';
  returns.forEach(function(r, idx) {
    var color = condColors[r.user_condition] || '#888';
    var date  = r.returned_at ? new Date(r.returned_at.replace(' ','T')).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '—';
    html +=
      '<tr>' +
        '<td><strong>' + escHtml(r.book_title) + '</strong><br><span style="font-size:0.8rem;color:var(--text-secondary);">' + escHtml(r.book_author) + '</span></td>' +
        '<td>' + escHtml(r.first_name + ' ' + r.last_name) + '<br><span style="font-size:0.8rem;color:var(--text-secondary);">@' + escHtml(r.username) + '</span></td>' +
        '<td><span style="font-weight:700;color:' + color + ';">' + r.user_condition.charAt(0).toUpperCase() + r.user_condition.slice(1) + '</span></td>' +
        '<td style="max-width:180px;word-break:break-word;">' + (r.user_description ? escHtml(r.user_description) : '<em style="color:#aaa;">None</em>') + '</td>' +
        '<td style="white-space:nowrap;">' + date + '</td>' +
        '<td><button class="btn-primary" style="font-size:0.82rem;padding:6px 14px;" onclick="openApproveModal(' + idx + ')">Verify & Approve</button></td>' +
      '</tr>';
  });
  tbody.innerHTML = html;
}

function openApproveModal(idx) {
  var r = _pendingReturnsCache[idx];
  _currentReturnData = r;
  document.getElementById('arBook').textContent         = r.book_title + ' — ' + r.book_author;
  document.getElementById('arUser').textContent         = r.first_name + ' ' + r.last_name + ' (@' + r.username + ')';
  document.getElementById('arUserCondition').textContent = r.user_condition.charAt(0).toUpperCase() + r.user_condition.slice(1);
  document.getElementById('arRealCondition').value      = '';
  document.getElementById('arAdminNotes').value         = '';
  var descEl = document.getElementById('arUserDesc');
  if (r.user_description) {
    document.getElementById('arUserDescText').textContent = ' ' + r.user_description;
    descEl.style.display = 'block';
  } else {
    descEl.style.display = 'none';
  }
  document.getElementById('arError').style.display = 'none';
  document.getElementById('approveReturnModal').classList.add('open');
}

function submitApproveReturn() {
  var realCond   = document.getElementById('arRealCondition').value;
  var adminNotes = document.getElementById('arAdminNotes').value.trim();
  var errEl      = document.getElementById('arError');

  if (!realCond) {
    errEl.textContent = 'Please select the real condition of the book.';
    errEl.style.display = 'block';
    return;
  }
  errEl.style.display = 'none';

  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action:         'approveReturn',
      return_id:      _currentReturnData.return_id,
      real_condition: realCond,
      admin_notes:    adminNotes
    })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.success) {
      closeModal('approveReturnModal');
      loadPendingReturns();
      var msg = '✅ Return approved.';
      if (data.total_fine > 0) {
        msg += ' Fine charged: Rs ' + Number(data.total_fine).toLocaleString('en-IN', {minimumFractionDigits:2});
      } else {
        msg += ' No fines.';
      }
      alert(msg);
    } else {
      errEl.textContent = data.error || 'Could not approve return.';
      errEl.style.display = 'block';
    }
  })
  .catch(function() {
    errEl.textContent = 'Network error. Please try again.';
    errEl.style.display = 'block';
  });
}

function loadReviews() {
  fetch('reviews_api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getAllReviews' })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) { renderReviews(data.reviews || []); })
  .catch(function() {
    document.getElementById('reviewsBody').innerHTML =
      '<tr><td colspan="5" style="text-align:center;color:#e53e3e;padding:32px;">Failed to load reviews.</td></tr>';
  });
}

function renderReviews(reviews) {
  var tbody = document.getElementById('reviewsBody');
  if (!reviews.length) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-secondary);padding:32px;">No reviews yet.</td></tr>';
    return;
  }
  var html = '';
  reviews.forEach(function(r) {
    var stars = '';
    for (var s = 1; s <= 5; s++) stars += s <= r.rating ? '★' : '☆';
    var date = r.created_at ? new Date(r.created_at.replace(' ', 'T')).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
    html +=
      '<tr>' +
        '<td><strong>' + escHtml(r.book_title) + '</strong><br><span style="font-size:0.8rem;color:var(--text-secondary);">' + escHtml(r.book_author) + '</span></td>' +
        '<td>' + escHtml(r.first_name + ' ' + r.last_name) + '<br><span style="font-size:0.8rem;color:var(--text-secondary);">@' + escHtml(r.username) + '</span></td>' +
        '<td><span style="color:#f59e0b;font-size:1.1rem;letter-spacing:1px;">' + stars + '</span><br><span style="font-size:0.78rem;color:var(--text-secondary);">' + r.rating + '/5</span></td>' +
        '<td style="max-width:220px;white-space:pre-wrap;word-break:break-word;">' + (r.review ? escHtml(r.review) : '<em style="color:#aaa;">No written review</em>') + '</td>' +
        '<td style="white-space:nowrap;">' + date + '</td>' +
      '</tr>';
  });
  tbody.innerHTML = html;
}

function escHtml(s) {
  return String(s).replace(/[&<>"']/g, function(c) {
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
  });
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
  document.getElementById('fColor').value  = 'color-1';
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
  document.getElementById('fColor').value  = book.color || 'color-1';
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
    color:  document.getElementById('fColor').value,
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

// ── ADMIN: Mark a borrowing as lost ──
function adminMarkLost(borrowingId, bookTitle, bookPrice) {
  var priceStr = bookPrice ? 'Rs ' + Number(bookPrice).toLocaleString('en-IN', {minimumFractionDigits:2}) : 'the full book price';
  if (!confirm('Confirm "' + bookTitle + '" as lost?\n\n' + priceStr + ' will be charged to the user. This cannot be undone.')) return;
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'adminMarkLost', borrowing_id: borrowingId })
  })
  .then(function(r) { return r.json(); })
  .then(function(d) {
    if (d.success) {
      alert('Book marked as lost. Rs ' + Number(d.book_price).toLocaleString('en-IN', {minimumFractionDigits:2}) + ' charged to user.');
      // Reload borrowed books list
      switchView('BorrowedBooks');
    } else {
      alert('Error: ' + (d.error || 'Could not mark as lost.'));
    }
  });
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
    function applyBadge(selector, count, color) {
      if (!count) return;
      var card = document.querySelector(selector);
      if (!card) return;
      var existing = card.querySelector('.dash-pending-badge');
      if (!existing) {
        var badge = document.createElement('div');
        badge.className = 'dash-pending-badge';
        badge.style.cssText = 'position:absolute;top:14px;right:14px;background:#fff;color:' + color + ';border-radius:12px;padding:2px 9px;font-size:0.78rem;font-weight:700;font-family:var(--font-body);';
        card.style.position = 'relative';
        card.appendChild(badge);
        existing = badge;
      }
      existing.textContent = count + ' pending';
    }
    applyBadge('.dash-panel.panel-red',    data.pendingCount,        '#c0392b');
    applyBadge('.dash-panel.panel-orange', data.pendingReturnsCount, '#b7520a');

    var retBadge = document.getElementById('pendingReturnsBadge');
    if (retBadge && data.pendingReturnsCount > 0) {
      retBadge.textContent = data.pendingReturnsCount + ' awaiting verification';
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

    var isLostReported = parseInt(r.lost_reported) ? true : false;
    var tr = document.createElement('tr');
    if (isLostReported) tr.style.cssText = 'background:#fff5f5;';
    var lostBadge = isLostReported ? ' <span style="background:#fed7d7;color:#c53030;font-size:0.72rem;padding:2px 7px;border-radius:12px;font-weight:600;vertical-align:middle;">🚨 Lost Reported</span>' : '';
    tr.innerHTML =
      '<td><strong>' + escHtml(r.book_title) + '</strong>' + lostBadge + '</td>' +
      '<td>' + escHtml(r.book_author) + '</td>' +
      '<td>' +
        '<div style="font-weight:600;">' + fullName + '</div>' +
        '<div style="font-size:0.8rem;color:var(--text-secondary);">@' + escHtml(r.username) + '</div>' +
      '</td>' +
      '<td style="white-space:nowrap;">' + borrowDate + '</td>' +
      '<td style="white-space:nowrap;">' + dueDateFmt + '</td>' +
      '<td>' + daysLeftHtml + '</td>' +
      '<td>' + extBadge + '</td>' +
      '<td style="white-space:nowrap;">' +
        '<button class="btn-edit" style="font-size:0.8rem;padding:4px 10px;margin-right:4px;" onclick="openBorrowedBookDetail(' + r.borrowing_id + ')">View</button>' +
        (isLostReported ? '<button class="btn-danger" style="font-size:0.8rem;padding:4px 10px;" onclick="adminMarkLost(' + r.borrowing_id + ', \'' + escHtml(r.book_title).replace(/'/g,"&#39;") + '\', ' + (parseFloat(r.book_price) || 1500) + ')">🚨 Confirm Lost</button>' : '') +
      '</td>';
    tbody.appendChild(tr);
  }
}

function openBorrowedBookDetail(borrowingId) {
  var record = null;
  for (var i = 0; i < borrowedBooksData.length; i++) {
    if (parseInt(borrowedBooksData[i].borrowing_id) === borrowingId) {
      record = borrowedBooksData[i];
      break;
    }
  }
  if (!record) return;

  var extCount = parseInt(record.extension_count) || 0;

  if (extCount > 0) {
    // If there are extensions, show extension detail
    openExtensionDetail(borrowingId);
  } else {
    // No extensions — show a simple borrow info modal
    var fullName = ((record.first_name || '') + ' ' + (record.last_name || '')).trim() || record.username;
    document.getElementById('extDetailTitle').textContent = 'Borrow Details — ' + record.book_title;

    var borrowDate = record.borrow_date ? new Date(record.borrow_date + 'T00:00:00').toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '—';
    var dueDate    = record.due_date    ? new Date(record.due_date    + 'T00:00:00').toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '—';

    var today = new Date(); today.setHours(0,0,0,0);
    var due   = record.due_date ? new Date(record.due_date + 'T00:00:00') : null;
    var daysLeft = due ? Math.round((due - today) / (1000 * 60 * 60 * 24)) : null;
    var statusLabel = daysLeft === null ? '—' : (daysLeft < 0 ? '⚠ Overdue by ' + Math.abs(daysLeft) + ' day(s)' : daysLeft === 0 ? 'Due Today' : daysLeft + ' day(s) left');

    document.getElementById('extDetailBorrowInfo').innerHTML =
      '<strong>' + escHtml(record.book_title) + '</strong> &nbsp;·&nbsp; ' +
      'Borrowed by <strong>' + escHtml(fullName) + '</strong> (@' + escHtml(record.username) + ')' +
      '<br>Borrowed: <strong>' + borrowDate + '</strong> &nbsp;·&nbsp; Due: <strong>' + dueDate + '</strong>' +
      '<br>Status: <strong>' + statusLabel + '</strong>' +
      '<br>Genre: <strong>' + escHtml(record.book_genre || '—') + '</strong> &nbsp;·&nbsp; Author: <strong>' + escHtml(record.book_author || '—') + '</strong>';

    var listEl = document.getElementById('extDetailList');
    listEl.innerHTML = '<p style="color:var(--text-secondary);text-align:center;padding:18px;">No extensions for this borrowing.</p>';

    document.getElementById('extensionDetailModal').classList.add('open');
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

// ══════════════════════════════════════════════════════════════
// TRANSACTIONS MODAL
// ══════════════════════════════════════════════════════════════
var allTransactions = [];

function openTransactionsModal() {
  document.getElementById('transactionsModal').classList.add('open');
  loadTransactions();
  loadAdminBalance();
}

function loadAdminBalance() {
  fetch('wallet_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getAdminBalance' })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    var bal = data.balance || 0;
    var el = document.getElementById('adminSidebarBalance');
    if (el) el.textContent = 'Rs ' + Number(bal).toLocaleString('en-IN', {minimumFractionDigits:2});
    var tot = document.getElementById('txAdminTotal');
    if (tot) tot.textContent = 'Rs ' + Number(bal).toLocaleString('en-IN', {minimumFractionDigits:2});
  })
  .catch(function() {});
}

function loadTransactions() {
  document.getElementById('txTableBody').innerHTML =
    '<tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-secondary);">Loading...</td></tr>';

  fetch('wallet_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getAllTransactions' })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    allTransactions = data;
    computeSummary(data);
    renderTransactions(data);
  })
  .catch(function() {
    document.getElementById('txTableBody').innerHTML =
      '<tr><td colspan="5" style="text-align:center;padding:24px;color:#e53e3e;">Failed to load transactions.</td></tr>';
  });
}

function computeSummary(txs) {
  var borrowTotal = 0, fineTotal = 0, pdfTotal = 0;
  for (var i = 0; i < txs.length; i++) {
    var amt = Math.abs(parseFloat(txs[i].amount));
    if (txs[i].type === 'borrow_fee' || txs[i].type === 'extension_fee') borrowTotal += amt;
    else if (txs[i].type === 'overdue_fine' || txs[i].type === 'damage_fine' || txs[i].type === 'lost_book') fineTotal += amt;
    else if (txs[i].type === 'pdf_purchase') pdfTotal += amt;
  }
  function fmt(n) { return 'Rs ' + Number(n).toLocaleString('en-IN', {minimumFractionDigits:2}); }
  var bEl = document.getElementById('txBorrowTotal');
  var fEl = document.getElementById('txFineTotal');
  var pEl = document.getElementById('txPdfTotal');
  if (bEl) bEl.textContent = fmt(borrowTotal);
  if (fEl) fEl.textContent = fmt(fineTotal);
  if (pEl) pEl.textContent = fmt(pdfTotal);
}

function renderTransactions(txs) {
  var tbody = document.getElementById('txTableBody');
  if (!txs || txs.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-secondary);">No transactions yet.</td></tr>';
    return;
  }
  var typeLabels = {
    borrow_fee:    '📖 Borrow Fee',
    extension_fee: '🔁 Extension Fee',
    pdf_purchase:  '📄 PDF Purchase',
    overdue_fine:  '⏰ Overdue Fine',
    damage_fine:   '⚠️ Damage Fine',
    lost_book:     '🚨 Lost Book',
    top_up:        '💚 Top Up'
  };
  var typeBadgeColors = {
    borrow_fee:    '#dbeafe',
    extension_fee: '#e0e7ff',
    pdf_purchase:  '#ede9fe',
    overdue_fine:  '#fee2e2',
    damage_fine:   '#fef3c7',
    lost_book:     '#fce7f3',
    top_up:        '#dcfce7'
  };
  var html = '';
  for (var i = 0; i < txs.length; i++) {
    var tx   = txs[i];
    var amt  = parseFloat(tx.amount);
    var isIn = amt > 0; // top_up is positive from user perspective
    var label = typeLabels[tx.type] || tx.type;
    var bgColor = typeBadgeColors[tx.type] || '#f3f4f6';
    var dt = new Date(tx.created_at);
    var dtFmt = dt.toLocaleDateString('en-IN', {day:'2-digit',month:'short',year:'numeric'}) + ' ' +
                dt.toLocaleTimeString('en-IN', {hour:'2-digit',minute:'2-digit'});
    html += '<tr>' +
      '<td style="font-size:0.82rem;color:var(--text-secondary);">' + escHtml(dtFmt) + '</td>' +
      '<td><strong>' + escHtml(tx.first_name + ' ' + tx.last_name) + '</strong><br><span style="font-size:0.8rem;color:var(--text-secondary);">@' + escHtml(tx.username) + '</span></td>' +
      '<td><span style="background:' + bgColor + ';padding:3px 10px;border-radius:20px;font-size:0.8rem;font-weight:600;white-space:nowrap;">' + label + '</span></td>' +
      '<td style="font-size:0.88rem;color:var(--text-secondary);">' + escHtml(tx.book_title || '—') + '</td>' +
      '<td style="font-weight:700;color:' + (amt < 0 ? '#e53e3e' : '#16a34a') + ';white-space:nowrap;">' +
        (amt < 0 ? '−' : '+') + 'Rs ' + Number(Math.abs(amt)).toLocaleString('en-IN', {minimumFractionDigits:2}) +
      '</td>' +
    '</tr>';
  }
  tbody.innerHTML = html;
}

function filterTransactions() {
  var q    = (document.getElementById('txSearch').value || '').toLowerCase();
  var type = document.getElementById('txTypeFilter').value;
  var filtered = allTransactions.filter(function(tx) {
    var matchType = !type || tx.type === type;
    var matchQ    = !q ||
      (tx.first_name + ' ' + tx.last_name).toLowerCase().includes(q) ||
      (tx.username || '').toLowerCase().includes(q) ||
      (tx.book_title || '').toLowerCase().includes(q) ||
      (tx.type || '').toLowerCase().includes(q) ||
      (tx.description || '').toLowerCase().includes(q);
    return matchType && matchQ;
  });
  renderTransactions(filtered);
}

// Load admin balance on page load
window.addEventListener('load', function() {
  loadAdminBalance();
});

// ══════════════════════════════════════════════════════════════
// ANALYTICS
// ══════════════════════════════════════════════════════════════
var _chartBorrowings = null;
var _chartRevenue    = null;
var _chartTopBooks   = null;

var _typeLabels = {
  borrow_fee:    'Borrow Fees',
  extension_fee: 'Extension Fees',
  pdf_purchase:  'PDF Sales',
  overdue_fine:  'Overdue Fines',
  damage_fine:   'Damage Fines',
  lost_book:     'Lost Book'
};

var _chartColors = [
  '#5c3d1e', '#8b5e3c', '#b8860b', '#2980b9',
  '#27ae60', '#8e44ad', '#c0392b', '#16a085'
];

function loadAnalytics() {
  loadAnalyticsSummary();
  loadBorrowingsChart();
  loadRevenueChart();
  loadTopBooksChart();
}

function _analyticsPost(action, callback) {
  fetch('analytics_api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: action })
  })
  .then(function(r) { return r.json(); })
  .then(callback)
  .catch(function(e) { console.error('Analytics error (' + action + '):', e); });
}

function loadAnalyticsSummary() {
  _analyticsPost('summaryStats', function(d) {
    var rev = document.getElementById('aStatRevenue');
    var bor = document.getElementById('aStatBorrowers');
    var ov  = document.getElementById('aStatOverdue');
    var usr = document.getElementById('aStatUsers');
    if (rev) rev.textContent = 'Rs ' + Number(d.revenue).toLocaleString('en-IN', {minimumFractionDigits: 2});
    if (bor) bor.textContent = d.activeBorrowers;
    if (ov)  ov.textContent  = d.overdue;
    if (usr) usr.textContent = d.totalUsers;
  });
}

function loadBorrowingsChart() {
  _analyticsPost('monthlyBorrowings', function(res) {
    var rows   = res.data || [];
    var labels = rows.map(function(r) { return r.month; });
    var counts = rows.map(function(r) { return parseInt(r.count); });

    var ctx = document.getElementById('chartBorrowings');
    if (!ctx) return;
    if (_chartBorrowings) _chartBorrowings.destroy();

    _chartBorrowings = new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Borrowings',
          data: counts,
          borderColor: '#5c3d1e',
          backgroundColor: 'rgba(92,61,30,0.08)',
          borderWidth: 2.5,
          pointBackgroundColor: '#5c3d1e',
          pointRadius: 4,
          tension: 0.35,
          fill: true
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
      }
    });
  });
}

function loadRevenueChart() {
  _analyticsPost('revenueBreakdown', function(res) {
    var rows   = res.data || [];
    var labels = rows.map(function(r) { return _typeLabels[r.type] || r.type; });
    var totals = rows.map(function(r) { return parseFloat(r.total); });

    var ctx = document.getElementById('chartRevenue');
    if (!ctx) return;
    if (_chartRevenue) _chartRevenue.destroy();

    _chartRevenue = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: totals,
          backgroundColor: _chartColors.slice(0, rows.length),
          borderWidth: 2,
          borderColor: '#fff'
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12 } },
          tooltip: {
            callbacks: {
              label: function(ctx) {
                return ' Rs ' + Number(ctx.raw).toLocaleString('en-IN', {minimumFractionDigits: 2});
              }
            }
          }
        }
      }
    });
  });
}

function loadTopBooksChart() {
  _analyticsPost('topBooks', function(res) {
    var rows   = res.data || [];
    var labels = rows.map(function(r) {
      return r.title.length > 28 ? r.title.substring(0, 26) + '…' : r.title;
    });
    var counts = rows.map(function(r) { return parseInt(r.borrow_count); });

    var ctx = document.getElementById('chartTopBooks');
    if (!ctx) return;
    if (_chartTopBooks) _chartTopBooks.destroy();

    _chartTopBooks = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Times Borrowed',
          data: counts,
          backgroundColor: _chartColors,
          borderRadius: 6,
          borderSkipped: false
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          x: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
      }
    });
  });
}

// ── Weekly digest trigger ─────────────────────────────────────
function sendWeeklyDigest() {
  var btn = document.getElementById('sendDigestBtn');
  if (btn) { btn.textContent = '⏳ Sending…'; btn.disabled = true; }

  fetch('weekly_digest.php', { method: 'POST' })
  .then(function(r) { return r.json(); })
  .then(function(d) {
    if (btn) { btn.textContent = '📧 Send Weekly Digest'; btn.disabled = false; }
    if (d.error) {
      showToast('Error: ' + d.error);
    } else {
      showToast('Digest sent — ' + d.sent + ' succeeded, ' + d.failed + ' failed out of ' + d.total + ' users.');
    }
  })
  .catch(function() {
    if (btn) { btn.textContent = '📧 Send Weekly Digest'; btn.disabled = false; }
    showToast('Failed to send digest. Check PHP error log.');
  });
}
