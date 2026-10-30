# Rabindra Maitree University (RMU) — Full-Stack Website Reconstruction

A responsive, database-driven university web portal inspired by the public information architecture, navigation hierarchy, and visual design tokens of **Rabindra Maitree University (Kushtia, Bangladesh)** — https://rmu.ac.bd/.

Built with **HTML5, CSS3, JavaScript, PHP 8+, MySQL/MariaDB (PDO prepared statements)**, and designed for local execution in **XAMPP**.

> [!NOTE]
> **Educational & Authorized Development Notice:**  
> This project is an independent educational and development prototype modeled after the publicly accessible information architecture of Rabindra Maitree University (Kushtia, Bangladesh). It does not replace the official administrative registrar of Rabindra Maitree University.

---

## 1. Key Features & Implementation Overview

### A. Public University Website
- **Modern Academic Branding:** Professional layout adhering to authentic reference design tokens (Academic Blue `#1e73be`, Dark Slate `#636363`, typography with `Poppins`, `Roboto Slab`, and `Hind Siliguri` for Bengali).
- **Responsive Header & Top Bar:** Verified hotline (`+8801302991188`), email (`info@rmu.ac.bd`), social links (Facebook, LinkedIn, WhatsApp), and real-time admission badge.
- **Hero Slider / Carousel:** Interactive banner with previous/next controls, dot indicators, autoplay, pause-on-hover, keyboard navigation (Left/Right arrows), and `prefers-reduced-motion` accessibility.
- **Notice Board Ticker & Board:** Pinned announcements, categorized notices (General, Admission, Academic, Scholarship, Exam), view counters, and PDF downloads.
- **Academic Hierarchy:**
  - **4 Faculties:** Faculty of Arts & Social Science, Faculty of Engineering & Technology, Faculty of Business Studies, Faculty of Agriculture.
  - **11 Departments:** English, Bangla, Fine Arts, Music, Physical Education, CSE, EEE, ICT, Microbiology, Business Administration, Agriculture.
  - **14+ Degree Programs:** Undergraduate and Graduate curricula with duration, credits, eligibility rules, and tuition fee schedules.
- **Campus News & Events:** Full event calendar and press release articles with timestamps and location details.
- **Global Search:** Multi-table search across notices, degree programs, departments, and news articles with highlighted keyword matching.
- **Resource Downloads Center:** Official downloadable PDF forms, academic calendars, syllabus guides, and scholarship vouchers.
- **Verified Contact Page:** Real campus address in Kushtia, hotline, office hours, interactive Google Map link, and secure inquiry form.

### B. Online Admission System (Prototype Intake)
- **Verified 10 Fields Matching Reference Form:**
  1. Full Name
  2. Bangladeshi Mobile Number (11 digits, validated against `013-019` prefix)
  3. Email Address (optional)
  4. Previous / Last College Name
  5. S.S.C GPA (1.00 – 5.00 range validation)
  6. H.S.C GPA (1.00 – 5.00 range validation)
  7. Home District
  8. HSC Group Stream (Science, Humanities, Commerce)
  9. Intended Program Selection (12 verified programs from reference)
  10. Special Quota Declaration (YES / NO)
- **Security & Integrity:** CSRF token verification, client-side & server-side validation, duplicate pending submission prevention.
- **Application Reference Generator:** Unique tracking number format (e.g. `RMU-2026-XXXX`).
- **Real-Time Status Tracking:** Applicants can track status (`Submitted`, `Under Review`, `Accepted`, `Additional Information Required`, `Rejected`) using their Reference ID or Mobile.
- **Printable Admission Slip:** Official admission receipt voucher format (`window.print()`).

### C. Protected Administration Dashboard (`/admin/`)
- **Secure Authentication:** Password hashing using `password_hash()` and `password_verify()`, session regeneration upon login to eliminate session fixation, brute-force attempt tracking.
- **Role-Based Clearance:** Granular access controls (`superadmin`, `admin`, `editor`).
- **Dashboard Metrics:** Real-time summary tiles of total applicants, pending reviews, active degree programs, notices, and unread inquiries.
- **Full CRUD Management:**
  - Manage Faculties (Add, Edit, Dean assignments, sort orders).
  - Manage Departments (Add, Edit, associate faculty, department code, chair name).
  - Manage Degree Programs (Add, Edit, duration, credits, tuition fees, eligibility).
  - Manage Faculty Members (Add, Edit, designations, qualifications, contact).
  - Manage Official Notices (Add, Edit, category, pinning, PDF attachment uploads).
  - Manage Events (Add, Edit, dates, times, locations).
  - Manage News (Add, Edit, lead summaries, rich HTML content).
  - Manage Downloadable Forms (Add, Edit, upload and catalog forms).
  - Manage Contact Inquiries (Review questions, toggle read/replied states).
  - Manage Site Settings (Hotline, address, active semester term, social links).
  - Security Audit Trail (Chronological logging of administrative actions with IP addresses).
- **First Administrator Setup Tool (`/admin/setup-admin.php`):** Safe administrative setup and password recovery.

---

## 2. Directory Structure

```
rmu-project/
├── index.php                 # Homepage with hero slider, ticker, faculties, notices, events
├── about.php                 # About Rabindra Maitree University
├── history.php               # History of the university since 2015
├── mission-vision.php        # Vision, mission & institutional core values
├── leadership.php            # Messages from Board Chairman, Vice-Chancellor, Treasurer
├── administration.php        # Key administrative offices directory
├── board-of-trustees.php     # Board of Trustees governance structure
├── faculties.php             # Academic faculties overview
├── faculty-detail.php        # Single faculty detail view
├── departments.php           # All 11 departments directory
├── department-detail.php     # Single department view with staff profiles and programs
├── programs.php              # All degree programs with level filter
├── program-detail.php        # Single program view with fees and syllabus
├── admissions.php            # Admission overview, guidelines, and steps
├── admission-form.php        # Prototype online admission intake form
├── admission-status.php      # Application tracking and printable voucher
├── tuition-fees.php          # Detailed tuition and fee schedules
├── scholarships.php          # Endowed scholarships (Mansur Ali, Fazli Hossain, etc.)
├── faqs.php                  # Frequently asked admission questions
├── notices.php               # Notice board with category filters and pagination
├── notice-detail.php         # Single notice detail view with attachment download
├── events.php                # Campus events calendar
├── event-detail.php          # Single event view
├── news.php                  # Campus news & press releases
├── news-detail.php           # Single news article view
├── downloads.php             # Downloadable resources and forms
├── contact.php               # Contact page with campus address and message form
├── search.php                # Multi-table global search results
├── privacy-policy.php        # Data privacy & prototype disclaimers
├── 404.php                   # Branded custom 404 error page
├── config/
│   ├── app.php               # Base URL auto-detection, timezone, security headers, sessions
│   ├── database.php          # Active database credentials
│   └── database.example.php  # Sample database configuration
├── includes/
│   ├── database.php          # PDO singleton connection wrapper
│   ├── functions.php         # Security, CSRF, validation, and layout helper utilities
│   ├── header.php            # HTML head, topbar, branding, and alert messages
│   ├── navigation.php        # Responsive navbar with dropdown menus and mobile offcanvas
│   ├── breadcrumbs.php       # Breadcrumb navigation bar component
│   └── footer.php            # 4-column academic footer with disclaimers
├── actions/
│   ├── submit-admission.php  # Admission form processor (CSRF, server validation, duplicate check)
│   └── submit-contact.php    # Contact message processor
├── assets/
│   ├── css/
│   │   ├── style.css         # Main stylesheet with authentic CSS variables
│   │   └── responsive.css    # Breakpoints for desktop, tablet, and mobile
│   ├── js/
│   │   ├── main.js           # Slider carousel, mobile drawer, alert auto-dismiss
│   │   └── validation.js     # Client-side admission & contact form validation
│   └── images/
│       ├── logo.jpg          # Authentic RMU logo asset
│       ├── logo.svg          # Vector university logo
│       ├── hero-1.svg        # Slide 1 banner
│       ├── hero-2.svg        # Slide 2 banner
│       └── hero-3.svg        # Slide 3 banner
├── admin/
│   ├── login.php             # Admin sign-in with password_verify and session regeneration
│   ├── logout.php            # Secure logout
│   ├── dashboard.php         # Metrics tiles, recent applications, audit preview
│   ├── admissions.php        # Admission intake review, status evaluation, and notes
│   ├── faculties.php         # Manage faculties
│   ├── departments.php       # Manage academic departments
│   ├── programs.php          # Manage degree programs
│   ├── faculty-members.php   # Manage faculty teachers and staff
│   ├── notices.php           # Manage notices with file uploads
│   ├── events.php            # Manage university events
│   ├── news.php              # Manage news articles
│   ├── downloads.php         # Manage downloadable documents
│   ├── messages.php          # Manage contact inquiries
│   ├── settings.php          # Manage global university settings
│   ├── audit-logs.php        # Chronological administrative audit logs
│   ├── setup-admin.php       # First administrator setup and recovery tool
│   └── includes/
│       ├── admin-header.php  # Admin navigation header
│       ├── admin-sidebar.php # Admin dashboard sidebar
│       └── admin-footer.php  # Admin layout footer
├── database/
│   ├── schema.sql            # Normalized MySQL schema (InnoDB, utf8mb4)
│   └── seed.sql              # Rich demonstration seed dataset
├── storage/
│   ├── uploads/              # Uploaded notice files and assets
│   └── downloads/            # Downloadable PDF documents
├── tests/
│   └── run_tests.php         # Automated CLI/HTTP test runner
├── REFERENCE_AUDIT.md        # Comprehensive reference website audit document
└── README.md                 # Complete documentation and setup manual
```

---

## 3. Installation Guide (XAMPP on Windows)

### Prerequisites
- **XAMPP** for Windows (with Apache and MySQL / MariaDB).
- **PHP 8.0+** (PHP 8.2 or 8.5 recommended with `pdo_mysql`, `mbstring`, `fileinfo` enabled).

### Step 1: Clone or Copy the Project
Copy the project folder into your XAMPP web root directory:
```
C:\xampp\htdocs\RRR ROBIMN
```
*(Alternatively, you can name the folder `rmu-project` at `C:\xampp\htdocs\rmu-project`).*

### Step 2: Start Apache and MySQL
1. Launch the **XAMPP Control Panel** (`C:\xampp\xampp-control.exe`).
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.
4. Both services should display green status lights.

### Step 3: Create the Database & Import Data
Open your terminal (PowerShell or Command Prompt) and run:
```powershell
# Navigate to the project directory
cd "C:\xampp\htdocs\RRR ROBIMN"

# Log into MySQL and create database
mysql -u root -e "CREATE DATABASE IF NOT EXISTS rmu_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import Schema and Seed data
mysql -u root rmu_db < database/schema.sql
mysql -u root rmu_db < database/seed.sql
```

*Alternatively, using **phpMyAdmin**:*
1. Open your browser and navigate to `http://localhost/phpmyadmin/`.
2. Click **New** and create a database named `rmu_db` with collation `utf8mb4_unicode_ci`.
3. Select `rmu_db` and click the **Import** tab.
4. Import `database/schema.sql` first.
5. Next, import `database/seed.sql`.

### Step 4: Configure the Database Connection
Verify that `config/database.php` matches your local MySQL server:
```php
return [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'dbname'   => 'rmu_db',
    'username' => 'root',
    'password' => '', // Leave blank for default XAMPP MySQL root
    'charset'  => 'utf8mb4'
];
```

### Step 5: Open the Public Website
In your web browser, open:
```
http://localhost/RRR%20ROBIMN/
```
*(Or `http://localhost/rmu-project/` if renamed).*

---

## 4. Admin Panel Access & Default Credentials

Navigate to the administrative login portal:
```
http://localhost/RRR%20ROBIMN/admin/login.php
```

### Default Demonstration Administrator:
- **Username:** `admin` (or `admin@rmu.ac.bd`)
- **Password:** `Admin@RMU2026!`
- **Role:** `superadmin`

> [!TIP]
> **Need to create a new administrator?**  
> Access the Administrator Setup Tool at `http://localhost/RRR%20ROBIMN/admin/setup-admin.php` to securely generate additional staff accounts.

---

## 5. Running the Automated Test Suite

A comprehensive test runner is included at `tests/run_tests.php`. It automatically tests:
- PHP syntax linting across all source files (`php -l`)
- Database connection & schema integrity
- Bangla UTF-8 text encoding
- All 30+ public page routes and parameters
- Bangladeshi mobile number validation regex
- GPA range checks (1.00 to 5.00)
- Online admission submission and duplicate prevention
- Contact form persistence
- Admin password verification (`password_verify`)
- Protection of admin routes against unauthenticated visitors
- Global search query execution

To run the test suite, execute:
```powershell
php "c:\Users\USER\RRR ROBIMN\tests\run_tests.php"
```

---

## 6. Testing the Workflows

### Testing Admission Intake:
1. Go to `http://localhost/RRR%20ROBIMN/admission-form.php`.
2. Enter student details (e.g., Full Name: `Rakibul Islam`, Mobile: `01712345678`, SSC GPA: `4.80`, HSC GPA: `4.50`, Group: `Science`, Subject: `BSc Engg in CSE`).
3. Click **Submit Application**.
4. You will be redirected to `admission-status.php` with a generated reference code (e.g. `RMU-2026-XXXX`).
5. Click **Print Application Voucher** to view the printable slip.
6. Log into the Admin Panel at `/admin/login.php`, go to **Admission Intake**, find the new submission, update its status to `Accepted`, and add counselor notes.
7. Return to the public tracking page to see the updated status in real-time.

### Testing Contact Messages:
1. Go to `http://localhost/RRR%20ROBIMN/contact.php`.
2. Fill out the contact inquiry form and submit.
3. In the Admin Panel, go to **Inquiries** (`/admin/messages.php`) to view the message, mark it as read, or mark it as replied.

---

## 7. Troubleshooting Common Issues

1. **"Can't connect to MySQL server on 'localhost'"**
   - Ensure the MySQL service in XAMPP is started.
   - If port 3306 is in use by another service, change the MySQL port in `C:\xampp\mysql\bin\my.ini` and update `config/database.php`.

2. **"Database rmu_db does not exist"**
   - Import `database/schema.sql` and `database/seed.sql` into MySQL following Step 3.

3. **Blank Screen or 500 Internal Server Error**
   - Check Apache error logs at `C:\xampp\apache\logs\error.log`.
   - Ensure PHP extensions `pdo_mysql`, `mbstring`, and `fileinfo` are enabled in `php.ini`.

4. **File Uploads Fail**
   - Ensure `storage/uploads/` and `storage/downloads/` directories exist and have write permissions.

---

## 8. License & Credits

- **Educational Reconstruction:** Antigravity Full-Stack Engineering & QA Team.
- **Reference Website:** Rabindra Maitree University (https://rmu.ac.bd/).
- **Fonts:** Google Fonts (`Poppins`, `Roboto Slab`, `Hind Siliguri`).
- **Icons:** FontAwesome 6 Free.

