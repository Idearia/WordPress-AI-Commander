// API Configuration
export const API_ENDPOINTS = {
  WP_USER_ME: '/wp-json/wp/v2/users/me',
  REALTIME_SESSION: '/wp-json/ai-commander/v1/realtime/session',
  REALTIME_TOOL: '/wp-json/ai-commander/v1/realtime/tool',
  READ_TEXT: '/wp-json/ai-commander/v1/read-text',
} as const;

export const OPENAI_API = {
  REALTIME_URL: 'https://api.openai.com/v1/realtime',
  DEFAULT_MODEL: 'gpt-4o-realtime-preview-2025-06-03',
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

import type { TranslationService } from '@/services/TranslationService';

/**
 * UI Messages class - provides translated messages with clean syntax
 * 
 * Usage:
 * 1. Initialize once: UiMessages.init(translationService)
 * 2. Use anywhere: UiMessages.ERROR_MESSAGES.INVALID_URL
 */
export class UiMessages {
  private static translationService: TranslationService | null = null;

  /**
   * Initialize with translation service
   */
  static init(translationService: TranslationService) {
    this.translationService = translationService;
  }

  /**
   * Get error messages (translated if available, fallback otherwise)
   */
  static get ERROR_MESSAGES() {
    const t = this.translationService?.t.bind(this.translationService) || 
              ((_key: string, fallback: string) => fallback);
    
    return {
      INVALID_URL: t('mobile.error.invalid_url', 'URL non valido. Inserisci un URL completo (es. https://www.tuosito.com)'),
      INVALID_CREDENTIALS: t('mobile.error.invalid_credentials', 'Credenziali non valide. Verifica nome utente e password.'),
      ACCESS_DENIED: t('mobile.error.access_denied', 'Accesso negato. Verifica i permessi utente sul sito.'),
      CONNECTION_FAILED: t('mobile.error.connection_failed', 'Impossibile connettersi al sito WordPress'),
      SESSION_FAILED: t('mobile.error.session_failed', 'Impossibile avviare la sessione'),
      TOOL_EXECUTION_FAILED: t('mobile.error.tool_execution_failed', 'Esecuzione tool fallita'),
      NETWORK_ERROR: t('mobile.error.network_error', 'Errore di rete'),
      DATA_CHANNEL_NOT_OPEN: t('mobile.error.data_channel_not_open', 'Canale dati non aperto'),
      TTS_FAILED: t('mobile.error.tts_failed', 'Errore nella riproduzione audio personalizzata.'),
      COMMUNICATION_ERROR: t('mobile.error.communication_error', 'Errore di comunicazione'),
      UNKNOWN_ERROR: t('mobile.error.unknown_error', 'Errore sconosciuto'),
    } as const;
  }

  /**
   * Get status messages (translated if available, fallback otherwise)
   */
  static get STATUS_MESSAGES() {
    const t = this.translationService?.t.bind(this.translationService) || 
              ((_key: string, fallback: string) => fallback);
    
    return {
      disconnected: t('mobile.status.disconnected', 'Premi per iniziare'),
      connecting: t('mobile.status.connecting', 'Connessione in corso...'),
      recording: t('mobile.status.recording', 'In ascolto...'),
      processing: t('mobile.status.processing', 'Elaborazione...'),
      speaking: t('mobile.status.speaking', 'Risposta in corso...'),
      speaking_interruptible: t('mobile.status.speaking_interruptible', 'Premi per interrompere'),
      tool_wait: t('mobile.status.tool_wait', 'Esecuzione comando...'),
      idle: t('mobile.status.idle', 'In attesa...'),
      error: t('mobile.status.error', 'Errore'),
    } as const;
  }

  /**
   * Get UI labels and text (translated if available, fallback otherwise)
   */
  static get UI_TEXT() {
    const t = this.translationService?.t.bind(this.translationService) || 
              ((_key: string, fallback: string) => fallback);
    
    return {
      // Form labels
      SITE_URL_LABEL: t('mobile.ui.site_url_label', 'URL del sito'),
      USERNAME_LABEL: t('mobile.ui.username_label', 'Nome utente'),
      APP_PASSWORD_LABEL: t('mobile.ui.app_password_label', 'Password dell\'app'),
      CONNECT_BTN: t('mobile.ui.connect_btn', 'Connetti'),
      CONNECTING_BTN: t('mobile.ui.connecting_btn', 'Connessione...'),
      
      // UI text
      ASSISTANT_NAME: t('mobile.ui.assistant_name', 'Assistente INofficina.it'),
      CHANGE_CONFIG: t('mobile.ui.change_config', 'Cambia configurazione'),
      DISCONNECT: t('mobile.ui.disconnect', 'Disconnetti'),
      GREETING: t('mobile.ui.greeting', 'Ciao! 👋'),
      
      // Confirmations
      LOGOUT_CONFIRM: t('mobile.confirm.logout', 'Vuoi disconnetterti e cancellare le credenziali salvate?'),
      
      // Dynamic messages
      SESSION_EXPIRED: t('mobile.dynamic.session_expired', 'Sessione scaduta. Accedi nuovamente.'),
      CREDENTIALS_NOT_FOUND: t('mobile.dynamic.credentials_not_found', 'Credenziali non trovate. Accedi nuovamente.'),
      CONNECTION_TEST_FAILED: t('mobile.dynamic.connection_test_failed', 'Impossibile connettersi. Verifica i dati e riprova.'),
    } as const;
  }
}

// Legacy constants for backward compatibility (deprecated - use UiMessages instead)
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
