/**
 * Validation utility functions - centralizované validácie
 * Pre formuláre a dátovú validáciu
 */

import { VALIDATION_MESSAGES, FILE_UPLOAD } from './constants.js';
import { isValidEmail } from './textUtils.js';

/**
 * Validácia povinného poľa
 * @param {any} value - Hodnota na validáciu
 * @param {string} fieldName - Názov poľa (pre error message)
 * @returns {string|null} Error message alebo null
 */
export function validateRequired(value) {
  if (value === null || value === undefined || value === '') {
    return VALIDATION_MESSAGES.REQUIRED;
  }
  if (typeof value === 'string' && value.trim() === '') {
    return VALIDATION_MESSAGES.REQUIRED;
  }
  return null;
}

/**
 * Validácia emailu
 * @param {string} email - Email na validáciu
 * @returns {string|null} Error message alebo null
 */
export function validateEmail(email) {
  if (!email) return null;
  
  if (!isValidEmail(email)) {
    return VALIDATION_MESSAGES.EMAIL;
  }
  
  return null;
}

/**
 * Validácia dĺžky stringu
 * @param {string} value - Hodnota na validáciu
 * @param {number} min - Minimálna dĺžka
 * @param {number} max - Maximálna dĺžka
 * @returns {string|null} Error message alebo null
 */
export function validateLength(value, min = 0, max = Infinity) {
  if (!value) return null;
  
  const length = value.length;
  
  if (length < min) {
    return VALIDATION_MESSAGES.MIN_LENGTH(min);
  }
  
  if (length > max) {
    return VALIDATION_MESSAGES.MAX_LENGTH(max);
  }
  
  return null;
}

/**
 * Validácia dátumu
 * @param {string|Date|null} value - Dátum na validáciu
 * @param {Object} options - Možnosti validácie
 * @returns {string|null} Error message alebo null
 */
export function validateDate(value, options = {}) {
  const {
    required = false,
    minDate = null,
    maxDate = null,
    allowFuture = true,
    allowPast = true
  } = options;
  
  if (!value) {
    return required ? VALIDATION_MESSAGES.REQUIRED : null;
  }
  
  const date = new Date(value);
  if (isNaN(date.getTime())) {
    return 'Zadajte platný dátum';
  }
  
  const now = new Date();
  
  if (!allowFuture && date > now) {
    return 'Dátum nesmie byť v budúcnosti';
  }
  
  if (!allowPast && date < now) {
    return 'Dátum nesmie byť v minulosti';
  }
  
  if (minDate && date < new Date(minDate)) {
    return `Dátum musí byť po ${new Date(minDate).toLocaleDateString('sk-SK')}`;
  }
  
  if (maxDate && date > new Date(maxDate)) {
    return `Dátum musí byť pred ${new Date(maxDate).toLocaleDateString('sk-SK')}`;
  }
  
  return null;
}

/**
 * Validácia URL
 * @param {string} url - URL na validáciu
 * @param {boolean} required - Je povinné
 * @returns {string|null} Error message alebo null
 */
export function validateUrl(url, required = false) {
  if (!url) {
    return required ? VALIDATION_MESSAGES.REQUIRED : null;
  }
  
  try {
    new URL(url);
    return null;
  } catch {
    return 'Zadajte platnú URL adresu';
  }
}

/**
 * Validácia súboru pre upload
 * @param {File} file - Súbor na validáciu
 * @param {Object} options - Možnosti validácie
 * @returns {string|null} Error message alebo null
 */
export function validateFile(file, options = {}) {
  if (!file) {
    return options.required ? VALIDATION_MESSAGES.REQUIRED : null;
  }
  
  const {
    maxSize = FILE_UPLOAD.MAX_SIZE,
    allowedTypes = FILE_UPLOAD.ALLOWED_TYPES,
    allowedExtensions = FILE_UPLOAD.ALLOWED_EXTENSIONS
  } = options;
  
  // Validácia veľkosti
  if (file.size > maxSize) {
    return VALIDATION_MESSAGES.FILE_SIZE;
  }
  
  // Validácia MIME type
  if (!allowedTypes.includes(file.type)) {
    return VALIDATION_MESSAGES.FILE_TYPE;
  }
  
  // Validácia prípony
  const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
  if (!allowedExtensions.includes(fileExtension)) {
    return VALIDATION_MESSAGES.FILE_TYPE;
  }
  
  return null;
}

/**
 * Validácia celého objektu (formulár)
 * @param {Object} data - Dátové objekt na validáciu
 * @param {Object} rules - Validáčné pravidlá
 * @returns {Object} Objekt s errormi (prázdny ak žiadne)
 */
export function validateForm(data, rules) {
  const errors = {};
  
  for (const [field, fieldRules] of Object.entries(rules)) {
    const value = data[field];
    
    for (const rule of fieldRules) {
      let error = null;
      
      switch (rule.type) {
        case 'required':
          error = validateRequired(value);
          break;
          
        case 'email':
          error = validateEmail(value);
          break;
          
        case 'length':
          error = validateLength(value, rule.min, rule.max);
          break;
          
        case 'date':
          error = validateDate(value, rule.options);
          break;
          
        case 'url':
          error = validateUrl(value, rule.required);
          break;
          
        case 'file':
          error = validateFile(value, rule.options);
          break;
          
        case 'custom':
          error = rule.validator(value);
          break;
          
        default:
          console.warn(`Unknown validation rule: ${rule.type}`);
      }
      
      if (error) {
        errors[field] = error;
        break; // Stop na prvom error pre dané pole
      }
    }
  }
  
  return errors;
}

/**
 * Skontroluje či formulár má validáčné errory
 * @param {Object} errors - Error objekt
 * @returns {boolean} True ak má errory
 */
export function hasErrors(errors) {
  return Object.keys(errors).length > 0;
}

/**
 * Zí prvý error z error objektu
 * @param {Object} errors - Error objekt
 * @returns {string|null} Prvý error alebo null
 */
export function getFirstError(errors) {
  const errorKeys = Object.keys(errors);
  return errorKeys.length > 0 ? errors[errorKeys[0]] : null;
}

/**
 * Vytvorí validáčné pravidlá pre bežné polia
 */
export const commonRules = {
  required: { type: 'required' },
  email: { type: 'email' },
  title: [
    { type: 'required' },
    { type: 'length', min: 3, max: 255 }
  ],
  description: [
    { type: 'required' },
    { type: 'length', min: 10, max: 2000 }
  ],
  bio: [
    { type: 'length', max: 160 }
  ],
  location: [
    { type: 'length', max: 60 }
  ]
};
