-- ============================================
-- Online Proctored Examination System
-- Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS proctor_app;
USE proctor_app;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'student') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Exams table
CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    duration INT NOT NULL COMMENT 'Duration in minutes',
    total_marks INT NOT NULL DEFAULT 0,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    webcam_enabled TINYINT(1) DEFAULT 0,
    tab_switch_limit INT DEFAULT 3,
    fullscreen_required TINYINT(1) DEFAULT 1,
    randomize_questions TINYINT(1) DEFAULT 0,
    shuffle_options TINYINT(1) DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Questions table
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    type ENUM('mcq', 'descriptive') NOT NULL,
    question TEXT NOT NULL,
    option_a VARCHAR(500) DEFAULT NULL,
    option_b VARCHAR(500) DEFAULT NULL,
    option_c VARCHAR(500) DEFAULT NULL,
    option_d VARCHAR(500) DEFAULT NULL,
    correct_answer VARCHAR(10) DEFAULT NULL COMMENT 'Comma-separated: A,B,C,D for multi-select MCQ',
    marks INT NOT NULL DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Exam attempts table
CREATE TABLE exam_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exam_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME DEFAULT NULL,
    score INT DEFAULT 0,
    total_marks INT DEFAULT 0,
    mcq_score INT DEFAULT 0,
    descriptive_score INT DEFAULT 0,
    tab_switches INT DEFAULT 0,
    status ENUM('in_progress', 'completed', 'auto_submitted', 'violation') DEFAULT 'in_progress',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attempt (user_id, exam_id)
) ENGINE=InnoDB;

-- Answers table
CREATE TABLE answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    answer TEXT,
    is_correct TINYINT(1) DEFAULT NULL,
    marks_obtained INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_answer (attempt_id, question_id)
) ENGINE=InnoDB;

-- Violation logs table
CREATE TABLE violation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    user_id INT NOT NULL,
    exam_id INT NOT NULL,
    violation_type ENUM('tab_switch', 'fullscreen_exit', 'webcam_blocked', 'copy_paste') NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Webcam captures table
CREATE TABLE webcam_captures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    user_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- UPGRADE: If you already have the database,
-- run this ALTER to support multi-select MCQ:
-- ============================================
-- ALTER TABLE questions MODIFY correct_answer VARCHAR(10) DEFAULT NULL COMMENT 'Comma-separated: A,B,C,D for multi-select MCQ';
