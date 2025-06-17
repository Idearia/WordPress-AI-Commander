import { StateManager } from './StateManager';
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
} from '@/types';
import { ERROR_MESSAGES } from '@/utils/constants';

export class SessionManager {
  private webrtcService: WebRTCService;
  private audioService: AudioService;

  constructor(
    private stateManager: StateManager,
    private apiService: ApiService,
    private audioElement: HTMLAudioElement
  ) {
    this.webrtcService = new WebRTCService();
    this.audioService = new AudioService(apiService);
  }

  async startSession(): Promise<void> {
    try {
      // Batch state updates to avoid multiple notifications
      this.stateManager.setState({
        status: 'connecting',
        messages: [],
        toolCallQueue: [],
        currentToolCallId: null,
      });

      // Get session token from WordPress
      const sessionData = await this.apiService.createSession();
      this.stateManager.setSessionData(
        sessionData.client_secret.value,
        sessionData.modalities || ['text', 'audio']
      );

      console.log('Session modalities:', sessionData.modalities);
      console.log('Custom TTS enabled:', !sessionData.modalities?.includes('audio'));

      // Start WebRTC session
      await this.webrtcService.startSession(sessionData.client_secret.value, sessionData.model, {
        onDataChannelOpen: () => {
          this.stateManager.updateStatus('recording');
        },
        onDataChannelMessage: (event) => this.handleServerEvent(event),
        onDataChannelError: (error) => {
          console.error('Data channel error:', error);
          this.handleError(ERROR_MESSAGES.COMMUNICATION_ERROR);
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
      this.handleError((error as Error).message || ERROR_MESSAGES.SESSION_FAILED);
      this.stopSession();
    }
  }

  stopSession(): void {
    // Interrupt any ongoing custom TTS playback
    if (this.stateManager.getState().isPlayingCustomTts) {
      this.audioService.interruptCustomTts();
    }

    this.webrtcService.closeSession();
    this.audioService.cleanup(this.audioElement);
    this.stateManager.updateStatus('disconnected');
    this.stateManager.updateTranscript('');
  }

  setVadEnabled(enabled: boolean): void {
    console.log(`[SessionManager] setVadEnabled called with:`, enabled);
    try {
      this.webrtcService.updateTurnDetection(enabled ? 'server_vad' : 'none');
      console.log(`[SessionManager] VAD ${enabled ? 'enabled' : 'disabled'} - session update sent`);
      
      // When re-enabling VAD after press-to-talk, commit the audio buffer and create response
      if (enabled) {
        console.log('[SessionManager] Committing audio buffer after press-to-talk');
        this.webrtcService.sendEvent({
          type: 'input_audio_buffer.commit'
        });
        
        // Create a response after committing the buffer
        console.log('[SessionManager] Creating response after press-to-talk');
        this.webrtcService.sendEvent({
          type: 'response.create'
        });
      }
    } catch (error) {
      console.error('[SessionManager] Failed to update VAD:', error);
    }
  }

  private async handleServerEvent(event: MessageEvent): Promise<void> {
    try {
      const data: RealtimeEvent = JSON.parse(event.data);
      console.log('Server event:', data.type, data);

      const state = this.stateManager.getState();

      switch (data.type) {
        case 'input_audio_buffer.speech_started':
          this.webrtcService.unmuteMicrophone();
          this.stateManager.updateStatus('recording');
          break;

        case 'input_audio_buffer.speech_stopped':
          this.stateManager.updateStatus('processing');
          break;

        case 'conversation.item.input_audio_transcription.completed':
          this.stateManager.addMessage({
            type: 'user',
            content: (data as TranscriptionEvent).transcript,
          });
          break;

        case 'response.created':
          this.stateManager.updateTranscript('');
          break;

        case 'response.audio_transcript.delta':
        case 'response.text.delta':
          this.stateManager.appendTranscript((data as DeltaEvent).delta || '');
          break;

        case 'response.audio.delta':
          if (!state.isCustomTtsEnabled) {
            this.stateManager.updateStatus('speaking');
          }
          break;

        case 'response.function_call_arguments.delta':
          // Only update to tool_wait if not already in that state
          if (state.status !== 'tool_wait') {
            this.stateManager.updateStatus('tool_wait');
          }
          break;

        case 'response.done':
          await this.handleResponseDone(data as ResponseDoneEvent);
          break;

        case 'output_audio_buffer.stopped':
          if (!state.isCustomTtsEnabled) {
            this.stateManager.updateStatus('idle');
          }
          break;

        case 'error':
          console.error('API Error:', data);
          this.handleError((data as ErrorEvent).message || ERROR_MESSAGES.UNKNOWN_ERROR);
          break;
      }
    } catch (error) {
      console.error('Error parsing server event:', error);
    }
  }

  private async handleResponseDone(data: ResponseDoneEvent): Promise<void> {
    // Clear typing indicator
    this.stateManager.updateTranscript('');

    if (data.response.status === 'failed') {
      this.stateManager.updateStatus('error');
      return;
    }

    const responseOutput = data.response?.output?.[0]?.content?.[0];
    const responseText = responseOutput?.text || responseOutput?.transcript;

    if (responseText) {
      this.stateManager.addMessage({ type: 'assistant', content: responseText });
    }

    // If custom TTS is enabled, synthesize and play audio now
    const state = this.stateManager.getState();
    if (state.isCustomTtsEnabled && responseText) {
      await this.playCustomTts(responseText);
    }

    // Handle function calls
    if (data.response.output) {
      data.response.output.forEach((outputItem) => {
        if (
          outputItem.type === 'function_call' &&
          outputItem.call_id &&
          outputItem.name &&
          outputItem.arguments
        ) {
          this.stateManager.queueToolCall({
            name: outputItem.name,
            arguments: outputItem.arguments,
            call_id: outputItem.call_id,
          });
        }
      });
    }

    // Process tool calls if any
    const toolCall = this.stateManager.dequeueToolCall();
    if (toolCall) {
      await this.processToolCall(toolCall);
    }
  }

  private async playCustomTts(text: string): Promise<void> {
    console.log('[SessionManager] Starting custom TTS for text:', text);
    try {
      await this.audioService.playCustomTtsAudio(
        text,
        this.audioElement,
        () => {
          console.log('[SessionManager] Custom TTS started');
          this.stateManager.setPlayingCustomTts(true);
          this.stateManager.updateStatus('speaking');
          this.webrtcService.muteMicrophone();
        },
        () => {
          console.log('[SessionManager] Custom TTS ended');
          const state = this.stateManager.getState();
          if (state.status !== 'disconnected') {
            this.stateManager.setPlayingCustomTts(false);
            this.webrtcService.unmuteMicrophone();
            this.stateManager.updateStatus('recording');
          }
        }
      );
    } catch (error) {
      console.error('Custom TTS error:', error);
      if (this.stateManager.getState().status !== 'disconnected') {
        this.handleError(ERROR_MESSAGES.TTS_FAILED);
      }
    }
  }

  private async processToolCall(toolCall: ToolCall): Promise<void> {
    // Status is already set to 'tool_wait' when receiving function_call_arguments.delta
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
          message: result.message || ERROR_MESSAGES.TOOL_EXECUTION_FAILED,
        });
      }
    } catch (error) {
      this.sendFunctionResult(toolCall.call_id, {
        error: true,
        message: ERROR_MESSAGES.NETWORK_ERROR,
      });
    }

    // Process next tool call if any
    const nextToolCall = this.stateManager.dequeueToolCall();
    if (nextToolCall) {
      await this.processToolCall(nextToolCall);
    }
  }

  private sendFunctionResult(callId: string, result: unknown): void {
    try {
      // Send function result
      this.webrtcService.sendEvent({
        type: 'conversation.item.create',
        item: {
          type: 'function_call_output',
          call_id: callId,
          output: JSON.stringify(result),
        },
      });

      // Request response
      this.webrtcService.sendEvent({
        type: 'response.create',
      });
    } catch (error) {
      this.handleError(ERROR_MESSAGES.DATA_CHANNEL_NOT_OPEN);
    }
  }

  interruptTts(): void {
    if (this.stateManager.getState().isPlayingCustomTts) {
      this.audioService.interruptCustomTts();
    } else {
      this.stopSession();
    }
  }

  private handleError(message: string): void {
    console.error('Error:', message);
    this.stateManager.updateStatus('error');
  }
}
