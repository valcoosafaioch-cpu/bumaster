document.addEventListener('DOMContentLoaded', function () {
    initProductGallery();
    initProductFlags();
    initProductDescription();
    initFeedbackTabs();
    initAdminReplyToggles();
    initReviewGallery();
    initReviewUploadPreview();
    initReviewRating();
    initAutoGrowTextareas();
    initReviewAjaxSubmit();
});

function initProductGallery() {
  const wrap = document.querySelector('.pp-images-wrap');
  if (!wrap) return;

  const thumbsAside = wrap.querySelector('.pp-thumbs');
  const thumbsScroll = thumbsAside ? thumbsAside.querySelector('.pp-thumbs-scroll') : null;
  const mainImg = wrap.querySelector('#pp-main-photo');
  const prevBtn = wrap.querySelector('.pp-prev');
  const nextBtn = wrap.querySelector('.pp-next');
  const upBtn = thumbsAside ? thumbsAside.querySelector('.pp-thumbs-up') : null;
  const dnBtn = thumbsAside ? thumbsAside.querySelector('.pp-thumbs-down') : null;
  const mainBox = wrap.querySelector('.pp-aspect-4x5');

  if (!thumbsScroll || !mainImg || !prevBtn || !nextBtn || !mainBox) {
    return;
  }

  const thumbs = () => Array.from(thumbsScroll.querySelectorAll('.pp-thumb'));

  function setActive(container, idx) {
    const arr = Array.from(container.querySelectorAll('.pp-thumb'));

    arr.forEach(function (thumb, i) {
      thumb.classList.toggle('is-active', i === idx);
    });

    if (arr[idx] && arr[idx].scrollIntoView) {
      arr[idx].scrollIntoView({ block: 'nearest' });
    }
  }

  function updateArrowsVisibility(scrollBox, up, dn) {
    if (!scrollBox || !up || !dn) return;

    const canUp = scrollBox.scrollTop > 2;
    const canDn = scrollBox.scrollTop + scrollBox.clientHeight < scrollBox.scrollHeight - 2;

    up.hidden = !canUp;
    dn.hidden = !canDn;
  }

  function syncThumbsHeight() {
    if (!mainBox || !thumbsScroll) return;

    const h = mainBox.clientHeight;
    thumbsScroll.style.maxHeight = h + 'px';
    updateArrowsVisibility(thumbsScroll, upBtn, dnBtn);
  }

  let idx = 0;

  (function syncInitialIndex() {
    const arr = thumbs();
    const activeIndex = arr.findIndex(function (thumb) {
      return thumb.classList.contains('is-active');
    });

    if (activeIndex >= 0) {
      idx = activeIndex;
    }
  })();

  thumbs().forEach(function (thumb, i) {
    thumb.addEventListener('click', function () {
      idx = i;
      mainImg.src = thumb.dataset.large || thumb.src;
      setActive(thumbsScroll, idx);

      document.dispatchEvent(new CustomEvent('pp-image-changed', {
        detail: { index: idx }
      }));
    });
  });

  function step(delta) {
    const arr = thumbs();
    if (!arr.length) return;

    idx = (idx + delta + arr.length) % arr.length;

    const thumb = arr[idx];
    mainImg.src = thumb.dataset.large || thumb.src;
    setActive(thumbsScroll, idx);

    document.dispatchEvent(new CustomEvent('pp-image-changed', {
      detail: { index: idx }
    }));

    if (lightbox && lightbox.classList.contains('is-open')) {
      setActive(lbThumbsScroll, idx);
      lbPhoto.src = mainImg.src;
    }
  }

  prevBtn.addEventListener('click', function () {
    step(-1);
  });

  nextBtn.addEventListener('click', function () {
    step(1);
  });

  function pageScroll(dir) {
    const page = thumbsScroll.clientHeight - 20;
    thumbsScroll.scrollBy({
      top: dir * page,
      behavior: 'smooth'
    });
  }

  if (upBtn) {
    upBtn.addEventListener('click', function () {
      pageScroll(-1);
    });
  }

  if (dnBtn) {
    dnBtn.addEventListener('click', function () {
      pageScroll(1);
    });
  }

  thumbsScroll.addEventListener('scroll', function () {
    updateArrowsVisibility(thumbsScroll, upBtn, dnBtn);
  });

  window.addEventListener('load', syncThumbsHeight);
  window.addEventListener('resize', syncThumbsHeight);
  window.addEventListener('resize', function () {
    updateArrowsVisibility(thumbsScroll, upBtn, dnBtn);
  });

  updateArrowsVisibility(thumbsScroll, upBtn, dnBtn);

  const lightbox = document.querySelector('.pp-lightbox');
  if (!lightbox) return;

  const lbPhoto = lightbox.querySelector('.pp-lightbox-photo');
  const lbPrev = lightbox.querySelector('.pp-prev');
  const lbNext = lightbox.querySelector('.pp-next');
  const lbThumbsScroll = lightbox.querySelector('.pp-thumbs-scroll');
  const lbUp = lightbox.querySelector('.pp-thumbs-up');
  const lbDn = lightbox.querySelector('.pp-thumbs-down');

  if (!lbPhoto || !lbPrev || !lbNext || !lbThumbsScroll || !lbUp || !lbDn) {
    return;
  }

  function buildLightboxThumbs() {
    lbThumbsScroll.innerHTML = '';

    const arr = thumbs();

    arr.forEach(function (thumb, i) {
      const img = document.createElement('img');
      img.className = 'pp-thumb' + (i === idx ? ' is-active' : '');
      img.src = thumb.src;
      img.alt = thumb.alt || '';
      img.dataset.large = thumb.dataset.large || thumb.src;

      img.addEventListener('click', function () {
        idx = i;
        const large = img.dataset.large;

        mainImg.src = large;
        lbPhoto.src = large;

        setActive(thumbsScroll, idx);
        setActive(lbThumbsScroll, idx);

        document.dispatchEvent(new CustomEvent('pp-image-changed', {
          detail: { index: idx }
        }));
      });

      lbThumbsScroll.appendChild(img);
    });
  }

  function openLightbox() {
    lbPhoto.src = mainImg.src;
    buildLightboxThumbs();
    setActive(lbThumbsScroll, idx);

    lightbox.classList.add('is-open');
    document.documentElement.classList.add('pp-no-scroll');

    requestAnimationFrame(function () {
      updateArrowsVisibility(lbThumbsScroll, lbUp, lbDn);
    });

    setTimeout(function () {
      updateArrowsVisibility(lbThumbsScroll, lbUp, lbDn);
    }, 150);
  }

  function closeLightbox() {
    lightbox.classList.remove('is-open');
    document.documentElement.classList.remove('pp-no-scroll');
  }

  [mainBox, mainImg].forEach(function (element) {
    element.addEventListener('click', function (event) {
      if (event.target.closest('.pp-main-nav')) return;
      openLightbox();
    });
  });

  lightbox.addEventListener('click', function (event) {
    if (event.target.hasAttribute('data-close') || event.target.classList.contains('pp-lightbox')) {
      closeLightbox();
    }
  });

  lbPrev.addEventListener('click', function () {
    step(-1);
    buildLightboxThumbs();
    setActive(lbThumbsScroll, idx);
    lbPhoto.src = mainImg.src;
  });

  lbNext.addEventListener('click', function () {
    step(1);
    buildLightboxThumbs();
    setActive(lbThumbsScroll, idx);
    lbPhoto.src = mainImg.src;
  });

  lbUp.addEventListener('click', function () {
    const page = lbThumbsScroll.clientHeight - 20;
    lbThumbsScroll.scrollBy({
      top: -page,
      behavior: 'smooth'
    });
  });

  lbDn.addEventListener('click', function () {
    const page = lbThumbsScroll.clientHeight - 20;
    lbThumbsScroll.scrollBy({
      top: page,
      behavior: 'smooth'
    });
  });

  lbThumbsScroll.addEventListener('scroll', function () {
    updateArrowsVisibility(lbThumbsScroll, lbUp, lbDn);
  });
}

function initProductFlags() {
  const flags = document.querySelector('.pp-aspect-4x5 .pp-flags');
  if (!flags) return;

  function updateFlags(i) {
    flags.style.display = (i === 0) ? '' : 'none';
  }

  updateFlags(0);

  document.addEventListener('pp-image-changed', function (event) {
    const i = event.detail && typeof event.detail.index === 'number'
      ? event.detail.index
      : 0;

    updateFlags(i);
  });
}

function initProductDescription() {
  const desc = document.getElementById('pp-desc-text');
  const toggle = document.getElementById('pp-desc-toggle');

  if (!desc || !toggle) return;

  const textSpan = toggle.querySelector('.pp-desc-toggle-text');

  function setState(expanded) {
    if (expanded) {
      desc.classList.remove('is-collapsed');
      desc.classList.add('is-expanded');
      toggle.classList.add('is-expanded');
      toggle.setAttribute('aria-expanded', 'true');

      if (textSpan) {
        textSpan.textContent = 'Свернуть';
      }
    } else {
      desc.classList.add('is-collapsed');
      desc.classList.remove('is-expanded');
      toggle.classList.remove('is-expanded');
      toggle.setAttribute('aria-expanded', 'false');

      if (textSpan) {
        textSpan.textContent = 'Показать полностью';
      }
    }
  }

  function init() {
    setState(false);

    requestAnimationFrame(function () {
      const canScroll = desc.scrollHeight > desc.clientHeight + 4;

      if (!canScroll) {
        desc.classList.remove('is-collapsed');
        desc.classList.remove('is-expanded');
        toggle.style.display = 'none';
      }
    });
  }

  toggle.addEventListener('click', function () {
    const isCollapsed = desc.classList.contains('is-collapsed');
    setState(isCollapsed);
  });

  init();
}

function initFeedbackTabs() {
  const tabs = Array.from(document.querySelectorAll('.pp-feedback-tab'));
  const panels = Array.from(document.querySelectorAll('.pp-feedback-panel'));

  if (!tabs.length || !panels.length) return;

  function activateTab(targetId) {
    panels.forEach(function (panel) {
      panel.classList.toggle('is-active', panel.id === targetId);
      panel.hidden = panel.id !== targetId;
    });

    tabs.forEach(function (tab) {
      const isActive = tab.getAttribute('data-tab-target') === targetId;
      tab.classList.toggle('is-active', isActive);
      tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      const targetId = tab.getAttribute('data-tab-target');
      if (!targetId) return;
      activateTab(targetId);
    });
  });

  const activeTab = tabs.find(function (tab) {
    return tab.classList.contains('is-active');
  });

  if (activeTab) {
    const targetId = activeTab.getAttribute('data-tab-target');
    if (targetId) {
      activateTab(targetId);
    }
  }
}

function initAdminReplyToggles() {
  const buttons = document.querySelectorAll('.js-admin-reply-toggle');

  buttons.forEach(function (button) {
    const targetId = button.getAttribute('data-target');
    if (!targetId) return;

    const form = document.getElementById(targetId);
    if (!form) return;

    button.addEventListener('click', function () {
      form.classList.toggle('is-collapsed');
    });
  });
}

function initReviewGallery() {
  const lightbox = document.getElementById('pp-review-lightbox');
  const lightboxImage = document.getElementById('pp-review-lightbox-image');
  const openButtons = document.querySelectorAll('[data-pp-review-open-gallery]');
  const closeButtons = document.querySelectorAll('[data-pp-review-close]');
  const prevButton = document.querySelector('[data-pp-review-prev]');
  const nextButton = document.querySelector('[data-pp-review-next]');

  if (!lightbox || !lightboxImage) return;

  let galleryImages = [];
  let currentIndex = 0;

  function renderImage() {
    if (!galleryImages.length) return;

    lightboxImage.src = galleryImages[currentIndex].src;
    lightboxImage.alt = galleryImages[currentIndex].alt || '';
  }

  function openGallery(images, startIndex) {
    galleryImages = images || [];
    currentIndex = startIndex || 0;

    if (!galleryImages.length) return;

    renderImage();
    lightbox.hidden = false;
    document.documentElement.classList.add('pp-no-scroll');
  }

  function closeGallery() {
    lightbox.hidden = true;
    lightboxImage.src = '';
    lightboxImage.alt = '';
    document.documentElement.classList.remove('pp-no-scroll');
    galleryImages = [];
    currentIndex = 0;
  }

  function showPrev() {
    if (!galleryImages.length) return;

    currentIndex = (currentIndex - 1 + galleryImages.length) % galleryImages.length;
    renderImage();
  }

  function showNext() {
    if (!galleryImages.length) return;

    currentIndex = (currentIndex + 1) % galleryImages.length;
    renderImage();
  }

  openButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      let images = [];
      const rawImages = button.getAttribute('data-gallery-images');
      const startIndex = parseInt(button.getAttribute('data-gallery-index'), 10) || 0;

      if (rawImages) {
        try {
          images = JSON.parse(rawImages);
        } catch (e) {
          images = [];
        }
      }

      openGallery(images, startIndex);
    });
  });

  closeButtons.forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.stopPropagation();
      closeGallery();
    });
  });

  if (prevButton) {
    prevButton.addEventListener('click', function (event) {
      event.stopPropagation();
      showPrev();
    });
  }

  if (nextButton) {
    nextButton.addEventListener('click', function (event) {
      event.stopPropagation();
      showNext();
    });
  }

  lightbox.addEventListener('click', function (event) {
    if (event.target.hasAttribute('data-pp-review-close') || event.target.classList.contains('pp-review-lightbox')) {
      closeGallery();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (lightbox.hidden) return;

    if (event.key === 'Escape') {
      closeGallery();
    }

    if (event.key === 'ArrowLeft') {
      showPrev();
    }

    if (event.key === 'ArrowRight') {
      showNext();
    }
  });
}

function initReviewUploadPreview() {
  const input = document.getElementById('review_images');
  const preview = document.getElementById('pp-review-upload-preview');

  if (!input || !preview) return;

  input.addEventListener('change', function () {
    preview.innerHTML = '';

    const files = Array.prototype.slice.call(input.files || [], 0, 5);

    files.forEach(function (file) {
      if (!file.type || file.type.indexOf('image/') !== 0) {
        return;
      }

      const reader = new FileReader();

      reader.onload = function (event) {
        const item = document.createElement('div');
        item.className = 'pp-feedback-upload-preview-item';

        const img = document.createElement('img');
        img.className = 'pp-feedback-upload-preview-thumb';
        img.src = event.target.result;
        img.alt = file.name;

        item.appendChild(img);
        preview.appendChild(item);
      };

      reader.readAsDataURL(file);
    });
  });
}

function initReviewRating() {
  const container = document.querySelector('.pp-feedback-rating-inputs');
  if (!container) return;

  const stars = Array.prototype.slice.call(container.querySelectorAll('.pp-star-btn'));
  const hidden = document.getElementById('pp-review-rating');

  if (!hidden) return;

  let selected = parseInt(
    hidden.value || container.getAttribute('data-current-rating') || '0',
    10
  ) || 0;

  const minRating = parseInt(
    container.getAttribute('data-min-rating') || '0',
    10
  ) || 0;

  function updateDisplay(value) {
    const currentValue = value || 0;
    const base = Math.max(currentValue, minRating);

    stars.forEach(function (button) {
      const starValue = parseInt(button.getAttribute('data-star'), 10);
      button.classList.toggle('is-filled', starValue <= base);
    });
  }

  updateDisplay(selected);

  stars.forEach(function (button) {
    const starValue = parseInt(button.getAttribute('data-star'), 10);

    button.addEventListener('mouseenter', function () {
      if (starValue < minRating) return;
      updateDisplay(starValue);
    });

    button.addEventListener('mouseleave', function () {
      updateDisplay(selected);
    });

    button.addEventListener('click', function () {
      if (starValue < minRating) return;

      selected = starValue;
      hidden.value = String(selected);
      updateDisplay(selected);
    });
  });
}

function initReviewAjaxSubmit() {
  const form = document.getElementById('pp-review-form');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const submitBtn = document.getElementById('pp-review-submit');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Отправка...';
    }

    const formData = new FormData(form);

    // 👉 Ключевой момент — говорим серверу, что это AJAX
    formData.append('is_ajax', '1');

    fetch(window.location.href, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
    .then(res => res.json())
    .then(data => {

      // очистка старых сообщений
      const oldMsg = form.querySelector('.pp-feedback-error, .pp-feedback-success');
      if (oldMsg) oldMsg.remove();

      if (data.error) {
        showFormMessage(form, data.error, 'error');
      }

      if (data.success) {
        showFormMessage(form, data.success, 'success');

        // скрываем форму
        form.style.display = 'none';

        // 👉 перезагружаем только блок отзывов
        reloadReviewsBlock();
      }

    })
    .catch(() => {
      showFormMessage(form, 'Ошибка отправки. Попробуйте ещё раз.', 'error');
    })
    .finally(() => {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Оставить отзыв';
      }
    });
  });
}

function showFormMessage(form, text, type) {
  const p = document.createElement('p');
  p.className = 'pp-feedback-' + type;
  p.textContent = text;

  form.prepend(p);
}

function reloadReviewsBlock() {
  const container = document.querySelector('#pp-tab-reviews');
  if (!container) return;

  fetch(window.location.href, { credentials: 'same-origin' })
    .then(res => res.text())
    .then(html => {
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const newBlock = doc.querySelector('#pp-tab-reviews');

      if (newBlock) {
        container.innerHTML = newBlock.innerHTML;

        initAdminReplyToggles();
        initReviewGallery();
      }
    });
}

function initAutoGrowTextareas() {
  const textareas = document.querySelectorAll('.pp-feedback-form textarea');
  if (!textareas.length) return;

  textareas.forEach(function (textarea) {
    function autoGrow() {
      textarea.style.height = 'auto';

      const computed = window.getComputedStyle(textarea);
      const maxHeight = parseInt(computed.maxHeight, 10) || 330;
      const nextHeight = Math.min(textarea.scrollHeight, maxHeight);

      textarea.style.height = nextHeight + 'px';

      if (textarea.scrollHeight > maxHeight) {
        textarea.style.overflowY = 'auto';
      } else {
        textarea.style.overflowY = 'hidden';
      }
    }

    textarea.addEventListener('input', autoGrow);
    textarea.addEventListener('change', autoGrow);

    autoGrow();
  });
}