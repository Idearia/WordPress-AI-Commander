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
            
            // Auto-scroll to bottom when messages change
            useEffect(() => {
                if (messagesEndRef.current) {
                    messagesEndRef.current.scrollIntoView({ behavior: 'smooth' });
                }
            }, [messages]);
            
            return e(
                'div',
                { className: 'wp-nlc-message-list' },
                messages.map((message, index) => 
                    e(MessageItem, { key: index, message: message })
                ),
                e('div', { ref: messagesEndRef })
            );
        };

        /**
         * InputArea Component
         * 
         * Provides an input area for the user to type messages.
         */
        const InputArea = ({ onSendMessage, isProcessing }) => {
            const [inputValue, setInputValue] = useState('');
            const textareaRef = useRef(null);
            
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
            
            return e(
                'div',
                { className: 'wp-nlc-input-container' },
                e('textarea', {
                    className: 'wp-nlc-message-input',
                    placeholder: 'Type your command here...',
                    value: inputValue,
                    onChange: handleInputChange,
                    onKeyDown: handleKeyDown,
                    disabled: isProcessing,
                    ref: textareaRef
                }),
                e(
                    'button',
                    {
                        className: 'wp-nlc-send-button',
                        onClick: handleSendMessage,
                        disabled: isProcessing || !inputValue.trim()
                    },
                    'Send'
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
            const [messages, setMessages] = useState([
                { role: 'assistant', content: 'Hello! I\'m your WordPress assistant. How can I help you today?' }
            ]);
            const [actions, setActions] = useState([]);
            const [isProcessing, setIsProcessing] = useState(false);
            
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
                        command: message
                    },
                    success: function(response) {
                        setIsProcessing(false);
                        
                        if (response.success) {
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
                model: wpNlcData.model
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
