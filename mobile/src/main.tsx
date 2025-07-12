import React from 'react';
import { createRoot } from 'react-dom/client';
import { AppProvider } from '@/context/AppContext';
import { App } from '@/components/App';

// Import styles
import './styles/main.css';

// Get the app container
const container = document.getElementById('app');

if (!container) {
  throw new Error('Could not find app container element');
}

// Create React root and render the app
const root = createRoot(container);

root.render(
  <React.StrictMode>
    <AppProvider>
      <App />
    </AppProvider>
  </React.StrictMode>
);

console.log('[Main] React app initialized');