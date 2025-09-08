/**
 * SCORM Data Normalizer
 * Нормализует данные SCORM commit для передачи на сервер
 */
class ScormNormalizer {
  constructor() {
    this.fieldMapping = {
      'cmi.core.student_id': 'studentId',
      'cmi.core.student_name': 'studentName',
      'cmi.core.lesson_location': 'lessonLocation',
      'cmi.core.lesson_status': 'lessonStatus',
      'cmi.core.score.raw': 'scoreRaw',
      'cmi.core.score.max': 'scoreMax',
      'cmi.core.score.min': 'scoreMin',
      'cmi.core.score.scaled': 'scoreScaled',
      'cmi.core.total_time': 'totalTime',
      'cmi.core.session_time': 'sessionTime',
      'cmi.core.lesson_mode': 'lessonMode',
      'cmi.core.exit': 'exitStatus',
      'cmi.core.entry': 'entry',
      'cmi.core.credit': 'credit',
      'cmi.suspend_data': 'suspendData',
      'cmi.launch_data': 'launchData',
      'cmi.comments': 'comments',
      'cmi.comments_from_lms': 'commentsFromLms'
    };
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
      totalTime: data['cmi.core.total_time'] || '0000:00:00',
      sessionTime: data['cmi.core.session_time'] || 'PT0S',
      sessionTimeSeconds: this.parseISO8601Duration(data['cmi.core.session_time']),
      suspendData: data['cmi.suspend_data'] || null,
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
   * Удаляет пустые поля из объекта
   */
  cleanEmptyFields(obj) {
    if (Array.isArray(obj)) {
      return obj.filter(item => item !== null && item !== undefined);
    }

    if (obj !== null && typeof obj === 'object') {
      const cleaned = {};
      for (const [key, value] of Object.entries(obj)) {
        if (value !== null && value !== undefined && value !== '') {
          if (typeof value === 'object') {
            const cleanedValue = this.cleanEmptyFields(value);
            if (Array.isArray(cleanedValue) ? cleanedValue.length > 0 : Object.keys(cleanedValue).length > 0) {
              cleaned[key] = cleanedValue;
            }
          } else {
            cleaned[key] = value;
          }
        }
      }
      return cleaned;
    }

    return obj;
  }

  /**
   * Создает компактную версию для отправки на сервер
   */
  createCompactVersion(normalizedData) {
   debugger
    return {
      studentId: normalizedData.student.id,
      studentName: normalizedData.student.name,
      lessonLocation: normalizedData.lesson.location,
      lessonStatus: normalizedData.lesson.status,
      score: normalizedData.score.raw,
      scorePercentage: normalizedData.score.percentage,
      session: normalizedData.session,
      interactions: normalizedData.interactions.map(i => ({
        id: i.id,
        type: i.type,
        description: i.description,
        learnerResponse: i.learnerResponse,
        correctResponse: i.correctResponse,
        result: i.result,
        weighting: i.weighting,
        latency: i.latency,
        timestamp: i.timestamp,
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

    // Основная информация о студенте и уроке
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

    // Результаты
    if (normalizedData.score) {
      scormData['cmi.core.score.raw'] = normalizedData.score.raw ? normalizedData.score.raw.toString() : '';
      scormData['cmi.core.score.max'] = normalizedData.score.max ? normalizedData.score.max.toString() : '';
      scormData['cmi.core.score.min'] = normalizedData.score.min ? normalizedData.score.min.toString() : '';
      scormData['cmi.score.scaled'] = normalizedData.score.scaled ? normalizedData.score.scaled.toString() : '';
    }

    // Информация о сессии
    if (normalizedData.session) {
      scormData['cmi.core.total_time'] = normalizedData.session.totalTime || '0000:00:00';
      scormData['cmi.core.session_time'] = normalizedData.session.sessionTime || 'PT0S';
      scormData['cmi.suspend_data'] = normalizedData.session.suspendData || '';
      scormData['cmi.launch_data'] = normalizedData.session.launchData || '';
      scormData['cmi.comments'] = normalizedData.session.comments || '';
      scormData['cmi.comments_from_lms'] = normalizedData.session.commentsFromLms || '';
    }

    // Взаимодействия
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

    // Цели (objectives)
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
    scormResult['cmi.core.lesson_location'] = '';
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

    scormResult['cmi.objectives._count'] = '0';

    return scormResult;
  }
}
