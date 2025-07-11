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

    if (typeof wp.i18n === 'undefined') {
        console.error('wp.i18n not loaded');
        return;
    }

    const { useState, useRef, useCallback, createElement: e } = wp.element;
    const { __, sprintf } = wp.i18n;

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
    const StatusIndicator = ({ status, message }) => {
        const niceStatus = status.charAt(0).toUpperCase() + status.replace(/[-_]/g, ' ').slice(1);
        return e(
            'div', { className: `ai-commander-realtime-status ${status === 'error' ? 'error-message' : ''}` },
            message || sprintf(__('Status: %s', 'ai-commander'), niceStatus)
        );
    };

    // --- Main Realtime Interface Component ---
    const RealtimeInterface = ({ config }) => {
        const [status, setStatus] = useState('disconnected'); // disconnected,i connecting, idle, recording, processing, speaking, tool_wait, error
        const [ephemeralKey, setEphemeralKey] = useState(null);
        const [ephemeralKeyExpiration, setEphemeralKeyExpiration] = useState(null);
        const [sessionId, setSessionId] = useState(null);
        const [errorMessage, setErrorMessage] = useState('');
        const [assistantTranscriptDelta, setAssistantTranscriptDelta] = useState('');
        const [messages, setMessages] = useState([]); // New state to track messages with their source

        const peerConnectionRef = useRef(null);
        const dataChannelRef = useRef(null);
        const localStreamRef = useRef(null);
        const remoteAudioRef = useRef(null); // Ref for the <audio> element
        const toolCallQueueRef = useRef([]); // Queue for pending tool calls
        const currentToolCallIdRef = useRef(null); // Track the ID of the tool call being processed

        // Helper function to determine if custom TTS is enabled
        // When using a custom TTS, the backend must take care of
        // setting the modalities parameter of the realtime session 
        // to text-only, lest the audios from the realtime and TTS
        // APIs will clash.
        const isCustomTtsEnabled = () => {
            return config.useCustomTts === true;
        };

        // --- Microphone helpers (mute / unmute during TTS playback) ---
        const muteMicrophone = () => {
            if (localStreamRef.current) {
                localStreamRef.current.getAudioTracks().forEach(track => {
                    track.enabled = false;
                });
            }
        };

        const unmuteMicrophone = () => {
            if (localStreamRef.current) {
                localStreamRef.current.getAudioTracks().forEach(track => {
                    track.enabled = true;
                });
            }
        };

        // --- Custom TTS playback when Realtime API audio is disabled ---
        const playCustomTtsAudio = async (text) => {
            if (!text) return;

            try {
                // Update UI status
                setStatus('speaking');

                // Mute the microphone so that speaker output is not captured
                muteMicrophone();

                const formData = new FormData();
                formData.append('action', 'ai_commander_read_text');
                formData.append('nonce', config.nonce);
                formData.append('text', text);

                // Fetch the synthesized audio from backend
                const response = await fetch(config.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                });

                if (!response.ok) {
                    throw new Error(`TTS request failed with status ${response.status}`);
                }

                const audioBlob = await response.blob();

                // Prepare audio element for playback
                if (remoteAudioRef.current) {
                    // Revoke previous object URL if any
                    if (remoteAudioRef.current.dataset.objectUrl) {
                        URL.revokeObjectURL(remoteAudioRef.current.dataset.objectUrl);
                        delete remoteAudioRef.current.dataset.objectUrl;
                    }

                    // Ensure srcObject is cleared when using blob src
                    remoteAudioRef.current.srcObject = null;

                    const objectUrl = URL.createObjectURL(audioBlob);
                    remoteAudioRef.current.dataset.objectUrl = objectUrl;
                    remoteAudioRef.current.src = objectUrl;

                    // Play and await end
                    await remoteAudioRef.current.play();

                    // Wait for playback to finish
                    await new Promise((resolve) => {
                        remoteAudioRef.current.onended = resolve;
                    });

                    // Clean up object URL
                    URL.revokeObjectURL(objectUrl);
                    delete remoteAudioRef.current.dataset.objectUrl;
                }

            } catch (err) {
                console.error('Error during custom TTS playback:', err);
                handleError(__('Failed to play synthesized audio.', 'ai-commander'));
            } finally {
                // Re-enable microphone and update status
                unmuteMicrophone();
                setStatus('idle');
            }
        };

        // --- WebRTC and Session Management ---

        const startRecordingSession = useCallback(async () => {
            if (status === 'connecting' || status === 'recording') return; // Prevent double clicks

            setStatus('connecting');
            setErrorMessage('');
            setAssistantTranscriptDelta('');
            setMessages([]); // Clear messages array
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
                    throw new Error(tokenResponse.data.message || __('Failed to create realtime session.', 'ai-commander'));
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
                        handleDisconnect(__('Connection lost.', 'ai-commander'));
                    }
                };

                pc.oniceconnectionstatechange = () => {
                    console.log('ICE Connection State:', pc.iceConnectionState);
                    if (pc.iceConnectionState === 'failed' || pc.iceConnectionState === 'disconnected' || pc.iceConnectionState === 'closed') {
                        handleDisconnect(__('ICE connection failed.', 'ai-commander'));
                    }
                };

                // 4. Get User Media (Microphone)
                console.log('Requesting microphone access...');
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error(__('Browser does not support audio recording.', 'ai-commander'));
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
                    if (status !== 'disconnected') {
                        handleDisconnect(__('Data channel closed unexpectedly.', 'ai-commander'));
                    }
                };

                dc.onerror = (error) => {
                    console.error('Data Channel Error:', error);
                    handleError(__('Data channel communication error.', 'ai-commander'));
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
                    handleError(__('Microphone permission denied. Please allow access in your browser settings.', 'ai-commander'));
                } else {
                    handleError(error.message || __('Failed to start session and recording.', 'ai-commander'));
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
            setStatus('disconnected'); // Return to disconnected state
            setAssistantTranscriptDelta(''); // Clear any partial transcript
        }, []); // No dependencies needed as it uses refs and closeSession

        // --- Audio Handling (Simplified, logic moved to start/stop session) ---

        // --- Main Action Button Logic ---
        const handleToggleButtonClick = () => {
            if (status === 'recording' || status === 'processing' || status === 'speaking' || status === 'tool_wait') {
                stopRecordingSession();
            } else if (status === 'disconnected' || status === 'error') {
                startRecordingSession();
            }
            // Do nothing if connecting
        };

        // --- Event Handling ---

        // List of events at https://platform.openai.com/docs/api-reference/realtime-server-events
        const handleServerEvent = (serverEvent) => {
            try {
                const event = JSON.parse(serverEvent.data);
                console.log(`Received Server Event: ${event.type}`);

                switch (event.type) {
                    case 'session.created':
                    case 'session.updated':
                        break;

                    // When VAD starts, set status to recording
                    case 'input_audio_buffer.speech_started':
                        // Ensure mic is unmuted when user speaks again
                        unmuteMicrophone();
                        setStatus('recording');
                        break;

                    // When VAD stops, set status to processing
                    case 'input_audio_buffer.speech_stopped':
                        if (status === 'recording') setStatus('processing'); // Move to processing after VAD stop
                        break;

                    // Save user's transcript
                    case 'conversation.item.input_audio_transcription.completed':
                        // Only received if input transcription is enabled in settings
                        setMessages(prev => [...prev, { type: 'user', content: event.transcript }]);
                        break;

                    // When AI starts processing the request, set status to processing
                    case 'response.created':
                        if (status !== 'tool_wait') setStatus('processing');
                        break;

                    // Save AI's transcript as it comes in
                    case 'response.audio_transcript.delta':
                    case 'response.text.delta': // Handle both text and audio transcript deltas
                        setAssistantTranscriptDelta(prev => prev + (event.delta || ''));
                        console.log('Assistant transcript delta:', assistantTranscriptDelta);
                        break;

                    // When AI starts speaking, set status to speaking
                    case 'response.audio.delta':
                        if (!isCustomTtsEnabled()) {
                            setStatus('speaking');
                        }
                        break;

                    // Could potentially stream arguments, but waiting for `response.done` is usually simpler
                    case 'response.function_call_arguments.delta':
                        setStatus('tool_wait');
                        break;

                    // The AI finished processing the request (but might not have
                    // finished speaking yet)
                    case 'response.done':
                        // Check for error in the response
                        if (event.response.status === 'failed') {
                            setStatus('error');
                            const errorMessage = event.response?.status_details?.error?.message || 'Unknown error';
                            setErrorMessage(sprintf(__('Error from AI: %s', 'ai-commander'), errorMessage));
                            break;
                        }

                        // Get text response from AI
                        const responseOutput = event.response?.output?.[0]?.content?.[0];
                        const responseOutputText = responseOutput?.text || responseOutput?.transcript;
                        if (responseOutputText) {
                            setMessages(prev => [...prev, { type: 'assistant', content: responseOutputText }]);
                        }
                        setAssistantTranscriptDelta('');

                        // If custom TTS is enabled, synthesize and play audio now
                        if (isCustomTtsEnabled() && responseOutputText) {
                            // Do not rely on Realtime API's audio; instead, call our endpoint
                            playCustomTtsAudio(responseOutputText);
                        }

                        // Check for function calls in the final response
                        event.response.output.forEach(outputItem => {
                            if (outputItem.type === 'function_call' && outputItem.name && outputItem.arguments && outputItem.call_id) {
                                console.log(`Queueing tool call: ${outputItem.name} with ID ${outputItem.call_id}`);
                                // Add tool call to messages only if showToolCalls is enabled
                                if (config.showToolCalls) {
                                    setMessages(prev => [...prev, {
                                        type: 'tool_call',
                                        name: outputItem.name,
                                        arguments: JSON.parse(outputItem.arguments),
                                        call_id: outputItem.call_id
                                    }]);
                                }

                                toolCallQueueRef.current.push({
                                    name: outputItem.name,
                                    arguments: outputItem.arguments, // Keep as JSON string
                                    call_id: outputItem.call_id,
                                });
                            }
                        });

                        // Process the next tool call if any, otherwise return to connected state
                        if (toolCallQueueRef.current.length > 0) {
                            processNextToolCall();
                        }
                        break;

                    // The AI finished speaking
                    case 'output_audio_buffer.stopped':
                        if (!isCustomTtsEnabled()) {
                            setStatus('idle'); // Ready for next user input
                        }
                        break;

                    case 'error':
                        console.error('Realtime API Error Event:', event);
                        handleError(`API Error: ${event.message || 'Unknown error'}`);
                        break;

                    default:
                        console.log('Unhandled server event type:', event.type);
                }
            } catch (error) {
                console.error('Error parsing server event:', error, 'Raw data:', serverEvent.data);
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
                            message: sprintf(__('Backend Error: %s', 'ai-commander'), response.data.message || __('Unknown execution error', 'ai-commander')),
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
                        message: sprintf(__('AJAX Error: %s', 'ai-commander'), error || __('Failed to contact backend', 'ai-commander')),
                        code: 'ajax_error'
                    };
                    sendFunctionResult(toolCall.call_id, errorResult);
                }
            });
        };

        const sendFunctionResult = (callId, result) => {
            if (!dataChannelRef.current || dataChannelRef.current.readyState !== 'open') {
                handleError(__('Data channel not open, cannot send function result.', 'ai-commander'));
                return;
            }

            // The ID must match the call_id received from OpenAI
            if (callId !== currentToolCallIdRef.current) {
                console.warn(`Mismatch in call IDs. Expected ${currentToolCallIdRef.current}, got ${callId}. Ignoring.`);
                // Potentially handle this case more robustly if needed
                return;
            }

            // Add tool result to messages only if showToolCalls is enabled
            if (config.showToolCalls) {
                setMessages(prev => [...prev, {
                    type: 'tool_result',
                    call_id: callId,
                    result: result
                }]);
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
            // Only transition to disconnected if not already in error or disconnected
            if (status !== 'error' && status !== 'disconnected') {
                setErrorMessage(message || __('Connection closed unexpectedly.', 'ai-commander'));
                setStatus('disconnected'); // Go back to disconnected
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
        };

        // --- UI Rendering ---

        const getButton = () => {
            switch (status) {
                case 'disconnected':
                    return { text: __('Start Conversation', 'ai-commander'), icon: e(MicrophoneIcon), disabled: false };

                case 'connecting':
                    return { text: __('Start Conversation', 'ai-commander'), icon: e(Spinner), disabled: true };

                case 'recording':
                case 'processing':
                case 'speaking':
                case 'tool_wait':
                case 'idle':
                    return { text: __('Stop Conversation', 'ai-commander'), icon: e(MicrophoneIcon), disabled: false };

                case 'error':
                    return { text: __('Please refresh page', 'ai-commander'), icon: null, disabled: true };

                default:
                    return { text: __('Please refresh page', 'ai-commander'), icon: null, disabled: true };
            }
        };

        const { text: buttonText, icon: buttonIcon, disabled: isButtonDisabled } = getButton();

        return e(
            'div', { className: 'ai-commander-realtime-interface' },

            e('div', { className: 'ai-commander-realtime-controls' },
                e('button',
                    {
                        className: `ai-commander-realtime-button status-${status}`,
                        onClick: handleToggleButtonClick, // Updated handler
                        disabled: isButtonDisabled,
                    },
                    buttonIcon,
                    buttonText,
                )
            ),

            errorMessage ? e(StatusIndicator, { status: 'error', message: errorMessage }) : e(StatusIndicator, { status: status }),

            e('div', { className: 'ai-commander-realtime-transcript' },
                e('h3', null, __('Conversation Transcript:', 'ai-commander')),
                e('div', {
                    id: 'ai-commander-transcript-output',
                    className: 'ai-commander-chat-container'
                },
                    // Render messages as chat bubbles
                    messages.map((message, index) => {
                        if (message.type === 'user' || message.type === 'assistant') {
                            return e('div', {
                                key: index,
                                className: `ai-commander-message ${message.type === 'user' ? 'user-message' : 'ai-message'}`
                            },
                                e('div', {
                                    className: `ai-commander-bubble ${message.type === 'user' ? 'user-bubble' : 'ai-bubble'}`
                                }, message.content)
                            );
                        } else if (message.type === 'tool_call') {
                            return e('div', {
                                key: index,
                                className: 'ai-commander-message ai-message'
                            },
                                e('div', {
                                    className: 'ai-commander-bubble ai-bubble tool-call'
                                },
                                    e('strong', {}, sprintf(__('Called tool: %s', 'ai-commander'), message.name)),
                                    e('pre', {}, JSON.stringify(message.arguments, null, 2))
                                )
                            );
                        } else if (message.type === 'tool_result') {
                            return e('div', {
                                key: index,
                                className: 'ai-commander-message ai-message'
                            },
                                e('div', {
                                    className: 'ai-commander-bubble ai-bubble tool-result'
                                },
                                    e('strong', {}, __('Tool result:', 'ai-commander')),
                                    e('pre', {}, JSON.stringify(message.result, null, 2))
                                )
                            );
                        }
                        return null;
                    }),
                    // Show current turn transcript separately
                    assistantTranscriptDelta && e('div', {
                        className: `ai-commander-message ${status === 'recording' ? 'user-message' : 'ai-message'}`
                    },
                        e('div', {
                            className: `ai-commander-bubble ${status === 'recording' ? 'user-bubble' : 'ai-bubble'}`
                        }, assistantTranscriptDelta)
                    )
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
            container.innerHTML = '<p>' + __('Error: Plugin configuration data is missing.', 'ai-commander') + '</p>';
            return;
        }

        const config = {
            ajaxUrl: aiCommanderRealtimeData.ajax_url,
            nonce: aiCommanderRealtimeData.nonce,
            realtimeApiBaseUrl: aiCommanderRealtimeData.realtime_api_base_url,
            realtimeModel: aiCommanderRealtimeData.realtime_model,
            realtimeVoice: aiCommanderRealtimeData.realtime_voice,
            useCustomTts: !!aiCommanderRealtimeData.use_custom_tts,
            showToolCalls: !!aiCommanderRealtimeData.realtime_show_tool_calls
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