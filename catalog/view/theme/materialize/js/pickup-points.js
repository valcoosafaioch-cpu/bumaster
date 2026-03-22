document.addEventListener('DOMContentLoaded', function () {
  var modal = document.getElementById('pickup-point-modal');

  if (!modal) {
    return;
  }

  var modalTitle = document.getElementById('pickup-point-modal-title');
  var openButtons = document.querySelectorAll('.js-pickup-modal-open');
  var closeButtons = document.querySelectorAll('.js-pickup-modal-close');
  var stubMode = modal.querySelector('[data-modal-mode="stub"]');
  var widgetMode = modal.querySelector('[data-modal-mode="widget"]');
  var widgetContainer = document.getElementById('pickup-point-cdek-widget-container');
  var cdekSelectionBox = document.getElementById('pickup-point-cdek-selection');
  var cdekSelectionAddress = document.getElementById('pickup-point-cdek-selection-address');
  var cdekSelectionCode = document.getElementById('pickup-point-cdek-selection-code');
  var cdekSaveButton = document.getElementById('pickup-point-cdek-save-button');

  var cdekWidgetInstance = null;
  var cdekWidgetInitPromise = null;
  var selectedCdekPoint = null;
  var selectedCdekTariff = null;
  var selectedCdekDeliveryMode = '';

  function getPointCodeLabel() {
    if (!widgetContainer) {
      return '';
    }

    return widgetContainer.getAttribute('data-point-code-label') || '';
  }

  function setModalMode(mode, widgetType) {
    if (stubMode) {
      stubMode.style.display = mode === 'stub' ? '' : 'none';
    }

    if (widgetMode) {
      widgetMode.style.display = mode === 'widget' && widgetType === 'cdek' ? '' : 'none';
    }

    if (widgetContainer) {
      widgetContainer.setAttribute(
        'data-widget-active',
        mode === 'widget' && widgetType === 'cdek' ? '1' : '0'
      );
    }
  }

  function resetSelectedCdekPoint() {
    selectedCdekPoint = null;
    selectedCdekTariff = null;
    selectedCdekDeliveryMode = '';

    if (cdekSelectionAddress) {
      cdekSelectionAddress.textContent = '';
    }

    if (cdekSelectionCode) {
      cdekSelectionCode.textContent = '';
    }

    if (cdekSelectionBox) {
      cdekSelectionBox.style.display = 'none';
    }

    if (cdekSaveButton) {
      cdekSaveButton.disabled = true;
      cdekSaveButton.textContent =
        cdekSaveButton.getAttribute('data-default-text') || 'Сохранить пункт выдачи';
    }
  }

  function updateSelectedCdekPointUi() {
    if (!selectedCdekPoint || selectedCdekDeliveryMode !== 'office') {
      resetSelectedCdekPoint();
      return;
    }

    var addressParts = [];

    if (selectedCdekPoint.city) {
      addressParts.push(selectedCdekPoint.city);
    }

    if (selectedCdekPoint.address) {
      addressParts.push(selectedCdekPoint.address);
    }

    if (cdekSelectionAddress) {
      cdekSelectionAddress.textContent = addressParts.join(', ');
    }

    if (cdekSelectionCode) {
      var pointCodeLabel = getPointCodeLabel();
      var pointCode = selectedCdekPoint.code || '';

      cdekSelectionCode.textContent = pointCodeLabel
        ? pointCodeLabel + ': ' + pointCode
        : pointCode;
    }

    if (cdekSelectionBox) {
      cdekSelectionBox.style.display = '';
    }

    if (cdekSaveButton) {
      cdekSaveButton.disabled = false;
      cdekSaveButton.textContent =
        cdekSaveButton.getAttribute('data-default-text') || 'Сохранить пункт выдачи';
    }
  }

  function saveSelectedCdekPoint() {
    if (!selectedCdekPoint || selectedCdekDeliveryMode !== 'office') {
      return;
    }

    if (!widgetContainer || !widgetContainer.dataset.saveUrl) {
      alert('Не найден URL сохранения пункта выдачи');
      return;
    }

    if (cdekSaveButton) {
      cdekSaveButton.disabled = true;
      cdekSaveButton.textContent =
        cdekSaveButton.getAttribute('data-loading-text') || 'Сохраняем...';
    }

    var body = new URLSearchParams();
    body.append('service_code', 'cdek');
    body.append('delivery_mode', selectedCdekDeliveryMode);
    body.append('point_code', selectedCdekPoint.code || '');
    body.append('point_name', selectedCdekPoint.name || '');
    body.append('point_address', selectedCdekPoint.address || '');
    body.append('city', selectedCdekPoint.city || '');
    body.append('postal_code', selectedCdekPoint.postal_code || '');
    body.append('region', selectedCdekPoint.region || '');
    body.append('country', selectedCdekPoint.country_code || '');
    body.append('raw_payload', JSON.stringify(selectedCdekPoint || {}));
    body.append('tariff_json', JSON.stringify(selectedCdekTariff || {}));

    console.log('[CDEK] save payload', {
      service_code: 'cdek',
      delivery_mode: selectedCdekDeliveryMode,
      point_code: selectedCdekPoint.code || '',
      point_name: selectedCdekPoint.name || '',
      point_address: selectedCdekPoint.address || '',
      city: selectedCdekPoint.city || '',
      postal_code: selectedCdekPoint.postal_code || '',
      region: selectedCdekPoint.region || '',
      country: selectedCdekPoint.country_code || '',
      raw_payload: JSON.stringify(selectedCdekPoint || {}),
      tariff_json: JSON.stringify(selectedCdekTariff || {})
    });

    fetch(widgetContainer.dataset.saveUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (json) {
        if (!json || !json.success) {
          throw new Error((json && json.error) ? json.error : 'Не удалось сохранить пункт выдачи');
        }

        window.location.reload();
      })
      .catch(function (error) {
        if (cdekSaveButton) {
          cdekSaveButton.disabled = false;
          cdekSaveButton.textContent =
            cdekSaveButton.getAttribute('data-default-text') || 'Сохранить пункт выдачи';
        }

        alert(error.message || 'Не удалось сохранить пункт выдачи');
      });
  }

  function initCdekWidget() {
    if (!widgetContainer) {
      return Promise.reject(new Error('CDEK widget container not found'));
    }

    if (cdekWidgetInstance) {
      return Promise.resolve(cdekWidgetInstance);
    }

    if (cdekWidgetInitPromise) {
      return cdekWidgetInitPromise;
    }

    if (typeof window.CDEKWidget !== 'function') {
      return Promise.reject(new Error('CDEKWidget is not loaded'));
    }

    cdekWidgetInitPromise = new Promise(function (resolve, reject) {
      try {
        widgetContainer.innerHTML = '';

        cdekWidgetInstance = new window.CDEKWidget({
          root: 'pickup-point-cdek-widget-container',
          apiKey: widgetContainer.dataset.widgetApiKey || '',
          defaultLocation: [
            parseFloat(widgetContainer.dataset.widgetDefaultLng || '37.6176'),
            parseFloat(widgetContainer.dataset.widgetDefaultLat || '55.7558')
          ],
          servicePath: '/index.php?route=extension/module/cdek_widget/service',
          lang: widgetContainer.dataset.widgetLang || 'rus',

          hideFilters: {
            have_cashless: true,
            have_cash: true,
            is_dressing_room: true,
            type: true
          },

          forceFilters: {
            type: 'PVZ'
          },

          hideDeliveryOptions: {
            door: true,
            office: false
          },

          onChoose: function (deliveryMode, tariff, address) {
            selectedCdekDeliveryMode = deliveryMode || '';

            selectedCdekTariff = tariff ? {
              tariff_code: tariff.tariff_code || '',
              tariff_name: tariff.tariff_name || '',
              tariff_description: tariff.tariff_description || '',
              delivery_mode: tariff.delivery_mode || '',
              period_min: tariff.period_min || '',
              period_max: tariff.period_max || '',
              delivery_sum: tariff.delivery_sum || ''
            } : {};

            selectedCdekPoint = address ? {
              city_code: address.city_code || '',
              city: address.city || '',
              type: address.type || '',
              postal_code: address.postal_code || '',
              country_code: address.country_code || '',
              have_cashless: !!address.have_cashless,
              have_cash: !!address.have_cash,
              allowed_cod: !!address.allowed_cod,
              is_dressing_room: !!address.is_dressing_room,
              code: address.code || '',
              name: address.name || '',
              address: address.address || '',
              work_time: address.work_time || '',
              location: Array.isArray(address.location) ? address.location : []
            } : null;

            updateSelectedCdekPointUi();
          }
        });

        widgetContainer.dataset.widgetLoaded = '1';
        resolve(cdekWidgetInstance);
      } catch (error) {
        cdekWidgetInitPromise = null;
        cdekWidgetInstance = null;
        reject(error);
      }
    });

    return cdekWidgetInitPromise;
  }

  function openModal(title, serviceName, labelType, pickerMode, widgetType) {
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('pickup-modal-open');
    resetSelectedCdekPoint();

    if (modalTitle) {
      modalTitle.textContent = title;
    }

    if (pickerMode === 'widget' && widgetType === 'cdek') {
      setModalMode('widget', 'cdek');

      initCdekWidget().catch(function (error) {
        console.error('[CDEK] Widget init error', error);
      });
    } else {
      setModalMode('stub', '');
    }

    modal.setAttribute('data-service-name', serviceName);
    modal.setAttribute('data-label-type', labelType);
    modal.setAttribute('data-picker-mode', pickerMode || 'stub');
    modal.setAttribute('data-widget-type', widgetType || '');
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('pickup-modal-open');
    setModalMode('stub', '');
    resetSelectedCdekPoint();
  }

  openButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      openModal(
        button.getAttribute('data-modal-title') || '',
        button.getAttribute('data-service-name') || '',
        button.getAttribute('data-label-type') || 'pickup_point',
        button.getAttribute('data-picker-mode') || 'stub',
        button.getAttribute('data-widget-type') || ''
      );
    });
  });

  closeButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      closeModal();
    });
  });

  if (cdekSaveButton) {
    cdekSaveButton.addEventListener('click', function () {
      saveSelectedCdekPoint();
    });
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && modal.classList.contains('is-open')) {
      closeModal();
    }
  });
});