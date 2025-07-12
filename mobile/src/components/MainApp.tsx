import React, { useState } from 'react';
import { useTranslation } from '@/hooks/useTranslation';
import { useAppContext } from '@/context/AppContext';
import { ChatContainer } from './ChatContainer';
import { MicButton } from './MicButton';

interface MainAppProps {
  onStartRecording: () => void;
  onStopRecording: () => void;
  onInterruptTts: () => void;
  onPressAndHoldStart?: () => void;
  onPressAndHoldEnd?: () => void;
  onChangeConfig: () => void;
  onLogout: () => void;
}

export function MainApp({
  onStartRecording,
  onStopRecording,
  onInterruptTts,
  onPressAndHoldStart,
  onPressAndHoldEnd,
  onChangeConfig,
  onLogout
}: MainAppProps) {
  const { t } = useTranslation();
  const { state: _state } = useAppContext();
  const [isSettingsOpen, setIsSettingsOpen] = useState(false);

  const handleSettingsClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    setIsSettingsOpen(!isSettingsOpen);
  };

  const handleChangeConfig = () => {
    setIsSettingsOpen(false);
    onChangeConfig();
  };

  const handleLogout = () => {
    if (confirm(t('mobile.confirm.logout', 'Do you want to disconnect and delete saved credentials?'))) {
      setIsSettingsOpen(false);
      onLogout();
    }
  };

  // Close settings menu when clicking outside
  React.useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      const target = e.target as Element;
      if (!target.closest('.btn-settings') && !target.closest('.settings-menu')) {
        setIsSettingsOpen(false);
      }
    };

    document.addEventListener('click', handleClickOutside);
    return () => document.removeEventListener('click', handleClickOutside);
  }, []);

  return (
    <div className="app-container active">
      <header className="app-header">
        <div className="app-title">
          <div className="logo-small">IN</div>
          <span>{t('mobile.ui.assistant_name', 'INofficina.it Assistant')}</span>
        </div>
        <button className="btn-settings" onClick={handleSettingsClick}>
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 15.5A3.5 3.5 0 0 1 8.5 12A3.5 3.5 0 0 1 12 8.5a3.5 3.5 0 0 1 3.5 3.5a3.5 3.5 0 0 1-3.5 3.5m7.43-2.53c.04-.32.07-.64.07-.97c0-.33-.03-.66-.07-1l2.11-1.63c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.31-.61-.22l-2.49 1c-.52-.39-1.06-.73-1.69-.98l-.37-2.65A.506.506 0 0 0 14 2h-4c-.25 0-.46.18-.5.42l-.37 2.65c-.63.25-1.17.59-1.69.98l-2.49-1c-.22-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64L4.57 11c-.04.34-.07.67-.07 1c0 .33.03.65.07.97l-2.11 1.66c-.19.15-.25.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1.01c.52.4 1.06.74 1.69.99l.37 2.65c.04.24.25.42.5.42h4c.25 0 .46-.18.5-.42l.37-2.65c.63-.26 1.17-.59 1.69-.99l2.49 1.01c.22.08.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.66Z"/>
          </svg>
        </button>

        <div className={`settings-menu ${isSettingsOpen ? 'active' : ''}`}>
          <div className="settings-item" onClick={handleChangeConfig}>
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
            </svg>
            <span>{t('mobile.ui.change_config', 'Change configuration')}</span>
          </div>
          <div className="settings-item" onClick={handleLogout}>
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M16 17v-3H9v-4h7V7l5 5-5 5M14 2a2 2 0 0 1 2 2v2h-2V4H5v16h9v-2h2v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9z"/>
            </svg>
            <span>{t('mobile.ui.disconnect', 'Disconnect')}</span>
          </div>
        </div>
      </header>

      <ChatContainer />

      <MicButton
        onStartRecording={onStartRecording}
        onStopRecording={onStopRecording}
        onInterruptTts={onInterruptTts}
        onPressAndHoldStart={onPressAndHoldStart}
        onPressAndHoldEnd={onPressAndHoldEnd}
      />

      <audio style={{ display: 'none' }} autoPlay playsInline />
    </div>
  );
}