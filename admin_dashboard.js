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
  var views = ['Dashboard', 'Library', 'Admin'];
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

// ── INIT ──
loadBooks(function() {
  renderLibrary();
  renderAdmin();
});
