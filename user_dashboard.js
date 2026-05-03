// ── DATA ──
var books             = [];
var borrowedBooks     = []; // book IDs this user currently has borrowed (approved)
var allBorrowedIds    = []; // book IDs borrowed/pending by ANYONE
var pendingRequestIds = []; // book IDs this user has pending requests for
var favoriteBooks     = [];
var currentBookId     = null;
var activeGenre       = 'All';
var userBalance       = 0;  // user's current wallet balance

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
    el.style.color = userBalance < 500 ? '#e53e3e' : 'var(--accent)';
  }
}


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

    var availBadge;
    if (isBorrowedByMe) {
      availBadge = '<span class="u-avail-badge u-avail-mine">📖 Borrowed by you</span>';
    } else if (isPendingByMe) {
      availBadge = '<span class="u-avail-badge" style="background:#fefcbf;color:#b7791f;">⏳ Request Pending</span>';
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

  updateHeartBtn(id);
  loadAndRenderComments(id);
  document.getElementById('uDetailModal').classList.add('open');
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

  // Show fee info
  var feeEl = document.getElementById('uBorrowFeeInfo');
  if (feeEl) {
    feeEl.innerHTML = '<span style="color:var(--text-secondary);font-size:0.88rem;">💰 Fee: <strong>Rs 100 × days</strong> &nbsp;|&nbsp; Your balance: <strong style="color:var(--accent);">Rs ' + Number(userBalance).toLocaleString('en-IN', {minimumFractionDigits:2}) + '</strong></span>';
  }

  document.getElementById('uDetailModal').classList.remove('open');
  document.getElementById('uBorrowModal').classList.add('open');
}


// ── SUBMIT BORROW REQUEST ──
function submitBorrowRequest() {
  var username   = document.getElementById('uBorrowUsername').value.trim();
  // sync dropdown → hidden
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

  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action:      'requestBorrow',
      book_id:     currentBookId,
      username:    username,
      borrow_days: borrowDays,
      note:        note
    })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) {
      pendingRequestIds.push(currentBookId);
      allBorrowedIds.push(currentBookId);
      closeModal('uBorrowModal');
      renderBooksGrid(document.getElementById('uSearchInput').value.toLowerCase());
      showToast('📨 Borrow request submitted! Awaiting admin approval.');
    } else {
      errEl.textContent = data.error || 'Could not submit request.';
      errEl.style.display = 'block';
    }
  })
  .catch(function() {
    errEl.textContent = 'Request failed. Please try again.';
    errEl.style.display = 'block';
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
  handleConditionChange();
  document.getElementById('uDetailModal').classList.remove('open');
  document.getElementById('uReturnModal').classList.add('open');
}

// ── CONDITION DROPDOWN HANDLER ──
function handleConditionChange() {
  var condition = document.getElementById('uReturnCondition').value;
  var label     = document.getElementById('uReturnDescLabel');
  var textarea  = document.getElementById('uReturnDescription');
  if (condition === 'bad' || condition === 'damaged') {
    label.innerHTML = 'Description <span style="color:#e53e3e;">*</span> <span style="color:#888;font-weight:400;">(required for damage)</span>';
    textarea.placeholder = 'Please describe the damage in detail...';
  } else {
    label.innerHTML = 'Description <span style="color:#888;font-weight:400;">(optional)</span>';
    textarea.placeholder = 'Any additional notes about the book condition? (optional)';
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
      borrowedBooks  = borrowedBooks.filter(function(b) { return b.book_id !== currentBookId; });
      allBorrowedIds = allBorrowedIds.filter(function(id) { return id !== currentBookId; });
      closeModal('uReturnModal');
      renderBooksGrid(document.getElementById('uSearchInput').value.toLowerCase());
      renderReadingList();
      // Update balance
      if (data.new_balance !== null && data.new_balance !== undefined) {
        userBalance = data.new_balance;
        renderBalance();
      }
      if (data.total_fine && data.total_fine > 0) {
        var fineMsg = '⚠️ Book returned. Fine charged: Rs ' + Number(data.total_fine).toLocaleString('en-IN', {minimumFractionDigits:2});
        showToast(fineMsg);
        // Show fine breakdown modal
        showFineBreakdown(data.fines, data.total_fine, data.new_balance);
      } else {
        showToast('✅ Book returned successfully! No fines.');
      }
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
      // Already paid — free re-download
      triggerDownload(book);
      showToast('📥 Re-downloading "' + book.title + '" (already purchased)...');
    } else {
      // Show purchase confirm
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
  var canAfford = userBalance >= fee;
  document.getElementById('uPdfConfirmBtn').disabled  = !canAfford;
  document.getElementById('uPdfConfirmBtn').style.opacity = canAfford ? '1' : '0.5';
  document.getElementById('uPdfAffordWarn').style.display = canAfford ? 'none' : 'block';
  document.getElementById('uDetailModal').classList.remove('open');
  document.getElementById('uPdfPurchaseModal').classList.add('open');
}

function confirmPdfPurchase() {
  if (!currentBookId) return;
  var book = null;
  for (var i = 0; i < books.length; i++) {
    if (books[i].id === currentBookId) { book = books[i]; break; }
  }
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
      triggerDownload(book);
      showToast('✅ Purchase successful! Downloading "' + (book ? book.title : '') + '"...');
    } else {
      showToast('❌ ' + (data.error || 'Purchase failed.'));
    }
  })
  .catch(function() { showToast('❌ Purchase failed.'); });
}

function triggerDownload(book) {
  var link = document.createElement('a');
  link.href     = 'download_book.php?book_id=' + currentBookId;
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

  // Build a short 4-5 sec preview text
  var previewText = title + ', by ' + author + '. Genre: ' + genre + '. Available in the TechGiants Library.';

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

// ── INIT ── books → borrowings → all borrowed ids → pending requests → favorites → render
loadBalance(function() {
  loadBooks(function() {
    loadBorrowings(function() {
      loadAllBorrowedIds(function() {
        loadPendingRequests(function() {
          loadFavorites(function() {
            renderGenreTabs();
            renderBooksGrid('');
            // Show welcome popup if redirected from login
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
  document.getElementById('uProfileTabInfo').style.display     = tab === 'info' ? 'block' : 'none';
  document.getElementById('uProfileTabPassword').style.display = tab === 'password' ? 'block' : 'none';
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
var extendingBookId = null;

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
  document.getElementById('uExtendModal').classList.add('open');
}

function submitExtend() {
  var days   = parseInt(document.getElementById('uExtendDaysSelect').value);
  var reason = document.getElementById('uExtendReason').value.trim();
  var errEl  = document.getElementById('uExtendError');
  var sucEl  = document.getElementById('uExtendSuccess');
  errEl.style.display = 'none';
  sucEl.style.display = 'none';

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

  fetch('borrow_actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'extendBorrow', book_id: extendingBookId, extend_days: days, reason: reason })
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) {
      // Update local borrowedBooks
      for (var i = 0; i < borrowedBooks.length; i++) {
        if (borrowedBooks[i].book_id === extendingBookId) {
          borrowedBooks[i].due_date           = data.new_due_date;
          borrowedBooks[i].total_extended_days = (borrowedBooks[i].total_extended_days || 0) + days;
          break;
        }
      }
      // Update balance
      if (data.new_balance !== null && data.new_balance !== undefined) {
        userBalance = data.new_balance;
        renderBalance();
      }
      var newDueFmt = new Date(data.new_due_date + 'T00:00:00').toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
      var feeMsg = data.fee_charged ? ' (Rs ' + Number(data.fee_charged).toLocaleString('en-IN') + ' charged)' : '';
      sucEl.textContent = '✅ Extended! New due date: ' + newDueFmt + '.' + feeMsg;
      sucEl.style.display = 'block';
      document.getElementById('uExtendDaysSelect').disabled = true;
      document.getElementById('uExtendReason').disabled     = true;
      document.getElementById('uExtendSubmitBtn').disabled  = true;
      document.getElementById('uExtendSubmitBtn').style.opacity = '0.5';
      renderReadingList();
      setTimeout(function() { closeModal('uExtendModal'); }, 2500);
      showToast('✅ Extended to ' + newDueFmt + feeMsg + '!');
    } else {
      errEl.textContent = data.error || 'Could not extend borrow.';
      errEl.style.display = 'block';
    }
  })
  .catch(function() {
    errEl.textContent = 'Request failed. Please try again.';
    errEl.style.display = 'block';
  });
}
