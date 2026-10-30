<?php
/**
 * Rabindra Maitree University (RMU) Homepage
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Home';
$db = getDb();

// Fetch latest pinned notices
$noticesStmt = $db->query("SELECT id, slug, title_en, title_bn, category, published_date FROM notices WHERE is_active = 1 ORDER BY is_pinned DESC, published_date DESC LIMIT 5");
$latestNotices = $noticesStmt->fetchAll();

// Fetch faculties
$facultiesStmt = $db->query("SELECT id, slug, name_en, name_bn, dean_name, overview, icon FROM faculties WHERE is_active = 1 ORDER BY sort_order ASC");
$faculties = $facultiesStmt->fetchAll();

// Fetch upcoming events
$eventsStmt = $db->query("SELECT id, slug, title_en, title_bn, event_date, event_time, location, description FROM events WHERE is_active = 1 ORDER BY event_date ASC LIMIT 3");
$upcomingEvents = $eventsStmt->fetchAll();

// Fetch latest news
$newsStmt = $db->query("SELECT id, slug, title_en, title_bn, published_date, summary FROM news WHERE is_active = 1 ORDER BY published_date DESC LIMIT 3");
$latestNews = $newsStmt->fetchAll();

// Fetch downloads
$downloadsStmt = $db->query("SELECT id, title, category, file_path, file_size FROM downloads WHERE is_active = 1 ORDER BY id ASC LIMIT 4");
$quickDownloads = $downloadsStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Announcement Notice Ticker -->
<?php if (!empty($latestNotices)): ?>
<div class="ticker-bar">
  <div class="container" style="display:flex; align-items:center;">
    <span class="ticker-label"><i class="fas fa-bullhorn"></i> Notice</span>
    <div class="ticker-content">
      <a href="<?= APP_URL ?>/notice-detail.php?slug=<?= e($latestNotices[0]['slug']) ?>" class="ticker-item">
        <strong><?= e($latestNotices[0]['title_en']) ?></strong> (<?= e($latestNotices[0]['title_bn']) ?>) — Published: <?= format_date($latestNotices[0]['published_date']) ?>
      </a>
    </div>
    <a href="<?= APP_URL ?>/notices.php" style="font-size:0.82rem; font-weight:600; margin-left:1rem; white-space:nowrap;">View All <i class="fas fa-arrow-right"></i></a>
  </div>
</div>
<?php endif; ?>

<!-- Hero Carousel Slider -->
<section class="hero-slider-section" aria-label="University Banners">
  <div class="slider-container">
    <!-- Slide 1 -->
    <div class="slide active" style="background-image: url('<?= APP_URL ?>/assets/images/campus-students.jpg'); background-position: center 30%; background-size: cover;">
      <div class="slide-overlay" style="background: linear-gradient(90deg, rgba(8, 28, 55, 0.88) 0%, rgba(12, 48, 88, 0.60) 50%, rgba(0, 0, 0, 0.25) 100%);"></div>
      <div class="container">
        <div class="slide-content">
          <span class="slide-badge"><i class="fas fa-university"></i> Rabindra Maitree University</span>
          <h2 class="slide-title">Empowering Future Leaders Through Knowledge &amp; Values</h2>
          <p class="slide-desc">
            Approved by the UGC and Ministry of Education, RMU offers contemporary undergraduate and graduate education grounded in ethical leadership, humanism, and practical technology.
          </p>
          <div class="slide-btns">
            <a href="<?= APP_URL ?>/admission-form.php" class="btn-primary"><i class="fas fa-edit"></i> Apply for Fall 2026</a>
            <a href="<?= APP_URL ?>/about.php" class="btn-outline-white"><i class="fas fa-info-circle"></i> Learn About RMU</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Slide 2 -->
    <div class="slide" style="background-image: url('<?= APP_URL ?>/assets/images/campus-students.jpg'); background-position: center 30%; background-size: cover;">
      <div class="slide-overlay" style="background: linear-gradient(90deg, rgba(8, 28, 55, 0.88) 0%, rgba(12, 48, 88, 0.60) 50%, rgba(0, 0, 0, 0.25) 100%);"></div>
      <div class="container">
        <div class="slide-content">
          <span class="slide-badge" style="background:rgba(207,46,46,0.85);"><i class="fas fa-graduation-cap"></i> Admission Open Fall 2026</span>
          <h2 class="slide-title">Merit Scholarships &amp; Tuition Fee Waivers Up to 100%</h2>
          <p class="slide-desc">
            Explore advanced degree programs in Computer Science &amp; Engineering, Electrical Engineering, Business Administration, English, and Agricultural Sciences.
          </p>
          <div class="slide-btns">
            <a href="<?= APP_URL ?>/programs.php" class="btn-primary"><i class="fas fa-book-open"></i> Explore Programs</a>
            <a href="<?= APP_URL ?>/tuition-fees.php" class="btn-outline-white"><i class="fas fa-calculator"></i> Fee Structure</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Slide 3 -->
    <div class="slide" style="background-image: url('<?= APP_URL ?>/assets/images/campus-students.jpg'); background-position: center 30%; background-size: cover;">
      <div class="slide-overlay" style="background: linear-gradient(90deg, rgba(8, 28, 55, 0.88) 0%, rgba(12, 48, 88, 0.60) 50%, rgba(0, 0, 0, 0.25) 100%);"></div>
      <div class="container">
        <div class="slide-content">
          <span class="slide-badge"><i class="fas fa-microscope"></i> Modern Research Facilities</span>
          <h2 class="slide-title">Excellence in Science, Agriculture &amp; Cultural Heritage</h2>
          <p class="slide-desc">
            Located in Kushtia, the historic home of Nobel laureate Rabindranath Tagore, RMU nurtures innovative minds with state-of-the-art laboratories and renowned faculty.
          </p>
          <div class="slide-btns">
            <a href="<?= APP_URL ?>/faculties.php" class="btn-primary"><i class="fas fa-laptop-code"></i> Explore Faculties</a>
            <a href="<?= APP_URL ?>/contact.php" class="btn-outline-white"><i class="fas fa-map-marker-alt"></i> Visit Campus</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Controls -->
    <button class="slider-ctrl slider-prev" aria-label="Previous Slide"><i class="fas fa-chevron-left"></i></button>
    <button class="slider-ctrl slider-next" aria-label="Next Slide"><i class="fas fa-chevron-right"></i></button>

    <!-- Indicators -->
    <div class="slider-dots">
      <button class="dot active" aria-label="Slide 1"></button>
      <button class="dot" aria-label="Slide 2"></button>
      <button class="dot" aria-label="Slide 3"></button>
    </div>
  </div>
</section>

<!-- University Fast Facts Bar -->
<section style="background-color: var(--rmu-white); border-bottom: 1px solid var(--rmu-border); padding: 1.5rem 0;">
  <div class="container">
    <div class="home-stats-grid">
      <div>
        <div style="font-size: 2rem; font-weight: 700; color: var(--rmu-primary);">2015</div>
        <div style="font-size: 0.85rem; color: var(--rmu-muted); text-transform: uppercase; font-weight: 600;">Year Established</div>
      </div>
      <div>
        <div style="font-size: 2rem; font-weight: 700; color: var(--rmu-primary);">4</div>
        <div style="font-size: 0.85rem; color: var(--rmu-muted); text-transform: uppercase; font-weight: 600;">Faculties</div>
      </div>
      <div>
        <div style="font-size: 2rem; font-weight: 700; color: var(--rmu-primary);">11</div>
        <div style="font-size: 0.85rem; color: var(--rmu-muted); text-transform: uppercase; font-weight: 600;">Academic Departments</div>
      </div>
      <div>
        <div style="font-size: 2rem; font-weight: 700; color: var(--rmu-primary);">14+</div>
        <div style="font-size: 0.85rem; color: var(--rmu-muted); text-transform: uppercase; font-weight: 600;">Degree Programs</div>
      </div>
      <div>
        <div style="font-size: 2rem; font-weight: 700; color: var(--rmu-primary);">1,700+</div>
        <div style="font-size: 0.85rem; color: var(--rmu-muted); text-transform: uppercase; font-weight: 600;">Regular Students</div>
      </div>
    </div>
  </div>
</section>

<!-- About University Section -->
<section class="section section-bg-light">
  <div class="container">
    <div class="grid-2" style="align-items: center;">
      <div>
        <span class="section-subtitle">About Rabindra Maitree University</span>
        <h2 class="section-title" style="text-align: left; margin-bottom: 1.25rem;">Dedicated to Enlightenment, Research and Humane Values</h2>
        <div style="width: 50px; height: 3px; background: var(--rmu-primary); margin-bottom: 1.5rem;"></div>
        <p style="color: var(--rmu-muted); margin-bottom: 1rem; line-height: 1.7;">
          Rabindra Maitree University was established in 2015 under the Private University Act 2010 of Bangladesh. Situated in the culturally prosperous district of Kushtia, RMU was conceived by eminent educationists, philanthropists, and researchers to provide modern, affordable higher education to students of South-Western Bangladesh and beyond.
        </p>
        <p style="color: var(--rmu-muted); margin-bottom: 1.5rem; line-height: 1.7;">
          With UGC approved curricula across Engineering, Business, Agriculture, and Arts &amp; Social Sciences, the university maintains an open intellectual climate, student-centric pedagogy, and merit-based tuition concessions.
        </p>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
          <a href="<?= APP_URL ?>/about.php" class="btn-primary"><i class="fas fa-university"></i> Read University History</a>
          <a href="<?= APP_URL ?>/leadership.php" style="font-weight:600; padding:0.75rem 1.4rem; border:1px solid var(--rmu-border); border-radius:var(--rmu-radius-sm); color:var(--rmu-dark); display:inline-flex; align-items:center; gap:0.4rem; background:#fff;">
            <i class="fas fa-user-tie"></i> Messages from Leadership
          </a>
        </div>
      </div>

      <div style="background: var(--rmu-white); border-radius: var(--rmu-radius-lg); padding: 2.2rem; border: 1px solid var(--rmu-border); box-shadow: var(--rmu-shadow-md);">
        <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--rmu-primary); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 0.5rem;">
          <i class="fas fa-bullhorn" style="color: var(--rmu-accent);"></i> Notice Board Highlights
        </h3>
        <?php if (!empty($latestNotices)): ?>
          <?php foreach ($latestNotices as $notice): ?>
            <div style="padding: 0.85rem 0; border-bottom: 1px solid var(--rmu-border-light); display: flex; gap: 0.8rem; align-items: flex-start;">
              <span class="notice-category-badge <?= ($notice['category'] === 'Admission') ? 'badge-admission' : (($notice['category'] === 'Scholarship') ? 'badge-scholarship' : 'badge-academic') ?>" style="white-space:nowrap; margin-top:2px;">
                <?= e($notice['category']) ?>
              </span>
              <div>
                <a href="<?= APP_URL ?>/notice-detail.php?slug=<?= e($notice['slug']) ?>" style="font-weight: 600; color: var(--rmu-dark); font-size: 0.92rem; display: block; line-height: 1.4;">
                  <?= e($notice['title_en']) ?>
                </a>
                <small style="color: var(--rmu-muted);"><?= format_date($notice['published_date']) ?></small>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
        <div style="margin-top: 1.2rem; text-align: right;">
          <a href="<?= APP_URL ?>/notices.php" style="font-weight: 600; font-size: 0.88rem;">View All Notices <i class="fas fa-chevron-right"></i></a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Academic Faculties Section: Exactly 9 Image Cards Matching Reference Screenshot -->
<?php
$ourFacultiesList = [
    [
        'title' => 'Information & Communication Technology',
        'ribbon' => 'ICT',
        'desc' => 'Develop skills in using technology to improve communication and solve problems, and explore the latest trends in the industry.',
        'image' => APP_URL . '/assets/images/faculties/ict.jpg'
    ],
    [
        'title' => 'Computer Science & Engineering',
        'ribbon' => 'CSE',
        'desc' => 'Study the design and development of computer systems and software, and learn about emerging technologies such as artificial intelligence and machine learning.',
        'image' => APP_URL . '/assets/images/faculties/cse.jpg'
    ],
    [
        'title' => 'Electrical & Electronic Engineering (EEE)',
        'ribbon' => 'EEE',
        'desc' => 'Learn about electrical and electronic systems and their applications in modern technology, and gain practical skills through hands-on experience.',
        'image' => APP_URL . '/assets/images/faculties/eee.jpg'
    ],
    [
        'title' => 'English',
        'ribbon' => 'ENGLISH',
        'desc' => 'Improve your language skills through reading, writing, and analysis of literature, and develop critical thinking skills through analysis of texts.',
        'image' => APP_URL . '/assets/images/faculties/english.jpg'
    ],
    [
        'title' => 'Bangla',
        'ribbon' => 'BANGLA',
        'desc' => 'Study the language, literature, and culture of Bangladesh and its people, and develop proficiency in both spoken and written Bangla.',
        'image' => APP_URL . '/assets/images/faculties/bangla.jpg'
    ],
    [
        'title' => 'Physical Education & Sports Science',
        'ribbon' => 'PHYSICAL EDUCATION',
        'desc' => 'Learn about the science behind physical activity and the human body, and develop skills in coaching, training, and sports management.',
        'image' => APP_URL . '/assets/images/faculties/sports.jpg'
    ],
    [
        'title' => 'Microbiology',
        'ribbon' => 'MICROBIOLOGY',
        'desc' => 'Study microorganisms and their role in health, disease, and the environment, and gain practical laboratory skills in microbiology techniques.',
        'image' => APP_URL . '/assets/images/faculties/microbiology.jpg'
    ],
    [
        'title' => 'Business Administration',
        'ribbon' => 'BBA',
        'desc' => 'Develop knowledge and skills in management, accounting, finance, marketing, and other business functions, and prepare for leadership roles in the business world.',
        'image' => APP_URL . '/assets/images/faculties/bba.jpg'
    ],
    [
        'title' => 'Agriculture',
        'ribbon' => 'AGRICULTURE',
        'desc' => 'Learn about the science and business of agriculture, including crop and livestock production, sustainable agriculture practices, and agricultural economics.',
        'image' => APP_URL . '/assets/images/faculties/agriculture.jpg'
    ],
];
?>
<section class="our-faculties-section" id="our-faculties">
  <div class="container">
    <div class="our-faculties-header">
      <div class="our-faculties-subtitle">
        <i class="fas fa-university"></i> <span>Our Faculties</span>
      </div>
      <h2 class="our-faculties-title">Our Faculties</h2>
    </div>

    <div class="our-faculties-grid">
      <?php foreach ($ourFacultiesList as $facCard): ?>
        <a href="<?= APP_URL ?>/programs.php" class="our-faculty-card" style="text-decoration:none; color:inherit;">
          <div class="our-faculty-thumb">
            <img src="<?= e($facCard['image']) ?>" alt="<?= e($facCard['title']) ?> (<?= e($facCard['ribbon']) ?>)" loading="lazy">
          </div>
          <div class="our-faculty-body">
            <h3 class="our-faculty-name"><?= e($facCard['title']) ?></h3>
            <p class="our-faculty-desc"><?= e($facCard['desc']) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Admission CTA Banner -->
<section style="background: linear-gradient(135deg, #0b3c66 0%, #1e73be 100%); color: #fff; padding: 3.5rem 0;">
  <div class="container" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 2rem;">
    <div style="max-width: 680px;">
      <span style="background: rgba(255,255,255,0.2); padding: 0.25rem 0.8rem; border-radius: 20px; font-size: 0.82rem; font-weight: 600; text-transform: uppercase;">Direct Admission Application</span>
      <h2 style="font-size: 2.2rem; font-weight: 700; font-family: var(--rmu-font-serif); margin: 0.8rem 0 0.5rem;">Join Rabindra Maitree University in <?= e($currentTerm) ?></h2>
      <p style="opacity: 0.9; font-size: 1.05rem; line-height: 1.6;">
        Submit your online application through our digital admissions portal. Scholarships are awarded automatically based on your SSC and HSC GPA.
      </p>
    </div>
    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
      <a href="<?= APP_URL ?>/admission-form.php" class="btn-primary" style="background: var(--rmu-accent); padding: 0.9rem 2rem; font-size: 1rem;">
        <i class="fas fa-file-signature"></i> Fill Online Form
      </a>
      <a href="<?= APP_URL ?>/admissions.php" class="btn-outline-white" style="padding: 0.9rem 1.8rem; font-size: 1rem;">
        <i class="fas fa-info-circle"></i> Admission Guidelines
      </a>
    </div>
  </div>
</section>

<!-- Latest Events & News Section -->
<section class="section section-bg-white">
  <div class="container">
    <div class="grid-2">
      <!-- Upcoming Events -->
      <div>
        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1.8rem;">
          <h2 style="font-size: 1.6rem; font-weight: 700; color: var(--rmu-dark); font-family: var(--rmu-font-serif);">
            <i class="fas fa-calendar-alt" style="color: var(--rmu-primary);"></i> Upcoming Events
          </h2>
          <a href="<?= APP_URL ?>/events.php" style="font-weight: 600; font-size: 0.88rem;">All Events <i class="fas fa-chevron-right"></i></a>
        </div>

        <?php if (!empty($upcomingEvents)): ?>
          <?php foreach ($upcomingEvents as $evt): ?>
            <div class="notice-card">
              <div class="notice-date-box">
                <div class="day"><?= date('d', strtotime($evt['event_date'])) ?></div>
                <div class="month"><?= date('M', strtotime($evt['event_date'])) ?></div>
              </div>
              <div class="notice-info">
                <h4 class="notice-title">
                  <a href="<?= APP_URL ?>/event-detail.php?slug=<?= e($evt['slug']) ?>"><?= e($evt['title_en']) ?></a>
                </h4>
                <div style="font-size: 0.85rem; color: var(--rmu-muted); margin-bottom: 0.3rem;">
                  <i class="fas fa-clock"></i> <?= e($evt['event_time'] ?? 'All Day') ?> &bull; <i class="fas fa-map-marker-alt"></i> <?= e($evt['location']) ?>
                </div>
                <p style="font-size: 0.85rem; color: var(--rmu-muted);"><?= e(truncate_words($evt['description'], 16)) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- University News -->
      <div>
        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1.8rem;">
          <h2 style="font-size: 1.6rem; font-weight: 700; color: var(--rmu-dark); font-family: var(--rmu-font-serif);">
            <i class="fas fa-newspaper" style="color: var(--rmu-primary);"></i> Campus News
          </h2>
          <a href="<?= APP_URL ?>/news.php" style="font-weight: 600; font-size: 0.88rem;">All News <i class="fas fa-chevron-right"></i></a>
        </div>

        <?php if (!empty($latestNews)): ?>
          <?php foreach ($latestNews as $item): ?>
            <div style="background: var(--rmu-bg-light); border-radius: var(--rmu-radius-md); padding: 1.25rem; border: 1px solid var(--rmu-border); margin-bottom: 1rem;">
              <small style="color: var(--rmu-primary); font-weight: 600;"><?= format_date($item['published_date']) ?></small>
              <h4 style="font-size: 1.05rem; font-weight: 700; margin: 0.3rem 0 0.5rem; line-height: 1.4;">
                <a href="<?= APP_URL ?>/news-detail.php?slug=<?= e($item['slug']) ?>" style="color: var(--rmu-dark);">
                  <?= e($item['title_en']) ?>
                </a>
              </h4>
              <p style="font-size: 0.88rem; color: var(--rmu-muted); margin-bottom: 0.5rem;">
                <?= e(truncate_words($item['summary'], 20)) ?>
              </p>
              <a href="<?= APP_URL ?>/news-detail.php?slug=<?= e($item['slug']) ?>" style="font-size: 0.85rem; font-weight: 600;">
                Read Article <i class="fas fa-arrow-right"></i>
              </a>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- Important Downloads Section -->
<section class="section section-bg-light">
  <div class="container">
    <div class="section-header">
      <span class="section-subtitle">Official Forms &amp; Notices</span>
      <h2 class="section-title">Important Downloadable Documents</h2>
      <div class="section-line"></div>
    </div>

    <div class="grid-4">
      <?php foreach ($quickDownloads as $dl): ?>
        <div style="background: var(--rmu-white); border-radius: var(--rmu-radius-md); padding: 1.5rem; border: 1px solid var(--rmu-border); display: flex; flex-direction: column; justify-content: space-between;">
          <div>
            <div style="width: 44px; height: 44px; border-radius: 8px; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 1rem;">
              <i class="fas fa-file-pdf"></i>
            </div>
            <h4 style="font-size: 0.98rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.5rem; line-height: 1.4;">
              <?= e($dl['title']) ?>
            </h4>
            <span style="font-size: 0.78rem; color: var(--rmu-muted);"><?= e($dl['category']) ?> • <?= e($dl['file_size']) ?></span>
          </div>
          <div style="margin-top: 1.25rem;">
            <a href="<?= APP_URL ?>/<?= e($dl['file_path']) ?>" download class="btn-primary" style="padding: 0.5rem 1rem; font-size: 0.82rem; width: 100%; justify-content: center;">
              <i class="fas fa-download"></i> Download PDF
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

