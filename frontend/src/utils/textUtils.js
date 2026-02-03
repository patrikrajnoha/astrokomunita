/**
 * Text utility functions - centralizované text processing
 * Nahradzuje duplicitné text funkcie vo viacerých komponentoch
 */

/**
 * Vytvorí URL-friendly slug z textu
 * @param {string} text - Text na konverziu
 * @returns {string} URL-friendly slug
 */
export function slugify(text) {
  if (!text) return '';
  
  return text
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '') // Remove diacritics
    .replace(/[^a-z0-9\s-]/g, '')    // Keep only alphanumeric, space, hyphen
    .trim()
    .replace(/\s+/g, '-')            // Replace spaces with hyphens
    .replace(/-+/g, '-')             // Replace multiple hyphens with single
    .slice(0, 80);                   // Limit length
}

/**
 * Escape HTML znaky pre bezpečné zobrazenie
 * @param {string} text - Text na escapovanie
 * @returns {string} Escapovaný text
 */
export function escapeHtml(text) {
  if (!text) return '';
  
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/**
 * Jednoduchý inline markdown parser
 * Podporuje: **bold**, *italic*, `code`
 * @param {string} text - Text s markdownom
 * @returns {string} HTML
 */
export function inlineMarkdown(text) {
  if (!text) return '';
  
  const safe = escapeHtml(text);
  let html = safe;
  
  // Code (must be first to avoid conflicts)
  html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
  
  // Bold
  html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
  
  // Italic
  html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
  
  return html;
}

/**
 * Skráti text na zadanú dĺžku a pridá "..."
 * @param {string} text - Text na skrátenie
 * @param {number} maxLength - Maximálna dĺžka
 * @param {string} suffix - Prípona (default: '...')
 * @returns {string} Skrátený text
 */
export function truncate(text, maxLength = 100, suffix = '...') {
  if (!text) return '';
  
  if (text.length <= maxLength) return text;
  
  return text.slice(0, maxLength - suffix.length) + suffix;
}

/**
 * Zmení prvý písmeno na veľké
 * @param {string} text - Text na úpravu
 * @returns {string} Text s veľkým prvým písmenom
 */
export function capitalize(text) {
  if (!text) return '';
  
  return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
}

/**
 * Zmení prvý písmeno každého slova na veľké
 * @param {string} text - Text na úpravu
 * @returns {string} Title case text
 */
export function titleCase(text) {
  if (!text) return '';
  
  return text.replace(/\w\S*/g, (txt) => 
    txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase()
  );
}

/**
 * Odstráni HTML tagy z textu
 * @param {string} html - HTML string
 * @returns {string} Čistý text
 */
export function stripHtml(html) {
  if (!html) return '';
  
  return html.replace(/<[^>]*>/g, '');
}

/**
 * Validuje email formát
 * @param {string} email - Email na validáciu
 * @returns {boolean} True ak je validný
 */
export function isValidEmail(email) {
  if (!email) return false;
  
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
}

/**
 * Validuje URL formát
 * @param {string} url - URL na validáciu
 * @returns {boolean} True ak je validná
 */
export function isValidUrl(url) {
  if (!url) return false;
  
  try {
    new URL(url);
    return true;
  } catch {
    return false;
  }
}

/**
 * Pridá "s" pre plurál (anglické plurály)
 * @param {number} count - Počet
 * @param {string} word - Slovo v singulári
 * @returns {string} Slovo v správnom tvare
 */
export function pluralize(count, word) {
  if (count === 1) return word;
  return word + 's';
}

/**
 * Formátuje číslo s tisícovými oddelovačmi
 * @param {number} num - Číslo na formátovanie
 * @returns {string} Formátované číslo
 */
export function formatNumber(num) {
  if (num === null || num === undefined) return '';
  
  return new Intl.NumberFormat('sk-SK').format(num);
}
