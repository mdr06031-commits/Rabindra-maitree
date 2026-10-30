/**
 * Rabindra Maitree University (RMU) Form Validation
 */

document.addEventListener('DOMContentLoaded', function () {
  // 1. Admission Form Validation
  const admissionForm = document.getElementById('rmuAdmissionForm');
  if (admissionForm) {
    const fullNameInput = admissionForm.querySelector('input[name="full_name"]');
    const mobileInput = admissionForm.querySelector('input[name="mobile"]');
    const collegeInput = admissionForm.querySelector('input[name="last_college"]');
    const sscGpaInput = admissionForm.querySelector('input[name="ssc_gpa"]');
    const hscGpaInput = admissionForm.querySelector('input[name="hsc_gpa"]');
    const districtInput = admissionForm.querySelector('input[name="home_district"]');
    const subjectSelect = admissionForm.querySelector('select[name="interested_subject"]');

    function showError(input, message) {
      input.classList.add('is-invalid');
      let errorEl = input.parentElement.querySelector('.form-error-msg');
      if (!errorEl) {
        errorEl = document.createElement('div');
        errorEl.className = 'form-error-msg';
        input.parentElement.appendChild(errorEl);
      }
      errorEl.textContent = message;
      errorEl.style.display = 'block';
    }

    function clearError(input) {
      input.classList.remove('is-invalid');
      const errorEl = input.parentElement.querySelector('.form-error-msg');
      if (errorEl) {
        errorEl.style.display = 'none';
      }
    }

    // Real-time mobile validation
    if (mobileInput) {
      mobileInput.addEventListener('input', function () {
        const val = this.value.replace(/[^0-9]/g, '');
        if (val.length === 11 && /^01[3-9]\d{8}$/.test(val)) {
          clearError(this);
        }
      });
    }

    // GPA validation
    function checkGpa(input) {
      const val = parseFloat(input.value);
      if (isNaN(val) || val < 1.00 || val > 5.00) {
        showError(input, 'GPA must be between 1.00 and 5.00');
        return false;
      }
      clearError(input);
      return true;
    }

    if (sscGpaInput) {
      sscGpaInput.addEventListener('blur', function () { checkGpa(this); });
    }
    if (hscGpaInput) {
      hscGpaInput.addEventListener('blur', function () { checkGpa(this); });
    }

    admissionForm.addEventListener('submit', function (e) {
      let isValid = true;
      let firstErrorField = null;

      // Full Name
      if (!fullNameInput.value.trim() || fullNameInput.value.trim().length < 3) {
        showError(fullNameInput, 'Please provide applicant\'s full name (minimum 3 characters)');
        if (!firstErrorField) firstErrorField = fullNameInput;
        isValid = false;
      } else {
        clearError(fullNameInput);
      }

      // Mobile
      const cleanMobile = mobileInput.value.replace(/[^0-9]/g, '');
      if (!/^01[3-9]\d{8}$/.test(cleanMobile)) {
        showError(mobileInput, 'Enter a valid 11-digit Bangladeshi mobile number (e.g., 01712345678)');
        if (!firstErrorField) firstErrorField = mobileInput;
        isValid = false;
      } else {
        clearError(mobileInput);
      }

      // Last College
      if (!collegeInput.value.trim()) {
        showError(collegeInput, 'Previous college name is required');
        if (!firstErrorField) firstErrorField = collegeInput;
        isValid = false;
      } else {
        clearError(collegeInput);
      }

      // SSC GPA
      if (!checkGpa(sscGpaInput)) {
        if (!firstErrorField) firstErrorField = sscGpaInput;
        isValid = false;
      }

      // HSC GPA
      if (!checkGpa(hscGpaInput)) {
        if (!firstErrorField) firstErrorField = hscGpaInput;
        isValid = false;
      }

      // Home District
      if (!districtInput.value.trim()) {
        showError(districtInput, 'Home district is required');
        if (!firstErrorField) firstErrorField = districtInput;
        isValid = false;
      } else {
        clearError(districtInput);
      }

      // Subject
      if (subjectSelect && !subjectSelect.value) {
        showError(subjectSelect, 'Please select your intended academic program');
        if (!firstErrorField) firstErrorField = subjectSelect;
        isValid = false;
      } else if (subjectSelect) {
        clearError(subjectSelect);
      }

      // HSC Group radio check
      const hscGroupChecked = admissionForm.querySelector('input[name="hsc_group"]:checked');
      const hscGroupContainer = admissionForm.querySelector('.hsc-group-wrap');
      if (!hscGroupChecked && hscGroupContainer) {
        let err = hscGroupContainer.querySelector('.form-error-msg');
        if (!err) {
          err = document.createElement('div');
          err.className = 'form-error-msg';
          hscGroupContainer.appendChild(err);
        }
        err.textContent = 'Please select your HSC group stream';
        err.style.display = 'block';
        isValid = false;
      } else if (hscGroupContainer) {
        const err = hscGroupContainer.querySelector('.form-error-msg');
        if (err) err.style.display = 'none';
      }

      if (!isValid) {
        e.preventDefault();
        if (firstErrorField) {
          firstErrorField.focus();
        }
      } else {
        // Prevent double submit
        const submitBtn = admissionForm.querySelector('button[type="submit"]');
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting Application...';
        }
      }
    });
  }

  // 2. Contact Form Validation
  const contactForm = document.getElementById('rmuContactForm');
  if (contactForm) {
    contactForm.addEventListener('submit', function (e) {
      let valid = true;
      const name = contactForm.querySelector('input[name="full_name"]');
      const email = contactForm.querySelector('input[name="email"]');
      const message = contactForm.querySelector('textarea[name="message"]');

      if (!name.value.trim()) {
        name.classList.add('is-invalid');
        valid = false;
      } else {
        name.classList.remove('is-invalid');
      }

      if (!email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
        email.classList.add('is-invalid');
        valid = false;
      } else {
        email.classList.remove('is-invalid');
      }

      if (!message.value.trim() || message.value.trim().length < 10) {
        message.classList.add('is-invalid');
        valid = false;
      } else {
        message.classList.remove('is-invalid');
      }

      if (!valid) {
        e.preventDefault();
      } else {
        const submitBtn = contactForm.querySelector('button[type="submit"]');
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = 'Sending...';
        }
      }
    });
  }
});

