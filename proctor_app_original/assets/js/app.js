/* ============================================
   Online Proctored Examination System - JS
   ============================================ */

const ExamSystem = {
    timer: null,
    timeLeft: 0,
    tabSwitchCount: 0,
    tabSwitchLimit: 3,
    attemptId: null,
    examId: null,
    webcamStream: null,
    captureInterval: null,
    fullscreenRequired: false,
    currentQuestion: 0,
    totalQuestions: 0,

    // ---- TIMER ----
    startTimer(seconds, onTick, onEnd) {
        this.timeLeft = seconds;
        this.updateTimerDisplay();
        this.timer = setInterval(() => {
            this.timeLeft--;
            this.updateTimerDisplay();
            if (onTick) onTick(this.timeLeft);
            if (this.timeLeft <= 0) {
                clearInterval(this.timer);
                if (onEnd) onEnd();
            }
        }, 1000);
    },

    updateTimerDisplay() {
        const el = document.getElementById('exam-timer');
        if (!el) return;
        const h = Math.floor(this.timeLeft / 3600);
        const m = Math.floor((this.timeLeft % 3600) / 60);
        const s = this.timeLeft % 60;
        const display = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        el.textContent = display;

        const container = el.closest('.exam-timer');
        if (container) {
            container.classList.remove('warning', 'danger');
            if (this.timeLeft <= 60) container.classList.add('danger');
            else if (this.timeLeft <= 300) container.classList.add('warning');
        }
    },

    stopTimer() {
        if (this.timer) clearInterval(this.timer);
    },

    // ---- TAB SWITCH DETECTION ----
    initTabSwitchDetection() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.tabSwitchCount++;
                this.logViolation('tab_switch');
                this.updateViolationCounter();
                this.showViolationToast(`⚠️ Tab switch detected! (${this.tabSwitchCount}/${this.tabSwitchLimit})`);

                if (this.tabSwitchCount >= this.tabSwitchLimit) {
                    this.showViolationToast('🚫 Tab switch limit exceeded! Auto-submitting...');
                    setTimeout(() => this.autoSubmitExam('violation'), 1500);
                }
            }
        });

        // Detect copy/paste
        document.addEventListener('copy', (e) => {
            e.preventDefault();
            this.logViolation('copy_paste');
            this.showViolationToast('⚠️ Copy/Paste is not allowed!');
        });

        document.addEventListener('paste', (e) => {
            e.preventDefault();
            this.logViolation('copy_paste');
            this.showViolationToast('⚠️ Copy/Paste is not allowed!');
        });

        // Prevent right-click
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
        });
    },

    updateViolationCounter() {
        const el = document.getElementById('violation-count');
        if (el) el.textContent = this.tabSwitchCount;

        // Also update server
        fetch('<?= BASE_URL ?>/api/save_violation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                attempt_id: this.attemptId,
                tab_switches: this.tabSwitchCount
            })
        }).catch(() => {});
    },

    logViolation(type) {
        fetch(`${window.BASE_URL || ''}/api/save_violation.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                attempt_id: this.attemptId,
                exam_id: this.examId,
                violation_type: type,
                tab_switches: this.tabSwitchCount
            })
        }).catch(() => {});
    },

    showViolationToast(message) {
        const existing = document.querySelector('.violation-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = 'violation-toast';
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => toast.remove(), 3000);
    },

    // ---- FULLSCREEN ----
    requestFullscreen() {
        const el = document.documentElement;
        if (el.requestFullscreen) el.requestFullscreen();
        else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
        else if (el.msRequestFullscreen) el.msRequestFullscreen();
    },

    initFullscreenDetection() {
        if (!this.fullscreenRequired) return;
        this.requestFullscreen();

        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement && this.fullscreenRequired) {
                this.logViolation('fullscreen_exit');
                this.showViolationToast('⚠️ Please stay in fullscreen mode!');
                setTimeout(() => this.requestFullscreen(), 1000);
            }
        });
    },

    // ---- WEBCAM ----
    async initWebcam() {
        try {
            this.webcamStream = await navigator.mediaDevices.getUserMedia({
                video: { width: 320, height: 240 }
            });
            const video = document.getElementById('webcam-video');
            if (video) {
                video.srcObject = this.webcamStream;
                video.play();
            }
            // Capture every 30 seconds
            this.captureInterval = setInterval(() => this.captureWebcam(), 30000);
        } catch (err) {
            console.warn('Webcam access denied:', err);
            this.logViolation('webcam_blocked');
            this.showViolationToast('⚠️ Webcam access is required for this exam!');
        }
    },

    captureWebcam() {
        const video = document.getElementById('webcam-video');
        if (!video || !this.webcamStream) return;

        const canvas = document.createElement('canvas');
        canvas.width = 320;
        canvas.height = 240;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, 320, 240);

        const imageData = canvas.toDataURL('image/jpeg', 0.6);

        fetch(`${window.BASE_URL || ''}/api/capture_webcam.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                attempt_id: this.attemptId,
                image: imageData
            })
        }).catch(() => {});
    },

    stopWebcam() {
        if (this.webcamStream) {
            this.webcamStream.getTracks().forEach(t => t.stop());
        }
        if (this.captureInterval) clearInterval(this.captureInterval);
    },

    // ---- ANSWER MANAGEMENT ----
    saveAnswer(questionId, answer) {
        fetch(`${window.BASE_URL || ''}/api/submit_answer.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                attempt_id: this.attemptId,
                question_id: questionId,
                answer: answer
            })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                // Mark as answered in nav
                const navBtn = document.querySelector(`.q-nav-btn[data-qid="${questionId}"]`);
                if (navBtn) navBtn.classList.add('answered');
            }
        }).catch(() => {});
    },

    // ---- SUBMIT EXAM ----
    submitExam() {
        if (!confirm('Are you sure you want to submit the exam? This action cannot be undone.')) return;
        this.doSubmit('completed');
    },

    autoSubmitExam(reason) {
        this.doSubmit(reason || 'auto_submitted');
    },

    doSubmit(status) {
        this.stopTimer();
        this.stopWebcam();

        fetch(`${window.BASE_URL || ''}/api/submit_exam.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                attempt_id: this.attemptId,
                status: status,
                tab_switches: this.tabSwitchCount
            })
        }).then(r => r.json()).then(data => {
            if (data.success) {
                if (document.fullscreenElement) {
                    document.exitFullscreen().catch(() => {});
                }
                window.location.href = `${window.BASE_URL || ''}/student/result_detail.php?attempt_id=${this.attemptId}`;
            } else {
                alert('Error submitting exam. Please try again.');
            }
        }).catch(() => {
            window.location.href = `${window.BASE_URL || ''}/student/result_detail.php?attempt_id=${this.attemptId}`;
        });
    },

    // ---- QUESTION NAVIGATION ----
    showQuestion(index) {
        document.querySelectorAll('.question-card').forEach((q, i) => {
            q.style.display = i === index ? 'block' : 'none';
        });
        document.querySelectorAll('.q-nav-btn').forEach((btn, i) => {
            btn.classList.toggle('active', i === index);
        });
        this.currentQuestion = index;
        this.updateNavButtons();
    },

    updateNavButtons() {
        const prevBtn = document.getElementById('btn-prev');
        const nextBtn = document.getElementById('btn-next');
        if (prevBtn) prevBtn.disabled = this.currentQuestion === 0;
        if (nextBtn) {
            if (this.currentQuestion === this.totalQuestions - 1) {
                nextBtn.textContent = 'Finish';
            } else {
                nextBtn.textContent = 'Next →';
            }
        }
    },

    prevQuestion() {
        if (this.currentQuestion > 0) this.showQuestion(this.currentQuestion - 1);
    },

    nextQuestion() {
        if (this.currentQuestion < this.totalQuestions - 1) {
            this.showQuestion(this.currentQuestion + 1);
        }
    },

    // ---- INIT EXAM ----
    initExam(config) {
        this.attemptId = config.attemptId;
        this.examId = config.examId;
        this.tabSwitchLimit = config.tabSwitchLimit || 3;
        this.fullscreenRequired = config.fullscreenRequired || false;
        this.totalQuestions = config.totalQuestions || 0;

        window.BASE_URL = config.baseUrl || '';

        // Timer
        this.startTimer(config.timeLeft, null, () => {
            this.showViolationToast('⏰ Time is up! Auto-submitting...');
            setTimeout(() => this.autoSubmitExam('auto_submitted'), 1500);
        });

        // Tab switch detection
        this.initTabSwitchDetection();

        // Fullscreen
        if (this.fullscreenRequired) {
            this.initFullscreenDetection();
        }

        // Webcam
        if (config.webcamEnabled) {
            this.initWebcam();
        }

        // Show first question
        if (this.totalQuestions > 0) {
            this.showQuestion(0);
        }

        // MCQ selection handlers (checkbox multi-select)
        document.querySelectorAll('.option-item').forEach(item => {
            item.addEventListener('click', function(e) {
                // Prevent double-trigger when clicking checkbox directly
                if (e.target.type === 'checkbox') return;
                
                const checkbox = this.querySelector('input[type="checkbox"]');
                checkbox.checked = !checkbox.checked;
                this.classList.toggle('selected', checkbox.checked);
                
                // Gather all selected values for this question
                const qid = this.dataset.qid;
                const selected = [];
                document.querySelectorAll(`.option-item[data-qid="${qid}"]`).forEach(o => {
                    if (o.querySelector('input[type="checkbox"]').checked) {
                        selected.push(o.dataset.value);
                    }
                });
                
                ExamSystem.saveAnswer(qid, selected.join(','));
            });
            
            // Also handle direct checkbox clicks
            const cb = item.querySelector('input[type="checkbox"]');
            if (cb) {
                cb.addEventListener('change', function() {
                    const li = this.closest('.option-item');
                    li.classList.toggle('selected', this.checked);
                    
                    const qid = li.dataset.qid;
                    const selected = [];
                    document.querySelectorAll(`.option-item[data-qid="${qid}"]`).forEach(o => {
                        if (o.querySelector('input[type="checkbox"]').checked) {
                            selected.push(o.dataset.value);
                        }
                    });
                    
                    ExamSystem.saveAnswer(qid, selected.join(','));
                });
            }
        });

        // Descriptive answer auto-save
        let descTimers = {};
        document.querySelectorAll('.desc-answer').forEach(textarea => {
            textarea.addEventListener('input', function() {
                const qid = this.dataset.qid;
                clearTimeout(descTimers[qid]);
                descTimers[qid] = setTimeout(() => {
                    ExamSystem.saveAnswer(qid, this.value);
                }, 1000);
            });
        });

        // Question nav buttons
        document.querySelectorAll('.q-nav-btn').forEach((btn, i) => {
            btn.addEventListener('click', () => this.showQuestion(i));
        });
    }
};

// ---- ADMIN HELPERS ----
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this?');
}

function toggleSidebar() {
    document.querySelector('.sidebar')?.classList.toggle('open');
}
