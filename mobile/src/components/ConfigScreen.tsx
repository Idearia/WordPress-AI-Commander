import React, { useState } from 'react';
import { useAppContext } from '@/context/AppContext';
import { ApiService } from '@/services/ApiService';
import { UI_CONFIG, STORAGE_KEYS } from '@/utils/constants';

interface ConfigScreenProps {
  onConfigSuccess: (apiService: ApiService) => void;
}

export function ConfigScreen({ onConfigSuccess }: ConfigScreenProps) {
  const { state, setSiteConfig } = useAppContext();
  const [isConnecting, setIsConnecting] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    
    const formData = new FormData(e.currentTarget);
    const url = formData.get('siteUrl') as string;
    const username = formData.get('username') as string;
    const appPassword = formData.get('appPassword') as string;

    if (!url || !username || !appPassword) return;

    // Validate URL
    try {
      const validUrl = new URL(url);
      if (!validUrl.protocol.startsWith('http')) {
        throw new Error('URL must start with http:// or https://');
      }
    } catch (error) {
      setError('Invalid URL. Enter a complete URL (e.g. https://www.yoursite.com)');
      return;
    }

    // Generate bearer token
    const bearerToken = ApiService.generateBearerToken(username, appPassword);

    // Test connection
    setIsConnecting(true);
    setError('');

    try {
      const apiService = new ApiService(url, bearerToken);
      await apiService.testConnection();

      // Save configuration
      const cleanUrl = url.replace(/\/$/, ''); // Remove trailing slash
      setSiteConfig(cleanUrl, username, bearerToken);
      localStorage.setItem(STORAGE_KEYS.APP_PASSWORD, appPassword);

      // Notify parent of successful config
      onConfigSuccess(apiService);
    } catch (error) {
      setError(
        (error as Error).message || 'Unable to connect. Check your data and try again.'
      );
    } finally {
      setIsConnecting(false);
    }
  };

  // Auto-hide error after display duration
  React.useEffect(() => {
    if (error) {
      const timer = setTimeout(() => setError(''), UI_CONFIG.ERROR_DISPLAY_DURATION);
      return () => clearTimeout(timer);
    }
    return undefined;
  }, [error]);

  return (
    <div className="config-screen">
      <div className="config-card">
        <div className="config-logo">IN</div>
        <h1 className="config-title">INofficina Voice Assistant</h1>
        <p className="config-subtitle">Enter the URL of your WordPress site and credentials</p>

        <div style={{
          background: '#f7fafc',
          border: '1px solid #e2e8f0',
          borderRadius: '8px',
          padding: '1rem',
          marginBottom: '1.5rem',
          fontSize: '0.875rem',
          color: '#4a5568'
        }}>
          <strong>Note:</strong> For the password, use an "Application Password" generated from your WordPress profile, not the normal password.
          <a 
            href="https://wordpress.org/documentation/article/application-passwords/" 
            target="_blank" 
            style={{ color: '#667eea', textDecoration: 'underline' }}
          >
            How to generate an app password →
          </a>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label className="form-label" htmlFor="siteUrl">Site URL</label>
            <input
              type="url"
              id="siteUrl"
              name="siteUrl"
              className="form-input"
              placeholder="https://www.yoursite.com"
              defaultValue={state.siteUrl}
              required
            />
            <p className="form-hint">The complete URL of your WordPress INofficina site</p>
          </div>

          <div className="form-group">
            <label className="form-label" htmlFor="username">Username</label>
            <input
              type="text"
              id="username"
              name="username"
              className="form-input"
              placeholder="mario.rossi"
              defaultValue={state.username}
              autoComplete="username"
              required
            />
            <p className="form-hint">Your WordPress username</p>
          </div>

          <div className="form-group">
            <label className="form-label" htmlFor="appPassword">App password</label>
            <input
              type="password"
              id="appPassword"
              name="appPassword"
              className="form-input"
              placeholder="xxxx xxxx xxxx xxxx"
              autoComplete="current-password"
              required
            />
            <p className="form-hint">The application password generated in WordPress (not the normal password)</p>
          </div>

          {error && (
            <div className="form-error" style={{ display: 'block' }}>
              {error}
            </div>
          )}

          <button type="submit" className="btn-primary" disabled={isConnecting}>
            {isConnecting ? 'Connecting...' : 'Connect'}
          </button>
        </form>
      </div>
    </div>
  );
}