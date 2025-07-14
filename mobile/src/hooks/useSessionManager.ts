import { useMemo, useRef, useEffect } from 'react';
import { useAppContext } from '@/context/AppContext';
import { SessionManager, SessionManagerDispatch } from '@/services/SessionManager';
import { ApiService } from '@/services/ApiService';

/**
 * Custom React hook for managing session manager instance.
 * This hook creates and memoizes a SessionManager instance with proper React integration.
 * The manager is recreated whenever the API service or audio element changes.
 */
export const useSessionManager = (
  apiService: ApiService | null,
  audioElement: HTMLAudioElement | null
): SessionManager | null => {
  const {
    state,
    updateStatus,
    addMessage,
    clearMessages,
    updateTranscript,
    appendTranscript,
    queueToolCall,
    dequeueToolCall,
    setSessionData,
    setPlayingCustomTts,
  } = useAppContext();

  // Use ref to maintain current state reference
  const stateRef = useRef(state);

  // Update state ref whenever state changes
  useEffect(() => {
    stateRef.current = state;
  }, [state]);

  // Create session manager instance, memoized by API service and audio element
  const sessionManager = useMemo(() => {
    // Return null if required dependencies are not available
    if (!apiService || !audioElement) {
      return null;
    }

    // Create dispatch object that directly uses React context functions
    const dispatch: SessionManagerDispatch = {
      updateStatus,
      addMessage,
      clearMessages,
      updateTranscript,
      appendTranscript,
      queueToolCall,
      dequeueToolCall,
      setSessionData,
      setPlayingCustomTts,
    };

    // Create session manager with direct React integration - no bridge needed
    return new SessionManager(
      dispatch,
      () => stateRef.current, // Function to get current state from ref
      apiService,
      audioElement
    );
  }, [
    apiService,
    audioElement,
    updateStatus,
    addMessage,
    clearMessages,
    updateTranscript,
    appendTranscript,
    queueToolCall,
    dequeueToolCall,
    setSessionData,
    setPlayingCustomTts,
  ]);

  return sessionManager;
};
