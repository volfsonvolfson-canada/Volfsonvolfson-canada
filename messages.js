// Messages functionality for Back to Base
class MessagesSystem {
  constructor() {
    this.currentUser = null;
    this.init();
  }

  init() {
    this.checkAuth();
    this.setupEventListeners();
    this.loadMessages();
  }

  // Проверка аутентификации
  checkAuth() {
    const token = localStorage.getItem('btb_auth_token');
    const userData = localStorage.getItem('btb_user_data');
    
    if (!token || !userData) {
      // Пользователь не авторизован - перенаправляем на страницу входа
      window.location.href = 'login.html';
      return;
    }

    try {
      this.currentUser = JSON.parse(userData);
    } catch (error) {
      // Ошибка в данных пользователя - перенаправляем на страницу входа
      localStorage.removeItem('btb_auth_token');
      localStorage.removeItem('btb_user_data');
      window.location.href = 'login.html';
      return;
    }
  }

  // Настройка обработчиков событий
  setupEventListeners() {
    // Форма отправки сообщения
    const messageForm = document.getElementById('message-form');
    if (messageForm) {
      messageForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.sendMessage();
      });
    }
  }

  // Отправка сообщения
  async sendMessage() {
    const subject = document.getElementById('message-subject').value;
    const message = document.getElementById('message-text').value;

    if (!subject || !message) {
      this.showMessage('Please fill in all fields', 'error');
      return;
    }

    try {
      // Создаем новое сообщение
      const newMessage = {
        id: `msg_${Date.now()}_${Math.random().toString(36).slice(2,8)}`,
        subject: subject,
        message: message,
        from: this.currentUser.email,
        fromName: this.currentUser.name,
        timestamp: new Date().toISOString(),
        status: 'sent'
      };

      // Сохраняем в localStorage
      this.saveMessage(newMessage);

      // Очищаем форму
      document.getElementById('message-subject').value = '';
      document.getElementById('message-text').value = '';

      // Показываем сообщение об успехе
      this.showMessage('Message sent successfully!', 'success');

      // Обновляем список сообщений
      this.loadMessages();

    } catch (error) {
      this.showMessage('Failed to send message. Please try again.', 'error');
    }
  }

  // Сохранение сообщения
  saveMessage(message) {
    try {
      const messages = JSON.parse(localStorage.getItem('btb_messages') || '[]');
      messages.push(message);
      localStorage.setItem('btb_messages', JSON.stringify(messages));
    } catch (error) {
      console.error('Error saving message:', error);
    }
  }

  // Загрузка сообщений
  loadMessages() {
    try {
      const messages = JSON.parse(localStorage.getItem('btb_messages') || '[]');
      const userMessages = messages.filter(msg => msg.from === this.currentUser.email);
      
      this.renderMessages(userMessages);
    } catch (error) {
      console.error('Error loading messages:', error);
    }
  }

  // Отображение сообщений
  renderMessages(messages) {
    const container = document.getElementById('messages-list');
    if (!container) return;

    if (messages.length === 0) {
      container.innerHTML = '<p class="notice">No messages yet. Start a conversation with your host!</p>';
      return;
    }

    // Сортируем сообщения по времени (новые сверху)
    messages.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

    const messagesHtml = messages.map(message => this.renderMessageCard(message)).join('');
    container.innerHTML = messagesHtml;
  }

  // Создание карточки сообщения
  renderMessageCard(message) {
    const messageDate = new Date(message.timestamp);
    const formattedDate = messageDate.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });

    return `
      <div class="message-card">
        <div class="message-header">
          <h3>${message.subject}</h3>
          <span class="message-date">${formattedDate}</span>
        </div>
        <div class="message-content">
          <p>${message.message}</p>
        </div>
        <div class="message-status">
          <span class="status-badge status-${message.status}">${message.status}</span>
        </div>
      </div>
    `;
  }

  // Показ сообщений
  showMessage(message, type = 'info') {
    // Создаем временное сообщение
    const messageEl = document.createElement('div');
    messageEl.className = `auth-message auth-message--${type}`;
    messageEl.textContent = message;
    messageEl.style.position = 'fixed';
    messageEl.style.top = '20px';
    messageEl.style.right = '20px';
    messageEl.style.zIndex = '1000';
    messageEl.style.maxWidth = '300px';

    document.body.appendChild(messageEl);

    // Автоматически убираем сообщение через 5 секунд
    setTimeout(() => {
      if (messageEl.parentNode) {
        messageEl.parentNode.removeChild(messageEl);
      }
    }, 5000);
  }
}

// Инициализация системы сообщений
document.addEventListener('DOMContentLoaded', () => {
  window.messagesSystem = new MessagesSystem();
});

// Экспорт для использования в других файлах
window.MessagesSystem = MessagesSystem;


