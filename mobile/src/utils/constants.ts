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
  SITE_URL: 'aicommander_site_url',
  USERNAME: 'aicommander_username',
  APP_PASSWORD: 'aicommander_app_password',
} as const;

// Translations interface
interface Translations {
  status: Record<string, string>;
  errors: Record<string, string>;
  ui: Record<string, string>;
}

// Default translations (English)
const defaultTranslations: Translations = {
  status: {
    disconnected: 'Press to start',
    connecting: 'Connecting...',
    recording: 'Listening...',
    processing: 'Processing...',
    speaking: 'Responding...',
    speaking_interruptible: 'Press to interrupt',
    tool_wait: 'Executing command...',
    idle: 'Waiting...',
    error: 'Error',
  },
  errors: {
    invalid_url: 'Invalid URL. Enter a complete URL (e.g. https://www.yourshop.com)',
    invalid_credentials: 'Invalid credentials. Check username and password.',
    access_denied: 'Access denied. Check user permissions on the site.',
    connection_failed: 'Unable to connect to WordPress site',
    session_failed: 'Unable to start session',
    tool_execution_failed: 'Tool execution failed',
    network_error: 'Network error',
    data_channel_not_open: 'Data channel not open',
    tts_failed: 'Error in custom audio playback.',
    communication_error: 'Communication error',
    unknown_error: 'Unknown error',
    url_must_start_with_http: 'URL must start with http:// or https://',
    connection_generic: 'Unable to connect. Check your data and try again.',
  },
  ui: {
    // Page titles and headers
    page_title: 'INofficina.it Voice Assistant',
    config_title: 'INofficina Voice Assistant',
    config_subtitle: 'Enter your WordPress site URL and credentials',

    // Configuration form
    note_label: 'Note:',
    note_text:
      'For the password, use an "Application Password" generated from your WordPress profile, not your regular password.',
    how_to_generate_link: 'How to generate an app password →',

    // Form labels and placeholders
    site_url_label: 'Site URL',
    site_url_placeholder: 'https://www.yourshop.com',
    site_url_hint: 'The complete URL of your WordPress INofficina site',
    username_label: 'Username',
    username_placeholder: 'john.doe',
    username_hint: 'Your WordPress username',
    app_password_label: 'App password',
    app_password_hint:
      'The application password generated in WordPress (not your regular password)',

    // Buttons
    connect_button: 'Connect',
    connecting_button: 'Connecting...',

    // Main interface
    office_name: 'INofficina.it Assistant',
    change_config: 'Change configuration',
    disconnect: 'Disconnect',

    // Chat interface
    greeting_title: 'Hello! 👋',
    greeting_text:
      "I'm the INofficina.it voice assistant. I can help you manage your workshop appointments.",

    // Suggestions
    suggestion_1: '💬 "Is license plate XX333TT our customer?"',
    suggestion_2: '📅 "Schedule maintenance for tomorrow"',
    suggestion_3: '🔍 "Show today\'s appointments"',

    // Confirmation dialogs
    disconnect_confirm: 'Do you want to disconnect and delete saved credentials?',
  },
};

// Current translations (mutable)
let currentTranslations: Translations = { ...defaultTranslations };

// Function to update translations
export function setTranslations(translations: Translations): void {
  currentTranslations = translations;
}

// Export translation getters
export const STATUS_MESSAGES = new Proxy({} as Record<string, string>, {
  get(_, prop: string) {
    return currentTranslations.status[prop] || defaultTranslations.status[prop] || prop;
  },
});

export const ERROR_MESSAGES = new Proxy({} as Record<string, string>, {
  get(_, prop: string) {
    const key = prop.toLowerCase();
    return currentTranslations.errors[key] || defaultTranslations.errors[key] || prop;
  },
});

export const UI_TEXT = new Proxy({} as Record<string, string>, {
  get(_, prop: string) {
    const key = prop.toLowerCase();
    return currentTranslations.ui[key] || defaultTranslations.ui[key] || prop;
  },
});
