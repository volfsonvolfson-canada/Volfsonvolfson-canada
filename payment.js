/**
 * Payment.js
 * Интеграция Stripe Elements для обработки платежей
 */

// Stripe Publishable Key - должен быть установлен в config.php
// Для тестирования можно использовать: pk_test_...
let STRIPE_PUBLISHABLE_KEY = '';

// Инициализация Stripe
let stripe = null;
let elements = null;
let paymentElement = null;

/**
 * Инициализация Stripe и элементов платежа
 * @param {string} publishableKey Stripe Publishable Key
 * @param {string} clientSecret Client Secret от Payment Intent
 * @returns {Promise<boolean>} Успешность инициализации
 */
async function initStripe(publishableKey, clientSecret) {
  try {
    if (!publishableKey) {
      console.error('Stripe Publishable Key is not configured');
      return false;
    }

    if (!clientSecret) {
      console.error('Client Secret is required for Stripe initialization');
      return false;
    }

    STRIPE_PUBLISHABLE_KEY = publishableKey;

    // Загружаем Stripe.js
    if (!window.Stripe) {
      const script = document.createElement('script');
      script.src = 'https://js.stripe.com/v3/';
      script.async = true;
      await new Promise((resolve, reject) => {
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
      });
    }

    // Инициализируем Stripe
    stripe = window.Stripe(publishableKey);

    // Создаем элементы платежа
    elements = stripe.elements({
      clientSecret: clientSecret,
      appearance: {
        theme: 'stripe',
        variables: {
          colorPrimary: '#4a5568',
          colorBackground: '#ffffff',
          colorText: '#333333',
          colorDanger: '#e53e3e',
          fontFamily: 'system-ui, sans-serif',
          spacingUnit: '4px',
          borderRadius: '8px'
        }
      }
    });

    return true;
  } catch (error) {
    console.error('Stripe initialization error:', error);
    return false;
  }
}

/**
 * Создание Payment Element
 * @param {string} containerSelector Селектор контейнера для элемента платежа
 * @returns {Promise<boolean>} Успешность создания
 */
async function createPaymentElement(containerSelector) {
  try {
    if (!elements) {
      console.error('Stripe Elements not initialized');
      return false;
    }

    const container = document.querySelector(containerSelector);
    if (!container) {
      console.error('Payment container not found:', containerSelector);
      return false;
    }

    // Очищаем контейнер
    container.innerHTML = '';

    // Создаем Payment Element
    paymentElement = elements.create('payment', {
      layout: 'tabs'
    });

    // Монтируем элемент в контейнер
    paymentElement.mount(containerSelector);

    return true;
  } catch (error) {
    console.error('Create payment element error:', error);
    return false;
  }
}

/**
 * Обработка формы оплаты
 * @param {string} clientSecret Client Secret от Payment Intent
 * @param {Function} onSuccess Callback при успешной оплате
 * @param {Function} onError Callback при ошибке
 * @returns {Promise<boolean>} Успешность обработки
 */
async function handlePayment(clientSecret, onSuccess, onError) {
  try {
    if (!stripe || !paymentElement) {
      throw new Error('Stripe not initialized');
    }

    // Показываем индикатор загрузки
    showPaymentLoading();

    // Подтверждаем платеж
    const { error, paymentIntent } = await stripe.confirmPayment({
      elements: elements,
      clientSecret: clientSecret,
      confirmParams: {
        return_url: window.location.origin + '/booking-confirmation.html'
      },
      redirect: 'if_required'
    });

    hidePaymentLoading();

    if (error) {
      // Показываем ошибку
      showPaymentError(error.message);
      if (onError) {
        onError(error);
      }
      return false;
    }

    if (paymentIntent && paymentIntent.status === 'succeeded') {
      // Успешная оплата
      if (onSuccess) {
        onSuccess(paymentIntent);
      }
      return true;
    }

    // Если требуется редирект (для некоторых методов оплаты)
    if (paymentIntent && paymentIntent.status === 'requires_action') {
      // Stripe автоматически обработает редирект
      return true;
    }

    return false;
  } catch (error) {
    console.error('Handle payment error:', error);
    hidePaymentLoading();
    showPaymentError(error.message || 'An error occurred during payment');
    if (onError) {
      onError(error);
    }
    return false;
  }
}

/**
 * Показать ошибку оплаты
 * @param {string} message Сообщение об ошибке
 */
function showPaymentError(message) {
  // Удаляем предыдущие ошибки
  hidePaymentError();

  const errorContainer = document.getElementById('payment-errors') || document.createElement('div');
  errorContainer.id = 'payment-errors';
  errorContainer.className = 'payment-error';
  errorContainer.style.cssText = `
    background-color: #fed7d7;
    border: 1px solid #e53e3e;
    border-radius: 8px;
    padding: 12px;
    margin: 15px 0;
    color: #e53e3e;
  `;
  errorContainer.textContent = message || 'Payment failed';

  // Вставляем ошибку перед формой оплаты
  const paymentForm = document.getElementById('payment-form') || document.querySelector('.payment-form');
  if (paymentForm) {
    paymentForm.insertBefore(errorContainer, paymentForm.firstChild);
  } else {
    document.body.appendChild(errorContainer);
  }
}

/**
 * Скрыть ошибку оплаты
 */
function hidePaymentError() {
  const errorContainer = document.getElementById('payment-errors');
  if (errorContainer) {
    errorContainer.remove();
  }
}

/**
 * Показать состояние загрузки оплаты
 */
function showPaymentLoading() {
  const submitButton = document.getElementById('payment-submit') || document.querySelector('#payment-form button[type="submit"]');
  if (submitButton) {
    submitButton.disabled = true;
    submitButton.textContent = 'Processing...';
  }

  // Показываем overlay загрузки
  let overlay = document.getElementById('payment-loading-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'payment-loading-overlay';
    overlay.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10000;
    `;
    overlay.innerHTML = `
      <div style="background: white; padding: 30px; border-radius: 8px; text-align: center;">
        <div style="margin-bottom: 15px;">Processing your payment...</div>
        <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #4a5568; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto;"></div>
      </div>
    `;
    document.body.appendChild(overlay);

    // Добавляем CSS анимацию для спиннера
    if (!document.getElementById('loading-spinner-style')) {
      const style = document.createElement('style');
      style.id = 'loading-spinner-style';
      style.textContent = `
        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }
      `;
      document.head.appendChild(style);
    }
  }
  overlay.style.display = 'flex';
}

/**
 * Скрыть состояние загрузки оплаты
 */
function hidePaymentLoading() {
  const submitButton = document.getElementById('payment-submit') || document.querySelector('#payment-form button[type="submit"]');
  if (submitButton) {
    submitButton.disabled = false;
    submitButton.textContent = 'Pay now';
  }

  const overlay = document.getElementById('payment-loading-overlay');
  if (overlay) {
    overlay.style.display = 'none';
  }
}

/**
 * Инициализация формы оплаты на странице
 * @param {string} publishableKey Stripe Publishable Key
 * @param {string} clientSecret Client Secret от Payment Intent
 */
async function initPaymentForm(publishableKey, clientSecret) {
  try {
    // Инициализируем Stripe
    const initialized = await initStripe(publishableKey, clientSecret);
    if (!initialized) {
      showPaymentError('Failed to initialize payment system');
      return;
    }

    // Создаем Payment Element
    const elementCreated = await createPaymentElement('#payment-element');
    if (!elementCreated) {
      showPaymentError('Failed to create payment form');
      return;
    }

    // Привязываем обработчик формы оплаты
    const paymentForm = document.getElementById('payment-form');
    if (paymentForm) {
      paymentForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Обрабатываем платеж
        await handlePayment(
          clientSecret,
          (paymentIntent) => {
            // Успешная оплата
            console.log('Payment succeeded:', paymentIntent);
            
            // Редирект на страницу подтверждения с обновленными данными
            const confirmationCode = sessionStorage.getItem('last_confirmation_code') || '';
            window.location.href = 'booking-confirmation.html' + 
              (confirmationCode ? `?code=${encodeURIComponent(confirmationCode)}&paid=true` : '');
          },
          (error) => {
            // Ошибка оплаты
            console.error('Payment error:', error);
          }
        );
      });
    }
  } catch (error) {
    console.error('Init payment form error:', error);
    showPaymentError(error.message || 'Failed to initialize payment form');
  }
}

// Экспорт функций для использования в других модулях
window.PaymentAPI = {
  initStripe,
  createPaymentElement,
  handlePayment,
  initPaymentForm,
  showPaymentError,
  hidePaymentError
};



