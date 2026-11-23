-- Student Registration and Academic Management System
-- Adama Polytechnic College
-- Database schema (MySQL / MariaDB, as shipped with XAMPP)
--
-- You can import this file in phpMyAdmin, or open install.php in the
-- browser, which runs this file and also creates the first admin account.

CREATE DATABASE IF NOT EXISTS srms CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE srms;

-- System users (administrators and registrars) who can log in
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    role          ENUM('admin','registrar') NOT NULL DEFAULT 'registrar',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Students
CREATE TABLE IF NOT EXISTS students (
    student_id       VARCHAR(20)  PRIMARY KEY,
    first_name       VARCHAR(50)  NOT NULL,
    father_name      VARCHAR(50)  NOT NULL,
    grandfather_name VARCHAR(50)  NOT NULL,
    gender           ENUM('Male','Female') NOT NULL,
    date_of_birth    DATE         NOT NULL,
    phone            VARCHAR(20)  NOT NULL,
    department       VARCHAR(100) NOT NULL,
    program          VARCHAR(30)  NOT NULL,
    academic_year    VARCHAR(20)  NOT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Courses
CREATE TABLE IF NOT EXISTS courses (
    course_code  VARCHAR(15)  PRIMARY KEY,
    course_title VARCHAR(100) NOT NULL,
    credit_hours TINYINT UNSIGNED NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Grades: one result per student per course.
-- ON DELETE RESTRICT protects historical academic records: a student or
-- course that already has grades cannot be deleted.
CREATE TABLE IF NOT EXISTS grades (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  VARCHAR(20)  NOT NULL,
    course_code VARCHAR(15)  NOT NULL,
    mark        DECIMAL(5,2) NOT NULL,
    grade       CHAR(2)      NOT NULL,
    status      ENUM('Pass','Fail') NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_course (student_id, course_code),
    CONSTRAINT fk_grade_student FOREIGN KEY (student_id)
        REFERENCES students (student_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_grade_course FOREIGN KEY (course_code)
        REFERENCES courses (course_code) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;
