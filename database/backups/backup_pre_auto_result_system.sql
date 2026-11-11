-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: rmu_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admissions`
--

DROP TABLE IF EXISTS `admissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_ref` varchar(30) NOT NULL,
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
  `quota` enum('YES','NO') NOT NULL DEFAULT 'NO',
  `status` enum('Pending','Submitted','Under Review','Accepted','Rejected') NOT NULL DEFAULT 'Submitted',
  `admin_notes` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ssc_exam` varchar(50) DEFAULT 'S.S.C',
  `ssc_roll` varchar(50) DEFAULT NULL,
  `ssc_board` varchar(50) DEFAULT NULL,
  `ssc_result_type` varchar(50) DEFAULT 'GPA(out of 5)',
  `ssc_group` varchar(50) DEFAULT NULL,
  `ssc_year` varchar(10) DEFAULT NULL,
  `hsc_exam` varchar(50) DEFAULT 'H.S.C',
  `hsc_roll` varchar(50) DEFAULT NULL,
  `hsc_board` varchar(50) DEFAULT NULL,
  `hsc_result_type` varchar(50) DEFAULT 'GPA(out of 5)',
  `hsc_year` varchar(10) DEFAULT NULL,
  `grad_applicable` tinyint(1) NOT NULL DEFAULT 0,
  `grad_exam` varchar(100) DEFAULT NULL,
  `grad_university` varchar(255) DEFAULT NULL,
  `grad_subject` varchar(150) DEFAULT NULL,
  `grad_result_type` varchar(50) DEFAULT NULL,
  `grad_result` varchar(50) DEFAULT NULL,
  `grad_year` varchar(10) DEFAULT NULL,
  `grad_duration` varchar(50) DEFAULT NULL,
  `payment_status` enum('Pending','Paid','Failed','Refunded') NOT NULL DEFAULT 'Pending',
  `payment_gateway_mode` enum('sandbox','live') NOT NULL DEFAULT 'sandbox',
  `payment_amount` decimal(10,2) NOT NULL DEFAULT 150.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `sender_number` varchar(30) DEFAULT NULL,
  `payment_screenshot` varchar(255) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `portal_password` varchar(100) DEFAULT NULL,
  `exam_roll` varchar(50) DEFAULT NULL,
  `exam_date` varchar(100) DEFAULT 'Saturday, 15 November 2026 at 10:00 AM',
  `exam_center` varchar(150) DEFAULT 'RMU Permanent Campus, Kushtia',
  `photo` varchar(255) DEFAULT NULL,
  `academic_documents` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_ref` (`application_ref`),
  KEY `idx_admission_mobile` (`mobile`),
  KEY `idx_admission_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=251 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admissions`
--

LOCK TABLES `admissions` WRITE;
/*!40000 ALTER TABLE `admissions` DISABLE KEYS */;
INSERT INTO `admissions` VALUES (175,'RMU-2026-6238','Raju Khan',NULL,NULL,NULL,'Male','01311863929','mdr06031@gmail.com',NULL,NULL,'Fall 2026',NULL,NULL,'jrkrtom',3.33,4.00,'gvcfb cv','Science','Bangla — BA (Honours)','NO','Accepted','','127.0.0.1','2026-11-03 21:47:16','2026-11-03 21:48:46','S.S.C','65878','Dinajpur','GPA(out of 5)','Science','2011','H.S.C','74527456','Dinajpur','GPA(out of 5)','2014',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Paid','sandbox',150.00,'bKash','TRX21412494','01311863929','receipt_RMU20266238_1793720854.svg','2026-11-03 21:47:34','RMU2601011','rmu9232','ADM-01011','Saturday, 15 November 2026 at 10:00 AM','RMU Permanent Campus, Kushtia',NULL,NULL),(247,'RMU-2026-5636','Test Student Candidate',NULL,NULL,NULL,'Male','01700998877','test@candidate.com',NULL,NULL,'Fall 2026',NULL,NULL,'Kushtia Model College',4.50,4.30,'Kushtia','Science','BSc Engg in CSE','NO','Submitted',NULL,NULL,'2026-11-09 23:44:38','2026-11-09 23:44:38','S.S.C',NULL,NULL,'GPA(out of 5)',NULL,NULL,'H.S.C',NULL,NULL,'GPA(out of 5)',NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Pending','sandbox',150.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Saturday, 15 November 2026 at 10:00 AM','RMU Permanent Campus, Kushtia',NULL,NULL);
/*!40000 ALTER TABLE `admissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_audit_user` (`user_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=215 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-10-30 03:40:47'),(2,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-10-31 06:36:38'),(3,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-10-31 06:36:43'),(4,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-10-31 06:48:01'),(5,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-10-31 06:48:19'),(6,NULL,'FAILED_LOGIN_ATTEMPT','Failed login attempt for username: \'admin@rmu.ac.bd\'.','127.0.0.1','2026-10-31 06:53:20'),(7,NULL,'FAILED_LOGIN_ATTEMPT','Failed login attempt for username: \'admin@rmu.ac.bd\'.','127.0.0.1','2026-10-31 06:53:38'),(8,NULL,'FAILED_LOGIN_ATTEMPT','Failed login attempt for username: \'admin@rmu.ac.bd\'.','127.0.0.1','2026-10-31 06:54:38'),(9,2,'USER_LOGIN','Administrator \'user\' logged in successfully.','127.0.0.1','2026-10-31 06:55:46'),(10,NULL,'FAILED_LOGIN_ATTEMPT','Failed login attempt for username: \'admin\'.','127.0.0.1','2026-10-31 06:56:43'),(11,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-10-31 06:57:20'),(12,1,'ADMISSION_DELETED','Admin deleted admission application ID #130','127.0.0.1','2026-10-31 06:58:08'),(13,1,'ADMISSION_DELETED','Admin deleted admission application ID #102','127.0.0.1','2026-10-31 06:58:11'),(14,1,'ADMISSION_DELETED','Admin deleted admission application ID #1','127.0.0.1','2026-10-31 06:58:16'),(15,1,'ADMISSION_DELETED','Admin deleted admission application ID #98','127.0.0.1','2026-10-31 06:58:20'),(16,1,'ADMISSION_DELETED','Admin deleted admission application ID #87','127.0.0.1','2026-10-31 06:58:24'),(17,1,'ADMISSION_DELETED','Admin deleted admission application ID #86','127.0.0.1','2026-10-31 06:58:26'),(18,1,'ADMISSION_DELETED','Admin deleted admission application ID #2','127.0.0.1','2026-10-31 06:58:32'),(19,1,'ADMISSION_DELETED','Admin deleted admission application ID #85','127.0.0.1','2026-10-31 06:58:35'),(20,1,'ADMISSION_DELETED','Admin deleted admission application ID #76','127.0.0.1','2026-10-31 06:58:37'),(21,1,'ADMISSION_DELETED','Admin deleted admission application ID #73','127.0.0.1','2026-10-31 06:58:43'),(22,1,'ADMISSION_DELETED','Admin deleted admission application ID #3','127.0.0.1','2026-10-31 06:58:51'),(23,1,'PORTAL_NOTICE_DELETED','Deleted student portal notice ID #5','127.0.0.1','2026-10-31 06:59:16'),(24,1,'SEMESTER_PROGRESS_UPDATED','Updated semester progress for RMU2601009: Year=1st Year, Sem=Fall 2026 (1st Semester), CGPA=0, Status=Active','127.0.0.1','2026-10-31 07:11:16'),(25,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-02 23:30:49'),(26,1,'ADMISSION_STATUS_UPDATED','Updated application ID 67 to status \'Submitted\' with notes: \'\'','127.0.0.1','2026-11-02 23:31:43'),(27,1,'ADMISSION_STATUS_UPDATED','Updated application ID 67 to status \'Submitted\' with notes: \'\'','127.0.0.1','2026-11-02 23:31:46'),(28,1,'ADMISSION_STATUS_UPDATED','Updated application ID 136 to status \'Submitted\' with notes: \'\'','127.0.0.1','2026-11-02 23:31:48'),(29,1,'SEMESTER_PROGRESS_UPDATED','Updated semester progress for RMU2601009: Year=1st Year, Sem=8th Semester (Completed), CGPA=3.33, Status=Active','127.0.0.1','2026-11-02 23:35:13'),(30,1,'SEMESTER_PROGRESS_UPDATED','Updated semester progress for RMU2601009: Year=4th Year, Sem=Spring 2030 (8th Semester), CGPA=3.33, Status=Active','127.0.0.1','2026-11-02 23:35:52'),(31,1,'SEMESTER_PROGRESS_UPDATED','Updated semester progress for RMU2601009: Year=4th Year, Sem=Spring 2030 (8th Semester), CGPA=3.33, Status=Active','127.0.0.1','2026-11-02 23:47:29'),(32,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-03 00:40:10'),(33,NULL,'FAILED_LOGIN_ATTEMPT','Failed login attempt for username: \'admin\'.','127.0.0.1','2026-11-03 21:28:38'),(34,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-03 21:29:27'),(35,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-03 21:30:33'),(36,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-03 21:33:11'),(38,1,'ADMISSION_DELETED','Admin deleted admission application ID #169','127.0.0.1','2026-11-03 21:34:10'),(39,1,'ADMISSION_DELETED','Admin deleted admission application ID #67','127.0.0.1','2026-11-03 21:34:14'),(40,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-03 21:44:35'),(41,1,'ADMISSION_STATUS_UPDATED','Updated application ID 175 to status \'Accepted\' with notes: \'\'','127.0.0.1','2026-11-03 21:48:46'),(42,1,'ADMISSION_STATUS_UPDATED','Updated application ID 175 to status \'Accepted\' with notes: \'\'','127.0.0.1','2026-11-03 21:48:48'),(43,1,'STUDENT_GRADUATED_ALL_SEMESTERS','Admin marked all 8 semesters completed and graduated student RMU2601011 (CGPA: 3.85)','127.0.0.1','2026-11-03 22:51:53'),(44,1,'SEMESTER_PROGRESS_UPDATED','Updated semester progress for RMU2601011: Year=4th Year, Sem=8th Semester (Completed), CGPA=3.85, Status=Graduated','127.0.0.1','2026-11-03 22:52:06'),(45,1,'SEMESTER_PROGRESS_UPDATED','Updated semester progress for RMU2601011: Year=4th Year, Sem=8th Semester (Completed), CGPA=3.85, Status=Graduated','127.0.0.1','2026-11-03 22:52:09'),(46,1,'SEMESTER_PROGRESS_UPDATED','Updated semester progress for RMU2601011: Year=4th Year, Sem=8th Semester (Completed), CGPA=3.85, Status=Graduated','127.0.0.1','2026-11-03 22:52:17'),(47,1,'SEMESTER_PROGRESS_UPDATED','Updated semester progress for RMU2601011: Year=4th Year, Sem=8th Semester (Completed), CGPA=3.85, Status=Graduated','127.0.0.1','2026-11-03 22:52:20'),(48,1,'STUDENT_UPDATED','Updated student ID #49 (Raju Khan)','127.0.0.1','2026-11-03 22:53:16'),(49,1,'SEMESTER_PROGRESS_UPDATED','Updated semester progress for RMU2601011: Year=4th Year, Sem=8th Semester (Completed), CGPA=3.5, Status=Graduated','127.0.0.1','2026-11-03 22:53:50'),(50,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-03 23:13:57'),(51,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-03 23:48:05'),(52,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 00:30:37'),(53,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 00:31:00'),(54,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 00:31:27'),(55,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 00:39:57'),(56,1,'RESULT_EDITED','Updated result #21 (CSE 101) for Student #4. Reason: Administrative grade verification & update','127.0.0.1','2026-11-04 00:42:49'),(57,1,'RESULT_EDITED','Updated result #21 (CSE 101) for Student #4. Reason: Administrative grade verification & update','127.0.0.1','2026-11-04 00:43:08'),(58,1,'ADMISSION_REJECTED','Rejected application #202. Notes: Application rejected.','127.0.0.1','2026-11-04 00:45:01'),(59,1,'ADMISSION_DELETED','Admin deleted admission application ID #202','127.0.0.1','2026-11-04 00:45:11'),(60,1,'STUDENT_DELETED','Deleted student: Test Student Candidate (RMU2601010)','127.0.0.1','2026-11-04 00:49:31'),(61,1,'STUDENT_DELETED','Deleted student: Tarek Mahmud (RMU2601009)','127.0.0.1','2026-11-04 00:49:34'),(62,1,'STUDENT_DELETED','Deleted student: ghrt (RMU2601008)','127.0.0.1','2026-11-04 00:49:49'),(63,1,'STUDENT_DELETED','Deleted student: ghrt (RMU2601007)','127.0.0.1','2026-11-04 00:49:52'),(64,1,'STUDENT_DELETED','Deleted student: etfdfretgr (RMU2601005)','127.0.0.1','2026-11-04 00:49:56'),(65,1,'STUDENT_DELETED','Deleted student: Tanvir Hasan (RMU2601001)','127.0.0.1','2026-11-04 00:50:01'),(66,1,'STUDENT_DELETED','Deleted student: Nusrat Jahan (RMU2601002)','127.0.0.1','2026-11-04 00:50:05'),(67,1,'STUDENT_DELETED','Deleted student: Ahsan Habib (RMU2601003)','127.0.0.1','2026-11-04 00:50:08'),(68,1,'STUDENT_DELETED','Deleted student: Shakil Ahmed (RMU2601004)','127.0.0.1','2026-11-04 00:50:11'),(69,1,'STUDENT_DELETED','Deleted student: ghrt (RMU2601006)','127.0.0.1','2026-11-04 00:50:17'),(70,1,'STUDENT_ADDED','Added new student: monir khan (RMU2614581)','127.0.0.1','2026-11-04 00:55:06'),(71,1,'STUDENT_UPDATED','Updated student ID #90 (monir khan)','127.0.0.1','2026-11-04 00:57:04'),(72,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 01:05:44'),(73,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 01:25:21'),(74,1,'STUDENT_DELETED','Deleted student: Tanvir Hasan (RMU2601001)','127.0.0.1','2026-11-04 01:30:18'),(75,1,'STUDENT_DELETED','Deleted student: Ahsan Habib (RMU2601003)','127.0.0.1','2026-11-04 01:30:22'),(76,1,'STUDENT_DELETED','Deleted student: Nusrat Jahan (RMU2601002)','127.0.0.1','2026-11-04 01:30:26'),(77,1,'STUDENT_DELETED','Deleted student: monir khan (RMU2614581)','127.0.0.1','2026-11-04 01:30:30'),(78,1,'RESULT_DELETED','Deleted result #53 for Student #49','127.0.0.1','2026-11-04 01:32:28'),(79,1,'RESULT_DELETED','Deleted result #54 for Student #49','127.0.0.1','2026-11-04 01:32:32'),(80,1,'RESULT_DELETED','Deleted result #55 for Student #49','127.0.0.1','2026-11-04 01:32:37'),(81,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 01:53:09'),(82,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 01:54:20'),(83,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 01:55:31'),(84,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 01:55:53'),(85,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 01:56:13'),(86,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 01:56:54'),(87,1,'STUDENT_ADDED_ONE_PAGE','Enrolled student: E2E Test Student (RMU2609160)','127.0.0.1','2026-11-04 01:56:55'),(88,1,'RESULTS_DRAFT_ONE_PAGE','Saved results as Draft for Student #127 via One-Page Console','127.0.0.1','2026-11-04 01:56:55'),(89,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 01:57:31'),(90,1,'STUDENT_ADDED_ONE_PAGE','Enrolled student: E2E Test Student (RMU2609311)','127.0.0.1','2026-11-04 01:57:32'),(91,1,'RESULTS_DRAFT_ONE_PAGE','Saved results as Draft for Student #128 via One-Page Console','127.0.0.1','2026-11-04 01:57:32'),(92,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 01:57:59'),(93,1,'STUDENT_ADDED_ONE_PAGE','Enrolled student: E2E Test Student (RMU2609375)','127.0.0.1','2026-11-04 01:58:00'),(94,1,'RESULTS_DRAFT_ONE_PAGE','Saved results as Draft for Student #129 via One-Page Console','127.0.0.1','2026-11-04 01:58:00'),(95,1,'STUDENT_UPDATED_ONE_PAGE','Updated student ID #129 (E2E Test Student)','127.0.0.1','2026-11-04 01:58:00'),(96,1,'RESULTS_PUBLISHED_ONE_PAGE','Published results for Student #129 via One-Page Console','127.0.0.1','2026-11-04 01:58:00'),(97,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 01:58:08'),(98,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 02:05:13'),(99,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 02:05:58'),(100,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 02:06:22'),(101,1,'STUDENT_ADDED_ONE_PAGE','Enrolled student: E2E Test Student (RMU2609385)','127.0.0.1','2026-11-04 02:06:22'),(102,1,'RESULTS_DRAFT_ONE_PAGE','Saved results as Draft for Student #136 via One-Page Console','127.0.0.1','2026-11-04 02:06:22'),(103,1,'STUDENT_UPDATED_ONE_PAGE','Updated student ID #136 (E2E Test Student)','127.0.0.1','2026-11-04 02:06:23'),(104,1,'RESULTS_PUBLISHED_ONE_PAGE','Published results for Student #136 via One-Page Console','127.0.0.1','2026-11-04 02:06:23'),(105,1,'SEMESTER_SET_CURRENT','Activated term ID #7 as current semester','127.0.0.1','2026-11-04 02:09:51'),(106,1,'SEMESTER_SET_CURRENT','Activated term ID #8 as current semester','127.0.0.1','2026-11-04 02:09:55'),(107,1,'STUDENT_UPDATED_ONE_PAGE','Updated student ID #128 (E2E Test Student)','127.0.0.1','2026-11-04 02:11:29'),(108,1,'PAYMENT_VERIFIED_ADMISSION','Verified ৳150 admission fee for application ID #231','127.0.0.1','2026-11-04 02:11:48'),(109,1,'PAYMENT_VERIFIED_TUITION','Verified semester tuition fee for fee voucher ID #35','127.0.0.1','2026-11-04 02:12:13'),(110,NULL,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #137 (Bachelor Test Student) matching target CGPA 3.85','127.0.0.1','2026-11-04 02:34:36'),(111,NULL,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #138 (Master Test Student) matching target CGPA 3.72','127.0.0.1','2026-11-04 02:34:36'),(112,NULL,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #137 (Bachelor Test Student) matching target CGPA 3.55','127.0.0.1','2026-11-04 02:34:36'),(113,NULL,'RESULTS_VERIFIED_OFFICIAL','Admin verified 4 estimated course result(s) in 1st Semester as official for student #137','127.0.0.1','2026-11-04 02:34:36'),(114,NULL,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #139 (Bachelor Test Student) matching target CGPA 3.85','127.0.0.1','2026-11-04 02:35:26'),(115,NULL,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #140 (Master Test Student) matching target CGPA 3.72','127.0.0.1','2026-11-04 02:35:26'),(116,NULL,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #139 (Bachelor Test Student) matching target CGPA 3.55','127.0.0.1','2026-11-04 02:35:26'),(117,NULL,'RESULTS_VERIFIED_OFFICIAL','Admin verified 4 estimated course result(s) in 1st Semester as official for student #139','127.0.0.1','2026-11-04 02:35:26'),(118,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 02:35:32'),(119,1,'STUDENT_ADDED_ONE_PAGE','Enrolled student: E2E Test Student (RMU2609301)','127.0.0.1','2026-11-04 02:35:32'),(120,1,'RESULTS_DRAFT_ONE_PAGE','Saved results as Draft for Student #141 via One-Page Console','127.0.0.1','2026-11-04 02:35:32'),(121,1,'STUDENT_UPDATED_ONE_PAGE','Updated student ID #141 (E2E Test Student)','127.0.0.1','2026-11-04 02:35:32'),(122,1,'RESULTS_PUBLISHED_ONE_PAGE','Published results for Student #141 via One-Page Console','127.0.0.1','2026-11-04 02:35:32'),(123,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 02:35:38'),(124,NULL,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #154 (Bachelor Test Student) matching target CGPA 3.85','127.0.0.1','2026-11-04 22:16:05'),(125,NULL,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #155 (Master Test Student) matching target CGPA 3.72','127.0.0.1','2026-11-04 22:16:06'),(126,NULL,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #154 (Bachelor Test Student) matching target CGPA 3.55','127.0.0.1','2026-11-04 22:16:06'),(127,NULL,'RESULTS_VERIFIED_OFFICIAL','Admin verified 4 estimated course result(s) in 1st Semester as official for student #154','127.0.0.1','2026-11-04 22:16:06'),(128,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-04 22:17:06'),(129,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-09 22:57:41'),(130,NULL,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #156 (Bachelor Test Student) matching target CGPA 3.85','127.0.0.1','2026-11-09 22:57:46'),(131,NULL,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #157 (Master Test Student) matching target CGPA 3.72','127.0.0.1','2026-11-09 22:57:46'),(132,NULL,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #156 (Bachelor Test Student) matching target CGPA 3.55','127.0.0.1','2026-11-09 22:57:47'),(133,NULL,'RESULTS_VERIFIED_OFFICIAL','Admin verified 4 estimated course result(s) in 1st Semester as official for student #156','127.0.0.1','2026-11-09 22:57:47'),(134,2,'USER_LOGIN','Administrator \'user\' logged in successfully.','127.0.0.1','2026-11-09 23:02:11'),(135,2,'PAYMENT_VERIFIED_ADMISSION','Verified ৳150 admission fee for application ID #239','127.0.0.1','2026-11-09 23:07:23'),(136,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-09 23:40:36'),(137,1,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #164 (Bachelor Test Student) matching target CGPA 3.85','127.0.0.1','2026-11-09 23:41:01'),(138,1,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #165 (Master Test Student) matching target CGPA 3.72','127.0.0.1','2026-11-09 23:41:02'),(139,1,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #164 (Bachelor Test Student) matching target CGPA 3.55','127.0.0.1','2026-11-09 23:41:02'),(140,1,'RESULTS_VERIFIED_OFFICIAL','Admin verified 4 estimated course result(s) in 1st Semester as official for student #164','127.0.0.1','2026-11-09 23:41:02'),(141,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-09 23:41:03'),(142,1,'STUDENT_ADDED_ONE_PAGE','Enrolled student: E2E Test Student (RMU2609302)','127.0.0.1','2026-11-09 23:41:03'),(143,1,'RESULTS_DRAFT_ONE_PAGE','Saved results as Draft for Student #166 via One-Page Console','127.0.0.1','2026-11-09 23:41:03'),(144,1,'STUDENT_UPDATED_ONE_PAGE','Updated student ID #166 (E2E Test Student)','127.0.0.1','2026-11-09 23:41:03'),(145,1,'RESULTS_PUBLISHED_ONE_PAGE','Published results for Student #166 via One-Page Console','127.0.0.1','2026-11-09 23:41:03'),(146,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-09 23:42:39'),(153,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-09 23:43:18'),(166,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-09 23:43:51'),(179,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-09 23:44:04'),(180,1,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #172 (Bachelor Test Student) matching target CGPA 3.85','127.0.0.1','2026-11-09 23:44:05'),(181,1,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #173 (Master Test Student) matching target CGPA 3.72','127.0.0.1','2026-11-09 23:44:06'),(182,1,'ESTIMATED_RESULTS_GENERATED','Generated estimated academic results for student #172 (Bachelor Test Student) matching target CGPA 3.55','127.0.0.1','2026-11-09 23:44:06'),(183,1,'RESULTS_VERIFIED_OFFICIAL','Admin verified 4 estimated course result(s) in 1st Semester as official for student #172','127.0.0.1','2026-11-09 23:44:06'),(184,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-09 23:44:07'),(185,1,'STUDENT_ADDED_ONE_PAGE','Enrolled student: E2E Test Student (RMU2609157)','127.0.0.1','2026-11-09 23:44:07'),(186,1,'RESULTS_DRAFT_ONE_PAGE','Saved results as Draft for Student #174 via One-Page Console','127.0.0.1','2026-11-09 23:44:07'),(187,1,'STUDENT_UPDATED_ONE_PAGE','Updated student ID #174 (E2E Test Student)','127.0.0.1','2026-11-09 23:44:07'),(188,1,'RESULTS_PUBLISHED_ONE_PAGE','Published results for Student #174 via One-Page Console','127.0.0.1','2026-11-09 23:44:07'),(189,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-09 23:44:09'),(202,1,'USER_LOGIN','Administrator \'admin\' logged in successfully.','127.0.0.1','2026-11-09 23:45:51');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_messages` (
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
) ENGINE=InnoDB AUTO_INCREMENT=124 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_messages`
--

LOCK TABLES `contact_messages` WRITE;
/*!40000 ALTER TABLE `contact_messages` DISABLE KEYS */;
INSERT INTO `contact_messages` VALUES (1,'Md. Shafiul Islam','shafiul.student@gmail.com','01710998877','Inquiry regarding CSE Evening Batch','Assalamu Alaikum, I am an employed diploma graduate interested in enrolling in the BSc in CSE program. Does RMU offer evening or weekend classes for diploma holders?',0,0,'127.0.0.1','2026-10-17 12:39:47'),(2,'Nusrat Jahan','nusrat.jahan@yahoo.com','01823456789','Tuition Fee Waiver Inquiry for Fall 2026','Hello, I obtained GPA 5.00 in both SSC and HSC. What percentage of tuition waiver am I eligible to receive at Rabindra Maitree University?',1,1,'127.0.0.1','2026-10-17 12:39:47'),(3,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-17 12:56:19'),(4,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-17 12:58:06'),(5,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-17 12:58:40'),(6,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-17 12:59:35'),(7,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-17 13:16:28'),(8,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-17 13:32:32'),(9,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-17 13:41:33'),(10,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-17 13:44:33'),(11,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-17 13:58:08'),(12,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-17 14:01:28'),(13,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-17 14:10:30'),(14,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-17 14:11:38'),(15,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-17 14:22:30'),(16,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-19 11:13:15'),(17,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-19 11:31:04'),(18,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-19 11:37:42'),(19,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-19 11:46:28'),(20,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-19 12:04:13'),(21,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-19 12:15:42'),(22,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-19 12:21:17'),(23,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-19 12:27:06'),(24,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-20 10:41:58'),(25,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-20 10:59:10'),(26,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-20 11:08:42'),(27,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-20 11:09:52'),(28,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-20 11:20:52'),(29,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-20 11:27:15'),(30,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-20 11:28:33'),(31,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-20 11:38:48'),(32,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-20 11:46:40'),(33,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-20 11:52:31'),(34,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-20 11:55:46'),(35,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-20 11:58:02'),(36,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-20 12:12:34'),(37,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-20 12:19:07'),(38,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-20 12:25:57'),(39,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-20 12:33:12'),(40,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-20 12:41:25'),(41,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-25 10:26:30'),(42,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-25 10:34:27'),(43,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-25 10:36:18'),(44,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-26 07:01:05'),(45,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-26 07:33:57'),(46,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-26 08:21:24'),(47,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-26 08:37:11'),(48,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-26 09:15:19'),(49,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-26 10:07:06'),(50,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-26 10:10:16'),(51,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-26 10:22:00'),(52,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-27 01:35:02'),(53,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-27 01:43:17'),(54,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-27 02:12:31'),(55,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-27 07:19:01'),(56,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-27 07:28:55'),(57,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-27 07:50:22'),(58,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-27 07:58:26'),(59,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-27 08:36:28'),(60,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-27 09:04:07'),(61,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-27 09:10:38'),(62,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-27 09:14:25'),(63,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-28 07:00:07'),(64,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-28 07:12:46'),(65,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-28 07:19:04'),(66,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-28 07:22:51'),(67,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-28 07:29:55'),(68,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-28 07:34:24'),(69,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-28 07:48:13'),(70,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-28 07:52:19'),(71,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-28 08:11:40'),(72,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-28 08:13:31'),(73,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-28 08:28:10'),(74,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-28 09:27:57'),(75,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-28 10:03:59'),(76,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-28 10:04:49'),(77,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-28 10:11:42'),(78,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-29 22:26:06'),(79,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-29 22:39:41'),(80,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-29 23:12:55'),(81,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-29 23:16:44'),(82,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-29 23:38:21'),(83,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-30 00:27:19'),(84,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-30 00:37:45'),(85,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-30 03:36:49'),(86,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-31 06:44:38'),(87,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-31 06:47:37'),(88,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-31 06:56:06'),(89,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-10-31 07:09:14'),(90,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-02 23:28:40'),(91,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-02 23:46:01'),(92,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-02 23:54:57'),(93,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-03 00:06:12'),(94,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-03 00:08:12'),(95,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-03 00:26:04'),(96,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-03 00:31:54'),(97,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-03 00:32:56'),(98,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-03 00:33:50'),(99,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-03 00:34:23'),(100,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-03 21:24:53'),(101,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-03 21:30:56'),(102,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-03 21:44:25'),(103,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-03 23:00:56'),(104,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-03 23:13:45'),(105,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-03 23:41:29'),(106,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-03 23:47:39'),(107,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-03 23:48:31'),(108,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-04 00:27:22'),(109,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-04 00:28:17'),(110,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-04 00:29:41'),(111,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-04 00:30:19'),(112,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-04 01:03:02'),(113,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-04 01:03:56'),(114,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-04 01:05:30'),(115,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-04 01:25:11'),(116,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-04 01:50:17'),(117,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-04 01:51:50'),(118,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-04 01:52:58'),(119,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-04 01:59:18'),(120,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-04 02:36:02'),(121,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-04 22:15:49'),(122,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-09 23:40:51'),(123,'Automated Test User','test.user@rmu-test.com','01711002233','Test Unit Inquiry','Testing contact database pipeline.',0,0,'127.0.0.1','2026-11-09 23:44:38');
/*!40000 ALTER TABLE `contact_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,1,'dept-english','Department of English','','ENG','Prof. Shamsul Alam','english@rmu.ac.bd','+8801302991188','Offering comprehensive study of English literature, linguistics, communication skills, and critical theory.',1,1,'2026-10-17 12:39:47'),(2,1,'dept-bangla','Department of Bangla','','BNG','Dr. Anowar Hossain','bangla@rmu.ac.bd','+8801302991188','Dedicated to the heritage of Bengali literature, philology, folklore, and the profound philosophy of Rabindranath Tagore.',2,1,'2026-10-17 12:39:47'),(3,1,'dept-fine-arts','Department of Fine Arts','','FAR','Kazi Mahbubur Rahman','finearts@rmu.ac.bd','+8801302991188','Nurturing visual creativity, painting, sculpture, and graphic artistry with studio workshops.',3,1,'2026-10-17 12:39:47'),(4,1,'dept-music','Department of Music','','MUS','Rezwana Chowdhury','music@rmu.ac.bd','+8801302991188','Promoting classical music, Rabindra Sangeet, Nazrul Geeti, and traditional folk music heritage.',4,1,'2026-10-17 12:39:47'),(5,1,'dept-physical-education','Department of Physical Education and Sports Science','','PESS','Md. Rafiqul Islam','sports@rmu.ac.bd','+8801302991188','Focusing on athletic training, sports physiology, physical fitness management, and kinesiology.',5,1,'2026-10-17 12:39:47'),(6,2,'dept-cse','Department of Computer Science and Engineering','','CSE','Dr. Tariqul Islam','cse@rmu.ac.bd','+8801302991188','Equipping students with expertise in software engineering, algorithms, AI, cloud computing, and cybersecurity.',6,1,'2026-10-17 12:39:47'),(7,2,'dept-eee','Department of Electrical and Electronic Engineering','','EEE','Engr. Mahfuzur Rahman','eee@rmu.ac.bd','+8801302991188','Focusing on power systems, telecommunications, microelectronics, and renewable energy.',7,1,'2026-10-17 12:39:47'),(8,2,'dept-ict','Department of Information and Communication Technology','','ICT','Syed Nazmul Hasan','ict@rmu.ac.bd','+8801302991188','Specializing in computer networks, telecommunications, web technologies, and systems integration.',8,1,'2026-10-17 12:39:47'),(9,2,'dept-microbiology','Department of Microbiology','','MIC','Dr. Nusrat Jahan','microbiology@rmu.ac.bd','+8801302991188','Investigating bacterial and viral genetics, immunology, pharmaceutical microbiology, and clinical research.',9,1,'2026-10-17 12:39:47'),(10,3,'dept-business-administration','Department of Business Administration','','DBA','Dr. Mahmudul Hasan','dba@rmu.ac.bd','+8801302991188','Delivering premier business education in finance, marketing, HRM, supply chain, and strategic management.',10,1,'2026-10-17 12:39:47'),(11,4,'dept-agriculture','Department of Agriculture','','AGR','Dr. A. B. M. Faruq','agriculture@rmu.ac.bd','+8801302991188','Conducting scientific training in crop science, soil fertility, plant pathology, and modern agro-enterprise.',11,1,'2026-10-17 12:39:47');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `downloads`
--

DROP TABLE IF EXISTS `downloads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `downloads` (
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `downloads`
--

LOCK TABLES `downloads` WRITE;
/*!40000 ALTER TABLE `downloads` DISABLE KEYS */;
INSERT INTO `downloads` VALUES (1,'Undergraduate Admission Application Form 2026','Admission','rmu_undergrad_admission_form.pdf','storage/downloads/rmu_undergrad_admission_form.pdf','850 KB',420,1,'2026-10-17 12:39:47'),(2,'RMU Academic Calendar 2026 (All Semesters)','Academic','rmu_academic_calendar_2026.pdf','storage/downloads/rmu_academic_calendar_2026.pdf','620 KB',315,1,'2026-10-17 12:39:47'),(3,'Merit & Need-Based Scholarship Application Form','Admission','rmu_scholarship_application_form.pdf','storage/downloads/rmu_scholarship_application_form.pdf','480 KB',188,1,'2026-10-17 12:39:47'),(4,'Undergraduate Course Catalog & Fee Structure Guide','Admission','rmu_course_catalog_fee_structure.pdf','storage/downloads/rmu_course_catalog_fee_structure.pdf','1.4 MB',560,1,'2026-10-17 12:39:47'),(5,'Transcript & Degree Verification Request Form','Examination','rmu_transcript_request_form.pdf','storage/downloads/rmu_transcript_request_form.pdf','390 KB',95,1,'2026-10-17 12:39:47');
/*!40000 ALTER TABLE `downloads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `events` (
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `events`
--

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
INSERT INTO `events` VALUES (1,'fall-2026-orientation','Fall 2026 Freshers Orientation & Welcome Gala','','2026-11-10','10:00 AM - 3:00 PM','RMU Central Auditorium, Kushtia','Welcoming all newly admitted students of Fall 2026 semester with inspirational addresses from the Vice-Chancellor, deans, and a vibrant cultural showcase.',NULL,1,1,'2026-10-17 12:39:47'),(2,'national-rabindra-conference','National Rabindra Conference & Literary Fest','','2026-12-05','09:30 AM - 5:30 PM','Rabindra Moncho, Kushtia Campus','A two-day academic symposium bringing together eminent litterateurs, researchers, and Tagore scholars from across Bangladesh and abroad.',NULL,1,1,'2026-10-17 12:39:47'),(3,'rmu-tech-carnival-2026','RMU Annual Tech Carnival & Project Showcase','','2026-12-18','10:00 AM - 4:00 PM','Engineering Building & Computer Labs','Showcasing student engineering innovations in Robotics, Web Apps, IoT, and competitive programming hackathon.',NULL,1,1,'2026-10-17 12:39:47');
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faculties`
--

DROP TABLE IF EXISTS `faculties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faculties` (
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faculties`
--

LOCK TABLES `faculties` WRITE;
/*!40000 ALTER TABLE `faculties` DISABLE KEYS */;
INSERT INTO `faculties` VALUES (1,'arts-and-social-science','Faculty of Arts and Social Science','','Prof. Dr. M. A. Karim','The Faculty of Arts and Social Science fosters critical thinking, cultural appreciation, and creative expression through rich curricula in languages, arts, and physical education.','fa-feather-alt',1,1,'2026-10-17 12:39:47'),(2,'engineering-and-technology','Faculty of Engineering and Technology','','Dr. Engr. S. R. Rahman','The Faculty of Engineering is committed to producing world-class engineers, computer scientists, and technologists equipped with modern laboratory training and research skills.','fa-laptop-code',2,1,'2026-10-17 12:39:47'),(3,'business-studies','Faculty of Business Studies','','Prof. Mohammad Hossain','Dedicated to cultivating innovative business leaders and ethical entrepreneurs who drive economic and social progress in an interconnected global market.','fa-briefcase',3,1,'2026-10-17 12:39:47'),(4,'agriculture','Faculty of Agriculture','','Dr. Farhana Yasmin','Advancing agricultural science, sustainable farming, and food security through field research and cutting-edge agro-technology.','fa-seedling',4,1,'2026-10-17 12:39:47');
/*!40000 ALTER TABLE `faculties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faculty_members`
--

DROP TABLE IF EXISTS `faculty_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faculty_members` (
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faculty_members`
--

LOCK TABLES `faculty_members` WRITE;
/*!40000 ALTER TABLE `faculty_members` DISABLE KEYS */;
INSERT INTO `faculty_members` VALUES (1,6,'Dr. Tariqul Islam','Associate Professor & Head','tariqul@rmu.ac.bd','+8801302991188','PhD in Computer Science (BUET), MSc in CSE','Specializes in Distributed Systems, Artificial Intelligence, and Software Engineering methodologies.',NULL,1,1,'2026-10-17 12:39:47'),(2,6,'Farhana Akter','Assistant Professor','farhana.cse@rmu.ac.bd','+8801302991188','MSc in Computer Science & Engineering (DU)','Research interest in Data Mining, Natural Language Processing, and Machine Learning.',NULL,2,1,'2026-10-17 12:39:47'),(3,7,'Engr. Mahfuzur Rahman','Assistant Professor & Head','mahfuz.eee@rmu.ac.bd','+8801302991188','MSc in Electrical & Electronic Engineering (RUET)','Focuses on Renewable Solar Microgrids, Power Systems Analysis, and Industrial Automation.',NULL,1,1,'2026-10-17 12:39:47'),(4,1,'Prof. Shamsul Alam','Professor & Head','shamsul.eng@rmu.ac.bd','+8801302991188','PhD in English Literature (CU), MA in ELT','Renowned literary scholar focusing on Post-Colonial Literature, Shakespearean Drama, and Applied Linguistics.',NULL,1,1,'2026-10-17 12:39:47'),(5,10,'Dr. Mahmudul Hasan','Associate Professor & Head','mahmud.dba@rmu.ac.bd','+8801302991188','PhD in Finance (DU), MBA in Banking','Expert in Corporate Governance, Behavioral Finance, and SME Development.',NULL,1,1,'2026-10-17 12:39:47');
/*!40000 ALTER TABLE `faculty_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grading_rules`
--

DROP TABLE IF EXISTS `grading_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grading_rules` (
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grading_rules`
--

LOCK TABLES `grading_rules` WRITE;
/*!40000 ALTER TABLE `grading_rules` DISABLE KEYS */;
INSERT INTO `grading_rules` VALUES (1,80.00,100.00,'A+',4.00,'Outstanding',1,1,'2026-11-04 00:09:55','2026-11-04 00:09:55'),(2,75.00,79.99,'A',3.75,'Excellent',1,2,'2026-11-04 00:09:55','2026-11-04 00:09:55'),(3,70.00,74.99,'A-',3.50,'Very Good',1,3,'2026-11-04 00:09:55','2026-11-04 00:09:55'),(4,65.00,69.99,'B+',3.25,'Good',1,4,'2026-11-04 00:09:55','2026-11-04 00:09:55'),(5,60.00,64.99,'B',3.00,'Satisfactory',1,5,'2026-11-04 00:09:55','2026-11-04 00:09:55'),(6,55.00,59.99,'B-',2.75,'Above Average',1,6,'2026-11-04 00:09:55','2026-11-04 00:09:55'),(7,50.00,54.99,'C+',2.50,'Average',1,7,'2026-11-04 00:09:55','2026-11-04 00:09:55'),(8,45.00,49.99,'C',2.25,'Pass',1,8,'2026-11-04 00:09:55','2026-11-04 00:09:55'),(9,40.00,44.99,'D',2.00,'Conditional Pass',1,9,'2026-11-04 00:09:55','2026-11-04 00:09:55'),(10,0.00,39.99,'F',0.00,'Fail',1,10,'2026-11-04 00:09:55','2026-11-04 00:09:55');
/*!40000 ALTER TABLE `grading_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `news`
--

DROP TABLE IF EXISTS `news`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `news` (
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `news`
--

LOCK TABLES `news` WRITE;
/*!40000 ALTER TABLE `news` DISABLE KEYS */;
INSERT INTO `news` VALUES (1,'admission-fair-opening-2026','Grand Opening of Admission Fair Fall 2026 at RMU','','2026-09-30','The Fall 2026 admission fair kicked off with wide participation from prospective students, parents, and academic counselors at the Kushtia campus.','<p>The Fall 2026 admission fair was formally inaugurated by university leadership. The Vice-Chancellor emphasized the university commitment to quality education, state-of-the-art laboratory facilities, and affordable tuition with merit-based waivers. Numerous students enrolled on the first day across CSE, EEE, BBA, English, and Agriculture.</p>',NULL,1,1,'2026-10-17 12:39:47'),(2,'research-symposium-held','International Seminar on Sustainable Agricultural Innovations','','2026-09-12','Faculty of Agriculture hosted researchers and agricultural specialists to discuss modern climate-resilient farming techniques.','<p>A seminar on climate adaptation in rural farming was successfully hosted by the Department of Agriculture at Rabindra Maitree University. Keynote speakers presented findings on soil conservation, smart irrigation, and high-yield bio-fortified crop varieties.</p>',NULL,0,1,'2026-10-17 12:39:47'),(3,'ugc-academic-accreditation-visit','UGC High-Powered Delegation Commends RMU Campus Infrastructure','','2026-08-14','Delegates from the University Grants Commission visited laboratory facilities, library resources, and digital classrooms at RMU Kushtia.','<p>During an official inspection, UGC representatives praised the modern campus infrastructure, expansive seminar library, and well-equipped engineering labs of Rabindra Maitree University, reaffirming UGC regulatory compliance and academic standards.</p>',NULL,0,1,'2026-10-17 12:39:47');
/*!40000 ALTER TABLE `news` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notices`
--

DROP TABLE IF EXISTS `notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notices` (
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notices`
--

LOCK TABLES `notices` WRITE;
/*!40000 ALTER TABLE `notices` DISABLE KEYS */;
INSERT INTO `notices` VALUES (1,'admission-fair-fall-2026','Grand Opening of Admission Fair Fall 2026 at RMU','','Admission','<p>The Grand Opening of the Fall 2026 Semester Admission Fair was held with great enthusiasm at Rabindra Maitree University. During the admission fair, special waivers on admission fees and tuition fees are being offered across all undergraduate and postgraduate programs. All prospective students are cordially invited to visit the Kushtia campus or apply directly online.</p><p>Admission Hotline: +8801302991188 | Email: admission@rmu.ac.bd</p>','downloads/admission_fair_circular.pdf','2026-09-30',1,1,463,'2026-10-17 12:39:47'),(2,'mansur-ali-scholarship','Notice: Mansur Ali and Atharunnesa Trust Scholarship','','Scholarship','<p>Applications are invited from meritorious and economically challenged regular students of Rabindra Maitree University for the Mansur Ali and Atharunnesa Trust Scholarship Fund. Interested students must submit the completed application form along with academic transcripts and family income certificates to the Registrar Office by November 15, 2026.</p>','downloads/scholarship_form.pdf','2026-09-15',1,1,218,'2026-10-17 12:39:47'),(3,'fazli-hossain-scholarship','Mohammad Fazli Hossain Memorial Trust Scholarship Circular','','Scholarship','<p>The Mohammad Fazli Hossain Memorial Trust Fund Scholarship will be awarded to students who achieve the highest CGPA in the faculties of Science, Engineering, and Arts. Eligible students must submit their applications through their respective department heads.</p>',NULL,'2026-08-20',0,1,185,'2026-10-17 12:39:47'),(4,'hamim-khan-award','Notice Regarding Hamim Khan Memorial Cultural Award','','Academic','<p>The Hamim Khan Memorial Award will be conferred upon students in recognition of outstanding contributions to literature, visual arts, and cultural activities at the university. For detailed submission guidelines, please contact the Registrar Office.</p>',NULL,'2026-07-12',0,1,142,'2026-10-17 12:39:47'),(5,'mujibnagar-day-celebration','Celebration of Historic Mujibnagar Day at RMU Campus','','General','<p>The Historic Mujibnagar Day will be observed with due solemnity at Rabindra Maitree University Kushtia Campus. All faculty members, students, officers, and staff are requested to attend the commemorative seminar at the Central Auditorium.</p>',NULL,'2026-04-16',0,1,195,'2026-10-17 12:39:47');
/*!40000 ALTER TABLE `notices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `programs`
--

DROP TABLE IF EXISTS `programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `programs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `department_id` int(10) unsigned NOT NULL,
  `slug` varchar(100) NOT NULL,
  `title_en` varchar(150) NOT NULL,
  `title_bn` varchar(150) NOT NULL,
  `degree_level` enum('Undergraduate','Postgraduate','Diploma') NOT NULL DEFAULT 'Undergraduate',
  `duration` varchar(50) NOT NULL,
  `total_credits` int(10) unsigned NOT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programs`
--

LOCK TABLES `programs` WRITE;
/*!40000 ALTER TABLE `programs` DISABLE KEYS */;
INSERT INTO `programs` VALUES (1,6,'bsc-in-cse','Computer Science and Engineering (CSE)','','Undergraduate','4 Years (8 Semesters)',148,'Minimum GPA 2.50 in both SSC & HSC with Science background and Mathematics.','Comprehensive undergraduate engineering degree covering algorithms, systems, software engineering, databases, and AI.','BDT 3,80,000 (Waiver up to 50% available)',7,1,'2026-10-17 12:39:47'),(2,7,'bsc-in-eee','Electrical and Electronic Engineering (EEE)','','Undergraduate','4 Years (8 Semesters)',150,'Minimum GPA 2.50 in SSC & HSC with Physics and Mathematics.','In-depth engineering curriculum in circuits, power transmission, communication systems, and embedded electronics.','BDT 3,75,000',9,1,'2026-10-17 12:39:47'),(3,8,'bsc-in-ict','Information and Communication Technology (ICT)','','Undergraduate','4 Years (8 Semesters)',144,'Minimum GPA 2.50 in SSC & HSC from Science or relevant vocational background.','Focused on data communication, network engineering, cloud systems, and applied IT solutions.','BDT 3,50,000',8,1,'2026-10-17 12:39:47'),(4,9,'bsc-in-microbiology','Microbiology — BSc (Honours)','','Undergraduate','4 Years (8 Semesters)',140,'Minimum GPA 2.50 in SSC & HSC with Biology and Chemistry.','Hands-on laboratory training in molecular biology, clinical diagnostics, food safety, and biochemistry.','BDT 3,20,000',10,1,'2026-10-17 12:39:47'),(5,11,'bsc-in-agriculture','Agriculture — BSc (Honours)','','Undergraduate','4 Years (8 Semesters)',160,'Minimum GPA 2.50 in SSC & HSC with Science background (Biology compulsory).','The Bachelor of Science (Honours) in Agriculture provides rigorous academic and field-based training in crop production, agronomy, soil science, plant pathology, agricultural biotechnology, and sustainable farm management. Students gain hands-on experience through modern research farm laboratories and agro-industrial field visits.','BDT 3,40,000 (Waiver up to 50% available)',6,1,'2026-10-17 12:39:47'),(6,1,'ba-in-english','English — BA (Honours)','','Undergraduate','4 Years (8 Semesters)',128,'Minimum GPA 2.50 in SSC & HSC from any background with good score in English.','Exploration of British, American, and World literature, critical theory, and professional English writing.','BDT 2,40,000',2,1,'2026-10-17 12:39:47'),(7,2,'ba-in-bangla','Bangla — BA (Honours)','','Undergraduate','4 Years (8 Semesters)',128,'Minimum GPA 2.50 in SSC & HSC from any academic stream.','Rich study of Bengali literary history, grammar, linguistics, and Rabindranath Tagore heritage.','BDT 1,80,000',1,1,'2026-10-17 12:39:47'),(8,3,'ba-in-fine-arts','Fine Arts — BFA (Honours)','','Undergraduate','4 Years (8 Semesters)',132,'Minimum GPA 2.50 in SSC & HSC with artistic portfolio or aptitude test.','Studio instruction in drawing, painting, sculpture, visual graphics, and Bangladesh art history.','BDT 2,20,000',4,1,'2026-10-17 12:39:47'),(9,4,'ba-in-music','Music — BA (Honours)','','Undergraduate','4 Years (8 Semesters)',124,'Minimum GPA 2.50 in SSC & HSC with vocal or instrumental aptitude.','Rigorous vocal training, ragas, Tagore songs, Nazrul songs, and contemporary composition.','BDT 2,00,000',3,1,'2026-10-17 12:39:47'),(10,10,'bba','Business Administration — BBA (Honours)','','Undergraduate','4 Years (8 Semesters)',130,'Minimum GPA 2.50 in SSC & HSC from Science, Commerce, or Humanities.','Core foundations in financial management, marketing strategies, organizational behavior, and entrepreneurship.','BDT 3,10,000',5,1,'2026-10-17 12:39:47'),(11,1,'ma-in-english','MA in English','','Postgraduate','1 Year (2 Semesters)',36,'Bachelor degree in English with minimum 2nd class or CGPA 2.50.','Advanced literary discourse, applied linguistics, and research thesis in English studies.','BDT 90,000',4,1,'2026-10-17 12:39:47'),(12,2,'ma-in-bangla','MA in Bangla','','Postgraduate','1 Year (2 Semesters)',36,'BA in Bangla with minimum 2nd class or CGPA 2.50.','Specialized research in modern poetry, Rabindranath studies, and contemporary Bengali linguistics.','BDT 75,000',1,1,'2026-10-17 12:39:47'),(13,5,'mped','Master of Physical Education (MPEd)','','Postgraduate','1 Year (2 Semesters)',36,'BPED or relevant graduation degree with sports credentials.','Advanced sports pedagogy, athletic administration, exercise physiology, and sports medicine.','BDT 85,000',3,1,'2026-10-17 12:39:47'),(14,10,'mba','Master of Business Administration (MBA)','','Postgraduate','2 Years (60 Credits) / 1 Year for BBA holders',60,'Graduation in any discipline with minimum CGPA 2.50.','Executive management, strategic corporate finance, international trade, and organizational leadership.','BDT 1,60,000',2,1,'2026-10-17 12:39:47'),(15,5,'physical-education-sports-science','Physical Education and Sports Science','','Undergraduate','4 Years (8 Semesters)',128,'Minimum GPA 2.50 in SSC & HSC from any background with physical fitness or athletic participation record.','Comprehensive undergraduate curriculum blending athletic training, sports physiology, kinesiology, biomechanics, exercise management, and sports pedagogy for future coaches, athletic directors, and physical educators.','BDT 1,90,000',11,1,'2026-10-17 13:13:10');
/*!40000 ALTER TABLE `programs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `result_modification_history`
--

DROP TABLE IF EXISTS `result_modification_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `result_modification_history` (
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
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `result_modification_history`
--

LOCK TABLES `result_modification_history` WRITE;
/*!40000 ALTER TABLE `result_modification_history` DISABLE KEYS */;
INSERT INTO `result_modification_history` VALUES (14,NULL,49,1,'CSE 101','1st Semester (Spring 2023)','Subject Deleted','A+ ( marks)','DELETED','Administrative record deletion','2026-11-04 01:32:28'),(15,NULL,49,1,'CSE 102','1st Semester (Spring 2023)','Subject Deleted','A+ ( marks)','DELETED','Administrative record deletion','2026-11-04 01:32:32'),(16,NULL,49,1,'ENG 101','1st Semester (Spring 2023)','Subject Deleted','A ( marks)','DELETED','Administrative record deletion','2026-11-04 01:32:37');
/*!40000 ALTER TABLE `result_modification_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `result_requests`
--

DROP TABLE IF EXISTS `result_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `result_requests` (
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `result_requests`
--

LOCK TABLES `result_requests` WRITE;
/*!40000 ALTER TABLE `result_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `result_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `semester_terms`
--

DROP TABLE IF EXISTS `semester_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `semester_terms` (
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `semester_terms`
--

LOCK TABLES `semester_terms` WRITE;
/*!40000 ALTER TABLE `semester_terms` DISABLE KEYS */;
INSERT INTO `semester_terms` VALUES (1,'1st Semester (Spring 2023)','2023',1,NULL,NULL,0,0,'2026-11-04 00:09:55'),(2,'2nd Semester (Fall 2023)','2023',2,NULL,NULL,0,0,'2026-11-04 00:09:55'),(3,'3rd Semester (Spring 2024)','2024',3,NULL,NULL,0,0,'2026-11-04 00:09:55'),(4,'4th Semester (Fall 2024)','2024',4,NULL,NULL,0,0,'2026-11-04 00:09:55'),(5,'5th Semester (Spring 2025)','2025',5,NULL,NULL,0,0,'2026-11-04 00:09:55'),(6,'6th Semester (Fall 2025)','2025',6,NULL,NULL,0,0,'2026-11-04 00:09:55'),(7,'7th Semester (Spring 2026)','2026',7,NULL,NULL,0,0,'2026-11-04 00:09:55'),(8,'8th Semester (Fall 2026)','2026',8,NULL,NULL,1,1,'2026-11-04 00:09:55');
/*!40000 ALTER TABLE `semester_terms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(60) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(30) NOT NULL DEFAULT 'general',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_settings`
--

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
INSERT INTO `site_settings` VALUES (1,'site_name_en','Rabindra Maitree University','general','University English Name','2026-10-17 12:39:47'),(2,'site_name_bn','Rabindra Maitree University','general','University Bangla Name','2026-10-17 13:24:10'),(3,'site_tagline_en','A center of excellence in higher education and cultural enlightenment','general','University Tagline','2026-10-17 12:39:47'),(4,'site_tagline_bn','Center of Higher Education and Cultural Awakening','general','Bangla Tagline','2026-10-17 13:24:10'),(5,'established_year','2015','general','Year Founded','2026-10-17 12:39:47'),(6,'campus_address','97/71, Ram Chandra Roy, Jahid Chowdhury Rd, Kushtia 7000, Bangladesh','contact','Campus Address','2026-10-17 12:39:47'),(7,'contact_phone','+8801302991188','contact','Primary Hotline','2026-10-17 12:39:47'),(8,'contact_email','info@rmu.ac.bd','contact','Official Email Address','2026-10-17 12:39:47'),(9,'admission_phone','+8801302991188','contact','Admission Help Desk Phone','2026-10-17 12:39:47'),(10,'facebook_url','https://www.facebook.com/rmukushtia','social','Official Facebook Page','2026-10-17 12:39:47'),(11,'linkedin_url','https://www.linkedin.com/school/rmukushtia/','social','Official LinkedIn Page','2026-10-17 12:39:47'),(12,'whatsapp_url','https://wa.me/8801302991188','social','Official WhatsApp Link','2026-10-17 12:39:47'),(13,'admission_active_term','Fall 2026','admission','Current Active Admission Term','2026-10-17 12:39:47'),(14,'admission_deadline','2026-11-30','admission','Current Admission Deadline','2026-10-17 12:39:47'),(15,'office_hours','Saturday – Thursday: 9:00 AM – 5:00 PM','general','Operating Hours','2026-10-17 12:39:47');
/*!40000 ALTER TABLE `site_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_courses`
--

DROP TABLE IF EXISTS `student_courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_courses` (
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
) ENGINE=InnoDB AUTO_INCREMENT=350 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_courses`
--

LOCK TABLES `student_courses` WRITE;
/*!40000 ALTER TABLE `student_courses` DISABLE KEYS */;
INSERT INTO `student_courses` VALUES (179,49,'CSE 101','Introduction to Computer Science & Problem Solving',3.0,'Prof. Dr. M. A. Rahman','Sun & Tue 10:00 AM','Room 301',90,'1st Semester','Completed','2026-11-03 21:47:34'),(180,49,'CSE 102','Structured Programming Language (C)',3.0,'Engr. Tareq Ahmed','Mon & Wed 11:30 AM','Lab 2',90,'1st Semester','Completed','2026-11-03 21:47:34'),(181,49,'MAT 101','Differential and Integral Calculus',3.0,'Dr. Shamsul Huda','Sun & Wed 02:00 PM','Room 205',90,'1st Semester','Completed','2026-11-03 21:47:34'),(182,49,'ENG 101','English Reading and Composition Skills',3.0,'Ms. Sabrina Akter','Mon & Thu 03:30 PM','Room 304',90,'1st Semester','Completed','2026-11-03 21:47:34'),(283,4,'CSE 201','Data Structures & Algorithms',3.0,'Dr. Md. Nazmul Islam (Professor & Head)','Sun & Tue: 10:00 AM – 11:30 AM','Lab 203 (Engineering Bldg)',94,'Fall 2026','Enrolled','2026-11-04 01:51:12'),(284,4,'CSE 202','Data Structures & Algorithms Lab',1.5,'Engr. Rakibul Hasan (Lecturer)','Sun: 02:00 PM – 05:00 PM','Computer Lab 1',96,'Fall 2026','Enrolled','2026-11-04 01:51:12'),(285,4,'CSE 203','Object-Oriented Programming (Java)',3.0,'Ms. Tasnim Tabassum (Assistant Professor)','Mon & Wed: 11:30 AM – 01:00 PM','Room 304',90,'Fall 2026','Enrolled','2026-11-04 01:51:12'),(286,4,'CSE 204','Digital Logic Design & Microprocessors',3.0,'Dr. Farhana Yasmin (Associate Professor)','Mon & Wed: 09:30 AM – 11:00 AM','Hardware Lab 102',88,'Fall 2026','Enrolled','2026-11-04 01:51:12'),(287,4,'MAT 201','Discrete Mathematics & Graph Theory',3.0,'Mr. Tariqul Anam (Senior Lecturer)','Thu: 10:00 AM – 01:00 PM','Room 205',92,'Fall 2026','Enrolled','2026-11-04 01:51:12');
/*!40000 ALTER TABLE `student_courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_fees`
--

DROP TABLE IF EXISTS `student_fees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_fees` (
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
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_fees`
--

LOCK TABLES `student_fees` WRITE;
/*!40000 ALTER TABLE `student_fees` DISABLE KEYS */;
INSERT INTO `student_fees` VALUES (30,4,'Fall 2026 Semester Tuition & Lab Fee',34500.00,'2026-11-15','Paid','RMU-PAY-2026-98124','2026-10-05 11:20:00','2026-11-04 01:51:13'),(31,4,'Spring 2026 Semester Tuition & Exam Fee',32000.00,'2026-04-10','Paid','RMU-PAY-2026-44102','2026-03-28 14:15:00','2026-11-04 01:51:13'),(32,4,'Fall 2025 Semester Tuition Fee',32000.00,'2025-10-15','Paid','RMU-PAY-2025-87211','2025-10-02 09:40:00','2026-11-04 01:51:13'),(33,4,'Spring 2025 Admission & 1st Semester Fee',45000.00,'2025-01-20','Paid','RMU-PAY-2025-10245','2025-01-15 16:30:00','2026-11-04 01:51:13'),(35,4,'Mid-Term Examination & Lab Fee',12500.00,'2026-11-04','Paid','RMU-TXN-497215','2026-11-04 02:12:13','2026-11-04 01:53:00'),(36,4,'Library & Activity Fee',3500.00,'2026-11-04','Failed','RMU-FAIL-999',NULL,'2026-11-04 01:53:00'),(39,4,'Mid-Term Examination & Lab Fee',12500.00,'2026-11-04','Pending','RMU-TXN-PENDING1',NULL,'2026-11-04 02:36:04');
/*!40000 ALTER TABLE `student_fees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_notifications`
--

DROP TABLE IF EXISTS `student_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_notifications` (
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
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_notifications`
--

LOCK TABLES `student_notifications` WRITE;
/*!40000 ALTER TABLE `student_notifications` DISABLE KEYS */;
INSERT INTO `student_notifications` VALUES (48,127,'Welcome to Rabindra Maitree University!','Your official Student Portal account has been created. Student ID: RMU2609160.','general',NULL,0,'2026-11-04 01:56:55'),(49,128,'Welcome to Rabindra Maitree University!','Your official Student Portal account has been created. Student ID: RMU2609311.','general',NULL,0,'2026-11-04 01:57:32'),(58,137,'Academic Results Projected (Estimated)','Your semester-wise results have been estimated based on cumulative CGPA 3.85. IMPORTANT: ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT.','',NULL,0,'2026-11-04 02:34:36'),(59,138,'Academic Results Projected (Estimated)','Your semester-wise results have been estimated based on cumulative CGPA 3.72. IMPORTANT: ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT.','',NULL,0,'2026-11-04 02:34:36'),(60,137,'Academic Results Projected (Estimated)','Your semester-wise results have been estimated based on cumulative CGPA 3.55. IMPORTANT: ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT.','',NULL,0,'2026-11-04 02:34:36'),(61,137,'Official Results Verified','Your academic results for 1st Semester have been officially verified and confirmed by the Office of the Controller of Examinations.','',NULL,0,'2026-11-04 02:34:36');
/*!40000 ALTER TABLE `student_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_portal_notices`
--

DROP TABLE IF EXISTS `student_portal_notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_portal_notices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `category` enum('Academic','Exam','Payment','General') NOT NULL DEFAULT 'Academic',
  `content` text NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_portal_notices`
--

LOCK TABLES `student_portal_notices` WRITE;
/*!40000 ALTER TABLE `student_portal_notices` DISABLE KEYS */;
INSERT INTO `student_portal_notices` VALUES (13,'Mid-Term Examination Schedule: Fall 2026','Exam','All undergraduate students are hereby notified that the Mid-Term Examination of Fall 2026 semester will commence from November 22, 2026. Detailed routine is published on the portal.',1,'2026-11-04 01:51:13'),(14,'Admit Card Distribution for Upcoming Examinations','Academic','Students with 75% or higher class attendance and cleared semester dues can download their digital Admit Cards directly from the Student Portal starting November 10, 2026.',1,'2026-11-04 01:51:13'),(15,'Library Extended Hours for Final Exam Preparations','General','The Central Library and digital reading archives will remain open until 08:00 PM every evening to facilitate exam preparation.',0,'2026-11-04 01:51:13'),(16,'Fee Waiver & Merit Scholarship Application for Spring 2027','Payment','Students with CGPA 3.80 or above are invited to apply for the Rabindra Merit Waiver for the upcoming Spring 2027 academic session.',0,'2026-11-04 01:51:13');
/*!40000 ALTER TABLE `student_portal_notices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_results`
--

DROP TABLE IF EXISTS `student_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_results` (
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
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `is_estimated` tinyint(1) NOT NULL DEFAULT 0,
  `result_type` enum('Verified','Estimated') NOT NULL DEFAULT 'Verified',
  `publish_status` enum('Draft','Under Review','Published') NOT NULL DEFAULT 'Published',
  `published_at` datetime DEFAULT NULL,
  `published_by` int(10) unsigned DEFAULT NULL,
  `correction_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `student_results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=816 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_results`
--

LOCK TABLES `student_results` WRITE;
/*!40000 ALTER TABLE `student_results` DISABLE KEYS */;
INSERT INTO `student_results` VALUES (56,49,'1st Semester (Spring 2023)','MAT 101','Differential and Integral Calculus',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(57,49,'2nd Semester (Fall 2023)','CSE 103','Discrete Mathematics',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(58,49,'2nd Semester (Fall 2023)','CSE 104','Object Oriented Programming (Java)',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(59,49,'2nd Semester (Fall 2023)','PHY 101','Physics (Mechanics, Waves & Optics)',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(60,49,'2nd Semester (Fall 2023)','BAN 105','Bangla Language & Tagore Literary Studies',2.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(61,49,'3rd Semester (Spring 2024)','CSE 201','Data Structures & Algorithms',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(62,49,'3rd Semester (Spring 2024)','CSE 202','Data Structures & Algorithms Lab',1.5,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(63,49,'3rd Semester (Spring 2024)','CSE 203','Digital Logic Design & Microprocessors',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(64,49,'3rd Semester (Spring 2024)','MAT 102','Coordinate Geometry & Linear Algebra',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(65,49,'4th Semester (Fall 2024)','CSE 205','Database Management Systems',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(66,49,'4th Semester (Fall 2024)','CSE 206','Database Management Systems Lab',1.5,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(67,49,'4th Semester (Fall 2024)','CSE 207','Computer Architecture & Organization',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(68,49,'4th Semester (Fall 2024)','STA 201','Statistics & Probability for Engineers',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(69,49,'5th Semester (Spring 2025)','CSE 301','Software Engineering & System Analysis',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(70,49,'5th Semester (Spring 2025)','CSE 302','Operating Systems & System Programming',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(71,49,'5th Semester (Spring 2025)','CSE 303','Operating Systems Lab (Linux/Unix)',1.5,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(72,49,'5th Semester (Spring 2025)','CSE 304','Theory of Computation & Automata',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(73,49,'6th Semester (Fall 2025)','CSE 305','Computer Networks & Internet Protocols',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(74,49,'6th Semester (Fall 2025)','CSE 306','Computer Networks Laboratory',1.5,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(75,49,'6th Semester (Fall 2025)','CSE 307','Web Engineering & Full-Stack Development',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(76,49,'6th Semester (Fall 2025)','CSE 308','Microcontroller & Embedded Systems Lab',1.5,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(77,49,'7th Semester (Spring 2026)','CSE 401','Artificial Intelligence & Machine Learning',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(78,49,'7th Semester (Spring 2026)','CSE 402','Compiler Design & Construction',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(79,49,'7th Semester (Spring 2026)','CSE 403','Cyber Security & Cryptography',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(80,49,'7th Semester (Spring 2026)','CSE 499A','Capstone Project & Thesis (Part I)',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(81,49,'8th Semester (Fall 2026)','CSE 405','Cloud Computing & Distributed Systems',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(82,49,'8th Semester (Fall 2026)','CSE 406','Digital Image Processing & Vision',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(83,49,'8th Semester (Fall 2026)','CSE 499B','Capstone Project & Defense (Part II)',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(84,49,'8th Semester (Fall 2026)','MGT 401','Industrial Management & Professional Ethics',2.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-03 22:51:53'),(188,4,'1st Semester (Spring 2023)','CSE 101','Structured Programming in C',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(189,4,'1st Semester (Spring 2023)','CSE 102','Structured Programming Lab',1.5,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(190,4,'1st Semester (Spring 2023)','ENG 101','English Reading & Writing Skills',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(191,4,'1st Semester (Spring 2023)','MAT 101','Differential and Integral Calculus',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(192,4,'2nd Semester (Fall 2023)','CSE 103','Discrete Mathematics',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(193,4,'2nd Semester (Fall 2023)','CSE 104','Object Oriented Programming (Java)',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(194,4,'2nd Semester (Fall 2023)','PHY 101','Physics (Mechanics, Waves & Optics)',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(195,4,'2nd Semester (Fall 2023)','BAN 105','Bangla Language & Tagore Literary Studies',2.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(196,4,'3rd Semester (Spring 2024)','CSE 201','Data Structures & Algorithms',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(197,4,'3rd Semester (Spring 2024)','CSE 202','Data Structures & Algorithms Lab',1.5,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(198,4,'3rd Semester (Spring 2024)','CSE 203','Digital Logic Design & Microprocessors',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(199,4,'3rd Semester (Spring 2024)','MAT 102','Coordinate Geometry & Linear Algebra',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(200,4,'4th Semester (Fall 2024)','CSE 205','Database Management Systems',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(201,4,'4th Semester (Fall 2024)','CSE 206','Database Management Systems Lab',1.5,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(202,4,'4th Semester (Fall 2024)','CSE 207','Computer Architecture & Organization',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(203,4,'4th Semester (Fall 2024)','STA 201','Statistics & Probability for Engineers',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(204,4,'5th Semester (Spring 2025)','CSE 301','Software Engineering & System Analysis',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(205,4,'5th Semester (Spring 2025)','CSE 302','Operating Systems & System Programming',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(206,4,'5th Semester (Spring 2025)','CSE 303','Operating Systems Lab (Linux/Unix)',1.5,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(207,4,'5th Semester (Spring 2025)','CSE 304','Theory of Computation & Automata',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(208,4,'6th Semester (Fall 2025)','CSE 305','Computer Networks & Internet Protocols',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(209,4,'6th Semester (Fall 2025)','CSE 306','Computer Networks Laboratory',1.5,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(210,4,'6th Semester (Fall 2025)','CSE 307','Web Engineering & Full-Stack Development',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(211,4,'6th Semester (Fall 2025)','CSE 308','Microcontroller & Embedded Systems Lab',1.5,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(212,4,'7th Semester (Spring 2026)','CSE 401','Artificial Intelligence & Machine Learning',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(213,4,'7th Semester (Spring 2026)','CSE 402','Compiler Design & Construction',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(214,4,'7th Semester (Spring 2026)','CSE 403','Cyber Security & Cryptography',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(215,4,'7th Semester (Spring 2026)','CSE 499A','Capstone Project & Thesis (Part I)',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(216,4,'8th Semester (Fall 2026)','CSE 405','Cloud Computing & Distributed Systems',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(217,4,'8th Semester (Fall 2026)','CSE 406','Digital Image Processing & Vision',3.0,100,NULL,'A',3.75,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(218,4,'8th Semester (Fall 2026)','CSE 499B','Capstone Project & Defense (Part II)',3.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(219,4,'8th Semester (Fall 2026)','MGT 401','Industrial Management & Professional Ethics',2.0,100,NULL,'A+',4.00,'Semester Final Examination',1,0,'Verified','Published',NULL,NULL,NULL,'2026-11-04 01:51:17'),(224,128,'1st Semester','CSE 111','Computer Fundamentals',3.0,100,85.00,'A+',4.00,'Semester Final Examination',0,0,'Verified','Draft',NULL,NULL,'Initial E2E Test Student Setup','2026-11-04 01:57:32'),(225,128,'1st Semester','MAT 111','Mathematics I (Differential Calculus)',3.0,100,75.00,'A',3.75,'Semester Final Examination',0,0,'Verified','Draft',NULL,NULL,'Initial E2E Test Student Setup','2026-11-04 01:57:32'),(268,138,'1st Semester','MBA 501','Advanced Managerial Economics & Quantitative Methods',3.0,100,74.40,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.72','2026-11-04 02:34:36'),(269,138,'1st Semester','MBA 502','Strategic Financial Accounting & Reporting',3.0,100,65.90,'B+',3.25,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.72','2026-11-04 02:34:36'),(270,138,'1st Semester','MBA 503','Organizational Leadership & Strategic HRM',3.0,100,77.50,'A',3.75,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.72','2026-11-04 02:34:36'),(271,138,'1st Semester','MBA 504','Corporate Governance, Law & Ethical Leadership',3.0,100,90.50,'A+',4.00,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.72','2026-11-04 02:34:36'),(272,138,'2nd Semester','MBA 505','Advanced Corporate Finance & Valuation Analysis',3.0,100,78.20,'A',3.75,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.72','2026-11-04 02:34:36'),(273,138,'2nd Semester','MBA 506','Strategic Marketing & Consumer Analytics',3.0,100,73.80,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.72','2026-11-04 02:34:36'),(274,138,'2nd Semester','MBA 507','Global Supply Chain & Operations Excellence',3.0,100,88.90,'A+',4.00,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.72','2026-11-04 02:34:36'),(275,138,'2nd Semester','MBA 508','Enterprise Analytics, Big Data & Digital Transformation',3.0,100,78.60,'A',3.75,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.72','2026-11-04 02:34:36'),(276,138,'3rd Semester','MBA 601','Global Competitive Strategy & M&A Policy',3.0,100,75.40,'A',3.75,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.72','2026-11-04 02:34:36'),(277,138,'3rd Semester','MBA 602','Venture Capital, Innovation & Private Equity',3.0,100,72.40,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.72','2026-11-04 02:34:36'),(278,138,'3rd Semester','MBA 603','Applied Business Research Methods & Econometrics',3.0,100,78.30,'A',3.75,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.72','2026-11-04 02:34:36'),(279,138,'3rd Semester','MBA 604','International Financial Markets & Risk Hedging',3.0,100,84.90,'A+',4.00,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.72','2026-11-04 02:34:36'),(280,138,'4th Semester','MBA 605','Corporate Strategy Simulation & Case Analysis',3.0,100,75.90,'A',3.75,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.72','2026-11-04 02:34:36'),(281,138,'4th Semester','MBA 606','Executive Leadership & Sustainable Enterprise',3.0,100,71.20,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.72','2026-11-04 02:34:36'),(282,138,'4th Semester','MBA 607','Master\'s Graduate Thesis / Capstone Applied Project',4.0,100,86.80,'A+',4.00,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.72','2026-11-04 02:34:36'),(283,138,'4th Semester','MBA 608','Comprehensive Oral Examination & Viva Voce',1.0,100,76.90,'A',3.75,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.72','2026-11-04 02:34:36'),(284,137,'1st Semester','CSE 101','Structured Programming Language (C)',3.0,100,76.20,'A',3.75,'Semester Final Exam',1,0,'Verified','Published','2026-11-04 02:34:36',1,'Verified by administrator as official examination record','2026-11-04 02:34:36'),(285,137,'1st Semester','CSE 102','Structured Programming Laboratory',1.5,100,74.30,'A-',3.50,'Semester Final Exam',1,0,'Verified','Published','2026-11-04 02:34:36',1,'Verified by administrator as official examination record','2026-11-04 02:34:36'),(286,137,'1st Semester','MAT 101','Differential and Integral Calculus',3.0,100,76.10,'A',3.75,'Semester Final Exam',1,0,'Verified','Published','2026-11-04 02:34:36',1,'Verified by administrator as official examination record','2026-11-04 02:34:36'),(287,137,'1st Semester','ENG 101','English Reading and Composition Skills',3.0,100,88.40,'A+',4.00,'Semester Final Exam',1,0,'Verified','Published','2026-11-04 02:34:36',1,'Verified by administrator as official examination record','2026-11-04 02:34:36'),(288,137,'2nd Semester','CSE 103','Discrete Mathematics for Computing',3.0,100,74.20,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(289,137,'2nd Semester','CSE 104','Object Oriented Programming (C++)',3.0,100,68.60,'B+',3.25,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(290,137,'2nd Semester','CSE 105','Object Oriented Programming Lab',1.5,100,79.10,'A',3.75,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(291,137,'2nd Semester','PHY 101','Physics for Engineers (Mechanics & Waves)',3.0,100,72.80,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(292,137,'3rd Semester','CSE 201','Data Structures & Algorithms',3.0,100,73.20,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(293,137,'3rd Semester','CSE 202','Data Structures Laboratory',1.5,100,69.30,'B+',3.25,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(294,137,'3rd Semester','CSE 203','Digital Logic Design & Circuits',3.0,100,74.00,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(295,137,'3rd Semester','EEE 101','Basic Electrical Engineering & Circuit Analysis',3.0,100,79.50,'A',3.75,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(296,137,'4th Semester','CSE 205','Design & Analysis of Algorithms',3.0,100,74.40,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(297,137,'4th Semester','CSE 206','Database Management Systems (DBMS)',3.0,100,68.30,'B+',3.25,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(298,137,'4th Semester','CSE 207','Database Management Systems Lab',1.5,100,77.70,'A',3.75,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(299,137,'4th Semester','MAT 201','Linear Algebra & Complex Variables',3.0,100,72.20,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(300,137,'5th Semester','CSE 301','Operating Systems & System Programming',3.0,100,74.50,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(301,137,'5th Semester','CSE 302','Operating Systems Laboratory',1.5,100,68.60,'B+',3.25,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(302,137,'5th Semester','CSE 303','Microprocessors, Microcontrollers & Interfacing',3.0,100,70.60,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(303,137,'5th Semester','CSE 304','Software Engineering & System Architecture',3.0,100,79.20,'A',3.75,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(304,137,'6th Semester','CSE 305','Computer Networks & TCP/IP Protocols',3.0,100,71.60,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(305,137,'6th Semester','CSE 306','Computer Networks Laboratory',1.5,100,69.10,'B+',3.25,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(306,137,'6th Semester','CSE 307','Theory of Computation & Automata',3.0,100,77.50,'A',3.75,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(307,137,'6th Semester','CSE 308','Web Technologies & Distributed Cloud Systems',3.0,100,72.40,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(308,137,'7th Semester','CSE 401','Artificial Intelligence & Machine Learning',3.0,100,74.50,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(309,137,'7th Semester','CSE 402','Artificial Intelligence Lab',1.5,100,69.50,'B+',3.25,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(310,137,'7th Semester','CSE 403','Compiler Design & Syntax Analysis',3.0,100,71.50,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(311,137,'7th Semester','CSE 404','Cyber Security, Cryptography & Network Defense',3.0,100,76.30,'A',3.75,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(312,137,'8th Semester','CSE 405','Cloud Computing & High Performance Architectures',3.0,100,72.90,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(313,137,'8th Semester','CSE 406','Data Science & Big Data Analytics',3.0,100,68.90,'B+',3.25,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(314,137,'8th Semester','CSE 407','Senior Capstone Project & Engineering Thesis',4.0,100,78.50,'A',3.75,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36'),(315,137,'8th Semester','CSE 408','Comprehensive Academic Defense & Viva Voce',1.0,100,74.40,'A-',3.50,'Semester Final Exam',1,1,'Estimated','Published','2026-11-04 02:34:36',1,'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.55','2026-11-04 02:34:36');
/*!40000 ALTER TABLE `student_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_retention_archives`
--

DROP TABLE IF EXISTS `student_retention_archives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_retention_archives` (
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_retention_archives`
--

LOCK TABLES `student_retention_archives` WRITE;
/*!40000 ALTER TABLE `student_retention_archives` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_retention_archives` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
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
  `program_id` int(10) unsigned DEFAULT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `batch` varchar(30) NOT NULL,
  `current_year` varchar(30) NOT NULL,
  `current_semester` varchar(50) NOT NULL,
  `cgpa` decimal(3,2) NOT NULL DEFAULT 0.00,
  `results_visible` tinyint(1) NOT NULL DEFAULT 1,
  `results_hidden_reason` varchar(255) DEFAULT NULL,
  `credits_completed` int(10) unsigned NOT NULL DEFAULT 0,
  `total_credits_required` int(10) unsigned NOT NULL DEFAULT 140,
  `blood_group` varchar(10) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') NOT NULL DEFAULT 'Male',
  `home_district` varchar(60) DEFAULT NULL,
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
  KEY `student_id_2` (`student_id`),
  KEY `email_2` (`email`),
  KEY `idx_students_roll` (`roll_number`),
  KEY `idx_students_appno` (`application_number`),
  KEY `idx_students_username` (`username`),
  KEY `idx_students_status` (`status`),
  KEY `idx_students_results_visible` (`results_visible`)
) ENGINE=InnoDB AUTO_INCREMENT=185 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (4,'RMU2601001','ROLL-0004',NULL,'RMU2601001','Tanvir Hasan',NULL,NULL,'tanvir.cse@rmu.ac.bd','01712345678','$2y$12$XOMK7UE3cZDaMvrs.YpTTebTutB/nunaTEE.ELJwS0z6kcOJ8eAQ6',1,6,'12th Batch (2024–2028)','4th Year','8th Semester (Final)',3.89,1,NULL,85,144,'B+','2004-05-15','Male','Kushtia',NULL,NULL,'Graduated',1,'tanvir.svg','2026-11-04 01:51:12','2026-11-09 23:30:15',NULL,NULL,NULL,NULL,NULL,NULL),(49,'RMU2601011','ADM-01011','RMU-2026-6238','RMU2601011','Raju Khan',NULL,NULL,'rmu2601011@student.rmu.ac.bd','01311863929','$2y$12$leum/wsPwdqNp3e.RUhU8.XmM0vLz.Q2Akbf6CTPxQxrSnCsxOnb2',7,2,'Batch 26','4th Year','8th Semester (Completed)',3.89,1,NULL,77,140,'',NULL,'Male','gvcfb cv',NULL,NULL,'Graduated',1,NULL,'2026-11-03 21:47:34','2026-11-09 23:30:15',NULL,NULL,NULL,NULL,NULL,NULL),(115,'RMU2601002','ROLL-0115',NULL,'RMU2601002','Nusrat Jahan',NULL,NULL,'nusrat.bangla@rmu.ac.bd','01812345678','$2y$12$XOMK7UE3cZDaMvrs.YpTTebTutB/nunaTEE.ELJwS0z6kcOJ8eAQ6',7,2,'13th Batch (2025–2029)','1st Year','Fall 2026 (2nd Semester)',3.78,1,NULL,24,128,'O+','2005-08-22','Male','Jhenaidah',NULL,NULL,'Active',1,'nusrat.svg','2026-11-04 01:51:12','2026-11-09 23:30:15',NULL,NULL,NULL,NULL,NULL,NULL),(116,'RMU2601003','ROLL-0116',NULL,'RMU2601003','Ahsan Habib',NULL,NULL,'ahsan.bba@rmu.ac.bd','01912345678','$2y$12$XOMK7UE3cZDaMvrs.YpTTebTutB/nunaTEE.ELJwS0z6kcOJ8eAQ6',10,4,'11th Batch (2023–2027)','3rd Year','Fall 2026 (6th Semester)',3.65,1,NULL,76,132,'A+','2003-11-10','Male','Chuadanga',NULL,NULL,'Active',1,'ahsan.svg','2026-11-04 01:51:12','2026-11-09 23:30:15',NULL,NULL,NULL,NULL,NULL,NULL),(127,'RMU2609160','ROLL-0127',NULL,'RMU2609160','E2E Test Student','Md. Test Father','Mrs. Test Mother','rmu2609160@rmu.ac.bd','01799887766','$2y$12$laKfWG0mgD8N4B8kYK29w.3hgZio3czJ9iXT1sufNRetIpBGJ4jMO',1,6,'14th Batch (2026-2030)','1st Year','1st Semester (Spring 2026)',0.00,1,NULL,0,140,'A+','2004-03-20','Male','Kushtia','RMU Campus Hall, Kushtia','House 12, Road 4, Kushtia','Active',1,NULL,'2026-11-04 01:56:55','2026-11-09 23:30:15',NULL,NULL,NULL,NULL,NULL,NULL),(128,'RMU2609311','ROLL-0128',NULL,'RMU2609311','E2E Test Student','Md. Test Father','Mrs. Test Mother','rmu2609311@rmu.ac.bd','01799887766','$2y$12$L.hC2LEPpjLkdvr71e5o5ehdTXvL/v63TWaOX3Vypqt/K6RWlUo9S',1,6,'14th Batch (2026-2030)','1st Year','1st Semester (Spring 2026)',3.88,1,NULL,6,140,'A+','2004-03-20','Male','Kushtia','RMU Campus Hall, Kushtia','House 12, Road 4, Kushtia','Active',1,NULL,'2026-11-04 01:57:32','2026-11-09 23:30:15',NULL,NULL,NULL,NULL,NULL,NULL),(137,'RMU-TEST-BACH-7215','ROLL-0137',NULL,'RMU-TEST-BACH-7215','Bachelor Test Student',NULL,NULL,'rmu-test-bach-7215@rmu.ac.bd','01711223344','$2y$12$klr8kEmukXBib1zmHn0EK.5I0gWDmopfAfcWUWkJ5blPvmTrr2wZm',1,6,'14th Batch (2026-2030)','1st Year','1st Semester',3.55,1,NULL,84,140,NULL,NULL,'Male',NULL,NULL,NULL,'Active',1,NULL,'2026-11-04 02:34:35','2026-11-09 23:30:15',NULL,NULL,NULL,NULL,NULL,NULL),(138,'RMU-TEST-MAST-3034','ROLL-0138',NULL,'RMU-TEST-MAST-3034','Master Test Student',NULL,NULL,'rmu-test-mast-3034@rmu.ac.bd','01711223355','$2y$12$xyi7O6hs3HYdPsWiebPbVuMLvgJhSG0F8tfq4bdyQp6vVlOLR1/Dq',14,10,'MBA 2026 Batch','1st Year','1st Semester',3.72,1,NULL,47,60,NULL,NULL,'Male',NULL,NULL,NULL,'Active',1,NULL,'2026-11-04 02:34:36','2026-11-09 23:30:15',NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@rmu.ac.bd','$2y$12$n74nkgtjoV.KsSZuriqGBe9rDZ790pX5SrqX57JzG022KFT7Asz9W','Chief Administrator','superadmin','active',0,'2026-11-09 23:45:51','2026-10-17 12:39:47','2026-11-09 23:45:51'),(2,'user','user@rmu.ac.bd','\\.','Master User','superadmin','active',0,'2026-11-09 23:02:11','2026-10-31 06:55:25','2026-11-09 23:02:11');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-09 23:58:33
