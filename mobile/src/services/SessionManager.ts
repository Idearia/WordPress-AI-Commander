import { ApiService } from './ApiService';
import { WebRTCService } from './WebRTCService';
import { AudioService } from './AudioService';
import {
  RealtimeEvent,
  ToolCall,
  ResponseDoneEvent,
  TranscriptionEvent,
  DeltaEvent,
  ErrorEvent,
  AppState,
  AppStatus,
  Message,
} from '@/types';
import { UiMessages } from '@/utils/constants';

/**
 * Interface for React dispatch functions that SessionManager uses to update app state.
 * This eliminates the need for a bridge pattern by directly accepting React functions.
 */
export interface SessionManagerDispatch {
  updateStatus: (status: AppStatus) => void;
  addMessage: (message: Message) => void;
  clearMessages: () => void;
  updateTranscript: (transcript: string) => void;
  appendTranscript: (delta: string) => void;
  queueToolCall: (toolCall: ToolCall) => void;
  dequeueToolCall: () => ToolCall | null;
  setSessionData: (sessionToken: string, modalities: string[]) => void;
  setPlayingCustomTts: (isPlaying: boolean) => void;
}

export class SessionManager {
  private webrtcService: WebRTCService;
  private audioService: AudioService;

  /**
   * Creates a new SessionManager instance.
   * @param dispatch - React dispatch functions for updating app state
   * @param getState - Function to get current app state
   * @param apiService - Service for making API calls to WordPress backend
   * @param audioElement - HTML audio element for playing audio
   */
  constructor(
    private dispatch: SessionManagerDispatch,
    private getState: () => AppState,
    private apiService: ApiService,
    private audioElement: HTMLAudioElement
  ) {
    this.webrtcService = new WebRTCService();
    this.audioService = new AudioService(apiService);
  }

  /**
   * Starts a new voice session with OpenAI's Realtime API.
   * This establishes a WebRTC connection and configures the session.
   */
  async startSession(): Promise<void> {
    try {
      // Reset UI state for new session
      this.dispatch.updateStatus('connecting');
      this.dispatch.clearMessages();

      // Get session credentials from WordPress backend
      const sessionData = await this.apiService.createSession();
      this.dispatch.setSessionData(
        sessionData.client_secret.value,
        sessionData.modalities || ['text', 'audio']
      );

      console.log('[SessionManager] Session modalities:', sessionData.modalities);
      console.log('[SessionManager] Custom TTS enabled:', !sessionData.modalities?.includes('audio'));

      // Establish WebRTC connection to OpenAI
      await this.webrtcService.startSession(sessionData.client_secret.value, sessionData.model, {
        onDataChannelOpen: () => {
          this.dispatch.updateStatus('recording');
        },
        onDataChannelMessage: (event) => this.handleServerEvent(event),
        onDataChannelError: (error) => {
          console.error('Data channel error:', error);
          this.handleError(UiMessages.ERROR_MESSAGES.COMMUNICATION_ERROR);
        },
        onTrack: (event) => {
          if (this.audioElement && event.streams && event.streams[0]) {
            this.audioElement.srcObject = event.streams[0];
            this.audioElement.play().catch((e) => console.error('Audio play error:', e));
          }
        },
      });
    } catch (error) {
      console.error('Session start error:', error);
      this.handleError((error as Error).message || UiMessages.ERROR_MESSAGES.SESSION_FAILED);
      this.stopSession();
    }
  }

  /**
   * Stops the current voice session and cleans up resources.
   */
  stopSession(): void {
    // Interrupt any ongoing custom TTS playback
    if (this.getState().isPlayingCustomTts) {
      this.audioService.interruptCustomTts();
    }

    this.webrtcService.closeSession();
    this.audioService.cleanup(this.audioElement);
    this.dispatch.updateStatus('disconnected');
    this.dispatch.updateTranscript('');
  }

  /**
   * Enables or disables voice activity detection (VAD).
   * When disabled, the session uses press-and-hold mode instead of automatic voice detection.
   * @param enabled - Whether to enable VAD (true) or use press-and-hold (false)
   */
  setVadEnabled(enabled: boolean): void {
    console.log(`[SessionManager] setVadEnabled called with:`, enabled);
    try {
      this.webrtcService.updateTurnDetection(enabled ? 'server_vad' : 'none');
      console.log(`[SessionManager] VAD ${enabled ? 'enabled' : 'disabled'} - session update sent`);

      // When switching back to VAD after press-to-talk, process the recorded audio
      if (enabled) {
        console.log('[SessionManager] Committing audio buffer after press-to-talk');
        this.webrtcService.sendEvent({
          type: 'input_audio_buffer.commit',
        });

        // Request a response from OpenAI after committing the buffer
        console.log('[SessionManager] Creating response after press-to-talk');
        this.webrtcService.sendEvent({
          type: 'response.create',
        });
      }
    } catch (error) {
      console.error('[SessionManager] Failed to update VAD:', error);
    }
  }

  /**
   * Handles incoming events from OpenAI's Realtime API.
   * This processes various types of events like transcription, responses, tool calls, etc.
   */
  private async handleServerEvent(event: MessageEvent): Promise<void> {
    try {
      const data: RealtimeEvent = JSON.parse(event.data);
      console.log('[SessionManager] Server event:', data.type);

      const state = this.getState();

      switch (data.type) {
        case 'input_audio_buffer.speech_started':
          // User started speaking - unmute microphone and show recording state
          this.webrtcService.unmuteMicrophone();
          this.dispatch.updateStatus('recording');
          break;

        case 'input_audio_buffer.speech_stopped':
          // User stopped speaking - show processing state
          this.dispatch.updateStatus('processing');
          break;

        case 'conversation.item.input_audio_transcription.completed':
          // Add user's transcribed message to conversation
          this.dispatch.addMessage({
            type: 'user',
            content: (data as TranscriptionEvent).transcript,
          });
          break;

        case 'response.created':
          // OpenAI started generating a response - clear any typing indicators
          this.dispatch.updateTranscript('');
          break;

        case 'response.audio_transcript.delta':
        case 'response.text.delta':
          // OpenAI is streaming response text - show typing indicator
          this.dispatch.appendTranscript((data as DeltaEvent).delta || '');
          break;

        case 'response.audio.delta':
          // OpenAI is streaming audio response - show speaking state if not using custom TTS
          if (!state.isCustomTtsEnabled) {
            this.dispatch.updateStatus('speaking');
          }
          break;

        case 'response.function_call_arguments.delta':
          // OpenAI is calling a tool - show tool waiting state
          if (state.status !== 'tool_wait') {
            this.dispatch.updateStatus('tool_wait');
          }
          break;

        case 'response.done':
          // OpenAI finished generating response - process the complete response
          await this.handleResponseDone(data as ResponseDoneEvent);
          break;

        case 'output_audio_buffer.stopped':
          // Audio playback finished - return to recording state if not using custom TTS
          if (!state.isCustomTtsEnabled) {
            this.dispatch.updateStatus('recording');
          }
          break;

        case 'error':
          // Handle API errors
          console.error('API Error:', data);
          this.handleError((data as ErrorEvent).message || UiMessages.ERROR_MESSAGES.UNKNOWN_ERROR);
          break;
      }
    } catch (error) {
      console.error('Error parsing server event:', error);
    }
  }

  /**
   * Handles the completion of OpenAI's response.
   * This processes the final response text, handles tool calls, and manages custom TTS.
   */
  private async handleResponseDone(data: ResponseDoneEvent): Promise<void> {
    // Clear any typing indicators
    this.dispatch.updateTranscript('');

    if (data.response.status === 'failed') {
      this.dispatch.updateStatus('error');
      return;
    }

    // Extract response text from the response data
    const responseOutput = data.response?.output?.[0]?.content?.[0];
    const responseText = responseOutput?.text || responseOutput?.transcript;

    if (responseText) {
      this.dispatch.addMessage({ type: 'assistant', content: responseText });
    }

    // Play custom TTS if enabled (when OpenAI audio is disabled)
    const state = this.getState();
    if (state.isCustomTtsEnabled && responseText) {
      await this.playCustomTts(responseText);
    }

    // Process any tool calls in the response
    if (data.response.output) {
      data.response.output.forEach((outputItem) => {
        if (
          outputItem.type === 'function_call' &&
          outputItem.call_id &&
          outputItem.name &&
          outputItem.arguments
        ) {
          this.dispatch.queueToolCall({
            name: outputItem.name,
            arguments: outputItem.arguments,
            call_id: outputItem.call_id,
          });
        }
      });
    }

    // Execute the first tool call if any are queued
    const toolCall = this.dispatch.dequeueToolCall();
    if (toolCall) {
      await this.processToolCall(toolCall);
    } else {
      // If no tool calls and not using custom TTS, return to recording state
      const currentState = this.getState();
      if (!currentState.isCustomTtsEnabled) {
        this.dispatch.updateStatus('recording');
      }
    }
  }

  /**
   * Plays custom text-to-speech audio using WordPress backend.
   * This is used when OpenAI's audio modality is disabled.
   */
  private async playCustomTts(text: string): Promise<void> {
    try {
      await this.audioService.playCustomTtsAudio(
        text,
        this.audioElement,
        () => {
          console.log('[SessionManager] Custom TTS started');
          this.dispatch.setPlayingCustomTts(true);
          this.dispatch.updateStatus('speaking');
          this.webrtcService.muteMicrophone();
        },
        () => {
          console.log('[SessionManager] Custom TTS ended');
          const state = this.getState();
          if (state.status !== 'disconnected') {
            this.dispatch.setPlayingCustomTts(false);
            this.webrtcService.unmuteMicrophone();
            this.dispatch.updateStatus('recording');
          }
        }
      );
    } catch (error) {
      console.error('Custom TTS error:', error);
      if (this.getState().status !== 'disconnected') {
        this.handleError(UiMessages.ERROR_MESSAGES.TTS_FAILED);
      }
    }
  }

  /**
   * Executes a tool call by sending it to the WordPress backend.
   * Tools are WordPress actions that can be triggered by OpenAI.
   */
  private async processToolCall(toolCall: ToolCall): Promise<void> {
    // Status is already set to 'tool_wait' when receiving function_call_arguments.delta
    console.log('[SessionManager] Processing tool call:', toolCall);
    try {
      const result = await this.apiService.executeTool({
        tool_name: toolCall.name,
        arguments: toolCall.arguments,
      });

      if (!result.error) {
        this.sendFunctionResult(toolCall.call_id, result);
      } else {
        this.sendFunctionResult(toolCall.call_id, {
          error: true,
          message: result.message || UiMessages.ERROR_MESSAGES.TOOL_EXECUTION_FAILED,
        });
      }
    } catch (error) {
      this.sendFunctionResult(toolCall.call_id, {
        error: true,
        message: UiMessages.ERROR_MESSAGES.NETWORK_ERROR,
      });
    }

    // Process next tool call if any are queued
    const nextToolCall = this.dispatch.dequeueToolCall();
    if (nextToolCall) {
      await this.processToolCall(nextToolCall);
    }
  }

  /**
   * Sends a tool execution result back to OpenAI and requests a new response.
   */
  private sendFunctionResult(callId: string, result: unknown): void {
    try {
      // Send the tool result to OpenAI
      this.webrtcService.sendEvent({
        type: 'conversation.item.create',
        item: {
          type: 'function_call_output',
          call_id: callId,
          output: JSON.stringify(result),
        },
      });

      // Request a new response from OpenAI
      this.webrtcService.sendEvent({
        type: 'response.create',
      });
    } catch (error) {
      this.handleError(UiMessages.ERROR_MESSAGES.DATA_CHANNEL_NOT_OPEN);
    }
  }

  /**
   * Interrupts any ongoing text-to-speech playback.
   */
  interruptTts(): void {
    if (this.getState().isPlayingCustomTts) {
      this.audioService.interruptCustomTts();
    } else {
      this.stopSession();
    }
  }

  /**
   * Handles errors by updating the UI state and logging.
   */
  private handleError(message: string): void {
    console.error('Error:', message);
    this.dispatch.updateStatus('error');
  }
}
