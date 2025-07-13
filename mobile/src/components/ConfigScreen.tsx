import React, { useState } from 'react';
import { useAppContext } from '@/context/AppContext';
import { ApiService } from '@/services/ApiService';
import { UI_CONFIG, STORAGE_KEYS } from '@/utils/constants';
import { useTranslation } from '@/hooks/useTranslation';

interface ConfigScreenProps {
  onConfigSuccess: (apiService: ApiService) => void;
}

export function ConfigScreen({ onConfigSuccess }: ConfigScreenProps) {
  const { t } = useTranslation();
  const { state, setSiteConfig } = useAppContext();
  const [isConnecting, setIsConnecting] = useState(false);
  const [error, setError] = useState('');

  // Check if we have embedded config from WordPress
  const embeddedConfig = window.AI_COMMANDER_CONFIG;
  const hasEmbeddedConfig = !!embeddedConfig;

  // Check for Vite dev environment variable
  const viteBaseUrl = import.meta.env.VITE_WP_BASE_URL;
  const hasViteBaseUrl = !!viteBaseUrl;

  // We hide the URL field if we have either embedded config or Vite base URL
  const shouldHideUrlField = hasEmbeddedConfig || hasViteBaseUrl;

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();

    const formData = new FormData(e.currentTarget);
    const username = formData.get('username') as string;
    const appPassword = formData.get('appPassword') as string;

    // Get URL from embedded config, Vite env var, or form
    const url = hasEmbeddedConfig
      ? embeddedConfig.baseUrl
      : hasViteBaseUrl
        ? viteBaseUrl
        : (formData.get('siteUrl') as string);

    if (!url || !username || !appPassword) return;

    // Validate URL (only if not from embedded config or Vite)
    if (!shouldHideUrlField) {
      try {
        const validUrl = new URL(url);
        if (!validUrl.protocol.startsWith('http')) {
          throw new Error('URL must start with http:// or https://');
        }
      } catch (error) {
        setError('Invalid URL. Enter a complete URL (e.g. https://www.yoursite.com)');
        return;
      }
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
      setError((error as Error).message || 'Unable to connect. Check your data and try again.');
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
        <div className="config-logo">{t('mobile.ui.text_logo', 'AI')}</div>
        <h1 className="config-title">{t('mobile.ui.assistant_name', 'AI Commander Assistant')}</h1>
        <p className="config-subtitle">
          {hasEmbeddedConfig
            ? t(
                'mobile.ui.config.subtitle_embedded',
                'Enter your WordPress credentials to continue'
              )
            : hasViteBaseUrl
              ? t(
                  'mobile.ui.config.subtitle_vite',
                  'Development mode - Enter your WordPress credentials'
                )
              : t(
                  'mobile.ui.config.subtitle_default',
                  'Enter the URL of your WordPress site and credentials'
                )}
        </p>

        <div
          style={{
            background: '#f7fafc',
            border: '1px solid #e2e8f0',
            borderRadius: '8px',
            padding: '1rem',
            marginBottom: '1.5rem',
            fontSize: '0.875rem',
            color: '#4a5568',
          }}
        >
          <strong>{t('mobile.ui.config.note_title', 'Note:')}</strong>{' '}
          {t(
            'mobile.ui.config.note_text',
            'For the password, use an "Application Password" generated from your WordPress profile, not the normal password.'
          )}
        </div>

        <form onSubmit={handleSubmit}>
          {!shouldHideUrlField && (
            <div className="form-group">
              <label className="form-label" htmlFor="siteUrl">
                {t('mobile.ui.config.site_url_label', 'Site URL')}
              </label>
              <input
                type="url"
                id="siteUrl"
                name="siteUrl"
                className="form-input"
                placeholder={t('mobile.ui.config.site_url_placeholder', 'https://www.yoursite.com')}
                defaultValue={state.siteUrl}
                required
              />
              <p className="form-hint">
                {t('mobile.ui.config.site_url_hint', 'The complete URL of your WordPress site')}
              </p>
            </div>
          )}

          {hasViteBaseUrl && !hasEmbeddedConfig && (
            <div className="form-group" style={{ marginBottom: '1rem' }}>
              <div
                style={{
                  background: '#fff2e6',
                  border: '1px solid #ffd591',
                  borderRadius: '6px',
                  padding: '0.75rem',
                  fontSize: '0.875rem',
                  color: '#ad6800',
                }}
              >
                <strong>Development mode:</strong> {viteBaseUrl}
              </div>
            </div>
          )}

          <div className="form-group">
            <label className="form-label" htmlFor="username">
              {t('mobile.ui.config.username_label', 'Username')}
            </label>
            <input
              type="text"
              id="username"
              name="username"
              className="form-input"
              placeholder={t('mobile.ui.config.username_placeholder', 'john.doe')}
              defaultValue={state.username}
              autoComplete="username"
              required
            />
            <p className="form-hint">
              {t('mobile.ui.config.username_hint', 'Your WordPress username')}
            </p>
          </div>

          <div className="form-group">
            <label className="form-label" htmlFor="appPassword">
              {t('mobile.ui.config.app_password_label', 'App password')}
            </label>
            <input
              type="password"
              id="appPassword"
              name="appPassword"
              className="form-input"
              placeholder={t('mobile.ui.config.app_password_placeholder', 'xxxx xxxx xxxx xxxx')}
              autoComplete="current-password"
              required
            />
            <p className="form-hint">
              {t(
                'mobile.ui.config.app_password_hint',
                'The application password generated in WordPress (not the normal password)'
              )}
            </p>
          </div>

          {error && (
            <div className="form-error" style={{ display: 'block' }}>
              {error}
            </div>
          )}

          <button type="submit" className="btn-primary" disabled={isConnecting}>
            {isConnecting
              ? t('mobile.ui.config.connecting_btn', 'Connecting...')
              : t('mobile.ui.config.connect_btn', 'Connect')}
          </button>
        </form>
      </div>
    </div>
  );
}
