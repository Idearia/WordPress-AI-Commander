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
        const [status, setStatus] = useState('idle'); // idle, connecting, connected, recording, processing, speaking, tool_wait, error
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

        const establishSession = useCallback(async () => {
            setStatus('connecting');
            setErrorMessage('');
            setTranscript('');
            setCurrentTurnTranscript('');
            toolCallQueueRef.current = [];
            currentToolCallIdRef.current = null;

            try {
                // 1. Get ephemeral token from backend
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
                console.log('Realtime Session created:', tokenResponse.data);

                setEphemeralKey(tokenResponse.data.client_secret.value);
                setEphemeralKeyExpiration(tokenResponse.data.client_secret.expires_at);
                setSessionId(tokenResponse.data.id);

                // 2. Create RTCPeerConnection
                const pc = new RTCPeerConnection();
                peerConnectionRef.current = pc;

                // 3. Setup remote audio playback
                pc.ontrack = (event) => {
                    console.log('Remote track received:', event.track);
                    if (remoteAudioRef.current && event.streams && event.streams[0]) {
                        remoteAudioRef.current.srcObject = event.streams[0];
                        remoteAudioRef.current.play().catch(e => console.error('Audio play error:', e));
                        setStatus('speaking'); // AI starts speaking
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
                    if (pc.connectionState === 'connected') {
                        // Initial connection is good, but wait for data channel
                    }
                };

                pc.oniceconnectionstatechange = () => {
                    console.log('ICE Connection State:', pc.iceConnectionState);
                    if (pc.iceConnectionState === 'failed' || pc.iceConnectionState === 'disconnected' || pc.iceConnectionState === 'closed') {
                        handleDisconnect('ICE connection failed.');
                    }
                };

                // 4. Create Data Channel
                const dc = pc.createDataChannel('oai-events', { ordered: true });
                dataChannelRef.current = dc;

                dc.onopen = () => {
                    console.log('Data Channel opened.');
                    setStatus('connected'); // Fully connected and ready
                };

                dc.onclose = () => {
                    console.log('Data Channel closed.');
                    handleDisconnect('Data channel closed.');
                };

                dc.onerror = (error) => {
                    console.error('Data Channel Error:', error);
                    handleError('Data channel communication error.');
                };

                dc.onmessage = handleServerEvent;

                // 4.5 Add Audio Transceiver *before* creating offer
                // This signals the intent to send/receive audio, fixing the "no audio media section" error.
                pc.addTransceiver('audio', { direction: 'sendrecv' });
                console.log('Audio transceiver added.');

                // 5. Start SDP negotiation
                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);

                // 6. Send offer to OpenAI Realtime API
                const sdpResponse = await fetch(`${config.realtimeApiBaseUrl}?model=${config.realtimeModel}`, {
                    method: 'POST',
                    body: offer.sdp, // Send SDP offer as text
                    headers: {
                        'Authorization': `Bearer ${tokenResponse.data.client_secret.value}`,
                        'Content-Type': 'application/sdp', // Correct content type
                    },
                });

                if (!sdpResponse.ok) {
                    const errorText = await sdpResponse.text();
                    throw new Error(`SDP negotiation failed: ${sdpResponse.status} ${errorText}`);
                }

                const answerSdp = await sdpResponse.text();
                await pc.setRemoteDescription({ type: 'answer', sdp: answerSdp });

                console.log('WebRTC connection established.');

            } catch (error) {
                console.error('Session establishment error:', error);
                handleError(error.message || 'Failed to establish session.');
            }
        }, [config]);

        // --- Audio Handling ---

        const startRecording = async () => {
            if (!peerConnectionRef.current || status !== 'connected') {
                handleError('Not connected. Cannot start recording.');
                return;
            }

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                handleError('Your browser does not support audio recording.');
                return;
            }

            // Clear previous turn transcript
            setCurrentTurnTranscript('');

            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                localStreamRef.current = stream;

                // Remove existing audio track if any
                peerConnectionRef.current.getSenders().forEach(sender => {
                    if (sender.track && sender.track.kind === 'audio') {
                        peerConnectionRef.current.removeTrack(sender);
                    }
                });

                // Add new audio track
                stream.getTracks().forEach(track => {
                    peerConnectionRef.current.addTrack(track, stream);
                });

                console.log('Microphone access granted and track added.');
                setStatus('recording');
            } catch (error) {
                console.error('Microphone access error:', error);
                if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
                    handleError('Microphone permission denied. Please allow access in your browser settings.');
                } else {
                    handleError('Could not access microphone: ' + error.message);
                }
            }
        };

        const stopRecording = () => {
            if (localStreamRef.current) {
                localStreamRef.current.getTracks().forEach(track => track.stop());
                localStreamRef.current = null;
                console.log('Recording stopped, tracks released.');
            }
            // Let VAD handle the transition to processing/speaking
            if (status === 'recording') {
                setStatus('processing'); // Assume processing starts after stopping recording
            }
        };

        const toggleRecording = () => {
            if (status === 'recording') {
                stopRecording();
            } else if (status === 'connected' || status === 'speaking' || status === 'processing') {
                // Allow starting recording if connected or even if AI is speaking (for interruption)
                startRecording();
            } else if (status === 'idle' || status === 'error') {
                // Establish session if idle or error when button is clicked
                establishSession();
            }
        };

        // --- Event Handling ---

        const handleServerEvent = (event) => {
            try {
                const serverEvent = JSON.parse(event.data);
                console.log('Received Server Event:', serverEvent);

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
            // Don't immediately set to error, allow potential reconnection attempts or user action
            if (status !== 'error' && status !== 'idle') {
                setErrorMessage(message || 'Connection closed.');
                setStatus('idle'); // Go back to idle, user can try reconnecting
                closeSession(false);
            }
        };

        const closeSession = (cleanupRefs = true) => {
            console.log('Closing session...');
            if (localStreamRef.current) {
                localStreamRef.current.getTracks().forEach(track => track.stop());
                localStreamRef.current = null;
            }
            if (dataChannelRef.current) {
                dataChannelRef.current.close();
                if (cleanupRefs) dataChannelRef.current = null;
            }
            if (peerConnectionRef.current) {
                peerConnectionRef.current.close();
                if (cleanupRefs) peerConnectionRef.current = null;
            }
            if (remoteAudioRef.current) {
                remoteAudioRef.current.srcObject = null;
            }
            // Don't reset status here, let the caller decide (e.g., handleError sets 'error')
        };

        // --- UI Rendering ---

        const getButtonTextAndIcon = () => {
            switch (status) {
                case 'idle': return { text: 'Connect Session', icon: null };
                case 'connecting': return { text: 'Connecting...', icon: e(Spinner) };
                case 'connected': return { text: 'Start Recording', icon: e(MicrophoneIcon) };
                case 'recording': return { text: 'Stop Recording', icon: e(MicrophoneIcon) };
                case 'processing': return { text: 'Processing...', icon: e(Spinner) };
                case 'speaking': return { text: 'AI Speaking...', icon: e(Spinner) }; // Maybe allow interrupting?
                case 'tool_wait': return { text: 'Executing Tool...', icon: e(Spinner) };
                case 'error': return { text: 'Retry Connection', icon: null };
                default: return { text: 'Unknown State', icon: null };
            }
        };

        const { text: buttonText, icon: buttonIcon } = getButtonTextAndIcon();
        const isButtonDisabled = status === 'connecting' || status === 'processing' || status === 'tool_wait';

        // Adjust initial button title/text based on idle state meaning "Connect"
        const buttonTitle = status === 'recording' ? 'Stop recording' : (status === 'connected' ? 'Start recording' : 'Connect Session / Retry');

        return e(
            'div', { className: 'ai-commander-realtime-interface' },

            e('div', { className: 'ai-commander-realtime-controls' },
                e('button',
                    {
                        className: `ai-commander-realtime-button status-${status}`,
                        onClick: toggleRecording,
                        disabled: isButtonDisabled,
                        title: buttonTitle // Use dynamic title
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
            e('audio', { ref: remoteAudioRef, style: { display: 'none' } })
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

        console.log('Realtime config:', config);

        // Render the chat interface
        wp.element.render(
            e(RealtimeInterface, { config: config }),
            container
        );
    }

    // Initialize when the document is ready
    $(document).ready(init);

})(jQuery); 