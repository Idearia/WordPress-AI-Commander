import { useState, useEffect, useRef } from 'react';
import { useAppContext } from '@/context/AppContext';
import { TranslationProvider } from '@/hooks/useTranslation';
import { useApiService } from '@/hooks/useApiService';
import { useAudioService } from '@/hooks/useAudioService';
import { useSessionManager } from '@/hooks/useSessionManager';
import { ApiService } from '@/services/ApiService';
import { TranslationService } from '@/services/TranslationService';
import { ConfigScreen } from './ConfigScreen';
import { MainApp } from './MainApp';
import { STORAGE_KEYS } from '@/utils/constants';

export function App() {
  const { state, setSiteConfig, clearSiteConfig, setAssistantGreeting } = useAppContext();

  // Audio element reference for WebRTC
  const audioElementRef = useRef<HTMLAudioElement | null>(null);

  // React hooks for service management - automatically handle lifecycle
  const apiService = useApiService(state.siteUrl, state.bearerToken);
  const audioService = useAudioService(apiService);

  // SessionManager needs to be created after audio element is available
  // We'll use a state to track when it's ready
  const [audioElementReady, setAudioElementReady] = useState(false);
  const sessionManager = useSessionManager(
    apiService,
    audioElementReady ? audioElementRef.current : null
  );

  // Check if we have embedded config from WordPress
  const embeddedConfig = window.AI_COMMANDER_CONFIG;

  // Determine initial screen - if we have embedded config, only check for username/password
  const hasEmbeddedConfig = !!embeddedConfig;
  const hasStoredCredentials = hasEmbeddedConfig
    ? !!(state.username && localStorage.getItem(STORAGE_KEYS.APP_PASSWORD))
    : !!(state.siteUrl && state.username && localStorage.getItem(STORAGE_KEYS.APP_PASSWORD));

  const [currentScreen, setCurrentScreen] = useState<'config' | 'main'>(
    hasStoredCredentials ? 'main' : 'config'
  );
  const [isLoading, setIsLoading] = useState<boolean>(hasStoredCredentials);

  // Pre-load variables that might be in the embedded config, that is, the app
  // string translations and the assistant greeting.  We do it here, before the
  // first render, to avoid flickering (aka FOUC).  If these variables are not
  // found in the embedded config (because we are on a dev server), nothing is
  // done here, but later we will load them from the API.
  const [translationService] = useState(() => {
    const service = new TranslationService();
    const cfg = window.AI_COMMANDER_CONFIG;
    if (cfg?.translations) {
      try {
        service.setTranslations(cfg.translations, cfg.locale);
        console.log('[App] Preloaded translations from embedded config');
      } catch (err) {
        console.warn('[App] Failed to preload embedded translations:', err);
      }
    }
    return service;
  });

  const isInitializedRef = useRef<boolean>(false);

  // Initialize app on mount
  useEffect(() => {
    // Prevent duplicate initialization in React StrictMode
    if (isInitializedRef.current) {
      return;
    }
    isInitializedRef.current = true;

    initializeApp();

    // Set up audio element ref
    const audioElement = document.querySelector('#remoteAudio') as HTMLAudioElement;
    if (audioElement) {
      audioElementRef.current = audioElement;
      setAudioElementReady(true);
    }
  }, []);

  const ensureTranslationsLoaded = async (apiService: ApiService): Promise<void> => {
    if (!translationService.isTranslationsLoaded()) {
      // First try to use embedded translations
      if (embeddedConfig?.translations) {
        try {
          translationService.setTranslations(embeddedConfig.translations, embeddedConfig.locale);
          console.log('[App] Using embedded translations');
          return;
        } catch (error) {
          console.warn('[App] Failed to use embedded translations:', error);
        }
      }

      // Fallback to loading from API
      try {
        await translationService.loadTranslations(apiService);
        console.log('[App] Translations loaded from API');
      } catch (error) {
        console.warn('[App] Failed to load translations, using fallbacks:', error);
        // Continue anyway - components will use their fallback parameters
      }
    } else {
      console.log('[App] Translations already loaded');
    }
  };

  const ensureAssistantGreetingLoaded = async (apiService: ApiService): Promise<void> => {
    // First try to use embedded assistant greeting
    if (embeddedConfig?.assistantGreeting) {
      try {
        setAssistantGreeting(embeddedConfig.assistantGreeting);
        console.log('[App] Using embedded assistant greeting');
        return;
      } catch (error) {
        console.warn('[App] Failed to use embedded assistant greeting:', error);
      }
    }

    // Fallback to loading from API
      try {
        const assistantGreeting = await apiService.getAssistantGreeting();
        setAssistantGreeting(assistantGreeting);
        console.log('[App] Assistant greeting loaded from API');
      } catch (error) {
      console.warn('[App] Failed to load assistant greeting, using fallbacks:', error);
    }
  };

  const initializeApp = async () => {
    try {
      // If we have embedded config, use that as the base URL
      if (embeddedConfig) {
        console.log('[App] Using embedded WordPress config');

        // Check for stored credentials (username + password only)
        if (state.username && localStorage.getItem(STORAGE_KEYS.APP_PASSWORD)) {
          // Generate bearer token from stored credentials
          const storedPassword = localStorage.getItem(STORAGE_KEYS.APP_PASSWORD);
          const bearerToken = ApiService.generateBearerToken(state.username, storedPassword!);

          // Update state with embedded base URL and bearer token
          setSiteConfig(embeddedConfig.baseUrl, state.username, bearerToken);

          // Create temporary API service for translation loading
          const tempApiService = new ApiService(embeddedConfig.baseUrl, bearerToken);

          // Load translations and assistant greeting (will use embedded ones first)
          await ensureTranslationsLoaded(tempApiService);
          await ensureAssistantGreetingLoaded(tempApiService);

          // Show main app
          setCurrentScreen('main');
          setIsLoading(false);
          console.log('[App] Initialized with embedded config and stored credentials');
        } else {
          // Load embedded translations only if not already loaded (avoids duplicate log)
          if (embeddedConfig.translations && !translationService.isTranslationsLoaded()) {
            console.log('[App] Using embedded translations');
            translationService.setTranslations(embeddedConfig.translations, embeddedConfig.locale);
          }

          // Show config screen (only ask for username/password)
          setCurrentScreen('config');
          setIsLoading(false);
          console.log('[App] Embedded config available, showing config for credentials');
        }
      } else {
        // Check for Vite dev environment variable
        const viteBaseUrl = import.meta.env.VITE_WP_BASE_URL;

        if (viteBaseUrl) {
          console.log('[App] Using Vite development base URL:', viteBaseUrl);

          // Check for stored credentials (username + password only)
          if (state.username && localStorage.getItem(STORAGE_KEYS.APP_PASSWORD)) {
            // Generate bearer token from stored credentials
            const storedPassword = localStorage.getItem(STORAGE_KEYS.APP_PASSWORD);
            const bearerToken = ApiService.generateBearerToken(state.username, storedPassword!);

            // Update state with Vite base URL and bearer token
            setSiteConfig(viteBaseUrl, state.username, bearerToken);

            // Create temporary API service for translation loading
            const tempApiService = new ApiService(viteBaseUrl, bearerToken);

            // Load translations and assistant greeting from API
            await ensureTranslationsLoaded(tempApiService);
            await ensureAssistantGreetingLoaded(tempApiService);

            // Show main app
            setCurrentScreen('main');
            setIsLoading(false);
            console.log('[App] Initialized with Vite base URL and stored credentials');
          } else {
            // Show config screen (only ask for username/password)
            setCurrentScreen('config');
            setIsLoading(false);
            console.log('[App] Vite base URL available, showing config for credentials');
          }
        }
        // Fallback to legacy behavior - check for stored site URL + credentials
        else if (
          state.siteUrl &&
          state.username &&
          localStorage.getItem(STORAGE_KEYS.APP_PASSWORD)
        ) {
          // Generate bearer token from stored credentials
          const storedPassword = localStorage.getItem(STORAGE_KEYS.APP_PASSWORD);
          const bearerToken = ApiService.generateBearerToken(state.username, storedPassword!);

          // Update state with bearer token
          setSiteConfig(state.siteUrl, state.username, bearerToken);

          // Create temporary API service for translation loading
          const tempApiService = new ApiService(state.siteUrl, bearerToken);

          // Load translations and assistant greeting from API
          await ensureTranslationsLoaded(tempApiService);
          await ensureAssistantGreetingLoaded(tempApiService);

          // Show main app
          setCurrentScreen('main');
          setIsLoading(false);
          console.log('[App] Initialized with legacy stored credentials');
        } else {
          // No stored credentials, show config screen
          setCurrentScreen('config');
          setIsLoading(false);
          console.log('[App] No embedded config or stored credentials found');
        }
      }
    } catch (error) {
      console.error('[App] Failed to initialize app:', error);
      // Clear any invalid credentials and show config screen
      clearSiteConfig();
      setCurrentScreen('config');
      setIsLoading(false);
    }
  };

  const handleConfigSuccess = async (apiService: ApiService) => {
    // Always ensure translations are loaded after successful config
    await ensureTranslationsLoaded(apiService);

    // Switch to main app - hooks will automatically create services
    setCurrentScreen('main');
    console.log('[App] Config success, switched to main app');
  };

  const handleChangeConfig = () => {
    // Stop any active session before switching screens
    if (sessionManager) {
      sessionManager.stopSession();
    }
    setCurrentScreen('config');
    console.log('[App] Switched to config screen');
  };

  const handleLogout = () => {
    // Stop any active session and clear config
    if (sessionManager) {
      sessionManager.stopSession();
    }
    clearSiteConfig();
    setCurrentScreen('config');
    console.log('[App] User logged out');
  };

  /**
   * Handles starting a recording session.
   * Uses React hooks to automatically manage service lifecycle.
   */
  const handleStartRecording = async () => {
    try {
      setIsLoading(true);

      // Check if services are available (hooks auto-create them)
      if (!sessionManager) {
        throw new Error('Session manager not available. Please check your configuration.');
      }

      // Unlock mobile audio on first interaction
      if (audioService && audioElementRef.current) {
        audioService.unlockMobileAudio(audioElementRef.current);
      }

      // Start the session
      await sessionManager.startSession();
    } catch (error) {
      console.error('Failed to start recording session:', error);
      // TODO: Show error to user via context
    } finally {
      setIsLoading(false);
    }
  };

  const handleStopRecording = () => {
    if (sessionManager) {
      sessionManager.stopSession();
    }
  };

  const handleInterruptTts = () => {
    if (sessionManager) {
      sessionManager.interruptTts();
    }
  };

  const handlePressAndHoldStart = () => {
    if (sessionManager) {
      sessionManager.setVadEnabled(false);
    }
  };

  const handlePressAndHoldEnd = () => {
    if (sessionManager) {
      sessionManager.setVadEnabled(true);
    }
  };

  return (
    <TranslationProvider translationService={translationService}>
      <div id="app">
        {currentScreen === 'config' && <ConfigScreen onConfigSuccess={handleConfigSuccess} />}

        {currentScreen === 'main' && (
          <MainApp
            onStartRecording={handleStartRecording}
            onStopRecording={handleStopRecording}
            onInterruptTts={handleInterruptTts}
            onPressAndHoldStart={handlePressAndHoldStart}
            onPressAndHoldEnd={handlePressAndHoldEnd}
            onChangeConfig={handleChangeConfig}
            onLogout={handleLogout}
          />
        )}

        {/* Loading Overlay */}
        {isLoading && (
          <div className="loading-overlay" style={{ display: 'flex' }}>
            <div className="loading-spinner"></div>
          </div>
        )}

        {/* Audio element for WebRTC */}
        <audio id="remoteAudio" style={{ display: 'none' }} autoPlay playsInline />
      </div>
    </TranslationProvider>
  );
}
