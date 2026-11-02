<?php
/**
 * Seed All 8 Semesters with Courses & Grades for Demonstration Student
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();

// Find main demo student (RMU2601001 or ID 4)
$student = $db->query("SELECT * FROM students WHERE student_id = 'RMU2601001' OR id = 4 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "Student RMU2601001 not found.\n";
    exit(1);
}

$studentId = (int)$student['id'];

// Clear existing results for student
$db->prepare("DELETE FROM student_results WHERE student_id = ?")->execute([$studentId]);

$all8SemesterCourses = [
    // 1st Semester
    ['1st Semester (Spring 2023)', 'CSE 101', 'Structured Programming in C', 3.0, 'A+', 4.00],
    ['1st Semester (Spring 2023)', 'CSE 102', 'Structured Programming Lab', 1.5, 'A+', 4.00],
    ['1st Semester (Spring 2023)', 'ENG 101', 'English Reading & Writing Skills', 3.0, 'A', 3.75],
    ['1st Semester (Spring 2023)', 'MAT 101', 'Differential and Integral Calculus', 3.0, 'A+', 4.00],

    // 2nd Semester
    ['2nd Semester (Fall 2023)', 'CSE 103', 'Discrete Mathematics', 3.0, 'A+', 4.00],
    ['2nd Semester (Fall 2023)', 'CSE 104', 'Object Oriented Programming (Java)', 3.0, 'A', 3.75],
    ['2nd Semester (Fall 2023)', 'PHY 101', 'Physics (Mechanics, Waves & Optics)', 3.0, 'A', 3.75],
    ['2nd Semester (Fall 2023)', 'BAN 105', 'Bangla Language & Tagore Literary Studies', 2.0, 'A+', 4.00],

    // 3rd Semester
    ['3rd Semester (Spring 2024)', 'CSE 201', 'Data Structures & Algorithms', 3.0, 'A+', 4.00],
    ['3rd Semester (Spring 2024)', 'CSE 202', 'Data Structures & Algorithms Lab', 1.5, 'A+', 4.00],
    ['3rd Semester (Spring 2024)', 'CSE 203', 'Digital Logic Design & Microprocessors', 3.0, 'A', 3.75],
    ['3rd Semester (Spring 2024)', 'MAT 102', 'Coordinate Geometry & Linear Algebra', 3.0, 'A', 3.75],

    // 4th Semester
    ['4th Semester (Fall 2024)', 'CSE 205', 'Database Management Systems', 3.0, 'A+', 4.00],
    ['4th Semester (Fall 2024)', 'CSE 206', 'Database Management Systems Lab', 1.5, 'A+', 4.00],
    ['4th Semester (Fall 2024)', 'CSE 207', 'Computer Architecture & Organization', 3.0, 'A', 3.75],
    ['4th Semester (Fall 2024)', 'STA 201', 'Statistics & Probability for Engineers', 3.0, 'A', 3.75],

    // 5th Semester
    ['5th Semester (Spring 2025)', 'CSE 301', 'Software Engineering & System Analysis', 3.0, 'A+', 4.00],
    ['5th Semester (Spring 2025)', 'CSE 302', 'Operating Systems & System Programming', 3.0, 'A', 3.75],
    ['5th Semester (Spring 2025)', 'CSE 303', 'Operating Systems Lab (Linux/Unix)', 1.5, 'A+', 4.00],
    ['5th Semester (Spring 2025)', 'CSE 304', 'Theory of Computation & Automata', 3.0, 'A', 3.75],

    // 6th Semester
    ['6th Semester (Fall 2025)', 'CSE 305', 'Computer Networks & Internet Protocols', 3.0, 'A+', 4.00],
    ['6th Semester (Fall 2025)', 'CSE 306', 'Computer Networks Laboratory', 1.5, 'A+', 4.00],
    ['6th Semester (Fall 2025)', 'CSE 307', 'Web Engineering & Full-Stack Development', 3.0, 'A+', 4.00],
    ['6th Semester (Fall 2025)', 'CSE 308', 'Microcontroller & Embedded Systems Lab', 1.5, 'A', 3.75],

    // 7th Semester
    ['7th Semester (Spring 2026)', 'CSE 401', 'Artificial Intelligence & Machine Learning', 3.0, 'A+', 4.00],
    ['7th Semester (Spring 2026)', 'CSE 402', 'Compiler Design & Construction', 3.0, 'A', 3.75],
    ['7th Semester (Spring 2026)', 'CSE 403', 'Cyber Security & Cryptography', 3.0, 'A', 3.75],
    ['7th Semester (Spring 2026)', 'CSE 499A', 'Capstone Project & Thesis (Part I)', 3.0, 'A+', 4.00],

    // 8th Semester
    ['8th Semester (Fall 2026)', 'CSE 405', 'Cloud Computing & Distributed Systems', 3.0, 'A+', 4.00],
    ['8th Semester (Fall 2026)', 'CSE 406', 'Digital Image Processing & Vision', 3.0, 'A', 3.75],
    ['8th Semester (Fall 2026)', 'CSE 499B', 'Capstone Project & Defense (Part II)', 3.0, 'A+', 4.00],
    ['8th Semester (Fall 2026)', 'MGT 401', 'Industrial Management & Professional Ethics', 2.0, 'A+', 4.00]
];

$ins = $db->prepare("
    INSERT INTO student_results 
    (student_id, semester, course_code, course_name, credit, letter_grade, grade_point, exam_term)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'Semester Final Examination')
");

$totalCredits = 0;
$totalPoints = 0;

foreach ($all8SemesterCourses as $c) {
    $ins->execute([$studentId, $c[0], $c[1], $c[2], $c[3], $c[4], $c[5]]);
    $totalCredits += (float)$c[3];
    $totalPoints += ((float)$c[3] * (float)$c[5]);
}

$finalCgpa = round($totalPoints / $totalCredits, 2);

// Update student record
$db->prepare("
    UPDATE students 
    SET current_year = '4th Year', 
        current_semester = '8th Semester (Final)', 
        cgpa = ?, 
        credits_completed = ?, 
        status = 'Graduated' 
    WHERE id = ?
")->execute([$finalCgpa, (int)$totalCredits, $studentId]);

echo "Successfully seeded all 8 Semesters (32 Courses, {$totalCredits} Credits, CGPA {$finalCgpa}) for student {$student['student_id']}.\n";

