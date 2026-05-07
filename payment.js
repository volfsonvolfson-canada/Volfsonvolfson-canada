/**
 * Payment.js
 * Stripe Elements integration for payment processing
 */

// Stripe Publishable Key - must be set in config.php
// For testing you can use: pk_test_...
let STRIPE_PUBLISHABLE_KEY = '';

// Initializing Stripe
let stripe = null;
let elements = null;
let paymentElement = null;

/**
 * Initializing Stripe and Payment Elements
 * @param {string} publishableKey Stripe Publishable Key
 * @param {string} clientSecret Client Secret from Payment Intent
 * @returns {Promise<boolean>} Initialization success
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

    // Loading Stripe.js
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

    // Initializing Stripe
    stripe = window.Stripe(publishableKey);

    // Creating payment elements
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
 * Creating a Payment Element
 * @param {string} containerSelector Container selector for the payment element
 * @returns {Promise<boolean>} Creation success
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

    // Cleaning the container
    container.innerHTML = '';

    // Create a Payment Element
    paymentElement = elements.create('payment', {
      layout: 'tabs'
    });

    // Mounting the element into a container
    paymentElement.mount(containerSelector);

    return true;
  } catch (error) {
    console.error('Create payment element error:', error);
    return false;
  }
}

/**
 * Payment form processing
 * @param {string} clientSecret Client Secret from Payment Intent
 * @param {Function} onSuccess Callback upon successful payment
 * @param {Function} onError Callback on error
 * @returns {Promise<boolean>} Processing success
 */
async function handlePayment(clientSecret, onSuccess, onError) {
  try {
    if (!stripe || !paymentElement) {
      throw new Error('Stripe not initialized');
    }

    // Showing the loading indicator
    showPaymentLoading();

    // We confirm the payment
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
      // Showing the error
      showPaymentError(error.message);
      if (onError) {
        onError(error);
      }
      return false;
    }

    if (paymentIntent && paymentIntent.status === 'succeeded') {
      // Successful payment
      if (onSuccess) {
        onSuccess(paymentIntent);
      }
      return true;
    }

    // If a redirect is required (for some payment methods)
    if (paymentIntent && paymentIntent.status === 'requires_action') {
      // Stripe will automatically handle the redirect
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
 * Show payment error
 * @param {string} message Error message
 */
function showPaymentError(message) {
  // Removing previous errors
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

  // Insert an error before the payment form
  const paymentForm = document.getElementById('payment-form') || document.querySelector('.payment-form');
  if (paymentForm) {
    paymentForm.insertBefore(errorContainer, paymentForm.firstChild);
  } else {
    document.body.appendChild(errorContainer);
  }
}

/**
 * Hide payment error
 */
function hidePaymentError() {
  const errorContainer = document.getElementById('payment-errors');
  if (errorContainer) {
    errorContainer.remove();
  }
}

/**
 * Show payment loading status
 */
function showPaymentLoading() {
  const submitButton = document.getElementById('payment-submit') || document.querySelector('#payment-form button[type="submit"]');
  if (submitButton) {
    submitButton.disabled = true;
    submitButton.textContent = 'Processing...';
  }

  // Showing loading overlay
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

    // Adding CSS animation for the spinner
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
 * Hide payment loading status
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
 * Initializing the payment form on the page
 * @param {string} publishableKey Stripe Publishable Key
 * @param {string} clientSecret Client Secret from Payment Intent
 */
async function initPaymentForm(publishableKey, clientSecret) {
  try {
    // Initializing Stripe
    const initialized = await initStripe(publishableKey, clientSecret);
    if (!initialized) {
      showPaymentError('Failed to initialize payment system');
      return;
    }

    // Create a Payment Element
    const elementCreated = await createPaymentElement('#payment-element');
    if (!elementCreated) {
      showPaymentError('Failed to create payment form');
      return;
    }

    // Bind the payment form handler
    const paymentForm = document.getElementById('payment-form');
    if (paymentForm) {
      paymentForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Processing the payment
        await handlePayment(
          clientSecret,
          (paymentIntent) => {
            // Successful payment
            console.log('Payment succeeded:', paymentIntent);
            
            // Redirect to confirmation page with updated data
            const confirmationCode = sessionStorage.getItem('last_confirmation_code') || '';
            window.location.href = 'booking-confirmation.html' + 
              (confirmationCode ? `?code=${encodeURIComponent(confirmationCode)}&paid=true` : '');
          },
          (error) => {
            // Payment error
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

// Exporting functions for use in other modules
window.PaymentAPI = {
  initStripe,
  createPaymentElement,
  handlePayment,
  initPaymentForm,
  showPaymentError,
  hidePaymentError
};



