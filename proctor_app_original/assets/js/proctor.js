/* ============================================
   ExamProctor Enterprise — Proctoring Engine
   ============================================ */

const Proctor = {
    // Config
    config: {
        baseUrl: '',
        attemptId: null,
        examId: null,
        userId: null,
        sessionToken: null,
        // Feature flags
        webcamEnabled: false,
        faceDetection: false,
        audioMonitoring: false,
        fullscreenRequired: false,
        // Thresholds
        tabSwitchLimit: 3,
        maxViolations: 5,
        snapshotInterval: 10,  // seconds — stored in DB for review
        liveFrameInterval: 3,  // seconds — fast capture for admin live monitor
        heartbeatInterval: 15, // seconds
        autoSaveInterval: 30,  // seconds
        noiseThreshold: 40,    // audio level 0-100
    },

    // State
    state: {
        tabSwitches: 0,
        totalViolations: 0,
        faceViolations: 0,
        isActive: true,
        isSubmitting: false,
        webcamStream: null,
        audioContext: null,
        audioAnalyser: null,
        faceDetectionReady: false,
        permissionsGranted: { camera: false, mic: false },
        lastFaceCount: 1,
        networkOnline: true,
    },

    // Intervals
    intervals: {
        snapshot: null,
        heartbeat: null,
        autoSave: null,
        faceCheck: null,
        audioCheck: null,
    },

    // ========================
    // INITIALIZATION
    // ========================
    async init(config) {
        Object.assign(this.config, config);
        this.config.baseUrl = config.baseUrl || '';

        // Session token for device control
        this.config.sessionToken = this._generateToken();

        // Register session
        await this._registerSession();

        // Start monitoring systems
        this._initTabDetection();
        this._initCopyPasteBlock();
        this._initKeyboardBlock();
        this._initNetworkMonitor();

        // Start heartbeat
        this.intervals.heartbeat = setInterval(
            () => this._sendHeartbeat(), 
            this.config.heartbeatInterval * 1000
        );

        // Start auto-save
        this.intervals.autoSave = setInterval(
            () => this._autoSaveAnswers(), 
            this.config.autoSaveInterval * 1000
        );

        console.log('[Proctor] Engine initialized');
    },

    // ========================
    // PERMISSION MANAGEMENT
    // ========================
    async checkPermissions() {
        const result = { camera: 'denied', mic: 'denied' };

        try {
            // Check camera
            const camPerm = await navigator.permissions.query({ name: 'camera' }).catch(() => null);
            if (camPerm) {
                result.camera = camPerm.state;
                camPerm.addEventListener('change', () => {
                    if (camPerm.state === 'denied') {
                        this.logViolation('permission_denied', 'high', 'Camera permission revoked');
                        this._showWarning('Camera permission was revoked!', 'critical');
                    }
                });
            }

            // Check microphone
            const micPerm = await navigator.permissions.query({ name: 'microphone' }).catch(() => null);
            if (micPerm) {
                result.mic = micPerm.state;
                micPerm.addEventListener('change', () => {
                    if (micPerm.state === 'denied') {
                        this.logViolation('permission_denied', 'medium', 'Microphone permission revoked');
                    }
                });
            }
        } catch (e) {
            console.warn('[Proctor] Permission query not supported:', e);
        }

        return result;
    },

    async requestPermissions() {
        const status = { camera: 'denied', mic: 'denied', stream: null };

        try {
            const constraints = { video: { width: 640, height: 480 } };
            if (this.config.audioMonitoring) {
                constraints.audio = true;
            }

            const stream = await navigator.mediaDevices.getUserMedia(constraints);
            status.camera = 'granted';
            status.stream = stream;
            this.state.permissionsGranted.camera = true;

            if (constraints.audio) {
                status.mic = 'granted';
                this.state.permissionsGranted.mic = true;
            }

            this.state.webcamStream = stream;
        } catch (err) {
            console.error('[Proctor] Permission denied:', err.name);
            if (err.name === 'NotAllowedError') {
                status.camera = 'denied';
            } else if (err.name === 'NotFoundError') {
                status.camera = 'unavailable';
            } else {
                status.camera = 'blocked';
            }
        }

        // Log to server
        await this._savePermissions(status.camera, status.mic);
        return status;
    },

    async _savePermissions(camStatus, micStatus) {
        try {
            await fetch(`${this.config.baseUrl}/api/save_permissions.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    attempt_id: this.config.attemptId,
                    camera_status: camStatus,
                    mic_status: micStatus,
                })
            });
        } catch (e) { /* silent */ }
    },

    // ========================
    // WEBCAM & SNAPSHOTS
    // ========================
    startWebcam(videoElement) {
        if (!this.state.webcamStream) return false;

        videoElement.srcObject = this.state.webcamStream;
        videoElement.play();

        // Start periodic snapshots (stored in DB for review)
        this.intervals.snapshot = setInterval(
            () => this._captureSnapshot(),
            this.config.snapshotInterval * 1000
        );

        // Start fast live-frame capture for admin real-time monitoring
        this.intervals.liveFrame = setInterval(
            () => this._captureLiveFrame(),
            this.config.liveFrameInterval * 1000
        );

        return true;
    },

    async _captureLiveFrame() {
        const video = document.getElementById('webcam-video');
        if (!video || !this.state.webcamStream || this.state.isSubmitting) return;

        const canvas = document.createElement('canvas');
        canvas.width = 320;
        canvas.height = 240;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, 320, 240);
        const imageData = canvas.toDataURL('image/jpeg', 0.4);

        try {
            await fetch(`${this.config.baseUrl}/api/save_live_frame.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    attempt_id: this.config.attemptId,
                    image: imageData,
                    face_count: this.state.lastFaceCount,
                })
            });
        } catch (e) { /* silent */ }
    },

    async _captureSnapshot(faceCount = null) {
        const video = document.getElementById('webcam-video');
        if (!video || !this.state.webcamStream) return;

        const canvas = document.createElement('canvas');
        canvas.width = 320;
        canvas.height = 240;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, 320, 240);

        const imageData = canvas.toDataURL('image/jpeg', 0.5);

        try {
            await fetch(`${this.config.baseUrl}/api/save_snapshot.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    attempt_id: this.config.attemptId,
                    image: imageData,
                    face_count: faceCount,
                    has_face: faceCount !== null ? (faceCount > 0 ? 1 : 0) : null,
                })
            });
        } catch (e) { /* silent fail */ }
    },

    stopWebcam() {
        if (this.state.webcamStream) {
            this.state.webcamStream.getTracks().forEach(t => t.stop());
            this.state.webcamStream = null;
        }
        if (this.intervals.snapshot) clearInterval(this.intervals.snapshot);
        if (this.intervals.liveFrame) clearInterval(this.intervals.liveFrame);
    },

    // ========================
    // FACE DETECTION
    // ========================
    async initFaceDetection() {
        if (!this.config.faceDetection) return;

        try {
            // Load face-api models from CDN
            const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model';
            await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
            this.state.faceDetectionReady = true;
            console.log('[Proctor] Face detection models loaded');

            // Start face checking loop
            this._startFaceDetectionLoop();
        } catch (err) {
            console.error('[Proctor] Face detection init failed:', err);
            this.state.faceDetectionReady = false;
        }
    },

    _startFaceDetectionLoop() {
        const video = document.getElementById('webcam-video');
        if (!video || !this.state.faceDetectionReady) return;

        this.intervals.faceCheck = setInterval(async () => {
            if (this.state.isSubmitting) return;

            try {
                const detections = await faceapi.detectAllFaces(
                    video,
                    new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 })
                );

                const faceCount = detections.length;
                this.state.lastFaceCount = faceCount;
                this._updateFaceIndicator(faceCount);

                if (faceCount === 0) {
                    this.state.faceViolations++;
                    this.logViolation('no_face', 'high', 'No face detected', { face_count: 0 });
                    this._showWarning('No face detected! Please stay in front of the camera.', 'warning');
                    this._captureSnapshot(0);
                } else if (faceCount > 1) {
                    this.state.faceViolations++;
                    this.logViolation('multiple_faces', 'critical', `${faceCount} faces detected`, { face_count: faceCount });
                    this._showWarning(`${faceCount} faces detected! Only one person should be present.`, 'critical');
                    this._captureSnapshot(faceCount);
                }

                // Check auto-submit threshold
                this._checkViolationThreshold();
            } catch (e) { /* silent */ }
        }, 3000); // Check every 3 seconds
    },

    _updateFaceIndicator(count) {
        const el = document.getElementById('face-status');
        if (!el) return;
        if (count === 1) {
            el.textContent = 'Face OK';
            el.className = 'face-indicator ok';
        } else if (count === 0) {
            el.textContent = 'No face';
            el.className = 'face-indicator warn';
        } else {
            el.textContent = count + ' faces';
            el.className = 'face-indicator danger';
        }
    },

    // ========================
    // AUDIO MONITORING
    // ========================
    async initAudioMonitoring() {
        if (!this.config.audioMonitoring || !this.state.webcamStream) return;

        try {
            this.state.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const source = this.state.audioContext.createMediaStreamSource(this.state.webcamStream);
            this.state.audioAnalyser = this.state.audioContext.createAnalyser();
            this.state.audioAnalyser.fftSize = 256;
            source.connect(this.state.audioAnalyser);

            this.intervals.audioCheck = setInterval(() => this._checkAudioLevel(), 2000);
            console.log('[Proctor] Audio monitoring started');
        } catch (e) {
            console.warn('[Proctor] Audio monitoring init failed:', e);
        }
    },

    _checkAudioLevel() {
        if (!this.state.audioAnalyser || this.state.isSubmitting) return;

        const dataArray = new Uint8Array(this.state.audioAnalyser.frequencyBinCount);
        this.state.audioAnalyser.getByteFrequencyData(dataArray);

        const average = dataArray.reduce((a, b) => a + b, 0) / dataArray.length;
        const level = Math.round((average / 255) * 100);

        // Update UI indicator
        const el = document.getElementById('audio-level');
        if (el) {
            el.style.width = level + '%';
            el.style.background = level > this.config.noiseThreshold ? 'var(--rose, #e11d48)' : 'var(--em, #059669)';
        }

        if (level > this.config.noiseThreshold) {
            this.logViolation('audio_spike', 'medium', `High noise level: ${level}%`, { noise_level: level });
            this._showWarning('Unusual background noise detected!', 'warning');
        }
    },

    // ========================
    // TAB SWITCH DETECTION
    // ========================
    _initTabDetection() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && !this.state.isSubmitting) {
                this.state.tabSwitches++;
                this.state.totalViolations++;
                this.logViolation('tab_switch', 'high', `Tab switch #${this.state.tabSwitches}`);
                this._updateViolationCounter();
                this._showWarning(
                    `Tab switch detected! (${this.state.tabSwitches}/${this.config.tabSwitchLimit})`,
                    'warning'
                );

                if (this.state.tabSwitches >= this.config.tabSwitchLimit) {
                    this._showWarning('Tab switch limit exceeded! Auto-submitting...', 'critical');
                    setTimeout(() => this.autoSubmit('tab_switch_limit'), 1500);
                }

                this._checkViolationThreshold();
            }
        });

        // Detect window blur (covers alt-tab etc.)
        window.addEventListener('blur', () => {
            if (!document.hidden && !this.state.isSubmitting) {
                this.logViolation('window_blur', 'low', 'Window lost focus');
            }
        });
    },

    // ========================
    // COPY/PASTE & KEYBOARD BLOCK
    // ========================
    _initCopyPasteBlock() {
        ['copy', 'cut', 'paste'].forEach(evt => {
            document.addEventListener(evt, (e) => {
                // Allow in descriptive answer textareas (paste only for typing)
                if (evt === 'paste' && e.target.classList.contains('desc-answer')) return;
                e.preventDefault();
                this.logViolation('copy_paste', 'medium', `${evt} attempted`);
                this._showWarning('Copy/Paste is not allowed during exam!', 'warning');
            });
        });

        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
        });
    },

    _initKeyboardBlock() {
        document.addEventListener('keydown', (e) => {
            // Block common shortcuts
            const blocked = [
                (e.ctrlKey && e.key === 'c'),
                (e.ctrlKey && e.key === 'v'),
                (e.ctrlKey && e.key === 'a'),
                (e.ctrlKey && e.key === 'p'),
                (e.ctrlKey && e.key === 'u'),
                (e.ctrlKey && e.key === 's'),
                (e.ctrlKey && e.shiftKey && e.key === 'I'),
                (e.ctrlKey && e.shiftKey && e.key === 'J'),
                (e.key === 'F12'),
                (e.key === 'PrintScreen'),
            ];

            if (blocked.some(b => b)) {
                e.preventDefault();
                e.stopPropagation();
                this.logViolation('keyboard_shortcut', 'low', `Blocked: ${e.key}`);
            }
        });
    },

    // ========================
    // FULLSCREEN MANAGEMENT
    // ========================
    requestFullscreen() {
        const el = document.documentElement;
        if (el.requestFullscreen) el.requestFullscreen();
        else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
        else if (el.msRequestFullscreen) el.msRequestFullscreen();
    },

    initFullscreenMonitor() {
        if (!this.config.fullscreenRequired) return;
        this.requestFullscreen();

        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement && this.config.fullscreenRequired && !this.state.isSubmitting) {
                this.state.totalViolations++;
                this.logViolation('fullscreen_exit', 'high', 'Exited fullscreen mode');
                this._showWarning('Please stay in fullscreen mode!', 'warning');
                setTimeout(() => this.requestFullscreen(), 1000);
                this._checkViolationThreshold();
            }
        });
    },

    // ========================
    // NETWORK MONITOR
    // ========================
    _initNetworkMonitor() {
        window.addEventListener('online', () => {
            this.state.networkOnline = true;
            this._showWarning('Connection restored. Your answers are being synced.', 'info');
            this._syncOfflineAnswers();
        });

        window.addEventListener('offline', () => {
            this.state.networkOnline = false;
            this._showWarning('Network disconnected! Your answers are being saved locally.', 'critical');
        });
    },

    _syncOfflineAnswers() {
        // Trigger auto-save to push any locally cached answers
        this._autoSaveAnswers();
    },

    // ========================
    // HEARTBEAT
    // ========================
    async _sendHeartbeat() {
        try {
            const resp = await fetch(`${this.config.baseUrl}/api/heartbeat.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    attempt_id: this.config.attemptId,
                    session_token: this.config.sessionToken,
                    tab_switches: this.state.tabSwitches,
                    total_violations: this.state.totalViolations,
                })
            });
            const data = await resp.json();

            // Server can force-submit (admin action)
            if (data.force_submit) {
                this._showWarning('Exam terminated by administrator.', 'critical');
                setTimeout(() => this.autoSubmit('admin_terminated'), 1500);
            }

            // Check for duplicate session
            if (data.session_invalid) {
                this._showWarning('Another session detected! This exam will be submitted.', 'critical');
                setTimeout(() => this.autoSubmit('duplicate_session'), 1500);
            }
        } catch (e) { /* network issues handled by monitor */ }
    },

    // ========================
    // AUTO-SAVE
    // ========================
    _autoSaveAnswers() {
        // Gather all current answers from the DOM
        const answers = {};

        // MCQ checkboxes
        document.querySelectorAll('.option-item').forEach(item => {
            const qid = item.dataset.qid;
            if (!answers[qid]) answers[qid] = [];
            if (item.querySelector('input[type="checkbox"]')?.checked) {
                answers[qid].push(item.dataset.value);
            }
        });

        // Descriptive textareas
        document.querySelectorAll('.desc-answer').forEach(ta => {
            const qid = ta.dataset.qid;
            if (ta.value.trim()) {
                answers[qid] = ta.value;
            }
        });

        // Send to server
        if (Object.keys(answers).length > 0) {
            fetch(`${this.config.baseUrl}/api/auto_save.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    attempt_id: this.config.attemptId,
                    answers: answers,
                })
            }).catch(() => {
                // Store locally if offline
                try {
                    localStorage.setItem(
                        `exam_backup_${this.config.attemptId}`,
                        JSON.stringify(answers)
                    );
                } catch (e) { /* storage full or unavailable */ }
            });
        }
    },

    // ========================
    // VIOLATION LOGGING
    // ========================
    async logViolation(type, severity, message, metadata = null) {
        this.state.totalViolations++;
        this._updateViolationCounter();

        try {
            await fetch(`${this.config.baseUrl}/api/save_violation.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    attempt_id: this.config.attemptId,
                    exam_id: this.config.examId,
                    type: type,
                    severity: severity,
                    message: message,
                    metadata: metadata,
                    tab_switches: this.state.tabSwitches,
                    total_violations: this.state.totalViolations,
                })
            });
        } catch (e) { /* silent */ }
    },

    _checkViolationThreshold() {
        if (this.state.totalViolations >= this.config.maxViolations && !this.state.isSubmitting) {
            this._showWarning('Maximum violations exceeded! Exam will be auto-submitted.', 'critical');
            setTimeout(() => this.autoSubmit('max_violations'), 2000);
        }
    },

    _updateViolationCounter() {
        const el = document.getElementById('violation-count');
        if (el) el.textContent = this.state.totalViolations;
        const tabEl = document.getElementById('tab-switch-count');
        if (tabEl) tabEl.textContent = this.state.tabSwitches;
    },

    // ========================
    // SESSION CONTROL
    // ========================
    async _registerSession() {
        try {
            await fetch(`${this.config.baseUrl}/api/check_session.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    attempt_id: this.config.attemptId,
                    session_token: this.config.sessionToken,
                    action: 'register',
                })
            });
        } catch (e) { /* silent */ }
    },

    _generateToken() {
        return 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 12);
    },

    // ========================
    // EXAM SUBMISSION
    // ========================
    submitExam() {
        if (!confirm('Are you sure you want to submit? This cannot be undone.')) return;
        this._doSubmit('completed');
    },

    autoSubmit(reason) {
        this._doSubmit(reason === 'tab_switch_limit' || reason === 'max_violations' ? 'violation' : 'auto_submitted', reason);
    },

    async _doSubmit(status, reason = null) {
        if (this.state.isSubmitting) return;
        this.state.isSubmitting = true;

        // Stop all monitoring
        this._stopAll();

        // Final auto-save
        this._autoSaveAnswers();

        try {
            const resp = await fetch(`${this.config.baseUrl}/api/submit_exam.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    attempt_id: this.config.attemptId,
                    status: status,
                    tab_switches: this.state.tabSwitches,
                    total_violations: this.state.totalViolations,
                    auto_submit_reason: reason,
                })
            });
            const data = await resp.json();

            if (document.fullscreenElement) {
                document.exitFullscreen().catch(() => {});
            }

            window.location.href = `${this.config.baseUrl}/student/result_detail.php?attempt_id=${this.config.attemptId}`;
        } catch (e) {
            // Even on network failure, redirect to results
            window.location.href = `${this.config.baseUrl}/student/result_detail.php?attempt_id=${this.config.attemptId}`;
        }
    },

    _stopAll() {
        Object.values(this.intervals).forEach(id => { if (id) clearInterval(id); });
        this.stopWebcam();
        if (this.state.audioContext) {
            this.state.audioContext.close().catch(() => {});
        }
    },

    // ========================
    // UI HELPERS
    // ========================
    _showWarning(message, level = 'warning') {
        const existing = document.querySelector('.proctor-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = `proctor-toast proctor-toast-${level}`;
        toast.innerHTML = `<span class="proctor-toast-icon">${
            level === 'critical' ? '🚫' : level === 'warning' ? '⚠️' : 'ℹ️'
        }</span><span>${message}</span>`;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(120%)';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    },

    // ========================
    // ACCESS KEY VERIFICATION
    // ========================
    async verifyAccessKey(examId, accessKey) {
        try {
            const resp = await fetch(`${this.config.baseUrl}/api/verify_access_key.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ exam_id: examId, access_key: accessKey })
            });
            return await resp.json();
        } catch (e) {
            return { success: false, error: 'Network error' };
        }
    },
};

// ========================
// PROCTOR CSS (injected)
// ========================
(function injectProctorCSS() {
    const style = document.createElement('style');
    style.textContent = `
        .proctor-toast {
            position: fixed; top: 70px; right: 20px; z-index: 9999;
            padding: 14px 20px; border-radius: 10px;
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; font-weight: 600;
            font-family: 'Manrope', 'Outfit', system-ui, sans-serif;
            animation: proctorSlideIn 0.3s ease;
            transition: opacity 0.3s, transform 0.3s;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            max-width: 400px;
        }
        .proctor-toast-warning { background: #fef4e1; color: #92610a; border: 1px solid #f5d89a; }
        .proctor-toast-critical { background: #fde8ea; color: #be1842; border: 1px solid #f5c6cb; }
        .proctor-toast-info { background: #e0f2fe; color: #0369a1; border: 1px solid #a3d5f5; }
        .proctor-toast-icon { font-size: 16px; flex-shrink: 0; }
        @keyframes proctorSlideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        .face-indicator {
            padding: 4px 10px; border-radius: 20px;
            font-size: 10px; font-weight: 700;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .face-indicator.ok { background: rgba(5,150,105,.12); color: #059669; }
        .face-indicator.warn { background: rgba(180,83,9,.12); color: #b45309; }
        .face-indicator.danger { background: rgba(225,29,72,.12); color: #e11d48; }
        
        .audio-meter { width: 80px; height: 4px; border-radius: 2px; background: #e8ebf0; overflow: hidden; }
        .audio-meter-fill { height: 100%; border-radius: 2px; transition: width 0.3s, background 0.3s; }
        
        .permission-check { display: flex; align-items: center; gap: 8px; padding: 12px 16px; border-radius: 8px; margin-bottom: 8px; font-size: 13px; }
        .permission-check.granted { background: rgba(5,150,105,.08); color: #059669; border: 1px solid rgba(5,150,105,.15); }
        .permission-check.denied { background: rgba(225,29,72,.08); color: #e11d48; border: 1px solid rgba(225,29,72,.15); }
        .permission-check.pending { background: rgba(180,83,9,.08); color: #b45309; border: 1px solid rgba(180,83,9,.15); }
    `;
    document.head.appendChild(style);
})();
