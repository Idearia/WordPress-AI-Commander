import React, { useEffect, useRef } from 'react';
import { useTranslation } from '@/hooks/useTranslation';
import { useAppContext } from '@/context/AppContext';
import { Message } from '@/types';

export function ChatContainer() {
  const { t } = useTranslation();
  const { state } = useAppContext();
  const containerRef = useRef<HTMLDivElement>(null);
  const typingIndicatorRef = useRef<HTMLDivElement | null>(null);

  // Auto-scroll to bottom when messages change
  useEffect(() => {
    if (containerRef.current) {
      containerRef.current.scrollTop = containerRef.current.scrollHeight;
    }
  }, [state.messages, state.currentTranscript]);

  const renderMessage = (message: Message, index: number) => (
    <div key={index} className={`chat-message ${message.type}`}>
      <div className="message-bubble">
        {message.content}
      </div>
    </div>
  );

  const renderTypingIndicator = () => {
    if (!state.currentTranscript) return null;

    return (
      <div className="chat-message assistant" ref={typingIndicatorRef}>
        <div className="message-bubble">
          {state.currentTranscript || (
            <>
              <span className="typing-dot"></span>
              <span className="typing-dot"></span>
              <span className="typing-dot"></span>
            </>
          )}
        </div>
      </div>
    );
  };

  const renderEmptyState = () => (
    <div className="empty-state">
      <h2>{t('mobile.ui.greeting', 'Hello! 👋')}</h2>
      <p>{t('mobile.ui.greeting_text', 'I am the voice assistant for INofficina.it. I can help you manage your workshop appointments.')}</p>
      <div className="suggestions">
        <div className="suggestion">
          {t('mobile.suggestion.customer_check', '💬 "Is license plate XX333TT our customer?"')}
        </div>
        <div className="suggestion">
          {t('mobile.suggestion.book_service', '📅 "Schedule a service for tomorrow"')}
        </div>
        <div className="suggestion">
          {t('mobile.suggestion.show_appointments', '🔍 "Show today\'s appointments"')}
        </div>
      </div>
    </div>
  );

  const hasContent = state.messages.length > 0 || state.currentTranscript;

  return (
    <div className="chat-container" ref={containerRef}>
      {!hasContent && renderEmptyState()}
      {state.messages.map(renderMessage)}
      {renderTypingIndicator()}
    </div>
  );
}