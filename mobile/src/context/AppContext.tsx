import React, { createContext, useContext, useReducer, ReactNode } from 'react';
import { AppState, Message, ToolCall } from '@/types';
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
  | { type: 'QUEUE_TOOL_CALL'; payload: ToolCall }
  | { type: 'DEQUEUE_TOOL_CALL' }
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
  toolCallQueue: [],
  currentToolCallId: null,
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

    case 'QUEUE_TOOL_CALL':
      return {
        ...state,
        toolCallQueue: [...state.toolCallQueue, action.payload],
      };

    case 'DEQUEUE_TOOL_CALL':
      const [, ...remainingToolCalls] = state.toolCallQueue;
      return {
        ...state,
        toolCallQueue: remainingToolCalls,
        currentToolCallId: remainingToolCalls[0]?.call_id || null,
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
  queueToolCall: (toolCall: ToolCall) => void;
  dequeueToolCall: () => ToolCall | null;
  setSessionData: (sessionToken: string, modalities: string[]) => void;
  setPlayingCustomTts: (playing: boolean) => void;
}

// Create context
const AppContext = createContext<AppContextType | undefined>(undefined);

// Provider component
export function AppProvider({ children }: { children: ReactNode }) {
  const [state, dispatch] = useReducer(appReducer, initialState);

  // Helper functions
  const updateStatus = (status: AppState['status']) => {
    dispatch({ type: 'UPDATE_STATUS', payload: status });
  };

  const setSiteConfig = (siteUrl: string, username: string, bearerToken: string) => {
    dispatch({ type: 'SET_SITE_CONFIG', payload: { siteUrl, username, bearerToken } });
  };

  const clearSiteConfig = () => {
    dispatch({ type: 'CLEAR_SITE_CONFIG' });
  };

  const addMessage = (message: Message) => {
    dispatch({ type: 'ADD_MESSAGE', payload: message });
  };

  const clearMessages = () => {
    dispatch({ type: 'CLEAR_MESSAGES' });
  };

  const updateTranscript = (transcript: string) => {
    dispatch({ type: 'UPDATE_TRANSCRIPT', payload: transcript });
  };

  const appendTranscript = (text: string) => {
    dispatch({ type: 'APPEND_TRANSCRIPT', payload: text });
  };

  const queueToolCall = (toolCall: ToolCall) => {
    dispatch({ type: 'QUEUE_TOOL_CALL', payload: toolCall });
  };

  const dequeueToolCall = (): ToolCall | null => {
    const toolCall = state.toolCallQueue[0] || null;
    if (toolCall) {
      dispatch({ type: 'DEQUEUE_TOOL_CALL' });
    }
    return toolCall;
  };

  const setSessionData = (sessionToken: string, modalities: string[]) => {
    dispatch({ type: 'SET_SESSION_DATA', payload: { sessionToken, modalities } });
  };

  const setPlayingCustomTts = (playing: boolean) => {
    dispatch({ type: 'SET_PLAYING_CUSTOM_TTS', payload: playing });
  };

  const contextValue: AppContextType = {
    state,
    dispatch,
    updateStatus,
    setSiteConfig,
    clearSiteConfig,
    addMessage,
    clearMessages,
    updateTranscript,
    appendTranscript,
    queueToolCall,
    dequeueToolCall,
    setSessionData,
    setPlayingCustomTts,
  };

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
