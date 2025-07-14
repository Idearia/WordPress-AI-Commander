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
  SITE_URL: 'backend_site_url',
  USERNAME: 'backend_username',
  APP_PASSWORD: 'backend_app_password',
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
    const t =
      this.translationService?.t.bind(this.translationService) ||
      ((_key: string, fallback: string) => fallback);

    return {
      INVALID_URL: t(
        'mobile.error.invalid_url',
        'Invalid URL. Enter a complete URL (e.g. https://www.yoursite.com)'
      ),
      INVALID_CREDENTIALS: t(
        'mobile.error.invalid_credentials',
        'Invalid credentials. Check username and password.'
      ),
      ACCESS_DENIED: t(
        'mobile.error.access_denied',
        'Access denied. Check user permissions on the site.'
      ),
      CONNECTION_FAILED: t('mobile.error.connection_failed', 'Unable to connect to WordPress site'),
      SESSION_FAILED: t('mobile.error.session_failed', 'Unable to start session'),
      TOOL_EXECUTION_FAILED: t('mobile.error.tool_execution_failed', 'Tool execution failed'),
      NETWORK_ERROR: t('mobile.error.network_error', 'Network error'),
      DATA_CHANNEL_NOT_OPEN: t('mobile.error.data_channel_not_open', 'Data channel not open'),
      TTS_FAILED: t('mobile.error.tts_failed', 'Error in custom audio playback.'),
      COMMUNICATION_ERROR: t('mobile.error.communication_error', 'Communication error'),
      UNKNOWN_ERROR: t('mobile.error.unknown_error', 'Unknown error'),
    } as const;
  }

  /**
   * Get status messages (translated if available, fallback otherwise)
   */
  static get STATUS_MESSAGES() {
    const t =
      this.translationService?.t.bind(this.translationService) ||
      ((_key: string, fallback: string) => fallback);

    return {
      disconnected: t('mobile.status.disconnected', 'Press to start'),
      connecting: t('mobile.status.connecting', 'Connecting...'),
      recording: t('mobile.status.recording', 'Listening...'),
      processing: t('mobile.status.processing', 'Processing...'),
      speaking: t('mobile.status.speaking', 'Response in progress...'),
      speaking_interruptible: t('mobile.status.speaking_interruptible', 'Press to interrupt'),
      tool_wait: t('mobile.status.tool_wait', 'Executing command...'),
      error: t('mobile.status.error', 'Error'),
    } as const;
  }

  /**
   * Get UI labels and text (translated if available, fallback otherwise)
   */
  static get UI_TEXT() {
    const t =
      this.translationService?.t.bind(this.translationService) ||
      ((_key: string, fallback: string) => fallback);

    return {
      // Form labels
      SITE_URL_LABEL: t('mobile.ui.site_url_label', 'Site URL'),
      USERNAME_LABEL: t('mobile.ui.username_label', 'Username'),
      APP_PASSWORD_LABEL: t('mobile.ui.app_password_label', 'App password'),
      CONNECT_BTN: t('mobile.ui.connect_btn', 'Connect'),
      CONNECTING_BTN: t('mobile.ui.connecting_btn', 'Connecting...'),

      // UI text
      ASSISTANT_NAME: t('mobile.ui.assistant_name', 'AI Commander Assistant'),
      CHANGE_CONFIG: t('mobile.ui.change_config', 'Change configuration'),
      DISCONNECT: t('mobile.ui.disconnect', 'Disconnect'),
      GREETING: t('mobile.ui.greeting', 'Hello! 👋'),

      // Confirmations
      LOGOUT_CONFIRM: t(
        'mobile.confirm.logout',
        'Do you want to disconnect and delete saved credentials?'
      ),

      // Dynamic messages
      SESSION_EXPIRED: t('mobile.dynamic.session_expired', 'Session expired. Please log in again.'),
      CREDENTIALS_NOT_FOUND: t(
        'mobile.dynamic.credentials_not_found',
        'Credentials not found. Please log in again.'
      ),
      CONNECTION_TEST_FAILED: t(
        'mobile.dynamic.connection_test_failed',
        'Unable to connect. Check your data and try again.'
      ),
    } as const;
  }
}
