/**
 * Notifications composable - centralizované notifikácie
 * Toast správy pre úspech, error, warning, info
 */

import { ref } from 'vue';
import { TOAST_TYPES } from '@/utils/constants.js';

// Global state pre notifikácie
const notifications = ref([]);

/**
 * Pridá notifikáciu
 * @param {Object} notification - Notifikácia objekt
 */
function addNotification(notification) {
  const id = Date.now() + Math.random();
  const newNotification = {
    id,
    type: TOAST_TYPES.INFO,
    title: '',
    message: '',
    duration: 5000,
    persistent: false,
    ...notification
  };
  
  notifications.value.push(newNotification);
  
  // Auto-remove ak nie je persistent
  if (!newNotification.persistent && newNotification.duration > 0) {
    setTimeout(() => {
      removeNotification(id);
    }, newNotification.duration);
  }
  
  return id;
}

/**
 * Odstráni notifikáciu
 * @param {number} id - ID notifikácie
 */
function removeNotification(id) {
  const index = notifications.value.findIndex(n => n.id === id);
  if (index > -1) {
    notifications.value.splice(index, 1);
  }
}

/**
 * Vymaže všetky notifikácie
 */
function clearAll() {
  notifications.value = [];
}

/**
 * Success notifikácia
 * @param {string} message - Správa
 * @param {string} title - Titulok (optional)
 * @param {Object} options - Ďalšie možnosti
 */
function success(message, title = '', options = {}) {
  return addNotification({
    type: TOAST_TYPES.SUCCESS,
    title: title || 'Úspech',
    message,
    duration: 4000,
    ...options
  });
}

/**
 * Error notifikácia
 * @param {string} message - Správa
 * @param {string} title - Titulok (optional)
 * @param {Object} options - Ďalšie možnosti
 */
function error(message, title = '', options = {}) {
  return addNotification({
    type: TOAST_TYPES.ERROR,
    title: title || 'Chyba',
    message,
    duration: 8000,
    persistent: true,
    ...options
  });
}

/**
 * Warning notifikácia
 * @param {string} message - Správa
 * @param {string} title - Titulok (optional)
 * @param {Object} options - Ďalšie možnosti
 */
function warning(message, title = '', options = {}) {
  return addNotification({
    type: TOAST_TYPES.WARNING,
    title: title || 'Upozornenie',
    message,
    duration: 6000,
    ...options
  });
}

/**
 * Info notifikácia
 * @param {string} message - Správa
 * @param {string} title - Titulok (optional)
 * @param {Object} options - Ďalšie možnosti
 */
function info(message, title = '', options = {}) {
  return addNotification({
    type: TOAST_TYPES.INFO,
    title: title || 'Informácia',
    message,
    duration: 5000,
    ...options
  });
}

/**
 * API error handler - automatické zobrazenie error notifikácie
 * @param {Error|Object} err - Error objekt
 * @param {string} defaultMessage - Default správa
 */
function handleApiError(err, defaultMessage = 'Nastala chyba') {
  let message = defaultMessage;
  
  if (err?.response?.data?.message) {
    message = err.response.data.message;
  } else if (err?.message) {
    message = err.message;
  }
  
  // Validáčné errory - zobraziť detaily
  if (err?.response?.status === 422 && err?.response?.data?.errors) {
    const errors = err.response.data.errors;
    const errorMessages = Object.values(errors).flat();
    message = errorMessages.join(', ');
  }
  
  return error(message, 'Chyba');
}

/**
 * API success handler - automatické zobrazenie success notifikácie
 * @param {string} message - Správa
 * @param {Object} response - API response (optional)
 */
function handleApiSuccess(message, response = null) {
  let finalMessage = message;
  
  // Ak response obsahuje message, použiť ju
  if (response?.data?.message) {
    finalMessage = response.data.message;
  }
  
  return success(finalMessage);
}

/**
 * Composable pre použitie v komponentoch
 */
export function useNotifications() {
  return {
    // State
    notifications,
    
    // Methods
    addNotification,
    removeNotification,
    clearAll,
    success,
    error,
    warning,
    info,
    handleApiError,
    handleApiSuccess
  };
}

// Export static functions pre použitie mimo komponentov
export const notificationsApi = {
  success,
  error,
  warning,
  info,
  handleApiError,
  handleApiSuccess
};
