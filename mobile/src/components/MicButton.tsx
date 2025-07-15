import React, { useState, useRef } from 'react';
import { useTranslation } from '@/hooks/useTranslation';
import { useAppContext } from '@/context/AppContext';
import { AppStatus } from '@/types';

interface MicButtonProps {
  onStartRecording: () => void;
  onStopRecording: () => void;
  onInterruptTts: () => void;
  onPressAndHoldStart?: () => void;
  onPressAndHoldEnd?: () => void;
}

export function MicButton({
  onStartRecording,
  onStopRecording,
  onInterruptTts,
  onPressAndHoldStart,
  onPressAndHoldEnd,
}: MicButtonProps) {
  const { t } = useTranslation();
  const { state } = useAppContext();
  const [isPressAndHold, setIsPressAndHold] = useState(false);
  const pressStartTime = useRef(0);
  const pressTimer = useRef<number | null>(null);

  const PRESS_HOLD_DELAY = 300; // milliseconds to detect press-and-hold
  const MIN_CLICK_TIME = 50; // minimum time to consider it a click

  const handleClick = () => {
    console.log('[MicButton] handleClick called with status:', state.status);
    switch (state.status) {
      case 'disconnected':
      case 'error':
        onStartRecording();
        break;

      case 'speaking':
        onInterruptTts();
        break;

      case 'recording':
      case 'processing':
      case 'tool_wait':
        onStopRecording();
        break;

      case 'connecting':
        // Do nothing while connecting
        break;

      default:
        console.warn('Unknown mic button state:', state.status);
    }
  };

  const handlePressStart = (e: React.MouseEvent | React.TouchEvent) => {
    console.log('[MicButton] Press start event:', e.type, 'Current state:', state.status);

    // Only prevent default for touch events to avoid scroll
    if (e.type === 'touchstart') {
      e.preventDefault();
    }

    pressStartTime.current = Date.now();
    setIsPressAndHold(false);

    // Only start press-and-hold timer in recording state
    if (state.status === 'recording') {
      console.log(`[MicButton] Starting press-and-hold timer (${PRESS_HOLD_DELAY}ms)`);
      // Start timer for press-and-hold detection
      pressTimer.current = window.setTimeout(() => {
        setIsPressAndHold(true);
        console.log('[MicButton] Press-and-hold ACTIVATED');

        if (onPressAndHoldStart) {
          console.log('[MicButton] Calling onPressAndHoldStart callback');
          onPressAndHoldStart();
        }
      }, PRESS_HOLD_DELAY);
    } else {
      console.log('[MicButton] Not in recording state, skipping press-and-hold detection');
    }
  };

  const handlePressEnd = (e: React.MouseEvent | React.TouchEvent) => {
    const pressDuration = Date.now() - pressStartTime.current;
    console.log(
      '[MicButton] Press end event:',
      e.type,
      'Duration:',
      pressDuration,
      'ms',
      'isPressAndHold:',
      isPressAndHold
    );

    if (e.type === 'touchend') {
      e.preventDefault();
    }

    // Clear the timer
    if (pressTimer.current) {
      clearTimeout(pressTimer.current);
      pressTimer.current = null;
    }

    // If press-and-hold was active, end it
    if (isPressAndHold && onPressAndHoldEnd) {
      console.log('[MicButton] Ending press-and-hold, calling onPressAndHoldEnd callback');
      onPressAndHoldEnd();
      // Reset immediately
      setIsPressAndHold(false);
    } else if (
      pressStartTime.current > 0 &&
      pressDuration >= MIN_CLICK_TIME &&
      pressDuration < PRESS_HOLD_DELAY
    ) {
      // Normal click: between MIN_CLICK_TIME and PRESS_HOLD_DELAY
      console.log('[MicButton] Normal click detected, calling handleClick()');
      handleClick();
    }

    // Reset for next press
    setIsPressAndHold(false);
    pressStartTime.current = 0;
  };

  const getStatusText = (status: AppStatus): string => {
    switch (status) {
      case 'disconnected':
        return t('mobile.status.disconnected', 'Press to start');
      case 'connecting':
        return t('mobile.status.connecting', 'Connecting...');
      case 'recording':
        return t('mobile.status.recording', 'Listening...');
      case 'processing':
        return t('mobile.status.processing', 'Processing...');
      case 'speaking':
        return t('mobile.status.speaking_interruptible', 'Press to interrupt');
      case 'tool_wait':
        return t('mobile.status.tool_wait', 'Executing command...');
      case 'error':
        return t('mobile.status.error', 'Error');
      default:
        return status;
    }
  };

  const getButtonContent = () => {
    switch (state.status) {
      case 'connecting':
      case 'processing':
      case 'tool_wait':
        return <div className="spinner"></div>;

      case 'recording':
        return (
          <div className="sound-wave">
            <span className="sound-bar"></span>
            <span className="sound-bar"></span>
            <span className="sound-bar"></span>
            <span className="sound-bar"></span>
            <span className="sound-bar"></span>
          </div>
        );

      case 'speaking':
        return (
          <svg viewBox="0 0 24 24" fill="currentColor">
            <rect x="6" y="6" width="12" height="12" rx="2" />
          </svg>
        );

      default:
        return (
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z" />
            <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z" />
          </svg>
        );
    }
  };

  return (
    <div className="control-panel">
      <button
        className={`mic-button ${state.status} ${isPressAndHold ? 'press-and-hold' : ''}`}
        disabled={state.status === 'connecting'}
        onMouseDown={handlePressStart}
        onMouseUp={handlePressEnd}
        onTouchStart={handlePressStart}
        onTouchEnd={handlePressEnd}
      >
        {getButtonContent()}
      </button>
      <div className={`status-text ${state.status === 'error' ? 'error' : ''}`}>
        {getStatusText(state.status)}
      </div>
    </div>
  );
}
