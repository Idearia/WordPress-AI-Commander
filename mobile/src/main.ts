import { App } from './components/App';
import { StateManager } from './services/StateManager';
import { initializeElements, updateUIWithTranslations } from './utils/dom';
import './styles/main.css';

// Initialize the application when DOM is ready
document.addEventListener('DOMContentLoaded', async () => {
  const elements = initializeElements();
  const stateManager = new StateManager();
  const app = new App(elements, stateManager);

  // Apply default translations immediately
  updateUIWithTranslations();

  try {
    await app.init();
  } catch (error) {
    console.error('Failed to initialize app:', error);
  }
});
