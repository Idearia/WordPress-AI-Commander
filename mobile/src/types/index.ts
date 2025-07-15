// Global Config Types (from WordPress)
export interface PWAConfig {
  baseUrl: string;
  locale: string;
  translations: Record<string, string>;
  manifest: any;
  pwaPath: string;
  assistantGreeting: string;
  version: string;
}

declare global {
  interface Window {
    AI_COMMANDER_CONFIG?: PWAConfig;
  }
}

// App State Types
export interface AppState {
  siteUrl: string;
  username: string;
  bearerToken: string | null;
  sessionToken: string | null;
  status: AppStatus;
  messages: Message[];
  currentTranscript: string;
  isCustomTtsEnabled: boolean;
  modalities: string[];
  isPlayingCustomTts: boolean;
  assistantGreeting: string;
}

export type AppStatus =
  | 'disconnected'
  | 'connecting'
  | 'recording'
  | 'processing'
  | 'speaking'
  | 'tool_wait'
  | 'error';

export interface Message {
  type: 'user' | 'assistant';
  content: string;
  timestamp?: Date;
}

export interface ToolCall {
  name: string;
  arguments: string;
  call_id: string;
}

// API Response Types
export interface SessionResponse {
  client_secret: {
    value: string;
  };
  model?: string;
  modalities?: string[];
}

export interface ToolExecutionRequest {
  tool_name: string;
  arguments: string;
}

export interface ToolExecutionResponse {
  error?: boolean;
  message?: string;
  [key: string]: unknown;
}

// OpenAI Realtime API Event Types
export interface RealtimeEvent {
  type: string;
  [key: string]: unknown;
}

export interface ConversationItemCreateEvent extends RealtimeEvent {
  type: 'conversation.item.create';
  item: {
    type: string;
    call_id?: string;
    output?: string;
  };
}

export interface ResponseCreateEvent extends RealtimeEvent {
  type: 'response.create';
}

export interface ResponseDoneEvent extends RealtimeEvent {
  type: 'response.done';
  response: {
    status?: string;
    output?: Array<{
      type: string;
      content?: Array<{
        text?: string;
        transcript?: string;
      }>;
      call_id?: string;
      name?: string;
      arguments?: string;
    }>;
  };
}

export interface TranscriptionEvent extends RealtimeEvent {
  type: 'conversation.item.input_audio_transcription.completed';
  transcript: string;
}

export interface DeltaEvent extends RealtimeEvent {
  type: 'response.audio_transcript.delta' | 'response.text.delta';
  delta?: string;
}

export interface ErrorEvent extends RealtimeEvent {
  type: 'error';
  message?: string;
}

// UI Elements Type
export interface UIElements {
  configScreen: HTMLElement;
  mainApp: HTMLElement;
  configForm: HTMLFormElement;
  siteUrlInput: HTMLInputElement;
  usernameInput: HTMLInputElement;
  appPasswordInput: HTMLInputElement;
  configError: HTMLElement;
  connectBtn: HTMLButtonElement;
  settingsBtn: HTMLButtonElement;
  settingsMenu: HTMLElement;
  changeConfigBtn: HTMLElement;
  logoutBtn: HTMLElement;
  micButton: HTMLButtonElement;
  statusText: HTMLElement;
  chatContainer: HTMLElement;
  emptyState: HTMLElement;
  remoteAudio: HTMLAudioElement;
  loadingOverlay: HTMLElement;
}
