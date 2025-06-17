import { UIElements, AppState } from '@/types';
import { StateManager } from '@/services/StateManager';
import { ApiService } from '@/services/ApiService';
import { SessionManager } from '@/services/SessionManager';
import { AudioService } from '@/services/AudioService';
import { UIController } from './UIController';
import { MicButtonController } from './MicButtonController';
import { ERROR_MESSAGES, STORAGE_KEYS } from '@/utils/constants';

export class App {
  private uiController: UIController;
  private micController: MicButtonController;
  private sessionManager: SessionManager | null = null;
  // @ts-expect-error - Used in handleConfigSubmit
  private apiService: ApiService | null = null;
  private audioService: AudioService | null = null;
  private lastMessageCount = 0;

  constructor(
    private elements: UIElements,
    private stateManager: StateManager
  ) {
    this.uiController = new UIController(elements);
    this.micController = new MicButtonController(elements.micButton, elements.statusText, {
      onStartRecording: () => this.startRecordingSession(),
      onStopRecording: () => this.stopRecordingSession(),
      onInterruptTts: () => this.interruptTts(),
      onPressAndHoldStart: () => this.handlePressAndHoldStart(),
      onPressAndHoldEnd: () => this.handlePressAndHoldEnd(),
    });
  }

  async init(): Promise<void> {
    // Set up event listeners first
    this.setupEventListeners();

    // Subscribe to state changes
    this.stateManager.subscribe((state) => this.onStateChange(state));

    // Check if we have saved credentials
    const state = this.stateManager.getState();
    if (state.siteUrl && this.generateBearerToken()) {
      this.elements.siteUrlInput.value = state.siteUrl;
      this.elements.usernameInput.value = state.username;

      // Just show the main app without testing connection
      // Services will be initialized on first mic button click
      this.uiController.showMainApp();
    } else {
      this.uiController.showConfigScreen();
    }
  }

  private setupEventListeners(): void {
    // Config form submission
    this.elements.configForm.addEventListener('submit', (e) => this.handleConfigSubmit(e));

    // Settings menu
    this.elements.settingsBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      this.uiController.toggleSettingsMenu();
    });

    // Change configuration
    this.elements.changeConfigBtn.addEventListener('click', () => {
      this.uiController.hideSettingsMenu();
      this.uiController.showConfigScreen();
      this.uiController.populateConfigForm(
        this.stateManager.getState().siteUrl,
        this.stateManager.getState().username
      );
    });

    // Logout
    this.elements.logoutBtn.addEventListener('click', () => this.handleLogout());

    // Unlock mobile audio on first mic button click
    this.elements.micButton.addEventListener(
      'click',
      () => {
        if (this.audioService) {
          this.audioService.unlockMobileAudio(this.elements.remoteAudio);
        }
      },
      { once: true }
    );

    // Close settings menu when clicking outside
    document.addEventListener('click', (e) => {
      if (
        !this.elements.settingsBtn.contains(e.target as Node) &&
        !this.elements.settingsMenu.contains(e.target as Node)
      ) {
        this.uiController.hideSettingsMenu();
      }
    });
  }

  private onStateChange(state: AppState): void {
    // Update mic button state
    this.micController.setState(state.status);

    // Update UI based on state changes
    if (state.currentTranscript) {
      if (!this.uiController.hasTypingIndicator()) {
        this.uiController.showTypingIndicator();
      }
      this.uiController.updateTypingMessage(state.currentTranscript);
    } else {
      // Hide typing indicator when transcript is cleared
      this.uiController.hideTypingIndicator();
    }

    // Handle messages
    if (state.messages.length !== this.lastMessageCount) {
      if (state.messages.length === 0) {
        // Messages were cleared
        this.uiController.clearMessages();
        this.lastMessageCount = 0;
      } else if (state.messages.length > this.lastMessageCount) {
        // New messages were added
        const newMessages = state.messages.slice(this.lastMessageCount);
        newMessages.forEach((msg) => this.uiController.addMessage(msg));
        this.lastMessageCount = state.messages.length;
      } else {
        // Messages were removed (shouldn't happen normally)
        // Rebuild the entire message list
        this.uiController.clearMessages();
        state.messages.forEach((msg) => this.uiController.addMessage(msg));
        this.lastMessageCount = state.messages.length;
      }
    }
  }

  private generateBearerToken(): boolean {
    const storedPassword = localStorage.getItem(STORAGE_KEYS.APP_PASSWORD);
    const state = this.stateManager.getState();

    if (state.username && storedPassword) {
      const bearerToken = ApiService.generateBearerToken(state.username, storedPassword);
      this.stateManager.setState({ bearerToken });
      return true;
    }
    return false;
  }

  private async handleConfigSubmit(e: Event): Promise<void> {
    e.preventDefault();

    const url = this.elements.siteUrlInput.value.trim();
    const username = this.elements.usernameInput.value.trim();
    const appPassword = this.elements.appPasswordInput.value.trim();

    if (!url || !username || !appPassword) return;

    // Validate URL
    try {
      const validUrl = new URL(url);
      if (!validUrl.protocol.startsWith('http')) {
        throw new Error('URL deve iniziare con http:// o https://');
      }
    } catch (error) {
      this.uiController.showError(ERROR_MESSAGES.INVALID_URL);
      return;
    }

    // Generate bearer token
    const bearerToken = ApiService.generateBearerToken(username, appPassword);

    // Test connection
    this.uiController.disableConnectButton(true, 'Connessione...');

    try {
      const apiService = new ApiService(url, bearerToken);
      await apiService.testConnection();

      // Save configuration
      const cleanUrl = url.replace(/\/$/, ''); // Remove trailing slash
      this.stateManager.setSiteConfig(cleanUrl, username, bearerToken);
      localStorage.setItem(STORAGE_KEYS.APP_PASSWORD, appPassword);

      // Initialize services
      await this.initializeServices(cleanUrl, bearerToken);

      this.uiController.showMainApp();
    } catch (error) {
      this.uiController.showError(
        (error as Error).message || 'Impossibile connettersi. Verifica i dati e riprova.'
      );
    } finally {
      this.uiController.disableConnectButton(false, 'Connetti');
    }
  }

  private handleLogout(): void {
    if (confirm('Vuoi disconnetterti e cancellare le credenziali salvate?')) {
      this.stateManager.clearSiteConfig();
      this.uiController.clearConfigForm();
      this.uiController.hideSettingsMenu();
      this.uiController.showConfigScreen();
      this.stopRecordingSession();
    }
  }

  private async startRecordingSession(): Promise<void> {
    // Initialize services if not already done
    if (!this.sessionManager) {
      const state = this.stateManager.getState();
      if (!state.siteUrl || !state.bearerToken) {
        this.uiController.showError('Credenziali non trovate. Accedi nuovamente.');
        this.uiController.showConfigScreen();
        return;
      }

      try {
        this.uiController.showLoading(true);
        await this.initializeServices(state.siteUrl, state.bearerToken);
      } catch (error) {
        console.error('Failed to initialize services:', error);
        this.uiController.showLoading(false);
        this.uiController.showError('Sessione scaduta. Accedi nuovamente.');
        this.uiController.showConfigScreen();
        return;
      }
    }

    // Show loading while starting session
    this.uiController.showLoading(true);

    try {
      await this.sessionManager!.startSession();
    } catch (error) {
      console.error('Failed to start session:', error);
    } finally {
      this.uiController.showLoading(false);
    }
  }

  private stopRecordingSession(): void {
    if (this.sessionManager) {
      this.sessionManager.stopSession();
    }
    this.uiController.hideTypingIndicator();
  }

  private interruptTts(): void {
    if (this.sessionManager) {
      this.sessionManager.interruptTts();
    }
  }

  private handlePressAndHoldStart(): void {
    console.log('[App] handlePressAndHoldStart called, sessionManager exists:', !!this.sessionManager);
    if (this.sessionManager) {
      this.sessionManager.setVadEnabled(false);
    } else {
      console.log('[App] No sessionManager available');
    }
  }

  private handlePressAndHoldEnd(): void {
    console.log('[App] handlePressAndHoldEnd called, sessionManager exists:', !!this.sessionManager);
    if (this.sessionManager) {
      this.sessionManager.setVadEnabled(true);
    } else {
      console.log('[App] No sessionManager available');
    }
  }

  private async initializeServices(siteUrl: string, bearerToken: string): Promise<void> {
    const apiService = new ApiService(siteUrl, bearerToken);

    // Only test connection when actually needed
    // This will throw if credentials are invalid
    await apiService.testConnection();

    this.apiService = apiService;
    this.audioService = new AudioService(apiService);
    this.sessionManager = new SessionManager(
      this.stateManager,
      apiService,
      this.elements.remoteAudio
    );
  }
}
