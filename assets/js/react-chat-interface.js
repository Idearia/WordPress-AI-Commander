/**
 * React Chat Interface
 *
 * Implements a simple conversational interface using React.
 */

(function ($) {
    'use strict';

    // Access React and ReactDOM through wp.element
    function initChatInterface() {
        if (typeof wp === 'undefined' || typeof wp.element === 'undefined') {
            console.error('wp.element not loaded');
            return;
        }

        if (typeof wp.i18n === 'undefined') {
            console.error('wp.i18n not loaded');
            return;
        }

        const { useState, useEffect, useRef, createElement: e } = wp.element;
        const { __, sprintf } = wp.i18n;

        /**
         * ToolCallDetailsPopup Component
         * 
         * Displays a popup with the full details of a tool call.
         */
        const ToolCallDetailsPopup = ({ action, onClose }) => {
            if (!action) return null;

            return e(
                'div',
                { className: 'ai-commander-popup-overlay' },
                e(
                    'div',
                    { className: 'ai-commander-popup-content' },
                    e('div', { className: 'ai-commander-popup-header' },
                        e('h3', null, sprintf(__('Tool: %s', 'ai-commander'), action.tool)),
                        e('button', {
                            className: 'ai-commander-popup-close',
                            onClick: onClose
                        }, '×')
                    ),
                    e('div', { className: 'ai-commander-popup-body' },
                        e('h4', null, __('Tool call ID:', 'ai-commander')),
                        e('pre', null, action.tool_call_id),
                        e('h4', null, __('Arguments:', 'ai-commander')),
                        e('pre', null, JSON.stringify(action.arguments, null, 2)),
                        e('h4', null, __('Result:', 'ai-commander')),
                        e('pre', null, JSON.stringify(action.result, null, 2))
                    )
                )
            );
        };

        /**
         * ToolCallMessage Component
         * 
         * Renders a tool call as a message in the chat.
         */
        const ToolCallMessage = ({ action }) => {
            const [showDetails, setShowDetails] = useState(false);

            const toggleDetails = () => {
                setShowDetails(!showDetails);
            };

            // Function to handle action button clicks
            const handleActionButtonClick = (button, event) => {
                const buttonElement = event.target;

                switch (button.type) {
                    case 'link':
                        // Open link in new tab
                        window.open(button.url, button.target || '_blank');
                        break;

                    case 'modal':
                        // Create modal with HTML content
                        const modalContent = button.content || '';

                        // Create and append modal to the DOM
                        const modalOverlay = document.createElement('div');
                        modalOverlay.className = 'ai-commander-popup-overlay';

                        modalOverlay.innerHTML = `
                    <div class="ai-commander-popup-content">
                        <div class="ai-commander-popup-header">
                            <h3>${button.title || __('Details', 'ai-commander')}</h3>
                            <button class="ai-commander-popup-close">×</button>
                        </div>
                        <div class="ai-commander-popup-body">
                            ${modalContent}
                        </div>
                    </div>
                `;

                        // Add close button functionality
                        modalOverlay.querySelector('.ai-commander-popup-close').addEventListener('click', () => {
                            document.body.removeChild(modalOverlay);
                        });

                        // Add click outside to close
                        modalOverlay.addEventListener('click', (e) => {
                            if (e.target === modalOverlay) {
                                document.body.removeChild(modalOverlay);
                            }
                        });

                        // Append to body
                        document.body.appendChild(modalOverlay);
                        break;

                    case 'ajax':
                        // Disable the button and show spinner
                        const originalText = buttonElement.textContent;
                        buttonElement.disabled = true;
                        buttonElement.innerHTML = '<span class="ai-commander-spinner"></span> ' + (button.loadingText || __('Processing...', 'ai-commander'));

                        // Confirm if needed
                        if (button.confirmMessage && !window.confirm(button.confirmMessage)) {
                            // Re-enable button if user cancels
                            buttonElement.disabled = false;
                            buttonElement.textContent = originalText;
                            return;
                        }

                        // Send AJAX request
                        $.ajax({
                            url: button.url,
                            method: button.method || 'POST',
                            data: button.data || {},
                            success: function (response) {
                                // Re-enable button
                                buttonElement.disabled = false;
                                buttonElement.textContent = originalText;

                                // Handle success
                                handleAjaxResponse(response, button);
                            },
                            error: function (xhr, status, error) {
                                // Re-enable button
                                buttonElement.disabled = false;
                                buttonElement.textContent = originalText;

                                // Show error message
                                alert(__('Error: ', 'ai-commander') + (xhr.responseJSON?.message || error || __('Unknown error', 'ai-commander')));
                                console.error('AJAX error:', error);
                            }
                        });
                        break;

                    default:
                        console.warn('Unknown action button type:', button.type);
                }
            };

            // Function to handle AJAX responses
            const handleAjaxResponse = (response, button) => {
                // Check if response is successful
                if (!response.success) {
                    alert(__('Error: ', 'ai-commander') + (response.data?.message || __('Unknown error', 'ai-commander')));
                    return;
                }

                // Handle different response actions based on button configuration
                switch (button.responseAction) {
                    case 'refresh':
                        // Refresh the current page
                        window.location.reload();
                        break;

                    case 'redirect':
                        // Redirect to a URL from the response or button config
                        // with target=_blank
                        const redirectUrl = response.data?.redirect_url || button.redirectUrl;
                        const redirectTarget = response.data?.redirectTarget || button.redirectTarget || '_self';
                        if (redirectUrl) {
                            window.open(redirectUrl, redirectTarget);
                        }
                        break;

                    case 'message':
                        // Display a success message
                        const message = response.data?.message || button.successMessage || __('Operation completed successfully', 'ai-commander');
                        alert(message);
                        break;

                    case 'update':
                        // Update specific elements on the page
                        if (response.data?.updates) {
                            for (const [selector, content] of Object.entries(response.data.updates)) {
                                const element = document.querySelector(selector);
                                if (element) {
                                    element.innerHTML = content;
                                }
                            }
                        }
                        break;

                    case 'modal':
                        // Show response in a modal
                        const modalContent = response.data?.content || JSON.stringify(response.data);
                        const modalTitle = response.data?.title || button.modalTitle || __('Response', 'ai-commander');

                        // Create modal with the response content
                        const modalOverlay = document.createElement('div');
                        modalOverlay.className = 'ai-commander-popup-overlay';

                        modalOverlay.innerHTML = `
                    <div class="ai-commander-popup-content">
                        <div class="ai-commander-popup-header">
                            <h3>${modalTitle}</h3>
                            <button class="ai-commander-popup-close">×</button>
                        </div>
                        <div class="ai-commander-popup-body">
                            ${modalContent}
                        </div>
                    </div>
                `;

                        // Add close button functionality
                        modalOverlay.querySelector('.ai-commander-popup-close').addEventListener('click', () => {
                            document.body.removeChild(modalOverlay);
                        });

                        // Add click outside to close
                        modalOverlay.addEventListener('click', (e) => {
                            if (e.target === modalOverlay) {
                                document.body.removeChild(modalOverlay);
                            }
                        });

                        // Append to body
                        document.body.appendChild(modalOverlay);
                        break;

                    case 'custom':
                        // Execute a custom callback if defined
                        if (typeof window[button.callback] === 'function') {
                            window[button.callback](response, button);
                        }
                        break;

                    default:
                        // If no specific action is defined, check for common response patterns
                        if (response.data?.message) {
                            alert(response.data.message);
                        } else if (response.data?.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        }
                        break;
                }
            };

            return e(
                'div',
                { className: 'ai-commander-message assistant ai-commander-tool-call' },
                e('div',
                    { className: 'ai-commander-message-content' },
                    e('div', { className: 'ai-commander-tool-title' }, action.title),
                    e('div', {
                        className: 'ai-commander-tool-summary',
                        dangerouslySetInnerHTML: { __html: action.summary }
                    }),
                    e('div', { className: 'ai-commander-tool-actions' },
                        // Render custom action buttons if available
                        action.action_buttons && action.action_buttons.map((button, index) =>
                            e('button', {
                                key: index,
                                className: 'ai-commander-tool-action-button',
                                onClick: (event) => handleActionButtonClick(button, event)
                            }, button.label)
                        ),
                        // Always include the debug button
                        e('button', {
                            className: 'ai-commander-tool-details-button',
                            onClick: toggleDetails
                        }, __('Debug', 'ai-commander'))
                    )
                ),
                showDetails && e(ToolCallDetailsPopup, {
                    action: action,
                    onClose: toggleDetails
                })
            );
        };

        /**
         * MessageItem Component
         * 
         * Renders a single message in the chat.
         */
        const MessageItem = ({ message }) => {
            const { role, content, isToolCall, action } = message;

            // If this is a tool call message, render the ToolCallMessage component
            if (isToolCall && action) {
                return e(ToolCallMessage, { action: action });
            }

            // Function to preserve line breaks in text
            const formatContent = (text) => {
                if (!text) return '';

                // Split by newlines and create an array of text elements
                return text.split('\n').map((line, i) =>
                    e('div', { key: i, className: 'ai-commander-message-line' }, line)
                );
            };

            return e(
                'div',
                { className: `ai-commander-message ${role}` },
                e('div',
                    { className: 'ai-commander-message-content' },
                    formatContent(content)
                )
            );
        };

        /**
         * MessageList Component
         * 
         * Displays a list of messages.
         */
        const MessageList = ({ messages }) => {
            const messagesEndRef = useRef(null);
            const messageListRef = useRef(null);
            const prevMessagesLengthRef = useRef(0);

            // Improved scroll behavior to avoid excessive scrolling
            useEffect(() => {
                if (!messagesEndRef.current || !messageListRef.current) return;

                const container = messageListRef.current;
                const isScrolledToBottom = container.scrollHeight - container.clientHeight <= container.scrollTop + 50;
                const hasNewMessages = messages.length > prevMessagesLengthRef.current;

                // Only auto-scroll if we're already near the bottom or if there are new messages
                if (isScrolledToBottom || hasNewMessages) {
                    // Use a small timeout to ensure the DOM has updated
                    setTimeout(() => {
                        messagesEndRef.current.scrollIntoView({
                            behavior: 'smooth',
                            block: 'nearest' // Only scroll the minimum amount needed
                        });
                    }, 10);
                }

                prevMessagesLengthRef.current = messages.length;
            }, [messages]);

            return e(
                'div',
                {
                    className: 'ai-commander-message-list',
                    ref: messageListRef
                },
                messages.map((message, index) =>
                    e(MessageItem, { key: index, message: message })
                ),
                e('div', { ref: messagesEndRef })
            );
        };

        /**
         * MicrophoneIcon Component
         * 
         * SVG icon for the microphone button.
         */
        const MicrophoneIcon = () => {
            return e(
                'svg',
                {
                    className: 'ai-commander-mic-icon',
                    xmlns: 'http://www.w3.org/2000/svg',
                    viewBox: '0 0 24 24',
                    fill: 'currentColor'
                },
                e('path', {
                    d: 'M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z'
                }),
                e('path', {
                    d: 'M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z'
                })
            );
        };

        /**
         * RecordingStatus Component
         * 
         * Displays a recording status indicator.
         */
        const RecordingStatus = () => {
            return e(
                'div',
                { className: 'ai-commander-recording-status' },
                e('div', { className: 'ai-commander-recording-status-dot' }),
                __('Recording...', 'ai-commander')
            );
        };

        /**
         * InputArea Component
         * 
         * Provides an input area for the user to type messages.
         */
        const InputArea = ({ onSendMessage, isProcessing }) => {
            const [inputValue, setInputValue] = useState('');
            const [isRecording, setIsRecording] = useState(false);
            const [isSpeechEnabled, setIsSpeechEnabled] = useState(true);
            const textareaRef = useRef(null);
            const mediaRecorderRef = useRef(null);
            const audioChunksRef = useRef([]);

            const handleInputChange = (e) => {
                setInputValue(e.target.value);
            };

            const handleKeyDown = (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    handleSendMessage();
                }
            };

            const handleSendMessage = () => {
                const message = inputValue.trim();
                if (message && !isProcessing) {
                    onSendMessage(message);
                    setInputValue('');

                    // Focus the textarea after sending
                    if (textareaRef.current) {
                        textareaRef.current.focus();
                    }
                }
            };

            // Check if the browser supports the MediaRecorder API
            const checkMediaRecorderSupport = () => {
                // Check if we're on HTTPS or localhost
                const isSecureContext = window.isSecureContext ||
                    window.location.protocol === 'https:' ||
                    window.location.hostname === 'localhost' ||
                    window.location.hostname === '127.0.0.1';

                if (!isSecureContext) {
                    alert(__('Microphone access requires a secure connection (HTTPS). Please contact your administrator to enable HTTPS for this site.', 'ai-commander'));
                    return false;
                }

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert(__('Your browser does not support audio recording. Please use a modern browser like Chrome, Edge, or Firefox.', 'ai-commander'));
                    return false;
                }

                if (typeof MediaRecorder === 'undefined') {
                    alert(__('Your browser does not support the MediaRecorder API. Please use a modern browser like Chrome, Edge, or Firefox.', 'ai-commander'));
                    return false;
                }

                return true;
            };

            // Check for microphone support on component mount
            useEffect(() => {
                if (isSpeechEnabled) {
                    const isSupported = checkMediaRecorderSupport();
                    if (!isSupported) {
                        setIsSpeechEnabled(false);
                    }
                }
            }, []);

            const startRecording = async () => {
                // Check if the browser supports recording
                if (!checkMediaRecorderSupport()) {
                    return;
                }

                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    mediaRecorderRef.current = new MediaRecorder(stream);
                    audioChunksRef.current = [];

                    mediaRecorderRef.current.ondataavailable = (event) => {
                        if (event.data.size > 0) {
                            audioChunksRef.current.push(event.data);
                        }
                    };

                    mediaRecorderRef.current.onstop = () => {
                        const audioBlob = new Blob(audioChunksRef.current, { type: 'audio/webm' });
                        sendAudioToServer(audioBlob);

                        // Stop all audio tracks
                        stream.getTracks().forEach(track => track.stop());
                    };

                    mediaRecorderRef.current.start();
                    setIsRecording(true);
                } catch (error) {
                    console.error('Error accessing microphone:', error);

                    // Provide more specific error messages
                    if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
                        alert(__('Microphone access was denied. Please try again but this time allow microphone access.\n\nIn alternative, in most browsers, you can click on the camera/microphone icon in the address bar to change permissions.', 'ai-commander'));
                    } else if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
                        alert(__('No microphone was found. Please connect a microphone and try again.', 'ai-commander'));
                    } else if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
                        alert(__('Your microphone is busy or not available. Please close other applications that might be using your microphone.', 'ai-commander'));
                    } else {
                        alert(__('Could not access your microphone: ', 'ai-commander') + error.message);
                    }
                }
            };

            const stopRecording = () => {
                if (mediaRecorderRef.current && isRecording) {
                    mediaRecorderRef.current.stop();
                    setIsRecording(false);
                }
            };

            const toggleRecording = () => {
                if (isRecording) {
                    stopRecording();
                } else {
                    startRecording();
                }
            };

            // State for transcription loading
            const [isTranscribing, setIsTranscribing] = useState(false);

            const sendAudioToServer = (audioBlob) => {
                const formData = new FormData();
                formData.append('audio', audioBlob, 'recording.webm');
                formData.append('action', 'ai_commander_transcribe_audio');
                formData.append('nonce', aiCommanderData.nonce);

                // Show transcription loading state
                setIsTranscribing(true);

                $.ajax({
                    url: aiCommanderData.ajax_url,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        setIsTranscribing(false);

                        if (response.success && response.data.transcription) {
                            setInputValue(response.data.transcription);

                            // Automatically send the message if it's not empty
                            if (response.data.transcription.trim()) {
                                onSendMessage(response.data.transcription.trim());
                            }
                        } else {
                            console.error('Transcription error:', response.data ? response.data.message : 'Unknown error');
                            alert(__('Error transcribing audio: ', 'ai-commander') + (response.data ? response.data.message : __('Unknown error', 'ai-commander')));
                        }
                    },
                    error: function (xhr, status, error) {
                        setIsTranscribing(false);
                        console.error('AJAX error:', error);
                        alert(__('Error sending audio: ', 'ai-commander') + error);
                    }
                });
            };

            return e(
                'div',
                { className: 'ai-commander-input-container' },
                e('textarea', {
                    className: 'ai-commander-message-input',
                    placeholder: __('Type your command here...', 'ai-commander'),
                    value: inputValue,
                    onChange: handleInputChange,
                    onKeyDown: handleKeyDown,
                    disabled: isProcessing || isTranscribing,
                    ref: textareaRef
                }),
                isSpeechEnabled && e(
                    'button',
                    {
                        className: `ai-commander-mic-button ${isRecording ? 'recording' : ''} ${isTranscribing ? 'transcribing' : ''}`,
                        onClick: toggleRecording,
                        disabled: isProcessing || isTranscribing,
                        title: isRecording ? __('Stop recording', 'ai-commander') : (isTranscribing ? __('Transcribing...', 'ai-commander') : __('Start recording', 'ai-commander')),
                        type: 'button'
                    },
                    e(MicrophoneIcon)
                ),
                e(
                    'button',
                    {
                        className: 'ai-commander-send-button',
                        onClick: handleSendMessage,
                        disabled: isProcessing || isTranscribing || !inputValue.trim(),
                        type: 'button'
                    },
                    __('Send', 'ai-commander')
                ),
                isRecording && e(RecordingStatus),
                isTranscribing && e(
                    'div',
                    { className: 'ai-commander-transcribing-status' },
                    __('Transcribing audio...', 'ai-commander')
                )
            );
        };

        /**
         * ActionResults Component
         * 
         * This component is no longer used to display actions in the UI,
         * but we're keeping it for backward compatibility.
         */
        const ActionResults = ({ actions }) => {
            // Return null as we're now displaying actions as messages
            return null;
        };

        /**
         * ChatInterface Component
         * 
         * The main chat interface component.
         */
        const ChatInterface = ({ config }) => {
            const [messages, setMessages] = useState([]);
            const [actions, setActions] = useState([]);
            const [isProcessing, setIsProcessing] = useState(false);
            const [conversationId, setConversationId] = useState(null);

            // Function to create a new conversation
            const startNewConversation = () => {
                setIsProcessing(true);

                $.ajax({
                    url: config.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'ai_commander_create_conversation',
                        nonce: config.nonce
                    },
                    success: function (response) {
                        setIsProcessing(false);

                        if (response.success) {
                            // Set the new conversation ID
                            const newConversationId = response.data.conversation_uuid;
                            setConversationId(newConversationId);

                            // Save the conversation ID to localStorage
                            localStorage.setItem('wp_ai_commander_conversation_id', newConversationId);

                            // Set the initial messages
                            setMessages(response.data.messages || []);

                            // Clear any actions
                            setActions([]);
                        } else {
                            // Handle error
                            setMessages([
                                { role: 'assistant', content: sprintf(__('Error: %s', 'ai-commander'), response.data.message || __('Failed to create conversation', 'ai-commander')) }
                            ]);
                        }
                    },
                    error: function (xhr, status, error) {
                        setIsProcessing(false);

                        // Handle error
                        setMessages([
                            { role: 'assistant', content: sprintf(__('Error: %s', 'ai-commander'), error || __('Failed to create conversation', 'ai-commander')) }
                        ]);
                    }
                });
            };

            // Function to load an existing conversation
            const loadConversation = (conversationUuid) => {
                setIsProcessing(true);

                $.ajax({
                    url: config.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'ai_commander_get_conversation',
                        nonce: config.nonce,
                        conversation_uuid: conversationUuid
                    },
                    success: function (response) {
                        setIsProcessing(false);

                        if (response.success) {
                            // Set the conversation ID
                            setConversationId(response.data.conversation_uuid);

                            // Set the messages
                            setMessages(response.data.messages_for_frontend || []);

                            // Clear any actions
                            setActions([]);
                        } else {
                            // If there's an error loading the conversation, create a new one
                            console.error('Failed to load conversation:', response.data.message);
                            localStorage.removeItem('wp_ai_commander_conversation_id');
                            startNewConversation();
                        }
                    },
                    error: function (xhr, status, error) {
                        setIsProcessing(false);
                        console.error('Error loading conversation:', error);

                        // If there's an error, create a new conversation
                        localStorage.removeItem('wp_ai_commander_conversation_id');
                        startNewConversation();
                    }
                });
            };

            // When component mounts, check for existing conversation ID
            useEffect(() => {
                const savedConversationId = localStorage.getItem('wp_ai_commander_conversation_id');

                if (savedConversationId) {
                    // Load the existing conversation
                    loadConversation(savedConversationId);
                } else {
                    // Create a new conversation
                    startNewConversation();
                }
            }, []);

            const handleSendMessage = (message) => {
                // Add user message to the chat
                setMessages(prevMessages => [
                    ...prevMessages,
                    { role: 'user', content: message }
                ]);

                // Clear previous actions
                setActions([]);

                // Set processing state
                setIsProcessing(true);

                // Send the message to the server
                $.ajax({
                    url: config.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'ai_commander_process_command',
                        nonce: config.nonce,
                        command: message,
                        conversation_uuid: conversationId
                    },
                    success: function (response) {
                        if (response.success) {
                            // Update conversation ID if it changed
                            if (response.data.conversation_uuid) {
                                setConversationId(response.data.conversation_uuid);
                            }

                            // Instead of processing messages here, refetch the entire conversation
                            // This ensures server-side filtering is consistently applied
                            refetchConversation(response.data.conversation_uuid || conversationId);
                        } else {
                            setIsProcessing(false);

                            // Add error message to the chat
                            setMessages(prevMessages => [
                                ...prevMessages,
                                { role: 'assistant', content: sprintf(__('Error: %s', 'ai-commander'), response.data.message || __('Unknown error', 'ai-commander')) }
                            ]);
                        }
                    },
                    error: function (xhr, status, error) {
                        setIsProcessing(false);

                        // Add error message to the chat
                        setMessages(prevMessages => [
                            ...prevMessages,
                            { role: 'assistant', content: sprintf(__('Error: %s', 'ai-commander'), error || __('Failed to process command', 'ai-commander')) }
                        ]);
                    }
                });
            };

            // Function to refetch the entire conversation
            const refetchConversation = (convId) => {
                // Note: We don't set isProcessing to true here because it's already true
                // from the handleSendMessage function

                $.ajax({
                    url: config.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'ai_commander_get_conversation',
                        nonce: config.nonce,
                        conversation_uuid: convId
                    },
                    success: function (response) {
                        // Now we can set isProcessing to false after refetching
                        setIsProcessing(false);

                        if (response.success) {
                            // Replace all messages with the freshly filtered messages from the server
                            setMessages(response.data.messages_for_frontend || []);

                            // Update actions if needed
                            if (response.data.actions) {
                                setActions(response.data.actions);
                            } else {
                                setActions([]);
                            }
                        } else {
                            console.error('Failed to refetch conversation:', response.data.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        setIsProcessing(false);
                        console.error('Error refetching conversation:', error);
                    }
                });
            };

            return e(
                'div',
                { className: 'ai-commander-chat-container' },
                e(
                    'div',
                    { className: 'ai-commander-chat-header' },
                    e('h3', null, __('WordPress Assistant', 'ai-commander')),
                    e(
                        'button',
                        {
                            className: 'ai-commander-new-conversation-button',
                            onClick: startNewConversation,
                            disabled: isProcessing
                        },
                        __('New Conversation', 'ai-commander')
                    )
                ),
                e(MessageList, { messages: messages }),
                e(ActionResults, { actions: actions }),
                e(InputArea, {
                    onSendMessage: handleSendMessage,
                    isProcessing: isProcessing
                }),
                isProcessing && e(
                    'div',
                    { className: 'ai-commander-loading' },
                    e('span', { className: 'spinner is-active' }),
                    __('Processing your command...', 'ai-commander')
                )
            );
        };

        /**
         * Initialize the chat interface.
         */
        function init() {
            const container = document.getElementById('ai-commander-chat-interface');
            if (!container) {
                console.error('Chat interface container not found');
                return;
            }

            // Get configuration from global variable
            const config = {
                ajaxUrl: aiCommanderData.ajax_url,
                nonce: aiCommanderData.nonce,
            };

            // Render the chat interface
            wp.element.render(
                e(ChatInterface, { config: config }),
                container
            );
        }

        // Initialize when the document is ready
        $(document).ready(init);
    }

    // Check if wp.element is loaded
    if (typeof wp === 'undefined' || typeof wp.element === 'undefined') {
        console.error('wp.element not loaded. Make sure wp-element is enqueued properly.');
        return;
    }

    // Initialize the chat interface
    initChatInterface();

})(jQuery);
