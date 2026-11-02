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
function calculate_grade_from_marks(float $marks): array {
    $marks = max(0.00, min(100.00, $marks));
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

    foreach ($grouped as $semName => $coursesList) {
        $semNum = get_semester_number($semName);
        $semRegCr = 0.0;
        $semEarnedCr = 0.0;
        $semPts = 0.0;
        $processedCourses = [];
        $hasFailedCourse = false;

        foreach ($coursesList as $c) {
            $cr = (float)$c['credit'];
            $obtainedMarks = isset($c['marks']) && $c['marks'] !== null ? (float)$c['marks'] : null;
            $fullMarks = isset($c['full_marks']) ? (int)$c['full_marks'] : 100;
            $gp = (float)$c['grade_point'];
            $grade = trim($c['letter_grade']);
            $ptsSecured = round($cr * $gp, 2);
            $status = ($gp >= 2.00) ? 'Pass' : 'Fail';

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
        $subject = $adm['interested_subject'];
        $progStmt = $db->prepare("SELECT id, department_id, title_en FROM programs WHERE title_en = ? OR title_en LIKE ? LIMIT 1");
        $progStmt->execute([$subject, "%$subject%"]);
        $prog = $progStmt->fetch(PDO::FETCH_ASSOC);

        $programId = $prog ? (int)$prog['id'] : 1;
        $departmentId = $prog ? (int)$prog['department_id'] : 6;

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
                student_id, full_name, email, phone, password_hash,
                program_id, department_id, batch, current_year, current_semester,
                cgpa, credits_completed, total_credits_required, home_district, status, avatar
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, 'Batch 26', '1st Year', '1st Semester',
                0.00, 0, 140, ?, 'Active', ?
            )
        ");
        $insStudent->execute([
            $studentId, $adm['full_name'], $email, $adm['mobile'], $passwordHash,
            $programId, $departmentId, $adm['home_district'], $avatar
        ]);
        $newStudentPk = (int)$db->lastInsertId();

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



