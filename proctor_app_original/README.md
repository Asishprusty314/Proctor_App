# 🛡️ ExamProctor — Online Proctored Examination System

A complete web-based proctored examination system with **Admin** and **Student** roles, built with PHP, MySQL, HTML/CSS, and JavaScript.

---

## ✨ Features

### Admin Panel
- **Dashboard** — Stats overview: total exams, students, attempts, avg score
- **Exam Management** — Create, edit, activate/deactivate exams with scheduling
- **Proctoring Rules** — Webcam monitoring, tab switch limits, fullscreen enforcement
- **Question Bank** — MCQ and descriptive questions with marks and correct answers
- **Attempts & Grading** — View student answers, auto-grade MCQs, manually grade descriptive
- **Violation Logs** — Tab switches, fullscreen exits, webcam blocks
- **Webcam Captures** — View periodic screenshots taken during exams
- **Export CSV** — Download results as spreadsheet

### Student Portal
- **Dashboard** — Personal stats, available exams, recent results
- **Exam Browser** — View active exams within scheduled windows
- **Proctored Exam Interface** — Timer, question navigator, auto-save
- **Anti-Cheating** — Tab switch detection, fullscreen lock, copy/paste blocking
- **Webcam Monitoring** — Live video feed with periodic captures
- **Auto-Submit** — On timeout or violation limit exceeded
- **Result Review** — Score breakdown, correct answers, per-question details

### Security
- Password hashing with `password_hash()` / `password_verify()`
- Prepared statements for all database queries (SQL injection prevention)
- PHP session-based authentication
- Role-based access control (Admin/Student)
- Input sanitization on frontend and backend
- Protected upload directories

---

## 🗂️ Project Structure

```
exam-system/
├── index.php              # Login page
├── register.php           # Student registration
├── logout.php             # Session logout
├── database.sql           # MySQL schema
├── includes/
│   ├── config.php         # DB config & session setup
│   ├── auth.php           # Auth helpers
│   └── functions.php      # Utility functions
├── assets/
│   ├── css/style.css      # Complete stylesheet
│   └── js/app.js          # Timer, tab detection, webcam, fullscreen
├── admin/
│   ├── layout.php         # Admin layout template
│   ├── index.php          # Admin dashboard
│   ├── exams.php          # Manage exams
│   ├── add_exam.php       # Create exam
│   ├── edit_exam.php      # Edit exam
│   ├── questions.php      # Manage questions
│   ├── add_question.php   # Add/edit question
│   ├── students.php       # View students
│   ├── attempts.php       # View all attempts
│   ├── view_attempt.php   # Attempt details & grading
│   └── export_results.php # Export CSV
├── student/
│   ├── layout.php         # Student layout template
│   ├── index.php          # Student dashboard
│   ├── exams.php          # Available exams
│   ├── take_exam.php      # Exam interface (proctored)
│   ├── results.php        # My results list
│   └── result_detail.php  # Detailed result view
├── api/
│   ├── submit_answer.php  # Save individual answer (AJAX)
│   ├── submit_exam.php    # Submit entire exam (AJAX)
│   ├── save_violation.php # Log violation (AJAX)
│   └── capture_webcam.php # Save webcam screenshot (AJAX)
└── uploads/
    ├── .htaccess          # Security: block PHP execution
    └── captures/          # Webcam capture images
```

---

## 🚀 Setup Instructions (XAMPP/WAMP)

### Prerequisites
- **XAMPP** or **WAMP** installed
- PHP 7.4+ with MySQLi extension
- Modern web browser (Chrome/Firefox/Edge)

### Step 1: Copy Files
Copy the entire `exam-system` folder to:
- **XAMPP**: `C:\xampp\htdocs\exam-system`
- **WAMP**: `C:\wamp64\www\exam-system`

### Step 2: Create Database
1. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Create a new database named `proctor_app`
3. Import `database.sql`:
   - Click the `proctor_app` database
   - Go to **Import** tab
   - Choose `database.sql` file and click **Go**

### Step 3: Configure Database Connection
Edit `includes/config.php` to match your local MySQL settings:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');        // Set your local MySQL password
define('DB_NAME', 'proctor_app');
```

Set `BASE_URL` to the URL path where you installed the project (for example, `/exam-system/`):
```php
define('BASE_URL', '/exam-system/');
```

### Step 4: Create an Administrator Account
The database schema does not create any demo accounts. Register an account at `http://localhost/exam-system/register.php`, then promote it to administrator in phpMyAdmin:

```sql
UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';
```

### Step 5: Access the System
- **Login**: `http://localhost/exam-system/`
- **Student**: Register at the registration page

---

## 📖 Usage Guide

### Admin Workflow
1. **Login** as admin
2. **Create an exam** → Set title, duration, schedule, proctoring rules
3. **Add questions** → Link MCQ/descriptive questions to the exam
4. **Activate** the exam and share with students
5. **Monitor** attempts, view violations, grade descriptive answers
6. **Export** results to CSV

### Student Workflow
1. **Register** and **login**
2. **Browse** available exams on the dashboard
3. **Start** an exam → Read instructions → Begin
4. **Answer** questions — answers auto-save
5. **Submit** or wait for auto-submit on timeout
6. **View** results and answer review

---

## 🗄️ Database Tables

| Table | Purpose |
|-------|---------|
| `users` | Admin and student accounts |
| `exams` | Exam configuration and scheduling |
| `questions` | MCQ and descriptive questions |
| `exam_attempts` | Student exam sessions with scores |
| `answers` | Individual question responses |
| `violation_logs` | Tab switches, fullscreen exits, etc. |
| `webcam_captures` | Periodic webcam screenshots |

---

## ⚙️ Technical Details

- **Backend**: PHP 7.4+ (procedural with prepared statements)
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Frontend**: Vanilla HTML5, CSS3 (custom properties), JavaScript (ES6+)
- **AJAX**: Fetch API for real-time answer saving
- **Webcam**: `getUserMedia` API with periodic base64 captures
- **Fullscreen**: Fullscreen API with exit detection
- **Timer**: JavaScript countdown with auto-submit
- **UI**: Dark academic theme, responsive, sidebar navigation

---

## 📝 License

This project is provided for educational purposes.

---

## Enterprise Upgrade

### New Features

**Pre-Exam Gateway** (`student/pre_exam.php`)
- Browser compatibility check
- Camera & microphone permission detection
- Face detection availability check
- Fullscreen support verification
- Network connectivity test
- Access key verification (real-time)
- All checks must pass before exam starts

**Face Detection** (via face-api.js)
- Real-time face detection every 3 seconds
- Detects: no face, single face (OK), multiple faces
- Face status indicator in exam topbar
- Auto-captures flagged snapshot on violation
- Contributes to total violation count

**Audio Monitoring** (Web Audio API)
- Background noise level detection every 2 seconds
- Audio level meter in exam sidebar
- Logs spikes above configurable threshold
- Noise level included in violation metadata

**Access Key System**
- Exam-level keys (one key for all students)
- User-specific one-time keys (assigned to individuals)
- Bulk key generation with custom prefix
- Real-time key verification via API
- Admin key management page

**Session & Device Control**
- One active session per user enforced
- IP address and browser fingerprinting
- Session token system with heartbeat
- Duplicate session detection and force-termination
- Heartbeat every 15 seconds

**Enhanced Violation Logging**
- 10+ violation types with severity levels
- JSON metadata per violation
- IP address and user agent logged
- Progress bar showing violations vs threshold
- Auto-submit at configurable max violations

**Auto-Save & Fail-Safe**
- All answers auto-saved every 30 seconds
- Offline detection with local storage backup
- Auto-sync when connection restored
- Separate autosave table for recovery

**Live Admin Monitor** (`admin/live_monitor.php`)
- Real-time view of all active sessions
- Camera status, face detection, violation count
- Heartbeat freshness indicator
- Flagged snapshot count per student
- Live violation feed
- Auto-refreshes every 10 seconds

**Snapshot Gallery** (in `admin/view_attempt.php`)
- Grid view of all webcam captures
- Face count badge per snapshot
- Flagged snapshots highlighted in red
- Filter: All vs Flagged only

### Enterprise Database Tables
- `violations` — Enhanced with type, severity, metadata, IP
- `snapshots` — Webcam captures with face count & flags
- `permission_logs` — Camera/mic permission tracking
- `exam_sessions` — Device control & heartbeat
- `exam_keys` — Access key management
- `answer_autosave` — Auto-save backup

### Setup for Enterprise Upgrade
1. Import `database_upgrade.sql` in phpMyAdmin
2. Replace all files from the zip
3. Enable face detection CDN: face-api.js loads from jsdelivr CDN
4. Ensure `uploads/snapshots/` directory is writable
