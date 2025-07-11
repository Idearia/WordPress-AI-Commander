import { UIElements } from '@/types';
import { UI_TEXT, STATUS_MESSAGES } from '@/utils/constants';

export function initializeElements(): UIElements {
  return {
    configScreen: document.getElementById('configScreen') as HTMLElement,
    mainApp: document.getElementById('mainApp') as HTMLElement,
    configForm: document.getElementById('configForm') as HTMLFormElement,
    siteUrlInput: document.getElementById('siteUrl') as HTMLInputElement,
    usernameInput: document.getElementById('username') as HTMLInputElement,
    appPasswordInput: document.getElementById('appPassword') as HTMLInputElement,
    configError: document.getElementById('configError') as HTMLElement,
    connectBtn: document.getElementById('connectBtn') as HTMLButtonElement,
    settingsBtn: document.getElementById('settingsBtn') as HTMLButtonElement,
    settingsMenu: document.getElementById('settingsMenu') as HTMLElement,
    changeConfigBtn: document.getElementById('changeConfigBtn') as HTMLElement,
    logoutBtn: document.getElementById('logoutBtn') as HTMLElement,
    micButton: document.getElementById('micButton') as HTMLButtonElement,
    statusText: document.getElementById('statusText') as HTMLElement,
    chatContainer: document.getElementById('chatContainer') as HTMLElement,
    emptyState: document.getElementById('emptyState') as HTMLElement,
    remoteAudio: document.getElementById('remoteAudio') as HTMLAudioElement,
    loadingOverlay: document.getElementById('loadingOverlay') as HTMLElement,
  };
}

export function updateUIWithTranslations(): void {
  // Update page title
  document.title = UI_TEXT.page_title;

  // Update configuration screen
  const configTitle = document.querySelector('.config-title');
  if (configTitle) configTitle.textContent = UI_TEXT.config_title;

  const configSubtitle = document.querySelector('.config-subtitle');
  if (configSubtitle) configSubtitle.textContent = UI_TEXT.config_subtitle;

  // Update note section
  const noteSection = document.querySelector('div[style*="background: #f7fafc"]');
  if (noteSection) {
    noteSection.innerHTML = `<strong>${UI_TEXT.note_label}</strong> ${UI_TEXT.note_text}
      <a href="https://wordpress.org/documentation/article/application-passwords/" target="_blank" style="color: #667eea; text-decoration: underline;">${UI_TEXT.how_to_generate_link}</a>`;
  }

  // Update form labels
  const siteUrlLabel = document.querySelector('label[for="siteUrl"]');
  if (siteUrlLabel) siteUrlLabel.textContent = UI_TEXT.site_url_label;

  const siteUrlInput = document.getElementById('siteUrl') as HTMLInputElement;
  if (siteUrlInput) siteUrlInput.placeholder = UI_TEXT.site_url_placeholder;

  const siteUrlHint = siteUrlLabel?.parentElement?.querySelector('.form-hint');
  if (siteUrlHint) siteUrlHint.textContent = UI_TEXT.site_url_hint;

  const usernameLabel = document.querySelector('label[for="username"]');
  if (usernameLabel) usernameLabel.textContent = UI_TEXT.username_label;

  const usernameInput = document.getElementById('username') as HTMLInputElement;
  if (usernameInput) usernameInput.placeholder = UI_TEXT.username_placeholder;

  const usernameHint = usernameLabel?.parentElement?.querySelector('.form-hint');
  if (usernameHint) usernameHint.textContent = UI_TEXT.username_hint;

  const appPasswordLabel = document.querySelector('label[for="appPassword"]');
  if (appPasswordLabel) appPasswordLabel.textContent = UI_TEXT.app_password_label;

  const appPasswordHint = appPasswordLabel?.parentElement?.querySelector('.form-hint');
  if (appPasswordHint) appPasswordHint.textContent = UI_TEXT.app_password_hint;

  // Update connect button
  const connectBtn = document.getElementById('connectBtn') as HTMLButtonElement | null;
  if (connectBtn && !connectBtn.disabled) {
    connectBtn.textContent = UI_TEXT.connect_button;
  }

  // Update main interface
  const officeName = document.getElementById('officeName');
  if (officeName) officeName.textContent = UI_TEXT.office_name;

  // Update settings menu
  const changeConfigSpan = document.querySelector('#changeConfigBtn span');
  if (changeConfigSpan) changeConfigSpan.textContent = UI_TEXT.change_config;

  const disconnectSpan = document.querySelector('#logoutBtn span');
  if (disconnectSpan) disconnectSpan.textContent = UI_TEXT.disconnect;

  // Update chat interface
  const greetingTitle = document.querySelector('#emptyState h2');
  if (greetingTitle) greetingTitle.textContent = UI_TEXT.greeting_title;

  const greetingText = document.querySelector('#emptyState p');
  if (greetingText) greetingText.textContent = UI_TEXT.greeting_text;

  // Update suggestions
  const suggestions = document.querySelectorAll('.suggestion');
  if (suggestions.length >= 3) {
    suggestions[0].textContent = UI_TEXT.suggestion_1;
    suggestions[1].textContent = UI_TEXT.suggestion_2;
    suggestions[2].textContent = UI_TEXT.suggestion_3;
  }

  // Update status text
  const statusText = document.getElementById('statusText');
  if (statusText && statusText.textContent === 'Premi per iniziare') {
    statusText.textContent = STATUS_MESSAGES.disconnected;
  }
}
