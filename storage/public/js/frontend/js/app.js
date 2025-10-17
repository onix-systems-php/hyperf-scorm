/**
 * SCORM Uploader Vue.js Application
 * Handles file upload, progress tracking, and WebSocket integration
 */
const { createApp, ref, reactive, computed, onMounted, onUnmounted, nextTick } = Vue;

const ScormUploaderApp = createApp({
    setup() {
        // State management
        const apiToken = ref(localStorage.getItem('scorm_api_token') || '');
        const selectedFile = ref(null);
        const uploads = ref([]);
        const isDragging = ref(false);
        const isUploading = ref(false);
        const isTestingConnection = ref(false);
        const connectionStatus = ref(null);
        const wsConnected = ref(false);

        // WebSocket instance
        let wsManager = null;

        // API Configuration
        const apiConfig = {
            baseUrl: window.location.origin,
            endpoints: {
                upload: '/v1/scorm/packages/upload',      // ✅ Production ScormController
                status: '/v1/scorm/jobs',                 // ✅ ScormJobStatusController
                batchStatus: '/v1/scorm/jobs/batch-status', // ✅ Batch status endpoint
                cancel: '/v1/scorm/jobs'                  // ✅ Cancel endpoint (+ /{jobId}/cancel)
            }
        };

        // Computed properties
        const hasCompleted = computed(() => {
            return uploads.value.some(upload =>
                upload.status === 'completed' || upload.status === 'failed'
            );
        });

        // Utility functions
        const formatFileSize = (bytes) => {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        };

        const generateUploadId = () => {
            return 'upload_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
        };

        const validateFile = (file) => {
            if (!file) return { valid: false, error: 'No file selected' };

            if (!file.name.toLowerCase().endsWith('.zip')) {
                return { valid: false, error: 'Only ZIP files are allowed' };
            }

            const maxSize = 600 * 1024 * 1024; // 600MB
            if (file.size > maxSize) {
                return { valid: false, error: 'File size exceeds 600MB limit' };
            }

            return { valid: true };
        };

        // Authentication functions
        const saveToken = () => {
            localStorage.setItem('scorm_api_token', apiToken.value);
        };

        const testConnection = async () => {
            if (!apiToken.value) {
                connectionStatus.value = { success: false, message: 'Please enter API token' };
                return;
            }

            isTestingConnection.value = true;
            connectionStatus.value = null;

            try {
                // Test connection with a dummy request
                const response = await fetch(`${apiConfig.baseUrl}${apiConfig.endpoints.status}/test/status`, {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${apiToken.value}`,
                        'Content-Type': 'application/json'
                    }
                });

                if (response.ok || response.status === 404) {
                    // 404 is expected for test job ID, but means auth is working
                    connectionStatus.value = { success: true, message: 'Connection successful!' };
                    setupWebSocket();
                } else if (response.status === 401) {
                    connectionStatus.value = { success: false, message: 'Invalid API token' };
                } else {
                    connectionStatus.value = { success: false, message: `Connection failed (${response.status})` };
                }
            } catch (error) {
                connectionStatus.value = { success: false, message: 'Connection failed: ' + error.message };
            } finally {
                isTestingConnection.value = false;
            }
        };

        // File handling functions
        const handleDrop = (event) => {
            event.preventDefault();
            isDragging.value = false;

            if (!apiToken.value) return;

            const files = Array.from(event.dataTransfer.files);
            if (files.length > 0) {
                handleFileSelection(files[0]);
            }
        };

        const handleFileSelect = (event) => {
            const files = event.target.files;
            if (files.length > 0) {
                handleFileSelection(files[0]);
            }
        };

        const handleFileSelection = (file) => {
            const validation = validateFile(file);
            if (!validation.valid) {
                alert(validation.error);
                return;
            }

            selectedFile.value = file;
        };

        const clearSelection = () => {
            selectedFile.value = null;
        };

        // Upload functions
        const startUpload = async () => {
            if (!selectedFile.value || !apiToken.value) return;

            isUploading.value = true;
            const uploadId = generateUploadId();

            // Add upload to tracking list
            const upload = {
                id: uploadId,
                jobId: null,
                filename: selectedFile.value.name,
                fileSize: selectedFile.value.size,
                status: 'uploading',
                progress: 0,
                stage: 'uploading',
                stageDetails: 'Preparing upload...',
                startTime: Date.now(),
                error: null
            };

            uploads.value.unshift(upload);

            try {
                const formData = new FormData();
                formData.append('scorm_file', selectedFile.value);
                formData.append('metadata', JSON.stringify({
                    upload_id: uploadId,
                    client_timestamp: Date.now()
                }));

                const response = await fetch(`${apiConfig.baseUrl}${apiConfig.endpoints.upload}`, {
                    method: 'POST',
                    // headers: {
                    //     'Authorization': `Bearer ${apiToken.value}`
                    // },
                    body: formData
                });

                const result = await response.json();

                if (response.ok && result.data?.job_id) {
                    // Update upload with job ID
                    upload.jobId = result.data.job_id;
                    upload.status = 'processing';
                    upload.stage = 'initializing';
                    upload.stageDetails = 'Processing started...';

                    console.log('Upload started successfully:', result.data);
                } else {
                    throw new Error(result.message || 'Upload failed');
                }

            } catch (error) {
                console.error('Upload error:', error);
                upload.status = 'failed';
                upload.error = error.message;
                upload.progress = 0;
            } finally {
                isUploading.value = false;
                selectedFile.value = null;
            }
        };

        const cancelUpload = async (jobId) => {
            if (!jobId || !apiToken.value) return;

            try {
                const response = await fetch(`${apiConfig.baseUrl}${apiConfig.endpoints.cancel}/${jobId}/cancel`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${apiToken.value}`,
                        'Content-Type': 'application/json'
                    }
                });

                const result = await response.json();

                if (response.ok) {
                    // Update upload status
                    const upload = uploads.value.find(u => u.jobId === jobId);
                    if (upload) {
                        upload.status = 'cancelled';
                        upload.progress = 0;
                        upload.stageDetails = 'Upload cancelled';
                    }
                    console.log('Upload cancelled:', result);
                } else {
                    console.error('Cancel failed:', result);
                }
            } catch (error) {
                console.error('Cancel error:', error);
            }
        };

        const clearCompleted = () => {
            uploads.value = uploads.value.filter(upload =>
                upload.status === 'processing' || upload.status === 'uploading'
            );
        };

        // Status and progress functions
        const getStatusIcon = (status) => {
            const iconMap = {
                'uploading': 'bi bi-cloud-arrow-up text-primary',
                'processing': 'bi bi-gear-fill text-warning spinning',
                'completed': 'bi bi-check-circle-fill text-success',
                'failed': 'bi bi-x-circle-fill text-danger',
                'cancelled': 'bi bi-slash-circle text-secondary'
            };
            return iconMap[status] || 'bi bi-question-circle text-muted';
        };

        const getStatusBadgeClass = (status) => {
            const classMap = {
                'uploading': 'bg-primary',
                'processing': 'bg-warning',
                'completed': 'bg-success',
                'failed': 'bg-danger',
                'cancelled': 'bg-secondary'
            };
            return classMap[status] || 'bg-secondary';
        };

        const getProgressBarClass = (status, stage) => {
            if (status === 'completed') return 'bg-success';
            if (status === 'failed') return 'bg-danger';
            if (status === 'cancelled') return 'bg-secondary';

            // Different colors for different stages
            const stageMap = {
                'initializing': 'bg-info',
                'extracting': 'bg-primary',
                'processing': 'bg-warning',
                'uploading': 'bg-success'
            };
            return stageMap[stage] || 'bg-primary';
        };

        // WebSocket functions
        const setupWebSocket = () => {
            if (wsManager) {
                wsManager.disconnect();
            }

            wsManager = new WebSocketManager();

            // Setup WebSocket event listeners
            wsManager.on('onConnect', () => {
                wsConnected.value = true;
                console.log('WebSocket connected');
            });

            wsManager.on('onDisconnect', () => {
                wsConnected.value = false;
                console.log('WebSocket disconnected');
            });

            wsManager.on('onProgress', (data) => {
                updateUploadProgress(data.jobId, data.progress);
            });

            wsManager.on('onComplete', (data) => {
                markUploadComplete(data.jobId, data.success);
            });

            wsManager.on('onError', (data) => {
                if (data.jobId) {
                    markUploadFailed(data.jobId, data.error);
                }
            });

            // Connect to WebSocket
            wsManager.connect();
        };

        const updateUploadProgress = (jobId, progressData) => {
            const upload = uploads.value.find(u => u.jobId === jobId);
            if (upload && progressData) {
                upload.progress = progressData.progress || 0;
                upload.stage = progressData.stage || upload.stage;
                upload.stageDetails = progressData.stage_details || upload.stageDetails;

                if (progressData.status) {
                    upload.status = progressData.status;
                }
            }
        };

        const markUploadComplete = (jobId, success) => {
            const upload = uploads.value.find(u => u.jobId === jobId);
            if (upload) {
                upload.status = success ? 'completed' : 'failed';
                upload.progress = success ? 100 : 0;
                upload.stageDetails = success ? 'Upload completed successfully' : 'Upload failed';
            }
        };

        const markUploadFailed = (jobId, error) => {
            const upload = uploads.value.find(u => u.jobId === jobId);
            if (upload) {
                upload.status = 'failed';
                upload.progress = 0;
                upload.error = error;
                upload.stageDetails = 'Upload failed';
            }
        };

        // Lifecycle hooks
        onMounted(() => {
            // Auto-test connection if token is available
            if (apiToken.value) {
                setTimeout(() => {
                    testConnection();
                }, 1000);
            }
        });

        onUnmounted(() => {
            if (wsManager) {
                wsManager.disconnect();
            }
        });

        // Return reactive data and methods for template
        return {
            // Reactive data
            apiToken,
            selectedFile,
            uploads,
            isDragging,
            isUploading,
            isTestingConnection,
            connectionStatus,
            wsConnected,

            // Computed
            hasCompleted,

            // Methods
            formatFileSize,
            saveToken,
            testConnection,
            handleDrop,
            handleFileSelect,
            clearSelection,
            startUpload,
            cancelUpload,
            clearCompleted,
            getStatusIcon,
            getStatusBadgeClass,
            getProgressBarClass
        };
    }
});

// Mount the app
ScormUploaderApp.mount('#app');
