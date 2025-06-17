import { AppStatus } from '@/types';
import { STATUS_MESSAGES } from '@/utils/constants';

export interface MicButtonCallbacks {
  onStartRecording: () => void;
  onStopRecording: () => void;
  onInterruptTts: () => void;
}

export class MicButtonController {
  private currentState: AppStatus = 'disconnected';
  private callbacks: MicButtonCallbacks;
  private buttonElement: HTMLButtonElement;
  private statusElement: HTMLElement;

  constructor(
    buttonElement: HTMLButtonElement,
    statusElement: HTMLElement,
    callbacks: MicButtonCallbacks
  ) {
    this.buttonElement = buttonElement;
    this.statusElement = statusElement;
    this.callbacks = callbacks;
    this.buttonElement.addEventListener('click', () => this.handleClick());
  }

  handleClick(): void {
    switch (this.currentState) {
      case 'disconnected':
      case 'error':
        this.callbacks.onStartRecording();
        break;

      case 'speaking':
        this.callbacks.onInterruptTts();
        break;

      case 'recording':
      case 'processing':
      case 'tool_wait':
      case 'idle':
        this.callbacks.onStopRecording();
        break;

      case 'connecting':
        // Do nothing while connecting
        break;

      default:
        console.warn('Unknown mic button state:', this.currentState);
    }
  }

  setState(newState: AppStatus, options: { message?: string } = {}): void {
    console.log(`Mic button: ${this.currentState} → ${newState}`);
    this.currentState = newState;
    this.updateUI(newState, options);
  }

  private updateUI(state: AppStatus, options: { message?: string } = {}): void {
    this.buttonElement.className = 'mic-button';
    this.buttonElement.innerHTML = '';
    this.buttonElement.disabled = false;

    switch (state) {
      case 'disconnected':
        this.statusElement.textContent = STATUS_MESSAGES.disconnected;
        this.statusElement.className = 'status-text';
        this.buttonElement.innerHTML = this.getMicIcon();
        break;

      case 'connecting':
        this.statusElement.textContent = STATUS_MESSAGES.connecting;
        this.statusElement.className = 'status-text';
        this.buttonElement.innerHTML = '<div class="spinner"></div>';
        this.buttonElement.disabled = true;
        break;

      case 'recording':
        this.statusElement.textContent = STATUS_MESSAGES.recording;
        this.statusElement.className = 'status-text';
        this.buttonElement.classList.add('recording');
        this.buttonElement.innerHTML = this.getSoundWaveIcon();
        break;

      case 'processing':
        this.statusElement.textContent = STATUS_MESSAGES.processing;
        this.statusElement.className = 'status-text';
        this.buttonElement.innerHTML = '<div class="spinner"></div>';
        break;

      case 'speaking':
        this.statusElement.textContent = STATUS_MESSAGES.speaking_interruptible;
        this.statusElement.className = 'status-text';
        this.buttonElement.disabled = false;
        this.buttonElement.innerHTML = this.getStopIcon();
        break;

      case 'tool_wait':
        this.statusElement.textContent = STATUS_MESSAGES.tool_wait;
        this.statusElement.className = 'status-text';
        this.buttonElement.innerHTML = '<div class="spinner"></div>';
        break;

      case 'idle':
        this.statusElement.textContent = STATUS_MESSAGES.idle;
        this.statusElement.className = 'status-text';
        this.buttonElement.innerHTML = this.getStopIcon();
        break;

      case 'error':
        this.statusElement.textContent = options.message || STATUS_MESSAGES.error;
        this.statusElement.className = 'status-text error';
        this.buttonElement.innerHTML = this.getMicIcon();
        break;
    }
  }

  private getMicIcon(): string {
    return `
      <svg viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/>
        <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>
      </svg>
    `;
  }

  private getStopIcon(): string {
    return `
      <svg viewBox="0 0 24 24" fill="currentColor">
        <rect x="6" y="6" width="12" height="12" rx="2"/>
      </svg>
    `;
  }

  private getSoundWaveIcon(): string {
    return `
      <div class="sound-wave">
        <span class="sound-bar"></span>
        <span class="sound-bar"></span>
        <span class="sound-bar"></span>
        <span class="sound-bar"></span>
        <span class="sound-bar"></span>
      </div>
    `;
  }
}
