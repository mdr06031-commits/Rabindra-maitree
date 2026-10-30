# Rabindra Maitree University (RMU) — Reference Website Audit

**Audit Date:** October 2026  
**Reference Website URL:** https://rmu.ac.bd/  
**Audit Conducted By:** Antigravity Full-Stack Engineering & QA Team  
**Scope:** Public information architecture, navigation, typography, color tokens, forms, backend requirements, and discrepancies.

---

## 1. Verified URLs & Status

The following URLs on `https://rmu.ac.bd/` were inspected directly via HTTP requests:

| # | URL | HTTP Status | Verified Title / Content |
|---|-----|-------------|--------------------------|
| 1 | `https://rmu.ac.bd/` | **200 OK** | Rabindra Maitree University - RMU \| রবীন্দ্র মৈত্রী বিশ্ববিদ্যালয় |
| 2 | `https://rmu.ac.bd/about-us/` | **200 OK** | About Rabindra Maitree University- রবীন্দ্র মৈত্রী বিশ্ববিদ্যালয় \| RMU - RMU University |
| 3 | `https://rmu.ac.bd/faculties/` | **200 OK** | Faculties - Rabindra Maitree University - RMU University |
| 4 | `https://rmu.ac.bd/rmu-admission/` | **200 OK** | Rabindra Maitree University Online Admission Form - RMU University |
| 5 | `https://rmu.ac.bd/notice/` | **200 OK** | Notice - RMU University |
| 6 | `https://rmu.ac.bd/events/` | **200 OK** | events - RMU University |
| 7 | `https://rmu.ac.bd/contact/` | **200 OK** | Contact \| Robindro Maitree University - RMU |
| 8 | `https://rmu.ac.bd/rmu-admission-fair-fall-2026/` | **200 OK** | রবীন্দ্র মৈত্রী বিশ্ববিদ্যালয়ে ভর্তি মেলা ফল-২০২৬-এর শুভ উদ্বোধন |
| 9 | `https://rmu.ac.bd/%e0%a6%ae%e0%a6%a8%e0%a6%b8%e0%a7%81%e0%a6%b0-%e0%a6%86%e0%a6%b2%e0%a7%80-%e0%a6%8f%e0%a6%ac%e0%a6%82-%e0%a6%86%e0%a6%a4%e0%a6%be%e0%a6%b9%e0%a6%be%e0%a6%b0%e0%a7%81%e0%a6%a8%e0%a7%8d%e0%a6%a8/` | **200 OK** | মনসুর আলী এবং আতাহারুন্নেসা বৃত্তি - RMU University |
| 10 | `https://rmu.ac.bd/official-facebook-page-operated-without-authorization/` | **200 OK** | বিশ্ববিদ্যালয়ের অফিসিয়াল ফেসবুক পেজটি অনুমোদনবিহীনভাবে পরিচালিত হচ্ছে |
| 11 | `https://rmu.ac.bd/fall-2023/` | **200 OK** | Admission Open Fall-2023 - RMU University |

---

## 2. Inaccessible / Broken URLs on the Reference Site

During navigation inspection, several menu links on the reference site were found to be broken:

| URL | Observed Status | Discrepancy Analysis |
|-----|----------------|----------------------|
| `https://rmu.ac.bd/departments/` | **404 Not Found** | The navigation bar contains a "Departments" link, but WordPress returns a 404 page. In our reconstruction, a dedicated, database-backed `departments.php` page must be provided. |
| `https://rmu.ac.bd/chairman-message/` | **404 Not Found** | Listed in menu under leadership, but missing on server. |
| `https://rmu.ac.bd/vice-chancellor-message/` | **404 Not Found** | Listed in menu under leadership, but missing on server. |
| `https://rmu.ac.bd/treasurer-message/` | **404 Not Found** | Listed in menu under leadership, but missing on server. |
| `https://rmu.ac.bd/cfi` | **301 Moved** | Center for Innovation link redirects. |

*Reconstruction Decision:* For these missing pages, provide clean, structured educational placeholder pages and full admin management so university administrators can publish and update leadership messages and individual department pages seamlessly.

---

## 3. Header, Top Bar & Navigation Structure

### Top Announcement Bar
- **Background Color:** `#636363` (Dark Slate Gray)
- **Text Color:** `#ffffff`
- **Left Column:** Follow us social links:
  - Facebook (`https://www.facebook.com/rmukushtia`)
  - LinkedIn (`https://www.linkedin.com/school/rmukushtia/`)
  - WhatsApp (`https://wa.me/8801302991188`)
- **Right Column:**
  - Phone: `+8801302991188` (Call Now)
  - Email: `info@rmu.ac.bd` (Email Now)
  - Mobile Admission Button: "ADMISSION OPEN" (`https://wa.me/8801302991188`)

### University Branding Header
- Clean white background (`#ffffff`)
- University Monogram / Logo: RMU Emblem with Rabindranath Tagore silhouette, open book, and educational laurels
- Bengali Title: **রবীন্দ্র মৈত্রী বিশ্ববিদ্যালয়, কুষ্টিয়া**
- English Title: **Rabindra Maitree University, Kushtia**
- Motto / Subtitle: "This University is our dream" / Founded in 2015

### Main Navigation Bar
- Responsive navbar with desktop hover dropdowns and mobile offcanvas / collapse menu
- Active item indicator and smooth transitions
- Navigation links:
  1. **Home** (`/index.php`)
  2. **About Us** (`/about.php` with dropdowns: Overview, History, Vision & Mission, Leadership Messages, Board of Trustees)
  3. **Faculties** (`/faculties.php` with dropdowns for Arts & Social Science, Engineering, Business Studies, Agriculture)
  4. **Departments** (`/departments.php`)
  5. **Academic Programs** (`/programs.php`)
  6. **Admission** (`/admissions.php` with dropdowns: Admission Overview, Requirements, Online Application, Fees & Scholarships, FAQs)
  7. **Notices** (`/notices.php`)
  8. **Events & News** (`/events.php`, `/news.php`)
  9. **Downloads** (`/downloads.php`)
  10. **Contact Us** (`/contact.php`)
  11. **Apply Online** (Prominent Action CTA Button)

---

## 4. Visual Design Tokens & Palette

The reference website uses GeneratePress and Elementor with the following core tokens:

```css
:root {
  --rmu-primary: #1e73be;       /* Reference Academic Blue */
  --rmu-primary-dark: #135084;  /* Darker blue hover state */
  --rmu-primary-light: #e7f5fe; /* Soft accent background */
  --rmu-accent: #cf2e2e;        /* Notice/Alert Red */
  --rmu-dark: #222222;          /* Body text / contrast */
  --rmu-muted: #575760;         /* Secondary text */
  --rmu-border: #e2e8f0;        /* Subtle borders */
  --rmu-light-bg: #f7f8f9;      /* Section light background */
  --rmu-topbar: #636363;        /* Top announcement bar */
  --rmu-white: #ffffff;         /* Cards and main container */
  --rmu-font-sans: 'Poppins', 'Roboto', 'Hind Siliguri', sans-serif;
  --rmu-font-serif: 'Roboto Slab', serif;
  --rmu-shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
  --rmu-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
  --rmu-radius: 8px;
}
```

---

## 5. Academic Structure (Verified Faculties & Programs)

The live reference website verifies the following faculty structure:

### 1. Faculty of Arts and Social Science
- **Department of English** — BA (Hons) in English, MA in English
- **Department of Bangla** — BA (Hons) in Bangla, MA in Bangla
- **Department of Fine Arts** — Bachelor of Fine Arts (BFA)
- **Department of Music** — Bachelor of Music (BMus)
- **Department of Physical Education and Sports Science** — MPED (Master of Physical Education)

### 2. Faculty of Engineering & Technology
- **Department of Computer Science and Engineering (CSE)** — BSc Engg in CSE
- **Department of Electrical and Electronic Engineering (EEE)** — BSc Engg in EEE
- **Department of Information and Communication Technology (ICT)** — BSc Engg in ICT
- **Department of Microbiology** — BSc (Hons) in Microbiology

### 3. Faculty of Business Studies
- **Department of Business Administration** — Bachelor of Business Administration (BBA), Master of Business Administration (MBA)

### 4. Faculty of Agriculture
- **Department of Agriculture** — BSc (Hons) in Agriculture

---

## 6. Online Admission System (Field Audit)

The public admission form at `https://rmu.ac.bd/rmu-admission/` was analyzed in detail. The verified input fields are:

1. **Full Name** (`text`, required): Candidate's full name.
2. **Mobile Number** (`tel`/`text`, required): Bangladeshi mobile contact (11 digits, e.g., `01XXXXXXXXX`).
3. **Last College Name** (`text`, required): Previous college / institution attended.
4. **S.S.C GPA** (`number`/`decimal`, required, min 1.00, max 5.00): Secondary School Certificate GPA.
5. **H.S.C GPA** (`number`/`decimal`, required, min 1.00, max 5.00): Higher Secondary Certificate GPA.
6. **Home District** (`text`, required): Applicant's home district in Bangladesh.
7. **HSC Group** (`radio`, required):
   - Humanities (মান মানবিক)
   - Science (বিজ্ঞান)
   - Commerce (ব্যবসায় শিক্ষা)
8. **Interested subject to admit in RMU** (`select` / `radio`, required):
   - BSc Engg in EEE
   - BSc Engg in CSE
   - BSc Engg in ICT
   - BSc Engg in Microbiology
   - BSc Engg in Agriculture
   - BA in Bangla
   - BA in English
   - BA in Fine Arts
   - BA in Music
   - MA in Bangla
   - MA in English
   - MPED
9. **Have you any quota?** (`radio`, required):
   - YES
   - NO
10. **Application Submission:** Generates an application tracking reference (e.g., `RMU-2026-XXXX`), calculates aggregate eligibility, prevents duplicate submissions, and stores record for admin evaluation.

---

## 7. Notices, Scholarships & Events Audit

Verified authentic notice items from the live database:
1. **ভর্তি মেলা ফল-২০২৬-এর শুভ উদ্বোধন (Admission Fair Fall 2026 Opening)** — Admission fair with special waiver on admission fees.
2. **মনসুর আলী এবং আতাহারুন্নেসা বৃত্তি (Mansur Ali and Atharunnesa Scholarship)** — Need- and merit-based financial aid.
3. **মোহাম্মদ ফজলি হোসেন স্মারক ট্রাস্ট তহবিল বৃত্তি (Mohammad Fazli Hossain Memorial Trust Fund Scholarship)** — Endowed scholarship for meritorious students.
4. **হামিম খান স্মৃতি পুরস্কার (Hamim Khan Memorial Award)** — Academic excellence award.
5. **ঐতিহাসিক মুজিবনগর দিবস উদযাপন সংক্রান্ত বিজ্ঞপ্তি (Historic Mujibnagar Day Celebration)** — National commemoration event.
6. **Admission Open Circular** — Eligibility criteria, application deadline, and counseling schedule.

---

## 8. Footer & University Contact Information

Verified authentic institutional contact details:
- **Campus Address:** 97/71, Ram Chandra Roy, Jahid Chowdhury Rd, Kushtia 7000, Bangladesh
- **Contact Telephone:** +8801302991188
- **Official Inquiries Email:** `info@rmu.ac.bd`
- **Social Media:** Facebook (`rmukushtia`), LinkedIn (`rmukushtia`), WhatsApp (`+8801302991188`)
- **Office Hours:** Saturday to Thursday: 9:00 AM – 5:00 PM (Closed on Fridays and National Holidays)
- **Accreditation / Foundation:** Approved by UGC (University Grants Commission of Bangladesh) and Ministry of Education, Established in 2015.

---

## 9. Discrepancies & Reconstruction Strategy

1. **Broken Routes in Reference Site:** The live site has dead links for `/departments/` and leadership messages. The reconstruction implements working pages with database persistence and full admin control.
2. **Missing Tuition Schedule on Reference:** The live reference mentions waivers and admission fees in notices but has no central tuition page. The reconstruction provides a clear tuition fees and scholarship schedule table with clear educational demonstration disclaimers.
3. **Admin Panel:** The live site uses standard WordPress wp-admin. We will implement a custom, lightweight, highly secure PHP admin panel with role-based access control, CSRF tokens, PDO prepared statements, and session protection.
4. **Bangla & English Typography:** The reconstructed layout utilizes clean Google Fonts (`Poppins` and `Hind Siliguri`) with full UTF-8 (`utf8mb4`) database encoding to display both English and Bangla accurately.

