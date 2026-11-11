/**
 * Rabindra Maitree University (RMU) PWA Controller
 * Handles Service Worker registration, Android/Desktop install prompt hooks,
 * and non-intrusive iOS Safari Home Screen guidance.
 */
(function () {
  'use strict';

  if (window.__RMU_PWA_INIT__) return;
  window.__RMU_PWA_INIT__ = true;

  var appMeta = document.querySelector('meta[name="rmu-app-url"]');
  var rawAppUrl = appMeta ? (appMeta.getAttribute('content') || '') : '';
  var appUrl = rawAppUrl.replace(/\/+$/, '');
  var swUrl = (appUrl || '') + '/sw.js';

  var canRegisterSw =
    'serviceWorker' in navigator &&
    (window.location.protocol === 'https:' ||
      window.location.hostname === 'localhost' ||
      window.location.hostname === '127.0.0.1' ||
      window.location.hostname === '[::1]');

  if (canRegisterSw) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(swUrl).catch(function () {
        // Silent fail on unsupported environments
      });
    });
  }

  var deferredInstallPrompt = null;

  function showInstallTriggers() {
    var btns = document.querySelectorAll('[data-pwa-install-btn]');
    btns.forEach(function (btn) {
      btn.style.display = 'inline-flex';
      btn.hidden = false;
    });
    var containers = document.querySelectorAll('[data-pwa-install-wrap]');
    containers.forEach(function (el) {
      el.style.display = 'flex';
      el.hidden = false;
    });
  }

  function hideInstallTriggers() {
    var btns = document.querySelectorAll('[data-pwa-install-btn]');
    btns.forEach(function (btn) {
      btn.style.display = 'none';
      btn.hidden = true;
    });
    var containers = document.querySelectorAll('[data-pwa-install-wrap]');
    containers.forEach(function (el) {
      el.style.display = 'none';
      el.hidden = true;
    });
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredInstallPrompt = e;
    showInstallTriggers();
  });

  window.addEventListener('appinstalled', function () {
    deferredInstallPrompt = null;
    hideInstallTriggers();
  });

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-pwa-install-btn]');
    if (!trigger) return;
    e.preventDefault();
    if (!deferredInstallPrompt) return;

    deferredInstallPrompt.prompt();
    deferredInstallPrompt.userChoice
      .then(function () {
        deferredInstallPrompt = null;
        hideInstallTriggers();
      })
      .catch(function () {});
  });

  // iOS Safari Add-to-Home-Screen footer hint (only on iOS Safari outside standalone mode)
  function initIosHint() {
    var ua = window.navigator.userAgent || '';
    var isIos = /iphone|ipad|ipod/i.test(ua);
    var isSafari = /safari/i.test(ua) && !/crios|fxios|edgios|opios/i.test(ua);
    var isStandalone =
      window.navigator.standalone === true ||
      (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);

    if (!isIos || !isSafari || isStandalone) return;
    try {
      if (localStorage.getItem('rmu_ios_a2hs_dismissed') === '1') return;
    } catch (err) {}

    var iosHints = document.querySelectorAll('[data-pwa-ios-hint]');
    iosHints.forEach(function (el) {
      el.style.display = 'flex';
      el.hidden = false;
    });
  }

  document.addEventListener('click', function (e) {
    var dismiss = e.target.closest('[data-pwa-ios-dismiss]');
    if (!dismiss) return;
    e.preventDefault();
    var box = dismiss.closest('[data-pwa-ios-hint]');
    if (box) {
      box.style.display = 'none';
      box.hidden = true;
    }
    try {
      localStorage.setItem('rmu_ios_a2hs_dismissed', '1');
    } catch (err) {}
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initIosHint);
  } else {
    initIosHint();
  }
})();
