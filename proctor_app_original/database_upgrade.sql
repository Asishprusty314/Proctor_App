-- ============================================
-- ExamProctor Enterprise Upgrade
-- Run this AFTER the original database.sql
-- ============================================

USE proctor_app;

-- 1. Add access_key to exams table
ALTER TABLE exams 
    ADD COLUMN access_key VARCHAR(50) DEFAULT NULL AFTER shuffle_options,
    ADD COLUMN audio_monitoring TINYINT(1) DEFAULT 0 AFTER access_key,
    ADD COLUMN face_detection TINYINT(1) DEFAULT 1 AFTER audio_monitoring,
    ADD COLUMN snapshot_interval INT DEFAULT 10 COMMENT 'seconds between snapshots' AFTER face_detection,
    ADD COLUMN max_violations INT DEFAULT 5 COMMENT 'auto-submit threshold' AFTER snapshot_interval;

-- 2. User-specific exam access keys
CREATE TABLE IF NOT EXISTS exam_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    user_id INT DEFAULT NULL COMMENT 'NULL = any user can use',
    access_key VARCHAR(50) NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    used_at DATETIME DEFAULT NULL,
    used_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_key (access_key),
    INDEX idx_exam_user (exam_id, user_id)
) ENGINE=InnoDB;

-- 3. Enhanced violations table
CREATE TABLE IF NOT EXISTS violations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    user_id INT NOT NULL,
    exam_id INT NOT NULL,
    type VARCHAR(50) NOT NULL COMMENT 'tab_switch|no_face|multiple_faces|fullscreen_exit|copy_paste|audio_spike|permission_denied|webcam_blocked|mic_blocked|device_switch',
    severity ENUM('low','medium','high','critical') DEFAULT 'medium',
    message TEXT,
    metadata JSON DEFAULT NULL COMMENT 'Extra data like noise level, face count, etc.',
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    INDEX idx_attempt (attempt_id),
    INDEX idx_type (type),
    INDEX idx_severity (severity),
    INDEX idx_time (created_at)
) ENGINE=InnoDB;

-- 4. Snapshots table
CREATE TABLE IF NOT EXISTS snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    user_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    face_count INT DEFAULT NULL,
    has_face TINYINT(1) DEFAULT NULL,
    flagged TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    INDEX idx_attempt (attempt_id),
    INDEX idx_flagged (flagged)
) ENGINE=InnoDB;

-- 5. Permission logs
CREATE TABLE IF NOT EXISTS permission_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    user_id INT NOT NULL,
    camera_status ENUM('granted','denied','blocked','unavailable') DEFAULT 'denied',
    mic_status ENUM('granted','denied','blocked','unavailable') DEFAULT 'denied',
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    INDEX idx_attempt (attempt_id)
) ENGINE=InnoDB;

-- 6. Session control table
CREATE TABLE IF NOT EXISTS exam_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    attempt_id INT NOT NULL,
    session_token VARCHAR(100) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    browser VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    last_heartbeat DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_token (session_token)
) ENGINE=InnoDB;

-- 7. Auto-save tracking
CREATE TABLE IF NOT EXISTS answer_autosave (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    answer TEXT,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_autosave (attempt_id, question_id)
) ENGINE=InnoDB;

-- 8. Add fields to exam_attempts
ALTER TABLE exam_attempts
    ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL AFTER status,
    ADD COLUMN user_agent TEXT DEFAULT NULL AFTER ip_address,
    ADD COLUMN session_token VARCHAR(100) DEFAULT NULL AFTER user_agent,
    ADD COLUMN total_violations INT DEFAULT 0 AFTER session_token,
    ADD COLUMN face_violations INT DEFAULT 0 AFTER total_violations,
    ADD COLUMN auto_submit_reason VARCHAR(100) DEFAULT NULL AFTER face_violations,
    ADD COLUMN last_heartbeat DATETIME DEFAULT NULL AFTER auto_submit_reason;
