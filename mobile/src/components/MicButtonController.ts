import { AppStatus } from '@/types';
import { STATUS_MESSAGES } from '@/utils/constants';

export interface MicButtonCallbacks {
  onStartRecording: () => void;
  onStopRecording: () => void;
  onInterruptTts: () => void;
  onPressAndHoldStart?: () => void;
  onPressAndHoldEnd?: () => void;
}

export class MicButtonController {
  private currentState: AppStatus = 'disconnected';
  private callbacks: MicButtonCallbacks;
  private buttonElement: HTMLButtonElement;
  private statusElement: HTMLElement;
  private pressTimer: number | null = null;
  private isPressAndHold: boolean = false;
  private pressStartTime: number = 0;
  private readonly PRESS_HOLD_DELAY = 300; // milliseconds to detect press-and-hold
  private readonly MIN_CLICK_TIME = 50; // minimum time to consider it a click

  constructor(
    buttonElement: HTMLButtonElement,
    statusElement: HTMLElement,
    callbacks: MicButtonCallbacks
  ) {
    this.buttonElement = buttonElement;
    this.statusElement = statusElement;
    this.callbacks = callbacks;
    this.setupEventListeners();
  }

  private setupEventListeners(): void {
    // Click event for simple clicks
    this.buttonElement.addEventListener('click', () => {
      // Only handle click if it's not from a press-and-hold
      if (!this.isPressAndHold && this.pressStartTime === 0) {
        this.handleClick();
      }
    });

    // Mouse events
    this.buttonElement.addEventListener('mousedown', (e) => this.handlePressStart(e));
    this.buttonElement.addEventListener('mouseup', (e) => this.handlePressEnd(e));
    this.buttonElement.addEventListener('mouseleave', (e) => this.handlePressCancel(e));

    // Touch events
    this.buttonElement.addEventListener('touchstart', (e) => this.handlePressStart(e), {
      passive: false,
    });
    this.buttonElement.addEventListener('touchend', (e) => this.handlePressEnd(e), {
      passive: false,
    });
    this.buttonElement.addEventListener('touchcancel', (e) => this.handlePressCancel(e));
  }

  private handlePressStart(e: Event): void {
    console.log('[MicButton] Press start event:', e.type, 'Current state:', this.currentState);
    
    // Only prevent default for touch events to avoid scroll
    if (e.type === 'touchstart') {
      e.preventDefault();
    }

    this.pressStartTime = Date.now();
    this.isPressAndHold = false;

    // Only start press-and-hold timer in recording state
    if (this.currentState === 'recording') {
      console.log('[MicButton] Starting press-and-hold timer (300ms)');
      // Start timer for press-and-hold detection
      this.pressTimer = window.setTimeout(() => {
        this.isPressAndHold = true;
        console.log('[MicButton] Press-and-hold ACTIVATED');
        
        // Add visual feedback
        this.buttonElement.classList.add('press-and-hold');
        
        if (this.callbacks.onPressAndHoldStart) {
          console.log('[MicButton] Calling onPressAndHoldStart callback');
          this.callbacks.onPressAndHoldStart();
        }
      }, this.PRESS_HOLD_DELAY);
    } else {
      console.log('[MicButton] Not in recording state, skipping press-and-hold');
    }
  }

  private handlePressEnd(e: Event): void {
    const pressDuration = Date.now() - this.pressStartTime;
    console.log('[MicButton] Press end event:', e.type, 'Duration:', pressDuration, 'ms', 'isPressAndHold:', this.isPressAndHold);
    
    if (e.type === 'touchend') {
      e.preventDefault();
    }

    // Clear the timer
    if (this.pressTimer) {
      clearTimeout(this.pressTimer);
      this.pressTimer = null;
    }

    // If press-and-hold was active, end it
    if (this.isPressAndHold && this.callbacks.onPressAndHoldEnd) {
      console.log('[MicButton] Ending press-and-hold, calling onPressAndHoldEnd callback');
      
      // Remove visual feedback
      this.buttonElement.classList.remove('press-and-hold');
      
      this.callbacks.onPressAndHoldEnd();
      // Reset immediately
      this.isPressAndHold = false;
    } else if (
      this.pressStartTime > 0 &&
      pressDuration >= this.MIN_CLICK_TIME &&
      pressDuration < this.PRESS_HOLD_DELAY
    ) {
      // Normal click: between MIN_CLICK_TIME and PRESS_HOLD_DELAY
      console.log('[MicButton] Normal click detected, calling handleClick()');
      this.handleClick();
    }

    // Reset for next press
    this.isPressAndHold = false;
    this.pressStartTime = 0;
  }

  private handlePressCancel(_e: Event): void {
    console.log('[MicButton] Press cancelled');
    
    // Clear timer and reset state if user moves away
    if (this.pressTimer) {
      clearTimeout(this.pressTimer);
      this.pressTimer = null;
    }

    if (this.isPressAndHold && this.callbacks.onPressAndHoldEnd) {
      console.log('[MicButton] Press-and-hold cancelled, calling onPressAndHoldEnd');
      
      // Remove visual feedback
      this.buttonElement.classList.remove('press-and-hold');
      
      this.callbacks.onPressAndHoldEnd();
    }

    this.isPressAndHold = false;
    this.pressStartTime = 0;
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
