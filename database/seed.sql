-- =======================================================
-- Rabindra Maitree University (RMU) Database Seed Data
-- Demonstrative Educational Dataset (English Language)
-- =======================================================

-- 1. Site Settings
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `setting_group`, `description`) VALUES
('site_name_en', 'Rabindra Maitree University', 'general', 'University English Name'),
('site_name_bn', 'Rabindra Maitree University', 'general', 'University Alternate Name'),
('site_tagline_en', 'A center of excellence in higher education and cultural enlightenment', 'general', 'University Tagline'),
('site_tagline_bn', 'Center of Higher Education and Cultural Awakening', 'general', 'Secondary Tagline'),
('established_year', '2015', 'general', 'Year Founded'),
('campus_address', '97/71, Ram Chandra Roy, Jahid Chowdhury Rd, Kushtia 7000, Bangladesh', 'contact', 'Campus Address'),
('contact_phone', '+8801302991188', 'contact', 'Primary Hotline'),
('contact_email', 'info@rmu.ac.bd', 'contact', 'Official Email Address'),
('admission_phone', '+8801302991188', 'contact', 'Admission Help Desk Phone'),
('facebook_url', 'https://www.facebook.com/rmukushtia', 'social', 'Official Facebook Page'),
('linkedin_url', 'https://www.linkedin.com/school/rmukushtia/', 'social', 'Official LinkedIn Page'),
('whatsapp_url', 'https://wa.me/8801302991188', 'social', 'Official WhatsApp Link'),
('admission_active_term', 'Fall 2026', 'admission', 'Current Active Admission Term'),
('admission_deadline', '2026-11-30', 'admission', 'Current Admission Deadline'),
('office_hours', 'Saturday – Thursday: 9:00 AM – 5:00 PM', 'general', 'Operating Hours');

-- 2. Initial Administrator
-- Username: admin
-- Email: admin@rmu.ac.bd
-- Password: Admin@RMU2026! (Hash generated via password_hash('Admin@RMU2026!', PASSWORD_BCRYPT))
INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `role`, `status`) VALUES
('admin', 'admin@rmu.ac.bd', '$2y$12$n74nkgtjoV.KsSZuriqGBe9rDZ790pX5SrqX57JzG022KFT7Asz9W', 'Chief Administrator', 'superadmin', 'active');

-- 3. Faculties
INSERT INTO `faculties` (`id`, `slug`, `name_en`, `name_bn`, `dean_name`, `overview`, `icon`, `sort_order`, `is_active`) VALUES
(1, 'arts-and-social-science', 'Faculty of Arts and Social Science', NULL, 'Prof. Dr. M. A. Karim', 'The Faculty of Arts and Social Science fosters critical thinking, cultural appreciation, and creative expression through rich curricula in languages, arts, and physical education.', 'fa-feather-alt', 1, 1),
(2, 'engineering-and-technology', 'Faculty of Engineering and Technology', NULL, 'Dr. Engr. S. R. Rahman', 'The Faculty of Engineering is committed to producing world-class engineers, computer scientists, and technologists equipped with modern laboratory training and research skills.', 'fa-laptop-code', 2, 1),
(3, 'business-studies', 'Faculty of Business Studies', NULL, 'Prof. Mohammad Hossain', 'Dedicated to cultivating innovative business leaders and ethical entrepreneurs who drive economic and social progress in an interconnected global market.', 'fa-briefcase', 3, 1),
(4, 'agriculture', 'Faculty of Agriculture', NULL, 'Dr. Farhana Yasmin', 'Advancing agricultural science, sustainable farming, and food security through field research and cutting-edge agro-technology.', 'fa-seedling', 4, 1);

-- 4. Departments
INSERT INTO `departments` (`id`, `faculty_id`, `slug`, `name_en`, `name_bn`, `code`, `head_name`, `email`, `phone`, `overview`, `sort_order`, `is_active`) VALUES
(1, 1, 'dept-english', 'Department of English', NULL, 'ENG', 'Prof. Shamsul Alam', 'english@rmu.ac.bd', '+8801302991188', 'Offering comprehensive study of English literature, linguistics, communication skills, and critical theory.', 1, 1),
(2, 1, 'dept-bangla', 'Department of Bangla', NULL, 'BNG', 'Dr. Anowar Hossain', 'bangla@rmu.ac.bd', '+8801302991188', 'Dedicated to the heritage of Bengali literature, philology, folklore, and the profound philosophy of Rabindranath Tagore.', 2, 1),
(3, 1, 'dept-fine-arts', 'Department of Fine Arts', NULL, 'FAR', 'Kazi Mahbubur Rahman', 'finearts@rmu.ac.bd', '+8801302991188', 'Nurturing visual creativity, painting, sculpture, and graphic artistry with studio workshops.', 3, 1),
(4, 1, 'dept-music', 'Department of Music', NULL, 'MUS', 'Rezwana Chowdhury', 'music@rmu.ac.bd', '+8801302991188', 'Promoting classical music, Rabindra Sangeet, Nazrul Geeti, and traditional folk music heritage.', 4, 1),
(5, 1, 'dept-physical-education', 'Department of Physical Education and Sports Science', NULL, 'PESS', 'Md. Rafiqul Islam', 'sports@rmu.ac.bd', '+8801302991188', 'Focusing on athletic training, sports physiology, physical fitness management, and kinesiology.', 5, 1),
(6, 2, 'dept-cse', 'Department of Computer Science and Engineering', NULL, 'CSE', 'Dr. Tariqul Islam', 'cse@rmu.ac.bd', '+8801302991188', 'Equipping students with expertise in software engineering, algorithms, AI, cloud computing, and cybersecurity.', 6, 1),
(7, 2, 'dept-eee', 'Department of Electrical and Electronic Engineering', NULL, 'EEE', 'Engr. Mahfuzur Rahman', 'eee@rmu.ac.bd', '+8801302991188', 'Focusing on power systems, telecommunications, microelectronics, and renewable energy.', 7, 1),
(8, 2, 'dept-ict', 'Department of Information and Communication Technology', NULL, 'ICT', 'Syed Nazmul Hasan', 'ict@rmu.ac.bd', '+8801302991188', 'Specializing in computer networks, telecommunications, web technologies, and systems integration.', 8, 1),
(9, 2, 'dept-microbiology', 'Department of Microbiology', NULL, 'MIC', 'Dr. Nusrat Jahan', 'microbiology@rmu.ac.bd', '+8801302991188', 'Investigating bacterial and viral genetics, immunology, pharmaceutical microbiology, and clinical research.', 9, 1),
(10, 3, 'dept-business-administration', 'Department of Business Administration', NULL, 'DBA', 'Dr. Mahmudul Hasan', 'dba@rmu.ac.bd', '+8801302991188', 'Delivering premier business education in finance, marketing, HRM, supply chain, and strategic management.', 10, 1),
(11, 4, 'dept-agriculture', 'Department of Agriculture', NULL, 'AGR', 'Dr. A. B. M. Faruq', 'agriculture@rmu.ac.bd', '+8801302991188', 'Conducting scientific training in crop science, soil fertility, plant pathology, and modern agro-enterprise.', 11, 1);

-- 5. Academic Programs (Official RMU Subject List - 11 Undergraduate & Postgraduate)
INSERT INTO `programs` (`id`, `department_id`, `slug`, `title_en`, `title_bn`, `degree_level`, `duration`, `total_credits`, `eligibility`, `description`, `tuition_fee`, `sort_order`, `is_active`) VALUES
(1, 2, 'ba-in-bangla', 'Bangla — BA (Honours)', NULL, 'Undergraduate', '4 Years (8 Semesters)', 128, 'Minimum GPA 2.50 in SSC & HSC from any academic stream.', 'Rich study of Bengali literary history, grammar, linguistics, and Rabindranath Tagore heritage.', 'BDT 1,80,000', 1, 1),
(2, 1, 'ba-in-english', 'English — BA (Honours)', NULL, 'Undergraduate', '4 Years (8 Semesters)', 128, 'Minimum GPA 2.50 in SSC & HSC from any background with good score in English.', 'Exploration of British, American, and World literature, critical theory, and professional English writing.', 'BDT 2,40,000', 2, 1),
(3, 4, 'ba-in-music', 'Music — BA (Honours)', NULL, 'Undergraduate', '4 Years (8 Semesters)', 124, 'Minimum GPA 2.50 in SSC & HSC with vocal or instrumental aptitude.', 'Rigorous vocal training, ragas, Tagore songs, Nazrul songs, and contemporary composition.', 'BDT 2,00,000', 3, 1),
(4, 3, 'ba-in-fine-arts', 'Fine Arts — BFA (Honours)', NULL, 'Undergraduate', '4 Years (8 Semesters)', 132, 'Minimum GPA 2.50 in SSC & HSC with artistic portfolio or aptitude test.', 'Studio instruction in drawing, painting, sculpture, visual graphics, and Bangladesh art history.', 'BDT 2,20,000', 4, 1),
(5, 10, 'bba', 'Business Administration — BBA (Honours)', NULL, 'Undergraduate', '4 Years (8 Semesters)', 130, 'Minimum GPA 2.50 in SSC & HSC from Science, Commerce, or Humanities.', 'Core foundations in financial management, marketing strategies, organizational behavior, and entrepreneurship.', 'BDT 3,10,000', 5, 1),
(6, 11, 'bsc-in-agriculture', 'Agriculture — BSc (Honours)', NULL, 'Undergraduate', '4 Years (8 Semesters)', 160, 'Minimum GPA 2.50 in SSC & HSC with Science (Biology compulsory).', 'The Bachelor of Science (Honours) in Agriculture provides rigorous academic and field-based training in crop production, agronomy, soil science, plant pathology, agricultural biotechnology, and sustainable farm management. Students gain hands-on experience through modern research farm laboratories and agro-industrial field visits.', 'BDT 3,40,000 (Waiver up to 50% available)', 6, 1),
(7, 6, 'bsc-in-cse', 'Computer Science and Engineering (CSE)', NULL, 'Undergraduate', '4 Years (8 Semesters)', 148, 'Minimum GPA 2.50 in both SSC & HSC with Science background and Mathematics.', 'Comprehensive undergraduate engineering degree covering algorithms, systems, software engineering, databases, and AI.', 'BDT 3,80,000 (Waiver up to 50% available)', 7, 1),
(8, 8, 'bsc-in-ict', 'Information and Communication Technology (ICT)', NULL, 'Undergraduate', '4 Years (8 Semesters)', 144, 'Minimum GPA 2.50 in SSC & HSC from Science or relevant vocational background.', 'Focused on data communication, network engineering, cloud systems, and applied IT solutions.', 'BDT 3,50,000', 8, 1),
(9, 7, 'bsc-in-eee', 'Electrical and Electronic Engineering (EEE)', NULL, 'Undergraduate', '4 Years (8 Semesters)', 150, 'Minimum GPA 2.50 in SSC & HSC with Physics and Mathematics.', 'In-depth engineering curriculum in circuits, power transmission, communication systems, and embedded electronics.', 'BDT 3,75,000', 9, 1),
(10, 9, 'bsc-in-microbiology', 'Microbiology — BSc (Honours)', NULL, 'Undergraduate', '4 Years (8 Semesters)', 140, 'Minimum GPA 2.50 in SSC & HSC with Biology and Chemistry.', 'Hands-on laboratory training in molecular biology, clinical diagnostics, food safety, and biochemistry.', 'BDT 3,20,000', 10, 1),
(11, 5, 'physical-education-sports-science', 'Physical Education and Sports Science', NULL, 'Undergraduate', '4 Years (8 Semesters)', 128, 'Minimum GPA 2.50 in SSC & HSC from any background with physical fitness or athletic participation record.', 'Comprehensive undergraduate curriculum blending athletic training, sports physiology, kinesiology, biomechanics, exercise management, and sports pedagogy for future coaches, athletic directors, and physical educators.', 'BDT 1,90,000', 11, 1),
(12, 2, 'ma-in-bangla', 'MA in Bangla', NULL, 'Postgraduate', '1 Year (2 Semesters)', 36, 'BA in Bangla with minimum 2nd class or CGPA 2.50.', 'Specialized research in modern poetry, Rabindranath studies, and contemporary Bengali linguistics.', 'BDT 75,000', 1, 1),
(13, 10, 'mba', 'Master of Business Administration (MBA)', NULL, 'Postgraduate', '2 Years (60 Credits) / 1 Year for BBA holders', 60, 'Graduation in any discipline with minimum CGPA 2.50.', 'Executive management, strategic corporate finance, international trade, and organizational leadership.', 'BDT 1,60,000', 2, 1),
(14, 5, 'mped', 'Master of Physical Education (MPEd)', NULL, 'Postgraduate', '1 Year (2 Semesters)', 36, 'BPED or relevant graduation degree with sports credentials.', 'Advanced sports pedagogy, athletic administration, exercise physiology, and sports medicine.', 'BDT 85,000', 3, 1),
(15, 1, 'ma-in-english', 'MA in English', NULL, 'Postgraduate', '1 Year (2 Semesters)', 36, 'Bachelor degree in English with minimum 2nd class or CGPA 2.50.', 'Advanced literary discourse, applied linguistics, and research thesis in English studies.', 'BDT 90,000', 4, 1);

-- 6. Faculty Members
INSERT INTO `faculty_members` (`department_id`, `full_name`, `designation`, `email`, `phone`, `qualification`, `bio`, `sort_order`, `is_active`) VALUES
(6, 'Dr. Tariqul Islam', 'Associate Professor & Head', 'tariqul@rmu.ac.bd', '+8801302991188', 'PhD in Computer Science (BUET), MSc in CSE', 'Specializes in Distributed Systems, Artificial Intelligence, and Software Engineering methodologies.', 1, 1),
(6, 'Farhana Akter', 'Assistant Professor', 'farhana.cse@rmu.ac.bd', '+8801302991188', 'MSc in Computer Science & Engineering (DU)', 'Research interest in Data Mining, Natural Language Processing, and Machine Learning.', 2, 1),
(7, 'Engr. Mahfuzur Rahman', 'Assistant Professor & Head', 'mahfuz.eee@rmu.ac.bd', '+8801302991188', 'MSc in Electrical & Electronic Engineering (RUET)', 'Focuses on Renewable Solar Microgrids, Power Systems Analysis, and Industrial Automation.', 1, 1),
(1, 'Prof. Shamsul Alam', 'Professor & Head', 'shamsul.eng@rmu.ac.bd', '+8801302991188', 'PhD in English Literature (CU), MA in ELT', 'Renowned literary scholar focusing on Post-Colonial Literature, Shakespearean Drama, and Applied Linguistics.', 1, 1),
(10, 'Dr. Mahmudul Hasan', 'Associate Professor & Head', 'mahmud.dba@rmu.ac.bd', '+8801302991188', 'PhD in Finance (DU), MBA in Banking', 'Expert in Corporate Governance, Behavioral Finance, and SME Development.', 1, 1);

-- 7. Notices (Pure English)
INSERT INTO `notices` (`slug`, `title_en`, `title_bn`, `category`, `content`, `file_path`, `published_date`, `is_pinned`, `is_active`, `view_count`) VALUES
('admission-fair-fall-2026', 'Grand Opening of Admission Fair Fall 2026 at RMU', NULL, 'Admission', '<p>The Grand Opening of the Fall 2026 Semester Admission Fair was held with great enthusiasm at Rabindra Maitree University. During the admission fair, special waivers on admission fees and tuition fees are being offered across all undergraduate and postgraduate programs. All prospective students are cordially invited to visit the Kushtia campus or apply directly online.</p><p>Admission Hotline: +8801302991188 | Email: admission@rmu.ac.bd</p>', 'downloads/admission_fair_circular.pdf', '2026-09-30', 1, 1, 342),
('mansur-ali-scholarship', 'Notice: Mansur Ali and Atharunnesa Trust Scholarship', NULL, 'Scholarship', '<p>Applications are invited from meritorious and economically challenged regular students of Rabindra Maitree University for the Mansur Ali and Atharunnesa Trust Scholarship Fund. Interested students must submit the completed application form along with academic transcripts and family income certificates to the Registrar Office by November 15, 2026.</p>', 'downloads/scholarship_form.pdf', '2026-09-15', 1, 1, 218),
('fazli-hossain-scholarship', 'Mohammad Fazli Hossain Memorial Trust Scholarship Circular', NULL, 'Scholarship', '<p>The Mohammad Fazli Hossain Memorial Trust Fund Scholarship will be awarded to students who achieve the highest CGPA in the faculties of Science, Engineering, and Arts. Eligible students must submit their applications through their respective department heads.</p>', NULL, '2026-08-20', 0, 1, 185),
('hamim-khan-award', 'Notice Regarding Hamim Khan Memorial Cultural Award', NULL, 'Academic', '<p>The Hamim Khan Memorial Award will be conferred upon students in recognition of outstanding contributions to literature, visual arts, and cultural activities at the university. For detailed submission guidelines, please contact the Registrar Office.</p>', NULL, '2026-07-12', 0, 1, 142),
('mujibnagar-day-celebration', 'Celebration of Historic Mujibnagar Day at RMU Campus', NULL, 'General', '<p>The Historic Mujibnagar Day will be observed with due solemnity at Rabindra Maitree University Kushtia Campus. All faculty members, students, officers, and staff are requested to attend the commemorative seminar at the Central Auditorium.</p>', NULL, '2026-04-16', 0, 1, 195);

-- 8. Events (Pure English)
INSERT INTO `events` (`slug`, `title_en`, `title_bn`, `event_date`, `event_time`, `location`, `description`, `is_featured`, `is_active`) VALUES
('fall-2026-orientation', 'Fall 2026 Freshers Orientation & Welcome Gala', NULL, '2026-11-10', '10:00 AM - 3:00 PM', 'RMU Central Auditorium, Kushtia', 'Welcoming all newly admitted students of Fall 2026 semester with inspirational addresses from the Vice-Chancellor, deans, and a vibrant cultural showcase.', 1, 1),
('national-rabindra-conference', 'National Rabindra Conference & Literary Fest', NULL, '2026-12-05', '09:30 AM - 5:30 PM', 'Rabindra Moncho, Kushtia Campus', 'A two-day academic symposium bringing together eminent litterateurs, researchers, and Tagore scholars from across Bangladesh and abroad.', 1, 1),
('rmu-tech-carnival-2026', 'RMU Annual Tech Carnival & Project Showcase', NULL, '2026-12-18', '10:00 AM - 4:00 PM', 'Engineering Building & Computer Labs', 'Showcasing student engineering innovations in Robotics, Web Apps, IoT, and competitive programming hackathon.', 1, 1);

-- 9. News (Pure English)
INSERT INTO `news` (`slug`, `title_en`, `title_bn`, `published_date`, `summary`, `content`, `is_featured`, `is_active`) VALUES
('admission-fair-opening-2026', 'Grand Opening of Admission Fair Fall 2026 at RMU', NULL, '2026-09-30', 'The Fall 2026 admission fair kicked off with wide participation from prospective students, parents, and academic counselors at the Kushtia campus.', '<p>The Fall 2026 admission fair was formally inaugurated by university leadership. The Vice-Chancellor emphasized the university commitment to quality education, state-of-the-art laboratory facilities, and affordable tuition with merit-based waivers. Numerous students enrolled on the first day across CSE, EEE, BBA, English, and Agriculture.</p>', 1, 1),
('research-symposium-held', 'International Seminar on Sustainable Agricultural Innovations', NULL, '2026-09-12', 'Faculty of Agriculture hosted researchers and agricultural specialists to discuss modern climate-resilient farming techniques.', '<p>A seminar on climate adaptation in rural farming was successfully hosted by the Department of Agriculture at Rabindra Maitree University. Keynote speakers presented findings on soil conservation, smart irrigation, and high-yield bio-fortified crop varieties.</p>', 0, 1),
('ugc-academic-accreditation-visit', 'UGC High-Powered Delegation Commends RMU Campus Infrastructure', NULL, '2026-08-14', 'Delegates from the University Grants Commission visited laboratory facilities, library resources, and digital classrooms at RMU Kushtia.', '<p>During an official inspection, UGC representatives praised the modern campus infrastructure, expansive seminar library, and well-equipped engineering labs of Rabindra Maitree University, reaffirming UGC regulatory compliance and academic standards.</p>', 0, 1);

-- 10. Downloads
INSERT INTO `downloads` (`title`, `category`, `file_name`, `file_path`, `file_size`, `download_count`, `is_active`) VALUES
('Undergraduate Admission Application Form 2026', 'Admission', 'rmu_undergrad_admission_form.pdf', 'storage/downloads/rmu_undergrad_admission_form.pdf', '850 KB', 420, 1),
('RMU Academic Calendar 2026 (All Semesters)', 'Academic', 'rmu_academic_calendar_2026.pdf', 'storage/downloads/rmu_academic_calendar_2026.pdf', '620 KB', 315, 1),
('Merit & Need-Based Scholarship Application Form', 'Admission', 'rmu_scholarship_application_form.pdf', 'storage/downloads/rmu_scholarship_application_form.pdf', '480 KB', 188, 1),
('Undergraduate Course Catalog & Fee Structure Guide', 'Admission', 'rmu_course_catalog_fee_structure.pdf', 'storage/downloads/rmu_course_catalog_fee_structure.pdf', '1.4 MB', 560, 1),
('Transcript & Degree Verification Request Form', 'Examination', 'rmu_transcript_request_form.pdf', 'storage/downloads/rmu_transcript_request_form.pdf', '390 KB', 95, 1);

-- 11. Initial Admission Test Submissions (Demonstration records)
INSERT INTO `admissions` (`application_ref`, `full_name`, `mobile`, `email`, `last_college`, `ssc_gpa`, `hsc_gpa`, `home_district`, `hsc_group`, `interested_subject`, `quota`, `status`, `admin_notes`, `ip_address`) VALUES
('RMU-2026-1001', 'Anik Rahman', '01711223344', 'anik.rahman@example.com', 'Kushtia Govt College', 4.85, 4.70, 'Kushtia', 'Science', 'Computer Science and Engineering (CSE)', 'NO', 'Accepted', 'High academic score in Science. Recommended for 20% tuition waiver.', '127.0.0.1'),
('RMU-2026-1002', 'Farzana Yasmin', '01812345678', 'farzana.y@example.com', 'Meherpur Govt College', 4.50, 4.40, 'Meherpur', 'Humanities', 'English — BA (Honours)', 'NO', 'Under Review', 'Verified certificates. Interview scheduled for Monday.', '127.0.0.1'),
('RMU-2026-1003', 'Tanvir Ahmed', '01998765432', 'tanvir.ahmed@example.com', 'Chuadanga Govt College', 4.20, 4.10, 'Chuadanga', 'Commerce', 'Business Administration — BBA (Honours)', 'YES', 'Submitted', 'Freedom fighter quota claimed; awaiting supporting documentation.', '127.0.0.1');

-- 12. Contact Messages Demonstration
INSERT INTO `contact_messages` (`full_name`, `email`, `phone`, `subject`, `message`, `is_read`, `replied`, `ip_address`) VALUES
('Md. Shafiul Islam', 'shafiul.student@gmail.com', '01710998877', 'Inquiry regarding CSE Evening Batch', 'Assalamu Alaikum, I am an employed diploma graduate interested in enrolling in the BSc in CSE program. Does RMU offer evening or weekend classes for diploma holders?', 0, 0, '127.0.0.1'),
('Nusrat Jahan', 'nusrat.jahan@yahoo.com', '01823456789', 'Tuition Fee Waiver Inquiry for Fall 2026', 'Hello, I obtained GPA 5.00 in both SSC and HSC. What percentage of tuition waiver am I eligible to receive at Rabindra Maitree University?', 1, 1, '127.0.0.1');
