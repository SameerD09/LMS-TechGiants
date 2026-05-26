<?php
// chatbot_auth_widget.php — floating assistant for login/signup pages
// Include with $_chatPage set to 'login' or 'signup' before requiring this file.
$_authPage = $_chatPage ?? 'login';
$_authApi  = '/LMS_v19/chatbot_public_api.php';
?>

<!-- ── Auth chatbot launcher ──────────────────────────────── -->
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
    <div class="lms-chat-header-actions">
      <button id="lms-chat-clear" type="button" title="Clear chat" class="lms-chat-clear-btn">🗑</button>
      <button id="lms-chat-close" type="button" aria-label="Close">✕</button>
    </div>
  </div>

  <div id="lms-chat-messages" class="lms-chat-messages"></div>

  <form id="lms-chat-form" class="lms-chat-form" autocomplete="off">
    <input id="lms-chat-input" type="text"
           placeholder="Ask about login, signup, passwords…"
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
  const API  = <?= json_encode($_authApi) ?>;
  const PAGE = <?= json_encode($_authPage) ?>;

  const launcher = document.getElementById('lms-chat-launcher');
  const panel    = document.getElementById('lms-chat-panel');
  const closeBtn = document.getElementById('lms-chat-close');
  const clearBtn = document.getElementById('lms-chat-clear');
  const form     = document.getElementById('lms-chat-form');
  const input    = document.getElementById('lms-chat-input');
  const sendBtn  = document.getElementById('lms-chat-send');
  const messages = document.getElementById('lms-chat-messages');
  let greeted = false;

  const HISTORY_KEY = 'lms_auth_chat_history';
  let chatHistory = [];

  function saveHistory() {
    try { sessionStorage.setItem(HISTORY_KEY, JSON.stringify(chatHistory)); } catch {}
  }
  function loadStoredHistory() {
    try { return JSON.parse(sessionStorage.getItem(HISTORY_KEY) || '[]'); } catch { return []; }
  }

  // ── Context-aware chips ───────────────────────────────────────────────────
  const LOGIN_CHIPS = [
    { label: '🔑 How to log in',       msg: 'How do I log in?'                   },
    { label: '🔒 Forgot password',      msg: 'I forgot my password'               },
    { label: '👤 Forgot username',      msg: 'I forgot my username'               },
    { label: '📝 How to sign up',       msg: 'How do I sign up?'                  },
    { label: '🏛️ About TechGiants',    msg: 'What is TechGiants Library?'        },
  ];

  const SIGNUP_CHIPS = [
    { label: '📝 How to sign up',       msg: 'How do I sign up?'                  },
    { label: '👤 Username rules',       msg: 'What are the username requirements?' },
    { label: '🔒 Password rules',       msg: 'What are the password requirements?' },
    { label: '💰 Starting balance',     msg: 'What is the starting wallet balance?'},
    { label: '🏛️ About TechGiants',    msg: 'What is TechGiants Library?'        },
  ];

  const GREETING_CHIPS = PAGE === 'signup' ? SIGNUP_CHIPS : LOGIN_CHIPS;

  const GREETING_TEXT = PAGE === 'signup'
    ? "Hi! 👋 I'm **Gem**, TechGiants Library's assistant.\n\nNeed help creating your account? I can walk you through the signup process, explain the rules, or tell you what to expect after joining.\n\nWhat can I help you with?"
    : "Hi! 👋 I'm **Gem**, TechGiants Library's assistant.\n\nHaving trouble signing in, or want to know more about TechGiants? I'm here to help!\n\nWhat can I help you with?";

  // ── Open / close ──────────────────────────────────────────────────────────
  function openChat() {
    panel.classList.add('lms-open');
    panel.setAttribute('aria-hidden', 'false');
    launcher.classList.add('lms-hidden');
    if (!greeted) {
      chatHistory = loadStoredHistory();
      if (chatHistory.length > 0) {
        chatHistory.forEach(function(entry) {
          if (entry.role === 'user') addUser(entry.text, true);
          else if (entry.role === 'bot') addBot(entry.text, true);
        });
      } else {
        addBot(GREETING_TEXT, true);
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

  function resetChat() {
    chatHistory = [];
    sessionStorage.removeItem(HISTORY_KEY);
    messages.innerHTML = '';
    greeted = false;
    openChat();
  }

  launcher.addEventListener('click', openChat);
  closeBtn.addEventListener('click', closeChat);
  clearBtn.addEventListener('click', resetChat);

  // ── Chips ─────────────────────────────────────────────────────────────────
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

  // ── Escape & minimal markdown ─────────────────────────────────────────────
  function esc(s) {
    return String(s).replace(/[&<>"']/g, c =>
      ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  function toHtml(text) {
    return esc(text)
      .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.*?)\*/g,     '<em>$1</em>')
      .replace(/`([^`]+)`/g,     '<code style="background:var(--cream,#f5f0e8);padding:1px 4px;border-radius:4px;font-family:monospace;">$1</code>')
      .replace(/^[•\-]\s+(.+)$/gm, '<li>$1</li>')
      .replace(/(<li>[\s\S]*?<\/li>)/g, '<ul>$1</ul>')
      .replace(/\n/g, '<br>');
  }

  // ── Bubbles ───────────────────────────────────────────────────────────────
  function addUser(text, skipSave) {
    const el = document.createElement('div');
    el.className = 'lms-msg user';
    el.textContent = text;
    messages.appendChild(el);
    scrollDown();
    if (!skipSave) { chatHistory.push({role:'user', text}); saveHistory(); }
  }

  function addBot(text, skipSave) {
    const el = document.createElement('div');
    el.className = 'lms-msg bot';
    el.innerHTML = toHtml(text);
    messages.appendChild(el);
    scrollDown();
    if (!skipSave) { chatHistory.push({role:'bot', text}); saveHistory(); }
  }

  // ── Typing indicator ──────────────────────────────────────────────────────
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

  // ── Submit ────────────────────────────────────────────────────────────────
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
      const res = await fetch(API, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
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

        // Follow-up chips based on response type and current page
        if (data.type === 'intent') {
          if (PAGE === 'signup') {
            addChips([
              { label: '📝 Signup steps',         msg: 'How do I sign up?'                   },
              { label: '👤 Username rules',        msg: 'What are the username requirements?' },
              { label: '🔒 Password rules',        msg: 'What are the password requirements?' },
              { label: '💰 Starting balance',      msg: 'What is the starting wallet balance?'},
            ]);
          } else {
            addChips([
              { label: '🔑 Login steps',           msg: 'How do I log in?'                   },
              { label: '🔒 Forgot password',       msg: 'I forgot my password'               },
              { label: '📝 Sign up',               msg: 'How do I sign up?'                  },
              { label: '🏛️ About TechGiants',     msg: 'What is TechGiants Library?'        },
            ]);
          }
        } else {
          // empty / unmatched — show general chips
          addChips(GREETING_CHIPS);
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
