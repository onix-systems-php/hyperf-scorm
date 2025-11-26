/**
 * WebSocket Manager for SCORM Upload Progress
 * Handles real-time updates from the server
 */
class WebSocketManager {
    constructor()
    {
        this.socket = null;
        this.isConnected = false;
        this.reconnectInterval = 3000; // 3 seconds
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 10;
        this.callbacks = {
            onConnect: [],
            onDisconnect: [],
            onMessage: [],
            onError: []
        };
        this.userId = null;
        this.pingInterval = null;
    }

    /**
     * Connect to WebSocket server
     */
    connect(userId = null)
    {
        if (this.socket && this.socket.readyState === WebSocket.OPEN) {
            console.log('WebSocket already connected');
            return;
        }

        this.userId = userId;
        const wsUrl = this.getWebSocketUrl();

        console.log('Connecting to WebSocket:', wsUrl);

        try {
            this.socket = new WebSocket(wsUrl);
            this.setupEventListeners();
        } catch (error) {
            console.error('Failed to create WebSocket connection:', error);
            this.triggerCallbacks('onError', error);
            this.scheduleReconnect();
        }
    }

    /**
     * Get WebSocket URL based on current location
     */
    getWebSocketUrl()
    {
        const protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
        const hostname = location.hostname;
        const port = 9501; // Default WebSocket port for Hyperf

        return `${protocol}//${hostname}:${port}`;
    }

    /**
     * Setup WebSocket event listeners
     */
    setupEventListeners()
    {
        this.socket.onopen = (event) => {
            console.log('WebSocket connected');
            this.isConnected = true;
            this.reconnectAttempts = 0;
            this.triggerCallbacks('onConnect', event);

            // Send subscription message if userId is available
            if (this.userId) {
                this.subscribeToUpdates(this.userId);
            }

            // Setup ping to keep connection alive
            this.startPing();
        };

        this.socket.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                console.log('WebSocket message received:', data);

                // Handle different message types
                switch (data.type) {
                    case 'scorm_upload_progress':
                        this.handleProgressUpdate(data);
                        break;
                    case 'scorm_completed':
                        this.handleCompletionNotification(data);
                        break;
                    case 'scorm_error':
                        this.handleErrorNotification(data);
                        break;
                    case 'pong':
                        // Pong response - connection is alive
                        break;
                    default:
                        console.log('Unknown message type:', data.type);
                }

                this.triggerCallbacks('onMessage', data);
            } catch (error) {
                console.error('Error parsing WebSocket message:', error);
            }
        };

        this.socket.onclose = (event) => {
            console.log('WebSocket disconnected:', event.code, event.reason);
            this.isConnected = false;
            this.stopPing();
            this.triggerCallbacks('onDisconnect', event);

            // Attempt to reconnect unless it was intentional
            if (event.code !== 1000) {
                this.scheduleReconnect();
            }
        };

        this.socket.onerror = (error) => {
            console.error('WebSocket error:', error);
            this.triggerCallbacks('onError', error);
        };
    }

    /**
     * Subscribe to SCORM processing updates for a user
     */
    subscribeToUpdates(userId)
    {
        if (this.isConnected && this.socket.readyState === WebSocket.OPEN) {
            const message = {
                action: 'subscribe',
                user_id: userId
            };
            this.socket.send(JSON.stringify(message));
            console.log('Subscribed to updates for user:', userId);
        }
    }

    /**
     * Handle progress update message
     */
    handleProgressUpdate(data)
    {
        // Progress data structure:
        // {
        //   type: 'scorm_upload_progress',
        //   job_id: 'uuid',
        //   progress: {
        //     stage: 'extracting',
        //     progress: 45,
        //     stage_details: 'Extracting files (156/324)',
        //     file_size: 15728640,
        //     processed_bytes: 7077888,
        //     memory_usage: 67108864
        //   }
        // }

        this.triggerCallbacks('onProgress', {
            jobId: data.job_id,
            progress: data.progress
        });
    }

    /**
     * Handle completion notification
     */
    handleCompletionNotification(data)
    {
        this.triggerCallbacks('onComplete', {
            jobId: data.job_id,
            result: data.result,
            success: data.success
        });
    }

    /**
     * Handle error notification
     */
    handleErrorNotification(data)
    {
        this.triggerCallbacks('onError', {
            jobId: data.job_id,
            error: data.error
        });
    }

    /**
     * Start ping interval to keep connection alive
     */
    startPing()
    {
        this.pingInterval = setInterval(() => {
            if (this.isConnected && this.socket.readyState === WebSocket.OPEN) {
                this.socket.send(JSON.stringify({ type: 'ping' }));
            }
        }, 30000); // Ping every 30 seconds
    }

    /**
     * Stop ping interval
     */
    stopPing()
    {
        if (this.pingInterval) {
            clearInterval(this.pingInterval);
            this.pingInterval = null;
        }
    }

    /**
     * Schedule reconnection attempt
     */
    scheduleReconnect()
    {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.log('Max reconnection attempts reached');
            return;
        }

        this.reconnectAttempts++;
        const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000); // Exponential backoff, max 30s

        console.log(`Scheduling reconnect attempt ${this.reconnectAttempts} in ${delay}ms`);

        setTimeout(() => {
            if (!this.isConnected) {
                this.connect(this.userId);
            }
        }, delay);
    }

    /**
     * Disconnect from WebSocket
     */
    disconnect()
    {
        console.log('Disconnecting WebSocket');
        this.stopPing();

        if (this.socket) {
            this.socket.close(1000, 'Intentional disconnect');
            this.socket = null;
        }

        this.isConnected = false;
    }

    /**
     * Add event callback
     */
    on(event, callback)
    {
        if (this.callbacks[event]) {
            this.callbacks[event].push(callback);
        }
    }

    /**
     * Remove event callback
     */
    off(event, callback)
    {
        if (this.callbacks[event]) {
            const index = this.callbacks[event].indexOf(callback);
            if (index > -1) {
                this.callbacks[event].splice(index, 1);
            }
        }
    }

    /**
     * Trigger callbacks for an event
     */
    triggerCallbacks(event, data)
    {
        if (this.callbacks[event]) {
            this.callbacks[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`Error in ${event} callback:`, error);
                }
            });
        }
    }

    /**
     * Get connection status
     */
    getStatus()
    {
        return {
            connected: this.isConnected,
            readyState: this.socket ? this.socket.readyState : WebSocket.CLOSED,
            reconnectAttempts: this.reconnectAttempts
        };
    }
}

// Export for use in main app
window.WebSocketManager = WebSocketManager;