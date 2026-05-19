// ── DATA ──
var books             = [];
var borrowedBooks     = []; // book IDs this user currently has borrowed (approved)
var allBorrowedIds    = []; // book IDs borrowed/pending by ANYONE
var pendingRequestIds = []; // book IDs this user has pending requests for
var favoriteBooks     = [];
var currentBookId     = null;
var activeGenre       = 'All';
var userBalance       = 0;  // user's current wallet balance
var myWaitlist        = []; // [{book_id, queue_pos}] books user is waitlisted for
var bookDueDates      = {}; // {book_id: due_date_string} for currently borrowed books

// ── PRICING (mirrored from PHP) ──
var BORROW_FEE_PER_DAY   = 100;
var EXTENSION_FEE_PER_DAY = 80;
var PDF_PURCHASE_FEE      = 400;

var genres = ['All', 'Fiction', 'Fantasy', 'Design', 'Technology', 'Biography', 'History', 'Non-Fiction', 'Self-Help'];

// ── LOAD BALANCE ──
function loadBalance(callback) {
  fetch('wallet_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getMyBalance' })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    userBalance = data.balance || 0;
    renderBalance();
    if (callback) callback();
  })
  .catch(function() { console.error('Failed to load balance.'); });
}

function renderBalance() {
  var el = document.getElementById('uSidebarBalance');
  if (el) {
    el.textContent = 'Rs ' + Number(userBalance).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    el.style.color = userBalance < 200 ? '#e53e3e' : 'var(--accent)';
  }
  // Low balance warning banner
  var warn = document.getElementById('uLowBalanceWarn');
  if (warn) {
    if (userBalance < 200) {
      warn.style.display = 'flex';
    } else {
      warn.style.display = 'none';
    }
  }
}


// ── WALLET TOP-UP ──
function toggleTopUpForm() {
  var form = document.getElementById('uTopupForm');
  var isOpen = form.style.display !== 'none';
  form.style.display = isOpen ? 'none' : 'block';
  if (!isOpen) {
    document.getElementById('uTopupAmount').value = '';
    document.querySelectorAll('.u-topup-preset').forEach(function(b) { b.classList.remove('selected'); });
    var msg = document.getElementById('uTopupMsg');
    msg.style.display = 'none';
    msg.className = 'u-topup-msg';
    setTimeout(function() { document.getElementById('uTopupAmount').focus(); }, 50);
  }
}

function setTopupAmount(amount) {
  document.getElementById('uTopupAmount').value = amount;
  document.querySelectorAll('.u-topup-preset').forEach(function(b) {
    b.classList.toggle('selected', parseInt(b.textContent.replace(/[^0-9]/g, '')) === amount);
  });
}

function submitTopUp() {
  var input   = document.getElementById('uTopupAmount');
  var confirm = document.querySelector('.u-topup-confirm');
  var msg     = document.getElementById('uTopupMsg');
  var amount  = parseFloat(input.value);

  if (!amount || amount < 100 || amount > 100000) {
    msg.textContent  = 'Enter an amount between Rs 100 and Rs 1,00,000.';
    msg.className    = 'u-topup-msg error';
    msg.style.display = 'block';
    return;
  }

  confirm.disabled = true;
  confirm.textContent = 'Adding…';
  msg.style.display = 'none';

  fetch('wallet_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'selfTopUp', amount: amount })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    confirm.disabled = false;
    confirm.textContent = 'Add to Wallet';
    if (data.success) {
      userBalance = data.new_balance;
      renderBalance();
      input.value = '';
      document.querySelectorAll('.u-topup-preset').forEach(function(b) { b.classList.remove('selected'); });
      msg.textContent  = '✅ Rs ' + Number(amount).toLocaleString('en-IN', {minimumFractionDigits: 2}) + ' added! New balance: Rs ' + Number(data.new_balance).toLocaleString('en-IN', {minimumFractionDigits: 2});
      msg.className    = 'u-topup-msg success';
      msg.style.display = 'block';
      setTimeout(function() { document.getElementById('uTopupForm').style.display = 'none'; }, 2500);
    } else {
      msg.textContent  = data.error || 'Something went wrong. Please try again.';
      msg.className    = 'u-topup-msg error';
      msg.style.display = 'block';
    }
  })
  .catch(function() {
    confirm.disabled = false;
    confirm.textContent = 'Add to Wallet';
    msg.textContent  = 'Network error — please try again.';
    msg.className    = 'u-topup-msg error';
    msg.style.display = 'block';
  });
}

// ── LOAD BOOKS ──
function loadBooks(callback) {
  fetch('get_books.php')
    .then(function(res) { return res.json(); })
    .then(function(data) {
      books = data.map(function(b) {
        return {
          id:           parseInt(b.id),
          title:        b.title,
          author:       b.author,
          genre:        b.genre,
          year:         parseInt(b.year),
          isbn:         b.isbn,
          desc:         b.description,
          price:        b.price ? parseFloat(b.price) : 1500,
          avg_rating:   b.avg_rating ? parseFloat(b.avg_rating) : null,
          review_count: b.review_count ? parseInt(b.review_count) : 0,
          my_rating:    b.my_rating ? parseInt(b.my_rating) : null,
          color:        'color-' + ((parseInt(b.id) % 8) + 1)
        };
      });
      if (callback) callback();
    })
    .catch(function() { console.error('Failed to load books.'); });
}

// ── LOAD MY WAITLIST ──
function loadMyWaitlist(callback) {
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getMyWaitlist' })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    myWaitlist = data || [];
    if (callback) callback();
  })
  .catch(function() { if (callback) callback(); });
}

// ── LOAD BOOK DUE DATES ──
function loadBookDueDates(callback) {
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getBookDueDates' })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    bookDueDates = data || {};
    if (callback) callback();
  })
  .catch(function() { if (callback) callback(); });
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
    // data is now array of {book_id, borrow_date, due_date}
    borrowedBooks = data;
    if (callback) callback();
  })
  .catch(function() { console.error('Failed to load borrowings.'); });
}

// ── HELPER: get borrowing object for a book_id ──
function getBorrowing(bookId) {
  for (var i = 0; i < borrowedBooks.length; i++) {
    if (borrowedBooks[i].book_id === bookId) return borrowedBooks[i];
  }
  return null;
}

// ── HELPER: get just the book IDs from borrowedBooks ──
function getBorrowedIds() {
  return borrowedBooks.map(function(b) { return b.book_id; });
}

// ── LOAD ALL BORROWED/PENDING IDS (anyone) ──
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

// ── LOAD MY PENDING REQUEST IDS ──
function loadPendingRequests(callback) {
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getMyPendingRequests' })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    pendingRequestIds = data;
    if (callback) callback();
  })
  .catch(function() { console.error('Failed to load pending requests.'); });
}

// ── LOAD & SHOW REJECTED BORROW REQUESTS ──
function loadRejectedRequests(callback) {
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getMyRejectedRequests' })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data && data.length > 0) {
      var listEl = document.getElementById('uRejectedList');
      if (listEl) {
        listEl.innerHTML = '';
        for (var i = 0; i < data.length; i++) {
          var r = data[i];
          var processedDate = r.processed_at ? new Date(r.processed_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '';
          var item = document.createElement('div');
          item.style.cssText = 'background:var(--bg,#f8f8f6);border:1px solid var(--border,#e8e5df);border-radius:10px;padding:12px 14px;margin-bottom:10px;';
          item.innerHTML =
            '<div style="font-weight:600;color:var(--text-primary);margin-bottom:3px;">📚 ' + escHtml(r.book_title) + '</div>' +
            '<div style="font-size:0.85rem;color:var(--text-secondary);">by ' + escHtml(r.book_author) + '</div>' +
            (processedDate ? '<div style="font-size:0.82rem;color:#888;margin-top:4px;">Denied on: ' + processedDate + '</div>' : '');
          listEl.appendChild(item);
        }
      }
      // Mark all as seen
      var ids = data.map(function(r) { return r.id; });
      fetch('borrow_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'markRejectedSeen', ids: ids })
      });
      document.getElementById('uRejectedModal').classList.add('open');
    }
    if (callback) callback();
  })
  .catch(function() { if (callback) callback(); });
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

    var isBorrowedByMe   = getBorrowedIds().indexOf(b.id) !== -1;
    var isPendingByMe    = pendingRequestIds.indexOf(b.id) !== -1;
    var isBorrowedByAny  = allBorrowedIds.indexOf(b.id) !== -1;
    var isFav            = favoriteBooks.indexOf(b.id) !== -1;
    var waitlistEntry    = null;
    for (var w = 0; w < myWaitlist.length; w++) {
      if (parseInt(myWaitlist[w].book_id) === b.id) { waitlistEntry = myWaitlist[w]; break; }
    }

    var myBorrowing = getBorrowing(b.id);
    var isLostPending = myBorrowing && myBorrowing.lost_reported;

    var availBadge;
    if (isLostPending) {
      availBadge = '<span class="u-avail-badge" style="background:#fff5f5;color:#c53030;">🚨 Lost Report Pending</span>';
    } else if (isBorrowedByMe) {
      availBadge = '<span class="u-avail-badge u-avail-mine">📖 Borrowed by you</span>';
    } else if (isPendingByMe) {
      availBadge = '<span class="u-avail-badge" style="background:#fefcbf;color:#b7791f;">⏳ Request Pending</span>';
    } else if (waitlistEntry) {
      availBadge = '<span class="u-avail-badge" style="background:#ebf4ff;color:#2b6cb0;">⏳ Waitlisted #' + waitlistEntry.queue_pos + '</span>';
    } else if (isBorrowedByAny) {
      // Show expected availability
      var dueStr = bookDueDates[b.id];
      var dueLabel = '';
      if (dueStr) {
        var dueFmt = new Date(dueStr + 'T00:00:00').toLocaleDateString('en-US', {month:'short', day:'numeric'});
        dueLabel = ' <span style="font-size:0.75rem;color:#888;">~' + dueFmt + '</span>';
      }
      availBadge = '<span class="u-avail-badge u-avail-no">✗ Unavailable</span>' + dueLabel;
    } else {
      availBadge = '<span class="u-avail-badge u-avail-yes">✓ Available</span>';
    }

    // Star rating snippet
    var ratingHtml = '';
    if (b.avg_rating) {
      var stars = '';
      for (var s = 1; s <= 5; s++) {
        stars += s <= Math.round(b.avg_rating) ? '★' : '☆';
      }
      var reviewLabel = b.review_count + ' review' + (b.review_count !== 1 ? 's' : '');
      ratingHtml = '<span class="u-book-rating" title="' + b.avg_rating + ' / 5">' +
        stars +
        ' <span style="font-size:0.78rem;color:#888;">' + b.avg_rating + ' (' + reviewLabel + ')</span>' +
        '</span>';
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
        (ratingHtml ? '<div style="margin-top:4px;">' + ratingHtml + '</div>' : '') +
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

  var isBorrowedByMe  = getBorrowedIds().indexOf(id) !== -1;
  var isPendingByMe   = pendingRequestIds.indexOf(id) !== -1;
  var isBorrowedByAny = allBorrowedIds.indexOf(id) !== -1;

  // Status badge
  if (isBorrowedByMe) {
    document.getElementById('uDetailStatus').innerHTML =
      '<span class="badge badge-borrowed">📖 Borrowed by You</span>';
  } else if (isPendingByMe) {
    document.getElementById('uDetailStatus').innerHTML =
      '<span class="badge" style="background:#fefcbf;color:#b7791f;">⏳ Request Pending</span>';
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
    btn.textContent = 'Return This Book';
    btn.disabled    = false;
    btn.style.opacity = '1';
    btn.onclick     = returnBook;
    // Show extend button
    var existExtBtn = document.getElementById('uDetailExtendBtn');
    if (!existExtBtn) {
      var extBtn = document.createElement('button');
      extBtn.id        = 'uDetailExtendBtn';
      extBtn.className = 'u-ri-extend';
      extBtn.style.cssText = 'margin-top:10px;width:100%;';
      extBtn.textContent = '⏳ Extend Borrow Period';
      extBtn.onclick = function() { closeModal('uDetailModal'); openExtendModal(currentBookId); };
      btn.parentNode.insertBefore(extBtn, btn.nextSibling);
    } else {
      existExtBtn.style.display = 'block';
      existExtBtn.onclick = function() { closeModal('uDetailModal'); openExtendModal(currentBookId); };
    }
  } else if (isPendingByMe) {
    btn.textContent = '⏳ Awaiting Admin Approval';
    btn.disabled    = true;
    btn.style.opacity = '0.6';
    btn.onclick     = null;
    var hideExt = document.getElementById('uDetailExtendBtn');
    if (hideExt) hideExt.style.display = 'none';
  } else if (isBorrowedByAny) {
    btn.textContent = 'Not Available';
    btn.disabled    = true;
    btn.style.opacity = '0.6';
    btn.onclick     = null;
    var hideExt = document.getElementById('uDetailExtendBtn');
    if (hideExt) hideExt.style.display = 'none';
  } else {
    btn.textContent = 'Borrow This Book';
    btn.disabled    = false;
    btn.style.opacity = '1';
    btn.onclick     = openBorrowModal;
    var hideExt = document.getElementById('uDetailExtendBtn');
    if (hideExt) hideExt.style.display = 'none';
  }

  // ── Waitlist button (shown when book is borrowed by someone else) ──
  var existWlBtn = document.getElementById('uDetailWaitlistBtn');
  if (isBorrowedByAny && !isBorrowedByMe && !isPendingByMe) {
    var waitlistEntry = null;
    for (var w = 0; w < myWaitlist.length; w++) {
      if (parseInt(myWaitlist[w].book_id) === id) { waitlistEntry = myWaitlist[w]; break; }
    }
    if (!existWlBtn) {
      var wlBtn = document.createElement('button');
      wlBtn.id = 'uDetailWaitlistBtn';
      wlBtn.className = 'btn-sec';
      wlBtn.style.cssText = 'margin-top:8px;width:100%;font-size:0.88rem;';
      btn.parentNode.insertBefore(wlBtn, btn.nextSibling);
      existWlBtn = wlBtn;
    }
    if (waitlistEntry) {
      existWlBtn.textContent = '🔔 You\'re #' + waitlistEntry.queue_pos + ' in queue — Leave Waitlist';
      existWlBtn.style.display = 'block';
      existWlBtn.onclick = function() { leaveWaitlist(currentBookId); };
    } else {
      // Show expected return date
      var dueStr = bookDueDates[id];
      var dueInfo = dueStr ? ' (~back ' + new Date(dueStr + 'T00:00:00').toLocaleDateString('en-US', {month:'short', day:'numeric'}) + ')' : '';
      existWlBtn.textContent = '🔔 Join Waitlist' + dueInfo;
      existWlBtn.style.display = 'block';
      existWlBtn.onclick = function() { joinWaitlist(currentBookId); };
    }
  } else {
    if (existWlBtn) existWlBtn.style.display = 'none';
  }

  // ── Ratings row ──
  var ratingRow = document.getElementById('uDetailRatingRow');
  if (ratingRow) {
    var book2 = null;
    for (var ri = 0; ri < books.length; ri++) {
      if (books[ri].id === id) { book2 = books[ri]; break; }
    }
    if (book2 && book2.avg_rating) {
      var starStr = '';
      for (var s = 1; s <= 5; s++) starStr += s <= Math.round(book2.avg_rating) ? '★' : '☆';
      ratingRow.innerHTML = '<span style="color:#f59e0b;font-size:1rem;">' + starStr + '</span> <strong>' + book2.avg_rating + '</strong> <span style="color:#888;font-size:0.85rem;">(' + book2.review_count + ' review' + (book2.review_count !== 1 ? 's' : '') + ')</span>';
      ratingRow.style.display = 'block';
    } else {
      ratingRow.innerHTML = '<span style="color:#aaa;font-size:0.85rem;">No ratings yet</span>';
      ratingRow.style.display = 'block';
    }
  }

  updateHeartBtn(id);
  loadAndRenderComments(id);
  document.getElementById('uDetailModal').classList.add('open');
}

// ── WAITLIST: join / leave ──
function joinWaitlist(bookId) {
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'joinWaitlist', book_id: bookId })
  })
  .then(function(r) { return r.json(); })
  .then(function(d) {
    if (d.success) {
      loadMyWaitlist(function() {
        renderBooksGrid(document.getElementById('uSearchInput').value.toLowerCase());
        showToast('✅ Added to waitlist! You are #' + d.position + ' in queue.');
        openUDetail(bookId);
      });
    } else {
      showToast('⚠️ ' + (d.error || 'Could not join waitlist.'));
    }
  });
}

function leaveWaitlist(bookId) {
  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'leaveWaitlist', book_id: bookId })
  })
  .then(function(r) { return r.json(); })
  .then(function(d) {
    if (d.success) {
      loadMyWaitlist(function() {
        renderBooksGrid(document.getElementById('uSearchInput').value.toLowerCase());
        showToast('Removed from waitlist.');
        openUDetail(bookId);
      });
    }
  });
}

// ── OPEN BORROW REQUEST MODAL ──
function openBorrowModal() {
  if (!currentBookId) return;

  var book = null;
  for (var i = 0; i < books.length; i++) {
    if (books[i].id === currentBookId) { book = books[i]; break; }
  }
  if (!book) return;

  // Set book info
  document.getElementById('uBorrowModalBookInfo').innerHTML =
    '<strong style="color:var(--text-primary);">' + escapeHtml(book.title) + '</strong>' +
    ' <span style="margin:0 6px;">·</span> by ' + escapeHtml(book.author) +
    ' <span style="margin:0 6px;">·</span> <span class="badge badge-genre">' + escapeHtml(book.genre) + '</span>';

  // Reset fields
  document.getElementById('uBorrowUsername').value = '';
  document.getElementById('uBorrowNote').value     = '';
  document.getElementById('uBorrowDays').value     = '0';
  document.getElementById('uBorrowDaysSelect').value = '0';
  document.getElementById('uBorrowError').style.display = 'none';
  document.getElementById('uBorrowConfirmStep').style.display = 'none';
  document.getElementById('uBorrowFormActions').style.display = '';
  document.getElementById('uBorrowConfirmActions').style.display = 'none';

  var feeEl = document.getElementById('uBorrowFeeInfo');
  if (feeEl) {
    feeEl.innerHTML = '<span style="color:#276749;font-size:0.88rem;">✅ Borrowing is <strong>free</strong>. Late returns and damage may incur fines.</span>';
  }

  document.getElementById('uDetailModal').classList.remove('open');
  document.getElementById('uBorrowModal').classList.add('open');
}


// ── SUBMIT BORROW REQUEST — validate and show confirmation step ──
function submitBorrowRequest() {
  var username   = document.getElementById('uBorrowUsername').value.trim();
  document.getElementById('uBorrowDays').value = document.getElementById('uBorrowDaysSelect').value;
  var borrowDays = parseInt(document.getElementById('uBorrowDays').value);
  var note       = document.getElementById('uBorrowNote').value.trim();
  var errEl      = document.getElementById('uBorrowError');

  errEl.style.display = 'none';

  if (!username) {
    errEl.textContent = 'Please enter your username.';
    errEl.style.display = 'block';
    return;
  }
  if (!borrowDays || borrowDays < 1) {
    errEl.textContent = 'Please select how many days you want to borrow the book.';
    errEl.style.display = 'block';
    return;
  }

  var langRadio = document.querySelector('input[name="uBorrowLang"]:checked');
  var language  = langRadio ? langRadio.value : 'english';
  var langLabel = language === 'nepali' ? '🇳🇵 Nepali' : '🇬🇧 English';

  var book = null;
  for (var i = 0; i < books.length; i++) {
    if (books[i].id === currentBookId) { book = books[i]; break; }
  }

  var confirmDiv = document.getElementById('uBorrowConfirmStep');
  confirmDiv.innerHTML =
    '<div style="font-weight:700;color:var(--text-primary);margin-bottom:8px;">Please confirm your borrow request:</div>' +
    '<div>📖 <strong>' + escapeHtml(book ? book.title : '') + '</strong></div>' +
    '<div>⏳ Duration: <strong>' + borrowDays + ' day' + (borrowDays > 1 ? 's' : '') + '</strong></div>' +
    '<div>🌐 Edition: <strong>' + langLabel + '</strong></div>' +
    (note ? '<div>📝 Note: <em>' + escapeHtml(note) + '</em></div>' : '') +
    '<div style="margin-top:10px;padding:8px 12px;background:#dcfce7;border-radius:8px;color:#166534;font-weight:600;">✅ Borrowing is FREE — no charge</div>';
  confirmDiv.style.display = 'block';

  document.getElementById('uBorrowFormActions').style.display = 'none';
  document.getElementById('uBorrowConfirmActions').style.display = '';
}

function cancelBorrowConfirm() {
  document.getElementById('uBorrowConfirmStep').style.display = 'none';
  document.getElementById('uBorrowFormActions').style.display = '';
  document.getElementById('uBorrowConfirmActions').style.display = 'none';
}

// ── CONFIRM BORROW — actually POST the request ──
function confirmBorrowRequest() {
  var username   = document.getElementById('uBorrowUsername').value.trim();
  var borrowDays = parseInt(document.getElementById('uBorrowDays').value);
  var note       = document.getElementById('uBorrowNote').value.trim();
  var errEl      = document.getElementById('uBorrowError');
  var langRadio  = document.querySelector('input[name="uBorrowLang"]:checked');
  var language   = langRadio ? langRadio.value : 'english';

  var label   = document.getElementById('uBorrowConfirmLabel');
  var spinner = document.getElementById('uBorrowConfirmSpinner');
  var btn     = document.querySelector('#uBorrowConfirmActions .btn-primary');
  label.style.display   = 'none';
  spinner.style.display = '';
  btn.disabled = true;

  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action:      'requestBorrow',
      book_id:     currentBookId,
      username:    username,
      borrow_days: borrowDays,
      note:        note,
      language:    language
    })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) {
      pendingRequestIds.push(currentBookId);
      allBorrowedIds.push(currentBookId);
      closeModal('uBorrowModal');
      renderBooksGrid(document.getElementById('uSearchInput').value.toLowerCase());
      var langLabel = language === 'nepali' ? 'नेपाली (Nepali)' : 'English';
      showToast('📨 Borrow request submitted (' + langLabel + ' edition)! Awaiting admin approval.');
    } else {
      errEl.textContent = data.error || 'Could not submit request.';
      errEl.style.display = 'block';
      cancelBorrowConfirm();
    }
  })
  .catch(function() {
    errEl.textContent = 'Request failed. Please try again.';
    errEl.style.display = 'block';
    cancelBorrowConfirm();
  })
  .finally(function() {
    label.style.display   = '';
    spinner.style.display = 'none';
    btn.disabled = false;
  });
}

// ── RETURN — open condition modal ──
function returnBook() {
  if (!currentBookId) return;
  var book = null;
  for (var i = 0; i < books.length; i++) {
    if (books[i].id === currentBookId) { book = books[i]; break; }
  }
  if (!book) return;
  document.getElementById('uReturnModalBookInfo').innerHTML =
    '<strong style="color:var(--text-primary);">' + escapeHtml(book.title) + '</strong>' +
    ' <span style="margin:0 6px;">·</span> by ' + escapeHtml(book.author) +
    ' <span style="margin:0 6px;">·</span> <span class="badge badge-genre">' + escapeHtml(book.genre) + '</span>';
  document.getElementById('uReturnCondition').value = '';
  document.getElementById('uReturnDescription').value = '';
  document.getElementById('uReturnError').style.display = 'none';
  document.getElementById('uReturnDamageFine').style.display = 'none';

  // Calculate overdue fine preview
  var finePreview = document.getElementById('uReturnFinePreview');
  var borrowEntry = null;
  for (var j = 0; j < borrowedBooks.length; j++) {
    if (borrowedBooks[j].book_id === currentBookId) { borrowEntry = borrowedBooks[j]; break; }
  }
  if (borrowEntry && borrowEntry.due_date) {
    var today   = new Date(); today.setHours(0, 0, 0, 0);
    var dueDate = new Date(borrowEntry.due_date + 'T00:00:00'); dueDate.setHours(0, 0, 0, 0);
    var overdueDays = Math.floor((today - dueDate) / 86400000);
    if (overdueDays > 0) {
      var fine = overdueDays * 100;
      finePreview.innerHTML =
        '⚠️ <strong style="color:#c0392b;">Overdue by ' + overdueDays + ' day' + (overdueDays > 1 ? 's' : '') + '</strong>' +
        ' — Rs ' + fine.toLocaleString('en-IN') + ' overdue fine will be charged (Rs 100/day)';
      finePreview.style.background = '#fdf0ef';
      finePreview.style.border = '1px solid #f5c6c6';
      finePreview.style.color = '#742a2a';
    } else {
      finePreview.innerHTML = '✅ <strong style="color:#276749;">Returning on time</strong> — No overdue fine';
      finePreview.style.background = '#f0fdf4';
      finePreview.style.border = '1px solid #bbf7d0';
      finePreview.style.color = '#276749';
    }
    finePreview.style.display = 'block';
  } else {
    finePreview.style.display = 'none';
  }

  handleConditionChange();
  document.getElementById('uDetailModal').classList.remove('open');
  document.getElementById('uReturnModal').classList.add('open');
}

// ── CONDITION DROPDOWN HANDLER ──
function handleConditionChange() {
  var condition  = document.getElementById('uReturnCondition').value;
  var label      = document.getElementById('uReturnDescLabel');
  var textarea   = document.getElementById('uReturnDescription');
  var damageDiv  = document.getElementById('uReturnDamageFine');

  if (condition === 'bad' || condition === 'damaged') {
    label.innerHTML = 'Description <span style="color:#e53e3e;">*</span> <span style="color:#888;font-weight:400;">(required for damage)</span>';
    textarea.placeholder = 'Please describe the damage in detail...';
  } else {
    label.innerHTML = 'Description <span style="color:#888;font-weight:400;">(optional)</span>';
    textarea.placeholder = 'Any additional notes about the book condition? (optional)';
  }

  // Damage fee preview
  var book = null;
  for (var i = 0; i < books.length; i++) {
    if (books[i].id === currentBookId) { book = books[i]; break; }
  }
  var bookPrice = book ? (book.price || 1500) : 1500;

  if (condition === 'fair') {
    damageDiv.innerHTML = '⚠️ <strong style="color:#b7791f;">Fair condition fee: Rs 200</strong> will be charged by the admin upon verification.';
    damageDiv.style.background = '#fffbeb'; damageDiv.style.border = '1px solid #fbd38d'; damageDiv.style.color = '#744210';
    damageDiv.style.display = 'block';
  } else if (condition === 'bad') {
    damageDiv.innerHTML = '⚠️ <strong style="color:#c0392b;">Damage fee: Rs 500</strong> will be charged by the admin upon verification.';
    damageDiv.style.background = '#fdf0ef'; damageDiv.style.border = '1px solid #f5c6c6'; damageDiv.style.color = '#742a2a';
    damageDiv.style.display = 'block';
  } else if (condition === 'damaged') {
    var damageFine = Math.round(0.80 * bookPrice);
    damageDiv.innerHTML = '❌ <strong style="color:#c0392b;">Significant damage fee: Rs ' + damageFine.toLocaleString('en-IN') + '</strong> (80% of book price) will be charged by the admin upon verification.';
    damageDiv.style.background = '#fdf0ef'; damageDiv.style.border = '1px solid #f5c6c6'; damageDiv.style.color = '#742a2a';
    damageDiv.style.display = 'block';
  } else {
    damageDiv.style.display = 'none';
  }
}

// ── SUBMIT RETURN with condition ──
function submitReturn() {
  var condition   = document.getElementById('uReturnCondition').value;
  var description = document.getElementById('uReturnDescription').value.trim();
  var errEl       = document.getElementById('uReturnError');
  errEl.style.display = 'none';

  if (!condition) {
    errEl.textContent = 'Please select the book condition.';
    errEl.style.display = 'block';
    return;
  }
  if ((condition === 'bad' || condition === 'damaged') && !description) {
    errEl.textContent = 'Please describe the damage since the condition is bad/damaged.';
    errEl.style.display = 'block';
    return;
  }

  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'returnBook', book_id: currentBookId, condition: condition, description: description })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) {
      var returnedBookId = currentBookId;
      borrowedBooks  = borrowedBooks.filter(function(b) { return b.book_id !== currentBookId; });
      allBorrowedIds = allBorrowedIds.filter(function(id) { return id !== currentBookId; });
      closeModal('uReturnModal');
      renderBooksGrid(document.getElementById('uSearchInput').value.toLowerCase());
      renderReadingList();
      showToast('📬 Return submitted! An admin will verify the condition shortly.');
      setTimeout(function() { openRatingModal(returnedBookId); }, 900);
    } else {
      errEl.textContent = data.error || 'Could not return book.';
      errEl.style.display = 'block';
    }
  })
  .catch(function() {
    errEl.textContent = 'Request failed. Please try again.';
    errEl.style.display = 'block';
  });
}

// ── REPORT BOOK AS LOST ──
function openReportLostModal() {
  if (!currentBookId) return;
  var book = null;
  for (var i = 0; i < books.length; i++) {
    if (books[i].id === currentBookId) { book = books[i]; break; }
  }
  if (!book) return;
  var price = book.price ? parseFloat(book.price) : 1500;
  document.getElementById('uLostModalBookInfo').innerHTML =
    '<strong style="color:var(--text-primary);">' + escapeHtml(book.title) + '</strong>' +
    ' by ' + escapeHtml(book.author);
  document.getElementById('uLostCostInfo').innerHTML =
    '<strong>ℹ️ How this works:</strong> Reporting this book as lost will notify the admin. ' +
    'The admin will review and confirm the loss, after which ' +
    '<strong>Rs ' + price.toLocaleString('en-IN', {minimumFractionDigits:2}) + '</strong> ' +
    '(full book value) will be charged to your wallet. ' +
    'The book stays on your account until the admin confirms.';
  document.getElementById('uLostError').style.display = 'none';
  closeModal('uReturnModal');
  document.getElementById('uLostModal').classList.add('open');
}

function confirmReportLost() {
  var errEl = document.getElementById('uLostError');
  errEl.style.display = 'none';

  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'reportLost', book_id: currentBookId })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) {
      // Mark the borrowing as lost_reported in local state (don't remove it)
      for (var i = 0; i < borrowedBooks.length; i++) {
        if (borrowedBooks[i].book_id === currentBookId) {
          borrowedBooks[i].lost_reported = 1;
          break;
        }
      }
      closeModal('uLostModal');
      renderBooksGrid(document.getElementById('uSearchInput').value.toLowerCase());
      renderReadingList();
      showToast('🚨 Lost report submitted. The admin will review and confirm.');
    } else {
      errEl.textContent = data.error || 'Failed to report book as lost.';
      errEl.style.display = 'block';
    }
  })
  .catch(function() {
    errEl.textContent = 'Network error. Please try again.';
    errEl.style.display = 'block';
  });
}

// ── BOOK RATING MODAL ──
var _ratingBookId = null;
var _selectedRating = 0;

function openRatingModal(bookId) {
  _ratingBookId = bookId;
  var book = null;
  for (var i = 0; i < books.length; i++) {
    if (books[i].id === bookId) { book = books[i]; break; }
  }
  var titleEl = document.getElementById('uRatingBookTitle');
  if (titleEl) titleEl.textContent = book ? book.title : '';
  // Pre-fill with this user's own previous rating (if any)
  var existing = (book && book.my_rating) ? book.my_rating : 0;
  _selectedRating = existing;
  var container = document.getElementById('uStarPicker');
  if (container) { container.innerHTML = ''; container._starsReady = false; }
  renderStarPicker(existing);
  var reviewEl = document.getElementById('uRatingReview');
  if (reviewEl) reviewEl.value = '';
  var msgEl = document.getElementById('uRatingMsg');
  if (msgEl) {
    if (existing) {
      msgEl.textContent = 'You previously rated this ' + existing + ' star' + (existing !== 1 ? 's' : '') + '. Update below.';
      msgEl.style.color = '#718096';
      msgEl.style.display = 'block';
    } else {
      msgEl.textContent = '';
      msgEl.style.display = 'none';
    }
  }
  document.getElementById('uRatingModal').classList.add('open');
}

function renderStarPicker(selected) {
  var container = document.getElementById('uStarPicker');
  if (!container) return;

  // Build DOM only once; afterwards just update display
  if (!container._starsReady) {
    container.innerHTML = '';
    for (var s = 1; s <= 5; s++) {
      var btn = document.createElement('span');
      btn.className = 'u-star';
      btn.textContent = '☆';
      btn.title = s + ' star' + (s > 1 ? 's' : '');
      btn.dataset.star = String(s);
      container.appendChild(btn);
    }
    // Event delegation — no DOM rebuild on hover/click
    container.onclick = function(e) {
      var star = parseInt(e.target.dataset.star);
      if (star >= 1 && star <= 5) { _selectedRating = star; _updateStars(star); }
    };
    container.onmouseover = function(e) {
      var star = parseInt(e.target.dataset.star);
      if (star >= 1 && star <= 5) _updateStars(star);
    };
    container.onmouseout = function(e) {
      if (!container.contains(e.relatedTarget)) _updateStars(_selectedRating);
    };
    container._starsReady = true;
  }
  _updateStars(selected);
}

function _updateStars(selected) {
  var spans = document.querySelectorAll('#uStarPicker .u-star');
  spans.forEach(function(span, idx) {
    var filled = idx + 1 <= selected;
    span.className = 'u-star' + (filled ? ' u-star-filled' : '');
    span.textContent = filled ? '★' : '☆';
  });
}

function submitRating() {
  if (!_selectedRating) {
    var msgEl = document.getElementById('uRatingMsg');
    msgEl.textContent = 'Please select a star rating.';
    msgEl.style.color = '#e53e3e';
    msgEl.style.display = 'block';
    return;
  }
  var review = document.getElementById('uRatingReview').value.trim();
  fetch('reviews_api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'submitRating', book_id: _ratingBookId, rating: _selectedRating, review: review })
  })
  .then(function(r) { return r.json(); })
  .then(function(d) {
    if (d.success) {
      // Update local book data
      for (var i = 0; i < books.length; i++) {
        if (books[i].id === _ratingBookId) {
          books[i].avg_rating   = d.avg_rating;
          books[i].review_count = d.total;
          books[i].my_rating    = _selectedRating;
          break;
        }
      }
      closeModal('uRatingModal');
      showToast('⭐ Thanks for rating!');
      renderBooksGrid(document.getElementById('uSearchInput').value.toLowerCase());
    } else {
      var msgEl = document.getElementById('uRatingMsg');
      msgEl.textContent = d.error || 'Could not submit rating.';
      msgEl.style.color = '#e53e3e';
      msgEl.style.display = 'block';
    }
  });
}

// ── READING HISTORY ──
function openReadingHistory() {
  var histEl = document.getElementById('uHistoryList');
  if (histEl) histEl.innerHTML = '<p style="color:#888;text-align:center;padding:20px;">Loading...</p>';

  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getReadingHistory' })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (!histEl) return;
    if (!data.length) {
      histEl.innerHTML = '<p style="color:#aaa;text-align:center;padding:30px;">No reading history yet. Borrow and return books to build your history!</p>';
      return;
    }
    histEl.innerHTML = '';
    for (var i = 0; i < data.length; i++) {
      var h = data[i];
      var borrowed = h.borrow_date ? new Date(h.borrow_date + 'T00:00:00').toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '—';
      var returned = h.return_date ? new Date(h.return_date + 'T00:00:00').toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '—';
      var condIcon = h.condition_status === 'lost' ? '🚨' : (h.condition_status === 'excellent' ? '⭐' : (h.condition_status === 'good' ? '👍' : '📋'));
      var statusColor = h.status === 'lost' ? '#e53e3e' : '#38a169';
      var item = document.createElement('div');
      item.className = 'u-history-item';
      item.innerHTML =
        '<div class="u-history-title">' + escapeHtml(h.title) + '</div>' +
        '<div class="u-history-meta">by ' + escapeHtml(h.author) + ' &nbsp;·&nbsp; <span class="badge badge-genre">' + escapeHtml(h.genre) + '</span></div>' +
        '<div class="u-history-dates">📅 Borrowed: <strong>' + borrowed + '</strong> &nbsp;→&nbsp; Returned: <strong>' + returned + '</strong></div>' +
        '<div class="u-history-status">' + condIcon + ' Condition: <strong style="color:' + statusColor + ';">' + (h.condition_status || h.status) + '</strong></div>';
      histEl.appendChild(item);
    }
  })
  .catch(function() {
    if (histEl) histEl.innerHTML = '<p style="color:#e53e3e;text-align:center;padding:30px;">Failed to load history. Please try again.</p>';
  });
}

// ── QUICK RETURN from Now Reading panel ──
function quickReturn(id) {
  currentBookId = id;
  var book = null;
  for (var i = 0; i < books.length; i++) {
    if (books[i].id === id) { book = books[i]; break; }
  }
  if (!book) return;
  document.getElementById('uReturnModalBookInfo').innerHTML =
    '<strong style="color:var(--text-primary);">' + escapeHtml(book.title) + '</strong>' +
    ' <span style="margin:0 6px;">·</span> by ' + escapeHtml(book.author) +
    ' <span style="margin:0 6px;">·</span> <span class="badge badge-genre">' + escapeHtml(book.genre) + '</span>';
  document.getElementById('uReturnCondition').value = '';
  document.getElementById('uReturnDescription').value = '';
  document.getElementById('uReturnError').style.display = 'none';
  handleConditionChange();
  document.getElementById('uReturnModal').classList.add('open');
}

// ── DOWNLOAD — charge PDF fee then trigger download ──
function downloadBook() {
  if (!currentBookId) return;
  var book = null;
  for (var i = 0; i < books.length; i++) {
    if (books[i].id === currentBookId) { book = books[i]; break; }
  }
  if (!book) return;

  // First check ownership / show confirm
  fetch('wallet_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'checkPdfOwnership', book_id: currentBookId })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.owned) {
      var dlCount = data.download_count || 0;
      var maxDl   = data.max_downloads || 3;
      if (dlCount >= maxDl) {
        showToast('❌ Download limit reached (' + maxDl + '/' + maxDl + '). You cannot re-download this PDF.');
        return;
      }
      openPdfRedownload(book, dlCount, maxDl);
    } else {
      var fee = data.fee || 400;
      openPdfPurchaseConfirm(book, fee);
    }
  })
  .catch(function() {
    showToast('❌ Could not check purchase status.');
  });
}

function openPdfPurchaseConfirm(book, fee) {
  document.getElementById('uPdfBookTitle').textContent = book.title;
  document.getElementById('uPdfFee').textContent = 'Rs ' + Number(fee).toLocaleString('en-IN');
  document.getElementById('uPdfBalance').textContent = 'Rs ' + Number(userBalance).toLocaleString('en-IN', {minimumFractionDigits:2});
  var balAfter = userBalance - fee;
  var balAfterEl = document.getElementById('uPdfBalanceAfter');
  if (balAfterEl) {
    balAfterEl.textContent = 'Rs ' + Number(Math.max(0, balAfter)).toLocaleString('en-IN', {minimumFractionDigits:2});
    balAfterEl.style.color = balAfter < 0 ? '#e53e3e' : '#276749';
  }
  // Reset actions area to default buttons
  resetPdfActions();
  // Show purchase mode
  document.getElementById('uPdfFeeSection').style.display  = '';
  document.getElementById('uPdfOwnedNotice').style.display = 'none';
  document.getElementById('uPdfAffordWarn').style.display  = 'none';
  // Reset language to english
  document.getElementById('uPdfLangEn').checked = true;
  document.getElementById('uDetailModal').classList.remove('open');
  document.getElementById('uPdfPurchaseModal').classList.add('open');
}

function openPdfRedownload(book, dlCount, maxDl) {
  dlCount = dlCount || 0;
  maxDl   = maxDl   || 3;
  document.getElementById('uPdfBookTitle').textContent = book.title;
  // Show re-download mode — reset actions first, then swap buttons
  resetPdfActions();
  document.getElementById('uPdfFeeSection').style.display  = 'none';
  document.getElementById('uPdfAffordWarn').style.display  = 'none';
  var ownedNotice = document.getElementById('uPdfOwnedNotice');
  ownedNotice.style.display = 'block';
  ownedNotice.innerHTML = '&#10003; You already own this PDF. Re-download it below. ' +
    '<strong>' + (maxDl - dlCount) + ' of ' + maxDl + ' downloads remaining.</strong>';
  document.getElementById('uPdfConfirmBtn').style.display  = 'none';
  document.getElementById('uPdfRedownloadBtn').style.display = 'inline-flex';
  // Reset language to english
  document.getElementById('uPdfLangEn').checked = true;
  document.getElementById('uDetailModal').classList.remove('open');
  document.getElementById('uPdfPurchaseModal').classList.add('open');
}

function confirmPdfRedownload() {
  var book = null;
  for (var i = 0; i < books.length; i++) {
    if (books[i].id === currentBookId) { book = books[i]; break; }
  }
  var langRadio = document.querySelector('input[name="uPdfLang"]:checked');
  var lang = langRadio ? langRadio.value : 'english';
  var langLabel = lang === 'nepali' ? 'नेपाली' : 'English';
  closeModal('uPdfPurchaseModal');
  triggerDownload(book, lang);
  showToast('📥 Re-downloading "' + (book ? book.title : '') + '" (' + langLabel + ')...');
}

function showPdfConfirmStep() {
  var fee      = 400;
  var canAfford = userBalance >= fee;
  var fmtBal   = Number(userBalance).toLocaleString('en-IN', {minimumFractionDigits:2});
  var fmtAfter = Number(Math.max(0, userBalance - fee)).toLocaleString('en-IN', {minimumFractionDigits:2});
  var area     = document.getElementById('uPdfActionsArea');
  if (!area) return;

  if (!canAfford) {
    area.innerHTML =
      '<div style="background:#fdf0ef;border:1.5px solid #f5c6c6;border-radius:10px;padding:12px 16px;margin-bottom:12px;font-size:0.88rem;color:#742a2a;line-height:1.6;">' +
        '&#9888; <strong>Insufficient balance.</strong><br>' +
        'This book costs Rs ' + fee.toLocaleString('en-IN') + ' but your balance is Rs ' + fmtBal + '.<br>Please top up your wallet first.' +
      '</div>' +
      '<div class="modal-actions"><button class="btn-sec" onclick="resetPdfActions()">Go Back</button></div>';
    return;
  }

  area.innerHTML =
    '<div style="background:#fff8e6;border:1.5px solid #f6c90e;border-radius:10px;padding:12px 16px;margin-bottom:12px;font-size:0.88rem;color:#7a5c00;line-height:1.6;">' +
      '<strong>Confirm purchase?</strong><br>' +
      'Rs <strong>' + fee.toLocaleString('en-IN') + '</strong> will be deducted from your wallet.<br>' +
      'Balance: Rs ' + fmtBal + ' &rarr; Rs <strong style="color:#276749;">' + fmtAfter + '</strong>' +
    '</div>' +
    '<div class="modal-actions">' +
      '<button class="btn-primary" id="uPdfYesBtn" onclick="confirmPdfPurchase()">' +
        '<span id="uPdfYesLabel">Yes, Buy Now</span>' +
        '<span id="uPdfYesSpinner" style="display:none;">Processing…</span>' +
      '</button>' +
      '<button class="btn-sec" onclick="resetPdfActions()">Go Back</button>' +
    '</div>';
}

function resetPdfActions() {
  var area = document.getElementById('uPdfActionsArea');
  if (!area) return;
  area.innerHTML =
    '<div class="modal-actions" id="uPdfDefaultActions">' +
      '<button class="btn-primary" id="uPdfConfirmBtn" onclick="showPdfConfirmStep()">Buy &amp; Download</button>' +
      '<button class="btn-primary" id="uPdfRedownloadBtn" onclick="confirmPdfRedownload()" style="display:none;">&#11015; Download</button>' +
      '<button class="btn-sec" onclick="closeModal(\'uPdfPurchaseModal\')">Cancel</button>' +
    '</div>';
}

function confirmPdfPurchase() {
  if (!currentBookId) return;
  var book = null;
  for (var i = 0; i < books.length; i++) {
    if (books[i].id === currentBookId) { book = books[i]; break; }
  }
  // Capture language now — before the modal closes or any async happens
  var langRadio = document.querySelector('input[name="uPdfLang"]:checked');
  var lang = langRadio ? langRadio.value : 'english';

  var btn = document.getElementById('uPdfYesBtn');
  var lbl = document.getElementById('uPdfYesLabel');
  var spn = document.getElementById('uPdfYesSpinner');
  if (lbl) lbl.style.display = 'none';
  if (spn) spn.style.display = '';
  if (btn) btn.disabled = true;

  fetch('wallet_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'chargePdfPurchase', book_id: currentBookId, book_title: book ? book.title : '' })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) {
      userBalance = data.new_balance !== undefined ? data.new_balance : userBalance;
      renderBalance();
      closeModal('uPdfPurchaseModal');
      triggerDownload(book, lang);
      var newBalFmt = Number(userBalance).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
      showToast('✅ Rs 400 deducted · Downloading "' + (book ? book.title : '') + '" · New balance: Rs ' + newBalFmt);
    } else {
      showToast('❌ ' + (data.error || 'Purchase failed.'));
      var btn2 = document.getElementById('uPdfYesBtn');
      var lbl2 = document.getElementById('uPdfYesLabel');
      var spn2 = document.getElementById('uPdfYesSpinner');
      if (lbl2) lbl2.style.display = '';
      if (spn2) spn2.style.display = 'none';
      if (btn2) btn2.disabled = false;
    }
  })
  .catch(function() {
    showToast('❌ Purchase failed. Please try again.');
    var btn2 = document.getElementById('uPdfYesBtn');
    var lbl2 = document.getElementById('uPdfYesLabel');
    var spn2 = document.getElementById('uPdfYesSpinner');
    if (lbl2) lbl2.style.display = '';
    if (spn2) spn2.style.display = 'none';
    if (btn2) btn2.disabled = false;
  });
}

function triggerDownload(book, lang) {
  if (!lang) {
    var langRadio = document.querySelector('input[name="uPdfLang"]:checked');
    lang = langRadio ? langRadio.value : 'english';
  }
  var link = document.createElement('a');
  link.href     = 'download_book.php?book_id=' + currentBookId + '&lang=' + encodeURIComponent(lang);
  link.download = (book ? book.title : 'book') + '.pdf';
  link.style.display = 'none';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// ── Fine breakdown modal ──
function showFineBreakdown(fines, total, newBalance) {
  var list = document.getElementById('uFineBreakdownList');
  if (!list) return;
  var html = '';
  for (var i = 0; i < fines.length; i++) {
    html += '<div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">' +
      '<span>' + escapeHtml(fines[i].label) + '</span>' +
      '<strong style="color:#e53e3e;">Rs ' + Number(fines[i].amount).toLocaleString('en-IN', {minimumFractionDigits:2}) + '</strong></div>';
  }
  html += '<div style="display:flex;justify-content:space-between;padding:10px 0;font-weight:700;">' +
    '<span>Total Fine</span>' +
    '<span style="color:#e53e3e;">Rs ' + Number(total).toLocaleString('en-IN', {minimumFractionDigits:2}) + '</span></div>';
  if (newBalance !== null && newBalance !== undefined) {
    html += '<div style="padding:8px 0;color:var(--text-secondary);font-size:0.9rem;">New balance: <strong>Rs ' + Number(newBalance).toLocaleString('en-IN', {minimumFractionDigits:2}) + '</strong></div>';
  }
  list.innerHTML = html;
  document.getElementById('uFineBreakdownModal').classList.add('open');
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
    body: JSON.stringify({ action: 'addComment', book_id: currentBookId, comment: text, csrf_token: _csrfToken })
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
    var borrow = borrowedBooks[i];
    var id     = borrow.book_id;
    var book   = null;
    for (var j = 0; j < books.length; j++) {
      if (books[j].id === id) { book = books[j]; break; }
    }
    if (!book) continue;

    var dueDateStr    = borrow.due_date;
    var borrowDateStr = borrow.borrow_date;
    var countdownHtml = '';

    if (dueDateStr) {
      var today = new Date();
      today.setHours(0, 0, 0, 0);
      var due      = new Date(dueDateStr + 'T00:00:00');
      var daysLeft = Math.round((due - today) / (1000 * 60 * 60 * 24));
      var dueFormatted = due.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
      var borrowFormatted = borrowDateStr
        ? new Date(borrowDateStr + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
        : '';

      var barColor, statusLabel, barPct;
      if (daysLeft < 0) {
        barColor    = '#e53e3e';
        statusLabel = '<span class="u-due-overdue">\u26a0\ufe0f Overdue by ' + Math.abs(daysLeft) + ' day' + (Math.abs(daysLeft) !== 1 ? 's' : '') + '</span>';
        barPct      = 100;
      } else if (daysLeft === 0) {
        barColor    = '#e53e3e';
        statusLabel = '<span class="u-due-overdue">\u26a0\ufe0f Due Today!</span>';
        barPct      = 100;
      } else if (daysLeft === 1) {
        barColor    = '#dd6b20';
        statusLabel = '<span class="u-due-warn">\ud83d\udd14 Due Tomorrow</span>';
        barPct      = 85;
      } else if (daysLeft <= 3) {
        barColor    = '#d69e2e';
        statusLabel = '<span class="u-due-warn">\u23f0 ' + daysLeft + ' days left</span>';
        barPct      = Math.round((daysLeft / 7) * 100);
      } else {
        barColor    = '#38a169';
        statusLabel = '<span class="u-due-ok">\u2705 ' + daysLeft + ' days left</span>';
        barPct      = Math.round((daysLeft / 7) * 100);
      }

      countdownHtml =
        '<div class="u-ri-due-block">' +
          '<div class="u-ri-due-top">' +
            statusLabel +
            '<span class="u-ri-due-date">Due: ' + dueFormatted + '</span>' +
          '</div>' +
          '<div class="u-ri-due-bar-bg">' +
            '<div class="u-ri-due-bar-fill" style="width:' + barPct + '%;background:' + barColor + ';"></div>' +
          '</div>' +
          (borrowFormatted ? '<div class="u-ri-borrow-date">Borrowed: ' + borrowFormatted + '</div>' : '') +
          (borrow.total_extended_days > 0 ? '<div class="u-ri-borrow-date" style="color:#e67e22;">⏳ Extended by ' + borrow.total_extended_days + ' day' + (borrow.total_extended_days > 1 ? 's' : '') + '</div>' : '') +
        '</div>';
    }

    var item = document.createElement('div');
    item.className = 'u-reading-item';
    item.innerHTML =
      '<div class="u-ri-cover ' + book.color + '">' + book.title + '</div>' +
      '<div class="u-ri-info">' +
        '<div class="u-ri-title">' + book.title + '</div>' +
        '<div class="u-ri-author">by ' + book.author + '</div>' +
        '<div class="u-ri-genre"><span class="badge badge-genre">' + book.genre + '</span></div>' +
        countdownHtml +
      '</div>' +
      '<div style="display:flex;gap:8px;align-items:center;">' +
        '<button class="u-ri-audio" id="audioBtn-' + id + '" onclick="playAudioPreview(' + id + ', \'' + book.title.replace(/'/g, "\\'") + '\', \'' + book.author.replace(/'/g, "\\'") + '\', \'' + book.genre.replace(/'/g, "\\'") + '\')" title="Listen to audio preview">🔊 Listen</button>' +
        '<button class="u-ri-return" onclick="quickReturn(' + id + ')">Return</button>' +
        '<button class="u-ri-extend" onclick="openExtendModal(' + id + ')">Extend</button>' +
      '</div>';
    list.appendChild(item);
  }
}

// ── AUDIO PREVIEW ──
var _audioPreviewSynth = window.speechSynthesis;
var _audioPreviewActive = null; // track which book id is playing

function playAudioPreview(bookId, title, author, genre) {
  var btn = document.getElementById('audioBtn-' + bookId);

  // If already playing this book, stop it
  if (_audioPreviewActive === bookId) {
    _audioPreviewSynth.cancel();
    _audioPreviewActive = null;
    if (btn) { btn.textContent = '🔊 Listen'; btn.classList.remove('u-ri-audio-playing'); }
    return;
  }

  // Stop any other playing preview
  if (_audioPreviewActive !== null) {
    _audioPreviewSynth.cancel();
    var prevBtn = document.getElementById('audioBtn-' + _audioPreviewActive);
    if (prevBtn) { prevBtn.textContent = '🔊 Listen'; prevBtn.classList.remove('u-ri-audio-playing'); }
    _audioPreviewActive = null;
  }

  // Build preview text — same paragraphs as the PDF sample chapter
  var bookDesc = '';
  for (var i = 0; i < books.length; i++) {
    if (books[i].id === bookId) { bookDesc = books[i].description || ''; break; }
  }
  var previewText = [
    'This is the digital edition of "' + title + '" by ' + author + ', made available through the TechGiants Library system. This copy has been prepared for personal reading and educational purposes.',
    bookDesc ? bookDesc : '',
    'It was a bright cold day in April, and the clocks were striking thirteen. The wind cut through the narrow streets with quiet persistence, carrying with it the faint smell of old paper and forgotten things. Somewhere in the distance, a door opened and closed.',
    'She had read every book on the shelf twice, and still the answers escaped her. Knowledge, she had come to believe, was not the same as understanding — and understanding was not the same as wisdom. These were three entirely different countries, each requiring a separate journey to reach.',
    'The library was quiet at this hour. Dust motes drifted lazily in the pale afternoon light that slanted through the high windows. He pulled the book from its place on the shelf and turned it over in his hands, reading the back cover with the careful attention of someone who knows that the first impression of a book is rarely the final one.'
  ].filter(Boolean).join(' ');

  var utterance = new SpeechSynthesisUtterance(previewText);
  utterance.rate  = 0.92;
  utterance.pitch = 1.05;
  utterance.lang  = 'en-US';

  utterance.onstart = function() {
    _audioPreviewActive = bookId;
    if (btn) { btn.textContent = '⏹ Stop'; btn.classList.add('u-ri-audio-playing'); }
  };

  utterance.onend = function() {
    _audioPreviewActive = null;
    if (btn) { btn.textContent = '🔊 Listen'; btn.classList.remove('u-ri-audio-playing'); }
  };

  utterance.onerror = function() {
    _audioPreviewActive = null;
    if (btn) { btn.textContent = '🔊 Listen'; btn.classList.remove('u-ri-audio-playing'); }
    showToast('Audio preview not supported on this browser.');
  };

  _audioPreviewSynth.speak(utterance);
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

// ── LOGOUT CONFIRM ──
function openLogoutConfirm() {
  document.getElementById('uLogoutModal').classList.add('open');
}

// ── INIT ── books → borrowings → all borrowed ids → pending → favorites → waitlist → due dates → render
loadBalance(function() {
  loadBooks(function() {
    loadBorrowings(function() {
      loadAllBorrowedIds(function() {
        loadPendingRequests(function() {
          loadRejectedRequests(function() {
            loadFavorites(function() {
              loadMyWaitlist(function() {
                loadBookDueDates(function() {
                  renderGenreTabs();
                  renderBooksGrid('');
                  if (typeof _showWelcome !== 'undefined' && _showWelcome) {
                    var name = typeof _welcomeName !== 'undefined' ? _welcomeName : 'there';
                    document.getElementById('uWelcomeName').textContent = 'Welcome back, ' + name + '! 👋';
                    document.getElementById('uWelcomeModal').classList.add('open');
                  }
                });
              });
            });
          });
        });
      });
    });
  });
});

// ── PROFILE MODAL ──

function openProfileModal() {
  fetch('profile_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'getProfile' })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (!data.success) { showToast('Failed to load profile.'); return; }
    var u = data.user;
    // populate avatar
    document.getElementById('uProfileBigAvatar').textContent = (u.first_name[0] || '?').toUpperCase();
    document.getElementById('uProfileAvatarName').textContent = u.first_name + ' ' + u.last_name;
    document.getElementById('uProfileAvatarEmail').textContent = u.email;
    // populate form
    document.getElementById('uProfFirstName').value = u.first_name;
    document.getElementById('uProfLastName').value  = u.last_name;
    document.getElementById('uProfUsername').value  = u.username;
    document.getElementById('uProfEmail').value     = u.email;
    // clear messages
    setProfileMsg('uProfileInfoMsg', '', '');
    setProfileMsg('uProfilePwMsg', '', '');
    // clear password fields
    document.getElementById('uProfOldPw').value     = '';
    document.getElementById('uProfNewPw').value     = '';
    document.getElementById('uProfConfirmPw').value = '';
    // show info tab by default
    switchProfileTab('info');
    document.getElementById('uProfileModal').classList.add('open');
  })
  .catch(function() { showToast('Network error. Try again.'); });
}

function switchProfileTab(tab) {
  document.getElementById('uTabInfo').classList.toggle('active', tab === 'info');
  document.getElementById('uTabPassword').classList.toggle('active', tab === 'password');
  var histTab = document.getElementById('uTabHistory');
  if (histTab) histTab.classList.toggle('active', tab === 'history');
  document.getElementById('uProfileTabInfo').style.display     = tab === 'info' ? 'block' : 'none';
  document.getElementById('uProfileTabPassword').style.display = tab === 'password' ? 'block' : 'none';
  var histPanel = document.getElementById('uProfileTabHistory');
  if (histPanel) histPanel.style.display = tab === 'history' ? 'block' : 'none';
  if (tab === 'history') openReadingHistory();
}

function setProfileMsg(id, msg, type) {
  var el = document.getElementById(id);
  el.textContent = msg;
  el.className = 'u-profile-msg' + (type ? ' ' + type : '');
}

function saveProfile() {
  var firstName = document.getElementById('uProfFirstName').value.trim();
  var lastName  = document.getElementById('uProfLastName').value.trim();
  var username  = document.getElementById('uProfUsername').value.trim();
  var email     = document.getElementById('uProfEmail').value.trim();

  if (!firstName || !lastName || !username || !email) {
    setProfileMsg('uProfileInfoMsg', 'Please fill in all fields.', 'error'); return;
  }

  fetch('profile_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'updateProfile', first_name: firstName, last_name: lastName, username: username, email: email })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) {
      setProfileMsg('uProfileInfoMsg', '✅ ' + data.message, 'success');
      // update sidebar display
      document.getElementById('uSidebarAvatar').textContent = firstName[0].toUpperCase();
      document.getElementById('uSidebarName').textContent   = firstName + ' ' + lastName;
      var usernameEl = document.getElementById('uSidebarUsername');
      if (usernameEl) usernameEl.textContent = '@' + username;
      // update avatar in modal
      document.getElementById('uProfileBigAvatar').textContent  = firstName[0].toUpperCase();
      document.getElementById('uProfileAvatarName').textContent = firstName + ' ' + lastName;
      document.getElementById('uProfileAvatarEmail').textContent = email;
      showToast('Profile updated!');
    } else {
      setProfileMsg('uProfileInfoMsg', '❌ ' + data.error, 'error');
    }
  })
  .catch(function() { setProfileMsg('uProfileInfoMsg', 'Network error. Try again.', 'error'); });
}

function savePassword() {
  var oldPw     = document.getElementById('uProfOldPw').value;
  var newPw     = document.getElementById('uProfNewPw').value;
  var confirmPw = document.getElementById('uProfConfirmPw').value;

  if (!oldPw || !newPw || !confirmPw) {
    setProfileMsg('uProfilePwMsg', 'Please fill in all password fields.', 'error'); return;
  }
  if (newPw.length < 6) {
    setProfileMsg('uProfilePwMsg', 'New password must be at least 6 characters.', 'error'); return;
  }
  if (newPw !== confirmPw) {
    setProfileMsg('uProfilePwMsg', 'New passwords do not match.', 'error'); return;
  }

  fetch('profile_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'changePassword', old_password: oldPw, new_password: newPw, confirm_password: confirmPw })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) {
      setProfileMsg('uProfilePwMsg', '✅ ' + data.message, 'success');
      document.getElementById('uProfOldPw').value     = '';
      document.getElementById('uProfNewPw').value     = '';
      document.getElementById('uProfConfirmPw').value = '';
      showToast('Password updated!');
    } else {
      setProfileMsg('uProfilePwMsg', '❌ ' + data.error, 'error');
    }
  })
  .catch(function() { setProfileMsg('uProfilePwMsg', 'Network error. Try again.', 'error'); });
}

function togglePw(inputId, icon) {
  var inp = document.getElementById(inputId);
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.textContent = '🙈';
  } else {
    inp.type = 'password';
    icon.textContent = '👁';
  }
}


// ── EXTEND BORROW ──
var extendingBookId     = null;
var _pendingExtendDays   = 0;
var _pendingExtendReason = '';

function openExtendModal(bookId) {
  extendingBookId = bookId;
  var borrow = getBorrowing(bookId);
  var book   = null;
  for (var i = 0; i < books.length; i++) {
    if (books[i].id === bookId) { book = books[i]; break; }
  }
  if (!book || !borrow) return;

  // Max allowed remaining extensions
  var alreadyExtended = borrow.total_extended_days || 0;
  var remaining = 7 - alreadyExtended;

  // Book info
  document.getElementById('uExtendModalBookInfo').innerHTML =
    '<strong style="color:var(--text-primary);">' + escapeHtml(book.title) + '</strong>' +
    ' <span style="margin:0 6px;">·</span> by ' + escapeHtml(book.author) +
    ' <span style="margin:0 6px;">·</span> <span class="badge badge-genre">' + escapeHtml(book.genre) + '</span>';

  // Current due date
  var dueDateFmt = borrow.due_date
    ? new Date(borrow.due_date + 'T00:00:00').toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'})
    : '—';
  document.getElementById('uExtendCurrentDue').textContent = 'Current due date: ' + dueDateFmt;

  // Extension allowance info
  var allowanceEl = document.getElementById('uExtendAllowance');
  if (remaining <= 0) {
    allowanceEl.innerHTML = '<span style="color:#e53e3e;font-weight:600;">⚠ You have used all 7 extension days allowed.</span>';
    document.getElementById('uExtendDaysSelect').disabled = true;
    document.getElementById('uExtendReason').disabled     = true;
    document.getElementById('uExtendSubmitBtn').disabled  = true;
    document.getElementById('uExtendSubmitBtn').style.opacity = '0.5';
  } else {
    allowanceEl.innerHTML = 'You can extend by up to <strong>' + remaining + ' more day' + (remaining > 1 ? 's' : '') + '</strong>. (Max 7 total extension days per borrow.)';
    document.getElementById('uExtendDaysSelect').disabled = false;
    document.getElementById('uExtendReason').disabled     = false;
    document.getElementById('uExtendSubmitBtn').disabled  = false;
    document.getElementById('uExtendSubmitBtn').style.opacity = '1';
    // Rebuild select options up to remaining
    var sel = document.getElementById('uExtendDaysSelect');
    sel.innerHTML = '<option value="">Select days...</option>';
    for (var d = 1; d <= remaining; d++) {
      var opt = document.createElement('option');
      opt.value       = d;
      opt.textContent = d + ' day' + (d > 1 ? 's' : '');
      sel.appendChild(opt);
    }
  }

  document.getElementById('uExtendReason').value = '';
  document.getElementById('uExtendError').style.display  = 'none';
  document.getElementById('uExtendSuccess').style.display = 'none';
  resetExtendActions();
  document.getElementById('uExtendModal').classList.add('open');
}

function submitExtend() {
  var days   = parseInt(document.getElementById('uExtendDaysSelect').value);
  var reason = document.getElementById('uExtendReason').value.trim();
  var errEl  = document.getElementById('uExtendError');
  errEl.style.display = 'none';
  document.getElementById('uExtendSuccess').style.display = 'none';

  if (!days || days < 1) {
    errEl.textContent = 'Please select how many days to extend.';
    errEl.style.display = 'block';
    return;
  }
  if (!reason) {
    errEl.textContent = 'Please provide a reason for extending.';
    errEl.style.display = 'block';
    return;
  }

  showExtendConfirmStep(days, reason);
}

function showExtendConfirmStep(days, reason) {
  _pendingExtendDays   = days;
  _pendingExtendReason = reason;

  var fee      = days * EXTENSION_FEE_PER_DAY;
  var canAfford = userBalance >= fee;
  var fmtBal   = Number(userBalance).toLocaleString('en-IN', {minimumFractionDigits:2});
  var fmtFee   = Number(fee).toLocaleString('en-IN');
  var fmtAfter = Number(Math.max(0, userBalance - fee)).toLocaleString('en-IN', {minimumFractionDigits:2});
  var area     = document.getElementById('uExtendActionsArea');
  if (!area) return;

  if (!canAfford) {
    area.innerHTML =
      '<div style="background:#fdf0ef;border:1.5px solid #f5c6c6;border-radius:10px;padding:12px 16px;margin-bottom:12px;font-size:0.88rem;color:#742a2a;line-height:1.6;">' +
        '&#9888; <strong>Insufficient balance.</strong><br>' +
        'Extending by ' + days + ' day' + (days > 1 ? 's' : '') + ' costs Rs ' + fmtFee + ', but your balance is Rs ' + fmtBal + '.<br>Please top up your wallet first.' +
      '</div>' +
      '<div class="detail-actions"><button class="btn-sec" onclick="resetExtendActions()">Go Back</button></div>';
    return;
  }

  area.innerHTML =
    '<div style="background:#fff8e6;border:1.5px solid #f6c90e;border-radius:10px;padding:12px 16px;margin-bottom:12px;font-size:0.88rem;color:#7a5c00;line-height:1.6;">' +
      '<strong>Confirm extension?</strong><br>' +
      days + ' day' + (days > 1 ? 's' : '') + ' &times; Rs ' + EXTENSION_FEE_PER_DAY + '/day = Rs <strong>' + fmtFee + '</strong> will be deducted.<br>' +
      'Balance: Rs ' + fmtBal + ' &rarr; Rs <strong style="color:#276749;">' + fmtAfter + '</strong>' +
    '</div>' +
    '<div class="detail-actions">' +
      '<button class="btn-primary" id="uExtendYesBtn" onclick="confirmExtend()">' +
        '<span id="uExtendYesLabel">Yes, Extend</span>' +
        '<span id="uExtendYesSpinner" style="display:none;">Processing…</span>' +
      '</button>' +
      '<button class="btn-sec" onclick="resetExtendActions()">Go Back</button>' +
    '</div>';
}

function resetExtendActions() {
  var area = document.getElementById('uExtendActionsArea');
  if (!area) return;
  area.innerHTML =
    '<div class="detail-actions">' +
      '<button class="btn-primary" id="uExtendSubmitBtn" onclick="submitExtend()">Confirm Extension</button>' +
      '<button class="btn-sec" onclick="closeModal(\'uExtendModal\')">Cancel</button>' +
    '</div>';
}

function confirmExtend() {
  var days   = _pendingExtendDays;
  var reason = _pendingExtendReason;
  var btn = document.getElementById('uExtendYesBtn');
  var lbl = document.getElementById('uExtendYesLabel');
  var spn = document.getElementById('uExtendYesSpinner');
  if (lbl) lbl.style.display = 'none';
  if (spn) spn.style.display = '';
  if (btn) btn.disabled = true;

  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'extendBorrow', book_id: extendingBookId, extend_days: days, reason: reason })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    var sucEl = document.getElementById('uExtendSuccess');
    var errEl = document.getElementById('uExtendError');
    if (data.success) {
      for (var i = 0; i < borrowedBooks.length; i++) {
        if (borrowedBooks[i].book_id === extendingBookId) {
          borrowedBooks[i].due_date            = data.new_due_date;
          borrowedBooks[i].total_extended_days = (borrowedBooks[i].total_extended_days || 0) + days;
          break;
        }
      }
      if (data.new_balance !== null && data.new_balance !== undefined) {
        userBalance = data.new_balance;
        renderBalance();
      }
      var newDueFmt = new Date(data.new_due_date + 'T00:00:00').toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
      var feeMsg = data.fee_charged ? ' (Rs ' + Number(data.fee_charged).toLocaleString('en-IN') + ' charged)' : '';
      resetExtendActions();
      document.getElementById('uExtendSubmitBtn').disabled  = true;
      document.getElementById('uExtendSubmitBtn').style.opacity = '0.5';
      document.getElementById('uExtendDaysSelect').disabled = true;
      document.getElementById('uExtendReason').disabled     = true;
      sucEl.textContent = '✅ Extended! New due date: ' + newDueFmt + '.' + feeMsg;
      sucEl.style.display = 'block';
      renderReadingList();
      setTimeout(function() { closeModal('uExtendModal'); }, 2500);
      showToast('✅ Extended to ' + newDueFmt + feeMsg + '!');
    } else {
      resetExtendActions();
      errEl.textContent = data.error || 'Could not extend borrow.';
      errEl.style.display = 'block';
    }
  })
  .catch(function() {
    resetExtendActions();
    document.getElementById('uExtendError').textContent = 'Request failed. Please try again.';
    document.getElementById('uExtendError').style.display = 'block';
  });
}
