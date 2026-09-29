-- ============================================================
-- School Management System — PostgreSQL Schema
-- Database : School_db
-- Updated  : April 2026
-- ============================================================
-- Fresh install:
--   psql -U postgres -c "CREATE DATABASE \"School_db\";"
--   psql -U postgres -d School_db -f school_complete.sql
-- ============================================================

-- ============================================================
-- DROP (fresh install ke liye uncomment karo)
-- ============================================================
-- DROP TABLE IF EXISTS marks          CASCADE;
-- DROP TABLE IF EXISTS results        CASCADE;
-- DROP TABLE IF EXISTS notice_reads   CASCADE;
-- DROP TABLE IF EXISTS notices        CASCADE;
-- DROP TABLE IF EXISTS notes          CASCADE;
-- DROP TABLE IF EXISTS exam_timetable CASCADE;
-- DROP TABLE IF EXISTS timetable      CASCADE;
-- DROP TABLE IF EXISTS attendance     CASCADE;
-- DROP TABLE IF EXISTS subjects       CASCADE;
-- DROP TABLE IF EXISTS users          CASCADE;
-- DROP TABLE IF EXISTS teachers       CASCADE;
-- DROP TABLE IF EXISTS students       CASCADE;
-- DROP TABLE IF EXISTS classes        CASCADE;

-- ============================================================
-- TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS classes (
    id         SERIAL       PRIMARY KEY,
    name       VARCHAR(100) NOT NULL UNIQUE,
    section    VARCHAR(50),
    created_at TIMESTAMPTZ  DEFAULT now()
);

CREATE TABLE IF NOT EXISTS students (
    id          SERIAL       PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    class       VARCHAR(100) NOT NULL,
    contact     VARCHAR(255),
    photo       VARCHAR(255),
    student_key VARCHAR(20)  NOT NULL UNIQUE,
    created_at  TIMESTAMPTZ  DEFAULT now()
);

CREATE TABLE IF NOT EXISTS teachers (
    id          SERIAL       PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    subject     VARCHAR(255),
    contact     VARCHAR(255),
    photo       VARCHAR(255),
    teacher_key VARCHAR(20)  NOT NULL UNIQUE,
    created_at  TIMESTAMPTZ  DEFAULT now()
);

CREATE TABLE IF NOT EXISTS users (
    id         SERIAL       PRIMARY KEY,
    username   VARCHAR(100) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       VARCHAR(20)  NOT NULL CHECK (role IN ('admin','teacher','student')),
    student_id INTEGER      REFERENCES students(id) ON DELETE SET NULL,
    teacher_id INTEGER      REFERENCES teachers(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ  DEFAULT now()
);

CREATE TABLE IF NOT EXISTS subjects (
    id           SERIAL       PRIMARY KEY,
    subject_name VARCHAR(255) NOT NULL,
    class_id     VARCHAR(100) NOT NULL,
    created_at   TIMESTAMPTZ  DEFAULT now(),
    UNIQUE (subject_name, class_id)
);

-- Exam schedule (admin/exams.php) — single source of truth for all exams
CREATE TABLE IF NOT EXISTS exam_timetable (
    id          SERIAL       PRIMARY KEY,
    exam_name   VARCHAR(255) NOT NULL,
    class_id    VARCHAR(100) NOT NULL,
    subject_id  INTEGER      REFERENCES subjects(id) ON DELETE SET NULL,
    exam_date   DATE         NOT NULL,
    start_time  TIME         NOT NULL,
    end_time    TIME         NOT NULL,
    created_by  INTEGER      REFERENCES users(id) ON DELETE SET NULL,
    created_at  TIMESTAMPTZ  DEFAULT now()
);

-- Weekly class timetable
CREATE TABLE IF NOT EXISTS timetable (
    id         SERIAL       PRIMARY KEY,
    class_id   VARCHAR(100) NOT NULL,
    day        VARCHAR(20)  NOT NULL,
    period     INTEGER      NOT NULL,
    subject    VARCHAR(255) NOT NULL,
    teacher    VARCHAR(255),
    start_time TIME         NOT NULL,
    end_time   TIME         NOT NULL,
    created_at TIMESTAMPTZ  DEFAULT now(),
    UNIQUE (class_id, day, period)
);

CREATE TABLE IF NOT EXISTS attendance (
    id              SERIAL      PRIMARY KEY,
    student_id      INTEGER     NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    attendance_date DATE        NOT NULL,
    status          VARCHAR(20) NOT NULL CHECK (status IN ('present','absent','late','leave')),
    created_by      INTEGER     REFERENCES users(id) ON DELETE SET NULL,
    created_at      TIMESTAMPTZ DEFAULT now(),
    UNIQUE (student_id, attendance_date)
);

CREATE TABLE IF NOT EXISTS notices (
    id         SERIAL       PRIMARY KEY,
    title      VARCHAR(255) NOT NULL,
    message    TEXT         NOT NULL,
    target     VARCHAR(50)  NOT NULL DEFAULT 'all'
                            CHECK (target IN ('all','students','teachers')),
    file_path  VARCHAR(255),
    created_by INTEGER      REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ  DEFAULT now()
);

CREATE TABLE IF NOT EXISTS notice_reads (
    id        SERIAL      PRIMARY KEY,
    notice_id INTEGER     NOT NULL REFERENCES notices(id)  ON DELETE CASCADE,
    user_id   INTEGER     NOT NULL REFERENCES users(id)    ON DELETE CASCADE,
    read_at   TIMESTAMPTZ DEFAULT now(),
    UNIQUE (notice_id, user_id)
);

CREATE TABLE IF NOT EXISTS notes (
    id          SERIAL       PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    class       VARCHAR(100) NOT NULL,
    subject     VARCHAR(100) NOT NULL,
    file_path   VARCHAR(255) NOT NULL,
    uploaded_by INTEGER      REFERENCES users(id) ON DELETE SET NULL,
    created_at  TIMESTAMPTZ  DEFAULT now()
);

CREATE TABLE IF NOT EXISTS note_reads (
    id      SERIAL      PRIMARY KEY,
    note_id INTEGER     NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
    user_id INTEGER     NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    read_at TIMESTAMPTZ DEFAULT now(),
    UNIQUE (note_id, user_id)
);

-- Admin/teacher single-entry results (exam_timetable se linked)
CREATE TABLE IF NOT EXISTS results (
    id          SERIAL       PRIMARY KEY,
    student_id  INTEGER      NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    exam_name   VARCHAR(255) NOT NULL,
    exam_class  VARCHAR(100) NOT NULL,
    subject_id  INTEGER      NOT NULL REFERENCES subjects(id) ON DELETE CASCADE,
    marks       NUMERIC(8,2) NOT NULL,
    total_marks NUMERIC(8,2) NOT NULL DEFAULT 100,
    created_by  INTEGER      REFERENCES users(id) ON DELETE SET NULL,
    created_at  TIMESTAMPTZ  DEFAULT now(),
    CONSTRAINT results_student_exam_subject_unique
        UNIQUE (student_id, exam_name, exam_class, subject_id)
);

-- Teacher bulk-entry marks (exam_timetable se linked)
CREATE TABLE IF NOT EXISTS marks (
    id             SERIAL       PRIMARY KEY,
    student_id     INTEGER      NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    exam_name      VARCHAR(255) NOT NULL,
    exam_class     VARCHAR(100) NOT NULL,
    subject_id     INTEGER      NOT NULL REFERENCES subjects(id) ON DELETE CASCADE,
    marks_obtained NUMERIC(8,2) NOT NULL,
    total_marks    NUMERIC(8,2) NOT NULL DEFAULT 100,
    created_by     INTEGER      REFERENCES users(id) ON DELETE SET NULL,
    created_at     TIMESTAMPTZ  DEFAULT now(),
    CONSTRAINT marks_student_exam_subject_unique
        UNIQUE (student_id, exam_name, exam_class, subject_id)
);

-- ============================================================
-- INDEXES
-- ============================================================

CREATE INDEX IF NOT EXISTS idx_students_class       ON students(class);
CREATE INDEX IF NOT EXISTS idx_subjects_class       ON subjects(class_id);
CREATE INDEX IF NOT EXISTS idx_exam_tt_class        ON exam_timetable(class_id);
CREATE INDEX IF NOT EXISTS idx_exam_tt_name         ON exam_timetable(exam_name);
CREATE INDEX IF NOT EXISTS idx_timetable_class      ON timetable(class_id);
CREATE INDEX IF NOT EXISTS idx_attendance_student   ON attendance(student_id);
CREATE INDEX IF NOT EXISTS idx_attendance_date      ON attendance(attendance_date);
CREATE INDEX IF NOT EXISTS idx_results_student      ON results(student_id);
CREATE INDEX IF NOT EXISTS idx_results_exam         ON results(exam_name);
CREATE INDEX IF NOT EXISTS idx_marks_student        ON marks(student_id);
CREATE INDEX IF NOT EXISTS idx_marks_exam           ON marks(exam_name);
CREATE INDEX IF NOT EXISTS idx_notice_reads_notice  ON notice_reads(notice_id);
CREATE INDEX IF NOT EXISTS idx_notice_reads_user    ON notice_reads(user_id);
CREATE INDEX IF NOT EXISTS idx_note_reads_note      ON note_reads(note_id);
CREATE INDEX IF NOT EXISTS idx_note_reads_user      ON note_reads(user_id);
CREATE INDEX IF NOT EXISTS idx_notes_class          ON notes(class);

-- ============================================================
-- DEFAULT ADMIN USER
-- username : admin
-- password : admin123
-- ============================================================

-- INSERT INTO users (username, password, role)
-- VALUES (
--     'admin',
--     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
--     'admin'
-- )
-- ON CONFLICT (username) DO NOTHING;

-- ============================================================
-- END
-- ============================================================
