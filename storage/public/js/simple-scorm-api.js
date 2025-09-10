/*
Simple, Complete SCORM API Implementation
Works with any SCORM course - no complications
*/

(function() {
    'use strict';

    var initialized = false;
    var terminated = false;
    var lastError = "0";
    var sessionStartTime = new Date();
    // var normalizer = new SCORMNormalizer();


    // SCORM data storage
    var data = {
        "cmi.core.student_id": "",
        "cmi.core.student_name": "",
        "cmi.core.lesson_location": "",
        "cmi.core.credit": "credit",
        "cmi.core.lesson_status": "not attempted",
        "cmi.core.entry": "",
        "cmi.core.score.raw": "",
        "cmi.core.score.max": "",
        "cmi.core.score.min": "",
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

    // Initialize objectives from manifest
    function initObjectives() {
        var manifestObjectives = window.manifestObjectives || ['obj_playing', 'obj_etiquette', 'obj_handicapping', 'obj_havingfun'];

        for (var i = 0; i < manifestObjectives.length; i++) {
            objectives['cmi.objectives.' + i + '.id'] = manifestObjectives[i];
            objectives['cmi.objectives.' + i + '.status'] = 'not attempted';
            objectives['cmi.objectives.' + i + '.score.raw'] = '';
            objectives['cmi.objectives.' + i + '.score.max'] = '';
            objectives['cmi.objectives.' + i + '.score.min'] = '';
        }

        data['cmi.objectives._count'] = manifestObjectives.length.toString();
    }

    function saveData() {
        if (!window.courseId || !window.learnerName) return;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/save-scorm-data.php', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify({
            courseId: window.courseId,
            learnerName: window.learnerName,
            data: data,
            interactions: interactions,
            objectives: objectives
        }));
    }

    function loadData() {
        if (!window.courseId || !window.learnerName) return;

        try {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'api/get-scorm-data.php?courseId=' + encodeURIComponent(window.courseId) + '&learnerName=' + encodeURIComponent(window.learnerName), false);
            xhr.send();

            if (xhr.status === 200) {
                var savedData = JSON.parse(xhr.responseText);
                if (savedData && savedData.data) {
                    Object.assign(data, savedData.data);
                }
                if (savedData && savedData.interactions) {
                    Object.assign(interactions, savedData.interactions);
                }
                if (savedData && savedData.objectives) {
                    Object.assign(objectives, savedData.objectives);
                }
            }
        } catch (e) {
            // Ignore errors, start fresh
        }
    }

    // SCORM 1.2 API
    window.API = {
        LMSInitialize: function(parameter) {
            if (parameter !== "") {
                lastError = "201";
                return "false";
            }

            if (initialized) {
                lastError = "101";
                return "false";
            }

            data["cmi.core.student_id"] = window.learnerId || "guest";
            data["cmi.core.student_name"] = window.learnerName || "Guest User";

            loadData();
            initObjectives();

            initialized = true;
            terminated = false;
            sessionStartTime = new Date();
            lastError = "0";

            return "true";
        },

        LMSFinish: function(parameter) {
            if (parameter !== "") {
                lastError = "201";
                return "false";
            }

            if (!initialized || terminated) {
                lastError = "301";
                return "false";
            }

            var sessionTime = new Date() - sessionStartTime;
            var hours = Math.floor(sessionTime / 3600000);
            var minutes = Math.floor((sessionTime % 3600000) / 60000);
            var seconds = Math.floor((sessionTime % 60000) / 1000);

            data["cmi.core.session_time"] =
                String(hours).padStart(4, '0') + ':' +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');

            saveData();
            terminated = true;
            lastError = "0";

            return "true";
        },

        LMSGetValue: function(element) {
            if (!initialized || terminated) {
                lastError = "301";
                return "";
            }

            // Handle core data
            if (data.hasOwnProperty(element)) {
                lastError = "0";
                return data[element];
            }

            // Handle interactions
            if (element.indexOf("cmi.interactions.") === 0) {
                lastError = "0";
                return interactions[element] || "";
            }

            // Handle objectives
            if (element.indexOf("cmi.objectives.") === 0) {
                lastError = "0";
                return objectives[element] || "";
            }

            lastError = "401";
            return "";
        },

        LMSSetValue: function(element, value) {
            if (!initialized || terminated) {
                lastError = "301";
                return "false";
            }


            // Validate lesson status
            if (element === "cmi.core.lesson_status") {
                var validStatuses = ["passed", "completed", "failed", "incomplete", "browsed", "not attempted"];
                if (validStatuses.indexOf(value) === -1) {
                    lastError = "405";
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

                // Auto-save interactions immediately
                saveData();
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

            saveData();
            lastError = "0";
            return "true";
        },

        LMSCommit: function(parameter) {
            if (parameter !== "") {
                lastError = "201";
                return "false";
            }

            if (!initialized || terminated) {
                lastError = "301";
                return "false";
            }

          // const result = normalizer.normalize(originalScormData);
          //
          // const compact = normalizer.createCompactVersion(result);

          // const backToScorm = normalizer.convertCompactBackToScorm(compact);
            saveData();
            lastError = "0";
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

    // SCORM 2004 API
    window.API_1484_11 = {
        Initialize: function(parameter) {
            return window.API.LMSInitialize(parameter);
        },

        Terminate: function(parameter) {
            return window.API.LMSFinish(parameter);
        },

        GetValue: function(element) {
            // Map SCORM 2004 to 1.2
            var mappings = {
                "cmi.learner_id": "cmi.core.student_id",
                "cmi.learner_name": "cmi.core.student_name",
                "cmi.location": "cmi.core.lesson_location",
                "cmi.completion_status": "cmi.core.lesson_status",
                "cmi.score.raw": "cmi.core.score.raw",
                "cmi.score.max": "cmi.core.score.max",
                "cmi.score.min": "cmi.core.score.min",
                "cmi.exit": "cmi.core.exit",
                "cmi.session_time": "cmi.core.session_time"
            };

            var mappedElement = mappings[element] || element;
            return window.API.LMSGetValue(mappedElement);
        },

        SetValue: function(element, value) {
            var mappings = {
                "cmi.location": "cmi.core.lesson_location",
                "cmi.completion_status": "cmi.core.lesson_status",
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

})();
