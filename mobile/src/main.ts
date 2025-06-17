import { App } from './components/App';
import { StateManager } from './services/StateManager';
import { initializeElements } from './utils/dom';
import './styles/main.css';

// Initialize the application when DOM is ready
document.addEventListener('DOMContentLoaded', async () => {
  const elements = initializeElements();
  const stateManager = new StateManager();
  const app = new App(elements, stateManager);

  try {
    await app.init();
  } catch (error) {
    console.error('Failed to initialize app:', error);
  }
});