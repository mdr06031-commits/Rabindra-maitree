<?php
/**
 * Global Utility and Security Functions
 */

require_once __DIR__ . '/database.php';

/**
 * Escape HTML output to prevent XSS
 */
function e(?string $string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Generate CSRF Token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render CSRF hidden input
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify CSRF Token
 */
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get Site Setting from Database
 */
function get_setting(string $key, string $default = ''): string {
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        try {
            $db = getDb();
            $stmt = $db->query("SELECT setting_key, setting_value FROM site_settings");
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // fallback
        }
    }
    return $settings[$key] ?? $default;
}

/**
 * Convert string to URL slug
 */
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

/**
 * Format date for university notices and events
 */
function format_date(string $date, string $format = 'M d, Y'): string {
    if (empty($date) || $date === '0000-00-00') return '';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : $date;
}

/**
 * Flash messaging helpers
 */
function flash_set(string $type, string $message): void {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][$type] = $message;
}

function flash_get(string $type): ?string {
    if (isset($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

function flash_has(string $type): bool {
    return isset($_SESSION['flash'][$type]);
}

/**
 * Authentication and Role Checks
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['user_name'] ?? '',
        'full_name' => $_SESSION['user_fullname'] ?? '',
        'role'      => $_SESSION['user_role'] ?? '',
        'email'     => $_SESSION['user_email'] ?? ''
    ];
}

function require_login(): void {
    if (!is_logged_in()) {
        flash_set('error', 'Please log in to access the administrative dashboard.');
        header("Location: " . APP_URL . "/admin/login.php");
        exit;
    }
}

function require_role(array $allowedRoles): void {
    require_login();
    $role = $_SESSION['user_role'] ?? '';
    if (!in_array($role, $allowedRoles, true)) {
        flash_set('error', 'Access denied. You do not possess the required administrative clearance.');
        header("Location: " . APP_URL . "/admin/dashboard.php");
        exit;
    }
}

/**
 * Log Administrative Action
 */
function log_audit(string $action, string $details = ''): void {
    try {
        $db = getDb();
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $details, $ip]);
    } catch (Exception $e) {
        error_log("Failed to write audit log: " . $e->getMessage());
    }
}

/**
 * Bangladeshi Mobile Number Validation
 * Accepts 11 digits starting with 013-019
 */
function validate_bd_mobile(string $mobile): bool {
    $cleaned = preg_replace('/[^0-9]/', '', $mobile);
    if (str_starts_with($cleaned, '8801')) {
        $cleaned = '01' . substr($cleaned, 4);
    }
    return (bool)preg_match('/^01[3-9]\d{8}$/', $cleaned);
}

/**
 * Normalize Bangladeshi Mobile Number
 */
function normalize_bd_mobile(string $mobile): string {
    $cleaned = preg_replace('/[^0-9]/', '', $mobile);
    if (str_starts_with($cleaned, '8801')) {
        $cleaned = '01' . substr($cleaned, 4);
    }
    return $cleaned;
}

/**
 * Validate GPA (Between 1.00 and 5.00)
 */
function validate_gpa($gpa): bool {
    if (!is_numeric($gpa)) return false;
    $val = (float)$gpa;
    return $val >= 1.00 && $val <= 5.00;
}

/**
 * Generate Application Tracking Reference Number
 * Format: RMU-2026-XXXX
 */
function generate_application_ref(PDO $db): string {
    $year = date('Y');
    do {
        $randNum = mt_rand(1000, 9999);
        $ref = "RMU-{$year}-{$randNum}";
        $stmt = $db->prepare("SELECT COUNT(*) FROM admissions WHERE application_ref = ?");
        $stmt->execute([$ref]);
        $exists = $stmt->fetchColumn() > 0;
    } while ($exists);
    return $ref;
}

/**
 * Fetch Faculties with their Departments for Menus
 */
function get_faculties_menu(): array {
    static $tree = null;
    if ($tree !== null) return $tree;
    $tree = [];
    try {
        $db = getDb();
        $faculties = $db->query("SELECT id, slug, name_en, name_bn FROM faculties WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
        $deptStmt = $db->prepare("SELECT id, faculty_id, slug, name_en, name_bn, code FROM departments WHERE faculty_id = ? AND is_active = 1 ORDER BY sort_order ASC");
        foreach ($faculties as $f) {
            $deptStmt->execute([$f['id']]);
            $depts = $deptStmt->fetchAll();
            $tree[] = [
                'faculty'     => $f,
                'departments' => $depts
            ];
        }
    } catch (Exception $e) {
        // fallback empty
    }
    return $tree;
}

/**
 * Truncate words with ellipsis
 */
function truncate_words(string $text, int $limit = 25): string {
    $clean = strip_tags($text);
    $words = explode(' ', $clean);
    if (count($words) > $limit) {
        return implode(' ', array_slice($words, 0, $limit)) . '...';
    }
    return $clean;
}

/**
 * Fetch Categorized Academic Programs for Navigation Menu Dropdown
 */
function get_programs_menu(): array {
    static $menu = null;
    if ($menu !== null) return $menu;
    $menu = ['undergraduate' => [], 'postgraduate' => []];
    try {
        $db = getDb();
        $stmt = $db->query("SELECT id, slug, title_en, degree_level, duration FROM programs WHERE is_active = 1 ORDER BY FIELD(degree_level, 'Undergraduate', 'Postgraduate'), sort_order ASC");
        while ($row = $stmt->fetch()) {
            if ($row['degree_level'] === 'Undergraduate') {
                $menu['undergraduate'][] = $row;
            } else {
                $menu['postgraduate'][] = $row;
            }
        }
    } catch (Exception $e) {
        // fallback empty
    }
    return $menu;
}

/**
 * Official Academic Programs & 4-Year Subject Curriculums
 */
function get_all_programs_curriculum(): array {
    static $curriculums = null;
    if ($curriculums !== null) return $curriculums;

    $curriculums = [
        'ba-in-bangla' => [
            'id' => 1,
            'title' => 'BA (Honours) in Bangla',
            'slug' => 'ba-in-bangla',
            'department' => 'Department of Bangla',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'BAN 101', 'name' => 'History of Bangla Literature (Ancient & Medieval Periods)'],
                    ['code' => 'BAN 102', 'name' => 'Introduction to Bangla Linguistics, Phonetics & Grammar'],
                    ['code' => 'BAN 103', 'name' => 'Bangla Folklore, Heritage & Oral Literature'],
                    ['code' => 'BAN 104', 'name' => 'Bangla Prose Composition, Rhetoric & Prosody']
                ],
                '2nd Year' => [
                    ['code' => 'BAN 201', 'name' => 'Modern Bangla Literature (1801–1947: Renaissance to Partition)'],
                    ['code' => 'BAN 202', 'name' => '19th & 20th Century Bangla Poetry (Madhusudan to Jibanananda)'],
                    ['code' => 'BAN 203', 'name' => 'Evolution of Bangla Drama & Theatre Practices'],
                    ['code' => 'BAN 204', 'name' => 'Modern Bangla Prose, Essays & Periodicals']
                ],
                '3rd Year' => [
                    ['code' => 'BAN 301', 'name' => 'Rabindra Literature: Novels, Short Stories & Philosophy'],
                    ['code' => 'BAN 302', 'name' => 'Kazi Nazrul Islam: Rebel Aesthetics, Poetry & Music'],
                    ['code' => 'BAN 303', 'name' => 'Eastern & Western Literary Criticism and Aesthetics'],
                    ['code' => 'BAN 304', 'name' => 'Bangla Fiction & Modern Short Story Traditions']
                ],
                '4th Year' => [
                    ['code' => 'BAN 401', 'name' => 'Literature of Bangladesh Liberation War (1971) & Post-Independence Era'],
                    ['code' => 'BAN 402', 'name' => 'South Asian & Comparative Literature in Translation'],
                    ['code' => 'BAN 403', 'name' => 'Contemporary Bangla Fiction & Modern Poetry'],
                    ['code' => 'BAN 404', 'name' => 'Research Methodology, Fieldwork & Academic Monograph']
                ]
            ]
        ],
        'ba-in-english' => [
            'id' => 2,
            'title' => 'BA (Honours) in English',
            'slug' => 'ba-in-english',
            'department' => 'Department of English',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'ENG 101', 'name' => 'Introduction to English Poetry & Versification'],
                    ['code' => 'ENG 102', 'name' => 'Introduction to Prose & Non-Fictional Writing'],
                    ['code' => 'ENG 103', 'name' => 'Fundamentals of English Drama & Performance'],
                    ['code' => 'ENG 104', 'name' => 'Advanced English Language Skills, Phonetics & Composition']
                ],
                '2nd Year' => [
                    ['code' => 'ENG 201', 'name' => '16th & 17th Century English Poetry and Drama (Shakespeare & Marlowe)'],
                    ['code' => 'ENG 202', 'name' => '18th Century English Literature, Prose & Satire'],
                    ['code' => 'ENG 203', 'name' => 'Romantic Poetry (Wordsworth, Coleridge, Keats, Shelley, Byron)'],
                    ['code' => 'ENG 204', 'name' => 'Introduction to English Linguistics & Applied Syntax']
                ],
                '3rd Year' => [
                    ['code' => 'ENG 301', 'name' => 'Victorian Poetry and the Rise of the Victorian Novel'],
                    ['code' => 'ENG 302', 'name' => '20th Century Modernist Poetry and Fiction (Yeats, Eliot, Woolf, Joyce)'],
                    ['code' => 'ENG 303', 'name' => 'American Literature: Puritans to Postmodernists'],
                    ['code' => 'ENG 304', 'name' => 'History of Western Literary Theory & Classical Criticism']
                ],
                '4th Year' => [
                    ['code' => 'ENG 401', 'name' => 'Modern & Contemporary Critical Theory (Post-Structuralism to Cultural Studies)'],
                    ['code' => 'ENG 402', 'name' => 'Postcolonial Literatures in English (Africa, Caribbean, South Asia)'],
                    ['code' => 'ENG 403', 'name' => 'English Language Teaching (ELT) Methodology & Testing'],
                    ['code' => 'ENG 404', 'name' => 'Shakespeare: Selected Tragedies, Comedies & Critical Perspectives'],
                    ['code' => 'ENG 405', 'name' => 'Senior Seminar & Research Monograph']
                ]
            ]
        ],
        'ba-in-music' => [
            'id' => 3,
            'title' => 'BA (Honours) in Music',
            'slug' => 'ba-in-music',
            'department' => 'Department of Music',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'MUS 101', 'name' => 'Fundamentals of Classical Ragas, Swaras & Tala Systems'],
                    ['code' => 'MUS 102', 'name' => 'Rabindra Sangeet: Puja, Prem, Swadesh & Prakriti Stages'],
                    ['code' => 'MUS 103', 'name' => 'Folk Music Traditions of Greater Bengal (Baul, Bhatiali, Bhawaiya)'],
                    ['code' => 'MUS 104', 'name' => 'Voice Culture, Riyaz Techniques & Pitch Precision (Practical)']
                ],
                '2nd Year' => [
                    ['code' => 'MUS 201', 'name' => 'Khayal & Dhrupad Vocal Forms: Bandish & Improvised Alap'],
                    ['code' => 'MUS 202', 'name' => 'Nazrul Sangeet: Classical, Ragpradhan, Ghazal & Mass Songs'],
                    ['code' => 'MUS 203', 'name' => 'Bengali Musical Notation (Akarmatrik System) & History of Music'],
                    ['code' => 'MUS 204', 'name' => 'Traditional Folk Instruments: Harmonium, Tanpura, Tabla & Dotara']
                ],
                '3rd Year' => [
                    ['code' => 'MUS 301', 'name' => 'Advanced Ragas: Thumri, Dadra & Semi-Classical Expressions'],
                    ['code' => 'MUS 302', 'name' => 'Tagore Musical Dance Dramas (Chitrangada, Chandalika, Shyama)'],
                    ['code' => 'MUS 303', 'name' => 'Mystical Traditions: Lalon Shai, Hason Raja & Shah Abdul Karim'],
                    ['code' => 'MUS 304', 'name' => 'Composition Techniques, Western Harmony & World Music Traditions']
                ],
                '4th Year' => [
                    ['code' => 'MUS 401', 'name' => 'Modern Bengali Songs (Adhunik Gaan) & Recording Studio Artistry'],
                    ['code' => 'MUS 402', 'name' => 'Musicology, Aesthetics, Philosophy & Acoustics of Sound'],
                    ['code' => 'MUS 403', 'name' => 'Applied Music Technology & Digital Audio Workstations (DAW)'],
                    ['code' => 'MUS 404', 'name' => 'Graduation Solo Recital & Research Dissertation']
                ]
            ]
        ],
        'ba-in-fine-arts' => [
            'id' => 4,
            'title' => 'BFA (Honours) in Fine Arts',
            'slug' => 'ba-in-fine-arts',
            'department' => 'Department of Fine Arts',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'ART 101', 'name' => 'Fundamentals of Drawing, Object Study & Perspective Anatomy'],
                    ['code' => 'ART 102', 'name' => 'Color Theory, Chromatic Relationships & 2D Composition'],
                    ['code' => 'ART 103', 'name' => '3D Form, Spatial Structure & Clay Modeling Basics'],
                    ['code' => 'ART 104', 'name' => 'History of Eastern, Bengal & Ancient Asian Art Traditions']
                ],
                '2nd Year' => [
                    ['code' => 'ART 201', 'name' => 'Figurative & Portrait Drawing from Live Models'],
                    ['code' => 'ART 202', 'name' => 'Oil & Acrylic Painting Techniques: Still Life & Landscape'],
                    ['code' => 'ART 203', 'name' => 'Printmaking: Relief, Woodcut & Linocut Studio Workshop'],
                    ['code' => 'ART 204', 'name' => 'History of Western Art: Classical Antiquity to Impressionism']
                ],
                '3rd Year' => [
                    ['code' => 'ART 301', 'name' => 'Advanced Painting: Experimental Composition & Color Field'],
                    ['code' => 'ART 302', 'name' => 'Printmaking: Intaglio, Etching, Aquatint & Silk Screen Printing'],
                    ['code' => 'ART 303', 'name' => 'Sculpture Studio: Metal Fabrication, Terracotta & Wood Carving'],
                    ['code' => 'ART 304', 'name' => 'Modern Art Movements: Cubism, Surrealism & Abstract Expressionism']
                ],
                '4th Year' => [
                    ['code' => 'ART 401', 'name' => 'Contemporary Art Practices, Conceptual Art & Public Installations'],
                    ['code' => 'ART 402', 'name' => 'Curatorial Studies, Gallery Administration & Exhibition Design'],
                    ['code' => 'ART 403', 'name' => 'South Asian Contemporary Visual Culture & Digital Media Art'],
                    ['code' => 'ART 404', 'name' => 'Senior Studio Thesis, Graduation Exhibition & Artist Portfolio']
                ]
            ]
        ],
        'bba' => [
            'id' => 5,
            'title' => 'BBA (Honours) in Business Administration',
            'slug' => 'bba',
            'department' => 'Department of Business Administration',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'BBA 101', 'name' => 'Introduction to Modern Business & Organizations'],
                    ['code' => 'BBA 102', 'name' => 'Principles of Financial Accounting I & II'],
                    ['code' => 'BBA 103', 'name' => 'Microeconomics & Macroeconomic Principles'],
                    ['code' => 'BBA 104', 'name' => 'Business Mathematics & Commercial Computing Applications']
                ],
                '2nd Year' => [
                    ['code' => 'BBA 201', 'name' => 'Principles of Marketing & Consumer Behavior Analysis'],
                    ['code' => 'BBA 202', 'name' => 'Principles of Finance & Working Capital Management'],
                    ['code' => 'BBA 203', 'name' => 'Organizational Behavior & Human Resource Management'],
                    ['code' => 'BBA 204', 'name' => 'Business Statistics & Decision Analysis'],
                    ['code' => 'BBA 205', 'name' => 'Business Law, Contract Acts & Corporate Regulations']
                ],
                '3rd Year' => [
                    ['code' => 'BBA 301', 'name' => 'Managerial Accounting, Cost Estimation & Budgeting'],
                    ['code' => 'BBA 302', 'name' => 'Financial Markets, Banking Institutions & Credit Appraisal'],
                    ['code' => 'BBA 303', 'name' => 'Strategic Marketing, Brand Management & Supply Chain Logistics'],
                    ['code' => 'BBA 304', 'name' => 'Management Information Systems (MIS) & E-Commerce'],
                    ['code' => 'BBA 305', 'name' => 'Entrepreneurship, Innovation & Startup Incubation']
                ],
                '4th Year' => [
                    ['code' => 'BBA 401', 'name' => 'Strategic Corporate Management, Business Policy & Simulation'],
                    ['code' => 'BBA 402', 'name' => 'International Business Management & Global Trade'],
                    ['code' => 'BBA 403', 'name' => 'Business Ethics, CSR & Sustainable Business Practices'],
                    ['code' => 'BBA 404', 'name' => 'Digital Marketing Analytics, SEO & Social Media Campaigns'],
                    ['code' => 'BBA 405', 'name' => 'Capstone Internship Practicum, Research Monograph & Comprehensive Viva']
                ]
            ]
        ],
        'bsc-in-agriculture' => [
            'id' => 6,
            'title' => 'BSc (Honours) in Agriculture',
            'slug' => 'bsc-in-agriculture',
            'department' => 'Department of Agriculture',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'AGR 101', 'name' => 'Fundamentals of Agronomy & Crop Production Science'],
                    ['code' => 'AGR 102', 'name' => 'Agricultural Chemistry & Soil Science Foundations'],
                    ['code' => 'AGR 103', 'name' => 'Agricultural Botany: Morphology, Anatomy & Taxonomy'],
                    ['code' => 'AGR 104', 'name' => 'Principles of Horticulture & Nursery Management'],
                    ['code' => 'AGR 105', 'name' => 'Introduction to Agricultural Entomology & Beneficial Insects']
                ],
                '2nd Year' => [
                    ['code' => 'AGR 201', 'name' => 'Genetics, Cytology & Plant Breeding Fundamentals'],
                    ['code' => 'AGR 202', 'name' => 'Soil Fertility, Plant Nutrition & Fertilizer Technology'],
                    ['code' => 'AGR 203', 'name' => 'Plant Pathology & Crop Disease Epidemiology'],
                    ['code' => 'AGR 204', 'name' => 'Farm Machinery, Irrigation Systems & Drainage Engineering'],
                    ['code' => 'AGR 205', 'name' => 'Agricultural Microbiology, Bio-fertilizers & Composting']
                ],
                '3rd Year' => [
                    ['code' => 'AGR 301', 'name' => 'Agronomy of Major Field Crops (Cereals, Pulses, Oilseeds & Fiber)'],
                    ['code' => 'AGR 302', 'name' => 'Commercial Olericulture, Pomology & Floriculture'],
                    ['code' => 'AGR 303', 'name' => 'Economic Crop Entomology & Pesticide Toxicology'],
                    ['code' => 'AGR 304', 'name' => 'Agricultural Extension Education & Participatory Rural Appraisal'],
                    ['code' => 'AGR 305', 'name' => 'Post-Harvest Handling, Processing & Cold Storage Technology']
                ],
                '4th Year' => [
                    ['code' => 'AGR 401', 'name' => 'Agribusiness Management, Supply Chain Logistics & Agricultural Marketing'],
                    ['code' => 'AGR 402', 'name' => 'Agricultural Biotechnology, Tissue Culture & Molecular Breeding'],
                    ['code' => 'AGR 403', 'name' => 'Integrated Pest Management (IPM) & Biological Controls'],
                    ['code' => 'AGR 404', 'name' => 'Climate-Resilient Agriculture, Agroforestry & Environmental Sustainability'],
                    ['code' => 'AGR 405', 'name' => 'Field Practicum, Agricultural Extension Camp & Research Thesis']
                ]
            ]
        ],
        'bsc-in-cse' => [
            'id' => 7,
            'title' => 'BSc in Computer Science and Engineering (CSE)',
            'slug' => 'bsc-in-cse',
            'department' => 'Department of Computer Science and Engineering',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'CSE 101', 'name' => 'Structured Programming Language (C / C++) with Lab'],
                    ['code' => 'CSE 102', 'name' => 'Discrete Mathematics & Graph Theory'],
                    ['code' => 'CSE 103', 'name' => 'Electrical Circuits & Basic Electronics with Lab'],
                    ['code' => 'CSE 104', 'name' => 'Differential Calculus, Integral Calculus & Analytical Geometry'],
                    ['code' => 'CSE 105', 'name' => 'Physics: Waves, Electromagnetism & Modern Physics']
                ],
                '2nd Year' => [
                    ['code' => 'CSE 201', 'name' => 'Data Structures with Lab (Linear & Non-Linear Structures)'],
                    ['code' => 'CSE 202', 'name' => 'Object-Oriented Programming (Java / Python) with Lab'],
                    ['code' => 'CSE 203', 'name' => 'Digital Logic Design & Circuit Synthesis with Lab'],
                    ['code' => 'CSE 204', 'name' => 'Database Management Systems (DBMS) & SQL with Lab'],
                    ['code' => 'CSE 205', 'name' => 'Design & Analysis of Algorithms with Lab'],
                    ['code' => 'CSE 206', 'name' => 'Linear Algebra, Matrices & Differential Equations']
                ],
                '3rd Year' => [
                    ['code' => 'CSE 301', 'name' => 'Operating Systems & System Programming with Lab'],
                    ['code' => 'CSE 302', 'name' => 'Microprocessors, Microcontrollers & Embedded Systems with Lab'],
                    ['code' => 'CSE 303', 'name' => 'Computer Networks, TCP/IP Protocols & Socket Programming with Lab'],
                    ['code' => 'CSE 304', 'name' => 'Software Engineering, Design Patterns & Agile Methodologies'],
                    ['code' => 'CSE 305', 'name' => 'Theory of Computation, Formal Languages & Automata'],
                    ['code' => 'CSE 306', 'name' => 'Web Technologies & Full-Stack Application Development with Lab']
                ],
                '4th Year' => [
                    ['code' => 'CSE 401', 'name' => 'Artificial Intelligence & Machine Learning with Lab'],
                    ['code' => 'CSE 402', 'name' => 'Compiler Design, Syntax Analysis & Code Generation with Lab'],
                    ['code' => 'CSE 403', 'name' => 'Network Security, Cryptography & Cyber Defense Systems'],
                    ['code' => 'CSE 404', 'name' => 'Cloud Computing, Virtualization & Distributed Architectures'],
                    ['code' => 'CSE 405', 'name' => 'Senior Capstone Project / Undergraduate Research Thesis (Phase I & II)']
                ]
            ]
        ],
        'bsc-in-ict' => [
            'id' => 8,
            'title' => 'BSc in Information and Communication Technology (ICT)',
            'slug' => 'bsc-in-ict',
            'department' => 'Department of Information and Communication Technology',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'ICT 101', 'name' => 'Introduction to Information and Communication Technology'],
                    ['code' => 'ICT 102', 'name' => 'Structured Computer Programming with C/C++ & Lab'],
                    ['code' => 'ICT 103', 'name' => 'Basic Electrical & Electronic Engineering with Lab'],
                    ['code' => 'ICT 104', 'name' => 'Engineering Mathematics (Differential Calculus & Linear Algebra)'],
                    ['code' => 'ICT 105', 'name' => 'Applied Physics for Information & Communication Systems']
                ],
                '2nd Year' => [
                    ['code' => 'ICT 201', 'name' => 'Data Structures & Algorithmic Problem Solving with Lab'],
                    ['code' => 'ICT 202', 'name' => 'Object Oriented Design & Dynamic Web Application Development with Lab'],
                    ['code' => 'ICT 203', 'name' => 'Digital Electronics, Logic Design & Pulse Circuits with Lab'],
                    ['code' => 'ICT 204', 'name' => 'Relational Database Management Systems & Architecture with Lab'],
                    ['code' => 'ICT 205', 'name' => 'Signals, Linear Systems & Fourier Transforms']
                ],
                '3rd Year' => [
                    ['code' => 'ICT 301', 'name' => 'Data Communications & Modern Computer Networks with Lab'],
                    ['code' => 'ICT 302', 'name' => 'Telecommunication Engineering, Switching & Signaling Systems'],
                    ['code' => 'ICT 303', 'name' => 'Wireless & Mobile Cellular Communications (4G/5G Architectures)'],
                    ['code' => 'ICT 304', 'name' => 'Internet of Things (IoT), Sensor Networks & Microcontrollers with Lab'],
                    ['code' => 'ICT 305', 'name' => 'Information System Security, Cryptographic Protocols & Network Defense']
                ],
                '4th Year' => [
                    ['code' => 'ICT 401', 'name' => 'Optical Fiber Communication Systems, Photonics & Microwave Engineering with Lab'],
                    ['code' => 'ICT 402', 'name' => 'Cloud Infrastructure, Server Virtualization & DevOps Automation'],
                    ['code' => 'ICT 403', 'name' => 'Applied Artificial Intelligence, Data Mining & Big Data Analytics with Lab'],
                    ['code' => 'ICT 404', 'name' => 'Cyber Law, ICT Ethics, Digital Forensics & Intellectual Property'],
                    ['code' => 'ICT 405', 'name' => 'Senior Capstone Design Project / Engineering Thesis & Comprehensive Defense']
                ]
            ]
        ],
        'bsc-in-eee' => [
            'id' => 9,
            'title' => 'Electrical and Electronic Engineering (EEE)',
            'slug' => 'bsc-in-eee',
            'department' => 'Department of Electrical and Electronic Engineering',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'EEE 101', 'name' => 'Electrical Circuits I & Circuit Simulation Lab'],
                    ['code' => 'EEE 102', 'name' => 'Structured C Programming for Engineers with Lab'],
                    ['code' => 'EEE 103', 'name' => 'Engineering Physics (Electromagnetism & Waves)'],
                    ['code' => 'EEE 104', 'name' => 'Differential Calculus, Coordinate Geometry & Differential Equations']
                ],
                '2nd Year' => [
                    ['code' => 'EEE 201', 'name' => 'Electrical Circuits II & Network Analysis with Lab'],
                    ['code' => 'EEE 202', 'name' => 'Analog Electronics I & Semiconductor Devices with Lab'],
                    ['code' => 'EEE 203', 'name' => 'Digital Electronics & Logic Design with Lab'],
                    ['code' => 'EEE 204', 'name' => 'Signals and Linear Systems Analysis'],
                    ['code' => 'EEE 205', 'name' => 'Engineering Mathematics (Complex Variables & Linear Algebra)']
                ],
                '3rd Year' => [
                    ['code' => 'EEE 301', 'name' => 'Electromagnetic Fields and Waves'],
                    ['code' => 'EEE 302', 'name' => 'Electrical Machines I & II (Transformers & AC/DC Motors) with Lab'],
                    ['code' => 'EEE 303', 'name' => 'Microprocessor Systems & Interfacing (Intel 8086 & ARM) with Lab'],
                    ['code' => 'EEE 304', 'name' => 'Power System Analysis & Transmission Engineering with Lab'],
                    ['code' => 'EEE 305', 'name' => 'Control Systems Engineering & State Variable Analysis']
                ],
                '4th Year' => [
                    ['code' => 'EEE 401', 'name' => 'Power Electronics & Industrial Drives with Lab'],
                    ['code' => 'EEE 402', 'name' => 'Telecommunication Engineering & Digital Signal Processing (DSP)'],
                    ['code' => 'EEE 403', 'name' => 'Renewable Energy Technologies & Solar Photovoltaic Systems'],
                    ['code' => 'EEE 404', 'name' => 'Power Plant Engineering & High Voltage Protection'],
                    ['code' => 'EEE 405', 'name' => 'Capstone Senior Engineering Project / Research Thesis (Parts I & II)']
                ]
            ]
        ],
        'bsc-in-microbiology' => [
            'id' => 10,
            'title' => 'Microbiology — BSc (Honours)',
            'slug' => 'bsc-in-microbiology',
            'department' => 'Department of Microbiology',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'MIC 101', 'name' => 'Fundamentals of General Microbiology & Laboratory Techniques'],
                    ['code' => 'MIC 102', 'name' => 'Microbial Chemistry, Biomolecules & Enzymology'],
                    ['code' => 'MIC 103', 'name' => 'Basic Cell Biology, Microscopy & Staining Methods'],
                    ['code' => 'MIC 104', 'name' => 'Biostatistics & Applied Mathematics for Life Sciences']
                ],
                '2nd Year' => [
                    ['code' => 'MIC 201', 'name' => 'Microbial Genetics & Molecular Biology Foundations'],
                    ['code' => 'MIC 202', 'name' => 'Bacteriology & Systematics of Prokaryotes with Lab'],
                    ['code' => 'MIC 203', 'name' => 'Virology: Plant, Animal & Bacteriophage Biology'],
                    ['code' => 'MIC 204', 'name' => 'Mycology & Phycology (Fungi and Algae)'],
                    ['code' => 'MIC 205', 'name' => 'Microbial Ecology & Environmental Microbiology']
                ],
                '3rd Year' => [
                    ['code' => 'MIC 301', 'name' => 'Immunology & Immunotechnology with Practical'],
                    ['code' => 'MIC 302', 'name' => 'Medical Microbiology & Pathogenic Mechanisms'],
                    ['code' => 'MIC 303', 'name' => 'Food & Dairy Microbiology, Quality Control Standards'],
                    ['code' => 'MIC 304', 'name' => 'Industrial Microbiology & Fermentation Biotechnology'],
                    ['code' => 'MIC 305', 'name' => 'Pharmaceutical Microbiology & Antimicrobial Chemotherapy']
                ],
                '4th Year' => [
                    ['code' => 'MIC 401', 'name' => 'Clinical Microbiology, Diagnostic Pathology & Infection Control'],
                    ['code' => 'MIC 402', 'name' => 'Recombinant DNA Technology & Genetic Engineering with Lab'],
                    ['code' => 'MIC 403', 'name' => 'Genomics, Proteomics & Bioinformatics in Microbiology'],
                    ['code' => 'MIC 404', 'name' => 'Biosafety, Bioethics & Research Quality Management'],
                    ['code' => 'MIC 405', 'name' => 'Undergraduate Research Project & Laboratory Dissertation']
                ]
            ]
        ],
        'physical-education-sports-science' => [
            'id' => 11,
            'title' => 'Physical Education and Sports Science',
            'slug' => 'physical-education-sports-science',
            'department' => 'Department of Physical Education and Sports Science',
            'degree_level' => 'Undergraduate',
            'years' => [
                '1st Year' => [
                    ['code' => 'PES 101', 'name' => 'History and Principles of Physical Education and Sports'],
                    ['code' => 'PES 102', 'name' => 'Human Anatomy and Physiology in Exercise'],
                    ['code' => 'PES 103', 'name' => 'Foundations of Track and Field Athletics (Practical)'],
                    ['code' => 'PES 104', 'name' => 'Health Education, First Aid & Safety Measures']
                ],
                '2nd Year' => [
                    ['code' => 'PES 201', 'name' => 'Kinesiology and Biomechanics of Human Movement'],
                    ['code' => 'PES 202', 'name' => 'Sports Psychology and Motor Learning'],
                    ['code' => 'PES 203', 'name' => 'Team Sports I: Football, Cricket & Volleyball (Rules & Officiating)'],
                    ['code' => 'PES 204', 'name' => 'Gymnastics, Calisthenics & Physical Fitness Conditioning']
                ],
                '3rd Year' => [
                    ['code' => 'PES 301', 'name' => 'Exercise Physiology, Metabolic Testing & Fitness Assessment'],
                    ['code' => 'PES 302', 'name' => 'Sports Nutrition, Dietetics & Athletic Weight Management'],
                    ['code' => 'PES 303', 'name' => 'Team Sports II: Basketball, Handball, Badminton & Swimming'],
                    ['code' => 'PES 304', 'name' => 'Sports Pedagogy, Curriculum Design & Teaching Methods'],
                    ['code' => 'PES 305', 'name' => 'Prevention and Management of Sports Injuries & Sports Massage']
                ],
                '4th Year' => [
                    ['code' => 'PES 401', 'name' => 'Sports Training Methodology & Periodization'],
                    ['code' => 'PES 402', 'name' => 'Sports Management, Administration & Tournament Organization'],
                    ['code' => 'PES 403', 'name' => 'Adapted Physical Education for Specially Abled Athletes'],
                    ['code' => 'PES 404', 'name' => 'Measurement, Evaluation & Statistics in Physical Education'],
                    ['code' => 'PES 405', 'name' => 'Coaching Practicum, Field Internship & Undergraduate Thesis']
                ]
            ]
        ]
    ];

    return $curriculums;
}

/**
 * Get Curriculum for a specific program slug
 */
function get_program_curriculum_by_slug(string $slug): ?array {
    $all = get_all_programs_curriculum();
    return $all[$slug] ?? null;
}

/**
 * ========================================================
 * STUDENT PORTAL HELPER FUNCTIONS
 * ========================================================
 */

function is_student_logged_in(): bool {
    return isset($_SESSION['student_id']) && !empty($_SESSION['student_id']);
}

function get_logged_in_student(): ?array {
    if (!is_student_logged_in()) {
        return null;
    }
    static $studentCache = null;
    if ($studentCache !== null && isset($studentCache['id']) && $studentCache['id'] == (int)$_SESSION['student_pk_id']) {
        return $studentCache;
    }
    $db = getDb();
    $stmt = $db->prepare("
        SELECT s.*, p.title_en AS program_name, p.slug AS program_slug, d.name_en AS department_name
        FROM students s
        LEFT JOIN programs p ON s.program_id = p.id
        LEFT JOIN departments d ON s.department_id = d.id
        WHERE s.id = ? AND s.status != 'Suspended'
        LIMIT 1
    ");
    $stmt->execute([(int)$_SESSION['student_pk_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        unset($_SESSION['student_id'], $_SESSION['student_pk_id'], $_SESSION['student_name']);
        return null;
    }
    $studentCache = $student;
    return $student;
}

function require_student_login(): void {
    if (!is_student_logged_in()) {
        flash_set('error', 'Please log in with your Student ID and Password to access the Student Portal.');
        header('Location: ' . APP_URL . '/student/login.php');
        exit;
    }
}

function get_semester_number(string $name): int {
    if (preg_match('/1(?:st)?\s*Semester/i', $name)) return 1;
    if (preg_match('/2(?:nd)?\s*Semester/i', $name)) return 2;
    if (preg_match('/3(?:rd)?\s*Semester/i', $name)) return 3;
    if (preg_match('/4(?:th)?\s*Semester/i', $name)) return 4;
    if (preg_match('/5(?:th)?\s*Semester/i', $name)) return 5;
    if (preg_match('/6(?:th)?\s*Semester/i', $name)) return 6;
    if (preg_match('/7(?:th)?\s*Semester/i', $name)) return 7;
    if (preg_match('/8(?:th)?\s*Semester/i', $name)) return 8;
    if (preg_match('/\b([1-8])\b/', $name, $m)) return (int)$m[1];
    return 99;
}

/**
 * Calculate UGC Uniform Letter Grade, Grade Point, and Result Status from Marks (out of 100)
 */
function get_configured_grading_rules(): array {
    static $cachedRules = null;
    if ($cachedRules !== null) {
        return $cachedRules;
    }
    try {
        $db = getDb();
        $stmt = $db->query("SELECT * FROM grading_rules WHERE is_active = 1 ORDER BY sort_order ASC, min_marks DESC");
        $cachedRules = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Exception $e) {
        $cachedRules = [];
    }
    return $cachedRules ?: [];
}

function calculate_grade_from_marks(float $marks): array {
    $marks = max(0.00, min(100.00, $marks));
    $rules = get_configured_grading_rules();
    if (!empty($rules)) {
        foreach ($rules as $r) {
            $min = (float)$r['min_marks'];
            $max = (float)$r['max_marks'];
            if ($marks >= $min && ($marks <= $max || ($max >= 99.0 && $marks <= 100.0))) {
                $status = ($r['letter_grade'] === 'F' || (float)$r['grade_point'] <= 0.0) ? 'Fail' : 'Pass';
                return [
                    'letter_grade' => $r['letter_grade'],
                    'grade_point'  => (float)$r['grade_point'],
                    'status'       => $status,
                    'remarks'      => $r['remarks'] ?? ($status === 'Pass' ? 'Pass' : 'Fail')
                ];
            }
        }
    }

    if ($marks >= 80.00) {
        return ['letter_grade' => 'A+', 'grade_point' => 4.00, 'status' => 'Pass', 'remarks' => 'Outstanding'];
    } elseif ($marks >= 75.00) {
        return ['letter_grade' => 'A',  'grade_point' => 3.75, 'status' => 'Pass', 'remarks' => 'Excellent'];
    } elseif ($marks >= 70.00) {
        return ['letter_grade' => 'A-', 'grade_point' => 3.50, 'status' => 'Pass', 'remarks' => 'Very Good'];
    } elseif ($marks >= 65.00) {
        return ['letter_grade' => 'B+', 'grade_point' => 3.25, 'status' => 'Pass', 'remarks' => 'Good'];
    } elseif ($marks >= 60.00) {
        return ['letter_grade' => 'B',  'grade_point' => 3.00, 'status' => 'Pass', 'remarks' => 'Satisfactory'];
    } elseif ($marks >= 55.00) {
        return ['letter_grade' => 'B-', 'grade_point' => 2.75, 'status' => 'Pass', 'remarks' => 'Above Average'];
    } elseif ($marks >= 50.00) {
        return ['letter_grade' => 'C+', 'grade_point' => 2.50, 'status' => 'Pass', 'remarks' => 'Average'];
    } elseif ($marks >= 45.00) {
        return ['letter_grade' => 'C',  'grade_point' => 2.25, 'status' => 'Pass', 'remarks' => 'Pass'];
    } elseif ($marks >= 40.00) {
        return ['letter_grade' => 'D',  'grade_point' => 2.00, 'status' => 'Pass', 'remarks' => 'Conditional Pass'];
    } else {
        return ['letter_grade' => 'F',  'grade_point' => 0.00, 'status' => 'Fail', 'remarks' => 'Fail'];
    }
}

/**
 * Recalculate credit-weighted CGPA & completed credits for a student and sync with database
 */
function recalculate_student_cgpa(int $studentId): array {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) AS total_courses,
            SUM(credit) AS total_registered_credits,
            SUM(CASE WHEN grade_point >= 2.00 THEN credit ELSE 0 END) AS total_earned_credits,
            SUM(credit * grade_point) AS total_grade_points
        FROM student_results
        WHERE student_id = ? AND is_published = 1
    ");
    $stmt->execute([$studentId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalCourses = (int)($stats['total_courses'] ?? 0);
    $totalRegCredits = (float)($stats['total_registered_credits'] ?? 0);
    $totalEarnedCredits = (float)($stats['total_earned_credits'] ?? 0);
    $totalGradePoints = (float)($stats['total_grade_points'] ?? 0);

    $cgpa = ($totalCourses > 0 && $totalRegCredits > 0)
        ? round($totalGradePoints / $totalRegCredits, 2)
        : 0.00;

    $upd = $db->prepare("UPDATE students SET cgpa = ?, credits_completed = ? WHERE id = ?");
    $upd->execute([$cgpa, (int)$totalEarnedCredits, $studentId]);

    return [
        'cgpa' => $cgpa,
        'credits_completed' => (int)$totalEarnedCredits,
        'total_registered_credits' => $totalRegCredits,
        'total_grade_points' => $totalGradePoints,
        'total_courses' => $totalCourses,
    ];
}

/**
 * Get comprehensive 8-semester breakdown and cumulative statistics for a student
 */
function get_student_academic_summary(int $studentId, bool $publishedOnly = true): array {
    $db = getDb();
    
    // Fetch student profile
    $stStmt = $db->prepare("
        SELECT s.*, p.title_en AS program_name, d.name_en AS department_name, f.name_en AS faculty_name
        FROM students s
        LEFT JOIN programs p ON s.program_id = p.id
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN faculties f ON d.faculty_id = f.id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stStmt->execute([$studentId]);
    $student = $stStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        return ['has_results' => false, 'student' => null, 'semesters' => []];
    }

    $sql = "SELECT * FROM student_results WHERE student_id = ?";
    if ($publishedOnly) {
        $sql .= " AND is_published = 1";
    }
    $sql .= " ORDER BY id ASC";

    $rStmt = $db->prepare($sql);
    $rStmt->execute([$studentId]);
    $allResults = $rStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($allResults)) {
        return [
            'has_results' => false,
            'student' => $student,
            'semesters' => [],
            'semester_gpas' => [],
            'final_cgpa' => 0.00,
            'total_registered_credits' => 0.0,
            'total_earned_credits' => 0.0,
            'total_grade_points' => 0.0,
        ];
    }

    // Group by semester
    $grouped = [];
    foreach ($allResults as $res) {
        $semKey = trim($res['semester']);
        $grouped[$semKey][] = $res;
    }

    // Sort chronologically 1 to 8
    uksort($grouped, function($a, $b) {
        return get_semester_number($a) <=> get_semester_number($b);
    });

    $runningRegCredits = 0.0;
    $runningEarnedCredits = 0.0;
    $runningGradePoints = 0.0;
    $semestersData = [];
    $semesterGpas = [];
    $hasAnyEstimated = false;

    foreach ($grouped as $semName => $coursesList) {
        $semNum = get_semester_number($semName);
        $semRegCr = 0.0;
        $semEarnedCr = 0.0;
        $semPts = 0.0;
        $processedCourses = [];
        $hasFailedCourse = false;
        $semHasEstimated = false;

        foreach ($coursesList as $c) {
            $cr = (float)$c['credit'];
            $obtainedMarks = isset($c['marks']) && $c['marks'] !== null ? (float)$c['marks'] : null;
            $fullMarks = isset($c['full_marks']) ? (int)$c['full_marks'] : 100;
            $gp = (float)$c['grade_point'];
            $grade = trim($c['letter_grade']);
            $ptsSecured = round($cr * $gp, 2);
            $status = ($gp >= 2.00) ? 'Pass' : 'Fail';
            $isEst = !empty($c['is_estimated']);
            if ($isEst) {
                $semHasEstimated = true;
                $hasAnyEstimated = true;
            }

            if ($status === 'Fail') {
                $hasFailedCourse = true;
            }

            $semRegCr += $cr;
            if ($status === 'Pass') {
                $semEarnedCr += $cr;
            }
            $semPts += $ptsSecured;

            $processedCourses[] = [
                'id' => (int)$c['id'],
                'course_code' => $c['course_code'],
                'course_name' => $c['course_name'],
                'credit' => $cr,
                'full_marks' => $fullMarks,
                'marks' => $obtainedMarks,
                'letter_grade' => $grade,
                'grade_point' => $gp,
                'points_secured' => $ptsSecured,
                'status' => $status,
                'exam_term' => $c['exam_term'],
                'is_published' => (bool)$c['is_published'],
                'is_estimated' => $isEst,
                'result_type' => $c['result_type'] ?? ($isEst ? 'Estimated' : 'Verified'),
                'correction_reason' => $c['correction_reason'] ?? '',
            ];
        }

        $sgpa = ($semRegCr > 0) ? round($semPts / $semRegCr, 2) : 0.00;
        $semResultStatus = $hasFailedCourse ? 'PROMOTED (WITH CARRY)' : 'PASSED';

        $runningRegCredits += $semRegCr;
        $runningEarnedCredits += $semEarnedCr;
        $runningGradePoints += $semPts;
        $runningCgpa = ($runningRegCredits > 0) ? round($runningGradePoints / $runningRegCredits, 2) : 0.00;

        $semestersData[$semName] = [
            'semester_number' => $semNum,
            'semester_name' => $semName,
            'courses' => $processedCourses,
            'registered_credits' => $semRegCr,
            'earned_credits' => $semEarnedCr,
            'semester_points' => $semPts,
            'sgpa' => $sgpa,
            'status' => $semResultStatus,
            'cumulative_reg_credits' => $runningRegCredits,
            'cumulative_earned_credits' => $runningEarnedCredits,
            'cumulative_points' => $runningGradePoints,
            'cumulative_cgpa' => $runningCgpa,
            'is_estimated' => $semHasEstimated,
        ];

        $semesterGpas[$semNum] = $sgpa;
    }

    $finalCgpa = ($runningRegCredits > 0) ? round($runningGradePoints / $runningRegCredits, 2) : 0.00;

    return [
        'has_results' => true,
        'student' => $student,
        'semesters' => $semestersData,
        'semester_gpas' => $semesterGpas,
        'final_cgpa' => $finalCgpa,
        'total_registered_credits' => $runningRegCredits,
        'total_earned_credits' => $runningEarnedCredits,
        'total_grade_points' => $runningGradePoints,
        'has_estimated_results' => $hasAnyEstimated,
        'disclaimer_label' => $hasAnyEstimated ? 'ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT' : '',
    ];
}

function get_student_avatar_url(?array $student): string {
    if (!empty($student['avatar'])) {
        $avatarFile = $student['avatar'];
        $filePath = BASE_PATH . '/storage/uploads/students/' . $avatarFile;
        if (file_exists($filePath)) {
            return APP_URL . '/storage/uploads/students/' . $avatarFile;
        }
    }
    return APP_URL . '/assets/images/default-student.svg';
}

function get_admission_photo_url(?string $photoFilename): string {
    if (!empty($photoFilename)) {
        $path = BASE_PATH . '/storage/uploads/students/' . $photoFilename;
        if (file_exists($path)) {
            return APP_URL . '/storage/uploads/students/' . $photoFilename;
        }
    }
    return APP_URL . '/assets/images/default-student.svg';
}

function get_payment_screenshot_url(?string $filename): ?string {
    if (!empty($filename)) {
        $path = BASE_PATH . '/storage/uploads/payments/' . $filename;
        if (file_exists($path)) {
            return APP_URL . '/storage/uploads/payments/' . $filename;
        }
        $path2 = BASE_PATH . '/storage/uploads/' . $filename;
        if (file_exists($path2)) {
            return APP_URL . '/storage/uploads/' . $filename;
        }
    }
    return null;
}

/**
 * Generate next sequential Student ID for Fall 2026 intake
 */
function generate_next_student_id(PDO $db): string {
    $stmt = $db->query("SELECT student_id FROM students WHERE student_id LIKE 'RMU26%' ORDER BY id DESC LIMIT 1");
    $lastId = $stmt->fetchColumn();
    if ($lastId && preg_match('/RMU(\d+)/i', $lastId, $matches)) {
        $next = intval($matches[1]) + 1;
        return 'RMU' . $next;
    }
    return 'RMU2601004';
}

/**
 * Generate memorable initial password for Student Portal
 */
function generate_initial_student_password(): string {
    return 'rmu' . rand(1000, 9999);
}

/**
 * Provision new Student account and enrolled courses upon ৳150 Admission Fee Payment
 */
function provision_student_from_admission(int $admissionId): ?array {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM admissions WHERE id = ?");
    $stmt->execute([$admissionId]);
    $adm = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$adm) return null;

    if (!empty($adm['student_id']) && !empty($adm['portal_password'])) {
        $studentId = $adm['student_id'];
        $plainPassword = $adm['portal_password'];
        $examRoll = $adm['exam_roll'] ?? ('ADM-' . substr($studentId, -5));
    } else {
        $studentId = generate_next_student_id($db);
        $plainPassword = generate_initial_student_password();
        $examRoll = 'ADM-' . substr($studentId, -5);

        $upd = $db->prepare("UPDATE admissions SET student_id = ?, portal_password = ?, exam_roll = ? WHERE id = ?");
        $upd->execute([$studentId, $plainPassword, $examRoll, $admissionId]);
    }

    // Check if student already created in students table
    $chk = $db->prepare("SELECT id FROM students WHERE student_id = ?");
    $chk->execute([$studentId]);
    $existingStudentId = $chk->fetchColumn();

    if (!$existingStudentId) {
        $subject = $adm['interested_subject'] ?? '';
        $prog = null;
        if (!empty($adm['program_id'])) {
            $pStmt = $db->prepare("SELECT id, department_id, title_en FROM programs WHERE id = ? LIMIT 1");
            $pStmt->execute([$adm['program_id']]);
            $prog = $pStmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$prog && !empty($subject)) {
            $progStmt = $db->prepare("SELECT id, department_id, title_en FROM programs WHERE title_en = ? OR title_en LIKE ? LIMIT 1");
            $progStmt->execute([$subject, "%$subject%"]);
            $prog = $progStmt->fetch(PDO::FETCH_ASSOC);
        }

        $programId = $prog ? (int)$prog['id'] : (!empty($adm['program_id']) ? (int)$adm['program_id'] : 1);
        $departmentId = $prog ? (int)$prog['department_id'] : (!empty($adm['department_id']) ? (int)$adm['department_id'] : 6);

        $session = !empty($adm['academic_session']) ? $adm['academic_session'] : 'Batch 26';
        $fatherName = $adm['father_name'] ?? null;
        $motherName = $adm['mother_name'] ?? null;
        $dob = !empty($adm['date_of_birth']) ? $adm['date_of_birth'] : null;
        $gender = !empty($adm['gender']) ? $adm['gender'] : 'Male';
        $presentAddr = $adm['present_address'] ?? null;
        $permAddr = $adm['permanent_address'] ?? null;

        $email = !empty($adm['email']) ? $adm['email'] : (strtolower($studentId) . '@student.rmu.ac.bd');
        $emailChk = $db->prepare("SELECT id FROM students WHERE email = ?");
        $emailChk->execute([$email]);
        if ($emailChk->fetchColumn()) {
            $email = strtolower($studentId) . '@student.rmu.ac.bd';
        }

        $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $avatar = !empty($adm['photo']) ? $adm['photo'] : null;

        $insStudent = $db->prepare("
            INSERT INTO students (
                student_id, full_name, father_name, mother_name, email, phone, password_hash,
                program_id, department_id, batch, current_year, current_semester,
                cgpa, credits_completed, total_credits_required, dob, gender, home_district,
                present_address, permanent_address, status, avatar
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, '1st Year', '1st Semester',
                0.00, 0, 140, ?, ?, ?,
                ?, ?, 'Active', ?
            )
        ");
        $insStudent->execute([
            $studentId, $adm['full_name'], $fatherName, $motherName, $email, $adm['mobile'], $passwordHash,
            $programId, $departmentId, $session,
            $dob, $gender, $adm['home_district'],
            $presentAddr, $permAddr, $avatar
        ]);
        $newStudentPk = (int)$db->lastInsertId();

        // Send Welcome & ID Generation notification to student
        create_student_notification(
            $newStudentPk,
            "Admission Accepted — Welcome to RMU!",
            "Congratulations! Your admission application has been approved. Your official Student ID is {$studentId}. You can now view your enrolled courses, fee invoices, and academic results.",
            'general'
        );

        // Seed 4 sample 1st-semester courses
        $courses = [
            ['CSE 101', 'Introduction to Computer Science & Problem Solving', 3.0, 'Prof. Dr. M. A. Rahman', 'Sun & Tue 10:00 AM', 'Room 301'],
            ['CSE 102', 'Structured Programming Language (C)', 3.0, 'Engr. Tareq Ahmed', 'Mon & Wed 11:30 AM', 'Lab 2'],
            ['MAT 101', 'Differential and Integral Calculus', 3.0, 'Dr. Shamsul Huda', 'Sun & Wed 02:00 PM', 'Room 205'],
            ['ENG 101', 'English Reading and Composition Skills', 3.0, 'Ms. Sabrina Akter', 'Mon & Thu 03:30 PM', 'Room 304']
        ];
        $insCourse = $db->prepare("
            INSERT INTO student_courses (
                student_id, course_code, course_name, credit,
                instructor_name, class_schedule, room_no, attendance_percent, semester, status
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, 90, '1st Semester', 'Enrolled'
            )
        ");
        foreach ($courses as $c) {
            $insCourse->execute([
                $newStudentPk, $c[0], $c[1], $c[2], $c[3], $c[4], $c[5]
            ]);
        }
    }

    return [
        'student_id'      => $studentId,
        'portal_password' => $plainPassword,
        'exam_roll'       => $examRoll
    ];
}

/**
 * Result Management & Student Notifications Helpers
 */
function create_student_notification(int $studentId, string $title, string $message, string $type = 'result_published', ?int $relatedId = null): bool {
    try {
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO student_notifications (student_id, title, message, type, related_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$studentId, $title, $message, $type, $relatedId]);
    } catch (Exception $e) {
        return false;
    }
}

function get_student_notifications(int $studentId, bool $unreadOnly = false, int $limit = 10): array {
    try {
        $db = getDb();
        $sql = "SELECT * FROM student_notifications WHERE student_id = ?";
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY id DESC LIMIT " . (int)$limit;
        $stmt = $db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function get_student_unread_notifications_count(int $studentId): int {
    try {
        $db = getDb();
        $stmt = $db->prepare("SELECT COUNT(*) FROM student_notifications WHERE student_id = ? AND is_read = 0");
        $stmt->execute([$studentId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function mark_student_notifications_read(int $studentId, ?int $notificationId = null): bool {
    try {
        $db = getDb();
        if ($notificationId !== null) {
            $stmt = $db->prepare("UPDATE student_notifications SET is_read = 1 WHERE id = ? AND student_id = ?");
            return $stmt->execute([$notificationId, $studentId]);
        } else {
            $stmt = $db->prepare("UPDATE student_notifications SET is_read = 1 WHERE student_id = ?");
            return $stmt->execute([$studentId]);
        }
    } catch (Exception $e) {
        return false;
    }
}

function log_result_modification(?int $resultId, int $studentId, int $adminId, string $courseCode, string $semester, string $fieldName, ?string $oldValue, string $newValue, string $reason): bool {
    try {
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO result_modification_history 
            (result_id, student_id, admin_id, course_code, semester, field_name, old_value, new_value, reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$resultId, $studentId, $adminId, $courseCode, $semester, $fieldName, $oldValue, $newValue, $reason]);
    } catch (Exception $e) {
        return false;
    }
}

function get_pending_result_requests_count(): int {
    try {
        $db = getDb();
        return (int)$db->query("SELECT COUNT(*) FROM result_requests WHERE status = 'Pending'")->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * ========================================================
 * ONE-CLICK CGPA UPDATE & ESTIMATED RESULT GENERATOR ENGINE
 * ========================================================
 */

/**
 * Get standard semester-wise curriculum for any university program
 * Returns an array indexed by semester number (1..8 for Bachelor's, 1..4 for Master's)
 */
function get_program_semester_curriculum(int $programId): array {
    $db = getDb();
    $progStmt = $db->prepare("SELECT p.*, d.name_en AS dept_name, d.slug AS dept_slug FROM programs p LEFT JOIN departments d ON p.department_id = d.id WHERE p.id = ?");
    $progStmt->execute([$programId]);
    $prog = $progStmt->fetch(PDO::FETCH_ASSOC);

    $isMaster = ($prog && strtolower($prog['degree_level']) === 'postgraduate');
    $slug = $prog['slug'] ?? '';
    $totalSemesters = $isMaster ? 4 : 8;
    $curriculum = [];

    // 1. CSE
    if ($slug === 'bsc-in-cse') {
        $curriculum = [
            1 => [
                ['code' => 'CSE 101', 'name' => 'Structured Programming Language (C)', 'credit' => 3.0],
                ['code' => 'CSE 102', 'name' => 'Structured Programming Laboratory', 'credit' => 1.5],
                ['code' => 'MAT 101', 'name' => 'Differential and Integral Calculus', 'credit' => 3.0],
                ['code' => 'ENG 101', 'name' => 'English Reading and Composition Skills', 'credit' => 3.0]
            ],
            2 => [
                ['code' => 'CSE 103', 'name' => 'Discrete Mathematics for Computing', 'credit' => 3.0],
                ['code' => 'CSE 104', 'name' => 'Object Oriented Programming (C++)', 'credit' => 3.0],
                ['code' => 'CSE 105', 'name' => 'Object Oriented Programming Lab', 'credit' => 1.5],
                ['code' => 'PHY 101', 'name' => 'Physics for Engineers (Mechanics & Waves)', 'credit' => 3.0]
            ],
            3 => [
                ['code' => 'CSE 201', 'name' => 'Data Structures & Algorithms', 'credit' => 3.0],
                ['code' => 'CSE 202', 'name' => 'Data Structures Laboratory', 'credit' => 1.5],
                ['code' => 'CSE 203', 'name' => 'Digital Logic Design & Circuits', 'credit' => 3.0],
                ['code' => 'EEE 101', 'name' => 'Basic Electrical Engineering & Circuit Analysis', 'credit' => 3.0]
            ],
            4 => [
                ['code' => 'CSE 205', 'name' => 'Design & Analysis of Algorithms', 'credit' => 3.0],
                ['code' => 'CSE 206', 'name' => 'Database Management Systems (DBMS)', 'credit' => 3.0],
                ['code' => 'CSE 207', 'name' => 'Database Management Systems Lab', 'credit' => 1.5],
                ['code' => 'MAT 201', 'name' => 'Linear Algebra & Complex Variables', 'credit' => 3.0]
            ],
            5 => [
                ['code' => 'CSE 301', 'name' => 'Operating Systems & System Programming', 'credit' => 3.0],
                ['code' => 'CSE 302', 'name' => 'Operating Systems Laboratory', 'credit' => 1.5],
                ['code' => 'CSE 303', 'name' => 'Microprocessors, Microcontrollers & Interfacing', 'credit' => 3.0],
                ['code' => 'CSE 304', 'name' => 'Software Engineering & System Architecture', 'credit' => 3.0]
            ],
            6 => [
                ['code' => 'CSE 305', 'name' => 'Computer Networks & TCP/IP Protocols', 'credit' => 3.0],
                ['code' => 'CSE 306', 'name' => 'Computer Networks Laboratory', 'credit' => 1.5],
                ['code' => 'CSE 307', 'name' => 'Theory of Computation & Automata', 'credit' => 3.0],
                ['code' => 'CSE 308', 'name' => 'Web Technologies & Distributed Cloud Systems', 'credit' => 3.0]
            ],
            7 => [
                ['code' => 'CSE 401', 'name' => 'Artificial Intelligence & Machine Learning', 'credit' => 3.0],
                ['code' => 'CSE 402', 'name' => 'Artificial Intelligence Lab', 'credit' => 1.5],
                ['code' => 'CSE 403', 'name' => 'Compiler Design & Syntax Analysis', 'credit' => 3.0],
                ['code' => 'CSE 404', 'name' => 'Cyber Security, Cryptography & Network Defense', 'credit' => 3.0]
            ],
            8 => [
                ['code' => 'CSE 405', 'name' => 'Cloud Computing & High Performance Architectures', 'credit' => 3.0],
                ['code' => 'CSE 406', 'name' => 'Data Science & Big Data Analytics', 'credit' => 3.0],
                ['code' => 'CSE 407', 'name' => 'Senior Capstone Project & Engineering Thesis', 'credit' => 4.0],
                ['code' => 'CSE 408', 'name' => 'Comprehensive Academic Defense & Viva Voce', 'credit' => 1.0]
            ]
        ];
    }
    // 2. BBA
    elseif ($slug === 'bba') {
        $curriculum = [
            1 => [
                ['code' => 'BBA 101', 'name' => 'Introduction to Modern Business & Organizations', 'credit' => 3.0],
                ['code' => 'BBA 102', 'name' => 'Principles of Financial Accounting', 'credit' => 3.0],
                ['code' => 'BBA 103', 'name' => 'Microeconomic Principles for Decision Making', 'credit' => 3.0],
                ['code' => 'BBA 104', 'name' => 'Business Mathematics & Commercial Computing', 'credit' => 3.0]
            ],
            2 => [
                ['code' => 'BBA 105', 'name' => 'Macroeconomics & Global Monetary Policy', 'credit' => 3.0],
                ['code' => 'BBA 106', 'name' => 'Managerial Accounting & Cost Estimation', 'credit' => 3.0],
                ['code' => 'BBA 107', 'name' => 'Principles of Marketing & Consumer Analysis', 'credit' => 3.0],
                ['code' => 'BBA 108', 'name' => 'Business Communication & Public Relations', 'credit' => 3.0]
            ],
            3 => [
                ['code' => 'BBA 201', 'name' => 'Principles of Finance & Capital Budgeting', 'credit' => 3.0],
                ['code' => 'BBA 202', 'name' => 'Organizational Behavior & Human Resources', 'credit' => 3.0],
                ['code' => 'BBA 203', 'name' => 'Business Statistics & Probability Analysis', 'credit' => 3.0],
                ['code' => 'BBA 204', 'name' => 'Commercial Law, Contract Acts & Ethics', 'credit' => 3.0]
            ],
            4 => [
                ['code' => 'BBA 205', 'name' => 'Corporate Finance & Working Capital Management', 'credit' => 3.0],
                ['code' => 'BBA 206', 'name' => 'Consumer Behavior & Market Research', 'credit' => 3.0],
                ['code' => 'BBA 207', 'name' => 'Operations & Quality Management', 'credit' => 3.0],
                ['code' => 'BBA 208', 'name' => 'Management Information Systems (MIS)', 'credit' => 3.0]
            ],
            5 => [
                ['code' => 'BBA 301', 'name' => 'International Business & Global Logistics', 'credit' => 3.0],
                ['code' => 'BBA 302', 'name' => 'Financial Markets, Banking Institutions & Credit', 'credit' => 3.0],
                ['code' => 'BBA 303', 'name' => 'Strategic Marketing & Brand Management', 'credit' => 3.0],
                ['code' => 'BBA 304', 'name' => 'Corporate Taxation, Auditing & Compliance', 'credit' => 3.0]
            ],
            6 => [
                ['code' => 'BBA 305', 'name' => 'Supply Chain Management & Procurement', 'credit' => 3.0],
                ['code' => 'BBA 306', 'name' => 'Investment Analysis & Portfolio Management', 'credit' => 3.0],
                ['code' => 'BBA 307', 'name' => 'Digital Marketing Analytics & E-Commerce', 'credit' => 3.0],
                ['code' => 'BBA 308', 'name' => 'Corporate Governance & Social Responsibility', 'credit' => 3.0]
            ],
            7 => [
                ['code' => 'BBA 401', 'name' => 'Entrepreneurship & Startup Venture Incubation', 'credit' => 3.0],
                ['code' => 'BBA 402', 'name' => 'Strategic Management & Competitive Policy', 'credit' => 3.0],
                ['code' => 'BBA 403', 'name' => 'Project Appraisal & Feasibility Study', 'credit' => 3.0],
                ['code' => 'BBA 404', 'name' => 'Business Research Methodology & Seminar', 'credit' => 3.0]
            ],
            8 => [
                ['code' => 'BBA 405', 'name' => 'Corporate Policy & Strategic Simulation', 'credit' => 3.0],
                ['code' => 'BBA 406', 'name' => 'International Financial Management', 'credit' => 3.0],
                ['code' => 'BBA 407', 'name' => 'Senior Internship Practicum & Monograph', 'credit' => 4.0],
                ['code' => 'BBA 408', 'name' => 'Comprehensive Academic Viva Voce', 'credit' => 1.0]
            ]
        ];
    }
    // 3. MBA (Master of Business Administration - 4 Semesters)
    elseif ($slug === 'mba' || ($isMaster && strpos(strtolower($slug), 'business') !== false)) {
        $curriculum = [
            1 => [
                ['code' => 'MBA 501', 'name' => 'Advanced Managerial Economics & Quantitative Methods', 'credit' => 3.0],
                ['code' => 'MBA 502', 'name' => 'Strategic Financial Accounting & Reporting', 'credit' => 3.0],
                ['code' => 'MBA 503', 'name' => 'Organizational Leadership & Strategic HRM', 'credit' => 3.0],
                ['code' => 'MBA 504', 'name' => 'Corporate Governance, Law & Ethical Leadership', 'credit' => 3.0]
            ],
            2 => [
                ['code' => 'MBA 505', 'name' => 'Advanced Corporate Finance & Valuation Analysis', 'credit' => 3.0],
                ['code' => 'MBA 506', 'name' => 'Strategic Marketing & Consumer Analytics', 'credit' => 3.0],
                ['code' => 'MBA 507', 'name' => 'Global Supply Chain & Operations Excellence', 'credit' => 3.0],
                ['code' => 'MBA 508', 'name' => 'Enterprise Analytics, Big Data & Digital Transformation', 'credit' => 3.0]
            ],
            3 => [
                ['code' => 'MBA 601', 'name' => 'Global Competitive Strategy & M&A Policy', 'credit' => 3.0],
                ['code' => 'MBA 602', 'name' => 'Venture Capital, Innovation & Private Equity', 'credit' => 3.0],
                ['code' => 'MBA 603', 'name' => 'Applied Business Research Methods & Econometrics', 'credit' => 3.0],
                ['code' => 'MBA 604', 'name' => 'International Financial Markets & Risk Hedging', 'credit' => 3.0]
            ],
            4 => [
                ['code' => 'MBA 605', 'name' => 'Corporate Strategy Simulation & Case Analysis', 'credit' => 3.0],
                ['code' => 'MBA 606', 'name' => 'Executive Leadership & Sustainable Enterprise', 'credit' => 3.0],
                ['code' => 'MBA 607', 'name' => 'Master\'s Graduate Thesis / Capstone Applied Project', 'credit' => 4.0],
                ['code' => 'MBA 608', 'name' => 'Comprehensive Oral Examination & Viva Voce', 'credit' => 1.0]
            ]
        ];
    }
    // 4. MA in English (4 Semesters)
    elseif ($slug === 'ma-in-english') {
        $curriculum = [
            1 => [
                ['code' => 'MAE 501', 'name' => 'Advanced Critical Theory & Cultural Studies', 'credit' => 3.0],
                ['code' => 'MAE 502', 'name' => 'Modernist & Postmodern Fiction and Poetry', 'credit' => 3.0],
                ['code' => 'MAE 503', 'name' => 'Postcolonial Discourse & World Literatures', 'credit' => 3.0],
                ['code' => 'MAE 504', 'name' => 'Advanced Stylistics, Discourse Analysis & Pragmatics', 'credit' => 3.0]
            ],
            2 => [
                ['code' => 'MAE 505', 'name' => 'Shakespeare in Performance, Adaptation & Critique', 'credit' => 3.0],
                ['code' => 'MAE 506', 'name' => 'Diaspora Writings & Transnational Narratives', 'credit' => 3.0],
                ['code' => 'MAE 507', 'name' => 'Applied Linguistics & Second Language Acquisition', 'credit' => 3.0],
                ['code' => 'MAE 508', 'name' => 'Advanced Literary Research Methodology & Bibliography', 'credit' => 3.0]
            ],
            3 => [
                ['code' => 'MAE 601', 'name' => 'Feminist Poetics, Gender Studies & Queer Theory', 'credit' => 3.0],
                ['code' => 'MAE 602', 'name' => 'South Asian English Literature & Partition Studies', 'credit' => 3.0],
                ['code' => 'MAE 603', 'name' => 'Translation Studies: Hermeneutics & Practicum', 'credit' => 3.0],
                ['code' => 'MAE 604', 'name' => 'ELT Testing, Curriculum Design & Pedagogy', 'credit' => 3.0]
            ],
            4 => [
                ['code' => 'MAE 605', 'name' => 'Environmental Humanities & Ecocritical Literatures', 'credit' => 3.0],
                ['code' => 'MAE 606', 'name' => 'Contemporary World Poetry & Cultural Resistance', 'credit' => 3.0],
                ['code' => 'MAE 607', 'name' => 'Master of Arts Graduate Dissertation & Defense', 'credit' => 4.0],
                ['code' => 'MAE 608', 'name' => 'Graduate Comprehensive Viva Voce', 'credit' => 1.0]
            ]
        ];
    }
    // 5. MA in Bangla (4 Semesters)
    elseif ($slug === 'ma-in-bangla') {
        $curriculum = [
            1 => [
                ['code' => 'MAB 501', 'name' => 'Comparative Literary Theory & Aesthetics', 'credit' => 3.0],
                ['code' => 'MAB 502', 'name' => 'Ancient & Medieval Bangla Poetics and Manuscript Studies', 'credit' => 3.0],
                ['code' => 'MAB 503', 'name' => 'Modern Bangla Poetry: Rabindranath, Nazrul & Jibanananda', 'credit' => 3.0],
                ['code' => 'MAB 504', 'name' => 'Bangla Sociolinguistics & Historical Dialectology', 'credit' => 3.0]
            ],
            2 => [
                ['code' => 'MAB 505', 'name' => 'Literature of the Liberation War & Post-Colonial Bangladesh', 'credit' => 3.0],
                ['code' => 'MAB 506', 'name' => 'Evolution of Bangla Drama, Theatre & Folk Performances', 'credit' => 3.0],
                ['code' => 'MAB 507', 'name' => 'Modern Bangla Prose, Periodicals & Intellectual History', 'credit' => 3.0],
                ['code' => 'MAB 508', 'name' => 'Research Methodology in Bangla Literary Studies', 'credit' => 3.0]
            ],
            3 => [
                ['code' => 'MAB 601', 'name' => 'Tagore Studies: Philosophy, Aesthetics & Cultural Impact', 'credit' => 3.0],
                ['code' => 'MAB 602', 'name' => 'Folklore of Kushtia & Lalon Shahi Mystical Heritage', 'credit' => 3.0],
                ['code' => 'MAB 603', 'name' => 'Contemporary Bangla Fiction & Novelistic Traditions', 'credit' => 3.0],
                ['code' => 'MAB 604', 'name' => 'Comparative South Asian Literature in Translation', 'credit' => 3.0]
            ],
            4 => [
                ['code' => 'MAB 605', 'name' => 'Semiotics & Modern Literary Hermeneutics', 'credit' => 3.0],
                ['code' => 'MAB 606', 'name' => 'Bangla Lexicography, Orthography & Digital Linguistics', 'credit' => 3.0],
                ['code' => 'MAB 607', 'name' => 'Master\'s Research Dissertation & Public Defense', 'credit' => 4.0],
                ['code' => 'MAB 608', 'name' => 'Comprehensive Academic Viva Voce', 'credit' => 1.0]
            ]
        ];
    }
    // 6. Master of Physical Education (MPEd - 4 Semesters)
    elseif ($slug === 'mped') {
        $curriculum = [
            1 => [
                ['code' => 'MPE 501', 'name' => 'Advanced Exercise Physiology & Cardiorespiratory Testing', 'credit' => 3.0],
                ['code' => 'MPE 502', 'name' => 'Advanced Sports Biomechanics & Cinematographic Analysis', 'credit' => 3.0],
                ['code' => 'MPE 503', 'name' => 'Sports Psychology, Peak Performance & Mental Conditioning', 'credit' => 3.0],
                ['code' => 'MPE 504', 'name' => 'Advanced Research Methodology & Sports Statistics', 'credit' => 3.0]
            ],
            2 => [
                ['code' => 'MPE 505', 'name' => 'High Performance Athletic Training & Periodization', 'credit' => 3.0],
                ['code' => 'MPE 506', 'name' => 'Sports Medicine, Rehabilitation & Therapeutic Modalities', 'credit' => 3.0],
                ['code' => 'MPE 507', 'name' => 'Sports Nutrition, Ergogenic Aids & Metabolic Testing', 'credit' => 3.0],
                ['code' => 'MPE 508', 'name' => 'Sports Law, Ethics & Anti-Doping Regulations', 'credit' => 3.0]
            ],
            3 => [
                ['code' => 'MPE 601', 'name' => 'Strategic Sports Management & Global Governance', 'credit' => 3.0],
                ['code' => 'MPE 602', 'name' => 'Motor Learning, Neural Control & Skill Acquisition', 'credit' => 3.0],
                ['code' => 'MPE 603', 'name' => 'Fitness Technology, Wearable Sensors & Sports Analytics', 'credit' => 3.0],
                ['code' => 'MPE 604', 'name' => 'Adapted Physical Activities & Paralympic Sports Science', 'credit' => 3.0]
            ],
            4 => [
                ['code' => 'MPE 605', 'name' => 'Olympic Movement, Elite Coaching Practicum & Supervision', 'credit' => 3.0],
                ['code' => 'MPE 606', 'name' => 'Facility Design, Event Management & Sports Marketing', 'credit' => 3.0],
                ['code' => 'MPE 607', 'name' => 'Master\'s Research Thesis & Scientific Defense', 'credit' => 4.0],
                ['code' => 'MPE 608', 'name' => 'Comprehensive Oral Defense & Viva Voce', 'credit' => 1.0]
            ]
        ];
    }
    // 7. General fallback for any other program (e.g. EEE, ICT, English, Bangla, Music, Fine Arts, PES, Agriculture, Microbiology)
    else {
        // Derive prefix from slug or title
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $slug), 0, 3));
        if (empty($prefix) || strlen($prefix) < 2) $prefix = 'GEN';

        for ($s = 1; $s <= $totalSemesters; $s++) {
            $curriculum[$s] = [
                ['code' => "{$prefix} {$s}01", 'name' => "Core Subject {$s}.1 for " . ($prog['title_en'] ?? 'Program'), 'credit' => 3.0],
                ['code' => "{$prefix} {$s}02", 'name' => "Core Subject {$s}.2 for " . ($prog['title_en'] ?? 'Program'), 'credit' => 3.0],
                ['code' => "{$prefix} {$s}03", 'name' => "Applied Subject / Laboratory {$s}.3", 'credit' => ($s % 2 === 0 ? 1.5 : 3.0)],
                ['code' => "{$prefix} {$s}04", 'name' => ($s === $totalSemesters ? 'Senior Monograph & Comprehensive Viva' : "Academic Subject {$s}.4"), 'credit' => ($s === $totalSemesters ? 3.0 : 3.0)]
            ];
        }
    }

    return $curriculum;
}

/**
 * Generate Estimated Academic Results for a student to mathematically match target CGPA
 * Automatically respects Bachelor's (8 Semesters) or Master's (4 Semesters)
 * Safely replaces previous estimated results without duplicates
 */
function generate_estimated_student_results(int $studentId, float $targetCgpa, bool $publishToPortal = true, int $adminId = 1): array {
    $db = getDb();
    
    // Validate target CGPA range
    $targetCgpa = max(2.00, min(4.00, round($targetCgpa, 2)));

    // Fetch student and program
    $stStmt = $db->prepare("
        SELECT s.*, p.title_en AS program_name, p.degree_level, p.slug AS program_slug
        FROM students s
        LEFT JOIN programs p ON s.program_id = p.id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stStmt->execute([$studentId]);
    $student = $stStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        return ['success' => false, 'error' => 'Student record not found.'];
    }

    $programId = (int)($student['program_id'] ?? 1);
    $isMaster = (strtolower($student['degree_level'] ?? '') === 'postgraduate');
    $totalSemesters = $isMaster ? 4 : 8;

    $standardSemesterTitles = [
        1 => '1st Semester',
        2 => '2nd Semester',
        3 => '3rd Semester',
        4 => '4th Semester',
        5 => '5th Semester',
        6 => '6th Semester',
        7 => '7th Semester',
        8 => '8th Semester'
    ];

    // Load curriculum for this program
    $curriculum = get_program_semester_curriculum($programId);

    // Flatten all course items for mathematical point balancing
    $allCourses = [];
    foreach ($curriculum as $semNum => $courses) {
        if ($semNum > $totalSemesters) continue;
        foreach ($courses as $c) {
            $allCourses[] = [
                'semester_num' => $semNum,
                'semester_name' => $standardSemesterTitles[$semNum] ?? "Semester {$semNum}",
                'course_code' => $c['code'],
                'course_name' => $c['name'],
                'credit' => (float)($c['credit'] ?? 3.0),
                'full_marks' => 100
            ];
        }
    }

    if (empty($allCourses)) {
        return ['success' => false, 'error' => 'No curriculum courses available for this program.'];
    }

    // UGC Scale valid Grade Points
    $validGps = [4.00, 3.75, 3.50, 3.25, 3.00, 2.75, 2.50, 2.25, 2.00];

    // Find closest baseline Grade Point
    $baseGp = 4.00;
    foreach ($validGps as $gp) {
        if ($targetCgpa >= ($gp - 0.125)) {
            $baseGp = $gp;
            break;
        }
    }

    $totalCredits = 0.0;
    foreach ($allCourses as $c) {
        $totalCredits += $c['credit'];
    }
    $targetPoints = $targetCgpa * $totalCredits;

    // 1. Initial realistic distribution with pseudo-random variance
    $baseIdx = array_search($baseGp, $validGps);
    if ($baseIdx === false) $baseIdx = 0;

    foreach ($allCourses as $idx => &$c) {
        // Natural variance across subjects: -1, 0, +1 tier in grading ladder
        $pattern = [0, 1, 0, -1, 0, 1, -1, 0];
        $offset = $pattern[$idx % count($pattern)];
        $chosenIdx = max(0, min(count($validGps) - 1, $baseIdx + $offset));
        $c['gp'] = $validGps[$chosenIdx];
    }
    unset($c);

    // 2. Iterative optimization to converge credit-weighted CGPA exactly on target
    for ($iter = 0; $iter < 300; $iter++) {
        $currentPoints = 0.0;
        foreach ($allCourses as $c) {
            $currentPoints += ($c['credit'] * $c['gp']);
        }
        $diff = $targetPoints - $currentPoints;
        $currentCgpa = round($currentPoints / $totalCredits, 2);

        if (abs($currentCgpa - $targetCgpa) < 0.005) {
            break; // Exactly on target!
        }

        if ($diff > 0) {
            // Need higher points: find a course below 4.00 and upgrade it
            $candidates = [];
            foreach ($allCourses as $i => $c) {
                if ($c['gp'] < 4.00) $candidates[] = $i;
            }
            if (empty($candidates)) break;
            $pick = $candidates[$iter % count($candidates)];
            $curIdx = array_search($allCourses[$pick]['gp'], $validGps);
            if ($curIdx > 0) {
                $allCourses[$pick]['gp'] = $validGps[$curIdx - 1];
            }
        } else {
            // Need lower points: find a course above 2.00 and downgrade it
            $candidates = [];
            foreach ($allCourses as $i => $c) {
                if ($c['gp'] > 2.00) $candidates[] = $i;
            }
            if (empty($candidates)) break;
            $pick = $candidates[$iter % count($candidates)];
            $curIdx = array_search($allCourses[$pick]['gp'], $validGps);
            if ($curIdx < count($validGps) - 1) {
                $allCourses[$pick]['gp'] = $validGps[$curIdx + 1];
            }
        }
    }

    // 3. Assign plausible marks within each grade point's official band
    foreach ($allCourses as &$c) {
        $gp = $c['gp'];
        if ($gp >= 4.00) {
            $c['marks'] = 80.0 + (rand(0, 120) / 10.0); // 80.0 - 92.0
            $c['letter_grade'] = 'A+';
        } elseif ($gp >= 3.75) {
            $c['marks'] = 75.0 + (rand(0, 45) / 10.0);  // 75.0 - 79.5
            $c['letter_grade'] = 'A';
        } elseif ($gp >= 3.50) {
            $c['marks'] = 70.0 + (rand(0, 45) / 10.0);  // 70.0 - 74.5
            $c['letter_grade'] = 'A-';
        } elseif ($gp >= 3.25) {
            $c['marks'] = 65.0 + (rand(0, 45) / 10.0);  // 65.0 - 69.5
            $c['letter_grade'] = 'B+';
        } elseif ($gp >= 3.00) {
            $c['marks'] = 60.0 + (rand(0, 45) / 10.0);  // 60.0 - 64.5
            $c['letter_grade'] = 'B';
        } elseif ($gp >= 2.75) {
            $c['marks'] = 55.0 + (rand(0, 45) / 10.0);  // 55.0 - 59.5
            $c['letter_grade'] = 'B-';
        } elseif ($gp >= 2.50) {
            $c['marks'] = 50.0 + (rand(0, 45) / 10.0);  // 50.0 - 54.5
            $c['letter_grade'] = 'C+';
        } elseif ($gp >= 2.25) {
            $c['marks'] = 45.0 + (rand(0, 45) / 10.0);  // 45.0 - 49.5
            $c['letter_grade'] = 'C';
        } else {
            $c['marks'] = 40.0 + (rand(0, 45) / 10.0);  // 40.0 - 44.5
            $c['letter_grade'] = 'D';
        }
        $c['marks'] = round($c['marks'], 1);
    }
    unset($c);

    // 4. Save to Database
    // Safely delete ONLY prior estimated results to allow safe re-generation without duplicates
    // Any manually verified or official results (is_estimated = 0) are strictly preserved!
    $delStmt = $db->prepare("DELETE FROM student_results WHERE student_id = ? AND is_estimated = 1");
    $delStmt->execute([$studentId]);

    // Check existing verified results to avoid duplicating course codes in the same semester
    $existingVerified = $db->query("SELECT CONCAT(semester, ':', course_code) FROM student_results WHERE student_id = {$studentId} AND is_estimated = 0")->fetchAll(PDO::FETCH_COLUMN);

    $insStmt = $db->prepare("
        INSERT INTO student_results 
        (student_id, semester, course_code, course_name, credit, full_marks, marks, letter_grade, grade_point, exam_term, is_published, is_estimated, result_type, publish_status, published_at, published_by, correction_reason)
        VALUES (?, ?, ?, ?, ?, 100, ?, ?, ?, 'Semester Final Exam', ?, 1, 'Estimated', ?, NOW(), ?, ?)
    ");

    $pubVal = $publishToPortal ? 1 : 0;
    $pubStatus = $publishToPortal ? 'Published' : 'Draft';
    $reason = "ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT. Auto-generated to match target CGPA " . number_format($targetCgpa, 2);

    $insertedCount = 0;
    foreach ($allCourses as $c) {
        $key = $c['semester_name'] . ':' . $c['course_code'];
        if (in_array($key, $existingVerified)) {
            continue; // Keep verified course, don't overwrite with estimate
        }

        $insStmt->execute([
            $studentId,
            $c['semester_name'],
            $c['course_code'],
            $c['course_name'],
            $c['credit'],
            $c['marks'],
            $c['letter_grade'],
            $c['gp'],
            $pubVal,
            $pubStatus,
            $adminId,
            $reason
        ]);
        $insertedCount++;
    }

    // Recalculate official student profile summary
    recalculate_student_cgpa($studentId);

    // Update students table directly with exact target CGPA
    $db->prepare("UPDATE students SET cgpa = ?, credits_completed = ? WHERE id = ?")->execute([$targetCgpa, (int)$totalCredits, $studentId]);

    // Dispatch clear student in-app notification with mandatory disclaimer
    create_student_notification(
        $studentId,
        "Academic Results Projected (Estimated)",
        "Your semester-wise results have been estimated based on cumulative CGPA " . number_format($targetCgpa, 2) . ". IMPORTANT: ESTIMATED / AUTO-GENERATED RESULT — NOT AN OFFICIAL UNIVERSITY RESULT.",
        'result'
    );

    // Audit log
    log_audit('ESTIMATED_RESULTS_GENERATED', "Generated estimated academic results for student #{$studentId} ({$student['full_name']}) matching target CGPA {$targetCgpa}");

    return [
        'success' => true,
        'student_id' => $student['student_id'],
        'student_name' => $student['full_name'],
        'target_cgpa' => $targetCgpa,
        'degree_level' => $isMaster ? 'Postgraduate' : 'Undergraduate',
        'total_semesters' => $totalSemesters,
        'total_credits' => $totalCredits,
        'inserted_courses' => $insertedCount
    ];
}

/**
 * Administrator Verification Workflow: Verify an estimated semester or result as official
 */
function verify_semester_results_as_official(int $studentId, string $semester, int $adminId = 1): int {
    $db = getDb();
    $stmt = $db->prepare("
        UPDATE student_results 
        SET is_estimated = 0, 
            result_type = 'Verified', 
            publish_status = 'Published',
            is_published = 1,
            published_by = ?, 
            published_at = NOW(),
            correction_reason = 'Verified by administrator as official examination record'
        WHERE student_id = ? AND semester = ? AND is_estimated = 1
    ");
    $stmt->execute([$adminId, $studentId, $semester]);
    $affected = $stmt->rowCount();

    if ($affected > 0) {
        log_audit('RESULTS_VERIFIED_OFFICIAL', "Admin verified {$affected} estimated course result(s) in {$semester} as official for student #{$studentId}");
        create_student_notification(
            $studentId,
            "Official Results Verified",
            "Your academic results for {$semester} have been officially verified and confirmed by the Office of the Controller of Examinations.",
            'result'
        );
        recalculate_student_cgpa($studentId);
    }
    return $affected;
}





