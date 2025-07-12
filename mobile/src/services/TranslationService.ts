import { ApiService } from './ApiService';

/**
 * Translation Service
 *
 * Handles loading and managing translations for the mobile app.
 * Fetches translations from WordPress via the REST API and provides
 * a simple interface for translating strings throughout the app.
 */
export class TranslationService {
  private translations: Record<string, string> = {};
  private locale: string = 'en_US';
  private isLoaded: boolean = false;
  private isLoading: boolean = false;

  /**
   * Load translations from WordPress API
   */
  async loadTranslations(apiService: ApiService): Promise<void> {
    // Prevent concurrent calls
    if (this.isLoading || this.isLoaded) {
      console.log('[TranslationService] Already loading or loaded, skipping duplicate call');
      return;
    }

    this.isLoading = true;

    try {
      const response = await apiService.get('/wp-json/ai-commander/v1/translations');

      if (response.data && response.data.translations) {
        this.translations = response.data.translations;
        this.locale = response.data.locale || 'en_US';
        this.isLoaded = true;

        console.log(`[TranslationService] Loaded translations for locale: ${this.locale}`);
      } else {
        console.warn('[TranslationService] Invalid response format from translations API');
        this.isLoaded = true;
      }
    } catch (error) {
      console.error('[TranslationService] Failed to load translations:', error);
      this.isLoaded = true;
    } finally {
      this.isLoading = false;
    }
  }

  /**
   * Get translated string by key
   *
   * @param key Translation key (e.g., 'mobile.ui.connect_btn')
   * @param fallback Fallback string if translation not found
   * @returns Translated string or fallback
   */
  t(key: string, fallback?: string): string {
    if (!this.isLoaded) {
      // console.warn(`[TranslationService] Translations not loaded yet, using fallback for: ${key}`);
      return fallback || key;
    }

    return this.translations[key] || fallback || key;
  }

  /**
   * Check if translations are loaded
   */
  isTranslationsLoaded(): boolean {
    return this.isLoaded;
  }

  /**
   * Get current locale
   */
  getLocale(): string {
    return this.locale;
  }
}
