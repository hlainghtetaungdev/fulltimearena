(function () {
  const config = window.FTA_CONFIG || {};
  const baseUrl = config.baseUrl || '';
  const readNotificationsKey = 'fta_read_notification_ids';
  const hiddenNotificationsKey = 'fta_hidden_notification_ids';

  function todayKey() {
    const now = new Date();
    return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
  }

  function initSettingsPanel() {
    const toggle = document.querySelector('[data-settings-toggle]');
    const panel = document.querySelector('[data-settings-panel]');
    if (!toggle || !panel) return;

    toggle.addEventListener('click', () => {
      const isHidden = panel.hasAttribute('hidden');
      panel.toggleAttribute('hidden', !isHidden);
      toggle.setAttribute('aria-expanded', String(isHidden));
    });

    document.addEventListener('click', (event) => {
      if (panel.hasAttribute('hidden')) return;
      if (panel.contains(event.target) || toggle.contains(event.target)) return;
      panel.setAttribute('hidden', '');
      toggle.setAttribute('aria-expanded', 'false');
    });
  }

  async function clearBrowserCache() {
    if ('caches' in window) {
      const keys = await caches.keys();
      await Promise.all(keys.map((key) => caches.delete(key)));
    }

    if ('serviceWorker' in navigator) {
      const registrations = await navigator.serviceWorker.getRegistrations();
      await Promise.all(registrations.map((registration) => registration.update()));
    }
  }

  function initUpdateButton() {
    document.querySelectorAll('[data-update-app]').forEach((button) => {
      button.addEventListener('click', async () => {
        button.disabled = true;
        try {
          await clearBrowserCache();
          localStorage.removeItem('fta_telegram_hide_date');
        } finally {
          const url = new URL(window.location.href);
          url.searchParams.set('refresh', Date.now().toString());
          window.location.replace(url.toString());
        }
      });
    });
  }

  function initClearCacheButton() {
    document.querySelectorAll('[data-clear-cache]').forEach((button) => {
      button.addEventListener('click', async () => {
        button.disabled = true;
        const label = button.querySelector('span');
        const originalLabel = label ? label.textContent : '';
      try {
        await clearBrowserCache();
        if (label && button.dataset.doneLabel) label.textContent = button.dataset.doneLabel;
      } finally {
        window.setTimeout(() => {
          if (label && originalLabel) label.textContent = originalLabel;
          button.disabled = false;
        }, 1100);
      }
      });
    });
  }

  function initSlider() {
    document.querySelectorAll('[data-ad-slider]').forEach((slider) => {
      const slides = Array.from(slider.querySelectorAll('[data-slide]'));
      const dotRoot = slider.closest('.slide-section') || document;
      const dots = Array.from(dotRoot.querySelectorAll('[data-ad-dot]'));
      if (slides.length < 2) return;

      let index = 0;
      let resumeTimer = null;
      let scrollTimer = null;
      let autoTimer = null;

      const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

      const animateScroll = (targetLeft) => {
        const startLeft = slider.scrollLeft;
        const distance = targetLeft - startLeft;
        const duration = 1200;
        const started = performance.now();

        const step = (now) => {
          const progress = Math.min(1, (now - started) / duration);
          slider.scrollLeft = startLeft + distance * ease(progress);
          if (progress < 1) requestAnimationFrame(step);
        };

        requestAnimationFrame(step);
      };

      const setActive = (nextIndex) => {
        slides[index].classList.remove('active');
        if (dots[index]) dots[index].classList.remove('active');
        index = (nextIndex + slides.length) % slides.length;
        slides[index].classList.add('active');
        if (dots[index]) dots[index].classList.add('active');
      };

      const show = (nextIndex) => {
        setActive(nextIndex);
        animateScroll(slider.clientWidth * index);
      };

      const startAuto = () => {
        window.clearInterval(autoTimer);
        autoTimer = window.setInterval(() => {
          if (document.hidden) return;
          show(index + 1);
        }, 6200);
      };

      const pauseAuto = () => {
        window.clearInterval(autoTimer);
        window.clearTimeout(resumeTimer);
        resumeTimer = window.setTimeout(startAuto, 4500);
      };

      slider.addEventListener('pointerdown', pauseAuto);
      slider.addEventListener('touchstart', pauseAuto, { passive: true });
      dots.forEach((dot, dotIndex) => {
        dot.addEventListener('click', () => {
          pauseAuto();
          show(dotIndex);
        });
      });
      slider.addEventListener('scroll', () => {
        window.clearTimeout(scrollTimer);
        scrollTimer = window.setTimeout(() => {
          const nextIndex = Math.round(slider.scrollLeft / Math.max(1, slider.clientWidth));
          setActive(nextIndex);
        }, 90);
      }, { passive: true });

      window.addEventListener('resize', () => {
        slider.scrollLeft = slider.clientWidth * index;
      });

      startAuto();
    });
  }

  function initTelegramModal() {
    const modal = document.querySelector('[data-telegram-modal]');
    if (!modal) return;

    const hideDate = localStorage.getItem('fta_telegram_hide_date');
    if (hideDate !== todayKey()) {
      window.setTimeout(() => modal.removeAttribute('hidden'), 700);
    }

    const close = () => {
      const checkbox = modal.querySelector('[data-hide-telegram-today]');
      if (checkbox && checkbox.checked) {
        localStorage.setItem('fta_telegram_hide_date', todayKey());
      }
      modal.hidden = true;
    };

    modal.querySelectorAll('[data-close-telegram]').forEach((button) => {
      button.addEventListener('click', close);
    });

    modal.addEventListener('click', (event) => {
      if (event.target === modal) close();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !modal.hidden) close();
    });
  }

  function initAgentContactModal() {
    const modal = document.querySelector('[data-agent-contact-modal]');
    const toggle = document.querySelector('[data-agent-contact-toggle]');
    if (!modal || !toggle) return;

    const close = () => {
      modal.hidden = true;
    };

    toggle.addEventListener('click', () => {
      modal.hidden = false;
    });
    modal.querySelectorAll('[data-agent-contact-close]').forEach((button) => {
      button.addEventListener('click', close);
    });
    modal.addEventListener('click', (event) => {
      if (event.target === modal) close();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !modal.hidden) close();
    });
  }

  function initCopyButtons() {
    document.querySelectorAll('[data-copy]').forEach((button) => {
      button.addEventListener('click', async (event) => {
        event.preventDefault();
        event.stopPropagation();
        const value = button.getAttribute('data-copy') || '';
        const originalText = button.textContent;
        try {
          await navigator.clipboard.writeText(value);
          button.classList.add('copied');
          button.closest('.agent-payment-card, .payout-channel-card, .agent-payment-preview-card')?.classList.add('copy-success');
          button.textContent = 'Copied';
          window.setTimeout(() => {
            button.classList.remove('copied');
            button.closest('.agent-payment-card, .payout-channel-card, .agent-payment-preview-card')?.classList.remove('copy-success');
          }, 900);
        } catch (error) {
          const input = document.createElement('input');
          input.value = value;
          document.body.appendChild(input);
          input.select();
          document.execCommand('copy');
          input.remove();
        }
        window.setTimeout(() => {
          button.textContent = originalText;
        }, 900);
      });
    });
  }

  function initPaymentDetailModal() {
    const modal = document.querySelector('[data-payment-detail-modal]');
    const body = modal ? modal.querySelector('[data-payment-detail-body]') : null;
    if (!modal || !body) return;

    const close = () => {
      modal.hidden = true;
      body.innerHTML = '';
    };

    document.querySelectorAll('[data-payment-detail-open]').forEach((button) => {
      button.addEventListener('click', () => {
        const source = document.getElementById(button.dataset.paymentDetailOpen || '');
        if (!source) return;
        body.innerHTML = source.innerHTML;
        modal.hidden = false;
      });
    });

    modal.querySelectorAll('[data-payment-detail-close]').forEach((button) => button.addEventListener('click', close));
    modal.addEventListener('click', (event) => {
      if (event.target === modal) close();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !modal.hidden) close();
    });
  }

  function initUnitChoiceModal() {
    const modal = document.querySelector('[data-unit-choice-modal]');
    if (!modal) return;
    const close = () => {
      modal.hidden = true;
    };

    document.querySelectorAll('[data-unit-choice-toggle]').forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        modal.hidden = false;
      });
    });
    modal.querySelectorAll('[data-unit-choice-close]').forEach((button) => button.addEventListener('click', close));
    modal.addEventListener('click', (event) => {
      if (event.target === modal) close();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !modal.hidden) close();
    });
  }

  function initSubmitLocks() {
    document.querySelectorAll('form[data-lock-submit]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        if (form.dataset.submitting === '1') {
          event.preventDefault();
          return;
        }
        form.dataset.submitting = '1';
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
          button.dataset.originalLabel = button.textContent || '';
          button.disabled = true;
          button.classList.add('is-processing');
          button.textContent = 'Processing...';
        });
      });
    });
  }

  function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return;
    window.addEventListener('load', () => {
      navigator.serviceWorker.register(baseUrl + '/service-worker.php').catch(() => {});
    });
  }

  function readNotificationIds() {
    try {
      const value = JSON.parse(localStorage.getItem(readNotificationsKey) || '[]');
      return new Set(Array.isArray(value) ? value.map(Number).filter(Number.isFinite) : []);
    } catch (error) {
      return new Set();
    }
  }

  function hiddenNotificationIds() {
    try {
      const value = JSON.parse(localStorage.getItem(hiddenNotificationsKey) || '[]');
      return new Set(Array.isArray(value) ? value.map(Number).filter(Number.isFinite) : []);
    } catch (error) {
      return new Set();
    }
  }

  async function updateInboxBadge() {
    const badges = Array.from(document.querySelectorAll('[data-mail-badge]'));
    if (!badges.length) return;

    try {
      const response = await fetch(baseUrl + '/api/app.php?type=notifications&lang=' + encodeURIComponent(config.lang || 'en'), {
        cache: 'no-store',
        headers: { Accept: 'application/json' }
      });
      const json = await response.json();
      const items = json && json.status === 'success' && Array.isArray(json.data) ? json.data : [];
      const readIds = readNotificationIds();
      const hiddenIds = hiddenNotificationIds();
      const unread = items.filter((item) => {
        const id = Number(item && item.id);
        return id && !readIds.has(id) && !hiddenIds.has(id);
      }).length;

      badges.forEach((badge) => {
        badge.hidden = unread === 0;
        badge.textContent = unread > 99 ? '99+' : String(unread);
      });
    } catch (error) {
      badges.forEach((badge) => {
        badge.hidden = true;
      });
    }
  }

  window.FTA_UPDATE_INBOX_BADGE = updateInboxBadge;
  window.addEventListener('fta:notifications-read-changed', updateInboxBadge);

  initSettingsPanel();
  initUpdateButton();
  initClearCacheButton();
  initSlider();
  initTelegramModal();
  initAgentContactModal();
  initCopyButtons();
  initPaymentDetailModal();
  initUnitChoiceModal();
  initSubmitLocks();
  registerServiceWorker();
  updateInboxBadge();
  window.setInterval(() => {
    if (!document.hidden) updateInboxBadge();
  }, 60000);
})();
