// ── DATA ──
var books          = [];
var borrowedBooks  = []; // book IDs this user currently has borrowed (from DB)
var allBorrowedIds = []; // book IDs borrowed by ANYONE (for availability display)
var favoriteBooks  = [];
var currentBookId  = null;
var activeGenre    = 'All';

var genres = ['All', 'Fiction', 'Fantasy', 'Design', 'Technology', 'Biography', 'History', 'Non-Fiction', 'Self-Help'];

// ── LOAD BOOKS ──
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
    .catch(function() { console.error('Failed to load books.'); });
}

// ── LOAD MY BORROWINGS FROM DB ──
function loadBorrowings(callback) {
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getMyBorrowings' })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    borrowedBooks = data;
    if (callback) callback();
  })
  .catch(function() { console.error('Failed to load borrowings.'); });
}

// ── LOAD ALL BORROWED IDS (anyone) ──
function loadAllBorrowedIds(callback) {
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getAllBorrowedBookIds' })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    allBorrowedIds = data;
    if (callback) callback();
  })
  .catch(function() { console.error('Failed to load all borrowed ids.'); });
}

// ── LOAD FAVORITES FROM DB ──
function loadFavorites(callback) {
  fetch('favorite_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getFavorites' })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    favoriteBooks = data;
    if (callback) callback();
  })
  .catch(function() { console.error('Failed to load favorites.'); });
}

// ── NAVIGATION ──
function uSwitchView(name) {
  var views = ['Bookstore', 'Reading', 'Favorites', 'Authors'];
  for (var i = 0; i < views.length; i++) {
    var v = document.getElementById('uView' + views[i]);
    var n = document.getElementById('uNav' + views[i]);
    if (v) v.classList.remove('active');
    if (n) n.classList.remove('active');
  }
  var av = document.getElementById('uView' + name);
  var an = document.getElementById('uNav' + name);
  if (av) av.classList.add('active');
  if (an) an.classList.add('active');

  if (name === 'Reading')   renderReadingList();
  if (name === 'Authors')   renderAuthors();
  if (name === 'Favorites') renderFavorites();
}

// ── SEARCH ──
function uHandleSearch() {
  var q = document.getElementById('uSearchInput').value.toLowerCase();
  uSwitchView('Bookstore');
  renderBooksGrid(q);
}

// ── GENRE TABS ──
function renderGenreTabs() {
  var tabs = document.getElementById('uGenreTabs');
  tabs.innerHTML = '';
  for (var i = 0; i < genres.length; i++) {
    var tab = document.createElement('span');
    tab.className = 'u-genre-tab' + (genres[i] === activeGenre ? ' active' : '');
    tab.textContent = genres[i];
    tab.onclick = (function(g) { return function() {
      activeGenre = g;
      renderGenreTabs();
      renderBooksGrid('');
      document.getElementById('uSearchInput').value = '';
    }; })(genres[i]);
    tabs.appendChild(tab);
  }
}

// ── RENDER BOOKS GRID ──
function renderBooksGrid(q) {
  var filtered = books.filter(function(b) {
    var matchGenre = activeGenre === 'All' || b.genre === activeGenre;
    var matchQ = !q || b.title.toLowerCase().indexOf(q) !== -1 || b.author.toLowerCase().indexOf(q) !== -1 || (b.isbn && b.isbn.indexOf(q) !== -1);
    return matchGenre && matchQ;
  });

  var badge = document.getElementById('uBooksCountBadge');
  if (badge) badge.textContent = filtered.length + (filtered.length === 1 ? ' book' : ' books');

  var grid = document.getElementById('uBooksGrid');
  grid.innerHTML = '';

  for (var i = 0; i < filtered.length; i++) {
    var b = filtered[i];
    var card = document.createElement('div');
    card.className = 'u-book-card';
    card.onclick = (function(id) { return function() { openUDetail(id); }; })(b.id);

    var isBorrowedByMe   = borrowedBooks.indexOf(b.id) !== -1;
    var isBorrowedByAny  = allBorrowedIds.indexOf(b.id) !== -1;
    var isFav            = favoriteBooks.indexOf(b.id) !== -1;

    var availBadge;
    if (isBorrowedByMe) {
      availBadge = '<span class="u-avail-badge u-avail-mine">📖 Borrowed by you</span>';
    } else if (isBorrowedByAny) {
      availBadge = '<span class="u-avail-badge u-avail-no">✗ Unavailable</span>';
    } else {
      availBadge = '<span class="u-avail-badge u-avail-yes">✓ Available</span>';
    }

    card.innerHTML =
      '<div class="u-book-cover ' + b.color + '">' + b.title + '</div>' +
      '<div class="u-book-info">' +
        '<div class="u-book-title">' + b.title + '</div>' +
        '<div class="u-book-author">by ' + b.author + '</div>' +
        '<div class="u-book-meta">' +
          '<span class="u-book-genre-tag">' + b.genre + '</span>' +
          availBadge +
          (isFav ? '<span class="u-fav-tag">❤️</span>' : '') +
        '</div>' +
        '<div class="u-book-blurb">' + b.desc + '</div>' +
      '</div>' +
      '<div class="u-book-dots">⋮</div>';
    grid.appendChild(card);
  }

  if (!filtered.length) {
    grid.innerHTML = '<div class="u-empty-msg">No books found. Try a different search or genre.</div>';
  }
}

// ── BOOK DETAIL ──
function openUDetail(id) {
  var book = null;
  for (var i = 0; i < books.length; i++) {
    if (books[i].id === id) { book = books[i]; break; }
  }
  if (!book) return;
  currentBookId = id;

  document.getElementById('uDetailTitle').textContent = book.title;
  document.getElementById('uDetailCover').className   = 'detail-cover ' + book.color;
  document.getElementById('uDetailCover').textContent = book.title;

  var digCover = document.getElementById('uDigitalCover');
  digCover.className   = 'u-digital-cover ' + book.color;
  digCover.textContent = book.title;

  document.getElementById('uDetailAuthor').textContent = book.author;
  document.getElementById('uDetailGenre').textContent  = book.genre;
  document.getElementById('uDetailYear').textContent   = book.year;
  document.getElementById('uDetailIsbn').textContent   = book.isbn || '—';
  document.getElementById('uDetailDesc').textContent   = book.desc || '';

  var isBorrowedByMe  = borrowedBooks.indexOf(id) !== -1;
  var isBorrowedByAny = allBorrowedIds.indexOf(id) !== -1;

  // Status badge
  if (isBorrowedByMe) {
    document.getElementById('uDetailStatus').innerHTML =
      '<span class="badge badge-borrowed">📖 Borrowed by You</span>';
  } else if (isBorrowedByAny) {
    document.getElementById('uDetailStatus').innerHTML =
      '<span class="badge badge-borrowed">✗ Currently Unavailable</span>';
  } else {
    document.getElementById('uDetailStatus').innerHTML =
      '<span class="badge badge-available">✓ Available</span>';
  }

  // Borrow button
  var btn = document.getElementById('uBorrowBtn');
  if (isBorrowedByMe) {
    btn.textContent  = 'Return This Book';
    btn.disabled     = false;
    btn.onclick      = returnBook;
  } else if (isBorrowedByAny) {
    btn.textContent  = 'Not Available';
    btn.disabled     = true;
    btn.onclick      = null;
  } else {
    btn.textContent  = 'Borrow This Book';
    btn.disabled     = false;
    btn.onclick      = borrowBook;
  }

  updateHeartBtn(id);
  loadAndRenderComments(id);
  document.getElementById('uDetailModal').classList.add('open');
}

// ── BORROW ──
function borrowBook() {
  if (!currentBookId) return;
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'borrow', book_id: currentBookId })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) {
      borrowedBooks.push(currentBookId);
      allBorrowedIds.push(currentBookId);
      closeModal('uDetailModal');
      renderBooksGrid(document.getElementById('uSearchInput').value.toLowerCase());
      showToast('📖 Book borrowed successfully!');
    } else {
      showToast(data.error || 'Could not borrow book.');
    }
  })
  .catch(function() { showToast('Request failed.'); });
}

// ── RETURN ──
function returnBook() {
  if (!currentBookId) return;
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'returnBook', book_id: currentBookId })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) {
      borrowedBooks  = borrowedBooks.filter(function(id) { return id !== currentBookId; });
      allBorrowedIds = allBorrowedIds.filter(function(id) { return id !== currentBookId; });
      closeModal('uDetailModal');
      renderBooksGrid(document.getElementById('uSearchInput').value.toLowerCase());
      renderReadingList();
      showToast('✅ Book returned successfully!');
    } else {
      showToast('Could not return book.');
    }
  })
  .catch(function() { showToast('Request failed.'); });
}

// ── QUICK RETURN from Now Reading panel ──
function quickReturn(id) {
  var prev = currentBookId;
  currentBookId = id;
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'returnBook', book_id: id })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) {
      borrowedBooks  = borrowedBooks.filter(function(bid) { return bid !== id; });
      allBorrowedIds = allBorrowedIds.filter(function(bid) { return bid !== id; });
      renderReadingList();
      renderBooksGrid('');
      showToast('✅ Book returned!');
    }
  });
  currentBookId = prev;
}

// ── DOWNLOAD ──
function downloadBook() {
  if (!currentBookId) return;
  var book = null;
  for (var i = 0; i < books.length; i++) {
    if (books[i].id === currentBookId) { book = books[i]; break; }
  }
  if (!book) return;
  document.getElementById('uDownloadPopupTitle').textContent = '"' + book.title + '" Downloaded!';
  document.getElementById('uDownloadPopup').classList.add('open');
}

// ── FAVORITES (DB-backed) ──
function toggleFavorite() {
  if (!currentBookId) return;
  var idx = favoriteBooks.indexOf(currentBookId);
  if (idx === -1) {
    fetch('favorite_actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'addFavorite', book_id: currentBookId })
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (data.success) {
        favoriteBooks.push(currentBookId);
        updateHeartBtn(currentBookId);
        renderBooksGrid(document.getElementById('uSearchInput').value.toLowerCase());
        showToast('❤️ Added to Favorites!');
      }
    });
  } else {
    fetch('favorite_actions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'removeFavorite', book_id: currentBookId })
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (data.success) {
        favoriteBooks.splice(idx, 1);
        updateHeartBtn(currentBookId);
        renderBooksGrid(document.getElementById('uSearchInput').value.toLowerCase());
        showToast('💔 Removed from Favorites.');
      }
    });
  }
}

function updateHeartBtn(id) {
  var isFav = favoriteBooks.indexOf(id) !== -1;
  var btn   = document.getElementById('uHeartBtn');
  var lbl   = document.getElementById('uFavLabel');
  if (btn) btn.textContent = isFav ? '❤️' : '🤍';
  if (lbl) lbl.textContent = isFav ? 'Saved to Favorites' : 'Add to Favorites';
}

function renderFavorites() {
  var grid = document.getElementById('uFavoritesGrid');
  grid.innerHTML = '';
  if (!favoriteBooks.length) {
    grid.innerHTML = '<div class="u-empty-msg">No favorites yet. Click the 🤍 heart on any book to save it here!</div>';
    return;
  }
  for (var i = 0; i < favoriteBooks.length; i++) {
    var fid  = favoriteBooks[i];
    var book = null;
    for (var j = 0; j < books.length; j++) {
      if (books[j].id === fid) { book = books[j]; break; }
    }
    if (!book) continue;
    var card = document.createElement('div');
    card.className = 'u-book-card';
    card.onclick = (function(id) { return function() { openUDetail(id); }; })(book.id);
    card.innerHTML =
      '<div class="u-book-cover ' + book.color + '">' + book.title + '</div>' +
      '<div class="u-book-info">' +
        '<div class="u-book-title">' + book.title + '</div>' +
        '<div class="u-book-author">by ' + book.author + '</div>' +
        '<div class="u-book-meta">' +
          '<span class="u-book-genre-tag">' + book.genre + '</span>' +
          '<span class="u-fav-tag">❤️ Favorite</span>' +
        '</div>' +
        '<div class="u-book-blurb">' + book.desc + '</div>' +
      '</div>' +
      '<div class="u-book-dots">⋮</div>';
    grid.appendChild(card);
  }
}

// ── COMMENTS (DB-backed) ──
function loadAndRenderComments(bookId) {
  var list = document.getElementById('uCommentsList');
  list.innerHTML = '<div class="u-no-comments">Loading reviews...</div>';
  document.getElementById('uCommentInput').value = '';

  fetch('comment_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getComments', book_id: bookId })
  })
  .then(function(res) { return res.json(); })
  .then(function(comments) {
    list.innerHTML = '';
    if (!comments.length) {
      list.innerHTML = '<div class="u-no-comments">No reviews yet. Be the first to write one!</div>';
      return;
    }
    for (var i = 0; i < comments.length; i++) {
      var c    = comments[i];
      var item = document.createElement('div');
      item.className = 'u-comment-item';
      item.innerHTML =
        '<div class="u-comment-meta">' +
          '<span class="u-comment-user">' + escapeHtml(c.user) + '</span>' +
          '<span class="u-comment-time">' + escapeHtml(c.time) + '</span>' +
        '</div>' +
        '<div class="u-comment-text">' + escapeHtml(c.text) + '</div>';
      list.appendChild(item);
    }
  })
  .catch(function() {
    list.innerHTML = '<div class="u-no-comments">Could not load reviews.</div>';
  });
}

function submitComment() {
  if (!currentBookId) return;
  var input = document.getElementById('uCommentInput');
  var text  = input.value.trim();
  if (!text) { showToast('Please write something before posting.'); return; }

  fetch('comment_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'addComment', book_id: currentBookId, comment: text })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) {
      input.value = '';
      loadAndRenderComments(currentBookId);
      showToast('✅ Review posted!');
    } else {
      showToast('Failed to post review.');
    }
  })
  .catch(function() { showToast('Request failed.'); });
}

function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── READING LIST ──
function renderReadingList() {
  var list = document.getElementById('uReadingList');
  list.innerHTML = '';
  if (!borrowedBooks.length) {
    list.innerHTML = '<div class="u-empty-msg">You haven\'t borrowed any books yet. Visit the Bookstore to borrow some!</div>';
    return;
  }
  for (var i = 0; i < borrowedBooks.length; i++) {
    var id   = borrowedBooks[i];
    var book = null;
    for (var j = 0; j < books.length; j++) {
      if (books[j].id === id) { book = books[j]; break; }
    }
    if (!book) continue;
    var item = document.createElement('div');
    item.className = 'u-reading-item';
    item.innerHTML =
      '<div class="u-ri-cover ' + book.color + '">' + book.title + '</div>' +
      '<div class="u-ri-info">' +
        '<div class="u-ri-title">' + book.title + '</div>' +
        '<div class="u-ri-author">by ' + book.author + '</div>' +
        '<div class="u-ri-genre"><span class="badge badge-genre">' + book.genre + '</span></div>' +
      '</div>' +
      '<button class="u-ri-return" onclick="quickReturn(' + id + ')">Return</button>';
    list.appendChild(item);
  }
}

// ── AUTHORS ──
function renderAuthors() {
  var authorMap = {};
  for (var i = 0; i < books.length; i++) {
    var a = books[i].author;
    if (!authorMap[a]) authorMap[a] = { name: a, books: [], color: books[i].color };
    authorMap[a].books.push(books[i].title);
  }
  var grid    = document.getElementById('uAuthorsGrid');
  grid.innerHTML = '';
  var authors = Object.keys(authorMap);
  for (var i = 0; i < authors.length; i++) {
    var info = authorMap[authors[i]];
    var card = document.createElement('div');
    card.className = 'u-author-card';
    card.innerHTML =
      '<div class="u-author-avatar ' + info.color + '">' + info.name[0] + '</div>' +
      '<div class="u-author-name">' + info.name + '</div>' +
      '<div class="u-author-count">' + info.books.length + ' book' + (info.books.length > 1 ? 's' : '') + '</div>' +
      '<div class="u-author-titles">' + info.books.slice(0, 2).join(', ') + (info.books.length > 2 ? '...' : '') + '</div>';
    grid.appendChild(card);
  }
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

// ── INIT ── books → borrowings → all borrowed ids → favorites → render
loadBooks(function() {
  loadBorrowings(function() {
    loadAllBorrowedIds(function() {
      loadFavorites(function() {
        renderGenreTabs();
        renderBooksGrid('');
      });
    });
  });
});
