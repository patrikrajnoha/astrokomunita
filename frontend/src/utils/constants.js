/**
 * Application constants - centralizované konštanty
 * Statusy, typy, farby, atď.
 */

// Event candidate statusy
export const CANDIDATE_STATUS = {
  PENDING: 'pending',
  APPROVED: 'approved', 
  REJECTED: 'rejected',
  PUBLISHED: 'published'
};

// Event typy
export const EVENT_TYPES = {
  METEOR_SHOWER: 'meteor_shower',
  ECLIPSE: 'eclipse',
  COMET: 'comet',
  PLANETARY: 'planetary',
  AURORA: 'aurora',
  OTHER: 'other'
};

// Blog post statusy
export const BLOG_STATUS = {
  DRAFT: 'draft',
  SCHEDULED: 'scheduled', 
  PUBLISHED: 'published'
};

// Report typy
export const REPORT_TYPES = {
  SPAM: 'spam',
  INAPPROPRIATE: 'inappropriate',
  OFF_TOPIC: 'off_topic',
  HARASSMENT: 'harassment',
  COPYRIGHT: 'copyright',
  OTHER: 'other'
};

// Report statusy
export const REPORT_STATUS = {
  PENDING: 'pending',
  REVIEWING: 'reviewing',
  RESOLVED: 'resolved',
  DISMISSED: 'dismissed'
};

// User role
export const USER_ROLES = {
  USER: 'user',
  ADMIN: 'admin'
};

// API pagination defaults
export const PAGINATION = {
  DEFAULT_PAGE: 1,
  DEFAULT_PER_PAGE: 20,
  MAX_PER_PAGE: 100
};

// Admin tab konštanty
export const ADMIN_TABS = {
  DASHBOARD: 'dashboard',
  CANDIDATES: 'candidates', 
  EVENTS: 'events',
  BLOG: 'blog',
  REPORTS: 'reports',
  SETTINGS: 'settings'
};

// Event candidates tabs
export const CANDIDATES_TABS = {
  CRAWLED: 'crawled',
  MANUAL: 'manual',
  REVIEWED: 'reviewed',
  PUBLISHED: 'published'
};

// Farby pre statusy
export const STATUS_COLORS = {
  // Candidate statusy
  [CANDIDATE_STATUS.PENDING]: 'orange',
  [CANDIDATE_STATUS.APPROVED]: 'blue', 
  [CANDIDATE_STATUS.REJECTED]: 'red',
  [CANDIDATE_STATUS.PUBLISHED]: 'green',
  
  // Blog statusy
  [BLOG_STATUS.DRAFT]: 'gray',
  [BLOG_STATUS.SCHEDULED]: 'blue',
  [BLOG_STATUS.PUBLISHED]: 'green',
  
  // Report statusy
  [REPORT_STATUS.PENDING]: 'orange',
  [REPORT_STATUS.REVIEWING]: 'blue',
  [REPORT_STATUS.RESOLVED]: 'green',
  [REPORT_STATUS.DISMISSED]: 'gray'
};

// Labely pre statusy
export const STATUS_LABELS = {
  // Candidate statusy
  [CANDIDATE_STATUS.PENDING]: 'Čaká na schválenie',
  [CANDIDATE_STATUS.APPROVED]: 'Schválené',
  [CANDIDATE_STATUS.REJECTED]: 'Zamietnuté', 
  [CANDIDATE_STATUS.PUBLISHED]: 'Publikované',
  
  // Blog statusy
  [BLOG_STATUS.DRAFT]: 'Koncept',
  [BLOG_STATUS.SCHEDULED]: 'Naplánované',
  [BLOG_STATUS.PUBLISHED]: 'Publikované',
  
  // Report statusy
  [REPORT_STATUS.PENDING]: 'Čaká na vyriešenie',
  [REPORT_STATUS.REVIEWING]: 'Prebieha kontrola',
  [REPORT_STATUS.RESOLVED]: 'Vyriešené',
  [REPORT_STATUS.DISMISSED]: 'Zamietnuté'
};

// Labely pre event typy
export const EVENT_TYPE_LABELS = {
  [EVENT_TYPES.METEOR_SHOWER]: 'Meteorický dážď',
  [EVENT_TYPES.ECLIPSE]: 'Zatmenie',
  [EVENT_TYPES.COMET]: 'Kométa',
  [EVENT_TYPES.PLANETARY]: 'Planetárny úkaz',
  [EVENT_TYPES.AURORA]: 'Polárna žiara',
  [EVENT_TYPES.OTHER]: 'Iné'
};

// Labely pre report typy
export const REPORT_TYPE_LABELS = {
  [REPORT_TYPES.SPAM]: 'Spam',
  [REPORT_TYPES.INAPPROPRIATE]: 'Nevhodný obsah',
  [REPORT_TYPES.OFF_TOPIC]: 'Mimo témy',
  [REPORT_TYPES.HARASSMENT]: 'Obťažovanie',
  [REPORT_TYPES.COPYRIGHT]: 'Porušenie autorských práv',
  [REPORT_TYPES.OTHER]: 'Iné'
};

// Toast typy
export const TOAST_TYPES = {
  SUCCESS: 'success',
  ERROR: 'error',
  WARNING: 'warning',
  INFO: 'info'
};

// Loading state typy
export const LOADING_STATES = {
  IDLE: 'idle',
  LOADING: 'loading',
  SUCCESS: 'success',
  ERROR: 'error'
};

// File upload konštanty
export const FILE_UPLOAD = {
  MAX_SIZE: 5 * 1024 * 1024, // 5MB
  ALLOWED_TYPES: ['image/jpeg', 'image/png', 'image/webp'],
  ALLOWED_EXTENSIONS: ['.jpg', '.jpeg', '.png', '.webp']
};

// Form validation messages
export const VALIDATION_MESSAGES = {
  REQUIRED: 'Toto pole je povinné',
  EMAIL: 'Zadajte platný email',
  MIN_LENGTH: (min) => `Minimálna dĺžka je ${min} znakov`,
  MAX_LENGTH: (max) => `Maximálna dĺžka je ${max} znakov`,
  FILE_SIZE: `Maximálna veľkosť súboru je 5MB`,
  FILE_TYPE: 'Povolené formáty: JPG, PNG, WebP'
};

// API endpoints
export const API_ENDPOINTS = {
  // Admin endpoints
  ADMIN: {
    DASHBOARD: '/api/admin/dashboard',
    CANDIDATES: '/api/admin/candidates',
    EVENTS: '/api/admin/events',
    BLOG: '/api/admin/blog',
    REPORTS: '/api/admin/reports',
    USERS: '/api/admin/users',
    ASTROBOT: '/api/admin/astrobot'
  },
  // Public endpoints
  PUBLIC: {
    EVENTS: '/api/events',
    POSTS: '/api/posts',
    PROFILE: '/api/profile'
  }
};
