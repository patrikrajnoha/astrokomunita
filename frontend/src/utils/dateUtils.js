/**
 * Date utility functions - centralizované formátovanie dátumov
 * Nahradzuje duplicitné formatDate funkcie vo viacerých komponentoch
 */

/**
 * Formátuje dátum do lokálneho stringu
 * @param {string|Date|null} value - Dátum na formátovanie
 * @param {Object} options - Možnosti formátovania
 * @param {string} options.locale - Lokalizácia (default: 'sk-SK')
 * @param {string} options.dateStyle - Štýl dátumu (default: 'medium')
 * @param {string} options.timeStyle - Štýl času (default: 'short')
 * @returns {string} Formátovaný dátum alebo '-' pre null/invalid
 */
export function formatDate(value, options = {}) {
  if (!value) return '-';
  
  const {
    locale = 'sk-SK',
    dateStyle = 'medium',
    timeStyle = 'short'
  } = options;
  
  const d = new Date(value);
  if (isNaN(d.getTime())) return String(value);
  
  return d.toLocaleString(locale, { dateStyle, timeStyle });
}

/**
 * Formátuje dátum pre datetime-local input
 * @param {string|Date|null} value - Dátum na formátovanie
 * @returns {string} Formátovaný dátum alebo prázdny string
 */
export function toDateTimeLocal(value) {
  if (!value) return '';
  
  const d = new Date(value);
  if (isNaN(d.getTime())) return '';
  
  const pad = (n) => String(n).padStart(2, '0');
  const yyyy = d.getFullYear();
  const mm = pad(d.getMonth() + 1);
  const dd = pad(d.getDate());
  const hh = pad(d.getHours());
  const min = pad(d.getMinutes());
  
  return `${yyyy}-${mm}-${dd}T${hh}:${min}`;
}

/**
 * Konvertuje datetime-local string späť na ISO string
 * @param {string} value - DateTime local string
 * @returns {string|null} ISO string alebo null
 */
export function fromDateTimeLocal(value) {
  if (!value) return null;
  
  const d = new Date(value);
  if (isNaN(d.getTime())) return null;
  
  return d.toISOString();
}

/**
 * Formátuje relatívny čas (napr. "pred 2 hodinami")
 * @param {string|Date|null} value - Dátum
 * @param {string} locale - Lokalizácia (default: 'sk-SK')
 * @returns {string} Relatívny čas
 */
export function formatRelativeTime(value, locale = 'sk-SK') {
  if (!value) return '-';
  
  const d = new Date(value);
  if (isNaN(d.getTime())) return String(value);
  
  const now = new Date();
  const diffMs = now - d;
  const diffMinutes = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);
  
  if (diffMinutes < 1) return 'práve teraz';
  if (diffMinutes < 60) return `pred ${diffMinutes} minútami`;
  if (diffHours < 24) return `pred ${diffHours} hodinami`;
  if (diffDays < 7) return `pred ${diffDays} dňami`;
  
  return formatDate(value, { locale, timeStyle: undefined });
}

/**
 * Skontroluje či je dátum v minulosti
 * @param {string|Date|null} value - Dátum na kontrolu
 * @returns {boolean} True ak je dátum v minulosti
 */
export function isPast(value) {
  if (!value) return false;
  
  const d = new Date(value);
  if (isNaN(d.getTime())) return false;
  
  return d.getTime() <= Date.now();
}

/**
 * Skontroluje či je dátum v budúcnosti
 * @param {string|Date|null} value - Dátum na kontrolu
 * @returns {boolean} True ak je dátum v budúcnosti
 */
export function isFuture(value) {
  if (!value) return false;
  
  const d = new Date(value);
  if (isNaN(d.getTime())) return false;
  
  return d.getTime() > Date.now();
}
