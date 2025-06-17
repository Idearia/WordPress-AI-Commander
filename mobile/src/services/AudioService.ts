import { ERROR_MESSAGES } from '@/utils/constants';
import { ApiService } from './ApiService';

export class AudioService {
  private isMobileAudioUnlocked = false;
  private globalAudioContext: AudioContext | null = null;
  private isPlayingCustomTts = false;
  private currentAudioElement: HTMLAudioElement | null = null;

  constructor(private apiService: ApiService) {}

  /**
   * Unlocks mobile audio playback by resuming AudioContext and performing
   * a muted play/pause cycle on the audio element
   */
  unlockMobileAudio(audioElement: HTMLAudioElement): void {
    if (this.isMobileAudioUnlocked) return;
    this.isMobileAudioUnlocked = true;

    // Ensure an AudioContext is running
    try {
      this.globalAudioContext =
        this.globalAudioContext ||
        new (window.AudioContext ||
          (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext)();
      if (this.globalAudioContext.state === 'suspended') {
        this.globalAudioContext.resume();
      }
    } catch (error) {
      console.warn('AudioContext initialisation failed:', error);
    }

    // Perform a muted play/pause cycle to satisfy autoplay policies
    if (audioElement) {
      const wasMuted = audioElement.muted;
      audioElement.muted = true;

      const playPromise = audioElement.play();
      if (playPromise && typeof playPromise.then === 'function') {
        playPromise
          .then(() => {
            audioElement.pause();
            audioElement.currentTime = 0;
            audioElement.muted = wasMuted;
            console.log('[AI-Commander] Mobile audio unlocked');
          })
          .catch((err) => {
            console.warn('Mobile audio unlock play() rejected:', err);
            audioElement.muted = wasMuted;
          });
      } else {
        audioElement.muted = wasMuted;
      }
    }
  }

  /**
   * Plays custom TTS audio from the WordPress endpoint
   */
  async playCustomTtsAudio(
    text: string,
    audioElement: HTMLAudioElement,
    onStart?: () => void,
    onEnd?: () => void
  ): Promise<void> {
    if (!text) {
      console.log('[AudioService] No text provided for TTS');
      return;
    }

    console.log('[AudioService] Playing custom TTS for:', text);

    try {
      this.isPlayingCustomTts = true;
      this.currentAudioElement = audioElement;

      if (onStart) onStart();

      console.log('[AudioService] Fetching TTS audio from API...');
      const audioBlob = await this.apiService.getTextToSpeech(text);
      console.log('[AudioService] Received audio blob, size:', audioBlob.size);

      // Check if playback was interrupted
      if (!this.isPlayingCustomTts || !audioElement) {
        return;
      }

      // Prepare audio element for playback
      if (audioElement.dataset.objectUrl) {
        URL.revokeObjectURL(audioElement.dataset.objectUrl);
        delete audioElement.dataset.objectUrl;
      }

      audioElement.srcObject = null;
      const objectUrl = URL.createObjectURL(audioBlob);
      audioElement.dataset.objectUrl = objectUrl;
      audioElement.src = objectUrl;

      await audioElement.play();

      // Wait for playback to finish
      await new Promise<void>((resolve, reject) => {
        const checkInterrupted = () => {
          if (!this.isPlayingCustomTts) {
            audioElement.pause();
            resolve();
          }
        };

        audioElement.onended = () => resolve();
        audioElement.onerror = () => reject(new Error('Audio playback error'));

        // Check periodically if playback was interrupted
        const intervalId = setInterval(() => {
          checkInterrupted();
          if (!this.isPlayingCustomTts) {
            clearInterval(intervalId);
          }
        }, 100);

        // Clean up interval when promise resolves
        const originalResolve = resolve;
        resolve = () => {
          clearInterval(intervalId);
          originalResolve();
        };
      });

      // Clean up
      URL.revokeObjectURL(objectUrl);
      delete audioElement.dataset.objectUrl;
    } catch (err) {
      console.error('Error during custom TTS playback:', err);
      throw new Error(ERROR_MESSAGES.TTS_FAILED);
    } finally {
      this.isPlayingCustomTts = false;
      this.currentAudioElement = null;
      if (onEnd) onEnd();
    }
  }

  /**
   * Interrupts ongoing custom TTS playback
   */
  interruptCustomTts(): void {
    if (this.isPlayingCustomTts && this.currentAudioElement) {
      this.isPlayingCustomTts = false;
      this.currentAudioElement.pause();
      this.currentAudioElement.currentTime = 0;

      if (this.currentAudioElement.dataset.objectUrl) {
        URL.revokeObjectURL(this.currentAudioElement.dataset.objectUrl);
        delete this.currentAudioElement.dataset.objectUrl;
      }

      console.log('Custom TTS interrupted by user');
    }
  }

  /**
   * Cleans up audio resources
   */
  cleanup(audioElement: HTMLAudioElement): void {
    this.interruptCustomTts();

    if (audioElement) {
      audioElement.pause();
      audioElement.currentTime = 0;
      audioElement.srcObject = null;
      audioElement.src = '';

      if (audioElement.dataset.objectUrl) {
        URL.revokeObjectURL(audioElement.dataset.objectUrl);
        delete audioElement.dataset.objectUrl;
      }

      audioElement.onended = null;
    }
  }

  get isPlayingTts(): boolean {
    return this.isPlayingCustomTts;
  }
}
