import React, { useState, useEffect, useRef } from 'react';
import { useAppContext } from '@/context/AppContext';
import { TranslationProvider } from '@/hooks/useTranslation';
import { ApiService } from '@/services/ApiService';
import { TranslationService } from '@/services/TranslationService';
import { SessionManager } from '@/services/SessionManager';
import { AudioService } from '@/services/AudioService';
import { ConfigScreen } from './ConfigScreen';
import { MainApp } from './MainApp';
import { STORAGE_KEYS } from '@/utils/constants';

export function App() {
  const { 
    state, 
    setSiteConfig, 
    clearSiteConfig, 
    updateStatus, 
    addMessage, 
    clearMessages, 
    updateTranscript, 
    appendTranscript, 
    queueToolCall, 
    dequeueToolCall, 
    setSessionData, 
    setPlayingCustomTts 
  } = useAppContext();
  // Determine initial screen based on stored credentials
  const hasStoredCredentials = state.siteUrl && state.username && localStorage.getItem(STORAGE_KEYS.APP_PASSWORD);
  const [currentScreen, setCurrentScreen] = useState<'config' | 'main'>(hasStoredCredentials ? 'main' : 'config');
  const [isLoading, setIsLoading] = useState(hasStoredCredentials);
  const [translationService] = useState(() => new TranslationService());
  
  // Service references
  const apiServiceRef = useRef<ApiService | null>(null);
  const sessionManagerRef = useRef<SessionManager | null>(null);
  const audioServiceRef = useRef<AudioService | null>(null);
  const audioElementRef = useRef<HTMLAudioElement | null>(null);

  // Initialize app on mount
  useEffect(() => {
    initializeApp();
    
    // Set up audio element ref
    const audioElement = document.querySelector('#remoteAudio') as HTMLAudioElement;
    if (audioElement) {
      audioElementRef.current = audioElement;
    }
  }, []);

  const ensureTranslationsLoaded = async (apiService: ApiService): Promise<void> => {
    if (!translationService.isTranslationsLoaded()) {
      try {
        await translationService.loadTranslations(apiService);
        console.log('[App] Translations loaded successfully');
      } catch (error) {
        console.warn('[App] Failed to load translations, using fallbacks:', error);
        // Continue anyway - components will use their fallback parameters
      }
    } else {
      console.log('[App] Translations already loaded');
    }
  };

  const initializeApp = async () => {
    // Check if we have saved credentials and regenerate bearer token
    if (state.siteUrl && state.username && localStorage.getItem(STORAGE_KEYS.APP_PASSWORD)) {
      try {
        // Generate bearer token from stored credentials
        const storedPassword = localStorage.getItem(STORAGE_KEYS.APP_PASSWORD);
        const bearerToken = ApiService.generateBearerToken(state.username, storedPassword!);
        
        // Test connection with regenerated credentials
        const apiService = new ApiService(state.siteUrl, bearerToken);
        await apiService.testConnection();
        
        // Update state with valid bearer token
        setSiteConfig(state.siteUrl, state.username, bearerToken);
        
        // Load translations separately
        await ensureTranslationsLoaded(apiService);
        
        // Store API service for later use
        apiServiceRef.current = apiService;
        
        // Show main app (services will be initialized on first mic click)
        setCurrentScreen('main');
        setIsLoading(false);
        console.log('[App] Initialized with stored credentials');
      } catch (error) {
        console.warn('[App] Stored credentials invalid or connection failed:', error);
        // Clear invalid credentials and show config screen
        clearSiteConfig();
        setCurrentScreen('config');
        setIsLoading(false);
      }
    } else {
      // No stored credentials or invalid format
      console.log('[App] No valid stored credentials found');
      setCurrentScreen('config');
      setIsLoading(false);
    }
  };


  const handleConfigSuccess = async (apiService: ApiService) => {
    // Always ensure translations are loaded after successful config
    await ensureTranslationsLoaded(apiService);
    
    // Store API service for later use
    apiServiceRef.current = apiService;
    
    // Switch to main app
    setCurrentScreen('main');
    console.log('[App] Config success, switched to main app');
  };

  const handleChangeConfig = () => {
    // Clean up services
    cleanupServices();
    setCurrentScreen('config');
    console.log('[App] Switched to config screen');
  };

  const handleLogout = () => {
    // Clean up services and clear config
    cleanupServices();
    clearSiteConfig();
    setCurrentScreen('config');
    console.log('[App] User logged out');
  };

  const cleanupServices = () => {
    if (sessionManagerRef.current) {
      sessionManagerRef.current.stopSession();
      sessionManagerRef.current = null;
    }
    
    if (audioServiceRef.current && audioElementRef.current) {
      audioServiceRef.current.cleanup(audioElementRef.current);
      audioServiceRef.current = null;
    }
    
    apiServiceRef.current = null;
  };

  const initializeServices = async (): Promise<void> => {
    // Ensure we have a valid API service
    if (!apiServiceRef.current) {
      if (!state.siteUrl || !state.bearerToken) {
        throw new Error('No API service available. Please check your configuration.');
      }
      
      // Test connection to ensure credentials are still valid
      apiServiceRef.current = new ApiService(state.siteUrl, state.bearerToken);
      await apiServiceRef.current.testConnection();
      
      // Ensure translations are loaded with current API service
      await ensureTranslationsLoaded(apiServiceRef.current);
    }
    
    // Initialize audio service if needed
    if (!audioServiceRef.current) {
      audioServiceRef.current = new AudioService(apiServiceRef.current);
    }
    
    // Initialize session manager if needed
    if (!sessionManagerRef.current && audioElementRef.current) {
      // Create proper adapter between React context and SessionManager
      const stateManagerBridge = {
        getState: () => state,
        setState: (newState: any) => {
          // Map partial state updates to individual React context actions
          Object.keys(newState).forEach(key => {
            const value = newState[key];
            switch (key) {
              case 'status':
                updateStatus(value);
                break;
              case 'currentTranscript':
                updateTranscript(value);
                break;
              case 'isPlayingCustomTts':
                setPlayingCustomTts(value);
                break;
              default:
                console.log('[App] Unhandled setState key:', key, value);
            }
          });
        },
        subscribe: (_callback: any) => {
          // React handles subscriptions through re-renders, so this is a no-op
          console.log('[App] SessionManager subscribing to state changes (handled by React)');
          return () => {}; // Return unsubscribe function
        },
        updateStatus: updateStatus,
        setSiteConfig: setSiteConfig,
        clearSiteConfig: clearSiteConfig,
        addMessage: addMessage,
        clearMessages: clearMessages,
        updateTranscript: updateTranscript,
        appendTranscript: appendTranscript,
        queueToolCall: queueToolCall,
        dequeueToolCall: dequeueToolCall,
        setSessionData: setSessionData,
        setPlayingCustomTts: setPlayingCustomTts
      };

      sessionManagerRef.current = new SessionManager(
        stateManagerBridge as any,
        apiServiceRef.current,
        audioElementRef.current
      );
    }
  };

  const handleStartRecording = async () => {
    try {
      setIsLoading(true);
      await initializeServices();
      
      // Unlock mobile audio on first interaction
      if (audioServiceRef.current && audioElementRef.current) {
        audioServiceRef.current.unlockMobileAudio(audioElementRef.current);
      }
      
      if (sessionManagerRef.current) {
        await sessionManagerRef.current.startSession();
      }
    } catch (error) {
      console.error('Failed to start recording session:', error);
      // TODO: Show error to user via context
    } finally {
      setIsLoading(false);
    }
  };

  const handleStopRecording = () => {
    if (sessionManagerRef.current) {
      sessionManagerRef.current.stopSession();
    }
  };

  const handleInterruptTts = () => {
    if (sessionManagerRef.current) {
      sessionManagerRef.current.interruptTts();
    }
  };

  const handlePressAndHoldStart = () => {
    if (sessionManagerRef.current) {
      sessionManagerRef.current.setVadEnabled(false);
    }
  };

  const handlePressAndHoldEnd = () => {
    if (sessionManagerRef.current) {
      sessionManagerRef.current.setVadEnabled(true);
    }
  };

  return (
    <TranslationProvider translationService={translationService}>
      <div id="app">
        {currentScreen === 'config' && (
          <ConfigScreen onConfigSuccess={handleConfigSuccess} />
        )}
        
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