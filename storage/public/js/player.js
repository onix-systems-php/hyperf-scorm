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
    if (!this.debugPanel || !this.config.debug) return;

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

// Initialize player when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  window.scormPlayer = new ScormPlayer();
});
