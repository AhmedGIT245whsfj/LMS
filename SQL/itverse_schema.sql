SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

CREATE DATABASE IF NOT EXISTS lms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lms_db;

CREATE TABLE IF NOT EXISTS track (
  track_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  track_name VARCHAR(191) NOT NULL,
  track_desc TEXT NULL,
  track_img VARCHAR(255) NULL,
  PRIMARY KEY (track_id),
  UNIQUE KEY uq_track_name (track_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS course (
  course_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  course_name VARCHAR(191) NOT NULL,
  course_desc TEXT NULL,
  course_img VARCHAR(255) NULL,
  course_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  course_author VARCHAR(191) NULL,
  track_id INT UNSIGNED NULL,
  PRIMARY KEY (course_id),
  KEY idx_course_track (track_id),
  CONSTRAINT fk_course_track
    FOREIGN KEY (track_id) REFERENCES track(track_id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lesson (
  lesson_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lesson_name VARCHAR(191) NOT NULL,
  lesson_desc TEXT NULL,
  lesson_link VARCHAR(255) NULL,
  course_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (lesson_id),
  KEY idx_lesson_course (course_id),
  CONSTRAINT fk_lesson_course
    FOREIGN KEY (course_id) REFERENCES course(course_id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin (
  admin_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_name VARCHAR(191) NULL,
  admin_email VARCHAR(191) NOT NULL,
  admin_pass VARCHAR(255) NOT NULL,
  PRIMARY KEY (admin_id),
  UNIQUE KEY uq_admin_email (admin_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student (
  stu_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  stu_name VARCHAR(191) NOT NULL,
  stu_email VARCHAR(191) NOT NULL,
  stu_pass VARCHAR(255) NOT NULL,
  stu_occ VARCHAR(191) NOT NULL DEFAULT '',
  stu_img VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (stu_id),
  UNIQUE KEY uq_student_email (stu_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS courseorder (
  order_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  course_id INT UNSIGNED NOT NULL,
  stu_id INT UNSIGNED NOT NULL,
  order_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  order_status VARCHAR(50) NULL,
  txn_id VARCHAR(100) NULL,
  PRIMARY KEY (order_id),
  KEY idx_order_course (course_id),
  KEY idx_order_student (stu_id),
  CONSTRAINT fk_order_course
    FOREIGN KEY (course_id) REFERENCES course(course_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_order_student
    FOREIGN KEY (stu_id) REFERENCES student(stu_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS feedback (
  f_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  f_content TEXT NOT NULL,
  f_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  stu_id INT UNSIGNED NULL,
  PRIMARY KEY (f_id),
  KEY idx_feedback_student (stu_id),
  CONSTRAINT fk_feedback_student
    FOREIGN KEY (stu_id) REFERENCES student(stu_id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
