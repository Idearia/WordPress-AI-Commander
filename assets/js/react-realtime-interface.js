/**
 * React Realtime Chat Interface for AI Commander
 *
 * Handles WebRTC connection, audio streaming, transcription display,
 * and tool calling for real-time voice interaction.
 */

(function ($) {
    'use strict';

    if (typeof wp === 'undefined' || typeof wp.element === 'undefined') {
        console.error('wp.element not loaded');
        return;
    }

    const { useState, useRef, useCallback, createElement: e } = wp.element;

    // --- Helper Components ---

    /**
     * MicrophoneIcon Component
     */
    const MicrophoneIcon = () => e(
        'svg', { className: 'ai-commander-realtime-button-icon', width: '24', height: '24', viewBox: '0 0 24 24', fill: 'currentColor' },
        e('path', { d: 'M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z' }),
        e('path', { d: 'M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z' })
    );

    /**
     * Spinner Component
     */
    const Spinner = () => e('span', { className: 'ai-commander-spinner' });

    /**
     * StatusIndicator Component
     */
    const StatusIndicator = ({ status, message }) => e(
        'div', { className: `ai-commander-realtime-status ${status === 'error' ? 'error-message' : ''}` },
        message || 'Status: ' + status.charAt(0).toUpperCase() + status.slice(1)
    );

    // --- Main Realtime Interface Component ---
    const RealtimeInterface = ({ config }) => {
        const [status, setStatus] = useState('idle'); // idle, connecting, recording, processing, speaking, tool_wait, error
        const [ephemeralKey, setEphemeralKey] = useState(null);
        const [ephemeralKeyExpiration, setEphemeralKeyExpiration] = useState(null);
        const [sessionId, setSessionId] = useState(null);
        const [errorMessage, setErrorMessage] = useState('');
        const [transcript, setTranscript] = useState('');
        const [currentTurnTranscript, setCurrentTurnTranscript] = useState('');

        const peerConnectionRef = useRef(null);
        const dataChannelRef = useRef(null);
        const localStreamRef = useRef(null);
        const remoteAudioRef = useRef(null); // Ref for the <audio> element
        const toolCallQueueRef = useRef([]); // Queue for pending tool calls
        const currentToolCallIdRef = useRef(null); // Track the ID of the tool call being processed

        // --- WebRTC and Session Management ---

        const startRecordingSession = useCallback(async () => {
            if (status === 'connecting' || status === 'recording') return; // Prevent double clicks

            setStatus('connecting');
            setErrorMessage('');
            setTranscript('');
            setCurrentTurnTranscript('');
            toolCallQueueRef.current = [];
            currentToolCallIdRef.current = null;
            closeSession(false); // Clean up any previous connections first

            try {
                // 1. Get ephemeral token from backend
                console.log('Requesting ephemeral token...');
                const tokenResponse = await $.ajax({
                    url: config.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'ai_commander_create_realtime_session',
                        nonce: config.nonce,
                    },
                });

                if (!tokenResponse.success || !tokenResponse.data.client_secret.value) {
                    throw new Error(tokenResponse.data.message || 'Failed to create realtime session.');
                }
                console.log('Realtime Session created successfully. Token received.');

                const key = tokenResponse.data.client_secret.value;
                setEphemeralKey(key);
                setEphemeralKeyExpiration(tokenResponse.data.client_secret.expires_at);
                setSessionId(tokenResponse.data.id);

                // 2. Create RTCPeerConnection
                console.log('Creating Peer Connection...');
                const pc = new RTCPeerConnection();
                peerConnectionRef.current = pc;

                // 3. Setup remote audio playback
                pc.ontrack = (event) => {
                    console.log(`Remote ${event.track.kind} track received.`);
                    if (remoteAudioRef.current && event.streams && event.streams[0]) {
                        if (remoteAudioRef.current.srcObject !== event.streams[0]) {
                            remoteAudioRef.current.srcObject = event.streams[0];
                            console.log('Remote stream attached to audio element.');
                            remoteAudioRef.current.play().catch(e => console.error('Audio play error:', e));
                        } else {
                            console.log('Remote stream already attached.');
                        }
                        // Don't set status to speaking here, wait for audio/text deltas
                    } else {
                        console.warn('Could not attach remote stream to audio element.');
                    }
                };

                // Handle connection state changes
                pc.onconnectionstatechange = () => {
                    console.log('Peer Connection State:', pc.connectionState);
                    if (pc.connectionState === 'failed' || pc.connectionState === 'disconnected' || pc.connectionState === 'closed') {
                        handleDisconnect('Connection lost.');
                    }
                };

                pc.oniceconnectionstatechange = () => {
                    console.log('ICE Connection State:', pc.iceConnectionState);
                    if (pc.iceConnectionState === 'failed' || pc.iceConnectionState === 'disconnected' || pc.iceConnectionState === 'closed') {
                        handleDisconnect('ICE connection failed.');
                    }
                };

                // 4. Get User Media (Microphone)
                console.log('Requesting microphone access...');
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('Browser does not support audio recording.');
                }
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                localStreamRef.current = stream;
                console.log('Microphone access granted.');

                // 5. Add local audio track *before* creating offer
                stream.getTracks().forEach(track => {
                    pc.addTrack(track, stream);
                    console.log('Local audio track added to Peer Connection.');
                });

                // 6. Create Data Channel
                console.log('Creating Data Channel...');
                const dc = pc.createDataChannel('oai-events', { ordered: true });
                dataChannelRef.current = dc;

                dc.onopen = () => {
                    console.log('Data Channel opened.');
                    // Now that data channel is open and mic is ready, set status to recording
                    setStatus('recording');
                };

                dc.onclose = () => {
                    console.log('Data Channel closed.');
                    // Only handle disconnect if not initiated by user stop
                    if (status !== 'idle') {
                        handleDisconnect('Data channel closed unexpectedly.');
                    }
                };

                dc.onerror = (error) => {
                    console.error('Data Channel Error:', error);
                    handleError('Data channel communication error.');
                };

                dc.onmessage = handleServerEvent;

                // 7. Start SDP negotiation (Offer will include audio track info)
                console.log('Creating SDP Offer...');
                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);
                console.log('Local description (Offer) set.');

                // 8. Send offer to OpenAI Realtime API
                console.log('Sending SDP Offer to OpenAI...');
                const sdpResponse = await fetch(`${config.realtimeApiBaseUrl}?model=${config.realtimeModel}`, {
                    method: 'POST',
                    body: offer.sdp, // Send SDP offer as text
                    headers: {
                        'Authorization': `Bearer ${key}`,
                        'Content-Type': 'application/sdp',
                    },
                });

                if (!sdpResponse.ok) {
                    const errorText = await sdpResponse.text();
                    throw new Error(`SDP negotiation failed: ${sdpResponse.status} ${errorText}`);
                }
                console.log('SDP Offer sent successfully.');

                const answerSdp = await sdpResponse.text();
                console.log('Received SDP Answer.');
                await pc.setRemoteDescription({ type: 'answer', sdp: answerSdp });
                console.log('Remote description (Answer) set. WebRTC connection ready.');

                // Status is set to 'recording' in dc.onopen

            } catch (error) {
                console.error('Session start/recording error:', error);
                if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
                    handleError('Microphone permission denied. Please allow access in your browser settings.');
                } else {
                    handleError(error.message || 'Failed to start session and recording.');
                }
                closeSession(); // Ensure cleanup on error
            }
        }, [config, status]); // Include status in dependencies

        const stopRecordingSession = useCallback(() => {
            console.log('Stopping session and recording...');
            // Stop microphone first
            if (localStreamRef.current) {
                localStreamRef.current.getTracks().forEach(track => track.stop());
                localStreamRef.current = null;
                console.log('Microphone tracks stopped.');
            }
            // Close WebRTC connection and data channel
            closeSession();
            setStatus('idle'); // Return to idle state
            setCurrentTurnTranscript(''); // Clear any partial transcript
        }, []); // No dependencies needed as it uses refs and closeSession

        // --- Audio Handling (Simplified, logic moved to start/stop session) ---

        // --- Main Action Button Logic ---
        const handleToggleButtonClick = () => {
            if (status === 'recording' || status === 'processing' || status === 'speaking' || status === 'tool_wait') {
                stopRecordingSession();
            } else if (status === 'idle' || status === 'error') {
                startRecordingSession();
            }
            // Do nothing if connecting
        };

        // --- Event Handling ---

        const handleServerEvent = (event) => {
            try {
                const serverEvent = JSON.parse(event.data);
                console.log(`Received Server Event: ${serverEvent.type}`);

                switch (serverEvent.type) {
                    case 'session.created':
                    case 'session.updated':
                        break;

                    case 'input_audio_buffer.speech_started':
                        console.log('Server event: Speech started detected.');
                        setCurrentTurnTranscript(''); // Clear transcript for new speech turn
                        if (status !== 'recording') setStatus('recording'); // Reflect VAD start
                        break;

                    case 'input_audio_buffer.speech_stopped':
                        console.log('Server event: Speech stopped detected.');
                        if (status === 'recording') setStatus('processing'); // Move to processing after VAD stop
                        // Append the final turn transcript to the main transcript
                        if (currentTurnTranscript) {
                            setTranscript(prev => prev + currentTurnTranscript + '\n\n');
                            setCurrentTurnTranscript(''); // Clear for next turn
                        }
                        break;

                    case 'response.created':
                        console.log('Server event: AI is preparing response.');
                        if (status !== 'tool_wait') setStatus('processing'); // If not waiting for tool, we are processing
                        break;

                    case 'response.audio_transcript.delta':
                    case 'response.text.delta': // Handle both text and audio transcript deltas
                        setCurrentTurnTranscript(prev => prev + (serverEvent.delta || ''));
                        break;

                    case 'response.audio.delta':
                        // Audio data comes via WebRTC track, not typically needed here
                        if (status !== 'speaking' && status !== 'tool_wait') setStatus('speaking');
                        break;

                    case 'response.audio.done':
                        // Maybe transition status if needed, e.g., back to connected if no text follows
                        console.log('Server event: AI audio finished.');
                        // Don't reset status here, wait for response.done
                        break;

                    case 'response.function_call_arguments.delta':
                        // Could potentially stream arguments, but waiting for `response.done` is usually simpler
                        console.log('Server event: Function call arguments delta received.');
                        if (status !== 'tool_wait') setStatus('tool_wait');
                        break;

                    case 'response.done':
                        console.log('Server event: Response cycle finished.', serverEvent.response);
                        // Append the final turn transcript if any
                        if (currentTurnTranscript) {
                            setTranscript(prev => prev + currentTurnTranscript + '\n\n');
                            setCurrentTurnTranscript('');
                        }

                        // Check for function calls in the final response
                        if (serverEvent.response?.output?.length > 0) {
                            serverEvent.response.output.forEach(outputItem => {
                                if (outputItem.type === 'function_call' && outputItem.name && outputItem.arguments && outputItem.call_id) {
                                    console.log(`Queueing tool call: ${outputItem.name} with ID ${outputItem.call_id}`);
                                    toolCallQueueRef.current.push({
                                        name: outputItem.name,
                                        arguments: outputItem.arguments, // Keep as JSON string
                                        call_id: outputItem.call_id,
                                    });
                                }
                            });
                        }

                        // Process the next tool call if any, otherwise return to connected state
                        if (toolCallQueueRef.current.length > 0) {
                            processNextToolCall();
                        } else {
                            setStatus('connected'); // Ready for next user input
                        }
                        break;

                    case 'error':
                        console.error('Realtime API Error Event:', serverEvent);
                        handleError(`API Error: ${serverEvent.message || 'Unknown error'}`);
                        break;

                    default:
                        console.log('Unhandled server event type:', serverEvent.type);
                }
            } catch (error) {
                console.error('Error parsing server event:', error, 'Raw data:', event.data);
                // Avoid setting error status here unless it's critical
                // Consider setting error status if parsing fails consistently
            }
        };

        // --- Tool Calling Logic ---

        const processNextToolCall = () => {
            if (toolCallQueueRef.current.length === 0) {
                setStatus('connected'); // No more tools, ready for input
                return;
            }

            const toolCall = toolCallQueueRef.current.shift(); // Get the next tool call
            currentToolCallIdRef.current = toolCall.call_id; // Track the current call ID
            setStatus('tool_wait');
            setErrorMessage(''); // Clear previous errors
            console.log(`Executing tool: ${toolCall.name}`, toolCall.arguments);

            // Send AJAX request to backend to execute the tool
            $.ajax({
                url: config.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'ai_commander_execute_realtime_tool',
                    nonce: config.nonce,
                    tool_name: toolCall.name,
                    arguments: toolCall.arguments, // Send JSON string directly
                },
                success: function (response) {
                    if (response.success) {
                        console.log(`Tool ${toolCall.name} executed successfully. Result:`, response.data);
                        // Send the result back to OpenAI
                        sendFunctionResult(toolCall.call_id, response.data);
                    } else {
                        // Handle backend execution error - Send structured error back to OpenAI
                        console.error(`Tool execution failed on backend for ${toolCall.name}:`, response.data.message);
                        const errorResult = {
                            error: true,
                            message: `Backend Error: ${response.data.message || 'Unknown execution error'}`,
                            code: response.data.code || 'tool_execution_failed'
                        };
                        sendFunctionResult(toolCall.call_id, errorResult);
                    }
                },
                error: function (xhr, status, error) {
                    // Handle AJAX error - Send structured error back to OpenAI
                    console.error(`AJAX error executing tool ${toolCall.name}:`, error);
                    const errorResult = {
                        error: true,
                        message: `AJAX Error: ${error || 'Failed to contact backend'}`,
                        code: 'ajax_error'
                    };
                    sendFunctionResult(toolCall.call_id, errorResult);
                }
            });
        };

        const sendFunctionResult = (callId, result) => {
            if (!dataChannelRef.current || dataChannelRef.current.readyState !== 'open') {
                handleError('Data channel not open, cannot send function result.');
                return;
            }

            // The ID must match the call_id received from OpenAI
            if (callId !== currentToolCallIdRef.current) {
                console.warn(`Mismatch in call IDs. Expected ${currentToolCallIdRef.current}, got ${callId}. Ignoring.`);
                // Potentially handle this case more robustly if needed
                return;
            }

            const resultEvent = {
                type: "conversation.item.create",
                item: {
                    type: "function_call_output",
                    call_id: callId,
                    output: JSON.stringify(result), // Send the result (or error structure) as a JSON string
                },
            };

            console.log('Sending function result:', resultEvent);
            dataChannelRef.current.send(JSON.stringify(resultEvent));

            // Tell OpenAI to generate a response based on the function result
            const responseEvent = {
                type: "response.create",
            };
            console.log('Requesting response after function call.');
            dataChannelRef.current.send(JSON.stringify(responseEvent));

            // Clear the current tool call ID after sending result
            currentToolCallIdRef.current = null;
            // Status will be updated by server events (response.created, etc.)
        };

        // --- Error Handling and Cleanup ---

        const handleError = (message) => {
            console.error('Realtime Interface Error:', message);
            setErrorMessage(message);
            setStatus('error');
            closeSession(false); // Close current session without trying to re-establish immediately
        };

        const handleDisconnect = (message) => {
            console.warn('Disconnected:', message);
            // Only transition to idle if not already in error or idle
            if (status !== 'error' && status !== 'idle') {
                setErrorMessage(message || 'Connection closed unexpectedly.');
                setStatus('idle'); // Go back to idle
                closeSession(false); // Clean up refs without resetting state again
            }
        };

        const closeSession = (cleanupRefs = true) => {
            console.log('Closing session resources...');
            // Stop mic tracks just in case they are still running during an error cleanup
            if (localStreamRef.current) {
                localStreamRef.current.getTracks().forEach(track => track.stop());
                if (cleanupRefs) localStreamRef.current = null;
            }
            if (dataChannelRef.current) {
                // Check readyState before closing
                if (dataChannelRef.current.readyState === 'open' || dataChannelRef.current.readyState === 'connecting') {
                    dataChannelRef.current.close();
                    console.log('Data channel closed.');
                } else {
                    console.log('Data channel already closed or closing.');
                }
                if (cleanupRefs) dataChannelRef.current = null;
            }
            if (peerConnectionRef.current) {
                // Check connectionState before closing
                if (peerConnectionRef.current.connectionState !== 'closed') {
                    peerConnectionRef.current.close();
                    console.log('Peer connection closed.');
                } else {
                    console.log('Peer connection already closed.');
                }
                if (cleanupRefs) peerConnectionRef.current = null;
            }
            if (remoteAudioRef.current) {
                remoteAudioRef.current.srcObject = null;
                console.log('Remote audio source cleared.');
            }
            // Note: Setting status to idle is now handled by stopRecordingSession or handleDisconnect
        };

        // --- UI Rendering ---

        const getButtonTextAndIcon = () => {
            switch (status) {
                case 'idle': return { text: 'Start Recording', icon: e(MicrophoneIcon) };
                case 'connecting': return { text: 'Connecting...', icon: e(Spinner) };
                // case 'connected': // This state is removed
                case 'recording': return { text: 'Stop Recording', icon: e(MicrophoneIcon) };
                case 'processing': return { text: 'Processing...', icon: e(Spinner) }; // Still need processing/speaking states
                case 'speaking': return { text: 'AI Speaking...', icon: e(Spinner) };
                case 'tool_wait': return { text: 'Executing Tool...', icon: e(Spinner) };
                case 'error': return { text: 'Retry Session', icon: e(MicrophoneIcon) };
                default: return { text: 'Unknown State', icon: null };
            }
        };

        const { text: buttonText, icon: buttonIcon } = getButtonTextAndIcon();
        // Disable button only during the connection phase
        const isButtonDisabled = status === 'connecting';

        // Adjust title based on action
        const buttonTitle = status === 'recording' ? 'Stop session and recording' : 'Start a new session and record';

        return e(
            'div', { className: 'ai-commander-realtime-interface' },

            e('div', { className: 'ai-commander-realtime-controls' },
                e('button',
                    {
                        className: `ai-commander-realtime-button status-${status}`,
                        onClick: handleToggleButtonClick, // Updated handler
                        disabled: isButtonDisabled,
                        title: buttonTitle
                    },
                    buttonIcon,
                    buttonText
                )
            ),

            (status !== 'idle' && status !== 'connecting') && errorMessage && e(StatusIndicator, { status: 'error', message: errorMessage }),
            (status === 'processing' || status === 'speaking' || status === 'tool_wait') && !errorMessage && e(StatusIndicator, { status: status }),

            e('div', { className: 'ai-commander-realtime-transcript' },
                e('h3', null, 'Conversation Transcript:'),
                e('div', { id: 'ai-commander-transcript-output' },
                    transcript,
                    // Show current turn transcript separately while it's coming in
                    currentTurnTranscript ? e('span', { style: { color: '#888' } }, currentTurnTranscript) : null
                )
            ),

            // Hidden audio element for playback
            e('audio', { ref: remoteAudioRef, style: { display: 'none' }, autoplay: true })
        );
    };

    // --- Initialization ---

    function init() {
        const container = document.getElementById('ai-commander-realtime-interface');
        if (!container) {
            console.error('Realtime interface container not found');
            return;
        }

        // Get configuration from localized script
        if (typeof aiCommanderRealtimeData === 'undefined') {
            console.error('Realtime interface data (aiCommanderRealtimeData) not found.');
            container.innerHTML = '<p>Error: Plugin configuration data is missing.</p>';
            return;
        }

        const config = {
            ajaxUrl: aiCommanderRealtimeData.ajax_url,
            nonce: aiCommanderRealtimeData.nonce,
            realtimeApiBaseUrl: aiCommanderRealtimeData.realtime_api_base_url,
            realtimeModel: aiCommanderRealtimeData.realtime_model,
            realtimeVoice: aiCommanderRealtimeData.realtime_voice
        };

        // Render the chat interface
        wp.element.render(
            e(RealtimeInterface, { config: config }),
            container
        );
    }

    // Initialize when the document is ready
    $(document).ready(init);

})(jQuery); 