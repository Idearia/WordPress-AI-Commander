import { useMemo } from 'react';
import { ApiService } from '@/services/ApiService';

/**
 * Custom React hook for managing API service instance.
 * This hook creates and memoizes an ApiService instance based on site URL and bearer token.
 * The service is recreated whenever the URL or token changes.
 */
export const useApiService = (siteUrl: string, bearerToken: string | null): ApiService | null => {
  // Create API service instance, memoized by URL and token
  const apiService = useMemo(() => {
    // Return null if required parameters are missing
    if (!siteUrl || !bearerToken) {
      return null;
    }

    // Create new API service instance
    console.log('[useApiService] Creating API service for:', siteUrl);
    return new ApiService(siteUrl, bearerToken);
  }, [siteUrl, bearerToken]);

  return apiService;
};
