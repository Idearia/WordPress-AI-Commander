// API Configuration
export const API_ENDPOINTS = {
  WP_USER_ME: '/wp-json/wp/v2/users/me',
  REALTIME_SESSION: '/wp-json/ai-commander/v1/realtime/session',
  REALTIME_TOOL: '/wp-json/ai-commander/v1/realtime/tool',
  READ_TEXT: '/wp-json/ai-commander/v1/read-text',
} as const;

export const OPENAI_API = {
  REALTIME_URL: 'https://api.openai.com/v1/realtime',
  DEFAULT_MODEL: 'gpt-4o-realtime-preview-2024-12-17',
} as const;

// Audio Configuration
export const AUDIO_CONFIG = {
  SAMPLE_RATE: 48000,
  CHANNEL_COUNT: 1,
  SAMPLE_SIZE: 16,
  ECHO_CANCELLATION: false,
  NOISE_SUPPRESSION: false,
  AUTO_GAIN_CONTROL: false,
  MAX_BITRATE: 96000,
} as const;

// UI Configuration
export const UI_CONFIG = {
  ERROR_DISPLAY_DURATION: 5000,
  MOBILE_AUDIO_CHECK_INTERVAL: 100,
  SCROLL_BEHAVIOR: 'smooth' as const,
} as const;

// Storage Keys
export const STORAGE_KEYS = {
  SITE_URL: 'inofficina_site_url',
  USERNAME: 'inofficina_username',
  APP_PASSWORD: 'inofficina_app_password',
} as const;

// Status Messages
export const STATUS_MESSAGES = {
  disconnected: 'Premi per iniziare',
  connecting: 'Connessione in corso...',
  recording: 'In ascolto...',
  processing: 'Elaborazione...',
  speaking: 'Risposta in corso...',
  speaking_interruptible: 'Premi per interrompere',
  tool_wait: 'Esecuzione comando...',
  idle: 'In attesa...',
  error: 'Errore',
} as const;

// Error Messages
export const ERROR_MESSAGES = {
  INVALID_URL: 'URL non valido. Inserisci un URL completo (es. https://www.tuaofficina.it)',
  INVALID_CREDENTIALS: 'Credenziali non valide. Verifica nome utente e password.',
  ACCESS_DENIED: 'Accesso negato. Verifica i permessi utente sul sito.',
  CONNECTION_FAILED: 'Impossibile connettersi al sito WordPress',
  SESSION_FAILED: 'Impossibile avviare la sessione',
  TOOL_EXECUTION_FAILED: 'Tool execution failed',
  NETWORK_ERROR: 'Network error',
  DATA_CHANNEL_NOT_OPEN: 'Data channel not open',
  TTS_FAILED: 'Errore nella riproduzione audio personalizzato.',
  COMMUNICATION_ERROR: 'Errore di comunicazione',
  UNKNOWN_ERROR: 'Errore sconosciuto',
} as const;
