import { useMemo } from 'react';
import { AudioService } from '@/services/AudioService';
import { ApiService } from '@/services/ApiService';

/**
 * Custom React hook for managing audio service instance.
 * This hook creates and memoizes an AudioService instance based on the API service.
 * The service is recreated whenever the API service changes.
 */
export const useAudioService = (apiService: ApiService | null): AudioService | null => {
  // Create audio service instance, memoized by API service
  const audioService = useMemo(() => {
    // Return null if API service is not available
    if (!apiService) {
      return null;
    }

    // Create new audio service instance
    console.log('[useAudioService] Creating audio service');
    return new AudioService(apiService);
  }, [apiService]);

  return audioService;
};
