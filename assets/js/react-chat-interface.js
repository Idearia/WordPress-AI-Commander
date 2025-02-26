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
         * MessageItem Component
         * 
         * Renders a single message in the chat.
         */
        const MessageItem = ({ message }) => {
            const { role, content } = message;
            
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
         * Displays the results of actions performed by the chatbot.
         */
        const ActionResults = ({ actions }) => {
            if (!actions || actions.length === 0) {
                return null;
            }
            
            return e(
                'div',
                { className: 'wp-nlc-actions-container visible' },
                actions.map((action, index) => 
                    e(
                        'div',
                        { key: index, className: 'wp-nlc-action-item' },
                        e(
                            'div',
                            { className: 'wp-nlc-action-title' },
                            `Action: ${action.tool}`
                        ),
                        e(
                            'div',
                            { className: 'wp-nlc-action-result' },
                            e(
                                'pre',
                                null,
                                JSON.stringify(action.result, null, 2)
                            )
                        )
                    )
                )
            );
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
                            let messageContent = response.data.message;
                            
                            // Set actions if any
                            if (response.data.actions && response.data.actions.length > 0) {
                                setActions(response.data.actions);
                                
                                // Always append a summary of actions to the message
                                if (response.data.actions.length > 0) {
                                    const actionSummaries = response.data.actions
                                        .filter(action => action.summary) // Only include actions with summaries
                                        .map(action => action.summary);
                                    
                                    if (actionSummaries.length > 0) {
                                        // Add a header for the actions section
                                        messageContent = messageContent.trim();
                                        
                                        // Add the actions header
                                        messageContent += '\n\nActions performed:';
                                        
                                        // Add each action summary on a new line
                                        actionSummaries.forEach(summary => {
                                            messageContent += '\n- ' + summary;
                                        });
                                    }
                                }
                            }
                            
                            // Add assistant message to the chat
                            setMessages(prevMessages => [
                                ...prevMessages,
                                { role: 'assistant', content: messageContent }
                            ]);
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
