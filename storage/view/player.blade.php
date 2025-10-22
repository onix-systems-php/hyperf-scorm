@extends('OnixSystemsPHP\\HyperfScorm::layout')

@section('title', 'SCORM Player - ' . $package->title)

@section('content')

    <script>
        window.SCORM_CONFIG = {
            apiEndpoint: '{{ $apiEndpoint }}',
            timeout: {{ \Hyperf\Config\config('scorm.player.timeout', 30000) }},
            debug: {{ \Hyperf\Config\config('scorm.player.debug', true) ? 'true' : 'false' }},
            autoCommitInterval: {{ \Hyperf\Config\config('scorm.tracking.auto_commit_interval', 30) }} * 1000
        };

        window.packageId = '{{ $package->id }}';

        console.log('[SCORM] Initializing player...');
        console.log('[SCORM] API Endpoint:', window.SCORM_CONFIG.apiEndpoint);
    </script>

    <script>

        /*
SCORM API Implementation for Hyperf Package
Compatible with SCORM 1.2 and SCORM 2004
Integrates with Hyperf backend API endpoints
*/

        (function() {
            'use strict';

            var initialized = false;
            var terminated = false;
            var lastError = "0";
            var sessionStartTime = new Date();
            var apiCalls = 0;
            var pendingData = {};
            var pendingCommits = []; // Track all active commit requests

            var config = window.SCORM_CONFIG || {};
            var apiEndpoint = config.apiEndpoint;
            var user = {
                id: null,
                name: "Guest",
                session_token:  null
            };
            // var debug = config.debug || false; //notice for test
            var debug = true;

            // SCORM data storage - initialize with proper defaults
            var data = {
                "cmi.core.student_id": null,
                "cmi.core.student_name": null,
                "cmi.core.lesson_location": "",
                "cmi.core.credit": "credit",
                "cmi.core.lesson_status": "not attempted",
                "cmi.core.entry": "",
                "cmi.core.score.raw": "",
                "cmi.core.score.max": "100",
                "cmi.core.score.min": "0",
                "cmi.core.total_time": "0000:00:00",
                "cmi.core.lesson_mode": "normal",
                "cmi.core.exit": "",
                "cmi.core.session_time": "0000:00:00",
                "cmi.suspend_data": "",
                "cmi.launch_data": "",
                "cmi.comments": "",
                "cmi.comments_from_lms": "",
                "cmi.interactions._count": "0",
                "cmi.objectives._count": "0"
            };

            var interactions = {};
            var objectives = {};

            // Debug logging
            function debugLog(message) {
                if (debug) {
                    console.log('[SCORM API] ' + message);
                }
            }

            function saveDataToServer() {
                if (!user.session_token) {
                    debugLog('No attempt ID, cannot save data');
                    return Promise.resolve();
                }
                var cmiData = Object.assign({}, data, interactions, objectives, pendingData);
                var result = window.scormNormalizer.normalize(cmiData);
                var compactVersion = window.scormNormalizer.createCompactVersion(result)
                var apiUrl = generateApiUrl('commit');

                // Create promise and track it
                var commitPromise = fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    keepalive: true, // Keep request alive even if page/iframe is closed
                    body: JSON.stringify({
                        ...compactVersion
                    })
                }).then(function(response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                    }
                    return response.json();
                }).then(function(result) {
                    if (result.status === 200) {
                        pendingData = {};
                        debugLog('Data saved successfully to server');
                    } else {
                        throw new Error(result.message || 'Server error');
                    }
                }).catch(function(error) {
                    console.error('Failed to save SCORM data:', error);
                }).finally(function() {
                    // Remove from pending commits when done
                    var index = pendingCommits.indexOf(commitPromise);
                    if (index > -1) {
                        pendingCommits.splice(index, 1);
                    }
                });

                // Track this commit
                pendingCommits.push(commitPromise);

                return commitPromise;
            }

            function loadDataFromServerSync(parameter) {
                try {
                    var xhr = new XMLHttpRequest();
                    var apiUrl = `${apiEndpoint}/${window.packageId}/initialize`;
                    xhr.open('GET', apiUrl, false); // async: false
                    xhr.setRequestHeader('Content-Type', 'application/json');
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.send(JSON.stringify({
                        action: 'initialize',
                        parameter: parameter
                    }));

                    if (xhr.status === 200) {
                        var result = JSON.parse(xhr.responseText);
                        user = result.data.student;
                        var denormalizedData = window.scormNormalizer.denormalize(result.data);
                        Object.assign(data, denormalizedData);
                        debugLog('Session data loaded from server synchronously');
                    }
                } catch (e) {
                    debugLog('Failed to load data from server synchronously: ' + e.message);
                }
            }

            // SCORM 1.2 API
            window.API = {
                LMSInitialize: function(parameter) {
                    apiCalls++;
                    debugLog('LMSInitialize called with parameter: ' + parameter);

                    if (parameter !== "") {
                        lastError = "201"; // Invalid argument
                        return "false";
                    }

                    if (initialized) {
                        lastError = "101"; // Already initialized
                        return "false";
                    }

                    // data["cmi.core.student_id"] = user.id;
                    // data["cmi.core.student_name"] = user.name;
                    data["cmi.core.lesson_mode"] = "normal";

                    loadDataFromServerSync(parameter);

                    initialized = true;
                    terminated = false;
                    sessionStartTime = new Date();
                    lastError = "0";

                    debugLog('SCORM session initialized');
                    updateDebugPanel();

                    return "true";
                },

                LMSFinish: function(parameter) {
                    apiCalls++;
                    debugLog('LMSFinish called with parameter: ' + parameter);

                    if (parameter !== "") {
                        lastError = "201"; // Invalid argument
                        return "false";
                    }

                    if (!initialized || terminated) {
                        lastError = "301"; // Not initialized or already terminated
                        return "false";
                    }

                    // Calculate session time
                    var sessionTime = new Date() - sessionStartTime;
                    var hours = Math.floor(sessionTime / 3600000);
                    var minutes = Math.floor((sessionTime % 3600000) / 60000);
                    var seconds = Math.floor((sessionTime % 60000) / 1000);

                    data["cmi.core.session_time"] =
                        String(hours).padStart ? String(hours).padStart(4, '0') + ':' +
                            String(minutes).padStart(2, '0') + ':' +
                            String(seconds).padStart(2, '0') :
                            hours + ':' + minutes + ':' + seconds;

                    // Save final data
                    saveDataToServer();

                    // Terminate session on server
                    // if (user.sessionToken) {
                    //     fetch(apiEndpoint + '/session/' + user.sessionToken + '/terminate', {
                    //         method: 'POST',
                    //         headers: {
                    //             'Content-Type': 'application/json',
                    //             'X-Requested-With': 'XMLHttpRequest'
                    //         },
                    //         body: JSON.stringify({
                    //             action: 'terminate',
                    //             parameter: parameter
                    //         })
                    //     }).catch(function(error) {
                    //         console.error('Failed to terminate session:', error);
                    //     });
                    // }

                    terminated = true;
                    lastError = "0";

                    debugLog('SCORM session terminated');
                    updateDebugPanel();
                    return "true";
                },

                LMSGetValue: function(element) {
                    apiCalls++;

                    if (!initialized || terminated) {
                        lastError = "301";
                        return "";
                    }

                    var value = "";

                    // Handle core data
                    if (data.hasOwnProperty(element)) {
                        value = data[element];
                    }
                    // Handle interactions
                    else if (element.indexOf("cmi.interactions.") === 0) {
                        value = interactions[element] || "";
                    }
                    // Handle objectives
                    else if (element.indexOf("cmi.objectives.") === 0) {
                        value = objectives[element] || "";
                    }
                    else {
                        lastError = "401"; // Not implemented
                        return "";
                    }

                    debugLog('LMSGetValue(' + element + ') = ' + value);
                    lastError = "0";
                    updateDebugPanel();
                    return value;
                },

                LMSSetValue: function(element, value) {
                    apiCalls++;
                    debugLog('LMSSetValue(' + element + ', ' + value + ')');

                    if (!initialized || terminated) {
                        lastError = "301";
                        return "false";
                    }

                    // Validate lesson status
                    if (element === "cmi.core.lesson_status") {
                        var validStatuses = ["passed", "completed", "failed", "incomplete", "browsed", "not attempted"];
                        if (validStatuses.indexOf(value) === -1) {
                            lastError = "405"; // Incorrect data type
                            return "false";
                        }
                    }

                    // Store value
                    if (element.indexOf("cmi.interactions.") === 0) {
                        interactions[element] = value;

                        // Update interaction count
                        var match = element.match(/^cmi\.interactions\.(\d+)\./);
                        if (match) {
                            var index = parseInt(match[1]);
                            var count = parseInt(data["cmi.interactions._count"]);
                            if (index >= count) {
                                data["cmi.interactions._count"] = (index + 1).toString();
                            }
                        }
                    } else if (element.indexOf("cmi.objectives.") === 0) {
                        objectives[element] = value;

                        // Update objective count
                        var match = element.match(/^cmi\.objectives\.(\d+)\./);
                        if (match) {
                            var index = parseInt(match[1]);
                            var count = parseInt(data["cmi.objectives._count"]);
                            if (index >= count) {
                                data["cmi.objectives._count"] = (index + 1).toString();
                            }
                        }
                    } else {
                        data[element] = value;
                    }

                    // Add to pending data for server sync
                    pendingData[element] = value;

                    lastError = "0";
                    updateDebugPanel();
                    return "true";
                },

                LMSCommit: function(parameter) {
                    apiCalls++;
                    debugLog('LMSCommit called with parameter: ' + parameter);
                    if (parameter !== "") {
                        lastError = "201";
                        return "false";
                    }

                    if (!initialized || terminated) {
                        lastError = "301";
                        return "false";
                    }

                    saveDataToServer();
                    lastError = "0";
                    updateDebugPanel();
                    return "true";
                },

                LMSGetLastError: function() {
                    return lastError;
                },

                LMSGetErrorString: function(errorCode) {
                    var errors = {
                        "0": "No Error",
                        "101": "General Exception",
                        "201": "Invalid argument error",
                        "301": "Not initialized",
                        "401": "Not implemented error",
                        "405": "Incorrect Data Type"
                    };
                    return errors[errorCode] || "Unknown Error";
                },

                LMSGetDiagnostic: function(errorCode) {
                    return this.LMSGetErrorString(errorCode);
                }
            };

            // SCORM 2004 API (maps to SCORM 1.2)
            window.API_1484_11 = {
                Initialize: function(parameter) {
                    return window.API.LMSInitialize(parameter);
                },

                Terminate: function(parameter) {
                    return window.API.LMSFinish(parameter);
                },

                GetValue: function(element) {
                    // Map SCORM 2004 elements to SCORM 1.2
                    let mappings = {
                        "cmi.learner_id": "cmi.core.student_id",
                        "cmi.learner_name": "cmi.core.student_name",
                        "cmi.location": "cmi.core.lesson_location",
                        "cmi.completion_status": "cmi.core.lesson_status",
                        "cmi.success_status": "cmi.core.lesson_status",
                        "cmi.score.raw": "cmi.core.score.raw",
                        "cmi.score.max": "cmi.core.score.max",
                        "cmi.score.min": "cmi.core.score.min",
                        "cmi.exit": "cmi.core.exit",
                        "cmi.session_time": "cmi.core.session_time"
                    };

                    let mappedElement = mappings[element] || element;
                    return window.API.LMSGetValue(mappedElement);
                },

                SetValue: function(element, value) {
                    var mappings = {
                        "cmi.location": "cmi.core.lesson_location",
                        "cmi.completion_status": "cmi.core.lesson_status",
                        "cmi.success_status": "cmi.core.lesson_status",
                        "cmi.score.raw": "cmi.core.score.raw",
                        "cmi.score.max": "cmi.core.score.max",
                        "cmi.score.min": "cmi.core.score.min",
                        "cmi.exit": "cmi.core.exit",
                        "cmi.session_time": "cmi.core.session_time"
                    };

                    var mappedElement = mappings[element] || element;
                    return window.API.LMSSetValue(mappedElement, value);
                },

                Commit: function(parameter) {
                    return window.API.LMSCommit(parameter);
                },

                GetLastError: function() {
                    return window.API.LMSGetLastError();
                },

                GetErrorString: function(errorCode) {
                    return window.API.LMSGetErrorString(errorCode);
                },

                GetDiagnostic: function(errorCode) {
                    return window.API.LMSGetDiagnostic(errorCode);
                }
            };

            // Auto-commit at intervals
            if (config.autoCommitInterval > 0) {
                setInterval(function() {
                    if (initialized && !terminated && Object.keys(pendingData).length > 0) {
                        window.API.LMSCommit("");
                    }
                }, config.autoCommitInterval);
            }

            // Commit before page unload
            window.addEventListener('beforeunload', function() {
                if (initialized && !terminated) {
                    window.API.LMSCommit("");
                    window.API.LMSFinish("");
                }
            });

            // Debug panel update function
            function updateDebugPanel() {
                if (debug && typeof window.updateDebugPanel === 'function') {
                    window.updateDebugPanel();
                }
            }

            function generateApiUrl(action) {
                return `${apiEndpoint}/${window.packageId}/${action}/${user.session_token}`;
            }

            // Make debug data available globally
            window.scormApiDebugInfo = function() {
                return {
                    initialized: initialized,
                    terminated: terminated,
                    lastError: lastError,
                    apiCalls: apiCalls,
                    pendingDataCount: Object.keys(pendingData).length,
                    sessionToken: user.session_token
                };
            };

            // Export function to wait for all pending commits
            window.waitForScormCommits = function() {
                if (pendingCommits.length === 0) {
                    debugLog('No pending commits to wait for');
                    return Promise.resolve();
                }

                debugLog('Waiting for ' + pendingCommits.length + ' pending commits...');
                return Promise.all(pendingCommits).then(function() {
                    debugLog('All commits completed successfully');
                }).catch(function(error) {
                    console.error('Some commits failed:', error);
                });
            };

            // Export function to check if there are pending commits
            window.hasScormPendingCommits = function() {
                return pendingCommits.length > 0;
            };

            // Export function to force synchronous save (fallback for critical situations)
            window.forceScormCommitSync = function() {
                if (!user.session_token) {
                    debugLog('No attempt ID, cannot save data');
                    return false;
                }

                try {
                    debugLog('Forcing synchronous commit...');
                    var cmiData = Object.assign({}, data, interactions, objectives, pendingData);
                    var result = window.scormNormalizer.normalize(cmiData);
                    var compactVersion = window.scormNormalizer.createCompactVersion(result);
                    var apiUrl = generateApiUrl('commit');

                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', apiUrl, false); // Synchronous request
                    xhr.setRequestHeader('Content-Type', 'application/json');
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.send(JSON.stringify(compactVersion));

                    if (xhr.status === 200) {
                        var responseData = JSON.parse(xhr.responseText);
                        if (responseData.status === 200) {
                            pendingData = {};
                            debugLog('Synchronous commit successful');
                            return true;
                        }
                    }
                    console.error('Synchronous commit failed with status:', xhr.status);
                    return false;
                } catch (error) {
                    console.error('Synchronous commit error:', error);
                    return false;
                }
            };

            debugLog('SCORM API loaded and ready');

        })();

    </script>

    <script>
        console.log('[SCORM] API Objects available:');
        console.log('[SCORM] window.API:', typeof window.API);
        console.log('[SCORM] window.API_1484_11:', typeof window.API_1484_11);

        if (window.API) {
            console.log('[SCORM] SCORM 1.2 API ready');
        } else {
            console.error('[SCORM] SCORM 1.2 API NOT available!');
        }

        if (window.API_1484_11) {
            console.log('[SCORM] SCORM 2004 API ready');
        } else {
            console.error('[SCORM] SCORM 2004 API NOT available!');
        }
    </script>

    <div id="scorm-container">
        <div id="loading">
            <div class="loading-spinner"></div>
            <div>Loading SCORM content...</div>
            <div style="font-size: 12px; color: #666; margin-top: 10px;">
                Package: {{ $package->title }}
            </div>
        </div>
        <iframe id="scorm-frame" src="{{ $launchUrl }}" style="display:none;"></iframe>
    </div>

    <div id="debug-panel" class="scorm-debug-panel"></div>

    <script>
        class ScormPlayer {
            constructor() {
                this.frame = document.getElementById('scorm-frame');
                this.loading = document.getElementById('loading');
                this.container = document.getElementById('scorm-container');
                this.config = window.SCORM_CONFIG || {};
                this.sessionToken = this.config.sessionToken || null;
                this.debugPanel = document.getElementById('debug-panel');

                this.init();
            }

            init() {
                this.setupFrameHandlers();
                this.setupErrorHandling();
                this.setupKeyboardShortcuts();
                this.setupDebugPanel();
                console.log('SCORM Player initialized');
            }

            getSessionToken() {
                return this.sessionToken;
            }

            setupFrameHandlers() {
                this.frame.onload = () => {
                    console.log('SCORM content loaded');
                    this.hideLoading();
                    this.showFrame();

                    if (this.config.debug) {
                        this.showDebugPanel();
                        this.updateDebugPanel();
                    }
                };

                this.frame.onerror = (error) => {
                    console.error('SCORM frame error:', error);
                    this.showError('Failed to load SCORM content');
                };
            }

            setupErrorHandling() {
                window.onerror = (msg, url, line, col, error) => {
                    console.error('SCORM Player Error:', {msg, url, line, col, error});
                    if (this.config.debug) {
                        this.updateDebugPanel();
                    }
                };

                window.addEventListener('unhandledrejection', (event) => {
                    console.error('Unhandled promise rejection:', event.reason);
                    if (this.config.debug) {
                        this.updateDebugPanel();
                    }
                });
            }

            setupKeyboardShortcuts() {
                document.addEventListener('keydown', (event) => {
                    // F12 for debug panel
                    if (event.key === 'F12' && this.config.debug) {
                        event.preventDefault();
                        this.toggleDebugPanel();
                    }

                    // Escape to close debug panel
                    if (event.key === 'Escape') {
                        this.hideDebugPanel();
                    }
                });
            }

            setupDebugPanel() {
                if (this.config.debug && this.debugPanel) {
                    // Setup debug panel update function
                    window.updateDebugPanel = () => {
                        this.updateDebugPanel();
                    };

                    // Update debug panel every 2 seconds when active
                    setInterval(() => {
                        if (this.debugPanel && this.debugPanel.style.display !== 'none') {
                            this.updateDebugPanel();
                        }
                    }, 2000);
                }
            }

            hideLoading() {
                if (this.loading) {
                    this.loading.style.display = 'none';
                }
            }

            showFrame() {
                if (this.frame) {
                    this.frame.style.display = 'block';
                }
            }

            showError(message) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'scorm-error';
                errorDiv.style.cssText = `
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      text-align: center;
      z-index: 1000;
    `;
                errorDiv.innerHTML = `
            <div class="error-content">
                <h3 style="color: #e74c3c; margin: 0 0 10px 0;">Error</h3>
                <p style="margin: 0 0 15px 0;">${message}</p>
                <button onclick="location.reload()" style="background: #3498db; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">Reload</button>
            </div>
        `;

                this.container.appendChild(errorDiv);
            }

            showDebugPanel() {
                if (this.debugPanel) {
                    this.debugPanel.style.display = 'block';
                    this.updateDebugPanel();
                }
            }

            hideDebugPanel() {
                if (this.debugPanel) {
                    this.debugPanel.style.display = 'none';
                }
            }

            toggleDebugPanel() {
                if (this.debugPanel) {
                    if (this.debugPanel.style.display === 'none') {
                        this.showDebugPanel();
                    } else {
                        this.hideDebugPanel();
                    }
                }
            }

            updateDebugPanel() {
                // if (!this.debugPanel || !this.config.debug) return;

                const debugInfo = window.scormApiDebugInfo ? window.scormApiDebugInfo() : null;
                const api = window.API;

                if (debugInfo && api) {
                    this.debugPanel.innerHTML = `
        <div class="debug-header">
          <strong>SCORM Debug</strong>
          <button class="debug-toggle" onclick="window.scormPlayer.hideDebugPanel()">×</button>
        </div>
        <div class="debug-section">
          <strong>API Status:</strong><br>
          Initialized: ${debugInfo.initialized ? '✓' : '✗'}<br>
          Terminated: ${debugInfo.terminated ? '✓' : '✗'}<br>
          API Calls: ${debugInfo.apiCalls}<br>
          Last Error: ${debugInfo.lastError} (${api.LMSGetErrorString(debugInfo.lastError)})<br>
          Attempt ID: ${debugInfo.attemptId || 'None'}
        </div>
        <div class="debug-section">
          <strong>Data Status:</strong><br>
          Pending Data: ${debugInfo.pendingDataCount} items<br>
          Student ID: ${api.LMSGetValue('cmi.core.student_id')}<br>
          Lesson Status: ${api.LMSGetValue('cmi.core.lesson_status')}<br>
          Location: ${api.LMSGetValue('cmi.core.lesson_location')}<br>
          Score: ${api.LMSGetValue('cmi.core.score.raw')}
        </div>
        <div class="debug-section">
          <button onclick="window.API.LMSCommit('')" style="background: #27ae60; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; margin-right: 5px;">Force Commit</button>
          <button onclick="console.log(window.scormApiDebugInfo())" style="background: #f39c12; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer;">Log Info</button>
        </div>
      `;
                } else {
                    this.debugPanel.innerHTML = `
        <div class="debug-header">
          <strong>SCORM Debug</strong>
          <button class="debug-toggle" onclick="window.scormPlayer.hideDebugPanel()">×</button>
        </div>
        <div class="debug-section">
          <strong style="color: #e74c3c;">SCORM API not available</strong><br>
          Check console for errors.
        </div>
      `;
                }
            }
        }
        class ScormNormalizer {
            constructor() {
            }

            /**
             * Main method for SCORM data normalization
             * @param {Object} scormData - raw SCORM data
             * @returns {Object} - normalized data
             */
            normalize(scormData) {
                return {
                    student: this.extractStudentInfo(scormData),
                    lesson: this.extractLessonInfo(scormData),
                    score: this.extractScoreInfo(scormData),
                    interactions: this.extractInteractions(scormData),
                    objectives: this.extractObjectives(scormData),
                    session: this.extractSessionInfo(scormData),
                    metadata: this.extractMetadata(scormData)
                };

                // return this.cleanEmptyFields(normalized);
            }

            /**
             * Extracts student information
             */
            extractStudentInfo(data) {
                return {
                    id: data['cmi.core.student_id'] || null,
                    name: data['cmi.core.student_name'] || null
                };
            }

            /**
             * Extracts lesson information
             */
            extractLessonInfo(data) {
                return {
                    status: data['cmi.core.lesson_status'] || 'unknown',
                    location: data['cmi.core.lesson_location'] || null,
                    mode: data['cmi.core.lesson_mode'] || 'normal',
                    entry: data['cmi.core.entry'] || null,
                    exit: data['cmi.core.exit'] || null,
                    credit: data['cmi.core.credit'] || 'no-credit'
                };
            }

            /**
             * Extracts score information
             */
            extractScoreInfo(data) {
                const scoreData = {
                    raw: this.parseNumber(data['cmi.core.score.raw']),
                    max: this.parseNumber(data['cmi.core.score.max']),
                    min: this.parseNumber(data['cmi.core.score.min']),
                    scaled: this.parseNumber(data['cmi.core.score.scaled'])
                };

                // Calculate percentage if possible
                if (scoreData.raw !== null && scoreData.max !== null && scoreData.max > 0) {
                    scoreData.percentage = Math.round((scoreData.raw / scoreData.max) * 100);
                }

                return scoreData;
            }

            /**
             * Extracts interactions
             */
            extractInteractions(data) {
                const interactions = [];
                const count = parseInt(data['cmi.interactions._count']) || 0;

                for (let i = 0; i < count; i++) {
                    const interaction = {
                        id: data[`cmi.interactions.${i}.id`],
                        type: data[`cmi.interactions.${i}.type`],
                        description: this.cleanText(data[`cmi.interactions.${i}.description`]),
                        learnerResponse: this.parseResponse(data[`cmi.interactions.${i}.learner_response`]),
                        correctResponse: this.parseCorrectResponse(data[`cmi.interactions.${i}.correct_responses.0.pattern`]),
                        result: data[`cmi.interactions.${i}.result`],
                        weighting: this.parseNumber(data[`cmi.interactions.${i}.weighting`]),
                        latency: this.parseISO8601Duration(data[`cmi.interactions.${i}.latency`]),
                        timestamp: data[`cmi.interactions.${i}.timestamp`]
                    };

                    if (data[`cmi.interactions.${i}.objectives.0.id`]) {
                        interaction.objectives = [{
                            id: data[`cmi.interactions.${i}.objectives.0.id`]
                        }];
                    }

                    interactions.push(interaction);
                }

                return interactions;
            }

            /**
             * Extracts objectives
             */
            extractObjectives(data) {
                const objectives = [];
                const count = parseInt(data['cmi.objectives._count']) || 0;

                for (let i = 0; i < count; i++) {
                    objectives.push({
                        id: data[`cmi.objectives.${i}.id`],
                        score: {
                            raw: this.parseNumber(data[`cmi.objectives.${i}.score.raw`]),
                            max: this.parseNumber(data[`cmi.objectives.${i}.score.max`]),
                            min: this.parseNumber(data[`cmi.objectives.${i}.score.min`])
                        },
                        status: data[`cmi.objectives.${i}.status`]
                    });
                }

                return objectives;
            }

            /**
             * Extracts session information
             */
            extractSessionInfo(data) {
                return {
                    totalTime: this.parseISO8601Duration(data['cmi.core.total_time']) || 0,
                    sessionTime: this.parseISO8601Duration(data['cmi.core.session_time']),
                    sessionTimeSeconds: this.parseISO8601Duration(data['cmi.core.session_time']),
                    suspendData: Array.isArray(data['cmi.suspend_data']) ? JSON.stringify(data['cmi.suspend_data']) : data['cmi.suspend_data'],
                    launchData: data['cmi.launch_data'] || null,
                    comments: data['cmi.comments'] || null,
                    commentsFromLms: data['cmi.comments_from_lms'] || null
                };
            }

            /**
             * Extracts metadata
             */
            extractMetadata(data) {
                return {
                    processedAt: new Date().toISOString(),
                    scormVersion: '1.2', // can be determined automatically
                    totalInteractions: parseInt(data['cmi.interactions._count']) || 0,
                    totalObjectives: parseInt(data['cmi.objectives._count']) || 0
                };
            }

            /**
             * Parses numeric values
             */
            parseNumber(value) {
                if (value === '' || value === null || value === undefined) return null;
                const parsed = parseFloat(value);
                return isNaN(parsed) ? null : parsed;
            }

            /**
             * Parses learner responses
             */
            parseResponse(response) {
                if (!response) return [];
                return response.split('[,]').map(item => item.trim());
            }

            /**
             * Parses correct responses
             */
            parseCorrectResponse(response) {
                if (!response) return [];
                return response.split('[,]').map(item => item.trim());
            }

            /**
             * Parses ISO 8601 duration to seconds
             */
            parseISO8601Duration(duration) {
                if (!duration || !duration.startsWith('PT')) return 0;

                let seconds = 0;
                const matches = duration.match(/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+(?:\.\d+)?)S)?/);

                if (matches) {
                    seconds += parseInt(matches[1] || 0) * 3600;
                    seconds += parseInt(matches[2] || 0) * 60;
                    seconds += parseFloat(matches[3] || 0);
                }

                return Math.round(seconds);
            }

            /**
             * Cleans text from extra characters
             */
            cleanText(text) {
                if (!text) return null;
                return text.replace(/â€™/g, "'")
                    .replace(/Â/g, " ")
                    .replace(/\r/g, " ")
                    .trim();
            }

            /**
             * Creates compact version for server transmission
             */
            createCompactVersion(normalizedData) {
                return {
                    student_id: normalizedData.student.id,
                    student_name: normalizedData.student.name || 'Guest',
                    score: normalizedData.score.raw,
                    score_percentage: normalizedData.score.percentage,
                    session: {
                        total_time: normalizedData.session.totalTime,
                        session_time: normalizedData.session.sessionTime,
                        session_time_seconds: normalizedData.session.sessionTimeSeconds,
                        suspend_data: normalizedData.session.suspendData,
                        comments: normalizedData.session.comments,
                        comments_from_lms: normalizedData.session.commentsFromLms,
                        launch_data: normalizedData.session.launchData
                    },
                    lesson: {
                        status: normalizedData.lesson.status,
                        location: normalizedData.lesson.location,
                        mode: normalizedData.lesson.mode,
                        entry: normalizedData.lesson.entry,
                        exit: normalizedData.lesson.exit,
                        credit: normalizedData.lesson.credit
                    },
                    interactions: normalizedData.interactions.map(i => ({
                        id: i.id,
                        type: i.type,
                        description: i.description,
                        learner_response: i.learnerResponse,
                        correct_response: i.correctResponse,
                        result: i.result,
                        weighting: i.weighting,
                        latency_seconds: i.latency,
                        interaction_timestamp: i.timestamp,
                        objectives: i.objectives || []
                    })),
                    completedAt: normalizedData.metadata.processedAt
                };
            }

            /**
             * Converts normalized data back to SCORM format
             * @param {Object} normalizedData - normalized data
             * @returns {Object} - data in SCORM format
             */
            denormalize(normalizedData) {
                const scormData = {};
                if (normalizedData.student) {
                    scormData['cmi.core.student_id'] = normalizedData.student.id || '';
                    scormData['cmi.core.student_name'] = normalizedData.student.name || '';
                }

                if (normalizedData.lesson) {
                    scormData['cmi.core.lesson_status'] = normalizedData.lesson.status || 'incomplete';
                    scormData['cmi.core.lesson_location'] = normalizedData.lesson.location || '';
                    scormData['cmi.core.lesson_mode'] = normalizedData.lesson.mode || 'normal';
                    scormData['cmi.core.entry'] = normalizedData.lesson.entry || '';
                    scormData['cmi.core.exit'] = normalizedData.lesson.exit || '';
                    scormData['cmi.core.credit'] = normalizedData.lesson.credit || 'credit';
                }

                if (normalizedData.score) {
                    scormData['cmi.core.score.raw'] = normalizedData.score.raw ? normalizedData.score.raw.toString() : '';
                    scormData['cmi.core.score.max'] = normalizedData.score.max ? normalizedData.score.max.toString() : '';
                    scormData['cmi.core.score.min'] = normalizedData.score.min ? normalizedData.score.min.toString() : '';
                    scormData['cmi.score.scaled'] = normalizedData.score.scaled ? normalizedData.score.scaled.toString() : '';
                }

                if (normalizedData.session) {
                    scormData['cmi.core.total_time'] = this.formatTotalTime(normalizedData.session.total_time) || '0000:00:00';
                    scormData['cmi.core.session_time'] = this.encodeISO8601Duration(normalizedData.session.session_time) || 'PT0S';
                    scormData['cmi.suspend_data'] = normalizedData.session.suspend_data || '';
                    scormData['cmi.launch_data'] = normalizedData.session.launch_data || '';
                    scormData['cmi.comments'] = normalizedData.session.comments || '';
                    scormData['cmi.comments_from_lms'] = normalizedData.session.comments_from_lms || '';
                }

                if (normalizedData.interactions && Array.isArray(normalizedData.interactions)) {
                    scormData['cmi.interactions._count'] = normalizedData.interactions.length.toString();

                    normalizedData.interactions.forEach((interaction, index) => {
                        scormData[`cmi.interactions.${index}.id`] = interaction.id || '';
                        scormData[`cmi.interactions.${index}.type`] = interaction.type || 'choice';
                        scormData[`cmi.interactions.${index}.description`] = this.encodeText(interaction.description) || '';
                        scormData[`cmi.interactions.${index}.learner_response`] = this.encodeResponse(interaction.learnerResponse);
                        scormData[`cmi.interactions.${index}.correct_responses.0.pattern`] = this.encodeResponse(interaction.correctResponse);
                        scormData[`cmi.interactions.${index}.result`] = interaction.result || '';
                        scormData[`cmi.interactions.${index}.weighting`] = interaction.weighting ? interaction.weighting.toString() : '';
                        scormData[`cmi.interactions.${index}.latency`] = this.encodeISO8601Duration(interaction.latency);
                        scormData[`cmi.interactions.${index}.timestamp`] = interaction.timestamp || '';

                        // Objectives для interaction
                        if (interaction.objectives && interaction.objectives.length > 0) {
                            scormData[`cmi.interactions.${index}.objectives.0.id`] = interaction.objectives[0].id || '';
                        }
                    });
                } else {
                    scormData['cmi.interactions._count'] = '0';
                }

                if (normalizedData.objectives && Array.isArray(normalizedData.objectives)) {
                    scormData['cmi.objectives._count'] = normalizedData.objectives.length.toString();

                    normalizedData.objectives.forEach((objective, index) => {
                        scormData[`cmi.objectives.${index}.id`] = objective.id || '';
                        scormData[`cmi.objectives.${index}.status`] = objective.status || '';

                        if (objective.score) {
                            scormData[`cmi.objectives.${index}.score.raw`] = objective.score.raw ? objective.score.raw.toString() : '';
                            scormData[`cmi.objectives.${index}.score.max`] = objective.score.max ? objective.score.max.toString() : '';
                            scormData[`cmi.objectives.${index}.score.min`] = objective.score.min ? objective.score.min.toString() : '';
                        }
                    });
                } else {
                    scormData['cmi.objectives._count'] = '0';
                }

                return scormData;
            }

            /**
             * Encodes text back to SCORM format with special characters
             */
            encodeText(text) {
                if (!text) return '';
                return text.replace(/'/g, 'â€™')
                    .replace(/ /g, 'Â ')
                    .replace(/\n/g, '\r');
            }

            /**
             * Encodes response array into SCORM format string
             */
            encodeResponse(responseArray) {
                if (!responseArray || !Array.isArray(responseArray)) return '';
                return responseArray.join('[,]');
            }

            /**
             * Converts seconds to ISO 8601 duration
             */
            encodeISO8601Duration(seconds) {
                if (!seconds || seconds === 0) return 'PT0S';

                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const secs = seconds % 60;

                let duration = 'PT';
                if (hours > 0) duration += `${hours}H`;
                if (minutes > 0) duration += `${minutes}M`;
                if (secs > 0) {
                    // If there's a decimal part, preserve it
                    if (secs % 1 !== 0) {
                        duration += `${secs.toFixed(2)}S`;
                    } else {
                        duration += `${secs}S`;
                    }
                }

                return duration === 'PT' ? 'PT0S' : duration;
            }

            /**
             * Converts seconds to SCORM total time format HHHH:MM:SS
             */
            formatTotalTime(seconds) {
                if (!seconds || seconds === 0) return '0000:00:00';

                const totalSeconds = Math.floor(seconds);
                const hours = Math.floor(totalSeconds / 3600);
                const minutes = Math.floor((totalSeconds % 3600) / 60);
                const secs = totalSeconds % 60;

                // Format as HHHH:MM:SS
                const hoursStr = hours.toString().padStart(4, '0');
                const minutesStr = minutes.toString().padStart(2, '0');
                const secsStr = secs.toString().padStart(2, '0');

                return `${hoursStr}:${minutesStr}:${secsStr}`;
            }

            /**
             * Creates SCORM format data from compact version
             */
            createScormFromCompact(compactData) {
                const normalizedData = {
                    student: {
                        id: compactData.studentId || '',
                        name: compactData.studentName || ''
                    },
                    lesson: {
                        status: compactData.lessonStatus || 'incomplete',
                        mode: 'normal',
                        exit: compactData.lessonStatus === 'completed' ? 'normal' : 'suspend',
                        credit: 'credit'
                    },
                    score: {
                        raw: compactData.score || 0,
                        max: 100,
                        min: 0,
                        scaled: compactData.score ? (compactData.score / 100) : 0,
                        percentage: compactData.scorePercentage || 0
                    },
                    interactions: compactData.interactions ? compactData.interactions.map((interaction, index) => ({
                        id: interaction.id || `interaction_${index}`,
                        type: 'choice',
                        description: interaction.description || '',
                        learnerResponse: Array.isArray(interaction.response) ? interaction.response : [interaction.response].filter(Boolean),
                        correctResponse: Array.isArray(interaction.correctResponse) ? interaction.correctResponse : [],
                        result: interaction.result || 'unknown',
                        weighting: interaction.weighting || 10,
                        latency: interaction.latency || 0,
                        timestamp: interaction.timestamp || compactData.completedAt || new Date().toISOString()
                    })) : [],
                    objectives: [],
                    session: {
                        totalTime: '0000:00:00',
                        sessionTime: this.encodeISO8601Duration(compactData.sessionTime || 0),
                        sessionTimeSeconds: compactData.sessionTime || 0,
                        suspendData: compactData.suspendData || '',
                        launchData: '',
                        comments: '',
                        commentsFromLms: ''
                    },
                    metadata: {
                        processedAt: compactData.completedAt || new Date().toISOString(),
                        scormVersion: '1.2',
                        totalInteractions: compactData.interactions ? compactData.interactions.length : 0,
                        totalObjectives: 0
                    }
                };

                return this.denormalize(normalizedData);
            }

            /**
             * NEW METHOD: Converts compact version directly back to SCORM format
             * This is what you need for: const compact = normalizer.createCompactVersion(result);
             * @param {Object} compactData - result of createCompactVersion()
             * @returns {Object} - data in SCORM format
             */
            convertCompactBackToScorm(compactData) {
                const scormResult = {};

                scormResult['cmi.core.student_id'] = compactData.studentId || '';
                scormResult['cmi.core.student_name'] = compactData.studentName || '';
                scormResult['cmi.core.lesson_status'] = compactData.lessonStatus || 'incomplete';
                scormResult['cmi.core.lesson_location'] = compactData.lesson.lesson_location || '';
                scormResult['cmi.core.lesson_mode'] = 'normal';
                scormResult['cmi.core.entry'] = '';
                scormResult['cmi.core.exit'] = compactData.lessonStatus === 'completed' ? 'normal' : 'suspend';
                scormResult['cmi.core.credit'] = 'credit';

                scormResult['cmi.core.score.raw'] = compactData.score ? compactData.score.toString() : '0';
                scormResult['cmi.core.score.max'] = '100';
                scormResult['cmi.core.score.min'] = '0';
                scormResult['cmi.score.scaled'] = compactData.score ? (compactData.score / 100).toFixed(4) : '0';

                scormResult['cmi.core.total_time'] = '0000:00:00';
                scormResult['cmi.core.session_time'] = this.encodeISO8601Duration(compactData.sessionTime || 0);
                scormResult['cmi.suspend_data'] = compactData.suspendData || '';
                scormResult['cmi.launch_data'] = '';
                scormResult['cmi.comments'] = '';
                scormResult['cmi.comments_from_lms'] = '';

                if (compactData.interactions && Array.isArray(compactData.interactions)) {
                    scormResult['cmi.interactions._count'] = compactData.interactions.length.toString();

                    compactData.interactions.forEach((interaction, index) => {
                        scormResult[`cmi.interactions.${index}.id`] = interaction.id || `interaction_${index}`;
                        scormResult[`cmi.interactions.${index}.type`] = 'choice';
                        scormResult[`cmi.interactions.${index}.description`] = interaction.description || '';
                        scormResult[`cmi.interactions.${index}.learner_response`] = this.encodeResponse(interaction.response);
                        scormResult[`cmi.interactions.${index}.correct_responses.0.pattern`] = this.encodeResponse(interaction.correctResponse);
                        scormResult[`cmi.interactions.${index}.result`] = interaction.result || 'unknown';
                        scormResult[`cmi.interactions.${index}.weighting`] = interaction.weighting ? interaction.weighting.toString() : '10';
                        scormResult[`cmi.interactions.${index}.latency`] = this.encodeISO8601Duration(interaction.latency || 0);
                        scormResult[`cmi.interactions.${index}.timestamp`] = interaction.timestamp || compactData.completedAt || new Date().toISOString();

                        // Objectives
                        if (interaction.objectives && interaction.objectives.length > 0) {
                            scormResult[`cmi.interactions.${index}.objectives.0.id`] = interaction.objectives[0].id;
                        } else {
                            scormResult[`cmi.interactions.${index}.objectives.0.id`] = 'main_objective';
                        }
                    });
                } else {
                    scormResult['cmi.interactions._count'] = '0';
                }

                // Objectives
                scormResult['cmi.objectives._count'] = '0';

                return scormResult;
            }
        }
        // Initialize player when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            window.scormPlayer = new ScormPlayer();
            window.scormNormalizer = new ScormNormalizer();
        });

    </script>
@endsection

@section('scripts')
    <!-- Additional scripts can go here -->
@endsection
