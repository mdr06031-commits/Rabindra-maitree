/**
 * Rabindra Maitree University (RMU) Frontend Scripts
 */

document.addEventListener('DOMContentLoaded', function () {
  // 1. Hero Carousel / Slider
  const slider = document.querySelector('.slider-container');
  if (slider) {
    const slides = slider.querySelectorAll('.slide');
    const dots = slider.querySelectorAll('.dot');
    const prevBtn = slider.querySelector('.slider-prev');
    const nextBtn = slider.querySelector('.slider-next');
    let currentIndex = 0;
    let timer = null;
    const intervalTime = 6000;

    function showSlide(index) {
      if (index >= slides.length) index = 0;
      if (index < 0) index = slides.length - 1;
      currentIndex = index;

      slides.forEach((s, i) => {
        s.classList.toggle('active', i === currentIndex);
      });

      dots.forEach((d, i) => {
        d.classList.toggle('active', i === currentIndex);
      });
    }

    function nextSlide() {
      showSlide(currentIndex + 1);
    }

    function prevSlide() {
      showSlide(currentIndex - 1);
    }

    function startAutoplay() {
      stopAutoplay();
      // Check reduced motion
      const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (!prefersReducedMotion) {
        timer = setInterval(nextSlide, intervalTime);
      }
    }

    function stopAutoplay() {
      if (timer) clearInterval(timer);
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', () => {
        nextSlide();
        startAutoplay();
      });
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', () => {
        prevSlide();
        startAutoplay();
      });
    }

    dots.forEach((dot, idx) => {
      dot.addEventListener('click', () => {
        showSlide(idx);
        startAutoplay();
      });
    });

    slider.addEventListener('mouseenter', stopAutoplay);
    slider.addEventListener('mouseleave', startAutoplay);

    // Keyboard navigation for carousel
    slider.setAttribute('tabindex', '0');
    slider.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowLeft') {
        prevSlide();
        startAutoplay();
      } else if (e.key === 'ArrowRight') {
        nextSlide();
        startAutoplay();
      }
    });

    startAutoplay();
  }

  // 2. Navigation Drawer & 3-Underline (☰) Button on Right Side
  const navToggleBtn = document.getElementById('navToggleBtn');
  const navDrawerCloseBtn = document.getElementById('navDrawerCloseBtn');
  const navMenu = document.querySelector('.nav-menu');
  const rmuNavbar = document.querySelector('.rmu-navbar');

  if (rmuNavbar) {
    // Create backdrop element for navigation drawer
    const backdrop = document.createElement('div');
    backdrop.className = 'mobile-backdrop';
    document.body.appendChild(backdrop);

    // Centralized action debounce to eliminate ANY double-execution
    let lastActionTime = 0;
    let lastActionTarget = null;
    function isDuplicateAction(target) {
      const now = Date.now();
      if (lastActionTarget === target && (now - lastActionTime) < 350) {
        return true;
      }
      lastActionTime = now;
      lastActionTarget = target;
      return false;
    }

    function openDrawer() {
      if (!navMenu) return;
      navMenu.classList.add('mobile-open');
      backdrop.classList.add('show');
      if (navToggleBtn) {
        navToggleBtn.classList.add('active');
        navToggleBtn.setAttribute('aria-expanded', 'true');
      }
    }

    function closeDrawer() {
      if (!navMenu) return;
      navMenu.classList.remove('mobile-open');
      backdrop.classList.remove('show');
      if (navToggleBtn) {
        navToggleBtn.classList.remove('active');
        navToggleBtn.setAttribute('aria-expanded', 'false');
      }
    }

    function toggleDrawer(e) {
      if (e) {
        if (typeof e.preventDefault === 'function') e.preventDefault();
        if (typeof e.stopPropagation === 'function') e.stopPropagation();
      }
      if (!navMenu) return;
      if (navMenu.classList.contains('mobile-open')) {
        closeDrawer();
      } else {
        openDrawer();
      }
    }

    // Expose drawer controls globally
    window.toggleNavDrawer = toggleDrawer;
    window.toggleDrawerMenu = toggleDrawer;
    window.openNavDrawer = openDrawer;
    window.closeNavDrawer = closeDrawer;

    if (navToggleBtn) {
      navToggleBtn.onclick = function (e) {
        toggleDrawer(e);
      };

      navToggleBtn.onkeydown = function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          toggleDrawer(e);
        } else if (e.key === 'Escape') {
          closeDrawer();
        }
      };
    }

    if (navDrawerCloseBtn) {
      navDrawerCloseBtn.onclick = function (e) {
        if (e && e.preventDefault) e.preventDefault();
        closeDrawer();
      };
    }

    backdrop.onclick = function () {
      closeDrawer();
    };

    // Close all open submenus
    function closeAllSubmenus(exceptItem = null) {
      document.querySelectorAll('.rmu-navbar .nav-item.has-dropdown').forEach(el => {
        if (el !== exceptItem) {
          el.classList.remove('dropdown-show');
          const link = el.querySelector('.dropdown-toggle') || el.querySelector('.nav-link');
          if (link) link.setAttribute('aria-expanded', 'false');
          const menu = el.querySelector('.dropdown-menu');
          if (menu) {
            menu.classList.remove('show');
            menu.style.setProperty('display', 'none', 'important');
          }
        }
      });
    }

    function toggleSubmenu(toggleEl, e) {
      if (e) {
        if (typeof e.preventDefault === 'function') e.preventDefault();
        if (typeof e.stopPropagation === 'function') e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
      }

      if (!toggleEl) return;
      const parentItem = toggleEl.closest('.nav-item.has-dropdown');
      if (!parentItem) return;

      if (isDuplicateAction(parentItem)) {
        return;
      }

      const dropdown = parentItem.querySelector('.dropdown-menu');
      const toggleLink = parentItem.querySelector('.dropdown-toggle') || toggleEl;
      const wasOpen = parentItem.classList.contains('dropdown-show');

      // Close sibling submenus in the same container
      closeAllSubmenus(parentItem);

      if (!wasOpen) {
        parentItem.classList.add('dropdown-show');
        if (toggleLink) toggleLink.setAttribute('aria-expanded', 'true');
        if (dropdown) {
          dropdown.classList.add('show');
          dropdown.style.setProperty('display', 'block', 'important');
          dropdown.style.setProperty('visibility', 'visible', 'important');
          dropdown.style.setProperty('opacity', '1', 'important');
        }
      } else {
        parentItem.classList.remove('dropdown-show');
        if (toggleLink) toggleLink.setAttribute('aria-expanded', 'false');
        if (dropdown) {
          dropdown.classList.remove('show');
          dropdown.style.setProperty('display', 'none', 'important');
          dropdown.style.removeProperty('visibility');
          dropdown.style.removeProperty('opacity');
        }
      }
    }

    // Expose globally
    window.toggleSubmenu = toggleSubmenu;
    window.toggleProgramsSubmenu = function (e) {
      const progLink = document.getElementById('programsMenuLink');
      toggleSubmenu(progLink, e);
    };
    window.toggleProgramsDropdown = window.toggleProgramsSubmenu;

    // Toggle Program Subjects Accordion (Level 2: Program -> 4 Academic Years & Subjects)
    function toggleProgramSubjects(slug, e) {
      if (e) {
        if (typeof e.preventDefault === 'function') e.preventDefault();
        if (typeof e.stopPropagation === 'function') e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
      }

      const item = document.querySelector('.program-accordion-item[data-program-slug="' + slug + '"]');
      if (!item) return;
      const header = document.getElementById('programHeader-' + slug);
      const subjects = document.getElementById('programSubjects-' + slug);
      const isExpanded = item.classList.contains('active');

      if (isExpanded) {
        item.classList.remove('active');
        if (header) header.setAttribute('aria-expanded', 'false');
        if (subjects) {
          subjects.style.setProperty('display', 'none', 'important');
        }
      } else {
        // Close other program accordions for clean single-program view
        document.querySelectorAll('.program-accordion-item').forEach(other => {
          if (other !== item) {
            other.classList.remove('active');
            const otherHeader = other.querySelector('.program-accordion-header');
            if (otherHeader) otherHeader.setAttribute('aria-expanded', 'false');
            const otherSubjects = other.querySelector('.program-subjects-collapse');
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
    }
    window.toggleProgramSubjects = toggleProgramSubjects;

    // Expand All Program Subjects
    window.expandAllProgramSubjects = function(e) {
      if (e) {
        if (typeof e.preventDefault === 'function') e.preventDefault();
        if (typeof e.stopPropagation === 'function') e.stopPropagation();
      }
      document.querySelectorAll('.program-accordion-item').forEach(item => {
        item.classList.add('active');
        const h = item.querySelector('.program-accordion-header');
        if (h) h.setAttribute('aria-expanded', 'true');
        const s = item.querySelector('.program-subjects-collapse');
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
      document.querySelectorAll('.program-accordion-item').forEach(item => {
        item.classList.remove('active');
        const h = item.querySelector('.program-accordion-header');
        if (h) h.setAttribute('aria-expanded', 'false');
        const s = item.querySelector('.program-subjects-collapse');
        if (s) {
          s.style.setProperty('display', 'none', 'important');
        }
      });
    };

    // Single click handler per dropdown link
    document.querySelectorAll('.rmu-navbar .nav-item.has-dropdown > .nav-link').forEach(link => {
      link.onclick = function (e) {
        if (this.id === 'programsMenuLink' || this.closest('.programs-nav-item')) {
          if (window.toggleProgramsDropdown) {
            window.toggleProgramsDropdown(e);
          } else {
            toggleSubmenu(this, e);
          }
        } else {
          toggleSubmenu(this, e);
        }
      };
    });

    // Global Escape key handler
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closeAllSubmenus();
        closeDrawer();
      }
    });

    // Close submenus on click outside
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.nav-item.has-dropdown') && !e.target.closest('#navToggleBtn') && !e.target.closest('.nav-menu')) {
        closeAllSubmenus();
        if (navMenu && navMenu.classList.contains('mobile-open')) {
          closeDrawer();
        }
      }
    });

    // Close drawer when selecting an actual page link (not dropdown toggle)
    rmuNavbar.addEventListener('click', function (e) {
      const pageLink = e.target.closest('a');
      if (pageLink && !pageLink.classList.contains('dropdown-toggle') && pageLink.getAttribute('href') !== 'javascript:void(0);') {
        closeDrawer();
      }
    });

    // Keyboard support for submenu toggles
    rmuNavbar.addEventListener('keydown', function (e) {
      const toggleLink = e.target.closest('.nav-item.has-dropdown > .nav-link, .nav-item.has-dropdown .dropdown-toggle');
      if (toggleLink && (e.key === 'Enter' || e.key === ' ')) {
        e.preventDefault();
        toggleSubmenu(toggleLink);
      }
    });

    // Automated verification triggers for testing
    try {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('test_overflow_open') === '1' && navToggleBtn) {
        setTimeout(() => {
          navToggleBtn.click();
        }, 150);
      }
      if (urlParams.get('test_programs_click') === '1' && navToggleBtn) {
        setTimeout(() => {
          navToggleBtn.click();
          setTimeout(() => {
            const progLink = document.getElementById('programsMenuLink');
            if (progLink) progLink.click();
          }, 150);
        }, 150);
      }
      if (urlParams.get('test_programs_toggle_twice') === '1' && navToggleBtn) {
        setTimeout(() => {
          navToggleBtn.click();
          setTimeout(() => {
            const progLink = document.getElementById('programsMenuLink');
            if (progLink) {
              progLink.click();
              setTimeout(() => {
                progLink.click();
              }, 300);
            }
          }, 150);
        }, 150);
      }
      if (urlParams.get('test_program_subject_click') && navToggleBtn) {
        const targetSlug = urlParams.get('test_program_subject_click');
        setTimeout(() => {
          navToggleBtn.click();
          setTimeout(() => {
            const progLink = document.getElementById('programsMenuLink');
            if (progLink) {
              progLink.click();
              setTimeout(() => {
                const progHeader = document.getElementById('programHeader-' + targetSlug);
                if (progHeader) progHeader.click();
              }, 200);
            }
          }, 200);
        }, 150);
      }
    } catch (err) {}
  }

  // 3. Auto-dismiss Alert Messages
  const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'opacity 0.5s ease';
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 500);
    }, 7000);
  });
});

