/*
SCORM API Implementation for Hyperf Package
Compatible with SCORM 1.2 and SCORM 2004
Integrates with Hyperf backend API endpoints
*/

(function () {
    'use strict';

    var initialized = false;
    var terminated = false;
    var lastError = "0";
    var sessionStartTime = new Date();
    var apiCalls = 0;
    var pendingData = {};

    var config = window.SCORM_CONFIG || {};
    var apiEndpoint = config.apiEndpoint;
    var user = window.user;
    // var debug = config.debug || false; //notice for test
    var debug = true;

    // SCORM data storage - initialize with proper defaults
    var data = {
        "cmi.core.student_id": user.id,
        "cmi.core.student_name": user.name,
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
    function debugLog(message)
    {
        if (debug) {
            console.log('[SCORM API] ' + message);
        }
    }


    function saveDataToServer()
    {
        if (!user.sessionToken) {
            debugLog('No attempt ID, cannot save data');
            return Promise.resolve();
        }
        var cmiData = Object.assign({}, data, interactions, objectives, pendingData);
        var result = window.scormNormalizer.normalize(cmiData);
        var compactVersion = window.scormNormalizer.createCompactVersion(result)
        var apiUrl = generateApiUrl('commit');

        return fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },

            body: JSON.stringify({
                ...compactVersion
            })
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status + ': ' + response.statusText);
            }
            return response.json();
        }).then(function (result) {
            if (result.status === 200) {
                pendingData = {};
                debugLog('Data saved successfully to server');
            } else {
                throw new Error(result.message || 'Server error');
            }
        }).catch(function (error) {
            console.error('Failed to save SCORM data:', error);
        });
    }

    function loadDataFromServerSync(parameter)
    {
        if (!user.sessionToken) {
            return;
        }
        try {
            var xhr = new XMLHttpRequest();
            var apiUrl = generateApiUrl('initialize');
            xhr.open('GET', apiUrl, false); // async: false
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(JSON.stringify({
                action: 'initialize',
                parameter: parameter
            }));

            if (xhr.status === 200) {
                var result = JSON.parse(xhr.responseText);
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
        LMSInitialize: function (parameter) {
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

            data["cmi.core.student_id"] = user.id;
            data["cmi.core.student_name"] = user.name;
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

        LMSFinish: function (parameter) {
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

        LMSGetValue: function (element) {
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
            } else {
                lastError = "401"; // Not implemented
                return "";
            }

            debugLog('LMSGetValue(' + element + ') = ' + value);
            lastError = "0";
            updateDebugPanel();
            return value;
        },

        LMSSetValue: function (element, value) {
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

        LMSCommit: function (parameter) {
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

        LMSGetLastError: function () {
            return lastError;
        },

        LMSGetErrorString: function (errorCode) {
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

        LMSGetDiagnostic: function (errorCode) {
            return this.LMSGetErrorString(errorCode);
        }
    };

    // SCORM 2004 API (maps to SCORM 1.2)
    window.API_1484_11 = {
        Initialize: function (parameter) {
            return window.API.LMSInitialize(parameter);
        },

        Terminate: function (parameter) {
            return window.API.LMSFinish(parameter);
        },

        GetValue: function (element) {
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

        SetValue: function (element, value) {
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

        Commit: function (parameter) {
            return window.API.LMSCommit(parameter);
        },

        GetLastError: function () {
            return window.API.LMSGetLastError();
        },

        GetErrorString: function (errorCode) {
            return window.API.LMSGetErrorString(errorCode);
        },

        GetDiagnostic: function (errorCode) {
            return window.API.LMSGetDiagnostic(errorCode);
        }
    };

    // Auto-commit at intervals
    if (config.autoCommitInterval > 0) {
        setInterval(function () {
            if (initialized && !terminated && Object.keys(pendingData).length > 0) {
                window.API.LMSCommit("");
            }
        }, config.autoCommitInterval);
    }

    // Commit before page unload
    window.addEventListener('beforeunload', function () {
        if (initialized && !terminated) {
            window.API.LMSCommit("");
            window.API.LMSFinish("");
        }
    });

    // Debug panel update function
    function updateDebugPanel()
    {
        if (debug && typeof window.updateDebugPanel === 'function') {
            window.updateDebugPanel();
        }
    }

    function generateApiUrl(action)
    {
        return `${apiEndpoint} / ${window.packageId} / ${action} / ${user.sessionToken}`;
    }

    // Make debug data available globally
    window.scormApiDebugInfo = function () {
        return {
            initialized: initialized,
            terminated: terminated,
            lastError: lastError,
            apiCalls: apiCalls,
            pendingDataCount: Object.keys(pendingData).length,
            sessionToken: user.sessionToken
        };
    };

    debugLog('SCORM API loaded and ready');

})();
