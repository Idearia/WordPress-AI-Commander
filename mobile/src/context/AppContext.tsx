import React, {
  createContext,
  useContext,
  useReducer,
  ReactNode,
  useCallback,
  useMemo,
} from 'react';
import { AppState, Message } from '@/types';
import { STORAGE_KEYS } from '@/utils/constants';

// Action types
type AppAction =
  | { type: 'SET_STATE'; payload: Partial<AppState> }
  | { type: 'UPDATE_STATUS'; payload: AppState['status'] }
  | { type: 'SET_SITE_CONFIG'; payload: { siteUrl: string; username: string; bearerToken: string } }
  | { type: 'CLEAR_SITE_CONFIG' }
  | { type: 'ADD_MESSAGE'; payload: Message }
  | { type: 'CLEAR_MESSAGES' }
  | { type: 'UPDATE_TRANSCRIPT'; payload: string }
  | { type: 'APPEND_TRANSCRIPT'; payload: string }
  | { type: 'SET_SESSION_DATA'; payload: { sessionToken: string; modalities: string[] } }
  | { type: 'SET_PLAYING_CUSTOM_TTS'; payload: boolean };

// Initial state
const initialState: AppState = {
  status: 'disconnected',
  siteUrl: localStorage.getItem(STORAGE_KEYS.SITE_URL) || '',
  username: localStorage.getItem(STORAGE_KEYS.USERNAME) || '',
  bearerToken: '',
  messages: [],
  currentTranscript: '',
  peerConnection: null,
  dataChannel: null,
  localStream: null,
  sessionToken: '',
  modalities: ['text', 'audio'],
  sessionModalities: [],
  isCustomTtsEnabled: false,
  isPlayingCustomTts: false,
};

// Reducer function
function appReducer(state: AppState, action: AppAction): AppState {
  switch (action.type) {
    case 'SET_STATE':
      return { ...state, ...action.payload };

    case 'UPDATE_STATUS':
      return { ...state, status: action.payload };

    case 'SET_SITE_CONFIG':
      // Save to localStorage
      localStorage.setItem(STORAGE_KEYS.SITE_URL, action.payload.siteUrl);
      localStorage.setItem(STORAGE_KEYS.USERNAME, action.payload.username);
      return {
        ...state,
        siteUrl: action.payload.siteUrl,
        username: action.payload.username,
        bearerToken: action.payload.bearerToken,
      };

    case 'CLEAR_SITE_CONFIG':
      localStorage.removeItem(STORAGE_KEYS.SITE_URL);
      localStorage.removeItem(STORAGE_KEYS.USERNAME);
      localStorage.removeItem(STORAGE_KEYS.APP_PASSWORD);
      return {
        ...state,
        siteUrl: '',
        username: '',
        bearerToken: '',
      };

    case 'ADD_MESSAGE':
      return {
        ...state,
        messages: [...state.messages, action.payload],
      };

    case 'CLEAR_MESSAGES':
      return {
        ...state,
        messages: [],
      };

    case 'UPDATE_TRANSCRIPT':
      return {
        ...state,
        currentTranscript: action.payload,
      };

    case 'APPEND_TRANSCRIPT':
      return {
        ...state,
        currentTranscript: state.currentTranscript + action.payload,
      };


    case 'SET_SESSION_DATA':
      return {
        ...state,
        sessionToken: action.payload.sessionToken,
        modalities: action.payload.modalities,
        isCustomTtsEnabled: !action.payload.modalities.includes('audio'),
      };

    case 'SET_PLAYING_CUSTOM_TTS':
      return {
        ...state,
        isPlayingCustomTts: action.payload,
      };

    default:
      return state;
  }
}

// Context type
interface AppContextType {
  state: AppState;
  dispatch: React.Dispatch<AppAction>;
  // Helper functions for common actions
  updateStatus: (status: AppState['status']) => void;
  setSiteConfig: (siteUrl: string, username: string, bearerToken: string) => void;
  clearSiteConfig: () => void;
  addMessage: (message: Message) => void;
  clearMessages: () => void;
  updateTranscript: (transcript: string) => void;
  appendTranscript: (text: string) => void;
  setSessionData: (sessionToken: string, modalities: string[]) => void;
  setPlayingCustomTts: (playing: boolean) => void;
}

// Create context
const AppContext = createContext<AppContextType | undefined>(undefined);

// Provider component
export function AppProvider({ children }: { children: ReactNode }) {
  const [state, dispatch] = useReducer(appReducer, initialState);

  // Helper functions – wrapped in useCallback so their reference
  // stays stable across re-renders, thus allowing memoization of
  // hooks using them.  This is both:
  // - a performance optimization
  // - a critical bug fix, because the useSessionManager hook depends
  //   on these dispatch functions, and if they change at every render,
  //   the SessionManager class will be re-created at every render,
  //   losing its internal state, thus breaking the interrupt and
  //   press-and-hold functionalities.
  const updateStatus = useCallback(
    (status: AppState['status']) => {
      console.log('[AppContext] Updating status:', status.toUpperCase());
      dispatch({ type: 'UPDATE_STATUS', payload: status });
    },
    [dispatch]
  );

  const setSiteConfig = useCallback(
    (siteUrl: string, username: string, bearerToken: string) => {
      dispatch({ type: 'SET_SITE_CONFIG', payload: { siteUrl, username, bearerToken } });
    },
    [dispatch]
  );

  const clearSiteConfig = useCallback(() => {
    dispatch({ type: 'CLEAR_SITE_CONFIG' });
  }, [dispatch]);

  const addMessage = useCallback(
    (message: Message) => {
      dispatch({ type: 'ADD_MESSAGE', payload: message });
    },
    [dispatch]
  );

  const clearMessages = useCallback(() => {
    dispatch({ type: 'CLEAR_MESSAGES' });
  }, [dispatch]);

  const updateTranscript = useCallback(
    (transcript: string) => {
      dispatch({ type: 'UPDATE_TRANSCRIPT', payload: transcript });
    },
    [dispatch]
  );

  const appendTranscript = useCallback(
    (text: string) => {
      dispatch({ type: 'APPEND_TRANSCRIPT', payload: text });
    },
    [dispatch]
  );

  const setSessionData = useCallback(
    (sessionToken: string, modalities: string[]) => {
      dispatch({ type: 'SET_SESSION_DATA', payload: { sessionToken, modalities } });
    },
    [dispatch]
  );

  const setPlayingCustomTts = useCallback(
    (playing: boolean) => {
      dispatch({ type: 'SET_PLAYING_CUSTOM_TTS', payload: playing });
    },
    [dispatch]
  );

  // Memoise the context so its reference only changes when necessary.
  // Wrapping the context in a useMemo is a common React pattern to avoid
  // unnecessary re-renders.
  const contextValue: AppContextType = useMemo(
    () => ({
      state,
      dispatch,
      updateStatus,
      setSiteConfig,
      clearSiteConfig,
      addMessage,
      clearMessages,
      updateTranscript,
      appendTranscript,
      setSessionData,
      setPlayingCustomTts,
    }),
    [
      state,
      dispatch,
      updateStatus,
      setSiteConfig,
      clearSiteConfig,
      addMessage,
      clearMessages,
      updateTranscript,
      appendTranscript,
      setSessionData,
      setPlayingCustomTts,
    ]
  );

  return <AppContext.Provider value={contextValue}>{children}</AppContext.Provider>;
}

// Hook to use the context
export function useAppContext() {
  const context = useContext(AppContext);
  if (context === undefined) {
    throw new Error('useAppContext must be used within an AppProvider');
  }
  return context;
}
