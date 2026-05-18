<?php
// chatbot_widget.php — floating library assistant widget
if (empty($_SESSION['user']) || $_SESSION['user']['role'] === 'admin') return;
$_chatName = htmlspecialchars($_SESSION['user']['firstName'], ENT_QUOTES, 'UTF-8');
$_chatApi  = '/LMS_v19/chatbot_api.php';
?>

<!-- ── Chatbot launcher ─────────────────────────────────────── -->
<button id="lms-chat-launcher" type="button" title="Chat with Gem – Library Assistant">
  <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7
             8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8
             8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"
          stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
</button>

<!-- ── Chat panel ──────────────────────────────────────────── -->
<div id="lms-chat-panel" aria-hidden="true">

  <div class="lms-chat-header">
    <div class="lms-chat-title-row">
      <div class="lms-chat-avatar">📚</div>
      <div>
        <div class="lms-chat-name">Gem</div>
        <div class="lms-chat-sub">TechGiants Library Assistant</div>
      </div>
    </div>
    <button id="lms-chat-close" type="button" aria-label="Close">✕</button>
  </div>

  <div id="lms-chat-messages" class="lms-chat-messages"></div>

  <form id="lms-chat-form" class="lms-chat-form" autocomplete="off">
    <input id="lms-chat-input" type="text"
           placeholder="Ask about books, genres, due dates…"
           maxlength="500" required />
    <button id="lms-chat-send" type="submit" aria-label="Send">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"
              stroke="white" stroke-width="2.2"
              stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
  </form>
</div>


<script>
(function(){
  const API    = <?= json_encode($_chatApi) ?>;
  const FNAME  = <?= json_encode($_chatName) ?>;
  const launcher = document.getElementById('lms-chat-launcher');
  const panel    = document.getElementById('lms-chat-panel');
  const closeBtn = document.getElementById('lms-chat-close');
  const form     = document.getElementById('lms-chat-form');
  const input    = document.getElementById('lms-chat-input');
  const sendBtn  = document.getElementById('lms-chat-send');
  const messages = document.getElementById('lms-chat-messages');
  let greeted = false;

  // ── Session history (persists across page navigations) ────────
  const HISTORY_KEY = 'lms_chat_history_' + <?= json_encode((int)($_SESSION['user']['id'] ?? 0)) ?>;
  let chatHistory = [];

  function saveHistory() {
    try { sessionStorage.setItem(HISTORY_KEY, JSON.stringify(chatHistory)); } catch {}
  }
  function loadStoredHistory() {
    try { return JSON.parse(sessionStorage.getItem(HISTORY_KEY) || '[]'); } catch { return []; }
  }

  // ── Suggestion chips ──────────────────────────────────────────
  const GREETING_CHIPS = [
    { label: '📚 Suggest me a book',    msg: 'Suggest me a book'                    },
    { label: '📖 My borrowed books',    msg: 'My borrowed books'                    },
    { label: '📅 Check due dates',      msg: 'Check my due dates'                   },
    { label: '💰 My wallet balance',    msg: 'My wallet balance'                    },
    { label: '😔 I\'m feeling down',    msg: "I'm feeling down"                     },
    { label: '🏛️ About this library',  msg: 'About this library'                   },
    { label: '❤️ My favorites',         msg: 'My favorites'                         },
    { label: '📋 Reading history',      msg: 'My reading history'                   },
    { label: '📊 My reading stats',     msg: 'My reading stats'                     },
    { label: '🆕 New arrivals',         msg: "What's new in the library?"           },
    { label: '💸 My fines',             msg: 'Do I have any overdue fines?'         },
    { label: '🎭 Browse by genre',      msg: 'Show me fantasy books'               },
  ];

  function clearChips() {
    document.querySelectorAll('.lms-chips').forEach(el => el.remove());
  }

  function addChips(opts) {
    const row = document.createElement('div');
    row.className = 'lms-chips';
    opts.forEach(function(opt) {
      const btn = document.createElement('button');
      btn.className = 'lms-chip';
      btn.type = 'button';
      btn.textContent = opt.label;
      btn.addEventListener('click', function() {
        clearChips();
        input.value = opt.msg;
        form.dispatchEvent(new Event('submit'));
      });
      row.appendChild(btn);
    });
    messages.appendChild(row);
    scrollDown();
  }

  // ── Open / close ──────────────────────────────────────────────
  function openChat() {
    panel.classList.add('lms-open');
    panel.setAttribute('aria-hidden', 'false');
    launcher.classList.add('lms-hidden');
    if (!greeted) {
      chatHistory = loadStoredHistory();
      if (chatHistory.length > 0) {
        // Restore previous session messages
        chatHistory.forEach(function(entry) {
          if (entry.role === 'user') addUser(entry.text, true);
          else if (entry.role === 'bot') addBot(entry.text, true);
          else if (entry.role === 'book') addBookCard(entry.data, true);
        });
      } else {
        addBot("Hi " + FNAME + "! 👋 I'm **Gem**, your library assistant.\n\nI can suggest books by genre or topic, check your borrowed books, due dates, and answer any library questions.\n\nWhat can I help you with?", true);
        addChips(GREETING_CHIPS);
      }
      greeted = true;
    }
    setTimeout(() => input.focus(), 60);
  }
  function closeChat() {
    panel.classList.remove('lms-open');
    panel.setAttribute('aria-hidden', 'true');
    launcher.classList.remove('lms-hidden');
  }
  launcher.addEventListener('click', openChat);
  closeBtn.addEventListener('click', closeChat);

  // Clear history button
  (function() {
    const clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.title = 'Clear chat history';
    clearBtn.className = 'lms-chat-clear-btn';
    clearBtn.textContent = '🗑';
    clearBtn.addEventListener('click', function() {
      chatHistory = [];
      sessionStorage.removeItem(HISTORY_KEY);
      messages.innerHTML = '';
      clearChips();
      greeted = false;
      openChat();
    });
    closeBtn.parentNode.insertBefore(clearBtn, closeBtn);
  })();

  // ── Escape HTML ───────────────────────────────────────────────
  function esc(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  // ── Convert plain text to HTML (minimal markdown) ─────────────
  function toHtml(text) {
    return esc(text)
      .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.*?)\*/g,     '<em>$1</em>')
      .replace(/^[•\-]\s+(.+)$/gm, '<li>$1</li>')
      .replace(/(<li>[\s\S]*?<\/li>)/g, '<ul>$1</ul>')
      .replace(/\n/g, '<br>');
  }

  // ── Append user bubble ────────────────────────────────────────
  function addUser(text, skipSave) {
    const el = document.createElement('div');
    el.className = 'lms-msg user';
    el.textContent = text;
    messages.appendChild(el);
    scrollDown();
    if (!skipSave) { chatHistory.push({role:'user', text}); saveHistory(); }
  }

  // ── Append bot bubble ─────────────────────────────────────────
  function addBot(text, skipSave) {
    const el = document.createElement('div');
    el.className = 'lms-msg bot';
    el.innerHTML = toHtml(text);
    messages.appendChild(el);
    scrollDown();
    if (!skipSave) { chatHistory.push({role:'bot', text}); saveHistory(); }
  }

  // ── Append book card ──────────────────────────────────────────
  function addBookCard(book, skipSave) {
    const card = document.createElement('div');
    card.className = 'lms-book-card';

    let badge;
    if (book.available === true) {
      badge = '<span class="lms-book-badge lms-badge-library">✅ Available</span>';
    } else if (book.available === false) {
      badge = '<span class="lms-book-badge" style="background:#fee2e2;color:#c0392b;">❌ Borrowed</span>';
    } else if (book.in_library) {
      badge = '<span class="lms-book-badge lms-badge-library">✓ In Library</span>';
    } else {
      badge = '<span class="lms-book-badge lms-badge-suggest">Suggested Read</span>';
    }

    let termsHtml = '';
    if (book.matched_terms && book.matched_terms.length) {
      termsHtml = '<div class="lms-book-terms">Matched: '
        + book.matched_terms.slice(0, 5).map(t => '<span>' + esc(t) + '</span>').join('')
        + '</div>';
    }

    card.innerHTML =
      '<div class="lms-book-head">' +
        '<div class="lms-book-title">' + esc(book.title) + '</div>' +
        badge +
      '</div>' +
      '<div class="lms-book-meta">' + esc(book.author) + ' &nbsp;·&nbsp; ' + esc(book.genre) + ' &nbsp;·&nbsp; ' + esc(book.year) + '</div>' +
      '<div class="lms-book-desc">' + esc(book.description) + '</div>' +
      termsHtml;

    messages.appendChild(card);
    scrollDown();
    if (!skipSave) { chatHistory.push({role:'book', data: book}); saveHistory(); }
  }

  // ── Typing indicator ──────────────────────────────────────────
  function showTyping() {
    const el = document.createElement('div');
    el.className = 'lms-typing'; el.id = 'lms-typing';
    el.innerHTML = '<span></span><span></span><span></span>';
    messages.appendChild(el); scrollDown();
  }
  function hideTyping() {
    const t = document.getElementById('lms-typing');
    if (t) t.remove();
  }

  function scrollDown() { messages.scrollTop = messages.scrollHeight; }

  // ── Submit ────────────────────────────────────────────────────
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;

    clearChips();
    addUser(text);
    input.value = '';
    sendBtn.disabled = true;
    showTyping();

    try {
      const res  = await fetch(API, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({message: text}),
      });
      hideTyping();

      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        addBot(err.error || 'Something went wrong. Please try again.');
        sendBtn.disabled = false;
        return;
      }

      const data = await res.json();

      if (data.error) {
        addBot('⚠️ ' + data.error);
      } else {
        if (data.message) addBot(data.message);
        (data.books || []).forEach(addBookCard);

        // Contextual follow-up chips
        if (data.type === 'books') {
          addChips([
            { label: '🎭 Try another genre',    msg: 'Show me science fiction books' },
            { label: '📚 More suggestions',     msg: 'Suggest me a book'             },
            { label: '🔍 Check availability',   msg: 'Can I borrow The Alchemist?'   },
            { label: '🆕 New arrivals',         msg: "What's new in the library?"    },
          ]);
        } else if (data.type === 'dynamic') {
          addChips([
            { label: '📚 Suggest a book',       msg: 'Suggest me a book'             },
            { label: '🎭 Browse by genre',      msg: 'Show me fantasy books'         },
            { label: '📅 Check due dates',      msg: 'Check my due dates'            },
            { label: '📊 Reading stats',        msg: 'My reading stats'              },
          ]);
        } else if (data.type === 'intent') {
          addChips([
            { label: '📚 Suggest me a book',    msg: 'Suggest me a book'             },
            { label: '🔍 Is a book available?', msg: 'Is Harry Potter available?'    },
            { label: '🎯 Recommend for me',     msg: 'Recommend based on my history' },
            { label: '💸 My fines',             msg: 'Do I have any overdue fines?'  },
          ]);
        } else {
          addChips([
            { label: '📚 Suggest me a book',    msg: 'Suggest me a book'             },
            { label: '🎭 Browse by genre',      msg: 'Show me fiction books'         },
            { label: '✍️ Books by an author',   msg: 'Books by George Orwell'        },
            { label: '🆕 New arrivals',         msg: "What's new in the library?"    },
          ]);
        }
      }
    } catch {
      hideTyping();
      addBot('⚠️ Network error — please check your connection and try again.');
    }

    sendBtn.disabled = false;
    input.focus();
  });
})();
</script>
