class ScormPlayer {
  constructor() {
    this.frame = document.getElementById('scorm-frame');
    this.loading = document.getElementById('loading');
    this.container = document.getElementById('scorm-container');
    this.config = window.SCORM_CONFIG || {};
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
   * Основной метод нормализации данных SCORM
   * @param {Object} scormData - сырые данные SCORM
   * @returns {Object} - нормализованные данные
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
   * Извлекает информацию о студенте
   */
  extractStudentInfo(data) {
    return {
      id: data['cmi.core.student_id'] || null,
      name: data['cmi.core.student_name'] || null
    };
  }

  /**
   * Извлекает информацию о уроке
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
   * Извлекает информацию о результатах
   */
  extractScoreInfo(data) {
    const scoreData = {
      raw: this.parseNumber(data['cmi.core.score.raw']),
      max: this.parseNumber(data['cmi.core.score.max']),
      min: this.parseNumber(data['cmi.core.score.min']),
      scaled: this.parseNumber(data['cmi.core.score.scaled'])
    };

    // Вычисляем процент, если возможно
    if (scoreData.raw !== null && scoreData.max !== null && scoreData.max > 0) {
      scoreData.percentage = Math.round((scoreData.raw / scoreData.max) * 100);
    }

    return scoreData;
  }

  /**
   * Извлекает взаимодействия (interactions)
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
   * Извлекает цели (objectives)
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
   * Извлекает информацию о сессии
   */
  extractSessionInfo(data) {
    return {
      totalTime: this.parseISO8601Duration(data['cmi.core.total_time']) || 0,
      sessionTime: this.parseISO8601Duration(data['cmi.core.session_time']),
      sessionTimeSeconds: this.parseISO8601Duration(data['cmi.core.session_time']),
      suspendData: data['cmi.suspend_data'] || null, //TODO suspendData if empty then empty array. to fix later
      launchData: data['cmi.launch_data'] || null,
      comments: data['cmi.comments'] || null,
      commentsFromLms: data['cmi.comments_from_lms'] || null
    };
  }

  /**
   * Извлекает метаданные
   */
  extractMetadata(data) {
    return {
      processedAt: new Date().toISOString(),
      scormVersion: '1.2', // можно определить автоматически
      totalInteractions: parseInt(data['cmi.interactions._count']) || 0,
      totalObjectives: parseInt(data['cmi.objectives._count']) || 0
    };
  }

  /**
   * Парсит числовые значения
   */
  parseNumber(value) {
    if (value === '' || value === null || value === undefined) return null;
    const parsed = parseFloat(value);
    return isNaN(parsed) ? null : parsed;
  }

  /**
   * Парсит ответы учащегося
   */
  parseResponse(response) {
    if (!response) return [];
    return response.split('[,]').map(item => item.trim());
  }

  /**
   * Парсит правильные ответы
   */
  parseCorrectResponse(response) {
    if (!response) return [];
    return response.split('[,]').map(item => item.trim());
  }

  /**
   * Парсит ISO 8601 duration в секунды
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
   * Очищает текст от лишних символов
   */
  cleanText(text) {
    if (!text) return null;
    return text.replace(/â€™/g, "'")
      .replace(/Â/g, " ")
      .replace(/\r/g, " ")
      .trim();
  }

  /**
   * Создает компактную версию для отправки на сервер
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
   * Преобразует нормализированные данные обратно в формат SCORM
   * @param {Object} normalizedData - нормализованные данные
   * @returns {Object} - данные в формате SCORM
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
   * Кодирует текст обратно в формат с SCORM символами
   */
  encodeText(text) {
    if (!text) return '';
    return text.replace(/'/g, 'â€™')
      .replace(/ /g, 'Â ')
      .replace(/\n/g, '\r');
  }

  /**
   * Кодирует массив ответов в строку SCORM формата
   */
  encodeResponse(responseArray) {
    if (!responseArray || !Array.isArray(responseArray)) return '';
    return responseArray.join('[,]');
  }

  /**
   * Преобразует секунды в ISO 8601 duration
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
      // Если есть дробная часть, сохраняем её
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
   * Создает данные в формате SCORM из компактной версии
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
   * НОВЫЙ МЕТОД: Преобразует компактную версию напрямую обратно в SCORM формат
   * Это то, что вам нужно для: const compact = normalizer.createCompactVersion(result);
   * @param {Object} compactData - результат createCompactVersion()
   * @returns {Object} - данные в формате SCORM
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

    // Цели
    scormResult['cmi.objectives._count'] = '0';

    return scormResult;
  }
}
// Initialize player when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  window.scormPlayer = new ScormPlayer();
  window.scormNormalizer = new ScormNormalizer();
});

// module.exports = { SCORMNormalizer: ScormNormalizer };
