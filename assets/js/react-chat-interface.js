/**
 * React Chat Interface
 *
 * Implements a simple conversational interface using React.
 */

(function($) {
    'use strict';

    // Access React and ReactDOM through wp.element
    function initChatInterface() {
        if (typeof wp === 'undefined' || typeof wp.element === 'undefined') {
            console.error('wp.element not loaded');
            return;
        }

        const { useState, useEffect, useRef, createElement: e } = wp.element;

/**
 * ToolCallDetailsPopup Component
 * 
 * Displays a popup with the full details of a tool call.
 */
const ToolCallDetailsPopup = ({ action, onClose }) => {
    if (!action) return null;
    
    return e(
        'div',
        { className: 'wp-nlc-popup-overlay' },
        e(
            'div',
            { className: 'wp-nlc-popup-content' },
            e('div', { className: 'wp-nlc-popup-header' }, 
                e('h3', null, `Tool: ${action.tool}`),
                e('button', { 
                    className: 'wp-nlc-popup-close',
                    onClick: onClose
                }, '×')
            ),
            e('div', { className: 'wp-nlc-popup-body' },
                e('h4', null, 'Tool call ID:'),
                e('pre', null, action.tool_call_id),
                e('h4', null, 'Arguments:'),
                e('pre', null, JSON.stringify(action.arguments, null, 2)),
                e('h4', null, 'Result:'),
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
    
    return e(
        'div',
        { className: 'wp-nlc-message assistant wp-nlc-tool-call' },
        e('div', 
            { className: 'wp-nlc-message-content' },
            e('div', { className: 'wp-nlc-tool-title' }, action.title),
            e('div', { className: 'wp-nlc-tool-summary' }, action.summary),
            e('button', {
                className: 'wp-nlc-tool-details-button',
                onClick: toggleDetails
            }, 'View Details')
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
    
    // Simple function to preserve line breaks in text
    const formatContent = (text) => {
        if (!text) return '';
        
        // Split by newlines and create an array of text elements
        return text.split('\n').map((line, i) => 
            e('div', { key: i, className: 'wp-nlc-message-line' }, line)
        );
    };
    
    return e(
        'div',
        { className: `wp-nlc-message ${role}` },
        e('div', 
            { className: 'wp-nlc-message-content' },
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
                    className: 'wp-nlc-message-list',
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
                    className: 'wp-nlc-mic-icon',
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
                { className: 'wp-nlc-recording-status' },
                e('div', { className: 'wp-nlc-recording-status-dot' }),
                'Recording...'
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
            
            // Check if speech-to-text is enabled in settings
            useEffect(() => {
                // This value is set in the localized script data
                if (typeof wpNlcData !== 'undefined' && wpNlcData.enable_speech_to_text !== undefined) {
                    setIsSpeechEnabled(wpNlcData.enable_speech_to_text === '1');
                }
            }, []);
            
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
                    alert('Microphone access requires a secure connection (HTTPS). Please contact your administrator to enable HTTPS for this site.');
                    return false;
                }

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert('Your browser does not support audio recording. Please use a modern browser like Chrome, Edge, or Firefox.');
                    return false;
                }
                
                if (typeof MediaRecorder === 'undefined') {
                    alert('Your browser does not support the MediaRecorder API. Please use a modern browser like Chrome, Edge, or Firefox.');
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
                        alert('Microphone access was denied. Please allow microphone access in your browser settings and try again.\n\n' +
                              'In most browsers, you can click on the camera/microphone icon in the address bar to change permissions.');
                    } else if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
                        alert('No microphone was found. Please connect a microphone and try again.');
                    } else if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
                        alert('Your microphone is busy or not available. Please close other applications that might be using your microphone.');
                    } else {
                        alert('Could not access your microphone: ' + error.message);
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
                formData.append('action', 'wp_nlc_transcribe_audio');
                formData.append('nonce', wpNlcData.nonce);
                
                // Show transcription loading state
                setIsTranscribing(true);
                
                $.ajax({
                    url: wpNlcData.ajax_url,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        setIsTranscribing(false);
                        
                        if (response.success && response.data.transcription) {
                            setInputValue(response.data.transcription);
                            
                            // Automatically send the message if it's not empty
                            if (response.data.transcription.trim()) {
                                onSendMessage(response.data.transcription.trim());
                            }
                        } else {
                            console.error('Transcription error:', response.data ? response.data.message : 'Unknown error');
                            alert('Error transcribing audio: ' + (response.data ? response.data.message : 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        setIsTranscribing(false);
                        console.error('AJAX error:', error);
                        alert('Error sending audio: ' + error);
                    }
                });
            };
            
            return e(
                'div',
                { className: 'wp-nlc-input-container' },
                e('textarea', {
                    className: 'wp-nlc-message-input',
                    placeholder: 'Type your command here...',
                    value: inputValue,
                    onChange: handleInputChange,
                    onKeyDown: handleKeyDown,
                    disabled: isProcessing || isTranscribing,
                    ref: textareaRef
                }),
                isSpeechEnabled && e(
                    'button',
                    {
                        className: `wp-nlc-mic-button ${isRecording ? 'recording' : ''} ${isTranscribing ? 'transcribing' : ''}`,
                        onClick: toggleRecording,
                        disabled: isProcessing || isTranscribing,
                        title: isRecording ? 'Stop recording' : (isTranscribing ? 'Transcribing...' : 'Start recording'),
                        type: 'button'
                    },
                    e(MicrophoneIcon)
                ),
                e(
                    'button',
                    {
                        className: 'wp-nlc-send-button',
                        onClick: handleSendMessage,
                        disabled: isProcessing || isTranscribing || !inputValue.trim(),
                        type: 'button'
                    },
                    'Send'
                ),
                isRecording && e(RecordingStatus),
                isTranscribing && e(
                    'div',
                    { className: 'wp-nlc-transcribing-status' },
                    'Transcribing audio...'
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
                        action: 'wp_nlc_create_conversation',
                        nonce: config.nonce
                    },
                    success: function(response) {
                        setIsProcessing(false);
                        
                        if (response.success) {
                            // Set the new conversation ID
                            const newConversationId = response.data.conversation_uuid;
                            setConversationId(newConversationId);
                            
                            // Save the conversation ID to localStorage
                            localStorage.setItem('wp_nlc_conversation_id', newConversationId);
                            
                            // Set the initial messages
                            setMessages(response.data.messages || []);
                            
                            // Clear any actions
                            setActions([]);
                        } else {
                            // Handle error
                            setMessages([
                                { role: 'assistant', content: `Error: ${response.data.message || 'Failed to create conversation'}` }
                            ]);
                        }
                    },
                    error: function(xhr, status, error) {
                        setIsProcessing(false);
                        
                        // Handle error
                        setMessages([
                            { role: 'assistant', content: `Error: ${error || 'Failed to create conversation'}` }
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
                        action: 'wp_nlc_get_conversation',
                        nonce: config.nonce,
                        conversation_uuid: conversationUuid
                    },
                    success: function(response) {
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
                            localStorage.removeItem('wp_nlc_conversation_id');
                            startNewConversation();
                        }
                    },
                    error: function(xhr, status, error) {
                        setIsProcessing(false);
                        console.error('Error loading conversation:', error);
                        
                        // If there's an error, create a new conversation
                        localStorage.removeItem('wp_nlc_conversation_id');
                        startNewConversation();
                    }
                });
            };
            
            // When component mounts, check for existing conversation ID
            useEffect(() => {
                const savedConversationId = localStorage.getItem('wp_nlc_conversation_id');
                
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
                        action: 'wp_nlc_process_command',
                        nonce: config.nonce,
                        command: message,
                        conversation_uuid: conversationId
                    },
                    success: function(response) {
                        setIsProcessing(false);
                        
                        if (response.success) {
                            // Update conversation ID if it changed
                            if (response.data.conversation_uuid) {
                                setConversationId(response.data.conversation_uuid);
                            }
                            
                            // Process the response message and actions
                            const messageContent = response.data.message;
                            const hasActions = response.data.actions && response.data.actions.length > 0;
                            
                            // Only add the assistant message if it has content or there are no actions
                            if ((messageContent && messageContent.trim() !== '') || !hasActions) {
                                setMessages(prevMessages => [
                                    ...prevMessages,
                                    { role: 'assistant', content: messageContent }
                                ]);
                            }
                            
                            // Set actions if any and add them as separate messages
                            if (hasActions) {
                                setActions(response.data.actions);
                                
                                // Add each action as a separate message
                                response.data.actions.forEach(action => {
                                    setMessages(prevMessages => [
                                        ...prevMessages,
                                        { 
                                            role: 'assistant', 
                                            isToolCall: true,
                                            action: action
                                        }
                                    ]);
                                });
                            }
                        } else {
                            // Add error message to the chat
                            setMessages(prevMessages => [
                                ...prevMessages,
                                { role: 'assistant', content: `Error: ${response.data.message || 'Unknown error'}` }
                            ]);
                        }
                    },
                    error: function(xhr, status, error) {
                        setIsProcessing(false);
                        
                        // Add error message to the chat
                        setMessages(prevMessages => [
                            ...prevMessages,
                            { role: 'assistant', content: `Error: ${error || 'Failed to process command'}` }
                        ]);
                    }
                });
            };
            
            return e(
                'div',
                { className: 'wp-nlc-chat-container' },
                e(
                    'div',
                    { className: 'wp-nlc-chat-header' },
                    e('h3', null, 'WordPress Assistant'),
                    e(
                        'button',
                        {
                            className: 'wp-nlc-new-conversation-button',
                            onClick: startNewConversation,
                            disabled: isProcessing
                        },
                        'New Conversation'
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
                    { className: 'wp-nlc-loading' },
                    e('span', { className: 'spinner is-active' }),
                    'Processing your command...'
                )
            );
        };

        /**
         * Initialize the chat interface.
         */
        function init() {
            const container = document.getElementById('wp-nlc-chat-interface');
            if (!container) {
                console.error('Chat interface container not found');
                return;
            }
            
            // Get configuration from global variable
            const config = {
                ajaxUrl: wpNlcData.ajax_url,
                nonce: wpNlcData.nonce,
                apiKey: wpNlcData.api_key,
                model: wpNlcData.model,
                enableSpeechToText: wpNlcData.enable_speech_to_text
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
