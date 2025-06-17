import { UIElements, Message } from '@/types';
import { UI_CONFIG } from '@/utils/constants';

export class UIController {
  private typingMessageDiv: HTMLDivElement | null = null;

  constructor(private elements: UIElements) {}

  showConfigScreen(): void {
    this.elements.configScreen.style.display = 'flex';
    this.elements.mainApp.classList.remove('active');
  }

  showMainApp(): void {
    this.elements.configScreen.style.display = 'none';
    this.elements.mainApp.classList.add('active');
  }

  showError(message: string): void {
    this.elements.configError.textContent = message;
    this.elements.configError.style.display = 'block';
    setTimeout(() => {
      this.elements.configError.style.display = 'none';
    }, UI_CONFIG.ERROR_DISPLAY_DURATION);
  }

  showLoading(show: boolean): void {
    this.elements.loadingOverlay.style.display = show ? 'flex' : 'none';
  }

  toggleSettingsMenu(): void {
    this.elements.settingsMenu.classList.toggle('active');
  }

  hideSettingsMenu(): void {
    this.elements.settingsMenu.classList.remove('active');
  }

  clearMessages(): void {
    this.elements.chatContainer.innerHTML = '';
    this.elements.chatContainer.appendChild(this.elements.emptyState);
  }

  addMessage(message: Message): void {
    if (this.elements.emptyState.parentNode) {
      this.elements.emptyState.remove();
    }

    const messageDiv = document.createElement('div');
    messageDiv.className = `chat-message ${message.type}`;

    const bubbleDiv = document.createElement('div');
    bubbleDiv.className = 'message-bubble';
    bubbleDiv.textContent = message.content;

    messageDiv.appendChild(bubbleDiv);
    this.elements.chatContainer.appendChild(messageDiv);

    // Scroll to bottom
    this.elements.chatContainer.scrollTop = this.elements.chatContainer.scrollHeight;
  }

  showTypingIndicator(): void {
    if (this.elements.emptyState.parentNode) {
      this.elements.emptyState.remove();
    }

    this.typingMessageDiv = document.createElement('div');
    this.typingMessageDiv.className = 'chat-message assistant';
    this.typingMessageDiv.id = 'typingMessage';

    const bubbleDiv = document.createElement('div');
    bubbleDiv.className = 'message-bubble typing';
    bubbleDiv.innerHTML = `
      <span class="typing-dot"></span>
      <span class="typing-dot"></span>
      <span class="typing-dot"></span>
    `;

    this.typingMessageDiv.appendChild(bubbleDiv);
    this.elements.chatContainer.appendChild(this.typingMessageDiv);

    // Scroll to bottom
    this.elements.chatContainer.scrollTop = this.elements.chatContainer.scrollHeight;
  }

  updateTypingMessage(text: string): void {
    if (this.typingMessageDiv && text) {
      const bubble = this.typingMessageDiv.querySelector('.message-bubble');
      if (bubble) {
        bubble.className = 'message-bubble';
        bubble.textContent = text;

        // Scroll to bottom
        this.elements.chatContainer.scrollTop = this.elements.chatContainer.scrollHeight;
      }
    }
  }

  hideTypingIndicator(): void {
    if (this.typingMessageDiv) {
      this.typingMessageDiv.remove();
      this.typingMessageDiv = null;
    }
  }

  hasTypingIndicator(): boolean {
    return this.typingMessageDiv !== null;
  }

  populateConfigForm(siteUrl: string, username: string): void {
    this.elements.siteUrlInput.value = siteUrl;
    this.elements.usernameInput.value = username;
    this.elements.appPasswordInput.value = ''; // Don't populate password for security
  }

  clearConfigForm(): void {
    this.elements.siteUrlInput.value = '';
    this.elements.usernameInput.value = '';
    this.elements.appPasswordInput.value = '';
  }

  disableConnectButton(disabled: boolean, text?: string): void {
    this.elements.connectBtn.disabled = disabled;
    if (text) {
      this.elements.connectBtn.textContent = text;
    }
  }
}
