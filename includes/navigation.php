<?php
/**
 * Rabindra Maitree University (RMU) Navigation Bar Template
 * With Right-Side Three-Dot (⋮) Menu Button & Responsive Academic Drawer
 */
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$facultyTree = get_faculties_menu();

// Retrieve official programs and curriculums from centralized source
$academicProgramsWithCurriculum = array_values(get_all_programs_curriculum());

// Flat list reference for legacy compatibility
$academicPrograms = array_map(function($p) {
    return ['title' => $p['title'], 'slug' => $p['slug']];
}, $academicProgramsWithCurriculum);
?>
<nav class="rmu-navbar" aria-label="Main Navigation">
  <div class="container nav-container">
    <!-- Desktop Horizontal Navigation (Visible on Desktop >= 992px) -->
    <ul class="nav-desktop-bar" id="desktopNav">
      <!-- 1. Home -->
      <li class="desktop-nav-item <?= ($currentPage === 'index.php') ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/index.php" class="desktop-nav-link">
          <i class="fas fa-home"></i> Home
        </a>
      </li>

      <!-- 2. Programs Dropdown (All Academic Programs & Curriculums) -->
      <li class="desktop-nav-item has-desktop-dropdown <?= in_array($currentPage, ['programs.php', 'program-detail.php']) ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/programs.php" class="desktop-nav-link">
          <i class="fas fa-graduation-cap"></i> Programs <i class="fas fa-chevron-down" style="font-size:0.75rem; margin-left:3px;"></i>
        </a>
        <div class="desktop-dropdown-menu">
          <div class="desktop-dropdown-header">
            <strong><i class="fas fa-book-reader"></i> Academic Programs &amp; 4-Year Curricula</strong>
            <a href="<?= APP_URL ?>/programs.php" class="desktop-view-all-link">Browse All Programs &rarr;</a>
          </div>
          <div class="desktop-programs-grid">
            <?php foreach ($academicProgramsWithCurriculum as $dp): ?>
              <a href="<?= APP_URL ?>/program-detail.php?slug=<?= e($dp['slug']) ?>" class="desktop-program-card">
                <span class="desktop-prog-num"><?= (int)$dp['id'] ?></span>
                <span class="desktop-prog-info">
                  <span class="desktop-prog-title"><?= e($dp['title']) ?></span>
                  <small class="desktop-prog-dept"><?= e($dp['department']) ?> • 4 Years</small>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
          <div class="desktop-dropdown-footer">
            <a href="<?= APP_URL ?>/programs.php" class="btn-browse-all-curricula">
              <i class="fas fa-th-list"></i> View Complete Year-Wise Subjects &amp; Course Codes &rarr;
            </a>
          </div>
        </div>
      </li>

      <!-- 3. Admission Dropdown -->
      <li class="desktop-nav-item has-desktop-dropdown <?= in_array($currentPage, ['admissions.php', 'admission-form.php', 'admission-status.php', 'tuition-fees.php', 'scholarships.php', 'faqs.php']) ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/admissions.php" class="desktop-nav-link">
          <i class="fas fa-user-graduate"></i> Admission <i class="fas fa-chevron-down" style="font-size:0.75rem; margin-left:3px;"></i>
        </a>
        <ul class="desktop-simple-dropdown">
          <li><a href="<?= APP_URL ?>/student/login.php" style="color:var(--rmu-primary); font-weight:700;"><i class="fas fa-user-graduate"></i> Student Portal Login</a></li>
          <li><a href="<?= APP_URL ?>/admissions.php"><i class="fas fa-info-circle"></i> Overview &amp; Requirements</a></li>
          <li><a href="<?= APP_URL ?>/admission-form.php" style="color:var(--rmu-accent); font-weight:700;"><i class="fas fa-edit"></i> Online Admission Form</a></li>
          <li><a href="<?= APP_URL ?>/admission-status.php"><i class="fas fa-search-location"></i> Track Status</a></li>
          <li><a href="<?= APP_URL ?>/tuition-fees.php"><i class="fas fa-calculator"></i> Tuition Fees</a></li>
          <li><a href="<?= APP_URL ?>/scholarships.php"><i class="fas fa-award"></i> Scholarships</a></li>
          <li><a href="<?= APP_URL ?>/faqs.php"><i class="fas fa-question-circle"></i> FAQs</a></li>
        </ul>
      </li>

      <!-- 4. Notice Board -->
      <li class="desktop-nav-item has-desktop-dropdown <?= in_array($currentPage, ['notices.php', 'notice-detail.php']) ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/notices.php" class="desktop-nav-link">
          <i class="fas fa-bullhorn"></i> Notice Board <i class="fas fa-chevron-down" style="font-size:0.75rem; margin-left:3px;"></i>
        </a>
        <ul class="desktop-simple-dropdown">
          <li><a href="<?= APP_URL ?>/notices.php"><i class="fas fa-list"></i> All Notices</a></li>
          <li><a href="<?= APP_URL ?>/notices.php"><i class="fas fa-book"></i> Academic Notices</a></li>
          <li><a href="<?= APP_URL ?>/notices.php"><i class="fas fa-user-plus"></i> Admission Notices</a></li>
        </ul>
      </li>

      <!-- 5. Contact -->
      <li class="desktop-nav-item <?= ($currentPage === 'contact.php') ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/contact.php" class="desktop-nav-link">
          <i class="fas fa-phone-alt"></i> Contact
        </a>
      </li>

      <!-- 6. Campus -->
      <li class="desktop-nav-item has-desktop-dropdown <?= ($currentPage === 'campus.php') ? 'active' : '' ?>">
        <a href="<?= APP_URL ?>/campus.php" class="desktop-nav-link">
          <i class="fas fa-university"></i> Campus <i class="fas fa-chevron-down" style="font-size:0.75rem; margin-left:3px;"></i>
        </a>
        <ul class="desktop-simple-dropdown">
          <li><a href="<?= APP_URL ?>/campus.php"><i class="fas fa-map-marked-alt"></i> Campus Facilities</a></li>
          <li><a href="<?= APP_URL ?>/campus.php#classrooms"><i class="fas fa-chalkboard"></i> Modern Classrooms</a></li>
          <li><a href="<?= APP_URL ?>/campus.php#labs"><i class="fas fa-laptop-code"></i> Science &amp; Engineering Labs</a></li>
          <li><a href="<?= APP_URL ?>/campus.php#library"><i class="fas fa-book-reader"></i> Central Library</a></li>
        </ul>
      </li>
    </ul>

    <!-- Right Actions: Quick Search Bar + Three-Dot (⋮) Menu Button -->
    <div class="nav-right-actions">
      <!-- Quick Search Widget -->
      <div class="nav-search-wrap">
        <form action="<?= APP_URL ?>/search.php" method="GET" class="nav-search-form">
          <input type="text" name="q" placeholder="Search notices, programs..." aria-label="Search site">
          <button type="submit" aria-label="Submit Search">
            <i class="fas fa-search"></i>
          </button>
        </form>
      </div>

      <!-- Three-Dot (⋮) Navigation Menu Button -->
      <button type="button" 
              class="nav-toggle-btn" 
              id="navToggleBtn" 
              aria-label="Toggle Navigation Menu" 
              aria-expanded="false" 
              onclick="if(window.toggleDrawerMenu){window.toggleDrawerMenu(event);}"
              title="Navigation Menu">
        <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
      </button>
    </div>

    <!-- Navigation Drawer (Slide-out menu containing all items) -->
    <ul class="nav-menu" id="primaryNav">
      <!-- Navigation Drawer Header -->
      <li class="nav-drawer-header">
        <div class="nav-drawer-brand">
          <img src="<?= APP_URL ?>/assets/images/logo.jpg" alt="RMU Monogram" class="nav-drawer-logo" onerror="this.src='<?= APP_URL ?>/assets/images/logo.svg'">
          <div>
            <strong>Rabindra Maitree University</strong>
            <small>Academic &amp; Student Portal</small>
          </div>
        </div>
        <button type="button" class="nav-drawer-close-btn" id="navDrawerCloseBtn" aria-label="Close navigation menu" onclick="if(window.toggleDrawerMenu){window.toggleDrawerMenu(event);}">&times;</button>
      </li>

      <!-- 1. Home -->
      <li class="nav-item <?= ($currentPage === 'index.php') ? 'active' : '' ?>" data-nav-id="home">
        <a href="<?= APP_URL ?>/index.php" class="nav-link">
          <span><i class="fas fa-home nav-item-icon"></i> Home</span>
        </a>
      </li>

      <!-- 2. Programs Dropdown with Complete Academic Subject Curriculums -->
      <li class="nav-item has-dropdown has-custom-arrow programs-nav-item <?= in_array($currentPage, ['programs.php', 'program-detail.php']) ? 'active' : '' ?>" data-nav-id="programs">
        <a href="javascript:void(0);" 
           class="nav-link dropdown-toggle" 
           id="programsMenuLink"
           role="button" 
           aria-haspopup="true" 
           aria-expanded="false" 
           aria-controls="programsDropdownMenu"
           onclick="if(window.toggleProgramsDropdown){window.toggleProgramsDropdown(event);}">
          <span><i class="fas fa-graduation-cap nav-item-icon"></i> Programs</span>
          <i class="fas fa-chevron-down dropdown-arrow" aria-hidden="true"></i>
        </a>
        
        <!-- Programs Dropdown Menu (Accordion with all academic programs & their year-wise subjects) -->
        <ul class="dropdown-menu programs-dropdown-menu" id="programsDropdownMenu" role="menu" aria-labelledby="programsMenuLink" style="display: none;">
          <?php foreach ($academicProgramsWithCurriculum as $progIdx => $prog): ?>
            <li class="program-accordion-item" data-program-slug="<?= e($prog['slug']) ?>">
              <!-- Program Header Button (Clickable Accordion Toggle) -->
              <button type="button" 
                      class="program-accordion-header" 
                      id="programHeader-<?= e($prog['slug']) ?>" 
                      aria-expanded="false" 
                      aria-controls="programSubjects-<?= e($prog['slug']) ?>"
                      onclick="if(window.toggleProgramSubjects){window.toggleProgramSubjects('<?= e($prog['slug']) ?>', event);}">
                <span class="program-header-info">
                  <span class="program-number"><?= (int)$prog['id'] ?></span>
                  <span class="program-title-block">
                    <span class="program-title-text"><?= e($prog['title']) ?></span>
                    <small class="program-dept-text" style="display:block; font-size:0.72rem; color:#64748b; font-weight:500;"><?= e($prog['department']) ?></small>
                  </span>
                </span>
                <i class="fas fa-chevron-down program-arrow" aria-hidden="true"></i>
              </button>

              <!-- Program Subjects Collapse Container (Organized by Academic Year) -->
              <div class="program-subjects-collapse" 
                   id="programSubjects-<?= e($prog['slug']) ?>" 
                   aria-labelledby="programHeader-<?= e($prog['slug']) ?>"
                   style="display: none;">
                <div class="program-subjects-inner">
                  <?php foreach ($prog['years'] as $yearName => $subjects): ?>
                    <div class="academic-year-card">
                      <div class="academic-year-title">
                        <i class="fas fa-calendar-alt"></i>
                        <span><?= e($yearName) ?></span>
                        <span class="year-subject-count"><?= count($subjects) ?> Subjects</span>
                      </div>
                      <ul class="subject-curriculum-list">
                        <?php foreach ($subjects as $subj): ?>
                          <li class="subject-curriculum-item">
                            <span class="subject-code"><?= e($subj['code']) ?></span>
                            <span class="subject-name"><?= e($subj['name']) ?></span>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  <?php endforeach; ?>

                  <div class="program-action-footer">
                    <a href="<?= APP_URL ?>/program-detail.php?slug=<?= e($prog['slug']) ?>" class="program-detail-btn">
                      <i class="fas fa-external-link-alt"></i> View Program Overview &amp; Fees &rarr;
                    </a>
                  </div>
                </div>
              </div>
            </li>
          <?php endforeach; ?>

          <li class="programs-dropdown-footer">
            <a href="<?= APP_URL ?>/programs.php" class="view-all-programs-btn">
              <i class="fas fa-book-open"></i> Browse All Academic Programs with Filters &rarr;
            </a>
          </li>
        </ul>
      </li>

      <!-- 3. Admission Dropdown -->
      <li class="nav-item has-dropdown has-custom-arrow <?= in_array($currentPage, ['admissions.php', 'admission-form.php', 'admission-status.php', 'tuition-fees.php', 'scholarships.php', 'faqs.php']) ? 'active' : '' ?>" data-nav-id="admission">
        <a href="javascript:void(0);" class="nav-link dropdown-toggle" role="button" aria-haspopup="true" aria-expanded="false" onclick="if(window.toggleSubmenu){window.toggleSubmenu(this, event);}">
          <span><i class="fas fa-user-graduate nav-item-icon"></i> Admission</span>
          <i class="fas fa-chevron-down dropdown-arrow" aria-hidden="true"></i>
        </a>
        <ul class="dropdown-menu">
          <li><a href="<?= APP_URL ?>/student/login.php" class="dropdown-item" style="color:var(--rmu-primary); font-weight:700;"><i class="fas fa-user-graduate"></i> Student Portal Login</a></li>
          <li><a href="<?= APP_URL ?>/admissions.php" class="dropdown-item"><strong>Admission Overview &amp; Requirements</strong></a></li>
          <li><a href="<?= APP_URL ?>/admission-form.php" class="dropdown-item program-highlight" style="color:var(--rmu-accent); font-weight:700;"><i class="fas fa-file-signature"></i> Online Admission Form</a></li>
          <li><a href="<?= APP_URL ?>/admission-status.php" class="dropdown-item"><i class="fas fa-check-circle"></i> Application Status Tracking</a></li>
          <li><a href="<?= APP_URL ?>/tuition-fees.php" class="dropdown-item"><i class="fas fa-calculator"></i> Tuition Fees &amp; Expenses</a></li>
          <li><a href="<?= APP_URL ?>/scholarships.php" class="dropdown-item"><i class="fas fa-award"></i> Scholarships &amp; Waivers</a></li>
          <li><a href="<?= APP_URL ?>/faqs.php" class="dropdown-item"><i class="fas fa-question-circle"></i> Admission FAQs</a></li>
        </ul>
      </li>

      <!-- 4. Notice Board Dropdown -->
      <li class="nav-item has-dropdown has-custom-arrow <?= in_array($currentPage, ['notices.php', 'notice-detail.php']) ? 'active' : '' ?>" data-nav-id="notices">
        <a href="javascript:void(0);" class="nav-link dropdown-toggle" role="button" aria-haspopup="true" aria-expanded="false" onclick="if(window.toggleSubmenu){window.toggleSubmenu(this, event);}">
          <span><i class="fas fa-bullhorn nav-item-icon"></i> Notice Board</span>
          <i class="fas fa-chevron-down dropdown-arrow" aria-hidden="true"></i>
        </a>
        <ul class="dropdown-menu">
          <li><a href="<?= APP_URL ?>/notices.php" class="dropdown-item"><strong>All University Notices &amp; Circulars</strong></a></li>
          <li><a href="<?= APP_URL ?>/notices.php" class="dropdown-item"><i class="fas fa-book"></i> Academic Notices</a></li>
          <li><a href="<?= APP_URL ?>/notices.php" class="dropdown-item"><i class="fas fa-user-plus"></i> Admission Circulars</a></li>
          <li><a href="<?= APP_URL ?>/notices.php" class="dropdown-item"><i class="fas fa-pen-alt"></i> Exam &amp; Routine Notices</a></li>
        </ul>
      </li>

      <!-- 5. Contact -->
      <li class="nav-item <?= ($currentPage === 'contact.php') ? 'active' : '' ?>" data-nav-id="contact">
        <a href="<?= APP_URL ?>/contact.php" class="nav-link">
          <span><i class="fas fa-phone-alt nav-item-icon"></i> Contact</span>
        </a>
      </li>

      <!-- 6. Campus & Facilities Dropdown -->
      <li class="nav-item has-dropdown has-custom-arrow <?= in_array($currentPage, ['campus.php']) ? 'active' : '' ?>" data-nav-id="campus">
        <a href="javascript:void(0);" class="nav-link dropdown-toggle" role="button" aria-haspopup="true" aria-expanded="false" onclick="if(window.toggleSubmenu){window.toggleSubmenu(this, event);}">
          <span><i class="fas fa-university nav-item-icon"></i> Campus</span>
          <i class="fas fa-chevron-down dropdown-arrow" aria-hidden="true"></i>
        </a>
        <ul class="dropdown-menu">
          <li><a href="<?= APP_URL ?>/campus.php" class="dropdown-item"><strong>Kushtia Campus &amp; Facilities Overview</strong></a></li>
          <li><a href="<?= APP_URL ?>/campus.php#classrooms" class="dropdown-item"><i class="fas fa-chalkboard"></i> Modern Multimedia Classrooms</a></li>
          <li><a href="<?= APP_URL ?>/campus.php#labs" class="dropdown-item"><i class="fas fa-laptop-code"></i> Engineering &amp; Science Labs</a></li>
          <li><a href="<?= APP_URL ?>/campus.php#library" class="dropdown-item"><i class="fas fa-book-reader"></i> Central Library &amp; Archives</a></li>
          <li><a href="<?= APP_URL ?>/campus.php#cultural" class="dropdown-item"><i class="fas fa-music"></i> Cultural &amp; Recreation Center</a></li>
          <li><a href="<?= APP_URL ?>/contact.php" class="dropdown-item"><i class="fas fa-map-marker-alt"></i> Campus Location &amp; Visit</a></li>
        </ul>
      </li>
    </ul>
  </div>
</nav>

<!-- Comprehensive Styles for Three-Dot Menu Drawer & Academic Programs Accordion -->
<style>
  /* Three-Dot (⋮) Button Styling */
  .nav-toggle-btn {
    background: #1e73be !important;
    border: 1.5px solid #1e73be !important;
    color: #ffffff !important;
    width: 40px !important;
    min-width: 40px !important;
    height: 40px !important;
    padding: 0 !important;
    border-radius: 6px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 1.25rem !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
    box-shadow: 0 2px 6px rgba(30, 115, 190, 0.35) !important;
    margin-left: 0.4rem !important;
  }
  
  .nav-toggle-btn:hover,
  .nav-toggle-btn:focus,
  .nav-toggle-btn.active {
    background: #16568c !important;
    border-color: #16568c !important;
    box-shadow: 0 4px 12px rgba(22, 86, 140, 0.45) !important;
    transform: translateY(-1px) !important;
  }

  /* Icon styling for clean single-line rows */
  .nav-item-icon {
    width: 22px;
    text-align: center;
    margin-right: 8px;
    color: #1e73be;
    font-size: 1.05rem;
    display: inline-block;
  }

  /* Programs Dropdown Container inside Drawer */
  .programs-nav-item.dropdown-show #programsDropdownMenu,
  #programsDropdownMenu.show {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: static !important;
    max-height: 460px !important;
    overflow-y: auto !important;
    height: auto !important;
  }
  
  #programsDropdownMenu {
    display: none;
    position: static !important;
    background-color: #f1f5f9 !important;
    box-shadow: none !important;
    border: 1px solid #cbd5e1 !important;
    border-left: 4px solid #1e73be !important;
    border-radius: 8px !important;
    margin: 0.5rem 0 0.85rem 0 !important;
    padding: 0.5rem !important;
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    list-style: none !important;
    box-sizing: border-box !important;
  }

  .programs-dropdown-header {
    padding: 0.4rem 0.5rem 0.65rem 0.5rem;
    border-bottom: 1px solid #cbd5e1;
    margin-bottom: 0.5rem;
  }

  .programs-header-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.45rem;
  }

  .programs-header-title i {
    color: #1e73be;
    font-size: 1rem;
  }

  .programs-header-subtitle {
    font-size: 0.76rem;
    color: #64748b;
    margin-top: 0.2rem;
    line-height: 1.35;
  }

  /* Program Accordion Item */
  .program-accordion-item {
    margin-bottom: 0.45rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    overflow: hidden;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
  }

  .program-accordion-item:hover,
  .program-accordion-item.active {
    border-color: #93c5fd;
    box-shadow: 0 2px 6px rgba(30, 115, 190, 0.1);
  }

  /* Program Header Button (Clickable Dropdown Toggle) */
  .program-accordion-header {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.65rem 0.75rem;
    background: #ffffff;
    border: none;
    text-align: left;
    cursor: pointer;
    font-family: inherit;
    transition: background-color 0.15s ease;
    outline: none;
    gap: 0.5rem;
  }

  .program-accordion-header:hover,
  .program-accordion-header:focus,
  .program-accordion-item.active .program-accordion-header {
    background-color: #f8fafc;
  }

  .program-header-info {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    flex: 1;
    min-width: 0;
  }

  .program-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    background-color: #e0f2fe;
    color: #0369a1;
    border-radius: 50%;
    font-size: 0.75rem;
    font-weight: 700;
    flex-shrink: 0;
  }

  .program-accordion-item.active .program-number {
    background-color: #1e73be;
    color: #ffffff;
  }

  .program-title-text {
    font-size: 0.88rem;
    font-weight: 600;
    color: #1e293b;
    line-height: 1.35;
    word-break: break-word;
  }

  .program-arrow {
    font-size: 0.78rem;
    color: #94a3b8;
    transition: transform 0.25s ease, color 0.25s ease;
    flex-shrink: 0;
  }

  .program-accordion-item.active .program-arrow {
    transform: rotate(180deg);
    color: #1e73be;
  }

  /* Program Subjects Collapse Section (Organized by Academic Year) */
  .program-subjects-collapse {
    display: none;
    background-color: #f8fafc;
    border-top: 1px solid #e2e8f0;
    padding: 0.65rem;
  }

  .program-accordion-item.active .program-subjects-collapse {
    display: block !important;
  }

  /* Academic Year Card */
  .academic-year-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    padding: 0.55rem 0.65rem;
    margin-bottom: 0.5rem;
  }

  .academic-year-card:last-of-type {
    margin-bottom: 0.35rem;
  }

  .academic-year-title {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #1e73be;
    padding-bottom: 0.3rem;
    border-bottom: 1px solid #f1f5f9;
    margin-bottom: 0.4rem;
  }

  .academic-year-title i {
    font-size: 0.85rem;
  }

  .year-subject-count {
    margin-left: auto;
    font-size: 0.7rem;
    font-weight: 600;
    color: #475569;
    background: #f1f5f9;
    padding: 1px 6px;
    border-radius: 10px;
    text-transform: none;
    letter-spacing: normal;
  }

  /* Subject Curriculum List */
  .subject-curriculum-list {
    list-style: none !important;
    padding: 0 !important;
    margin: 0 !important;
  }

  .subject-curriculum-item {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    padding: 0.28rem 0;
    font-size: 0.81rem;
    line-height: 1.4;
    border-bottom: 1px dashed #f1f5f9;
  }

  .subject-curriculum-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
  }

  .subject-code {
    display: inline-block;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.72rem;
    font-weight: 700;
    color: #334155;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    padding: 1px 5px;
    border-radius: 3px;
    white-space: nowrap;
    flex-shrink: 0;
  }

  .subject-name {
    color: #334155;
    font-weight: 500;
    flex: 1;
    word-break: break-word;
  }

  /* Program Action Footer */
  .program-action-footer {
    margin-top: 0.5rem;
    text-align: right;
  }

  .program-detail-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: #1e73be !important;
    text-decoration: none !important;
    padding: 0.35rem 0.6rem;
    border-radius: 4px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    transition: all 0.15s ease;
  }

  .program-detail-btn:hover {
    background: #1e73be;
    color: #ffffff !important;
  }

  /* Programs Dropdown Footer */
  .programs-dropdown-footer {
    margin-top: 0.55rem;
    padding-top: 0.4rem;
    border-top: 1px solid #cbd5e1;
  }

  .view-all-programs-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.55rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: #ffffff !important;
    background: #1e73be;
    border-radius: 5px;
    text-decoration: none !important;
    transition: background-color 0.2s ease;
  }

  .view-all-programs-btn:hover {
    background: #16568c;
    color: #ffffff !important;
  }

  .programs-nav-item.dropdown-show #programsMenuLink .dropdown-arrow {
    transform: rotate(180deg) !important;
    color: #1e73be !important;
  }
</style>

<!-- Self-Contained JavaScript for Instant, Cache-Proof Drawer and Programs Multi-Level Accordion -->
<script>
(function() {
  // Toggle Program Subjects Accordion (Level 2: Program -> 4 Academic Years & Subjects)
  window.toggleProgramSubjects = function(slug, e) {
    if (e) {
      if (typeof e.preventDefault === 'function') e.preventDefault();
      if (typeof e.stopPropagation === 'function') e.stopPropagation();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
    }
    var item = document.querySelector('.program-accordion-item[data-program-slug="' + slug + '"]');
    if (!item) return;
    var header = document.getElementById('programHeader-' + slug);
    var subjects = document.getElementById('programSubjects-' + slug);
    var isExpanded = item.classList.contains('active');

    if (isExpanded) {
      item.classList.remove('active');
      if (header) header.setAttribute('aria-expanded', 'false');
      if (subjects) {
        subjects.style.setProperty('display', 'none', 'important');
      }
    } else {
      // Close other program accordions for clean single-program view
      document.querySelectorAll('.program-accordion-item').forEach(function(other) {
        if (other !== item) {
          other.classList.remove('active');
          var otherHeader = other.querySelector('.program-accordion-header');
          if (otherHeader) otherHeader.setAttribute('aria-expanded', 'false');
          var otherSubjects = other.querySelector('.program-subjects-collapse');
          if (otherSubjects) {
            otherSubjects.style.setProperty('display', 'none', 'important');
          }
        }
      });
      item.classList.add('active');
      if (header) header.setAttribute('aria-expanded', 'true');
      if (subjects) {
        subjects.style.setProperty('display', 'block', 'important');
        subjects.style.setProperty('visibility', 'visible', 'important');
        subjects.style.setProperty('opacity', '1', 'important');
      }
    }
  };

  // Expand All Program Subjects
  window.expandAllProgramSubjects = function(e) {
    if (e) {
      if (typeof e.preventDefault === 'function') e.preventDefault();
      if (typeof e.stopPropagation === 'function') e.stopPropagation();
    }
    document.querySelectorAll('.program-accordion-item').forEach(function(item) {
      item.classList.add('active');
      var h = item.querySelector('.program-accordion-header');
      if (h) h.setAttribute('aria-expanded', 'true');
      var s = item.querySelector('.program-subjects-collapse');
      if (s) {
        s.style.setProperty('display', 'block', 'important');
        s.style.setProperty('visibility', 'visible', 'important');
        s.style.setProperty('opacity', '1', 'important');
      }
    });
  };

  // Collapse All Program Subjects
  window.collapseAllProgramSubjects = function(e) {
    if (e) {
      if (typeof e.preventDefault === 'function') e.preventDefault();
      if (typeof e.stopPropagation === 'function') e.stopPropagation();
    }
    document.querySelectorAll('.program-accordion-item').forEach(function(item) {
      item.classList.remove('active');
      var h = item.querySelector('.program-accordion-header');
      if (h) h.setAttribute('aria-expanded', 'false');
      var s = item.querySelector('.program-subjects-collapse');
      if (s) {
        s.style.setProperty('display', 'none', 'important');
      }
    });
  };

  // Toggle Programs Dropdown Menu (Level 1: Menu -> 8 Programs)
  window.toggleProgramsDropdown = function(e) {
    if (e) {
      if (typeof e.preventDefault === 'function') e.preventDefault();
      if (typeof e.stopPropagation === 'function') e.stopPropagation();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
    }

    var menu = document.getElementById('programsDropdownMenu');
    var parent = document.querySelector('.programs-nav-item');
    var link = document.getElementById('programsMenuLink');
    if (!menu) return;

    var isOpen = (menu.style.display === 'block') || menu.classList.contains('show') || (parent && parent.classList.contains('dropdown-show'));

    // Close any other open submenus first
    document.querySelectorAll('.rmu-navbar .nav-item.has-dropdown').forEach(function(el) {
      if (el !== parent) {
        el.classList.remove('dropdown-show');
        var d = el.querySelector('.dropdown-menu');
        if (d) {
          d.classList.remove('show');
          d.style.setProperty('display', 'none', 'important');
        }
        var l = el.querySelector('.dropdown-toggle');
        if (l) l.setAttribute('aria-expanded', 'false');
      }
    });

    if (isOpen) {
      if (parent) parent.classList.remove('dropdown-show');
      if (link) link.setAttribute('aria-expanded', 'false');
      menu.classList.remove('show');
      menu.style.setProperty('display', 'none', 'important');
    } else {
      if (parent) parent.classList.add('dropdown-show');
      if (link) link.setAttribute('aria-expanded', 'true');
      menu.classList.add('show');
      menu.style.setProperty('display', 'block', 'important');
      menu.style.setProperty('visibility', 'visible', 'important');
      menu.style.setProperty('opacity', '1', 'important');
      menu.style.setProperty('position', 'static', 'important');
      menu.style.setProperty('max-height', '460px', 'important');
      menu.style.setProperty('overflow-y', 'auto', 'important');
    }
  };
  window.toggleProgramsSubmenu = window.toggleProgramsDropdown;

  // Generic Submenu Toggle (Admission, Notice Board, Campus)
  window.toggleSubmenu = function(toggleEl, e) {
    if (e) {
      if (typeof e.preventDefault === 'function') e.preventDefault();
      if (typeof e.stopPropagation === 'function') e.stopPropagation();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
    }
    if (!toggleEl) return;
    var parent = toggleEl.closest('.nav-item.has-dropdown');
    if (!parent) return;
    if (parent.classList.contains('programs-nav-item')) {
      window.toggleProgramsDropdown(e);
      return;
    }

    var menu = parent.querySelector('.dropdown-menu');
    if (!menu) return;
    var isOpen = parent.classList.contains('dropdown-show') || menu.classList.contains('show') || (menu.style.display === 'block');
    document.querySelectorAll('.rmu-navbar .nav-item.has-dropdown').forEach(function(el) {
      if (el !== parent) {
        el.classList.remove('dropdown-show');
        var d = el.querySelector('.dropdown-menu');
        if (d) {
          d.classList.remove('show');
          d.style.setProperty('display', 'none', 'important');
        }
        var l = el.querySelector('.dropdown-toggle');
        if (l) l.setAttribute('aria-expanded', 'false');
      }
    });
    if (!isOpen) {
      parent.classList.add('dropdown-show');
      var l = parent.querySelector('.dropdown-toggle');
      if (l) l.setAttribute('aria-expanded', 'true');
      menu.classList.add('show');
      menu.style.setProperty('display', 'block', 'important');
      menu.style.setProperty('visibility', 'visible', 'important');
      menu.style.setProperty('opacity', '1', 'important');
      menu.style.setProperty('position', 'static', 'important');
    } else {
      parent.classList.remove('dropdown-show');
      var l = parent.querySelector('.dropdown-toggle');
      if (l) l.setAttribute('aria-expanded', 'false');
      menu.classList.remove('show');
      menu.style.setProperty('display', 'none', 'important');
    }
  };

  // Drawer Toggle (Three-Dot Menu Button)
  window.toggleDrawerMenu = function(e) {
    if (e) {
      if (typeof e.preventDefault === 'function') e.preventDefault();
      if (typeof e.stopPropagation === 'function') e.stopPropagation();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
    }

    var nav = document.getElementById('primaryNav');
    var btn = document.getElementById('navToggleBtn');
    var backdrop = document.querySelector('.mobile-backdrop');
    if (!nav) return;
    var isOpen = nav.classList.contains('mobile-open');
    if (!isOpen) {
      nav.classList.add('mobile-open');
      if (btn) {
        btn.classList.add('active');
        btn.setAttribute('aria-expanded', 'true');
      }
      if (backdrop) backdrop.classList.add('show');
    } else {
      nav.classList.remove('mobile-open');
      if (btn) {
        btn.classList.remove('active');
        btn.setAttribute('aria-expanded', 'false');
      }
      if (backdrop) backdrop.classList.remove('show');
    }
  };
})();
</script>
