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

--
-- Dumping data for table `users` (2 rows)
--
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `status`, `failed_login_attempts`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@rmu.ac.bd', '$2y$12$n74nkgtjoV.KsSZuriqGBe9rDZ790pX5SrqX57JzG022KFT7Asz9W', 'Chief Administrator', 'superadmin', 'active', 0, '2026-11-12 01:46:04', '2026-10-17 12:39:47', '2026-11-12 01:46:04'),
(2, 'user', 'user@rmu.ac.bd', '\\.', 'Master User', 'superadmin', 'active', 8, '2026-11-11 00:45:45', '2026-10-31 06:55:25', '2026-11-11 22:54:16');

--
-- Dumping data for table `site_settings` (26 rows)
--
INSERT INTO `site_settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `description`, `updated_at`) VALUES
(1, 'site_name_en', 'Rabindra Maitree University', 'general', 'University English Name', '2026-10-17 12:39:47'),
(2, 'site_name_bn', 'Rabindra Maitree University', 'general', 'University Bangla Name', '2026-10-17 13:24:10'),
(3, 'site_tagline_en', 'A center of excellence in higher education and cultural enlightenment', 'general', 'University Tagline', '2026-10-17 12:39:47'),
(4, 'site_tagline_bn', 'Center of Higher Education and Cultural Awakening', 'general', 'Bangla Tagline', '2026-10-17 13:24:10'),
(5, 'established_year', 2015, 'general', 'Year Founded', '2026-10-17 12:39:47'),
(6, 'campus_address', '97/71, Ram Chandra Roy, Jahid Chowdhury Rd, Kushtia 7000, Bangladesh', 'contact', 'Campus Address', '2026-10-17 12:39:47'),
(7, 'contact_phone', '+8801302991188', 'contact', 'Primary Hotline', '2026-10-17 12:39:47'),
(8, 'contact_email', 'info@rmu.ac.bd', 'contact', 'Official Email Address', '2026-10-17 12:39:47'),
(9, 'admission_phone', '+8801302991188', 'contact', 'Admission Help Desk Phone', '2026-10-17 12:39:47'),
(10, 'facebook_url', 'https://www.facebook.com/rmukushtia', 'social', 'Official Facebook Page', '2026-10-17 12:39:47'),
(11, 'linkedin_url', 'https://www.linkedin.com/school/rmukushtia/', 'social', 'Official LinkedIn Page', '2026-10-17 12:39:47'),
(12, 'whatsapp_url', 'https://wa.me/8801302991188', 'social', 'Official WhatsApp Link', '2026-10-17 12:39:47'),
(13, 'admission_active_term', 'Fall 2026', 'admission', 'Current Active Admission Term', '2026-10-17 12:39:47'),
(14, 'admission_deadline', '2026-11-30', 'admission', 'Current Admission Deadline', '2026-10-17 12:39:47'),
(15, 'office_hours', 'Saturday – Thursday: 9:00 AM – 5:00 PM', 'general', 'Operating Hours', '2026-10-17 12:39:47'),
(16, 'site_meta_title', 'Rabindra Maitree University (RMU) | UGC & Govt. Approved University in Kushtia, Bangladesh', 'seo', 'Primary Meta Title for Google and Search Engines', '2026-11-15 22:58:50'),
(17, 'site_meta_description', 'Rabindra Maitree University (RMU), Kushtia - A center of excellence in higher education, arts, engineering, and cultural enlightenment approved by UGC and the Govt. of Bangladesh.', 'seo', 'Meta Description tag for Google search result snippets', '2026-11-15 22:58:50'),
(18, 'site_meta_keywords', 'Rabindra Maitree University, RMU, RMU Kushtia, Private University Kushtia, UGC Approved University, CSE, BBA, English, Law, Admission 2026, Bangladesh Universities', 'seo', 'Target keywords for university website indexing', '2026-11-15 22:58:50'),
(19, 'google_site_verification', '', 'seo', 'Google Search Console verification code (content of google-site-verification meta tag)', '2026-11-15 22:58:50'),
(20, 'canonical_base_url', 'https://rmu.ac.bd', 'seo', 'Canonical production base URL for Google Indexing (update to your live domain)', '2026-11-15 22:58:50'),
(21, 'payment_bkash_no', '01336367336', 'payment', 'bKash Merchant / Personal Account Number', '2026-11-19 20:30:00'),
(22, 'payment_nagad_no', '01302991188', 'payment', 'Nagad Merchant / Personal Account Number', '2026-11-19 20:30:00'),
(23, 'payment_rocket_no', '01302991188-7', 'payment', 'DBBL Rocket Account Number', '2026-11-19 20:30:00'),
(24, 'payment_merchant_account', '01336367336', 'payment', 'Official Gateway Merchant ID / Number', '2026-11-19 20:30:00'),
(25, 'payment_bank_info', 'Dutch-Bangla Bank Ltd, Kushtia Branch. A/C: 115-120-998822', 'payment', 'Bank Direct Transfer Account Details', '2026-11-19 20:30:00'),
(26, 'payment_instructions', 'পেমেন্ট করার সময় রেফারেন্সে অবশ্যই আপনার Student ID অথবা Admission Roll নম্বর লিখবেন।', 'payment', 'Payment Instructions and Reference Guidelines', '2026-11-19 20:30:00');

--
-- Dumping data for table `audit_logs` (100 rows)
--
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(2175, 1, 'ACADEMIC_SESSION_TOGGLED', 'Enabled Academic Session \'Summer 2013\'', '127.0.0.1', '2026-11-12 01:26:20'),
(2176, 1, 'PASSING_YEAR_TOGGLED', 'Enabled Passing Year 2013', '127.0.0.1', '2026-11-12 01:26:20'),
(2180, 1, 'PROGRAM_EDITED', 'Updated program ID 10: Business Administration — BBA (Honours) (Bachelor / Honours, 5 Years / 12 Semesters, 160 Cr)', '127.0.0.1', '2026-11-12 01:26:21'),
(2182, 1, 'PROGRAM_EDITED', 'Updated program ID 10: Business Administration — BBA (Honours) (Bachelor / Honours, 4 Years / 8 Semesters, 130 Cr)', '127.0.0.1', '2026-11-12 01:26:22'),
(2188, NULL, 'STUDENT_DELETE_DENIED', 'Unauthorized role \'editor\' attempted permanent student deletion via API', '127.0.0.1', '2026-11-12 01:26:25'),
(2189, NULL, 'PROGRAM_ACTION_DENIED', 'Unauthorized role \'editor\' attempted permanent program deletion via API', '127.0.0.1', '2026-11-12 01:26:25'),
(2190, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:26:25'),
(2197, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:26:26'),
(2199, NULL, 'ADMISSION_PAYMENT_INITIATED', 'Initiated ৳150 Sandbox Gateway session RMUSBX-20261112012627-8089BD2034 via bKash for RMU-2026-0021', '127.0.0.1', '2026-11-12 01:26:27'),
(2200, NULL, 'ADMISSION_FEE_PAID', 'Applicant RMU-2026-0021 paid ৳150 fee via bKash Sandbox Gateway (Session: RMUSBX-20261112012627-8089BD2034, TrxID: SBK26111207F1FA38)', '127.0.0.1', '2026-11-12 01:26:30'),
(2206, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:26:30'),
(2212, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:26:33'),
(2225, 1, 'ALL_RESULTS_VERIFIED_OFFICIAL', 'Admin #1 verified 32 estimated result(s) as official for student #729', '127.0.0.1', '2026-11-12 01:26:36'),
(2232, 1, 'ESTIMATED_RESULTS_GENERATED', 'Generated 8-semester estimated results for student #734 (Bachelor Test Student) [Session: 14th Batch (2026-2030), PubDate: 2026-11-12, Target CGPA: 3.85, Realized CGPA: 3.85]', '127.0.0.1', '2026-11-12 01:26:40'),
(2233, 1, 'ESTIMATED_RESULTS_GENERATED', 'Generated 4-semester estimated results for student #735 (Master Test Student) [Session: MBA 2026 Batch, PubDate: 2026-11-12, Target CGPA: 3.72, Realized CGPA: 3.72]', '127.0.0.1', '2026-11-12 01:26:41'),
(2234, 1, 'ESTIMATED_RESULTS_GENERATED', 'Generated 8-semester estimated results for student #734 (Bachelor Test Student) [Session: 14th Batch (2026-2030), PubDate: 2026-11-12, Target CGPA: 3.55, Realized CGPA: 3.55]', '127.0.0.1', '2026-11-12 01:26:41'),
(2235, 1, 'RESULTS_VERIFIED_OFFICIAL', 'Admin verified 4 estimated course result(s) in 1st Semester as official for student #734', '127.0.0.1', '2026-11-12 01:26:41'),
(2236, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:26:42'),
(2237, 1, 'STUDENT_ADDED_ONE_PAGE', 'Enrolled student: E2E Test Student (RMU2609930)', '127.0.0.1', '2026-11-12 01:26:42'),
(2238, 1, 'RESULTS_DRAFT_ONE_PAGE', 'Saved results as Draft for Student #736 via One-Page Console', '127.0.0.1', '2026-11-12 01:26:42'),
(2239, 1, 'STUDENT_UPDATED_ONE_PAGE', 'Updated student ID #736 (E2E Test Student)', '127.0.0.1', '2026-11-12 01:26:42'),
(2240, 1, 'RESULTS_PUBLISHED_ONE_PAGE', 'Published results for Student #736 via One-Page Console', '127.0.0.1', '2026-11-12 01:26:43'),
(2243, NULL, 'ADMISSION_PAYMENT_INITIATED', 'Initiated ৳150 Sandbox Gateway session RMUSBX-20261112012643-7BBED1D441 via bKash for RMU-2026-0022', '127.0.0.1', '2026-11-12 01:26:43'),
(2244, NULL, 'ADMISSION_FEE_PAID', 'Applicant RMU-2026-0022 paid ৳150 fee via bKash Sandbox Gateway (Session: RMUSBX-20261112012643-7BBED1D441, TrxID: SBK261112CCF6FB81)', '127.0.0.1', '2026-11-12 01:26:45'),
(2245, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:26:46'),
(2247, 1, 'ADMISSION_REJECTED', 'Rejected application #672. Notes: E2E Integration Test Reject Verification', '127.0.0.1', '2026-11-12 01:26:46'),
(2248, 1, 'ADMISSION_ACCEPTED', 'Accepted application #671 and provisioned Student ID RMU2601006', '127.0.0.1', '2026-11-12 01:26:47'),
(2249, 1, 'ADMISSION_STATUS_UPDATED', 'Updated application ID 671 to status \'Accepted\' with notes: \'Sync test\'', '127.0.0.1', '2026-11-12 01:26:47'),
(2250, NULL, 'STUDENT_ACADEMIC_EDIT_BLOCKED', 'Blocked student #737 (RMU2601006) POST attempt to alter protected academic records', '127.0.0.1', '2026-11-12 01:26:48'),
(2273, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:27:08'),
(2287, NULL, 'STUDENT_ACTION_DENIED', 'Unauthorized role \'editor\' attempted action \'reset_student_password\' on student #4', '127.0.0.1', '2026-11-12 01:27:11'),
(2288, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:27:11'),
(2290, 1, 'ADMISSION_ACCEPTED', 'Accepted application #676 and provisioned Student ID RMU2601006', '127.0.0.1', '2026-11-12 01:27:13'),
(2291, 1, 'STUDENT_PASSWORD_RESET', 'Admin #1 reset login password and invalidated active portal sessions for student RMU2601006 (#742).', '127.0.0.1', '2026-11-12 01:27:14'),
(2292, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:27:14'),
(2293, NULL, 'STUDENT_SELF_PASSWORD_CHANGED', 'Student RMU2601006 (#742) updated their portal password.', '127.0.0.1', '2026-11-12 01:27:15'),
(2296, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:28:13'),
(2297, 1, 'USER_LOGOUT', 'Administrator \'admin\' logged out.', '127.0.0.1', '2026-11-12 01:28:40'),
(2298, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:29:34'),
(2299, 1, 'USER_LOGOUT', 'Administrator \'admin\' logged out.', '127.0.0.1', '2026-11-12 01:29:36'),
(2300, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:29:53'),
(2301, 1, 'USER_LOGOUT', 'Administrator \'admin\' logged out.', '127.0.0.1', '2026-11-12 01:29:56'),
(2302, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:38:00'),
(2303, 1, 'USER_LOGOUT', 'Administrator \'admin\' logged out.', '127.0.0.1', '2026-11-12 01:38:27'),
(2304, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:38:39'),
(2305, 1, 'NOTICE_ADDED', 'Added notice: RMU Live Sync Notice 1794425916685', '127.0.0.1', '2026-11-12 01:38:40'),
(2306, 1, 'STUDENT_COURSE_ENROLLED', 'Enrolled RMU-SYNC-1794425916685 (#746) in SYN 685 - Live Sync Verification Course 1794425916685', '127.0.0.1', '2026-11-12 01:38:41'),
(2307, 1, 'STUDENT_FEE_ADDED', 'Invoiced fee \'Desktop-Mobile Sync Fee 1794425916685\' (৳4500, Status: Due) for RMU-SYNC-1794425916685 (#746)', '127.0.0.1', '2026-11-12 01:38:42'),
(2308, 1, 'USER_LOGOUT', 'Administrator \'admin\' logged out.', '127.0.0.1', '2026-11-12 01:38:43'),
(2309, NULL, 'STUDENT_SELF_PASSWORD_CHANGED', 'Student RMU-SYNC-1794425916685 (#746) updated their portal password.', '127.0.0.1', '2026-11-12 01:38:47');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(2310, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:39:57'),
(2311, 1, 'RESULT_ADDED', 'Added CSE 101 (A+, 85/100 marks) for Student #757', '127.0.0.1', '2026-11-12 01:39:58'),
(2312, 1, 'RESULT_ADDED', 'Added CSE 102 (A+, 42/50 marks) for Student #757', '127.0.0.1', '2026-11-12 01:39:58'),
(2319, 1, 'RESULT_EDITED', 'Updated result #5675 (CSE 602) for Student #757. Reason: E2E Pass update & CGPA recalculation verification', '127.0.0.1', '2026-11-12 01:39:59'),
(2323, NULL, 'RESULT_ACTION_DENIED', 'Unauthorized role tried action \'import_semester_results\' on results manager for Student #757', '127.0.0.1', '2026-11-12 01:39:59'),
(2327, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:40:00'),
(2328, 1, 'ACADEMIC_SESSION_ADDED', 'Added Academic Session \'Spring 2036\' (enabled=1, current=0)', '127.0.0.1', '2026-11-12 01:40:00'),
(2329, 1, 'ACADEMIC_SESSION_UPDATED', 'Updated Academic Session #77 (Spring 2036 -> Spring 2036, enabled=1)', '127.0.0.1', '2026-11-12 01:40:00'),
(2330, 1, 'PASSING_YEAR_ADDED', 'Added Passing Year 2036 (label=2036, enabled=1)', '127.0.0.1', '2026-11-12 01:40:00'),
(2331, 1, 'ACADEMIC_SESSION_TOGGLED', 'Disabled Academic Session \'Summer 2013\'', '127.0.0.1', '2026-11-12 01:40:00'),
(2332, 1, 'PASSING_YEAR_TOGGLED', 'Disabled Passing Year 2013', '127.0.0.1', '2026-11-12 01:40:00'),
(2333, 1, 'ACADEMIC_SESSION_TOGGLED', 'Enabled Academic Session \'Summer 2013\'', '127.0.0.1', '2026-11-12 01:40:00'),
(2334, 1, 'PASSING_YEAR_TOGGLED', 'Enabled Passing Year 2013', '127.0.0.1', '2026-11-12 01:40:01'),
(2338, 1, 'PROGRAM_EDITED', 'Updated program ID 10: Business Administration — BBA (Honours) (Bachelor / Honours, 5 Years / 12 Semesters, 160 Cr)', '127.0.0.1', '2026-11-12 01:40:02'),
(2340, 1, 'PROGRAM_EDITED', 'Updated program ID 10: Business Administration — BBA (Honours) (Bachelor / Honours, 4 Years / 8 Semesters, 130 Cr)', '127.0.0.1', '2026-11-12 01:40:02'),
(2346, NULL, 'STUDENT_DELETE_DENIED', 'Unauthorized role \'editor\' attempted permanent student deletion via API', '127.0.0.1', '2026-11-12 01:40:05'),
(2347, NULL, 'PROGRAM_ACTION_DENIED', 'Unauthorized role \'editor\' attempted permanent program deletion via API', '127.0.0.1', '2026-11-12 01:40:05'),
(2348, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:40:05'),
(2355, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:40:06'),
(2357, NULL, 'ADMISSION_PAYMENT_INITIATED', 'Initiated ৳150 Sandbox Gateway session RMUSBX-20261112014007-F99B4E94A4 via bKash for RMU-2026-0024', '127.0.0.1', '2026-11-12 01:40:07'),
(2358, NULL, 'ADMISSION_FEE_PAID', 'Applicant RMU-2026-0024 paid ৳150 fee via bKash Sandbox Gateway (Session: RMUSBX-20261112014007-F99B4E94A4, TrxID: SBK2611126BD76741)', '127.0.0.1', '2026-11-12 01:40:10'),
(2364, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:40:10'),
(2370, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:40:13'),
(2383, 1, 'ALL_RESULTS_VERIFIED_OFFICIAL', 'Admin #1 verified 32 estimated result(s) as official for student #767', '127.0.0.1', '2026-11-12 01:40:17'),
(2390, 1, 'ESTIMATED_RESULTS_GENERATED', 'Generated 8-semester estimated results for student #772 (Bachelor Test Student) [Session: 14th Batch (2026-2030), PubDate: 2026-11-12, Target CGPA: 3.85, Realized CGPA: 3.85]', '127.0.0.1', '2026-11-12 01:40:21'),
(2391, 1, 'ESTIMATED_RESULTS_GENERATED', 'Generated 4-semester estimated results for student #773 (Master Test Student) [Session: MBA 2026 Batch, PubDate: 2026-11-12, Target CGPA: 3.72, Realized CGPA: 3.72]', '127.0.0.1', '2026-11-12 01:40:21'),
(2392, 1, 'ESTIMATED_RESULTS_GENERATED', 'Generated 8-semester estimated results for student #772 (Bachelor Test Student) [Session: 14th Batch (2026-2030), PubDate: 2026-11-12, Target CGPA: 3.55, Realized CGPA: 3.55]', '127.0.0.1', '2026-11-12 01:40:21'),
(2393, 1, 'RESULTS_VERIFIED_OFFICIAL', 'Admin verified 4 estimated course result(s) in 1st Semester as official for student #772', '127.0.0.1', '2026-11-12 01:40:21'),
(2394, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:40:22'),
(2395, 1, 'STUDENT_ADDED_ONE_PAGE', 'Enrolled student: E2E Test Student (RMU2609946)', '127.0.0.1', '2026-11-12 01:40:23'),
(2396, 1, 'RESULTS_DRAFT_ONE_PAGE', 'Saved results as Draft for Student #774 via One-Page Console', '127.0.0.1', '2026-11-12 01:40:23'),
(2397, 1, 'STUDENT_UPDATED_ONE_PAGE', 'Updated student ID #774 (E2E Test Student)', '127.0.0.1', '2026-11-12 01:40:23'),
(2398, 1, 'RESULTS_PUBLISHED_ONE_PAGE', 'Published results for Student #774 via One-Page Console', '127.0.0.1', '2026-11-12 01:40:23'),
(2401, NULL, 'ADMISSION_PAYMENT_INITIATED', 'Initiated ৳150 Sandbox Gateway session RMUSBX-20261112014024-EFA287786F via bKash for RMU-2026-0025', '127.0.0.1', '2026-11-12 01:40:24'),
(2402, NULL, 'ADMISSION_FEE_PAID', 'Applicant RMU-2026-0025 paid ৳150 fee via bKash Sandbox Gateway (Session: RMUSBX-20261112014024-EFA287786F, TrxID: SBK2611121C18853D)', '127.0.0.1', '2026-11-12 01:40:26'),
(2403, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:40:26'),
(2405, 1, 'ADMISSION_REJECTED', 'Rejected application #696. Notes: E2E Integration Test Reject Verification', '127.0.0.1', '2026-11-12 01:40:27'),
(2406, 1, 'ADMISSION_ACCEPTED', 'Accepted application #695 and provisioned Student ID RMU2601006', '127.0.0.1', '2026-11-12 01:40:27'),
(2407, 1, 'ADMISSION_STATUS_UPDATED', 'Updated application ID 695 to status \'Accepted\' with notes: \'Sync test\'', '127.0.0.1', '2026-11-12 01:40:28'),
(2408, NULL, 'STUDENT_ACADEMIC_EDIT_BLOCKED', 'Blocked student #775 (RMU2601006) POST attempt to alter protected academic records', '127.0.0.1', '2026-11-12 01:40:29'),
(2431, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:40:48'),
(2445, NULL, 'STUDENT_ACTION_DENIED', 'Unauthorized role \'editor\' attempted action \'reset_student_password\' on student #4', '127.0.0.1', '2026-11-12 01:40:52'),
(2446, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:40:52'),
(2448, 1, 'ADMISSION_ACCEPTED', 'Accepted application #700 and provisioned Student ID RMU2601006', '127.0.0.1', '2026-11-12 01:40:53'),
(2449, 1, 'STUDENT_PASSWORD_RESET', 'Admin #1 reset login password and invalidated active portal sessions for student RMU2601006 (#780).', '127.0.0.1', '2026-11-12 01:40:54'),
(2450, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:40:55'),
(2451, NULL, 'STUDENT_SELF_PASSWORD_CHANGED', 'Student RMU2601006 (#780) updated their portal password.', '127.0.0.1', '2026-11-12 01:40:56'),
(2454, 1, 'USER_LOGIN', 'Administrator \'admin\' logged in successfully.', '127.0.0.1', '2026-11-12 01:46:04'),
(2455, 1, 'STUDENT_PERMANENTLY_DELETED', 'Admin #1 permanently deleted student #632 (RMU2601005 - amir hasan) from database.', '127.0.0.1', '2026-11-12 01:46:15'),
(2456, 1, 'STUDENT_PERMANENTLY_DELETED', 'Admin #1 permanently deleted student #592 (RMU2601004 - tarek khan) from database.', '127.0.0.1', '2026-11-12 01:46:19');

--
-- Dumping data for table `faculties` (4 rows)
--
INSERT INTO `faculties` (`id`, `slug`, `name_en`, `name_bn`, `dean_name`, `overview`, `icon`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'arts-and-social-science', 'Faculty of Arts and Social Science', '', 'Prof. Dr. M. A. Karim', 'The Faculty of Arts and Social Science fosters critical thinking, cultural appreciation, and creative expression through rich curricula in languages, arts, and physical education.', 'fa-feather-alt', 1, 1, '2026-10-17 12:39:47'),
(2, 'engineering-and-technology', 'Faculty of Engineering and Technology', '', 'Dr. Engr. S. R. Rahman', 'The Faculty of Engineering is committed to producing world-class engineers, computer scientists, and technologists equipped with modern laboratory training and research skills.', 'fa-laptop-code', 2, 1, '2026-10-17 12:39:47'),
(3, 'business-studies', 'Faculty of Business Studies', '', 'Prof. Mohammad Hossain', 'Dedicated to cultivating innovative business leaders and ethical entrepreneurs who drive economic and social progress in an interconnected global market.', 'fa-briefcase', 3, 1, '2026-10-17 12:39:47'),
(4, 'agriculture', 'Faculty of Agriculture', '', 'Dr. Farhana Yasmin', 'Advancing agricultural science, sustainable farming, and food security through field research and cutting-edge agro-technology.', 'fa-seedling', 4, 1, '2026-10-17 12:39:47');

--
-- Dumping data for table `departments` (13 rows)
--
INSERT INTO `departments` (`id`, `faculty_id`, `slug`, `name_en`, `name_bn`, `code`, `head_name`, `email`, `phone`, `overview`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 1, 'dept-english', 'Department of English', '', 'ENG', 'Prof. Shamsul Alam', 'english@rmu.ac.bd', '+8801302991188', 'Offering comprehensive study of English literature, linguistics, communication skills, and critical theory.', 1, 1, '2026-10-17 12:39:47'),
(2, 1, 'dept-bangla', 'Department of Bangla', '', 'BNG', 'Dr. Anowar Hossain', 'bangla@rmu.ac.bd', '+8801302991188', 'Dedicated to the heritage of Bengali literature, philology, folklore, and the profound philosophy of Rabindranath Tagore.', 2, 1, '2026-10-17 12:39:47'),
(3, 1, 'dept-fine-arts', 'Department of Fine Arts', '', 'FAR', 'Kazi Mahbubur Rahman', 'finearts@rmu.ac.bd', '+8801302991188', 'Nurturing visual creativity, painting, sculpture, and graphic artistry with studio workshops.', 3, 1, '2026-10-17 12:39:47'),
(4, 1, 'dept-music', 'Department of Music', '', 'MUS', 'Rezwana Chowdhury', 'music@rmu.ac.bd', '+8801302991188', 'Promoting classical music, Rabindra Sangeet, Nazrul Geeti, and traditional folk music heritage.', 4, 1, '2026-10-17 12:39:47'),
(5, 1, 'dept-physical-education', 'Department of Physical Education and Sports Science', '', 'PESS', 'Md. Rafiqul Islam', 'sports@rmu.ac.bd', '+8801302991188', 'Focusing on athletic training, sports physiology, physical fitness management, and kinesiology.', 5, 1, '2026-10-17 12:39:47'),
(6, 2, 'dept-cse', 'Department of Computer Science and Engineering', '', 'CSE', 'Dr. Tariqul Islam', 'cse@rmu.ac.bd', '+8801302991188', 'Equipping students with expertise in software engineering, algorithms, AI, cloud computing, and cybersecurity.', 6, 1, '2026-10-17 12:39:47'),
(7, 2, 'dept-eee', 'Department of Electrical and Electronic Engineering', '', 'EEE', 'Engr. Mahfuzur Rahman', 'eee@rmu.ac.bd', '+8801302991188', 'Focusing on power systems, telecommunications, microelectronics, and renewable energy.', 7, 1, '2026-10-17 12:39:47'),
(8, 2, 'dept-ict', 'Department of Information and Communication Technology', '', 'ICT', 'Syed Nazmul Hasan', 'ict@rmu.ac.bd', '+8801302991188', 'Specializing in computer networks, telecommunications, web technologies, and systems integration.', 8, 1, '2026-10-17 12:39:47'),
(9, 2, 'dept-microbiology', 'Department of Microbiology', '', 'MIC', 'Dr. Nusrat Jahan', 'microbiology@rmu.ac.bd', '+8801302991188', 'Investigating bacterial and viral genetics, immunology, pharmaceutical microbiology, and clinical research.', 9, 1, '2026-10-17 12:39:47'),
(10, 3, 'dept-business-administration', 'Department of Business Administration', '', 'DBA', 'Dr. Mahmudul Hasan', 'dba@rmu.ac.bd', '+8801302991188', 'Delivering premier business education in finance, marketing, HRM, supply chain, and strategic management.', 10, 1, '2026-10-17 12:39:47'),
(11, 4, 'dept-agriculture', 'Department of Agriculture', '', 'AGR', 'Dr. A. B. M. Faruq', 'agriculture@rmu.ac.bd', '+8801302991188', 'Conducting scientific training in crop science, soil fertility, plant pathology, and modern agro-enterprise.', 11, 1, '2026-10-17 12:39:47'),
(12, 1, 'dept-education', 'Department of Education', 'Department of Education', 'EDU', 'Head of Education Studies', 'education@rmu.ac.bd', NULL, 'The Department of Education prepares skilled teachers, instructional designers, and academic leaders through modern pedagogy and supervised classroom practice.', 12, 1, '2026-11-10 00:04:33'),
(13, 1, 'dept-social-science', 'Department of Social Science', 'Department of Social Science', 'SOC', 'Head of Social Science', 'socialscience@rmu.ac.bd', NULL, 'The Department of Social Science explores sociology, public policy, governance, development studies, and social research methods.', 13, 1, '2026-11-10 01:21:45');

--
-- Dumping data for table `programs` (18 rows)
--
INSERT INTO `programs` (`id`, `department_id`, `slug`, `title_en`, `title_bn`, `degree_level`, `degree_type`, `duration`, `duration_years`, `total_semesters`, `total_credits`, `semester_credits_json`, `eligibility`, `description`, `tuition_fee`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 6, 'bsc-in-cse', 'Computer Science and Engineering (CSE)', '', 'Undergraduate', 'Bachelor / Honours', '4 Years (8 Semesters)', '4.0', 8, 148, NULL, 'Minimum GPA 2.50 in both SSC & HSC with Science background and Mathematics.', 'Comprehensive undergraduate engineering degree covering algorithms, systems, software engineering, databases, and AI.', 'BDT 3,80,000 (Waiver up to 50% available)', 7, 1, '2026-10-17 12:39:47'),
(2, 7, 'bsc-in-eee', 'Electrical and Electronic Engineering (EEE)', '', 'Undergraduate', 'Bachelor / Honours', '4 Years (8 Semesters)', '4.0', 8, 150, NULL, 'Minimum GPA 2.50 in SSC & HSC with Physics and Mathematics.', 'In-depth engineering curriculum in circuits, power transmission, communication systems, and embedded electronics.', 'BDT 3,75,000', 9, 1, '2026-10-17 12:39:47'),
(3, 8, 'bsc-in-ict', 'Information and Communication Technology (ICT)', '', 'Undergraduate', 'Bachelor / Honours', '4 Years (8 Semesters)', '4.0', 8, 144, NULL, 'Minimum GPA 2.50 in SSC & HSC from Science or relevant vocational background.', 'Focused on data communication, network engineering, cloud systems, and applied IT solutions.', 'BDT 3,50,000', 8, 1, '2026-10-17 12:39:47'),
(4, 9, 'bsc-in-microbiology', 'Microbiology — BSc (Honours)', '', 'Undergraduate', 'Bachelor / Honours', '4 Years (8 Semesters)', '4.0', 8, 140, NULL, 'Minimum GPA 2.50 in SSC & HSC with Biology and Chemistry.', 'Hands-on laboratory training in molecular biology, clinical diagnostics, food safety, and biochemistry.', 'BDT 3,20,000', 10, 1, '2026-10-17 12:39:47'),
(5, 11, 'bsc-in-agriculture', 'Agriculture — BSc (Honours)', '', 'Undergraduate', 'Bachelor / Honours', '4 Years (8 Semesters)', '4.0', 8, 160, NULL, 'Minimum GPA 2.50 in SSC & HSC with Science background (Biology compulsory).', 'The Bachelor of Science (Honours) in Agriculture provides rigorous academic and field-based training in crop production, agronomy, soil science, plant pathology, agricultural biotechnology, and sustainable farm management. Students gain hands-on experience through modern research farm laboratories and agro-industrial field visits.', 'BDT 3,40,000 (Waiver up to 50% available)', 6, 1, '2026-10-17 12:39:47'),
(6, 1, 'ba-in-english', 'English — BA (Honours)', '', 'Undergraduate', 'Bachelor / Honours', '4 Years (8 Semesters)', '4.0', 8, 128, NULL, 'Minimum GPA 2.50 in SSC & HSC from any background with good score in English.', 'Exploration of British, American, and World literature, critical theory, and professional English writing.', 'BDT 2,40,000', 2, 1, '2026-10-17 12:39:47'),
(7, 2, 'ba-in-bangla', 'Bangla — BA (Honours)', '', 'Undergraduate', 'Bachelor / Honours', '4 Years (8 Semesters)', '4.0', 8, 128, NULL, 'Minimum GPA 2.50 in SSC & HSC from any academic stream.', 'Rich study of Bengali literary history, grammar, linguistics, and Rabindranath Tagore heritage.', 'BDT 1,80,000', 1, 1, '2026-10-17 12:39:47'),
(8, 3, 'ba-in-fine-arts', 'Fine Arts — BFA (Honours)', '', 'Undergraduate', 'Bachelor / Honours', '4 Years (8 Semesters)', '4.0', 8, 132, NULL, 'Minimum GPA 2.50 in SSC & HSC with artistic portfolio or aptitude test.', 'Studio instruction in drawing, painting, sculpture, visual graphics, and Bangladesh art history.', 'BDT 2,20,000', 4, 1, '2026-10-17 12:39:47'),
(9, 4, 'ba-in-music', 'Music — BA (Honours)', '', 'Undergraduate', 'Bachelor / Honours', '4 Years (8 Semesters)', '4.0', 8, 124, NULL, 'Minimum GPA 2.50 in SSC & HSC with vocal or instrumental aptitude.', 'Rigorous vocal training, ragas, Tagore songs, Nazrul songs, and contemporary composition.', 'BDT 2,00,000', 3, 1, '2026-10-17 12:39:47'),
(10, 10, 'bba', 'Business Administration — BBA (Honours)', '', 'Undergraduate', 'Bachelor / Honours', '4 Years (8 Semesters)', '4.0', 8, 130, NULL, '', '', 'BDT 3,10,000', 5, 1, '2026-10-17 12:39:47'),
(11, 1, 'ma-in-english', 'MA in English', '', 'Postgraduate', 'Master\'s', '2 Years (4 Semesters)', '2.0', 4, 60, NULL, 'Bachelor degree in English with minimum 2nd class or CGPA 2.50.', 'Advanced literary discourse, applied linguistics, and research thesis in English studies.', 'BDT 90,000', 4, 1, '2026-10-17 12:39:47'),
(12, 2, 'ma-in-bangla', 'MA in Bangla', '', 'Postgraduate', 'Master\'s', '2 Years (4 Semesters)', '2.0', 4, 60, NULL, 'BA in Bangla with minimum 2nd class or CGPA 2.50.', 'Specialized research in modern poetry, Rabindranath studies, and contemporary Bengali linguistics.', 'BDT 75,000', 1, 1, '2026-10-17 12:39:47'),
(13, 5, 'mped', 'Master of Physical Education (MPEd)', '', 'Postgraduate', 'Master\'s', '2 Years (4 Semesters)', '2.0', 4, 60, NULL, 'BPED or relevant graduation degree with sports credentials.', 'Advanced sports pedagogy, athletic administration, exercise physiology, and sports medicine.', 'BDT 85,000', 3, 1, '2026-10-17 12:39:47'),
(14, 10, 'mba', 'Master of Business Administration (MBA)', '', 'Postgraduate', 'Master\'s', '2 Years (4 Semesters)', '2.0', 4, 60, NULL, 'Graduation in any discipline with minimum CGPA 2.50.', 'Executive management, strategic corporate finance, international trade, and organizational leadership.', 'BDT 1,60,000', 2, 1, '2026-10-17 12:39:47'),
(15, 5, 'physical-education-sports-science', 'Physical Education and Sports Science', '', 'Undergraduate', 'Bachelor / Honours', '4 Years (8 Semesters)', '4.0', 8, 128, NULL, 'Minimum GPA 2.50 in SSC & HSC from any background with physical fitness or athletic participation record.', 'Comprehensive undergraduate curriculum blending athletic training, sports physiology, kinesiology, biomechanics, exercise management, and sports pedagogy for future coaches, athletic directors, and physical educators.', 'BDT 1,90,000', 11, 1, '2026-10-17 13:13:10'),
(16, 12, 'b-ed', 'Bachelor of Education (B.Ed)', 'Bachelor of Education (B.Ed)', 'Undergraduate', 'B.Ed', '1 Year (2 Semesters)', '1.0', 2, 48, '{\"1\":24,\"2\":24}', 'Candidates must hold a recognized Bachelor degree (BA / BSS / BSc / BBS or equivalent) from an accredited university.', 'The Bachelor of Education (B.Ed) program equips future and practicing educators with educational psychology, pedagogical methodology, curriculum evaluation, and supervised teaching practicum. Supports 2-semester default or admin-configured 3-semester curriculum.', 'Tk. 45,000 Total Tuition Fee', 12, 1, '2026-11-10 00:04:33'),
(17, 13, 'bss-honours', 'BSS (Honours) in Social Science', 'BSS (Honours)', 'Undergraduate', 'Bachelor / Honours', '4 Years (8 Semesters)', '4.0', 8, 128, '{\"1\":16,\"2\":16,\"3\":16,\"4\":16,\"5\":16,\"6\":16,\"7\":16,\"8\":16}', 'Candidates must pass HSC or equivalent examination with minimum required GPA as per RMU undergraduate admission regulations.', 'The four-year eight-semester Bachelor of Social Science (BSS Honours) program offers multidisciplinary coursework in sociology, public administration, development studies, economics, and applied social research.', 'Tk. 1,40,000 Total Tuition Fee', 13, 1, '2026-11-10 01:21:45'),
(28, 7, 'eee', 'EEE', 'EEE', 'Undergraduate', 'Bachelor / Honours', '4 Years (8 Semesters)', '4.0', 8, 140, NULL, '', '', 'BDT 3,50,000', 0, 1, '2026-11-11 02:32:09');

--
-- Dumping data for table `academic_sessions` (60 rows)
--
INSERT INTO `academic_sessions` (`id`, `session_name`, `term_name`, `session_year`, `sort_order`, `is_enabled`, `is_current`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'Spring 2011', 'Spring', 2011, 20111, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-10 01:21:44'),
(2, 'Summer 2011', 'Summer', 2011, 20112, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-10 01:21:44'),
(3, 'Fall 2011', 'Fall', 2011, 20113, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-10 01:21:44'),
(4, 'Spring 2012', 'Spring', 2012, 20121, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-10 01:21:44'),
(5, 'Summer 2012', 'Summer', 2012, 20122, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-10 01:21:44'),
(6, 'Fall 2012', 'Fall', 2012, 20123, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-10 01:21:44'),
(7, 'Spring 2013', 'Spring', 2013, 20131, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-10 01:21:44'),
(8, 'Summer 2013', 'Summer', 2013, 20132, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-12 01:40:00'),
(9, 'Fall 2013', 'Fall', 2013, 20133, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-10 01:21:44'),
(10, 'Spring 2014', 'Spring', 2014, 20141, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-10 01:21:44'),
(11, 'Summer 2014', 'Summer', 2014, 20142, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-10 01:21:44'),
(12, 'Fall 2014', 'Fall', 2014, 20143, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-10 01:21:44'),
(13, 'Spring 2015', 'Spring', 2015, 20151, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-10 01:21:44'),
(14, 'Summer 2015', 'Summer', 2015, 20152, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-10 01:21:44'),
(15, 'Fall 2015', 'Fall', 2015, 20153, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-10 01:21:44'),
(16, 'Spring 2016', 'Spring', 2016, 20161, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-10 01:21:44'),
(17, 'Summer 2016', 'Summer', 2016, 20162, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-10 01:21:44'),
(18, 'Fall 2016', 'Fall', 2016, 20163, 1, 0, NULL, '2026-11-10 01:21:44', '2026-11-10 01:21:44'),
(19, 'Spring 2017', 'Spring', 2017, 20171, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(20, 'Summer 2017', 'Summer', 2017, 20172, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(21, 'Fall 2017', 'Fall', 2017, 20173, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(22, 'Spring 2018', 'Spring', 2018, 20181, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(23, 'Summer 2018', 'Summer', 2018, 20182, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(24, 'Fall 2018', 'Fall', 2018, 20183, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(25, 'Spring 2019', 'Spring', 2019, 20191, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(26, 'Summer 2019', 'Summer', 2019, 20192, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(27, 'Fall 2019', 'Fall', 2019, 20193, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(28, 'Spring 2020', 'Spring', 2020, 20201, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(29, 'Summer 2020', 'Summer', 2020, 20202, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(30, 'Fall 2020', 'Fall', 2020, 20203, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(31, 'Spring 2021', 'Spring', 2021, 20211, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(32, 'Summer 2021', 'Summer', 2021, 20212, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(33, 'Fall 2021', 'Fall', 2021, 20213, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(34, 'Spring 2022', 'Spring', 2022, 20221, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(35, 'Summer 2022', 'Summer', 2022, 20222, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(36, 'Fall 2022', 'Fall', 2022, 20223, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(37, 'Spring 2023', 'Spring', 2023, 20231, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(38, 'Summer 2023', 'Summer', 2023, 20232, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(39, 'Fall 2023', 'Fall', 2023, 20233, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(40, 'Spring 2024', 'Spring', 2024, 20241, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(41, 'Summer 2024', 'Summer', 2024, 20242, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(42, 'Fall 2024', 'Fall', 2024, 20243, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(43, 'Spring 2025', 'Spring', 2025, 20251, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(44, 'Summer 2025', 'Summer', 2025, 20252, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(45, 'Fall 2025', 'Fall', 2025, 20253, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(46, 'Spring 2026', 'Spring', 2026, 20261, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(47, 'Summer 2026', 'Summer', 2026, 20262, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(48, 'Fall 2026', 'Fall', 2026, 20263, 1, 1, NULL, '2026-11-10 01:21:45', '2026-11-12 01:40:03'),
(49, 'Spring 2027', 'Spring', 2027, 20271, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(50, 'Summer 2027', 'Summer', 2027, 20272, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45');
INSERT INTO `academic_sessions` (`id`, `session_name`, `term_name`, `session_year`, `sort_order`, `is_enabled`, `is_current`, `notes`, `created_at`, `updated_at`) VALUES
(51, 'Fall 2027', 'Fall', 2027, 20273, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(52, 'Spring 2028', 'Spring', 2028, 20281, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(53, 'Summer 2028', 'Summer', 2028, 20282, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(54, 'Fall 2028', 'Fall', 2028, 20283, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(55, 'Spring 2029', 'Spring', 2029, 20291, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(56, 'Summer 2029', 'Summer', 2029, 20292, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(57, 'Fall 2029', 'Fall', 2029, 20293, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(58, 'Spring 2030', 'Spring', 2030, 20301, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(59, 'Summer 2030', 'Summer', 2030, 20302, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(60, 'Fall 2030', 'Fall', 2030, 20303, 1, 0, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45');

--
-- Dumping data for table `passing_years` (20 rows)
--
INSERT INTO `passing_years` (`id`, `year_value`, `label`, `sort_order`, `is_enabled`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2011, 2011, 2011, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(2, 2012, 2012, 2012, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(3, 2013, 2013, 2013, 1, NULL, '2026-11-10 01:21:45', '2026-11-12 01:40:00'),
(4, 2014, 2014, 2014, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(5, 2015, 2015, 2015, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(6, 2016, 2016, 2016, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(7, 2017, 2017, 2017, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(8, 2018, 2018, 2018, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(9, 2019, 2019, 2019, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(10, 2020, 2020, 2020, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(11, 2021, 2021, 2021, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(12, 2022, 2022, 2022, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(13, 2023, 2023, 2023, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(14, 2024, 2024, 2024, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(15, 2025, 2025, 2025, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(16, 2026, 2026, 2026, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(17, 2027, 2027, 2027, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(18, 2028, 2028, 2028, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(19, 2029, 2029, 2029, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45'),
(20, 2030, 2030, 2030, 1, NULL, '2026-11-10 01:21:45', '2026-11-10 01:21:45');

--
-- Dumping data for table `academic_edit_history` (50 rows)
--
INSERT INTO `academic_edit_history` (`id`, `entity_type`, `entity_id`, `student_id_code`, `admin_id`, `field_name`, `old_value`, `new_value`, `reason`, `created_at`) VALUES
(3, 'session', 8, NULL, 1, 'is_enabled', 1, '0', 'Disabled Academic Session from admission intake', '2026-11-10 01:44:30'),
(4, 'passing_year', 3, NULL, 1, 'is_enabled', 1, '0', 'Disabled Passing Year from admission form', '2026-11-10 01:44:30'),
(5, 'session', 8, NULL, 1, 'is_enabled', '0', 1, 'Enabled Academic Session for admission intake', '2026-11-10 01:44:30'),
(6, 'passing_year', 3, NULL, 1, 'is_enabled', '0', 1, 'Enabled Passing Year for admission form', '2026-11-10 01:44:30'),
(19, 'program', 16, NULL, 1, 'duration_years', 1, '1.5', 'Program duration/semester structure configured from Semester Management', '2026-11-10 01:47:29'),
(20, 'program', 16, NULL, 1, 'total_semesters', 2, 3, 'Program duration/semester structure configured from Semester Management', '2026-11-10 01:47:29'),
(21, 'program', 16, NULL, 1, 'total_credits', 48, 54, 'Program duration/semester structure configured from Semester Management', '2026-11-10 01:47:29'),
(24, 'session', 8, NULL, 1, 'is_enabled', 1, '0', 'Disabled Academic Session from admission intake', '2026-11-10 01:47:59'),
(25, 'passing_year', 3, NULL, 1, 'is_enabled', 1, '0', 'Disabled Passing Year from admission form', '2026-11-10 01:47:59'),
(26, 'session', 8, NULL, 1, 'is_enabled', '0', 1, 'Enabled Academic Session for admission intake', '2026-11-10 01:47:59'),
(27, 'passing_year', 3, NULL, 1, 'is_enabled', '0', 1, 'Enabled Passing Year for admission form', '2026-11-10 01:48:00'),
(40, 'program', 16, NULL, 1, 'duration_years', 1, '1.5', 'Program duration/semester structure configured from Semester Management', '2026-11-10 01:48:56'),
(41, 'program', 16, NULL, 1, 'total_semesters', 2, 3, 'Program duration/semester structure configured from Semester Management', '2026-11-10 01:48:56'),
(42, 'program', 16, NULL, 1, 'total_credits', 48, 54, 'Program duration/semester structure configured from Semester Management', '2026-11-10 01:48:56'),
(51, 'program', 16, NULL, 1, 'duration_years', 1, '1.5', 'Program duration/semester structure configured from Semester Management', '2026-11-10 02:17:38'),
(52, 'program', 16, NULL, 1, 'total_semesters', 2, 3, 'Program duration/semester structure configured from Semester Management', '2026-11-10 02:17:38'),
(53, 'program', 16, NULL, 1, 'total_credits', 48, 54, 'Program duration/semester structure configured from Semester Management', '2026-11-10 02:17:38'),
(56, 'session', 8, NULL, 1, 'is_enabled', 1, '0', 'Disabled Academic Session from admission intake', '2026-11-10 02:17:42'),
(57, 'passing_year', 3, NULL, 1, 'is_enabled', 1, '0', 'Disabled Passing Year from admission form', '2026-11-10 02:17:42'),
(58, 'session', 8, NULL, 1, 'is_enabled', '0', 1, 'Enabled Academic Session for admission intake', '2026-11-10 02:17:42'),
(59, 'passing_year', 3, NULL, 1, 'is_enabled', '0', 1, 'Enabled Passing Year for admission form', '2026-11-10 02:17:42'),
(84, 'program', 16, NULL, 1, 'duration_years', 1, '1.5', 'Program duration/semester structure configured from Semester Management', '2026-11-11 00:16:52'),
(85, 'program', 16, NULL, 1, 'total_semesters', 2, 3, 'Program duration/semester structure configured from Semester Management', '2026-11-11 00:16:52'),
(86, 'program', 16, NULL, 1, 'total_credits', 48, 54, 'Program duration/semester structure configured from Semester Management', '2026-11-11 00:16:52'),
(89, 'session', 8, NULL, 1, 'is_enabled', 1, '0', 'Disabled Academic Session from admission intake', '2026-11-11 00:16:56'),
(90, 'passing_year', 3, NULL, 1, 'is_enabled', 1, '0', 'Disabled Passing Year from admission form', '2026-11-11 00:16:56'),
(91, 'session', 8, NULL, 1, 'is_enabled', '0', 1, 'Enabled Academic Session for admission intake', '2026-11-11 00:16:56'),
(92, 'passing_year', 3, NULL, 1, 'is_enabled', '0', 1, 'Enabled Passing Year for admission form', '2026-11-11 00:16:56'),
(109, 'program', 16, NULL, 1, 'duration_years', 1, '1.5', 'Program duration/semester structure configured from Semester Management', '2026-11-11 00:29:35'),
(110, 'program', 16, NULL, 1, 'total_semesters', 2, 3, 'Program duration/semester structure configured from Semester Management', '2026-11-11 00:29:35'),
(111, 'program', 16, NULL, 1, 'total_credits', 48, 54, 'Program duration/semester structure configured from Semester Management', '2026-11-11 00:29:35'),
(114, 'session', 8, NULL, 1, 'is_enabled', 1, '0', 'Disabled Academic Session from admission intake', '2026-11-11 00:29:39'),
(115, 'passing_year', 3, NULL, 1, 'is_enabled', 1, '0', 'Disabled Passing Year from admission form', '2026-11-11 00:29:39'),
(116, 'session', 8, NULL, 1, 'is_enabled', '0', 1, 'Enabled Academic Session for admission intake', '2026-11-11 00:29:39'),
(117, 'passing_year', 3, NULL, 1, 'is_enabled', '0', 1, 'Enabled Passing Year for admission form', '2026-11-11 00:29:39'),
(136, 'session', 8, NULL, 1, 'is_enabled', 1, '0', 'Disabled Academic Session from admission intake', '2026-11-11 00:39:36'),
(137, 'passing_year', 3, NULL, 1, 'is_enabled', 1, '0', 'Disabled Passing Year from admission form', '2026-11-11 00:39:36'),
(138, 'session', 8, NULL, 1, 'is_enabled', '0', 1, 'Enabled Academic Session for admission intake', '2026-11-11 00:39:36'),
(139, 'passing_year', 3, NULL, 1, 'is_enabled', '0', 1, 'Enabled Passing Year for admission form', '2026-11-11 00:39:36'),
(152, 'program', 16, NULL, 1, 'duration_years', 1, '1.5', 'Program duration/semester structure configured from Semester Management', '2026-11-11 00:39:44'),
(153, 'program', 16, NULL, 1, 'total_semesters', 2, 3, 'Program duration/semester structure configured from Semester Management', '2026-11-11 00:39:44'),
(154, 'program', 16, NULL, 1, 'total_credits', 48, 54, 'Program duration/semester structure configured from Semester Management', '2026-11-11 00:39:44'),
(161, 'session', 8, NULL, 1, 'is_enabled', 1, '0', 'Disabled Academic Session from admission intake', '2026-11-11 01:08:59'),
(162, 'passing_year', 3, NULL, 1, 'is_enabled', 1, '0', 'Disabled Passing Year from admission form', '2026-11-11 01:08:59'),
(163, 'session', 8, NULL, 1, 'is_enabled', '0', 1, 'Enabled Academic Session for admission intake', '2026-11-11 01:08:59'),
(164, 'passing_year', 3, NULL, 1, 'is_enabled', '0', 1, 'Enabled Passing Year for admission form', '2026-11-11 01:08:59'),
(177, 'program', 16, NULL, 1, 'duration_years', 1, '1.5', 'Program duration/semester structure configured from Semester Management', '2026-11-11 01:09:04'),
(178, 'program', 16, NULL, 1, 'total_semesters', 2, 3, 'Program duration/semester structure configured from Semester Management', '2026-11-11 01:09:04'),
(179, 'program', 16, NULL, 1, 'total_credits', 48, 54, 'Program duration/semester structure configured from Semester Management', '2026-11-11 01:09:04'),
(186, 'session', 8, NULL, 1, 'is_enabled', 1, '0', 'Disabled Academic Session from admission intake', '2026-11-11 01:27:28');

--
-- Dumping data for table `semester_terms` (8 rows)
--
INSERT INTO `semester_terms` (`id`, `term_name`, `academic_year`, `semester_number`, `start_date`, `end_date`, `is_current`, `is_admission_open`, `created_at`) VALUES
(1, '1st Semester (Spring 2023)', 2023, 1, NULL, NULL, 0, 0, '2026-11-04 00:09:55'),
(2, '2nd Semester (Fall 2023)', 2023, 2, NULL, NULL, 0, 0, '2026-11-04 00:09:55'),
(3, '3rd Semester (Spring 2024)', 2024, 3, NULL, NULL, 0, 0, '2026-11-04 00:09:55'),
(4, '4th Semester (Fall 2024)', 2024, 4, NULL, NULL, 0, 0, '2026-11-04 00:09:55'),
(5, '5th Semester (Spring 2025)', 2025, 5, NULL, NULL, 0, 0, '2026-11-04 00:09:55'),
(6, '6th Semester (Fall 2025)', 2025, 6, NULL, NULL, 0, 0, '2026-11-04 00:09:55'),
(7, '7th Semester (Spring 2026)', 2026, 7, NULL, NULL, 0, 0, '2026-11-04 00:09:55'),
(8, '8th Semester (Fall 2026)', 2026, 8, NULL, NULL, 1, 1, '2026-11-04 00:09:55');

--
-- Dumping data for table `faculty_members` (5 rows)
--
INSERT INTO `faculty_members` (`id`, `department_id`, `full_name`, `designation`, `email`, `phone`, `qualification`, `bio`, `image_url`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 6, 'Dr. Tariqul Islam', 'Associate Professor & Head', 'tariqul@rmu.ac.bd', '+8801302991188', 'PhD in Computer Science (BUET), MSc in CSE', 'Specializes in Distributed Systems, Artificial Intelligence, and Software Engineering methodologies.', NULL, 1, 1, '2026-10-17 12:39:47'),
(2, 6, 'Farhana Akter', 'Assistant Professor', 'farhana.cse@rmu.ac.bd', '+8801302991188', 'MSc in Computer Science & Engineering (DU)', 'Research interest in Data Mining, Natural Language Processing, and Machine Learning.', NULL, 2, 1, '2026-10-17 12:39:47'),
(3, 7, 'Engr. Mahfuzur Rahman', 'Assistant Professor & Head', 'mahfuz.eee@rmu.ac.bd', '+8801302991188', 'MSc in Electrical & Electronic Engineering (RUET)', 'Focuses on Renewable Solar Microgrids, Power Systems Analysis, and Industrial Automation.', NULL, 1, 1, '2026-10-17 12:39:47'),
(4, 1, 'Prof. Shamsul Alam', 'Professor & Head', 'shamsul.eng@rmu.ac.bd', '+8801302991188', 'PhD in English Literature (CU), MA in ELT', 'Renowned literary scholar focusing on Post-Colonial Literature, Shakespearean Drama, and Applied Linguistics.', NULL, 1, 1, '2026-10-17 12:39:47'),
(5, 10, 'Dr. Mahmudul Hasan', 'Associate Professor & Head', 'mahmud.dba@rmu.ac.bd', '+8801302991188', 'PhD in Finance (DU), MBA in Banking', 'Expert in Corporate Governance, Behavioral Finance, and SME Development.', NULL, 1, 1, '2026-10-17 12:39:47');

--
-- Dumping data for table `notices` (5 rows)
--
INSERT INTO `notices` (`id`, `slug`, `title_en`, `title_bn`, `category`, `content`, `file_path`, `published_date`, `is_pinned`, `is_active`, `view_count`, `created_at`) VALUES
(1, 'admission-fair-fall-2026', 'Grand Opening of Admission Fair Fall 2026 at RMU', '', 'Admission', '<p>The Grand Opening of the Fall 2026 Semester Admission Fair was held with great enthusiasm at Rabindra Maitree University. During the admission fair, special waivers on admission fees and tuition fees are being offered across all undergraduate and postgraduate programs. All prospective students are cordially invited to visit the Kushtia campus or apply directly online.</p><p>Admission Hotline: +8801302991188 | Email: admission@rmu.ac.bd</p>', 'downloads/admission_fair_circular.pdf', '2026-09-30', 1, 1, 499, '2026-10-17 12:39:47'),
(2, 'mansur-ali-scholarship', 'Notice: Mansur Ali and Atharunnesa Trust Scholarship', '', 'Scholarship', '<p>Applications are invited from meritorious and economically challenged regular students of Rabindra Maitree University for the Mansur Ali and Atharunnesa Trust Scholarship Fund. Interested students must submit the completed application form along with academic transcripts and family income certificates to the Registrar Office by November 15, 2026.</p>', 'downloads/scholarship_form.pdf', '2026-09-15', 1, 1, 218, '2026-10-17 12:39:47'),
(3, 'fazli-hossain-scholarship', 'Mohammad Fazli Hossain Memorial Trust Scholarship Circular', '', 'Scholarship', '<p>The Mohammad Fazli Hossain Memorial Trust Fund Scholarship will be awarded to students who achieve the highest CGPA in the faculties of Science, Engineering, and Arts. Eligible students must submit their applications through their respective department heads.</p>', NULL, '2026-08-20', 0, 1, 185, '2026-10-17 12:39:47'),
(4, 'hamim-khan-award', 'Notice Regarding Hamim Khan Memorial Cultural Award', '', 'Academic', '<p>The Hamim Khan Memorial Award will be conferred upon students in recognition of outstanding contributions to literature, visual arts, and cultural activities at the university. For detailed submission guidelines, please contact the Registrar Office.</p>', NULL, '2026-07-12', 0, 1, 142, '2026-10-17 12:39:47'),
(5, 'mujibnagar-day-celebration', 'Celebration of Historic Mujibnagar Day at RMU Campus', '', 'General', '<p>The Historic Mujibnagar Day will be observed with due solemnity at Rabindra Maitree University Kushtia Campus. All faculty members, students, officers, and staff are requested to attend the commemorative seminar at the Central Auditorium.</p>', NULL, '2026-04-16', 0, 1, 203, '2026-10-17 12:39:47');

--
-- Dumping data for table `events` (3 rows)
--
INSERT INTO `events` (`id`, `slug`, `title_en`, `title_bn`, `event_date`, `event_time`, `location`, `description`, `image_url`, `is_featured`, `is_active`, `created_at`) VALUES
(1, 'fall-2026-orientation', 'Fall 2026 Freshers Orientation & Welcome Gala', '', '2026-11-10', '10:00 AM - 3:00 PM', 'RMU Central Auditorium, Kushtia', 'Welcoming all newly admitted students of Fall 2026 semester with inspirational addresses from the Vice-Chancellor, deans, and a vibrant cultural showcase.', NULL, 1, 1, '2026-10-17 12:39:47'),
(2, 'national-rabindra-conference', 'National Rabindra Conference & Literary Fest', '', '2026-12-05', '09:30 AM - 5:30 PM', 'Rabindra Moncho, Kushtia Campus', 'A two-day academic symposium bringing together eminent litterateurs, researchers, and Tagore scholars from across Bangladesh and abroad.', NULL, 1, 1, '2026-10-17 12:39:47'),
(3, 'rmu-tech-carnival-2026', 'RMU Annual Tech Carnival & Project Showcase', '', '2026-12-18', '10:00 AM - 4:00 PM', 'Engineering Building & Computer Labs', 'Showcasing student engineering innovations in Robotics, Web Apps, IoT, and competitive programming hackathon.', NULL, 1, 1, '2026-10-17 12:39:47');

--
-- Dumping data for table `news` (3 rows)
--
INSERT INTO `news` (`id`, `slug`, `title_en`, `title_bn`, `published_date`, `summary`, `content`, `image_url`, `is_featured`, `is_active`, `created_at`) VALUES
(1, 'admission-fair-opening-2026', 'Grand Opening of Admission Fair Fall 2026 at RMU', '', '2026-09-30', 'The Fall 2026 admission fair kicked off with wide participation from prospective students, parents, and academic counselors at the Kushtia campus.', '<p>The Fall 2026 admission fair was formally inaugurated by university leadership. The Vice-Chancellor emphasized the university commitment to quality education, state-of-the-art laboratory facilities, and affordable tuition with merit-based waivers. Numerous students enrolled on the first day across CSE, EEE, BBA, English, and Agriculture.</p>', NULL, 1, 1, '2026-10-17 12:39:47'),
(2, 'research-symposium-held', 'International Seminar on Sustainable Agricultural Innovations', '', '2026-09-12', 'Faculty of Agriculture hosted researchers and agricultural specialists to discuss modern climate-resilient farming techniques.', '<p>A seminar on climate adaptation in rural farming was successfully hosted by the Department of Agriculture at Rabindra Maitree University. Keynote speakers presented findings on soil conservation, smart irrigation, and high-yield bio-fortified crop varieties.</p>', NULL, 0, 1, '2026-10-17 12:39:47'),
(3, 'ugc-academic-accreditation-visit', 'UGC High-Powered Delegation Commends RMU Campus Infrastructure', '', '2026-08-14', 'Delegates from the University Grants Commission visited laboratory facilities, library resources, and digital classrooms at RMU Kushtia.', '<p>During an official inspection, UGC representatives praised the modern campus infrastructure, expansive seminar library, and well-equipped engineering labs of Rabindra Maitree University, reaffirming UGC regulatory compliance and academic standards.</p>', NULL, 0, 1, '2026-10-17 12:39:47');

--
-- Dumping data for table `downloads` (5 rows)
--
INSERT INTO `downloads` (`id`, `title`, `category`, `file_name`, `file_path`, `file_size`, `download_count`, `is_active`, `created_at`) VALUES
(1, 'Undergraduate Admission Application Form 2026', 'Admission', 'rmu_undergrad_admission_form.pdf', 'storage/downloads/rmu_undergrad_admission_form.pdf', '850 KB', 420, 1, '2026-10-17 12:39:47'),
(2, 'RMU Academic Calendar 2026 (All Semesters)', 'Academic', 'rmu_academic_calendar_2026.pdf', 'storage/downloads/rmu_academic_calendar_2026.pdf', '620 KB', 315, 1, '2026-10-17 12:39:47'),
(3, 'Merit & Need-Based Scholarship Application Form', 'Admission', 'rmu_scholarship_application_form.pdf', 'storage/downloads/rmu_scholarship_application_form.pdf', '480 KB', 188, 1, '2026-10-17 12:39:47'),
(4, 'Undergraduate Course Catalog & Fee Structure Guide', 'Admission', 'rmu_course_catalog_fee_structure.pdf', 'storage/downloads/rmu_course_catalog_fee_structure.pdf', '1.4 MB', 560, 1, '2026-10-17 12:39:47'),
(5, 'Transcript & Degree Verification Request Form', 'Examination', 'rmu_transcript_request_form.pdf', 'storage/downloads/rmu_transcript_request_form.pdf', '390 KB', 95, 1, '2026-10-17 12:39:47');

--
-- Dumping data for table `contact_messages` (2 rows)
--
INSERT INTO `contact_messages` (`id`, `full_name`, `email`, `phone`, `subject`, `message`, `is_read`, `replied`, `ip_address`, `created_at`) VALUES
(1, 'Md. Shafiul Islam', 'shafiul.student@gmail.com', '01710998877', 'Inquiry regarding CSE Evening Batch', 'Assalamu Alaikum, I am an employed diploma graduate interested in enrolling in the BSc in CSE program. Does RMU offer evening or weekend classes for diploma holders?', 0, 0, '127.0.0.1', '2026-10-17 12:39:47'),
(2, 'Nusrat Jahan', 'nusrat.jahan@yahoo.com', '01823456789', 'Tuition Fee Waiver Inquiry for Fall 2026', 'Hello, I obtained GPA 5.00 in both SSC and HSC. What percentage of tuition waiver am I eligible to receive at Rabindra Maitree University?', 1, 1, '127.0.0.1', '2026-10-17 12:39:47');

--
-- Dumping data for table `student_portal_notices` (4 rows)
--
INSERT INTO `student_portal_notices` (`id`, `title`, `category`, `content`, `is_pinned`, `published_at`) VALUES
(13, 'Mid-Term Examination Schedule: Fall 2026', 'Exam', 'All undergraduate students are hereby notified that the Mid-Term Examination of Fall 2026 semester will commence from November 22, 2026. Detailed routine is published on the portal.', 1, '2026-11-04 01:51:13'),
(14, 'Admit Card Distribution for Upcoming Examinations', 'Academic', 'Students with 75% or higher class attendance and cleared semester dues can download their digital Admit Cards directly from the Student Portal starting November 10, 2026.', 1, '2026-11-04 01:51:13'),
(15, 'Library Extended Hours for Final Exam Preparations', 'General', 'The Central Library and digital reading archives will remain open until 08:00 PM every evening to facilitate exam preparation.', 0, '2026-11-04 01:51:13'),
(16, 'Fee Waiver & Merit Scholarship Application for Spring 2027', 'Payment', 'Students with CGPA 3.80 or above are invited to apply for the Rabindra Merit Waiver for the upcoming Spring 2027 academic session.', 0, '2026-11-04 01:51:13');

--
-- Dumping data for table `admission_sequences` (1 rows)
--
INSERT INTO `admission_sequences` (`seq_year`, `last_app_seq`, `last_roll_seq`, `updated_at`) VALUES
(2026, 25, 32, '2026-11-12 01:47:14');

--
-- Dumping data for table `admission_id_reservations` (10 rows)
--
INSERT INTO `admission_id_reservations` (`id`, `draft_token`, `session_key`, `seq_year`, `application_id`, `roll_number`, `status`, `committed_admission_id`, `expires_at`, `created_at`, `updated_at`) VALUES
(135, '2d3ea7b63e3fd2f9e3fb90071596fead114a9ebea98e10d8', 'db495783560ec327a0ac59060810daf5', 2026, 'RMU-2026-0016', '2026-0019', 'reserved', NULL, '2026-11-12 12:29:59', '2026-11-12 00:29:59', '2026-11-12 00:29:59'),
(141, 'c6070e357609a751009bb6a6d74aadca22b7f57fc3fc1e35', '82a622e2c84802e1bb0771eabbc69d68', 2026, 'RMU-2026-0017', '2026-0021', 'reserved', NULL, '2026-11-12 12:32:01', '2026-11-12 00:31:39', '2026-11-12 00:32:01'),
(150, '2e10337a8342a087e6d226a48d1b23424da8946f88500e74', 'e443d1fe732600e99a694e9109120e37', 2026, 'RMU-2026-0018', '2026-0022', 'reserved', NULL, '2026-11-12 12:44:37', '2026-11-12 00:44:37', '2026-11-12 00:44:37'),
(156, '5972937eed5a81bb144eaeacbc120fc276cfe39a7c7ca0b4', 'e3ac34a6a3403ba4fa1d22c373767301', 2026, 'RMU-2026-0019', '2026-0024', 'reserved', NULL, '2026-11-12 12:46:13', '2026-11-12 00:45:51', '2026-11-12 00:46:13'),
(157, 'b2c15ec75229b2788bf88e93660ffda8c42b182e5dae8e1f', '07445ebed4c77151076e91317248891d', 2026, 'RMU-2026-0020', '2026-0025', 'reserved', NULL, '2026-11-12 13:13:56', '2026-11-12 01:13:56', '2026-11-12 01:13:56'),
(166, 'a89776ae9008c2ea33f43f961aeffb693bf3ce0f2ec80f53', '4d2772c400ea6242d7d26e909d098031', 2026, 'RMU-2026-0021', '2026-0026', 'reserved', NULL, '2026-11-12 13:26:38', '2026-11-12 01:26:38', '2026-11-12 01:26:38'),
(172, '87d64ef1c6e5768c8ebf8128886dbb5a446531e5800b0b80', 'a506bac4914f4617ab2fd0d0ee31d946', 2026, 'RMU-2026-0022', '2026-0028', 'reserved', NULL, '2026-11-12 13:27:45', '2026-11-12 01:27:45', '2026-11-12 01:27:45'),
(173, 'f808d6e4c5c1db873a04e143ea2437a3f9560e00335d9698', '7cee52e30e564c40df2dd44c2603325e', 2026, 'RMU-2026-0023', '2026-0029', 'reserved', NULL, '2026-11-12 13:37:32', '2026-11-12 01:37:32', '2026-11-12 01:37:32'),
(182, 'd2dcba09346bc7b53d4890ff6b92d3bb7ec404c7d0214e81', '4978bfcd941a4e8af95780e1b635c03e', 2026, 'RMU-2026-0024', '2026-0030', 'reserved', NULL, '2026-11-12 13:40:19', '2026-11-12 01:40:19', '2026-11-12 01:40:19'),
(188, '94d084bf31a0146a0eb6fecc69113a48f15ce3c0e35640c0', '79d67ced149bcbd6a14e265655994c49', 2026, 'RMU-2026-0025', '2026-0032', 'reserved', NULL, '2026-11-12 13:47:14', '2026-11-12 01:47:14', '2026-11-12 01:47:14');

--
-- Dumping data for table `admissions` (1 rows)
--
INSERT INTO `admissions` (`id`, `application_ref`, `application_id`, `application_year`, `full_name`, `father_name`, `mother_name`, `date_of_birth`, `gender`, `mobile`, `email`, `present_address`, `permanent_address`, `academic_session`, `department_id`, `program_id`, `last_college`, `ssc_gpa`, `hsc_gpa`, `home_district`, `hsc_group`, `interested_subject`, `degree_type`, `duration_years`, `course_duration`, `total_semesters`, `quota`, `status`, `admin_notes`, `ip_address`, `created_at`, `updated_at`, `accepted_at`, `accepted_by`, `ssc_exam`, `ssc_roll`, `ssc_reg_no`, `ssc_board`, `ssc_result_type`, `ssc_group`, `ssc_year`, `hsc_exam`, `hsc_roll`, `hsc_reg_no`, `hsc_board`, `hsc_result_type`, `hsc_year`, `passing_year`, `expected_passing_year`, `historical_record_approved`, `historical_approved_by`, `historical_approved_at`, `historical_approval_note`, `original_submission_json`, `grad_applicable`, `grad_exam`, `grad_university`, `grad_subject`, `grad_result_type`, `grad_result`, `grad_year`, `grad_duration`, `payment_status`, `payment_gateway_mode`, `payment_amount`, `payment_method`, `transaction_id`, `sender_number`, `payment_screenshot`, `paid_at`, `student_id`, `roll_number`, `portal_password`, `exam_roll`, `exam_date`, `exam_center`, `photo`, `academic_documents`, `verification_token`, `pdf_path`, `pdf_generated_at`) VALUES
(601, 'RMU-2026-0015', 'RMU-2026-0015', 2026, 'amir hasan', 'Md. Test Father', 'Mrs. Test Mother', '2000-05-20', 'Male', '01732812678', 'mdr06031@gmail.com', 'fhhhghfhh', 'hhhhgfg', 'Fall 2026', 1, 6, 'HJHJ', '3.50', '3.33', 'gvcfb', 'Science', 'English — BA (Honours)', 'Bachelor / Honours', '4.0', '4 Years (8 Semesters)', 8, 'NO', 'Accepted', 'Application accepted by admissions committee.', '127.0.0.1', '2026-11-12 00:16:40', '2026-11-12 01:46:15', '2026-11-12 00:17:46', 1, 'S.S.C', 6587854, NULL, 'Dinajpur', 'GPA(out of 5)', 'Science', 2011, 'H.S.C', 74527456, NULL, 'Dinajpur', 'GPA(out of 5)', 2014, 2025, 2030, 0, NULL, NULL, NULL, '{\"application_year\":2026,\"program_id\":6,\"interested_subject\":\"English — BA (Honours)\",\"degree_type\":\"Bachelor \\/ Honours\",\"academic_session\":\"Fall 2026\",\"duration_years\":4,\"course_duration\":\"4 Years (8 Semesters)\",\"total_semesters\":8,\"expected_passing_year\":2030,\"passing_year\":\"2025\",\"submitted_at\":\"2026-11-12 00:16:40\"}', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Paid', 'sandbox', '150.00', 'bKash', 'SBK2611121E0A56AD', '01732812678', 'receipt_RMU20260015_1794421016_839.svg', '2026-11-12 00:16:56', NULL, '2026-0018', NULL, '2026-0018', 'Saturday, 15 November 2026 at 10:00 AM', 'RMU Permanent Campus, Kushtia', NULL, NULL, 'a909520939c6252a21e2eac9cbc269a476d1fc2b', 'storage/admissions/pdf/application_RMU-2026-0015_601.pdf', '2026-11-12 00:17:48');

--
-- Dumping data for table `admission_payment_transactions` (1 rows)
--
INSERT INTO `admission_payment_transactions` (`id`, `admission_id`, `application_ref`, `gateway_session_id`, `idempotency_key`, `transaction_id`, `gateway_provider`, `gateway_mode`, `amount`, `paid_amount`, `currency`, `sender_number`, `sandbox_otp`, `attempt_count`, `gateway_status`, `status`, `verification_signature`, `gateway_nonce`, `gateway_response_json`, `failure_reason`, `authorized_at`, `validated_once_at`, `verified_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(27, 601, 'RMU-2026-0015', 'RMUSBX-20261112001647-7ADBFDD77A', 'idem_caab40ab3fbf5da93de8217f545c3042', 'SBK2611121E0A56AD', 'bKash', 'sandbox', '150.00', '150.00', 'BDT', '01732812678', 656337, 0, 'VERIFIED', 'verified', 'e6d39e42f855a86f2b2063ba522443927b400226cec099e9a71070b35e1c8747', 'bdfa1fe2d89636251e50556157222a0e', '{\"gateway\":\"RMU Sandbox Payment Gateway\",\"provider\":\"bKash\",\"mode\":\"sandbox\",\"merchant_account\":\"01336367336\",\"gateway_session_id\":\"RMUSBX-20261112001647-7ADBFDD77A\",\"transaction_id\":\"SBK2611121E0A56AD\",\"application_ref\":\"RMU-2026-0015\",\"sender_number\":\"01732812678\",\"amount\":\"150.00\",\"currency\":\"BDT\",\"gateway_status\":\"AUTHORIZED\",\"authorized_at\":\"2026-11-12T00:16:56+06:00\"}', NULL, '2026-11-12 00:16:56', '2026-11-12 00:16:56', '2026-11-12 00:16:56', '2026-11-12 00:31:47', '2026-11-12 00:16:47', '2026-11-12 00:16:56');

--
-- Dumping data for table `students` (1 rows)
--
INSERT INTO `students` (`id`, `student_id`, `roll_number`, `application_number`, `username`, `full_name`, `father_name`, `mother_name`, `email`, `phone`, `password_hash`, `password_updated_at`, `password_updated_by`, `password_source`, `must_change_password`, `program_id`, `degree_type`, `department_id`, `batch`, `academic_session`, `duration_years`, `program_duration`, `total_semesters`, `current_year`, `current_semester`, `cgpa`, `result_publication_date`, `result_status`, `results_visible`, `result_access_status`, `result_suspended_reason`, `results_hidden_reason`, `credits_completed`, `total_credits_required`, `blood_group`, `dob`, `gender`, `home_district`, `passing_year`, `expected_passing_year`, `historical_record_approved`, `historical_approved_by`, `historical_approved_at`, `historical_approval_note`, `present_address`, `permanent_address`, `status`, `session_version`, `avatar`, `created_at`, `updated_at`, `deactivated_at`, `deactivated_by`, `deactivation_reason`, `deleted_at`, `deleted_by`, `deletion_reason`) VALUES
(4, 'RMU2601001', 'ROLL-0004', NULL, 'RMU2601001', 'Tanvir Hasan', NULL, NULL, 'tanvir.cse@rmu.ac.bd', '01712345678', '$2y$12$JnoQJBEMyUz8gene/Q/rgOkIS4RXvoAWKXRW3OrCOM/qW.evLSSoW', '2026-11-11 02:26:46', 2, 'admin_reset', 1, 1, 'Bachelor / Honours', 6, '12th Batch (2024–2028)', '12th Batch (2024–2028)', '4.0', '4 Years (8 Semesters)', 8, '4th Year', '8th Semester (Final)', '3.90', '2026-11-11', 'Published', 1, 'Active', NULL, NULL, 84, 170, 'B+', '2004-05-15', 'Male', 'Kushtia', NULL, 2028, 0, NULL, NULL, NULL, NULL, NULL, 'Graduated', 2, 'student_RMU2601001_1794342456.jpg', '2026-11-04 01:51:12', '2026-11-11 02:27:36', NULL, NULL, NULL, NULL, NULL, NULL);

--
-- Dumping data for table `student_courses` (5 rows)
--
INSERT INTO `student_courses` (`id`, `student_id`, `course_code`, `course_name`, `credit`, `instructor_name`, `class_schedule`, `room_no`, `attendance_percent`, `semester`, `status`, `created_at`) VALUES
(283, 4, 'CSE 201', 'Data Structures & Algorithms', '3.0', 'Dr. Md. Nazmul Islam (Professor & Head)', 'Sun & Tue: 10:00 AM – 11:30 AM', 'Lab 203 (Engineering Bldg)', 94, 'Fall 2026', 'Enrolled', '2026-11-04 01:51:12'),
(284, 4, 'CSE 202', 'Data Structures & Algorithms Lab', '1.5', 'Engr. Rakibul Hasan (Lecturer)', 'Sun: 02:00 PM – 05:00 PM', 'Computer Lab 1', 96, 'Fall 2026', 'Enrolled', '2026-11-04 01:51:12'),
(285, 4, 'CSE 203', 'Object-Oriented Programming (Java)', '3.0', 'Ms. Tasnim Tabassum (Assistant Professor)', 'Mon & Wed: 11:30 AM – 01:00 PM', 'Room 304', 90, 'Fall 2026', 'Enrolled', '2026-11-04 01:51:12'),
(286, 4, 'CSE 204', 'Digital Logic Design & Microprocessors', '3.0', 'Dr. Farhana Yasmin (Associate Professor)', 'Mon & Wed: 09:30 AM – 11:00 AM', 'Hardware Lab 102', 88, 'Fall 2026', 'Enrolled', '2026-11-04 01:51:12'),
(287, 4, 'MAT 201', 'Discrete Mathematics & Graph Theory', '3.0', 'Mr. Tariqul Anam (Senior Lecturer)', 'Thu: 10:00 AM – 01:00 PM', 'Room 205', 92, 'Fall 2026', 'Enrolled', '2026-11-04 01:51:12');

--
-- Dumping data for table `student_fees` (7 rows)
--
INSERT INTO `student_fees` (`id`, `student_id`, `fee_title`, `amount`, `due_date`, `status`, `transaction_id`, `paid_at`, `created_at`) VALUES
(30, 4, 'Fall 2026 Semester Tuition & Lab Fee', '34500.00', '2026-11-15', 'Paid', 'RMU-PAY-2026-98124', '2026-10-05 11:20:00', '2026-11-04 01:51:13'),
(31, 4, 'Spring 2026 Semester Tuition & Exam Fee', '32000.00', '2026-04-10', 'Paid', 'RMU-PAY-2026-44102', '2026-03-28 14:15:00', '2026-11-04 01:51:13'),
(32, 4, 'Fall 2025 Semester Tuition Fee', '32000.00', '2025-10-15', 'Paid', 'RMU-PAY-2025-87211', '2025-10-02 09:40:00', '2026-11-04 01:51:13'),
(33, 4, 'Spring 2025 Admission & 1st Semester Fee', '45000.00', '2025-01-20', 'Paid', 'RMU-PAY-2025-10245', '2025-01-15 16:30:00', '2026-11-04 01:51:13'),
(35, 4, 'Mid-Term Examination & Lab Fee', '12500.00', '2026-11-04', 'Paid', 'RMU-TXN-497215', '2026-11-04 02:12:13', '2026-11-04 01:53:00'),
(36, 4, 'Library & Activity Fee', '3500.00', '2026-11-04', 'Failed', 'RMU-FAIL-999', NULL, '2026-11-04 01:53:00'),
(39, 4, 'Mid-Term Examination & Lab Fee', '12500.00', '2026-11-04', 'Pending', 'RMU-TXN-PENDING1', NULL, '2026-11-04 02:36:04');

--
-- Dumping data for table `student_notifications` (2 rows)
--
INSERT INTO `student_notifications` (`id`, `student_id`, `title`, `message`, `type`, `related_id`, `is_read`, `created_at`) VALUES
(623, 4, 'Academic Results Projected (Estimated)', 'Your 8-semester estimated results (12th Batch (2024–2028)) have been generated for cumulative CGPA 3.89. IMPORTANT: ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT.', 'result', NULL, 0, '2026-11-11 02:19:38'),
(626, 4, 'Student Portal Password Reset', 'Your Student Portal login password was reset by university administration and active sessions were signed out. Obtain your temporary password from the Registrar Office and update it after signing in.', 'account_status', NULL, 0, '2026-11-11 02:26:46');

--
-- Dumping data for table `grading_rules` (10 rows)
--
INSERT INTO `grading_rules` (`id`, `min_marks`, `max_marks`, `letter_grade`, `grade_point`, `remarks`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, '80.00', '100.00', 'A+', '4.00', 'Outstanding', 1, 1, '2026-11-04 00:09:55', '2026-11-04 00:09:55'),
(2, '75.00', '79.99', 'A', '3.75', 'Excellent', 1, 2, '2026-11-04 00:09:55', '2026-11-04 00:09:55'),
(3, '70.00', '74.99', 'A-', '3.50', 'Very Good', 1, 3, '2026-11-04 00:09:55', '2026-11-04 00:09:55'),
(4, '65.00', '69.99', 'B+', '3.25', 'Good', 1, 4, '2026-11-04 00:09:55', '2026-11-04 00:09:55'),
(5, '60.00', '64.99', 'B', '3.00', 'Satisfactory', 1, 5, '2026-11-04 00:09:55', '2026-11-04 00:09:55'),
(6, '55.00', '59.99', 'B-', '2.75', 'Above Average', 1, 6, '2026-11-04 00:09:55', '2026-11-04 00:09:55'),
(7, '50.00', '54.99', 'C+', '2.50', 'Average', 1, 7, '2026-11-04 00:09:55', '2026-11-04 00:09:55'),
(8, '45.00', '49.99', 'C', '2.25', 'Pass', 1, 8, '2026-11-04 00:09:55', '2026-11-04 00:09:55'),
(9, '40.00', '44.99', 'D', '2.00', 'Conditional Pass', 1, 9, '2026-11-04 00:09:55', '2026-11-04 00:09:55'),
(10, '0.00', '39.99', 'F', '0.00', 'Fail', 1, 10, '2026-11-04 00:09:55', '2026-11-04 00:09:55');

--
-- Dumping data for table `student_results` (64 rows)
--
INSERT INTO `student_results` (`id`, `student_id`, `semester`, `course_code`, `course_name`, `credit`, `full_marks`, `marks`, `letter_grade`, `grade_point`, `exam_term`, `academic_session`, `is_published`, `is_estimated`, `result_type`, `publish_status`, `published_at`, `publication_date`, `published_by`, `correction_reason`, `created_at`) VALUES
(188, 4, '1st Semester (Spring 2023)', 'CSE 101', 'Structured Programming in C', '3.0', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(189, 4, '1st Semester (Spring 2023)', 'CSE 102', 'Structured Programming Lab', '1.5', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(190, 4, '1st Semester (Spring 2023)', 'ENG 101', 'English Reading & Writing Skills', '3.0', 100, NULL, 'A', '3.75', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(191, 4, '1st Semester (Spring 2023)', 'MAT 101', 'Differential and Integral Calculus', '3.0', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(192, 4, '2nd Semester (Fall 2023)', 'CSE 103', 'Discrete Mathematics', '3.0', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(193, 4, '2nd Semester (Fall 2023)', 'CSE 104', 'Object Oriented Programming (Java)', '3.0', 100, NULL, 'A', '3.75', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(194, 4, '2nd Semester (Fall 2023)', 'PHY 101', 'Physics (Mechanics, Waves & Optics)', '3.0', 100, NULL, 'A', '3.75', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(195, 4, '2nd Semester (Fall 2023)', 'BAN 105', 'Bangla Language & Tagore Literary Studies', '2.0', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(196, 4, '3rd Semester (Spring 2024)', 'CSE 201', 'Data Structures & Algorithms', '3.0', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(197, 4, '3rd Semester (Spring 2024)', 'CSE 202', 'Data Structures & Algorithms Lab', '1.5', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(198, 4, '3rd Semester (Spring 2024)', 'CSE 203', 'Digital Logic Design & Microprocessors', '3.0', 100, NULL, 'A', '3.75', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(199, 4, '3rd Semester (Spring 2024)', 'MAT 102', 'Coordinate Geometry & Linear Algebra', '3.0', 100, NULL, 'A', '3.75', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(200, 4, '4th Semester (Fall 2024)', 'CSE 205', 'Database Management Systems', '3.0', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(201, 4, '4th Semester (Fall 2024)', 'CSE 206', 'Database Management Systems Lab', '1.5', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(202, 4, '4th Semester (Fall 2024)', 'CSE 207', 'Computer Architecture & Organization', '3.0', 100, NULL, 'A', '3.75', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(203, 4, '4th Semester (Fall 2024)', 'STA 201', 'Statistics & Probability for Engineers', '3.0', 100, NULL, 'A', '3.75', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(204, 4, '5th Semester (Spring 2025)', 'CSE 301', 'Software Engineering & System Analysis', '3.0', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(205, 4, '5th Semester (Spring 2025)', 'CSE 302', 'Operating Systems & System Programming', '3.0', 100, NULL, 'A', '3.75', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(206, 4, '5th Semester (Spring 2025)', 'CSE 303', 'Operating Systems Lab (Linux/Unix)', '1.5', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(207, 4, '5th Semester (Spring 2025)', 'CSE 304', 'Theory of Computation & Automata', '3.0', 100, NULL, 'A', '3.75', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(208, 4, '6th Semester (Fall 2025)', 'CSE 305', 'Computer Networks & Internet Protocols', '3.0', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(209, 4, '6th Semester (Fall 2025)', 'CSE 306', 'Computer Networks Laboratory', '1.5', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(210, 4, '6th Semester (Fall 2025)', 'CSE 307', 'Web Engineering & Full-Stack Development', '3.0', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(211, 4, '6th Semester (Fall 2025)', 'CSE 308', 'Microcontroller & Embedded Systems Lab', '1.5', 100, NULL, 'A', '3.75', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(212, 4, '7th Semester (Spring 2026)', 'CSE 401', 'Artificial Intelligence & Machine Learning', '3.0', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(213, 4, '7th Semester (Spring 2026)', 'CSE 402', 'Compiler Design & Construction', '3.0', 100, NULL, 'A', '3.75', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(214, 4, '7th Semester (Spring 2026)', 'CSE 403', 'Cyber Security & Cryptography', '3.0', 100, NULL, 'A', '3.75', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(215, 4, '7th Semester (Spring 2026)', 'CSE 499A', 'Capstone Project & Thesis (Part I)', '3.0', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(216, 4, '8th Semester (Fall 2026)', 'CSE 405', 'Cloud Computing & Distributed Systems', '3.0', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(217, 4, '8th Semester (Fall 2026)', 'CSE 406', 'Digital Image Processing & Vision', '3.0', 100, NULL, 'A', '3.75', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(218, 4, '8th Semester (Fall 2026)', 'CSE 499B', 'Capstone Project & Defense (Part II)', '3.0', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(219, 4, '8th Semester (Fall 2026)', 'MGT 401', 'Industrial Management & Professional Ethics', '2.0', 100, NULL, 'A+', '4.00', 'Semester Final Examination', NULL, 0, 0, 'Verified', 'Draft', NULL, NULL, NULL, NULL, '2026-11-04 01:51:17'),
(3933, 4, '1st Semester', 'CSE 101', 'Structured Programming Language (C)', '3.0', 100, '78.40', 'A', '3.75', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3934, 4, '1st Semester', 'CSE 102', 'Structured Programming Laboratory', '1.5', 100, '75.90', 'A', '3.75', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3935, 4, '1st Semester', 'MAT 101', 'Differential and Integral Calculus', '3.0', 100, '86.40', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3936, 4, '1st Semester', 'ENG 101', 'English Reading and Composition Skills', '3.0', 100, '83.90', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3937, 4, '2nd Semester', 'CSE 103', 'Discrete Mathematics for Computing', '3.0', 100, '88.30', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3938, 4, '2nd Semester', 'CSE 104', 'Object Oriented Programming (C++)', '3.0', 100, '78.40', 'A', '3.75', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3939, 4, '2nd Semester', 'CSE 105', 'Object Oriented Programming Lab', '1.5', 100, '88.80', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3940, 4, '2nd Semester', 'PHY 101', 'Physics for Engineers (Mechanics & Waves)', '3.0', 100, '88.20', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3941, 4, '3rd Semester', 'CSE 201', 'Data Structures & Algorithms', '3.0', 100, '76.30', 'A', '3.75', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3942, 4, '3rd Semester', 'CSE 202', 'Data Structures Laboratory', '1.5', 100, '77.30', 'A', '3.75', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3943, 4, '3rd Semester', 'CSE 203', 'Digital Logic Design & Circuits', '3.0', 100, '78.90', 'A', '3.75', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3944, 4, '3rd Semester', 'EEE 101', 'Basic Electrical Engineering & Circuit Analysis', '3.0', 100, '88.40', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3945, 4, '4th Semester', 'CSE 205', 'Design & Analysis of Algorithms', '3.0', 100, '81.20', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3946, 4, '4th Semester', 'CSE 206', 'Database Management Systems (DBMS)', '3.0', 100, '77.30', 'A', '3.75', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3947, 4, '4th Semester', 'CSE 207', 'Database Management Systems Lab', '1.5', 100, '83.20', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3948, 4, '4th Semester', 'MAT 201', 'Linear Algebra & Complex Variables', '3.0', 100, '83.70', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3949, 4, '5th Semester', 'CSE 301', 'Operating Systems & System Programming', '3.0', 100, '91.30', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3950, 4, '5th Semester', 'CSE 302', 'Operating Systems Laboratory', '1.5', 100, '91.70', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38');
INSERT INTO `student_results` (`id`, `student_id`, `semester`, `course_code`, `course_name`, `credit`, `full_marks`, `marks`, `letter_grade`, `grade_point`, `exam_term`, `academic_session`, `is_published`, `is_estimated`, `result_type`, `publish_status`, `published_at`, `publication_date`, `published_by`, `correction_reason`, `created_at`) VALUES
(3951, 4, '5th Semester', 'CSE 303', 'Microprocessors, Microcontrollers & Interfacing', '3.0', 100, '84.10', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3952, 4, '5th Semester', 'CSE 304', 'Software Engineering & System Architecture', '3.0', 100, '84.70', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3953, 4, '6th Semester', 'CSE 305', 'Computer Networks & TCP/IP Protocols', '3.0', 100, '75.40', 'A', '3.75', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3954, 4, '6th Semester', 'CSE 306', 'Computer Networks Laboratory', '1.5', 100, '79.20', 'A', '3.75', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3955, 4, '6th Semester', 'CSE 307', 'Theory of Computation & Automata', '3.0', 100, '80.70', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3956, 4, '6th Semester', 'CSE 308', 'Web Technologies & Distributed Cloud Systems', '3.0', 100, '77.00', 'A', '3.75', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3957, 4, '7th Semester', 'CSE 401', 'Artificial Intelligence & Machine Learning', '3.0', 100, '90.20', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3958, 4, '7th Semester', 'CSE 402', 'Artificial Intelligence Lab', '1.5', 100, '76.00', 'A', '3.75', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3959, 4, '7th Semester', 'CSE 403', 'Compiler Design & Syntax Analysis', '3.0', 100, '82.90', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3960, 4, '7th Semester', 'CSE 404', 'Cyber Security, Cryptography & Network Defense', '3.0', 100, '91.20', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3961, 4, '8th Semester', 'CSE 405', 'Cloud Computing & High Performance Architectures', '3.0', 100, '79.10', 'A', '3.75', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3962, 4, '8th Semester', 'CSE 406', 'Data Science & Big Data Analytics', '3.0', 100, '76.50', 'A', '3.75', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3963, 4, '8th Semester', 'CSE 407', 'Senior Capstone Project & Engineering Thesis', '4.0', 100, '87.50', 'A+', '4.00', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38'),
(3964, 4, '8th Semester', 'CSE 408', 'Comprehensive Academic Defense & Viva Voce', '1.0', 100, '78.70', 'A', '3.75', 'Semester Final Exam', '12th Batch (2024–2028)', 1, 1, 'Estimated', 'Published', '2026-11-11 02:19:38', '2026-11-11', 2, 'ESTIMATED / GENERATED — ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA 3.89', '2026-11-11 02:19:38');

--
-- Dumping data for table `student_semester_results` (8 rows)
--
INSERT INTO `student_semester_results` (`id`, `student_id`, `semester_number`, `semester_name`, `academic_session`, `credit_hours`, `semester_gpa`, `completion_status`, `result_type`, `is_published`, `published_at`, `created_at`, `updated_at`) VALUES
(29, 4, 1, '1st Semester', '12th Batch (2024–2028)', '10.5', '3.89', 'Completed', 'Estimated', 1, '2026-11-11 02:19:38', '2026-11-10 00:12:29', '2026-11-11 02:19:38'),
(30, 4, 2, '2nd Semester', '12th Batch (2024–2028)', '10.5', '3.93', 'Completed', 'Estimated', 1, '2026-11-11 02:19:38', '2026-11-10 00:12:29', '2026-11-11 02:19:38'),
(31, 4, 3, '3rd Semester', '12th Batch (2024–2028)', '10.5', '3.82', 'Completed', 'Estimated', 1, '2026-11-11 02:19:38', '2026-11-10 00:12:29', '2026-11-11 02:19:38'),
(32, 4, 4, '4th Semester', '12th Batch (2024–2028)', '10.5', '3.93', 'Completed', 'Estimated', 1, '2026-11-11 02:19:38', '2026-11-10 00:12:29', '2026-11-11 02:19:38'),
(33, 4, 5, '5th Semester', '12th Batch (2024–2028)', '10.5', '4.00', 'Completed', 'Estimated', 1, '2026-11-11 02:19:38', '2026-11-10 00:12:29', '2026-11-11 02:19:38'),
(34, 4, 6, '6th Semester', '12th Batch (2024–2028)', '10.5', '3.82', 'Completed', 'Estimated', 1, '2026-11-11 02:19:38', '2026-11-10 00:12:29', '2026-11-11 02:19:38'),
(35, 4, 7, '7th Semester', '12th Batch (2024–2028)', '10.5', '3.96', 'Completed', 'Estimated', 1, '2026-11-11 02:19:38', '2026-11-10 00:12:29', '2026-11-11 02:19:38'),
(36, 4, 8, '8th Semester', '12th Batch (2024–2028)', '11.0', '3.84', 'Completed', 'Estimated', 1, '2026-11-11 02:19:38', '2026-11-10 00:12:29', '2026-11-11 02:19:38');

-- =====================================================================
-- End of Rabindra Maitree University Database Dump
-- All 31 Tables & Seed Data Successfully Processed.
-- =====================================================================
SET FOREIGN_KEY_CHECKS = 1;
