-- =========================
-- 1. CREATE TABLES
-- =========================

CREATE TABLE students (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50),
    age INT
);

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50),
    student_id INT,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

CREATE TABLE attendance (
    id SERIAL PRIMARY KEY,
    student_id INT,
    date DATE,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- =========================
-- 2. INSERT SAMPLE DATA
-- =========================

INSERT INTO students (name, age) VALUES
('Ali', 20),
('Ahmed', 22),
('Sara', 21);

INSERT INTO users (username, student_id) VALUES
('ali123', 1),
('ahmed22', 2);

INSERT INTO attendance (student_id, date) VALUES
(1, '2026-03-01'),
(2, '2026-03-02');

-- =========================
-- 3. SELECT EXAMPLES
-- =========================

-- All students
SELECT * FROM students;

-- Join example
SELECT students.name, attendance.date
FROM students
JOIN attendance ON students.id = attendance.student_id;

-- =========================
-- 4. UPDATE EXAMPLE
-- =========================

UPDATE students SET age = 25 WHERE id = 1;

-- =========================
-- 5. DELETE EXAMPLE
-- =========================

DELETE FROM attendance WHERE student_id = 1;
DELETE FROM students WHERE id = 1;

-- =========================
-- 6. ALTER TABLE EXAMPLES
-- =========================

-- Add column
ALTER TABLE students ADD email VARCHAR(100);

-- Rename column
ALTER TABLE students RENAME COLUMN name TO full_name;

-- Change data type
ALTER TABLE students ALTER COLUMN full_name TYPE VARCHAR(100);

-- Drop column
ALTER TABLE students DROP COLUMN email;

-- =========================
-- 7. FOREIGN KEY REMOVE
-- =========================

-- (constraint name check karne ke baad)
-- ALTER TABLE attendance DROP CONSTRAINT attendance_student_id_fkey;

-- =========================
-- 8. TRUNCATE EXAMPLES
-- =========================

-- Simple truncate
-- TRUNCATE TABLE students;

-- With identity reset
-- TRUNCATE TABLE students RESTART IDENTITY;

-- With cascade (important)
-- TRUNCATE TABLE students CASCADE;

-- Multiple tables
--TRUNCATE TABLE attendance, users, students RESTART IDENTITY CASCADE;

-- =========================
-- 9. SEQUENCE RESET (manual)
-- =========================

-- ALTER SEQUENCE students_id_seq RESTART WITH 1;

-- =========================
-- END
-- =========================