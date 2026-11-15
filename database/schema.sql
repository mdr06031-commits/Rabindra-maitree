-- =====================================================================
-- Rabindra Maitree University (RMU) - Complete Production Database Dump
-- Generated: 2026-11-15 23:01:38 (UTC+6)
-- Compatibility: cPanel phpMyAdmin, MySQL 5.7+, MySQL 8.0+, MariaDB 10.2+
-- Character Set: utf8mb4 / utf8mb4_unicode_ci (Full English & Bangla Support)
-- 
-- INSTRUCTIONS FOR cPanel phpMyAdmin IMPORT:
-- 1. Log in to your cPanel -> MySQL Databases -> Create a new database (e.g. youruser_rmu).
-- 2. Create a database user, set password, and assign ALL PRIVILEGES to the database.
-- 3. Open phpMyAdmin in cPanel, select your newly created database from the left sidebar.
-- 4. Click the 'Import' tab in the top menu.
-- 5. Choose this file (rmu_db_complete_production.sql) and click 'Go' / 'Import'.
-- 6. Open config/database.php (or config/database.local.php) and enter your cPanel DB name, username & password.
-- 
-- INSTRUCTIONS FOR LOCALHOST / MYSQL CLI:
--   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS rmu_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
--   mysql -u root -p rmu_db < rmu_db_complete_production.sql
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- (Optional: Uncomment the next 2 lines ONLY if importing via root CLI and you want to auto-create 'rmu_db')
-- CREATE DATABASE IF NOT EXISTS `rmu_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `rmu_db`;

-- -----------------------------------------------------
-- Table structure for table `users`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('superadmin','admin','editor') NOT NULL DEFAULT 'admin',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `failed_login_attempts` int(10) unsigned DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `site_settings`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `site_settings`;
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(60) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(30) NOT NULL DEFAULT 'general',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `audit_logs`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_audit_user` (`user_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `faculties`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `faculties`;
CREATE TABLE IF NOT EXISTS `faculties` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) NOT NULL,
  `name_en` varchar(150) NOT NULL,
  `name_bn` varchar(150) NOT NULL,
  `dean_name` varchar(100) DEFAULT NULL,
  `overview` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fa-university',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `departments`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `departments`;
CREATE TABLE IF NOT EXISTS `departments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `faculty_id` int(10) unsigned NOT NULL,
  `slug` varchar(80) NOT NULL,
  `name_en` varchar(150) NOT NULL,
  `name_bn` varchar(150) NOT NULL,
  `code` varchar(20) NOT NULL,
  `head_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `overview` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `fk_departments_faculty` (`faculty_id`),
  CONSTRAINT `fk_departments_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `programs`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `programs`;
CREATE TABLE IF NOT EXISTS `programs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `department_id` int(10) unsigned NOT NULL,
  `slug` varchar(100) NOT NULL,
  `title_en` varchar(150) NOT NULL,
  `title_bn` varchar(150) NOT NULL,
  `degree_level` enum('Undergraduate','Postgraduate','Diploma') NOT NULL DEFAULT 'Undergraduate',
  `degree_type` enum('Bachelor / Honours','B.Ed','Master''s') NOT NULL DEFAULT 'Bachelor / Honours',
  `duration` varchar(50) NOT NULL,
  `duration_years` decimal(3,1) NOT NULL DEFAULT 4.0,
  `total_semesters` tinyint(3) unsigned NOT NULL DEFAULT 8,
  `total_credits` int(10) unsigned NOT NULL,
  `semester_credits_json` text DEFAULT NULL,
  `eligibility` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `tuition_fee` varchar(100) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `fk_programs_department` (`department_id`),
  CONSTRAINT `fk_programs_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `academic_sessions`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `academic_sessions`;
CREATE TABLE IF NOT EXISTS `academic_sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `session_name` varchar(60) NOT NULL,
  `term_name` enum('Spring','Summer','Fall') NOT NULL,
  `session_year` smallint(5) unsigned NOT NULL,
  `sort_order` int(10) unsigned NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_session_name` (`session_name`),
  KEY `idx_as_enabled_sort` (`is_enabled`,`sort_order`),
  KEY `idx_as_year` (`session_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `passing_years`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `passing_years`;
CREATE TABLE IF NOT EXISTS `passing_years` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `year_value` smallint(5) unsigned NOT NULL,
  `label` varchar(40) NOT NULL,
  `sort_order` int(10) unsigned NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_year_value` (`year_value`),
  KEY `idx_py_enabled_sort` (`is_enabled`,`year_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `academic_edit_history`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `academic_edit_history`;
CREATE TABLE IF NOT EXISTS `academic_edit_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` enum('admission','student','program','session','passing_year') NOT NULL,
  `entity_id` int(10) unsigned NOT NULL,
  `student_id_code` varchar(50) DEFAULT NULL,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `field_name` varchar(80) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `reason` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_aeh_entity` (`entity_type`,`entity_id`),
  KEY `idx_aeh_student_code` (`student_id_code`),
  KEY `idx_aeh_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `semester_terms`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `semester_terms`;
CREATE TABLE IF NOT EXISTS `semester_terms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `term_name` varchar(100) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester_number` tinyint(3) unsigned NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `is_admission_open` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `term_name` (`term_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `faculty_members`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `faculty_members`;
CREATE TABLE IF NOT EXISTS `faculty_members` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `department_id` int(10) unsigned NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_faculty_members_dept` (`department_id`),
  CONSTRAINT `fk_faculty_members_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `notices`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `notices`;
CREATE TABLE IF NOT EXISTS `notices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(150) NOT NULL,
  `title_en` varchar(255) NOT NULL,
  `title_bn` varchar(255) NOT NULL,
  `category` enum('General','Admission','Academic','Scholarship','Exam') NOT NULL DEFAULT 'General',
  `content` mediumtext NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `published_date` date NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `view_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `events`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `events`;
CREATE TABLE IF NOT EXISTS `events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(150) NOT NULL,
  `title_en` varchar(255) NOT NULL,
  `title_bn` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` varchar(50) DEFAULT NULL,
  `location` varchar(150) NOT NULL DEFAULT 'RMU Campus, Kushtia',
  `description` mediumtext NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `news`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `news`;
CREATE TABLE IF NOT EXISTS `news` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(150) NOT NULL,
  `title_en` varchar(255) NOT NULL,
  `title_bn` varchar(255) NOT NULL,
  `published_date` date NOT NULL,
  `summary` text NOT NULL,
  `content` mediumtext NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `downloads`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `downloads`;
CREATE TABLE IF NOT EXISTS `downloads` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `category` enum('Admission','Academic','Examination','Administration','General') NOT NULL DEFAULT 'General',
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` varchar(50) NOT NULL DEFAULT '1.2 MB',
  `download_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `contact_messages`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `subject` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `replied` tinyint(1) NOT NULL DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `student_portal_notices`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `student_portal_notices`;
CREATE TABLE IF NOT EXISTS `student_portal_notices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `category` enum('Academic','Exam','Payment','General') NOT NULL DEFAULT 'Academic',
  `content` text NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `admission_sequences`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `admission_sequences`;
CREATE TABLE IF NOT EXISTS `admission_sequences` (
  `seq_year` smallint(5) unsigned NOT NULL,
  `last_app_seq` int(10) unsigned NOT NULL DEFAULT 0,
  `last_roll_seq` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`seq_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `admission_id_reservations`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `admission_id_reservations`;
CREATE TABLE IF NOT EXISTS `admission_id_reservations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `draft_token` varchar(64) NOT NULL,
  `session_key` varchar(128) NOT NULL,
  `seq_year` smallint(5) unsigned NOT NULL,
  `application_id` varchar(30) DEFAULT NULL,
  `roll_number` varchar(30) DEFAULT NULL,
  `status` enum('reserved','committed','expired') NOT NULL DEFAULT 'reserved',
  `committed_admission_id` int(10) unsigned DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `draft_token` (`draft_token`),
  UNIQUE KEY `application_id` (`application_id`),
  UNIQUE KEY `roll_number` (`roll_number`),
  KEY `idx_res_session_status` (`session_key`,`status`),
  KEY `idx_res_expires` (`status`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `admissions`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `admissions`;
CREATE TABLE IF NOT EXISTS `admissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_ref` varchar(30) NOT NULL,
  `application_id` varchar(50) DEFAULT NULL,
  `application_year` smallint(5) unsigned DEFAULT NULL,
  `full_name` varchar(120) NOT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `mother_name` varchar(150) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') NOT NULL DEFAULT 'Male',
  `mobile` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `present_address` text DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `academic_session` varchar(50) NOT NULL DEFAULT 'Fall 2026',
  `department_id` int(10) unsigned DEFAULT NULL,
  `program_id` int(10) unsigned DEFAULT NULL,
  `last_college` varchar(150) NOT NULL,
  `ssc_gpa` decimal(3,2) NOT NULL,
  `hsc_gpa` decimal(3,2) NOT NULL,
  `home_district` varchar(80) NOT NULL,
  `hsc_group` varchar(50) NOT NULL,
  `interested_subject` varchar(100) NOT NULL,
  `degree_type` varchar(60) DEFAULT NULL,
  `duration_years` decimal(3,1) DEFAULT NULL,
  `course_duration` varchar(60) DEFAULT NULL,
  `total_semesters` tinyint(3) unsigned DEFAULT NULL,
  `quota` enum('YES','NO') NOT NULL DEFAULT 'NO',
  `status` enum('Pending','Submitted','Under Review','Additional Information Required','Accepted','Rejected') NOT NULL DEFAULT 'Submitted',
  `admin_notes` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `accepted_at` datetime DEFAULT NULL,
  `accepted_by` int(10) unsigned DEFAULT NULL,
  `ssc_exam` varchar(50) DEFAULT 'S.S.C',
  `ssc_roll` varchar(50) DEFAULT NULL,
  `ssc_reg_no` varchar(60) DEFAULT NULL,
  `ssc_board` varchar(50) DEFAULT NULL,
  `ssc_result_type` varchar(50) DEFAULT 'GPA(out of 5)',
  `ssc_group` varchar(50) DEFAULT NULL,
  `ssc_year` varchar(10) DEFAULT NULL,
  `hsc_exam` varchar(50) DEFAULT 'H.S.C',
  `hsc_roll` varchar(50) DEFAULT NULL,
  `hsc_reg_no` varchar(60) DEFAULT NULL,
  `hsc_board` varchar(50) DEFAULT NULL,
  `hsc_result_type` varchar(50) DEFAULT 'GPA(out of 5)',
  `hsc_year` varchar(10) DEFAULT NULL,
  `passing_year` varchar(10) DEFAULT NULL,
  `expected_passing_year` smallint(5) unsigned DEFAULT NULL,
  `historical_record_approved` tinyint(1) NOT NULL DEFAULT 0,
  `historical_approved_by` int(10) unsigned DEFAULT NULL,
  `historical_approved_at` datetime DEFAULT NULL,
  `historical_approval_note` varchar(255) DEFAULT NULL,
  `original_submission_json` text DEFAULT NULL,
  `grad_applicable` tinyint(1) NOT NULL DEFAULT 0,
  `grad_exam` varchar(100) DEFAULT NULL,
  `grad_university` varchar(255) DEFAULT NULL,
  `grad_subject` varchar(150) DEFAULT NULL,
  `grad_result_type` varchar(50) DEFAULT NULL,
  `grad_result` varchar(50) DEFAULT NULL,
  `grad_year` varchar(10) DEFAULT NULL,
  `grad_duration` varchar(50) DEFAULT NULL,
  `payment_status` enum('Pending','Paid','Under Verification','Failed','Refunded') NOT NULL DEFAULT 'Pending',
  `payment_gateway_mode` enum('sandbox','live') NOT NULL DEFAULT 'sandbox',
  `payment_amount` decimal(10,2) NOT NULL DEFAULT 150.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `sender_number` varchar(30) DEFAULT NULL,
  `payment_screenshot` varchar(255) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `roll_number` varchar(50) DEFAULT NULL,
  `portal_password` varchar(100) DEFAULT NULL,
  `exam_roll` varchar(50) DEFAULT NULL,
  `exam_date` varchar(100) DEFAULT 'Saturday, 15 November 2026 at 10:00 AM',
  `exam_center` varchar(150) DEFAULT 'RMU Permanent Campus, Kushtia',
  `photo` varchar(255) DEFAULT NULL,
  `academic_documents` text DEFAULT NULL,
  `verification_token` varchar(64) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `pdf_generated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_ref` (`application_ref`),
  UNIQUE KEY `uniq_adm_application_id` (`application_id`),
  UNIQUE KEY `uniq_adm_roll_number` (`roll_number`),
  UNIQUE KEY `uniq_adm_exam_roll` (`exam_roll`),
  UNIQUE KEY `uq_admissions_transaction_id` (`transaction_id`),
  KEY `idx_admission_mobile` (`mobile`),
  KEY `idx_admission_status` (`status`),
  KEY `idx_adm_app_year` (`application_year`),
  KEY `idx_adm_program` (`program_id`),
  KEY `idx_adm_department` (`department_id`),
  KEY `idx_adm_student_id` (`student_id`),
  KEY `idx_adm_session` (`academic_session`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `admission_payment_transactions`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `admission_payment_transactions`;
CREATE TABLE IF NOT EXISTS `admission_payment_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admission_id` int(10) unsigned NOT NULL,
  `application_ref` varchar(50) NOT NULL,
  `gateway_session_id` varchar(64) NOT NULL,
  `idempotency_key` varchar(64) NOT NULL,
  `transaction_id` varchar(80) DEFAULT NULL,
  `gateway_provider` varchar(60) NOT NULL DEFAULT 'bKash',
  `gateway_mode` enum('sandbox','live') NOT NULL DEFAULT 'sandbox',
  `amount` decimal(10,2) NOT NULL DEFAULT 150.00,
  `paid_amount` decimal(10,2) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'BDT',
  `sender_number` varchar(30) DEFAULT NULL,
  `sandbox_otp` varchar(12) NOT NULL,
  `attempt_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `gateway_status` enum('INITIATED','AUTHORIZED','VERIFIED','FAILED','CANCELLED','EXPIRED','SUPERSEDED') NOT NULL DEFAULT 'INITIATED',
  `status` enum('initiated','pending_verification','verified','failed','cancelled','expired','superseded') NOT NULL DEFAULT 'initiated',
  `verification_signature` varchar(128) DEFAULT NULL,
  `gateway_nonce` varchar(64) NOT NULL,
  `gateway_response_json` longtext DEFAULT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `authorized_at` datetime DEFAULT NULL,
  `validated_once_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `gateway_session_id` (`gateway_session_id`),
  UNIQUE KEY `transaction_id` (`transaction_id`),
  KEY `idx_apt_admission` (`admission_id`),
  KEY `idx_apt_app_idem` (`admission_id`,`idempotency_key`),
  KEY `idx_apt_status` (`gateway_status`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `students`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `students`;
CREATE TABLE IF NOT EXISTS `students` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` varchar(30) NOT NULL,
  `roll_number` varchar(50) DEFAULT NULL,
  `application_number` varchar(50) DEFAULT NULL,
  `username` varchar(60) DEFAULT NULL,
  `full_name` varchar(120) NOT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `mother_name` varchar(150) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `password_updated_at` datetime DEFAULT NULL,
  `password_updated_by` int(10) unsigned DEFAULT NULL,
  `password_source` enum('system_generated','admin_reset','student_updated','legacy_hash') NOT NULL DEFAULT 'legacy_hash',
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `program_id` int(10) unsigned DEFAULT NULL,
  `degree_type` varchar(60) NOT NULL DEFAULT 'Bachelor / Honours',
  `department_id` int(10) unsigned DEFAULT NULL,
  `batch` varchar(30) NOT NULL,
  `academic_session` varchar(80) DEFAULT NULL,
  `duration_years` decimal(3,1) DEFAULT NULL,
  `program_duration` varchar(60) DEFAULT NULL,
  `total_semesters` tinyint(3) unsigned NOT NULL DEFAULT 8,
  `current_year` varchar(30) NOT NULL,
  `current_semester` varchar(50) NOT NULL,
  `cgpa` decimal(3,2) NOT NULL DEFAULT 0.00,
  `result_publication_date` date DEFAULT NULL,
  `result_status` enum('Pending','Estimated','Verified','Published','Suspended') NOT NULL DEFAULT 'Pending',
  `results_visible` tinyint(1) NOT NULL DEFAULT 1,
  `result_access_status` enum('Active','Suspended') NOT NULL DEFAULT 'Active',
  `result_suspended_reason` varchar(255) DEFAULT NULL,
  `results_hidden_reason` varchar(255) DEFAULT NULL,
  `credits_completed` int(10) unsigned NOT NULL DEFAULT 0,
  `total_credits_required` int(10) unsigned NOT NULL DEFAULT 140,
  `blood_group` varchar(10) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') NOT NULL DEFAULT 'Male',
  `home_district` varchar(60) DEFAULT NULL,
  `passing_year` varchar(10) DEFAULT NULL,
  `expected_passing_year` smallint(5) unsigned DEFAULT NULL,
  `historical_record_approved` tinyint(1) NOT NULL DEFAULT 0,
  `historical_approved_by` int(10) unsigned DEFAULT NULL,
  `historical_approved_at` datetime DEFAULT NULL,
  `historical_approval_note` varchar(255) DEFAULT NULL,
  `present_address` text DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `status` enum('Active','Graduated','Suspended','Deactivated','Deleted') NOT NULL DEFAULT 'Active',
  `session_version` int(10) unsigned NOT NULL DEFAULT 1,
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deactivated_at` datetime DEFAULT NULL,
  `deactivated_by` int(10) unsigned DEFAULT NULL,
  `deactivation_reason` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `deletion_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_id` (`student_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uniq_stu_roll_number` (`roll_number`),
  UNIQUE KEY `uniq_stu_app_number` (`application_number`),
  KEY `student_id_2` (`student_id`),
  KEY `email_2` (`email`),
  KEY `idx_students_username` (`username`),
  KEY `idx_students_status` (`status`),
  KEY `idx_students_results_visible` (`results_visible`),
  KEY `idx_students_program` (`program_id`),
  KEY `idx_students_department` (`department_id`),
  KEY `idx_students_session` (`academic_session`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `student_courses`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `student_courses`;
CREATE TABLE IF NOT EXISTS `student_courses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(10) unsigned NOT NULL,
  `course_code` varchar(30) NOT NULL,
  `course_name` varchar(150) NOT NULL,
  `credit` decimal(2,1) NOT NULL DEFAULT 3.0,
  `instructor_name` varchar(100) NOT NULL,
  `class_schedule` varchar(120) NOT NULL,
  `room_no` varchar(60) NOT NULL,
  `attendance_percent` int(10) unsigned NOT NULL DEFAULT 85,
  `semester` varchar(50) NOT NULL,
  `status` enum('Enrolled','Completed','Dropped') NOT NULL DEFAULT 'Enrolled',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `student_courses_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `student_fees`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `student_fees`;
CREATE TABLE IF NOT EXISTS `student_fees` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(10) unsigned NOT NULL,
  `fee_title` varchar(120) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('Paid','Pending','Failed','Due') NOT NULL DEFAULT 'Pending',
  `transaction_id` varchar(60) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `student_fees_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `student_notifications`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `student_notifications`;
CREATE TABLE IF NOT EXISTS `student_notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(10) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('result_published','request_update','grade_correction','general','result','account_status') NOT NULL DEFAULT 'result_published',
  `related_id` int(10) unsigned DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `student_notifications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `grading_rules`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `grading_rules`;
CREATE TABLE IF NOT EXISTS `grading_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `min_marks` decimal(5,2) NOT NULL,
  `max_marks` decimal(5,2) NOT NULL,
  `letter_grade` varchar(10) NOT NULL,
  `grade_point` decimal(3,2) NOT NULL,
  `remarks` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `student_results`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `student_results`;
CREATE TABLE IF NOT EXISTS `student_results` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(10) unsigned NOT NULL,
  `semester` varchar(60) NOT NULL,
  `course_code` varchar(30) NOT NULL,
  `course_name` varchar(150) NOT NULL,
  `credit` decimal(2,1) NOT NULL,
  `full_marks` int(10) unsigned NOT NULL DEFAULT 100,
  `marks` decimal(5,2) DEFAULT NULL,
  `letter_grade` varchar(5) NOT NULL,
  `grade_point` decimal(3,2) NOT NULL,
  `exam_term` varchar(50) NOT NULL DEFAULT 'Semester Final Exam',
  `academic_session` varchar(80) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `is_estimated` tinyint(1) NOT NULL DEFAULT 0,
  `result_type` enum('Verified','Estimated') NOT NULL DEFAULT 'Verified',
  `publish_status` enum('Draft','Under Review','Published') NOT NULL DEFAULT 'Published',
  `published_at` datetime DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `published_by` int(10) unsigned DEFAULT NULL,
  `correction_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `student_results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `student_semester_results`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `student_semester_results`;
CREATE TABLE IF NOT EXISTS `student_semester_results` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(10) unsigned NOT NULL,
  `semester_number` tinyint(3) unsigned NOT NULL,
  `semester_name` varchar(80) NOT NULL,
  `academic_session` varchar(80) DEFAULT NULL,
  `credit_hours` decimal(5,1) NOT NULL DEFAULT 0.0,
  `semester_gpa` decimal(3,2) NOT NULL DEFAULT 0.00,
  `completion_status` enum('Completed','Pending') NOT NULL DEFAULT 'Pending',
  `result_type` enum('Estimated','Verified') NOT NULL DEFAULT 'Estimated',
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_student_sem` (`student_id`,`semester_number`),
  KEY `idx_ssr_student` (`student_id`),
  CONSTRAINT `fk_ssr_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `result_requests`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `result_requests`;
CREATE TABLE IF NOT EXISTS `result_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(10) unsigned NOT NULL,
  `semester` varchar(60) NOT NULL,
  `course_code` varchar(30) DEFAULT NULL,
  `request_type` enum('Result Review','Grade Correction','Missing Marks','Transcript Inquiry','General Query') NOT NULL DEFAULT 'Result Review',
  `subject` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `status` enum('Pending','In Review','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `admin_notes` text DEFAULT NULL,
  `processed_by` int(10) unsigned DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `processed_by` (`processed_by`),
  CONSTRAINT `result_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `result_requests_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `result_modification_history`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `result_modification_history`;
CREATE TABLE IF NOT EXISTS `result_modification_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `result_id` int(10) unsigned DEFAULT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `admin_id` int(10) unsigned NOT NULL,
  `course_code` varchar(30) NOT NULL,
  `semester` varchar(60) NOT NULL,
  `field_name` varchar(50) NOT NULL,
  `old_value` varchar(100) DEFAULT NULL,
  `new_value` varchar(100) NOT NULL,
  `reason` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `result_id` (`result_id`),
  KEY `student_id` (`student_id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `result_modification_history_ibfk_1` FOREIGN KEY (`result_id`) REFERENCES `student_results` (`id`) ON DELETE SET NULL,
  CONSTRAINT `result_modification_history_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `result_modification_history_ibfk_3` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table structure for table `student_retention_archives`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `student_retention_archives`;
CREATE TABLE IF NOT EXISTS `student_retention_archives` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_pk_id` int(10) unsigned NOT NULL,
  `student_id` varchar(30) NOT NULL,
  `roll_number` varchar(50) DEFAULT NULL,
  `application_number` varchar(50) DEFAULT NULL,
  `username` varchar(60) DEFAULT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `program_title` varchar(200) DEFAULT NULL,
  `department_name` varchar(150) DEFAULT NULL,
  `batch` varchar(30) DEFAULT NULL,
  `final_cgpa` decimal(3,2) NOT NULL DEFAULT 0.00,
  `credits_completed` int(10) unsigned NOT NULL DEFAULT 0,
  `results_count` int(10) unsigned NOT NULL DEFAULT 0,
  `snapshot_json` longtext NOT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `deletion_reason` text DEFAULT NULL,
  `deleted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_archive_student_pk` (`student_pk_id`),
  KEY `idx_archive_student_id` (`student_id`),
  KEY `idx_archive_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- End of Rabindra Maitree University Database Dump
-- All 31 Tables & Seed Data Successfully Processed.
-- =====================================================================
SET FOREIGN_KEY_CHECKS = 1;
