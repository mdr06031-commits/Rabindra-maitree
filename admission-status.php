<?php
/**
 * Admission Application Status Tracking & Printable Slip
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Track Application Status';
$breadcrumbTitle = 'Online Application Status Tracking';
$breadcrumbs = [['title' => 'Admission', 'url' => APP_URL . '/admissions.php'], ['title' => 'Application Status']];

$searchQuery = trim($_GET['ref'] ?? ($_GET['mobile'] ?? ''));
$application = null;

if (!empty($searchQuery)) {
    $db = getDb();
    // Search by either application reference or mobile number
    $stmt = $db->prepare("
        SELECT * FROM admissions 
        WHERE application_ref = ? OR mobile = ? 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$searchQuery, $searchQuery]);
    $application = $stmt->fetch();
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/breadcrumbs.php';
?>

<section class="section section-bg-white">
  <div class="container" style="max-width: 850px;">
    <!-- Lookup Search Card -->
    <div style="background: var(--rmu-bg-light); border: 1px solid var(--rmu-border); border-radius: var(--rmu-radius-lg); padding: 2rem; margin-bottom: 2.5rem;">
      <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--rmu-dark); margin-bottom: 0.5rem;">
        <i class="fas fa-search-location" style="color: var(--rmu-primary);"></i> Check Application Status
      </h3>
      <p style="color: var(--rmu-muted); font-size: 0.9rem; margin-bottom: 1.2rem;">
        Enter your Application Reference Number (e.g., <code>RMU-2026-1001</code>) or your registered 11-digit mobile number.
      </p>

      <form action="<?= APP_URL ?>/admission-status.php" method="GET" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <input type="text" name="ref" value="<?= e($searchQuery) ?>" placeholder="Enter Application Ref (e.g., RMU-2026-XXXX) or Mobile" class="form-control" style="flex: 1; min-width: 250px;" required>
        <button type="submit" class="btn-primary" style="padding: 0.75rem 1.8rem;">
          <i class="fas fa-search"></i> Check Status
        </button>
      </form>
    </div>

    <?php if (!empty($searchQuery) && !$application): ?>
      <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle" style="font-size: 1.2rem;"></i>
        <div>
          No application was found matching <strong><?= e($searchQuery) ?></strong>. Please double-check your reference code or mobile number.
        </div>
      </div>
    <?php endif; ?>

    <?php if ($application): ?>
      <?php 
        $isPaid = (($application['payment_status'] ?? '') === 'Paid');
      ?>

      <!-- Application Fee Payment Notice / Callout -->
      <?php if (!$isPaid): ?>
        <div style="background:#fff1f2; border:2px solid #fecdd3; border-radius:12px; padding:1.5rem 1.8rem; margin-bottom:2rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem;">
          <div style="flex:1; min-width:280px;">
            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.4rem;">
              <span style="background:#be123c; color:#ffffff; padding:2px 8px; border-radius:4px; font-weight:700; font-size:0.75rem; text-transform:uppercase;">Payment Pending</span>
              <h3 style="font-size:1.2rem; font-weight:800; color:#9f1239; margin:0;">Application Fee: ৳150 BDT</h3>
            </div>
            <p style="color:#881337; font-size:0.92rem; margin:0;">
              আবেদন ফি <strong>৳১৫০ টাকা</strong> পরিশোধ করলেই আপনার রঙিন ছবিযুক্ত অফিসিয়াল <strong>অ্যাপ্লিকেশন কার্ড (Application Card)</strong> চলে আসবে এবং <strong>স্টুডেন্ট পোর্টালের আইডি ও পাসওয়ার্ড</strong> পেয়ে যাবেন।
            </p>
          </div>
          <div>
            <a href="<?= APP_URL ?>/admission-payment.php?ref=<?= urlencode($application['application_ref']) ?>" class="btn-primary" style="background:#e11d48; padding:0.85rem 1.8rem; font-size:1rem; font-weight:700; text-decoration:none; box-shadow:0 2px 4px rgba(225,29,72,0.3);">
              <i class="fas fa-credit-card"></i> Pay ৳150 with bKash/Nagad &rarr;
            </a>
          </div>
        </div>
      <?php else: ?>
        <!-- ============================================================== -->
        <!-- ⭐ STUDENT PORTAL ACCESS CREDENTIALS CARD                        -->
        <!-- ============================================================== -->
        <div style="background:linear-gradient(135deg, #1e3a8a 0%, #1e73be 100%); color:#ffffff; border-radius:12px; padding:1.8rem 2rem; margin-bottom:2rem; box-shadow:0 8px 16px -2px rgba(30,115,190,0.25); border:1px solid #60a5fa;">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.2rem; border-bottom:1px solid rgba(255,255,255,0.2); padding-bottom:1rem;">
            <div>
              <span style="background:rgba(255,255,255,0.2); color:#ffffff; padding:3px 10px; border-radius:20px; font-size:0.78rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
                <i class="fas fa-check-circle" style="color:#4ade80;"></i> Account Provisioned &amp; Active
              </span>
              <h3 style="font-size:1.35rem; font-weight:800; margin:0.4rem 0 0.2rem; color:#ffffff;">
                <i class="fas fa-user-graduate"></i> Student Academic Portal Credentials
              </h3>
              <p style="margin:0; font-size:0.88rem; color:#dbeafe;">
                আপনার ৳১৫০ আবেদন ফি পরিশোধ সম্পন্ন হয়েছে। নিচে আপনার স্টুডেন্ট পোর্টালের <strong>আইডি ও পাসওয়ার্ড</strong> দেওয়া হলো:
              </p>
            </div>
            <div>
              <a href="<?= APP_URL ?>/student/login.php?student_id=<?= urlencode($application['student_id'] ?? '') ?>&pwd=<?= urlencode($application['portal_password'] ?? '') ?>" style="background:#22c55e; color:#ffffff !important; font-weight:800; padding:0.85rem 1.6rem; text-decoration:none; border-radius:8px; display:inline-flex; align-items:center; gap:0.5rem; box-shadow:0 4px 8px rgba(0,0,0,0.2); font-size:1rem; transition:transform 0.2s ease;">
                <i class="fas fa-sign-in-alt"></i> Login to Student Portal Now &rarr;
              </a>
            </div>
          </div>

          <!-- Credentials Box -->
          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
            <div style="background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.2); border-radius:8px; padding:0.9rem 1.2rem; position:relative;">
              <small style="color:#bfdbfe; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px;">Your Student ID</small>
              <div style="font-family:monospace; font-size:1.45rem; font-weight:800; color:#ffffff; letter-spacing:1px; margin-top:2px;">
                <?= e($application['student_id'] ?? 'RMU2601004') ?>
              </div>
            </div>

            <div style="background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.2); border-radius:8px; padding:0.9rem 1.2rem; position:relative;">
              <small style="color:#bfdbfe; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px;">Portal Password</small>
              <div style="font-family:monospace; font-size:1.45rem; font-weight:800; color:#fde047; letter-spacing:1px; margin-top:2px;">
                <?= e($application['portal_password'] ?? 'rmu2026') ?>
              </div>
            </div>

            <div style="background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.2); border-radius:8px; padding:0.9rem 1.2rem;">
              <small style="color:#bfdbfe; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px;">Admitted Program</small>
              <div style="font-size:0.95rem; font-weight:700; color:#ffffff; margin-top:4px;">
                <?= e($application['interested_subject']) ?>
              </div>
            </div>
          </div>
        </div>

        <!-- ============================================================== -->
        <!-- ⭐ OFFICIAL APPLICATION CARD (অ্যাপ্লিকেশন কার্ড উইথ ছবি)          -->
        <!-- ============================================================== -->
        <?php
          $applicantPhotoUrl = get_admission_photo_url($application['photo'] ?? null);
          if (!empty($application['student_id'])) {
            $stPhoto = $db->query("SELECT avatar FROM students WHERE student_id = '{$application['student_id']}' LIMIT 1")->fetchColumn();
            if (!empty($stPhoto)) {
              $applicantPhotoUrl = get_student_avatar_url(['avatar' => $stPhoto]);
            }
          }
        ?>
        <div id="admitCardPrint" style="background:#ffffff; border:2.5px solid #1e73be; border-radius:12px; padding:2rem; margin-bottom:2rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.06); position:relative;">
          
          <!-- Application Card Header -->
          <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #1e73be; padding-bottom:1rem; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
            <div style="display:flex; align-items:center; gap:1rem;">
              <img src="<?= APP_URL ?>/assets/images/logo.jpg" alt="RMU Logo" style="width:65px; height:65px; object-fit:contain;" onerror="this.src='<?= APP_URL ?>/assets/images/logo.svg'">
              <div>
                <h3 style="font-size:1.35rem; font-weight:800; color:#1e73be; margin:0 0 2px;"><?= APP_NAME ?></h3>
                <div style="font-size:0.85rem; color:#475569; font-weight:600;">Office of Admissions &amp; Academic Records</div>
                <small style="color:#64748b;">Permanent Campus: Kushtia, Bangladesh &bull; Web: rmu.ac.bd</small>
              </div>
            </div>
            <div style="text-align:right;">
              <span style="background:#eff6ff; color:#1e73be; border:2px solid #1e73be; padding:6px 16px; border-radius:20px; font-weight:800; font-size:0.92rem; text-transform:uppercase; letter-spacing:0.5px;">
                APPLICATION CARD
              </span>
              <div style="font-size:0.8rem; color:#64748b; margin-top:4px;">Session: <strong>Fall 2026</strong></div>
              <div style="font-size:0.75rem; color:#15803d; font-weight:700; margin-top:2px;">
                <i class="fas fa-check-circle"></i> APPLICATION FEE: ৳150 (<?= e($application['payment_method'] ?? 'bKash') ?>)
              </div>
            </div>
          </div>

          <!-- Examinee & Application Particulars Grid -->
          <div style="display:grid; grid-template-columns:1fr 180px; gap:1.5rem; margin-bottom:1.5rem;">
            <div>
              <div style="display:grid; grid-template-columns:150px 1fr; gap:8px; font-size:0.9rem; margin-bottom:7px;">
                <span style="font-weight:600; color:#64748b;">Applicant Legal Name:</span>
                <strong style="color:#0f172a; font-size:1.05rem;"><?= e($application['full_name']) ?></strong>
              </div>
              <div style="display:grid; grid-template-columns:150px 1fr; gap:8px; font-size:0.9rem; margin-bottom:7px;">
                <span style="font-weight:600; color:#64748b;">Application Ref No:</span>
                <strong style="font-family:monospace; font-size:1.1rem; color:#1e73be;"><?= e($application['application_ref']) ?></strong>
              </div>
              <div style="display:grid; grid-template-columns:150px 1fr; gap:8px; font-size:0.9rem; margin-bottom:7px;">
                <span style="font-weight:600; color:#64748b;">Applicant Roll No:</span>
                <strong style="font-family:monospace; font-size:1.15rem; color:#b91c1c;"><?= e($application['exam_roll'] ?? ('APP-' . substr($application['application_ref'], -4))) ?></strong>
              </div>
              <div style="display:grid; grid-template-columns:150px 1fr; gap:8px; font-size:0.9rem; margin-bottom:7px;">
                <span style="font-weight:600; color:#64748b;">Applied Program:</span>
                <strong style="color:#0f172a;"><?= e($application['interested_subject']) ?></strong>
              </div>
              <div style="display:grid; grid-template-columns:150px 1fr; gap:8px; font-size:0.9rem; margin-bottom:7px;">
                <span style="font-weight:600; color:#64748b;">Contact Mobile:</span>
                <strong style="color:#0f172a;"><?= e($application['mobile']) ?></strong>
              </div>
              <div style="display:grid; grid-template-columns:150px 1fr; gap:8px; font-size:0.9rem; margin-bottom:7px;">
                <span style="font-weight:600; color:#64748b;">Issued Student ID:</span>
                <strong style="font-family:monospace; color:#15803d; font-size:1.05rem;"><?= e($application['student_id'] ?? 'RMU2601004') ?></strong>
              </div>
              <div style="display:grid; grid-template-columns:150px 1fr; gap:8px; font-size:0.9rem; margin-bottom:7px;">
                <span style="font-weight:600; color:#64748b;">Submission Date:</span>
                <strong style="color:#475569;"><?= format_date($application['created_at']) ?></strong>
              </div>
              <div style="display:grid; grid-template-columns:150px 1fr; gap:8px; font-size:0.9rem; margin-bottom:7px;">
                <span style="font-weight:600; color:#64748b;">Fee Paid Via &amp; Trx:</span>
                <strong style="color:#b91c1c; font-family:monospace;"><?= e($application['payment_method'] ?? 'bKash') ?> &bull; <?= e($application['transaction_id'] ?? 'N/A') ?></strong>
              </div>
              <div style="display:grid; grid-template-columns:150px 1fr; gap:8px; font-size:0.9rem; margin-bottom:7px;">
                <span style="font-weight:600; color:#64748b;">Sender &amp; Gateway:</span>
                <span style="color:#0f172a; font-size:0.86rem;">From: <strong><?= e($application['sender_number'] ?? $application['mobile']) ?></strong> &rarr; To: <strong>01336367336</strong></span>
              </div>
            </div>

            <!-- Candidate Photo with Live Picture & Upload Option -->
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; border:1px solid #cbd5e1; border-radius:8px; padding:10px; background:#f8fafc;">
              <div style="width:115px; height:135px; border:2px solid #94a3b8; background:#ffffff; border-radius:6px; display:flex; align-items:center; justify-content:center; overflow:hidden; margin-bottom:8px; box-shadow:0 2px 4px rgba(0,0,0,0.08);">
                <img src="<?= e($applicantPhotoUrl) ?>" alt="<?= e($application['full_name']) ?>" style="width:100%; height:100%; object-fit:cover;">
              </div>
              <div style="font-family:monospace; font-size:0.75rem; letter-spacing:1.5px; font-weight:700; color:#334155;">
                *<?= e($application['application_ref']) ?>*
              </div>
              <small style="color:#15803d; font-size:0.72rem; font-weight:700;"><i class="fas fa-check-circle"></i> Photo Verified</small>

              <!-- Upload / Change Photo button -->
              <form action="<?= APP_URL ?>/actions/upload-applicant-photo.php" method="POST" enctype="multipart/form-data" style="margin-top:6px; width:100%; text-align:center;">
                <?= csrf_field() ?>
                <input type="hidden" name="ref" value="<?= e($application['application_ref']) ?>">
                <label for="changePhotoQuick" style="font-size:0.72rem; color:var(--rmu-primary); font-weight:700; cursor:pointer; text-decoration:underline;">
                  <i class="fas fa-camera"></i> Change Photo
                </label>
                <input type="file" id="changePhotoQuick" name="photo" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="this.form.submit();">
              </form>
            </div>
          </div>

          <!-- Payment Verification Summary Strip -->
          <div style="background:#f0fdf4; border:1.5px solid #86efac; border-radius:8px; padding:10px 16px; margin-bottom:1.2rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:8px;">
              <i class="fas fa-check-circle" style="color:#16a34a; font-size:1.2rem;"></i>
              <div>
                <span style="font-size:0.86rem; color:#14532d; font-weight:700; display:block;">
                  ফি পরিশোধ নিশ্চিত: ৳১৫০ (প্রাপক নম্বর: 01336367336 &bull; মেথড: <?= e($application['payment_method'] ?? 'bKash') ?>)
                </span>
                <span style="font-size:0.78rem; color:#166534;">
                  প্রেরক নম্বর: <strong><?= e($application['sender_number'] ?? $application['mobile']) ?></strong> &bull; TrxID: <strong style="font-family:monospace;"><?= e($application['transaction_id'] ?? 'N/A') ?></strong>
                </span>
              </div>
            </div>
            <?php 
              $slipUrl = get_payment_screenshot_url($application['payment_screenshot'] ?? null);
              if ($slipUrl):
            ?>
              <div>
                <a href="<?= e($slipUrl) ?>" target="_blank" style="background:#15803d; color:#ffffff !important; padding:5px 12px; border-radius:6px; font-size:0.78rem; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:5px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                  <i class="fas fa-receipt"></i> পেমেন্ট স্লিপ / স্ক্রিনশট দেখুন &rarr;
                </a>
              </div>
            <?php endif; ?>
          </div>

          <!-- Instructions to Applicant -->
          <div style="background:#f8fafc; border-top:1px solid #e2e8f0; padding-top:1rem; font-size:0.8rem; color:#475569; line-height:1.5;">
            <strong>Important Notes for the Applicant:</strong>
            <ol style="margin:4px 0 0 1.2rem; padding:0;">
              <li>Keep this <strong>Application Card</strong> saved or printed for admission confirmation and document verification.</li>
              <li>Your Student Portal Account is now active. Use the <strong>Student ID</strong> and <strong>Password</strong> shown above to log into the Student Portal.</li>
              <li>Report to the RMU Admissions Office with your original academic certificates and mark sheets during office hours.</li>
            </ol>
          </div>

          <!-- Signatures -->
          <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-top:2rem; padding-top:1.5rem; border-top:1px dashed #cbd5e1; font-size:0.82rem;">
            <div style="text-align:center;">
              <div>_______________________________</div>
              <div style="color:#64748b; margin-top:3px;">Applicant's Signature</div>
            </div>
            <div style="text-align:center;">
              <div style="font-weight:700; color:#1e73be; font-family:'Brush Script MT', cursive; font-size:1.25rem;">Md. Rafiqul Islam</div>
              <div style="color:#64748b; margin-top:3px; font-weight:600;">Registrar &amp; Director of Admissions, RMU</div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Printable Application Summary Voucher -->
      <div id="printableVoucher" class="form-container" style="position: relative; border-top: 5px solid var(--rmu-primary);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid var(--rmu-border); padding-bottom: 1.25rem; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
          <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--rmu-primary); margin: 0 0 0.2rem;"><?= APP_NAME ?></h2>
            <div style="font-family: 'Hind Siliguri', sans-serif; font-size: 1.1rem; color: var(--rmu-dark);"><?= APP_NAME_BN ?></div>
            <small style="color: var(--rmu-muted);">Kushtia Campus &bull; Online Admission Intake System</small>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 0.8rem; color: var(--rmu-muted); text-transform: uppercase; font-weight: 600;">Application Reference</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: var(--rmu-accent);"><?= e($application['application_ref']) ?></div>
            <small style="color: var(--rmu-muted);">Date: <?= format_date($application['created_at']) ?></small>
          </div>
        </div>

        <!-- Status Indicator Pill -->
        <div style="margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; background: var(--rmu-bg-light); padding: 1rem 1.25rem; border-radius: var(--rmu-radius-sm);">
          <span style="font-weight: 600; color: var(--rmu-dark);">Application Status:</span>
          <?php
            $status = $application['status'];
            $badgeBg = '#fee2e2'; $badgeColor = '#991b1b';
            if ($status === 'Accepted') { $badgeBg = '#d1fae5'; $badgeColor = '#065f46'; }
            elseif ($status === 'Under Review') { $badgeBg = '#fef3c7'; $badgeColor = '#92400e'; }
            elseif ($status === 'Submitted') { $badgeBg = '#e0e7ff'; $badgeColor = '#3730a3'; }
          ?>
          <span style="background: <?= $badgeBg ?>; color: <?= $badgeColor ?>; padding: 0.35rem 1rem; border-radius: 20px; font-weight: 700; font-size: 0.95rem; text-transform: uppercase;">
            <?= e($status) ?>
          </span>
        </div>

        <?php if (!empty($application['admin_notes'])): ?>
          <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--rmu-radius-sm); padding: 1rem 1.25rem; margin-bottom: 1.5rem; font-size: 0.92rem; color: #92400e;">
            <strong>Admissions Office Feedback:</strong> <?= e($application['admin_notes']) ?>
          </div>
        <?php endif; ?>

        <!-- Application Data Table -->
        <h4 style="font-size: 1.1rem; color: var(--rmu-dark); margin-bottom: 0.8rem; font-weight: 700;">Applicant Academic Particulars</h4>
        <div class="table-responsive" style="margin-bottom: 1.5rem;">
          <table class="rmu-table">
            <tbody>
              <tr>
                <td style="width: 35%; font-weight: 600;">Applicant Full Name:</td>
                <td><?= e($application['full_name']) ?></td>
              </tr>
              <tr>
                <td style="font-weight: 600;">Mobile Number:</td>
                <td><?= e($application['mobile']) ?></td>
              </tr>
              <?php if (!empty($application['email'])): ?>
              <tr>
                <td style="font-weight: 600;">Email Address:</td>
                <td><?= e($application['email']) ?></td>
              </tr>
              <?php endif; ?>
              <tr>
                <td style="font-weight: 600;">Previous College:</td>
                <td><?= e($application['last_college']) ?> (<?= e($application['home_district']) ?>)</td>
              </tr>
              <tr>
                <td style="font-weight: 600;">SSC / Equivalent:</td>
                <td>
                  <strong><?= e($application['ssc_exam'] ?? 'S.S.C') ?></strong> &bull;
                  Board: <?= e($application['ssc_board'] ?? 'N/A') ?> &bull;
                  Group: <?= e($application['ssc_group'] ?? 'Science') ?> &bull;
                  Passing Year: <?= e($application['ssc_year'] ?? 'N/A') ?>
                  <br>
                  <span style="color: var(--rmu-primary); font-weight: 600;">
                    Roll No: <?= e($application['ssc_roll'] ?? 'N/A') ?> | GPA: <?= number_format($application['ssc_gpa'], 2) ?>
                  </span>
                </td>
              </tr>
              <tr>
                <td style="font-weight: 600;">HSC / Diploma:</td>
                <td>
                  <strong><?= e($application['hsc_exam'] ?? 'H.S.C') ?></strong> &bull;
                  Board: <?= e($application['hsc_board'] ?? 'N/A') ?> &bull;
                  Stream: <?= e($application['hsc_group']) ?> &bull;
                  Passing Year: <?= e($application['hsc_year'] ?? 'N/A') ?>
                  <br>
                  <span style="color: var(--rmu-primary); font-weight: 600;">
                    Roll No: <?= e($application['hsc_roll'] ?? 'N/A') ?> | GPA: <?= number_format($application['hsc_gpa'], 2) ?>
                  </span>
                </td>
              </tr>
              <?php if (!empty($application['grad_applicable'])): ?>
              <tr>
                <td style="font-weight: 600;">Graduation Level:</td>
                <td>
                  <strong><?= e($application['grad_exam'] ?? 'B.Sc Engineering') ?></strong> in <?= e($application['grad_subject'] ?? '') ?>
                  <br>
                  <span style="color: #64748b;">
                    Inst: <?= e($application['grad_university'] ?? '') ?> &bull; Duration: <?= e($application['grad_duration'] ?? '') ?> &bull; Year: <?= e($application['grad_year'] ?? '') ?>
                  </span>
                  <br>
                  <strong style="color: var(--rmu-accent);">Result: <?= e($application['grad_result_type'] ?? 'CGPA') ?> <?= e($application['grad_result'] ?? '') ?></strong>
                </td>
              </tr>
              <?php endif; ?>
              <tr>
                <td style="font-weight: 600;">Intended Program:</td>
                <td><strong style="color: var(--rmu-primary); font-size: 1.05rem;"><?= e($application['interested_subject']) ?></strong></td>
              </tr>
              <tr>
                <td style="font-weight: 600;">Special Quota Declared:</td>
                <td><?= e($application['quota']) ?></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--rmu-border); padding-top: 1.25rem; flex-wrap: wrap; gap: 1rem;">
          <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
            <?php if ($isPaid): ?>
              <button onclick="window.print();" class="btn-primary" style="background:#1e73be; font-weight:700;">
                <i class="fas fa-print"></i> Print Application Card &amp; Slip
              </button>
              <a href="<?= APP_URL ?>/student/login.php?student_id=<?= urlencode($application['student_id'] ?? '') ?>&pwd=<?= urlencode($application['portal_password'] ?? '') ?>" class="btn-primary" style="background:#15803d; text-decoration:none; font-weight:700;">
                <i class="fas fa-sign-in-alt"></i> Login to Student Portal
              </a>
            <?php else: ?>
              <a href="<?= APP_URL ?>/admission-payment.php?ref=<?= urlencode($application['application_ref']) ?>" class="btn-primary" style="background:#e11d48; text-decoration:none; font-weight:700;">
                <i class="fas fa-credit-card"></i> Pay ৳150 Fee Now &rarr;
              </a>
              <button onclick="window.print();" class="btn-primary" style="background:var(--rmu-secondary);">
                <i class="fas fa-print"></i> Print Application Draft
              </button>
            <?php endif; ?>
          </div>
          <a href="<?= APP_URL ?>/admission-form.php" style="font-size: 0.9rem; font-weight: 600;">
            Submit another application &rarr;
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Print Specific Styling -->
<style>
@media print {
  .rmu-topbar, .rmu-navbar, .rmu-footer, .breadcrumb-section, .btn-primary, form {
    display: none !important;
  }
  body, html {
    background: #ffffff !important;
    padding: 0 !important;
  }
  .form-container {
    box-shadow: none !important;
    border: none !important;
    padding: 0 !important;
  }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>

