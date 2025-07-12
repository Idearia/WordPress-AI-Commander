import { AppState, AppStatus, Message, ToolCall } from '@/types';
import { STORAGE_KEYS } from '@/utils/constants';

export class StateManager {
  private state: AppState;
  private listeners: Array<(state: AppState) => void> = [];

  constructor() {
    this.state = {
      siteUrl: localStorage.getItem(STORAGE_KEYS.SITE_URL) || '',
      username: localStorage.getItem(STORAGE_KEYS.USERNAME) || '',
      bearerToken: null,
      sessionToken: null,
      status: 'disconnected',
      messages: [],
      currentTranscript: '',
      peerConnection: null,
      dataChannel: null,
      localStream: null,
      toolCallQueue: [],
      currentToolCallId: null,
      isCustomTtsEnabled: false,
      modalities: ['text', 'audio'],
      sessionModalities: [],
      isPlayingCustomTts: false,
    };
  }

  getState(): AppState {
    return this.state;
  }

  setState(updates: Partial<AppState>): void {
    this.state = { ...this.state, ...updates };
    this.notifyListeners();
  }

  updateStatus(status: AppStatus): void {
    this.setState({ status });
  }

  addMessage(message: Message): void {
    this.setState({ messages: [...this.state.messages, message] });
  }

  clearMessages(): void {
    this.setState({ messages: [] });
  }

  setSiteConfig(siteUrl: string, username: string, bearerToken: string): void {
    this.setState({ siteUrl, username, bearerToken });
    localStorage.setItem(STORAGE_KEYS.SITE_URL, siteUrl);
    localStorage.setItem(STORAGE_KEYS.USERNAME, username);
  }

  clearSiteConfig(): void {
    this.setState({ siteUrl: '', username: '', bearerToken: null });
    localStorage.removeItem(STORAGE_KEYS.SITE_URL);
    localStorage.removeItem(STORAGE_KEYS.USERNAME);
    localStorage.removeItem(STORAGE_KEYS.APP_PASSWORD);
  }

  setSessionData(sessionToken: string, modalities: string[]): void {
    const isCustomTtsEnabled = !modalities.includes('audio');
    this.setState({ sessionToken, sessionModalities: modalities, isCustomTtsEnabled });
  }

  updateTranscript(transcript: string): void {
    this.setState({ currentTranscript: transcript });
  }

  appendTranscript(delta: string): void {
    this.setState({ currentTranscript: this.state.currentTranscript + delta });
  }

  queueToolCall(toolCall: ToolCall): void {
    this.setState({ toolCallQueue: [...this.state.toolCallQueue, toolCall] });
  }

  dequeueToolCall(): ToolCall | undefined {
    const [toolCall, ...rest] = this.state.toolCallQueue;
    this.setState({ toolCallQueue: rest, currentToolCallId: toolCall?.call_id || null });
    return toolCall;
  }

  setPlayingCustomTts(isPlaying: boolean): void {
    this.setState({ isPlayingCustomTts: isPlaying });
  }

  subscribe(listener: (state: AppState) => void): () => void {
    this.listeners.push(listener);
    return () => {
      this.listeners = this.listeners.filter((l) => l !== listener);
    };
  }

  private notifyListeners(): void {
    this.listeners.forEach((listener) => listener(this.state));
  }
}
