// Chat with Rob — server-backed chat (host_chat_api.php)
class MessagesSystem {
  constructor() {
    this.currentUser = null;
    /** @type {any[]} last successful `guest_chat_my_threads` list (DESC by activity) */
    this.lastThreads = [];
    this.init();
  }

  async init() {
    if (!(await this.ensureGuestSession())) {
      return;
    }
    this.setupEventListeners();
    void this.loadMessages();
  }

  /** @returns {Promise<boolean>} */
  async ensureGuestSession() {
    if (typeof window.btbVerifyGuestSession === 'function') {
      const { ok, user } = await window.btbVerifyGuestSession();
      if (!ok || !user) {
        window.location.href = 'login.html';
        return false;
      }
      this.currentUser = window.btbMapVerifyUser ? window.btbMapVerifyUser(user) : user;
      try {
        localStorage.setItem('btb_user_data', JSON.stringify(this.currentUser));
      } catch (_) {}
      return true;
    }
    const token = localStorage.getItem('btb_auth_token');
    const userData = localStorage.getItem('btb_user_data');
    if (!token || !userData) {
      window.location.href = 'login.html';
      return false;
    }
    try {
      this.currentUser = JSON.parse(userData);
    } catch (error) {
      try {
        localStorage.removeItem('btb_auth_token');
        localStorage.removeItem('btb_user_data');
      } catch (_) {}
      window.location.href = 'login.html';
      return false;
    }
    return true;
  }

  guestApiFetch(fd) {
    const apiHref = typeof window.btbApiPhp === 'function' ? window.btbApiPhp() : 'api.php';
    const base = { method: 'POST', body: fd, cache: 'no-store' };
    if (typeof window.btbGuestFetchInit === 'function') {
      return fetch(apiHref, window.btbGuestFetchInit(base));
    }
    const token = localStorage.getItem('btb_auth_token');
    const headers = token ? { Authorization: 'Bearer ' + token } : {};
    return fetch(apiHref, { ...base, credentials: 'same-origin', headers });
  }

  setupEventListeners() {
    const btn = document.getElementById('guest-chat-send-btn');
    if (btn && !btn.dataset.btbBound) {
      btn.dataset.btbBound = '1';
      btn.addEventListener('click', () => void this.sendFromComposer());
    }
    const ta = document.getElementById('guest-chat-compose-input');
    if (ta && !ta.dataset.btbEnterBound) {
      ta.dataset.btbEnterBound = '1';
      ta.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          void this.sendFromComposer();
        }
      });
    }
  }

  getLatestThreadId(threads) {
    if (!Array.isArray(threads) || threads.length === 0) {
      return 0;
    }
    const id = parseInt(threads[0].id, 10);
    return id > 0 ? id : 0;
  }

  updateCardTitle(hasThreads) {
    const titleEl = document.getElementById('guest-chat-card-title');
    if (titleEl) {
      titleEl.textContent = hasThreads ? 'Message History' : 'Send a message';
    }
  }

  async sendFromComposer() {
    const ta = document.getElementById('guest-chat-compose-input');
    if (!ta) {
      return;
    }
    const body = (ta.value || '').trim();
    if (!body) {
      this.showMessage('Please enter a message', 'error');
      return;
    }

    const latestId = this.getLatestThreadId(this.lastThreads);

    try {
      const fd = new FormData();
      fd.append('action', 'guest_chat_send');
      fd.append('body', body);
      if (latestId > 0) {
        fd.append('thread_id', String(latestId));
      }
      const res = await this.guestApiFetch(fd);
      if (res.status === 429) {
        this.showMessage('Too many requests. Please wait a minute and try again.', 'error');
        return;
      }
      const json = await res.json().catch(() => ({}));
      if (!json.success) {
        this.showMessage((json && json.error) || 'Could not send message', 'error');
        return;
      }
      ta.value = '';
      this.showMessage('Sent.', 'success');
      await this.loadMessages();
    } catch (e) {
      this.showMessage('Network error. Please try again.', 'error');
    }
  }

  async markThreadsRead(threads) {
    if (!Array.isArray(threads)) {
      return;
    }
    for (const t of threads) {
      if (!t || !t.guest_unread) {
        continue;
      }
      try {
        const fd = new FormData();
        fd.append('action', 'guest_chat_mark_read');
        fd.append('thread_id', String(t.id));
        await this.guestApiFetch(fd);
      } catch (_) {}
    }
    try {
      window.dispatchEvent(new CustomEvent('btb:messages:updated'));
    } catch (_) {}
  }

  fillListHint(container, text, opts) {
    const err = opts && opts.error;
    container.textContent = '';
    const p = document.createElement('p');
    p.className = 'guest-chat-empty-hint';
    if (err) {
      p.style.color = '#f87171';
    }
    p.textContent = text;
    container.appendChild(p);
  }

  async loadMessages() {
    const container = document.getElementById('messages-list');
    if (!container) {
      return;
    }
    this.fillListHint(container, 'Loading…', {});
    try {
      const fd = new FormData();
      fd.append('action', 'guest_chat_my_threads');
      const res = await this.guestApiFetch(fd);
      const json = await res.json().catch(() => ({}));
      if (!json.success) {
        this.lastThreads = [];
        this.fillListHint(
          container,
          (json && json.error) || 'Could not load messages. Try signing in again.',
          { error: true },
        );
        this.updateCardTitle(false);
        return;
      }
      const threads = (json.data && json.data.threads) || [];
      this.lastThreads = threads;
      void this.markThreadsRead(threads);
      this.updateCardTitle(threads.length > 0);
      this.renderChatStream(container, threads);
      try {
        window.dispatchEvent(new CustomEvent('btb:messages:updated'));
      } catch (_) {}
    } catch (e) {
      this.lastThreads = [];
      this.fillListHint(container, 'Network error loading messages.', { error: true });
      this.updateCardTitle(false);
    }
  }

  formatDate(iso) {
    if (!iso) {
      return '';
    }
    try {
      const d = new Date(iso);
      if (Number.isNaN(d.getTime())) {
        return String(iso);
      }
      return d.toLocaleString();
    } catch (_) {
      return String(iso);
    }
  }

  /**
   * One chat stream: show messages from the most recently active thread only
   * (API returns threads sorted by last_message_at DESC).
   */
  renderChatStream(container, threads) {
    if (!threads.length) {
      this.fillListHint(container, 'No messages yet — write your first message below.', {});
      return;
    }
    const primary = threads[0];
    const msgs = Array.isArray(primary.messages) ? primary.messages : [];
    container.textContent = '';
    if (msgs.length === 0) {
      this.fillListHint(container, 'No messages in this thread yet.', {});
      return;
    }
    msgs.forEach((m) => {
      const isGuest = m.sender !== 'staff';
      const bubble = document.createElement('div');
      bubble.className =
        'guest-chat-bubble ' + (isGuest ? 'guest-chat-bubble--guest' : 'guest-chat-bubble--host');
      const meta = document.createElement('div');
      meta.className = 'guest-chat-bubble-meta';
      meta.textContent = (isGuest ? 'You' : 'Host') + ' · ' + this.formatDate(m.created_at);
      const body = document.createElement('div');
      body.textContent = m.body != null ? String(m.body) : '';
      bubble.appendChild(meta);
      bubble.appendChild(body);
      container.appendChild(bubble);
    });
    container.scrollTop = container.scrollHeight;
  }

  showMessage(message, type = 'info') {
    const messageEl = document.createElement('div');
    messageEl.className = `auth-message auth-message--${type}`;
    messageEl.textContent = message;
    messageEl.style.position = 'fixed';
    messageEl.style.top = '20px';
    messageEl.style.right = '20px';
    messageEl.style.zIndex = '100010';
    messageEl.style.maxWidth = '320px';

    document.body.appendChild(messageEl);

    setTimeout(() => {
      if (messageEl.parentNode) {
        messageEl.parentNode.removeChild(messageEl);
      }
    }, 5000);
  }
}

function initMessagesApp() {
  if (!window.messagesSystem) {
    window.messagesSystem = new MessagesSystem();
  }
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initMessagesApp);
} else {
  initMessagesApp();
}

window.MessagesSystem = MessagesSystem;
