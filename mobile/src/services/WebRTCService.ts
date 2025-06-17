import { OPENAI_API, AUDIO_CONFIG } from '@/utils/constants';
import { RealtimeEvent } from '@/types';

export interface WebRTCCallbacks {
  onDataChannelOpen?: () => void;
  onDataChannelMessage?: (event: MessageEvent) => void;
  onDataChannelError?: (error: Event) => void;
  onTrack?: (event: RTCTrackEvent) => void;
}

export class WebRTCService {
  private peerConnection: RTCPeerConnection | null = null;
  private dataChannel: RTCDataChannel | null = null;
  private localStream: MediaStream | null = null;

  async startSession(
    sessionToken: string,
    model: string = OPENAI_API.DEFAULT_MODEL,
    callbacks: WebRTCCallbacks = {}
  ): Promise<void> {
    // Create RTCPeerConnection
    this.peerConnection = new RTCPeerConnection();

    // Setup remote audio handler
    if (callbacks.onTrack) {
      this.peerConnection.ontrack = callbacks.onTrack;
    }

    // Increase bitrate to 96kb/s
    this.peerConnection.getTransceivers().forEach((t) => {
      if (t.sender.track?.kind === 'audio') {
        const params = t.sender.getParameters();
        params.encodings = [{ maxBitrate: AUDIO_CONFIG.MAX_BITRATE }];
        t.sender.setParameters(params);
      }
    });

    // Get user media
    this.localStream = await navigator.mediaDevices.getUserMedia({
      audio: {
        sampleRate: AUDIO_CONFIG.SAMPLE_RATE,
        channelCount: AUDIO_CONFIG.CHANNEL_COUNT,
        sampleSize: AUDIO_CONFIG.SAMPLE_SIZE,
        echoCancellation: AUDIO_CONFIG.ECHO_CANCELLATION,
        noiseSuppression: AUDIO_CONFIG.NOISE_SUPPRESSION,
        autoGainControl: AUDIO_CONFIG.AUTO_GAIN_CONTROL,
      },
    });

    // Add tracks to peer connection
    this.localStream.getTracks().forEach((track) => {
      this.peerConnection!.addTrack(track, this.localStream!);
    });

    // Create data channel
    this.dataChannel = this.peerConnection.createDataChannel('oai-events', {
      ordered: true,
    });

    if (callbacks.onDataChannelOpen) {
      this.dataChannel.onopen = callbacks.onDataChannelOpen;
    }

    if (callbacks.onDataChannelMessage) {
      this.dataChannel.onmessage = callbacks.onDataChannelMessage;
    }

    if (callbacks.onDataChannelError) {
      this.dataChannel.onerror = callbacks.onDataChannelError;
    }

    // SDP negotiation
    const offer = await this.peerConnection.createOffer();
    await this.peerConnection.setLocalDescription(offer);

    const sdpResponse = await fetch(`${OPENAI_API.REALTIME_URL}?model=${model}`, {
      method: 'POST',
      body: offer.sdp,
      headers: {
        Authorization: `Bearer ${sessionToken}`,
        'Content-Type': 'application/sdp',
      },
    });

    if (!sdpResponse.ok) {
      throw new Error(`SDP negotiation failed: ${sdpResponse.status}`);
    }

    const answerSdp = await sdpResponse.text();
    await this.peerConnection.setRemoteDescription({
      type: 'answer',
      sdp: answerSdp,
    });
  }

  sendEvent(event: RealtimeEvent): void {
    if (!this.dataChannel || this.dataChannel.readyState !== 'open') {
      throw new Error('Data channel not open');
    }
    this.dataChannel.send(JSON.stringify(event));
  }

  muteMicrophone(): void {
    if (this.localStream) {
      this.localStream.getAudioTracks().forEach((track) => {
        track.enabled = false;
      });
    }
  }

  unmuteMicrophone(): void {
    if (this.localStream) {
      this.localStream.getAudioTracks().forEach((track) => {
        track.enabled = true;
      });
    }
  }

  updateTurnDetection(mode: 'server_vad' | 'none'): void {
    console.log(`[WebRTCService] updateTurnDetection called with mode:`, mode);
    const event: RealtimeEvent = {
      type: 'session.update',
      session: {
        turn_detection:
          mode === 'server_vad'
            ? {
                type: 'server_vad',
                threshold: 0.5,
                prefix_padding_ms: 300,
                silence_duration_ms: 200,
              }
            : null,
      },
    };
    console.log('[WebRTCService] Sending session.update event:', event);
    this.sendEvent(event);
  }

  closeSession(): void {
    if (this.localStream) {
      this.localStream.getTracks().forEach((track) => track.stop());
      this.localStream = null;
    }
    if (this.dataChannel) {
      this.dataChannel.close();
      this.dataChannel = null;
    }
    if (this.peerConnection) {
      this.peerConnection.close();
      this.peerConnection = null;
    }
  }
}
