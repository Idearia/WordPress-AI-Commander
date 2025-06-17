import { UIElements } from '@/types';

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
