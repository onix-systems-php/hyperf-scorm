/**
 * SCORM Upload WebSocket Client with Polling Fallback
 *
 * Usage:
 * const client = new ScormUploadClient(API_BASE_URL);
 *
 * // After receiving job_id from async upload
 * client.trackJob(jobId, {
 *   onProgress: (data) => { console.log('Progress:', data.progress, data.stage); },
 *   onComplete: (data) => { console.log('Completed! Package ID:', data.package_id); },
 *   onError: (error) => { console.error('Failed:', error); }
 * });
 */

class ScormUploadClient {
    constructor(apiBaseUrl) {
        this.apiBaseUrl = apiBaseUrl;
        this.ws = null;
        this.pollingInterval = null;
        this.pingInterval = null;
        this.callbacks = {};
        this.jobId = null;
        this.wsPort = 9502; // WebSocket server port
    }

    /**
     * Start tracking a job with WebSocket (primary) or Polling (fallback)
     */
    trackJob(jobId, callbacks) {
        this.jobId = jobId;
        this.callbacks = callbacks;

        // Try WebSocket first
        this.connectWebSocket(jobId);
    }

    /**
     * Connect to WebSocket server
     */
    connectWebSocket(jobId) {
        const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsHost = window.location.hostname;
        const wsUrl = `${wsProtocol}//${wsHost}:${this.wsPort}/scorm-progress?job_id=${jobId}`;

        console.log('[SCORM WS] Connecting to:', wsUrl);

        try {
            this.ws = new WebSocket(wsUrl);

            // Connection timeout (5 seconds)
            const timeout = setTimeout(() => {
                if (this.ws.readyState !== WebSocket.OPEN) {
                    console.warn('[SCORM WS] Connection timeout, falling back to polling');
                    this.ws.close();
                    this.startPolling(jobId);
                }
            }, 5000);

            this.ws.onopen = (event) => {
                clearTimeout(timeout);
                console.log('[SCORM WS] Connected');

                // Start ping interval for keep-alive
                this.pingInterval = setInterval(() => {
                    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                        this.ws.send('ping');
                    }
                }, 30000); // Every 30 seconds
            };

            this.ws.onmessage = (event) => {
                try {
                    const message = JSON.parse(event.data);

                    if (message.type === 'connected') {
                        console.log('[SCORM WS] Handshake complete:', message.job_id);
                    } else if (message.type === 'progress' && message.data) {
                        this.handleProgressUpdate(message.data);
                    } else if (message.type === 'pong') {
                        // Keep-alive response
                    }
                } catch (error) {
                    console.error('[SCORM WS] Failed to parse message:', error);
                }
            };

            this.ws.onerror = (error) => {
                console.error('[SCORM WS] Error:', error);
                // Fallback to polling
                this.startPolling(jobId);
            };

            this.ws.onclose = (event) => {
                console.log('[SCORM WS] Connection closed:', event.code, event.reason);

                // Clear ping interval
                if (this.pingInterval) {
                    clearInterval(this.pingInterval);
                    this.pingInterval = null;
                }

                // If job is still processing, fallback to polling
                if (this.jobId && (!this.callbacks.completed && !this.callbacks.failed)) {
                    console.log('[SCORM WS] Fallback to polling');
                    this.startPolling(jobId);
                }
            };

        } catch (error) {
            console.error('[SCORM WS] Failed to create WebSocket:', error);
            // Fallback to polling
            this.startPolling(jobId);
        }
    }

    /**
     * Start HTTP polling as fallback
     */
    startPolling(jobId) {
        if (this.pollingInterval) {
            return; // Already polling
        }

        console.log('[SCORM Poll] Starting polling for job:', jobId);

        // Poll every 2 seconds
        this.pollingInterval = setInterval(async () => {
            await this.checkJobStatus(jobId);
        }, 2000);

        // Check immediately
        this.checkJobStatus(jobId);
    }

    /**
     * Check job status via REST API
     */
    async checkJobStatus(jobId) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/v1/scorm/jobs/${jobId}/status`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to check job status');
            }

            const result = await response.json();
            this.handleProgressUpdate(result.data);

        } catch (error) {
            console.error('[SCORM Poll] Status check failed:', error);
            // Don't stop polling on network errors
        }
    }

    /**
     * Handle progress update (from WebSocket or Polling)
     */
    handleProgressUpdate(data) {
        // Call progress callback
        if (this.callbacks.onProgress) {
            this.callbacks.onProgress(data);
        }

        // Check if completed
        if (data.status === 'completed') {
            console.log('[SCORM] Job completed:', data.package_id);

            if (this.callbacks.onComplete) {
                this.callbacks.onComplete(data);
            }

            this.cleanup();
            this.callbacks.completed = true;

        } else if (data.status === 'failed') {
            console.error('[SCORM] Job failed:', data.error);

            if (this.callbacks.onError) {
                this.callbacks.onError(data.error);
            }

            this.cleanup();
            this.callbacks.failed = true;
        }
    }

    /**
     * Get stage label for display
     */
    static getStageLabel(stage) {
        const labels = {
            queued: 'Queued...',
            validating: 'Validating file...',
            uploading: 'Uploading to storage...',
            extracting: 'Extracting package...',
            parsing: 'Parsing manifest...',
            saving: 'Saving to database...',
            cleanup: 'Cleaning up...',
            completed: 'Completed',
            failed: 'Failed',
            processing: 'Processing...',
        };
        return labels[stage] || stage;
    }

    /**
     * Cleanup connections
     */
    cleanup() {
        // Close WebSocket
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }

        // Stop polling
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }

        // Stop ping
        if (this.pingInterval) {
            clearInterval(this.pingInterval);
            this.pingInterval = null;
        }

        this.jobId = null;
    }

    /**
     * Cancel tracking (for user-initiated cancellation)
     */
    cancel() {
        console.log('[SCORM] Cancelling job tracking');
        this.cleanup();
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ScormUploadClient;
}
