import React, { createContext, useContext } from 'react';
import { TranslationService } from '@/services/TranslationService';

// Translation context type
interface TranslationContextType {
  translationService: TranslationService;
  t: (key: string, fallback?: string) => string;
  isLoaded: boolean;
  locale: string;
}

// Create translation context
export const TranslationContext = createContext<TranslationContextType | undefined>(undefined);

// Hook to use translations
export function useTranslation() {
  const context = useContext(TranslationContext);
  if (context === undefined) {
    throw new Error('useTranslation must be used within a TranslationProvider');
  }
  return context;
}

// Translation provider props
export interface TranslationProviderProps {
  children: React.ReactNode;
  translationService: TranslationService;
}

// Provider component
export function TranslationProvider({ children, translationService }: TranslationProviderProps) {
  const t = (key: string, fallback?: string) => {
    return translationService.t(key, fallback);
  };

  const contextValue: TranslationContextType = {
    translationService,
    t,
    isLoaded: translationService.isTranslationsLoaded(),
    locale: translationService.getLocale(),
  };

  return (
    <TranslationContext.Provider value={contextValue}>
      {children}
    </TranslationContext.Provider>
  );
}