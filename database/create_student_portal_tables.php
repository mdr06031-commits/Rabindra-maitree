<?php
/**
 * Migration Script: Create Student Portal Tables & Seed Demonstration Data
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();

echo "Creating Student Portal Tables...\n";

// 1. Create students table
$db->exec("
CREATE TABLE IF NOT EXISTS `students` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` VARCHAR(30) NOT NULL UNIQUE,
  `full_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `program_id` INT UNSIGNED NULL,
  `department_id` INT UNSIGNED NULL,
  `batch` VARCHAR(30) NOT NULL,
  `current_year` VARCHAR(30) NOT NULL,
  `current_semester` VARCHAR(50) NOT NULL,
  `cgpa` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  `credits_completed` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_credits_required` INT UNSIGNED NOT NULL DEFAULT 140,
  `blood_group` VARCHAR(10) NULL,
  `dob` DATE NULL,
  `home_district` VARCHAR(60) NULL,
  `status` ENUM('Active','Graduated','Suspended') NOT NULL DEFAULT 'Active',
  `avatar` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`student_id`),
  INDEX (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// 2. Create student_courses table
$db->exec("
CREATE TABLE IF NOT EXISTS `student_courses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNSIGNED NOT NULL,
  `course_code` VARCHAR(30) NOT NULL,
  `course_name` VARCHAR(150) NOT NULL,
  `credit` DECIMAL(2,1) NOT NULL DEFAULT 3.0,
  `instructor_name` VARCHAR(100) NOT NULL,
  `class_schedule` VARCHAR(120) NOT NULL,
  `room_no` VARCHAR(60) NOT NULL,
  `attendance_percent` INT UNSIGNED NOT NULL DEFAULT 85,
  `semester` VARCHAR(50) NOT NULL,
  `status` ENUM('Enrolled','Completed','Dropped') NOT NULL DEFAULT 'Enrolled',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// 3. Create student_results table
$db->exec("
CREATE TABLE IF NOT EXISTS `student_results` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNSIGNED NOT NULL,
  `semester` VARCHAR(60) NOT NULL,
  `course_code` VARCHAR(30) NOT NULL,
  `course_name` VARCHAR(150) NOT NULL,
  `credit` DECIMAL(2,1) NOT NULL,
  `letter_grade` VARCHAR(5) NOT NULL,
  `grade_point` DECIMAL(3,2) NOT NULL,
  `exam_term` VARCHAR(50) NOT NULL DEFAULT 'Semester Final Exam',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// 4. Create student_fees table
$db->exec("
CREATE TABLE IF NOT EXISTS `student_fees` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNSIGNED NOT NULL,
  `fee_title` VARCHAR(120) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `due_date` DATE NOT NULL,
  `status` ENUM('Paid','Pending','Failed','Due') NOT NULL DEFAULT 'Pending',
  `transaction_id` VARCHAR(60) NULL,
  `paid_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// 5. Create student_portal_notices table
$db->exec("
CREATE TABLE IF NOT EXISTS `student_portal_notices` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `category` ENUM('Academic','Exam','Payment','General') NOT NULL DEFAULT 'Academic',
  `content` TEXT NOT NULL,
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `published_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

echo "Tables created successfully. Seeding demonstration data...\n";

// Password hash for all demo accounts: 'student123'
$demoPasswordHash = password_hash('student123', PASSWORD_DEFAULT);

// Retrieve program IDs for CSE, Bangla, BBA
$cseProgId = $db->query("SELECT id FROM programs WHERE slug = 'bsc-in-cse'")->fetchColumn() ?: 7;
$cseDeptId = $db->query("SELECT id FROM departments WHERE slug = 'dept-cse'")->fetchColumn() ?: 5;

$banglaProgId = $db->query("SELECT id FROM programs WHERE slug = 'ba-in-bangla'")->fetchColumn() ?: 1;
$banglaDeptId = $db->query("SELECT id FROM departments WHERE slug = 'dept-bangla'")->fetchColumn() ?: 1;

$bbaProgId = $db->query("SELECT id FROM programs WHERE slug = 'bba'")->fetchColumn() ?: 5;
$bbaDeptId = $db->query("SELECT id FROM departments WHERE slug = 'dept-business-admin'")->fetchColumn() ?: 4;

// Clear existing demo students
$db->exec("DELETE FROM students WHERE student_id IN ('RMU2601001', 'RMU2601002', 'RMU2601003')");

// Insert Student 1 (Tanvir Hasan - CSE)
$stmt = $db->prepare("
  INSERT INTO `students` (student_id, full_name, email, phone, password_hash, program_id, department_id, batch, current_year, current_semester, cgpa, credits_completed, total_credits_required, blood_group, dob, home_district, status, avatar)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)
");

$stmt->execute([
  'RMU2601001',
  'Tanvir Hasan',
  'tanvir.cse@rmu.ac.bd',
  '01712345678',
  $demoPasswordHash,
  $cseProgId,
  $cseDeptId,
  '12th Batch (2024–2028)',
  '2nd Year',
  'Fall 2026 (4th Semester)',
  3.85,
  48,
  144,
  'B+',
  '2004-05-15',
  'Kushtia',
  'tanvir.svg'
]);
$s1_id = $db->lastInsertId();

// Insert Student 2 (Nusrat Jahan - Bangla)
$stmt->execute([
  'RMU2601002',
  'Nusrat Jahan',
  'nusrat.bangla@rmu.ac.bd',
  '01812345678',
  $demoPasswordHash,
  $banglaProgId,
  $banglaDeptId,
  '13th Batch (2025–2029)',
  '1st Year',
  'Fall 2026 (2nd Semester)',
  3.78,
  24,
  128,
  'O+',
  '2005-08-22',
  'Jhenaidah',
  'nusrat.svg'
]);
$s2_id = $db->lastInsertId();

// Insert Student 3 (Ahsan Habib - BBA)
$stmt->execute([
  'RMU2601003',
  'Ahsan Habib',
  'ahsan.bba@rmu.ac.bd',
  '01912345678',
  $demoPasswordHash,
  $bbaProgId,
  $bbaDeptId,
  '11th Batch (2023–2027)',
  '3rd Year',
  'Fall 2026 (6th Semester)',
  3.65,
  76,
  132,
  'A+',
  '2003-11-10',
  'Chuadanga',
  'ahsan.svg'
]);
$s3_id = $db->lastInsertId();

// Seed Enrolled Courses for Tanvir Hasan (CSE 2nd Year Fall 2026)
$courseStmt = $db->prepare("
  INSERT INTO `student_courses` (student_id, course_code, course_name, credit, instructor_name, class_schedule, room_no, attendance_percent, semester, status)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Enrolled')
");

$cseCourses = [
  ['CSE 201', 'Data Structures & Algorithms', 3.0, 'Dr. Md. Nazmul Islam (Professor & Head)', 'Sun & Tue: 10:00 AM – 11:30 AM', 'Lab 203 (Engineering Bldg)', 94],
  ['CSE 202', 'Data Structures & Algorithms Lab', 1.5, 'Engr. Rakibul Hasan (Lecturer)', 'Sun: 02:00 PM – 05:00 PM', 'Computer Lab 1', 96],
  ['CSE 203', 'Object-Oriented Programming (Java)', 3.0, 'Ms. Tasnim Tabassum (Assistant Professor)', 'Mon & Wed: 11:30 AM – 01:00 PM', 'Room 304', 90],
  ['CSE 204', 'Digital Logic Design & Microprocessors', 3.0, 'Dr. Farhana Yasmin (Associate Professor)', 'Mon & Wed: 09:30 AM – 11:00 AM', 'Hardware Lab 102', 88],
  ['MAT 201', 'Discrete Mathematics & Graph Theory', 3.0, 'Mr. Tariqul Anam (Senior Lecturer)', 'Thu: 10:00 AM – 01:00 PM', 'Room 205', 92]
];

foreach ($cseCourses as $c) {
  $courseStmt->execute([$s1_id, $c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6], 'Fall 2026']);
}

// Seed Completed Results for Tanvir Hasan (1st Year)
$resultStmt = $db->prepare("
  INSERT INTO `student_results` (student_id, semester, course_code, course_name, credit, letter_grade, grade_point, exam_term)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$cseResults = [
  // 1st Semester
  ['Spring 2025 (1st Semester)', 'CSE 101', 'Structured Programming in C', 3.0, 'A+', 4.00, 'Semester Final Exam'],
  ['Spring 2025 (1st Semester)', 'CSE 102', 'Structured Programming Lab', 1.5, 'A+', 4.00, 'Semester Final Exam'],
  ['Spring 2025 (1st Semester)', 'ENG 101', 'English Reading & Writing Skills', 3.0, 'A', 3.75, 'Semester Final Exam'],
  ['Spring 2025 (1st Semester)', 'MAT 101', 'Differential & Integral Calculus', 3.0, 'A+', 4.00, 'Semester Final Exam'],
  // 2nd Semester
  ['Fall 2025 (2nd Semester)', 'CSE 103', 'Electrical Circuits & Devices', 3.0, 'A-', 3.50, 'Semester Final Exam'],
  ['Fall 2025 (2nd Semester)', 'PHY 101', 'Physics (Waves, Optics & Electromagnetism)', 3.0, 'A', 3.75, 'Semester Final Exam'],
  ['Fall 2025 (2nd Semester)', 'BAN 105', 'Bangla Language & Tagore Literary Studies', 2.0, 'A+', 4.00, 'Semester Final Exam'],
  // 3rd Semester
  ['Spring 2026 (3rd Semester)', 'CSE 104', 'Object-Oriented Programming (C++)', 3.0, 'A+', 4.00, 'Semester Final Exam'],
  ['Spring 2026 (3rd Semester)', 'MAT 102', 'Coordinate Geometry & Linear Algebra', 3.0, 'A', 3.75, 'Semester Final Exam'],
  ['Spring 2026 (3rd Semester)', 'CSE 105', 'Basic Electronics & Devices Lab', 1.5, 'A+', 4.00, 'Semester Final Exam']
];

foreach ($cseResults as $r) {
  $resultStmt->execute([$s1_id, $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6]]);
}

// Seed Fees for Tanvir Hasan
$feeStmt = $db->prepare("
  INSERT INTO `student_fees` (student_id, fee_title, amount, due_date, status, transaction_id, paid_at)
  VALUES (?, ?, ?, ?, ?, ?, ?)
");

$fees = [
  ['Fall 2026 Semester Tuition & Lab Fee', 34500.00, '2026-11-15', 'Paid', 'RMU-PAY-2026-98124', '2026-10-05 11:20:00'],
  ['Spring 2026 Semester Tuition & Exam Fee', 32000.00, '2026-04-10', 'Paid', 'RMU-PAY-2026-44102', '2026-03-28 14:15:00'],
  ['Fall 2025 Semester Tuition Fee', 32000.00, '2025-10-15', 'Paid', 'RMU-PAY-2025-87211', '2025-10-02 09:40:00'],
  ['Spring 2025 Admission & 1st Semester Fee', 45000.00, '2025-01-20', 'Paid', 'RMU-PAY-2025-10245', '2025-01-15 16:30:00']
];

foreach ($fees as $f) {
  $feeStmt->execute([$s1_id, $f[0], $f[1], $f[2], $f[3], $f[4], $f[5]]);
}

// Seed Student Portal Notices
$db->exec("DELETE FROM student_portal_notices");
$noticeStmt = $db->prepare("
  INSERT INTO `student_portal_notices` (title, category, content, is_pinned, published_at)
  VALUES (?, ?, ?, ?, NOW())
");

$notices = [
  ['Mid-Term Examination Schedule: Fall 2026', 'Exam', 'All undergraduate students are hereby notified that the Mid-Term Examination of Fall 2026 semester will commence from November 22, 2026. Detailed routine is published on the portal.', 1],
  ['Admit Card Distribution for Upcoming Examinations', 'Academic', 'Students with 75% or higher class attendance and cleared semester dues can download their digital Admit Cards directly from the Student Portal starting November 10, 2026.', 1],
  ['Library Extended Hours for Final Exam Preparations', 'General', 'The Central Library and digital reading archives will remain open until 08:00 PM every evening to facilitate exam preparation.', 0],
  ['Fee Waiver & Merit Scholarship Application for Spring 2027', 'Payment', 'Students with CGPA 3.80 or above are invited to apply for the Rabindra Merit Waiver for the upcoming Spring 2027 academic session.', 0]
];

foreach ($notices as $n) {
  $noticeStmt->execute([$n[0], $n[1], $n[2], $n[3]]);
}

echo "Student Portal Database Setup Completed Successfully!\n";

