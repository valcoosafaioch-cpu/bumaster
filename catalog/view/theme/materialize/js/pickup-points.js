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

  var cdekWidgetContainer = document.getElementById('pickup-point-cdek-widget-container');
  var cdekSelectionBox = document.getElementById('pickup-point-cdek-selection');
  var cdekSelectionAddress = document.getElementById('pickup-point-cdek-selection-address');
  var cdekSelectionCode = document.getElementById('pickup-point-cdek-selection-code');
  var cdekSaveButton = document.getElementById('pickup-point-cdek-save-button');
  var cdekWidgetHint = document.getElementById('pickup-point-cdek-widget-hint');
  var cdekSelectionType = document.getElementById('pickup-point-cdek-selection-type');
  var cdekSelectionComment = document.getElementById('pickup-point-cdek-selection-comment');

  var yandexWidgetWrap = document.getElementById('pickup-point-yandex-widget-wrap');
  var yandexWidgetContainer = document.getElementById('pickup-point-yandex-widget-container');
  var yandexLoadingOverlay = document.getElementById('pickup-point-yandex-loading');
  var yandexSelectionBox = document.getElementById('pickup-point-yandex-selection');
  var yandexSelectionAddress = document.getElementById('pickup-point-yandex-selection-address');
  var yandexSelectionFormat = document.getElementById('pickup-point-yandex-selection-format');
  var yandexSelectionComment = document.getElementById('pickup-point-yandex-selection-comment');
  var yandexSaveButton = document.getElementById('pickup-point-yandex-save-button');

  var russianPostWidgetWrap = document.getElementById('pickup-point-russian-post-widget-wrap');
  var russianPostWidgetContainer = document.getElementById('pickup-point-russian-post-widget-container');
  var russianPostSelectionBox = document.getElementById('pickup-point-russian-post-selection');
  var russianPostSelectionAddress = document.getElementById('pickup-point-russian-post-selection-address');
  var russianPostSelectionType = document.getElementById('pickup-point-russian-post-selection-type');
  var russianPostSelectionComment = document.getElementById('pickup-point-russian-post-selection-comment');
  var russianPostSelectionCode = document.getElementById('pickup-point-russian-post-selection-code');
  var russianPostSaveButton = document.getElementById('pickup-point-russian-post-save-button');

  var cdekWidgetInstance = null;
  var cdekWidgetInitPromise = null;
  var selectedCdekPoint = null;
  var selectedCdekTariff = null;
  var selectedCdekDeliveryMode = '';

  var yandexWidgetInstance = null;
  var yandexWidgetInitPromise = null;
  var yandexWidgetHandlerBound = false;
  var yandexWidgetObserver = null;
  var yandexWidgetHideTimer = null;
  var selectedYandexPoint = null;
  var selectedRussianPostPoint = null;

  function getPointCodeLabel(container) {
    if (!container) {
      return '';
    }

    return container.getAttribute('data-point-code-label') || '';
  }

  function formatYandexPointSummary(point) {
    if (!point || !point.address) {
      return '';
    }

    var comment = (point.address.comment || '').toLowerCase();

    if (comment.indexOf('постамат') !== -1) {
      return 'Постамат';
    }

    if (comment.indexOf('5post') !== -1 ) {
      return 'ПВЗ 5Post';
    }

    if (comment.indexOf('яндекс маркет') !== -1) {
      return 'ПВЗ Яндекс.Маркет';
    }

    return 'ПВЗ Яндекс.Маркет';
  }

  function setButtonDefaultState(button) {
    if (!button) {
      return;
    }

    button.disabled = true;
    button.textContent = button.getAttribute('data-default-text') || 'Сохранить пункт выдачи';
  }

  function setButtonEnabledState(button) {
    if (!button) {
      return;
    }

    button.disabled = false;
    button.textContent = button.getAttribute('data-default-text') || 'Сохранить пункт выдачи';
  }

  function setButtonLoadingState(button) {
    if (!button) {
      return;
    }

    button.disabled = true;
    button.textContent = button.getAttribute('data-loading-text') || 'Сохраняем...';
  }

  function showYandexLoadingOverlay() {
    if (!yandexLoadingOverlay) {
      return;
    }

    yandexLoadingOverlay.style.display = 'flex';
  }

  function hideYandexLoadingOverlay() {
    if (!yandexLoadingOverlay) {
      return;
    }

    yandexLoadingOverlay.style.display = 'none';
  }

  function scheduleHideYandexLoadingOverlay() {
    if (yandexWidgetHideTimer) {
      window.clearTimeout(yandexWidgetHideTimer);
    }

    yandexWidgetHideTimer = window.setTimeout(function () {
      hideYandexLoadingOverlay();
    }, 900);
  }

  function setModalMode(mode, widgetType) {
    if (stubMode) {
      stubMode.style.display = mode === 'stub' ? '' : 'none';
    }

    if (widgetMode) {
      widgetMode.style.display = mode === 'widget' ? '' : 'none';
    }

    if (cdekWidgetContainer) {
      cdekWidgetContainer.style.display = mode === 'widget' && widgetType === 'cdek' ? '' : 'none';
      cdekWidgetContainer.setAttribute(
        'data-widget-active',
        mode === 'widget' && widgetType === 'cdek' ? '1' : '0'
      );
    }

    if (cdekWidgetHint) {
      cdekWidgetHint.style.display = mode === 'widget' && widgetType === 'cdek' ? '' : 'none';
    }

    if (yandexWidgetWrap) {
      yandexWidgetWrap.style.display = mode === 'widget' && widgetType === 'yandex' ? '' : 'none';
    }

    if (russianPostWidgetWrap) {
      russianPostWidgetWrap.style.display = mode === 'widget' && widgetType === 'russian_post' ? '' : 'none';
    }

    if (yandexWidgetContainer) {
      yandexWidgetContainer.setAttribute(
        'data-widget-active',
        mode === 'widget' && widgetType === 'yandex' ? '1' : '0'
      );
    }

    if (russianPostWidgetContainer) {
      russianPostWidgetContainer.setAttribute(
        'data-widget-active',
        mode === 'widget' && widgetType === 'russian_post' ? '1' : '0'
      );
    }

    if (cdekSelectionBox) {
      cdekSelectionBox.style.display = mode === 'widget' && widgetType === 'cdek' && selectedCdekPoint ? '' : 'none';
    }

    if (yandexSelectionBox) {
      yandexSelectionBox.style.display = mode === 'widget' && widgetType === 'yandex' && selectedYandexPoint ? '' : 'none';
    }

    if (russianPostSelectionBox) {
      russianPostSelectionBox.style.display =
        mode === 'widget' && widgetType === 'russian_post' && selectedRussianPostPoint ? '' : 'none';
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

    if (cdekSelectionType) {
      cdekSelectionType.textContent = '';
      cdekSelectionType.style.display = 'none';
    }

    if (cdekSelectionComment) {
      cdekSelectionComment.textContent = '';
      cdekSelectionComment.style.display = 'none';
    }

    setButtonDefaultState(cdekSaveButton);
  }

  function resetSelectedYandexPoint() {
    selectedYandexPoint = null;

    if (yandexSelectionAddress) {
      yandexSelectionAddress.textContent = '';
    }

    if (yandexSelectionFormat) {
      yandexSelectionFormat.textContent = '';
      yandexSelectionFormat.style.display = 'none';
    }

    if (yandexSelectionComment) {
      yandexSelectionComment.textContent = '';
      yandexSelectionComment.style.display = 'none';
    }

    if (yandexSelectionBox) {
      yandexSelectionBox.style.display = 'none';
    }

    setButtonDefaultState(yandexSaveButton);
  }

  function resetSelectedRussianPostPoint() {
    selectedRussianPostPoint = null;

    if (russianPostSelectionAddress) {
      russianPostSelectionAddress.textContent = '';
    }

    if (russianPostSelectionType) {
      russianPostSelectionType.textContent = '';
      russianPostSelectionType.style.display = 'none';
    }

    if (russianPostSelectionComment) {
      russianPostSelectionComment.textContent = '';
      russianPostSelectionComment.style.display = 'none';
    }

    if (russianPostSelectionCode) {
      russianPostSelectionCode.textContent = '';
      russianPostSelectionCode.style.display = 'none';
    }

    if (russianPostSelectionBox) {
      russianPostSelectionBox.style.display = 'none';
    }

    setButtonDefaultState(russianPostSaveButton);
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

    if (cdekSelectionType) {
      var typeText = '';

      if ((selectedCdekPoint.type || '').toUpperCase() === 'POSTAMAT') {
        typeText = 'Постамат';
      } else if ((selectedCdekPoint.type || '').toUpperCase() === 'PVZ') {
        typeText = 'Пункт выдачи';
      } else if (selectedCdekPoint.type) {
        typeText = selectedCdekPoint.type;
      }

      if (typeText) {
        cdekSelectionType.textContent = typeText;
        cdekSelectionType.style.display = '';
      } else {
        cdekSelectionType.textContent = '';
        cdekSelectionType.style.display = 'none';
      }
    }

    if (cdekSelectionComment) {
      var comment = selectedCdekPoint.point_comment || '';

      if (comment) {
        cdekSelectionComment.textContent = 'Как добраться: ' + comment;
        cdekSelectionComment.style.display = '';
      } else {
        cdekSelectionComment.textContent = '';
        cdekSelectionComment.style.display = 'none';
      }
    }

    if (cdekSelectionBox) {
      cdekSelectionBox.style.display = '';
    }

  }

  function buildYandexAddress(point) {
    if (!point) {
      return '';
    }

    if (point.address && point.address.full_address) {
      return point.address.full_address;
    }

    var parts = [];

    if (point.address && point.address.locality) {
      parts.push(point.address.locality);
    }

    var streetHouse = [];

    if (point.address && point.address.street) {
      streetHouse.push(point.address.street);
    }

    if (point.address && point.address.house) {
      streetHouse.push(point.address.house);
    }

    if (streetHouse.length) {
      parts.push(streetHouse.join(', '));
    }

    if (point.address && point.address.comment) {
      parts.push(point.address.comment);
    }

    return parts.join(', ');
  }

  function formatRussianPostPointType(pointType) {
    if (!pointType) {
      return '';
    }

    if (pointType === 'postamat') {
      return 'Почтомат';
    }

    if (pointType === 'russian_post') {
      return 'Почтовое отделение';
    }

    if (pointType === 'additional_pvz') {
      return 'Партнёрский ПВЗ';
    }

    return '';
  }

  function buildRussianPostAddress(point) {
    if (!point) {
      return '';
    }

    var address = point.addressTo || '';
    var postalCode = point.indexTo || '';
    var pointType = point.pvzType || '';

    if (pointType === 'russian_post' && postalCode) {
      return postalCode + ', ' + address;
    }

    return address;
  }

  function updateSelectedYandexPointUi() {
    if (!selectedYandexPoint || !selectedYandexPoint.id) {
      resetSelectedYandexPoint();
      return;
    }

    var comment = selectedYandexPoint.address && selectedYandexPoint.address.comment
      ? selectedYandexPoint.address.comment
      : '';

    if (yandexSelectionAddress) {
      yandexSelectionAddress.textContent = buildYandexAddress(selectedYandexPoint);
    }

    if (yandexSelectionFormat) {
      var summary = formatYandexPointSummary(selectedYandexPoint);

      if (summary) {
        yandexSelectionFormat.textContent = summary;
        yandexSelectionFormat.style.display = '';
      } else {
        yandexSelectionFormat.textContent = '';
        yandexSelectionFormat.style.display = 'none';
      }
    }

    if (yandexSelectionComment) {
      if (comment) {
        yandexSelectionComment.textContent = 'Как добраться: ' + comment;
        yandexSelectionComment.style.display = '';
      } else {
        yandexSelectionComment.textContent = '';
        yandexSelectionComment.style.display = 'none';
      }
    }

    if (yandexSelectionBox) {
      yandexSelectionBox.style.display = '';
    }

    setButtonEnabledState(yandexSaveButton);
  }

  function updateSelectedRussianPostPointUi() {
    if (!selectedRussianPostPoint || !selectedRussianPostPoint.id) {
      resetSelectedRussianPostPoint();
      return;
    }

    var comment = selectedRussianPostPoint.deliveryDescription
      && selectedRussianPostPoint.deliveryDescription.description
        ? selectedRussianPostPoint.deliveryDescription.description
        : '';

    var pointCodeLabel = getPointCodeLabel(russianPostWidgetContainer);
    var pointCodeValue = selectedRussianPostPoint.indexTo || selectedRussianPostPoint.id || '';
    var pointTypeText = formatRussianPostPointType(selectedRussianPostPoint.pvzType);

    if (russianPostSelectionAddress) {
      russianPostSelectionAddress.textContent = buildRussianPostAddress(selectedRussianPostPoint);
    }

    if (russianPostSelectionType) {
      if (pointTypeText) {
        russianPostSelectionType.textContent = pointTypeText;
        russianPostSelectionType.style.display = '';
      } else {
        russianPostSelectionType.textContent = '';
        russianPostSelectionType.style.display = 'none';
      }
    }

    if (russianPostSelectionComment) {
      russianPostSelectionComment.textContent = '';
      russianPostSelectionComment.style.display = 'none';
    }

    if (russianPostSelectionCode) {
      russianPostSelectionCode.textContent = '';
      russianPostSelectionCode.style.display = 'none';
    }

    if (russianPostSelectionBox) {
      russianPostSelectionBox.style.display = '';
    }

    setButtonEnabledState(russianPostSaveButton);
  }

  function saveSelectedCdekPoint() {
    if (!selectedCdekPoint || selectedCdekDeliveryMode !== 'office') {
      return;
    }

    if (!cdekWidgetContainer || !cdekWidgetContainer.dataset.saveUrl) {
      alert('Не найден URL сохранения пункта выдачи');
      return;
    }

    setButtonLoadingState(cdekSaveButton);

    var body = new URLSearchParams();
    body.append('service_code', 'cdek');
    body.append('delivery_mode', selectedCdekDeliveryMode);
    body.append('point_code', selectedCdekPoint.code || '');
    body.append('point_type', selectedCdekPoint.type || '');
    body.append('point_name', selectedCdekPoint.name || '');
    body.append('point_address', selectedCdekPoint.address || '');
    body.append('point_comment', selectedCdekPoint.point_comment || '');
    body.append('city', selectedCdekPoint.city || '');
    body.append('postal_code', selectedCdekPoint.postal_code || '');
    body.append('region', selectedCdekPoint.region || '');
    body.append('country', selectedCdekPoint.country_code || '');
    body.append('location_json', JSON.stringify(Array.isArray(selectedCdekPoint.location) ? selectedCdekPoint.location : []));
    body.append('work_time', selectedCdekPoint.work_time || '');
    body.append(
      'raw_payload',
      JSON.stringify(
        (selectedCdekPoint && selectedCdekPoint.raw_point) ? selectedCdekPoint.raw_point : (selectedCdekPoint || {})
      )
    );
    body.append('tariff_json', JSON.stringify(selectedCdekTariff || {}));

    fetch(cdekWidgetContainer.dataset.saveUrl, {
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
        setButtonEnabledState(cdekSaveButton);
        alert(error.message || 'Не удалось сохранить пункт выдачи');
      });
  }

  function saveSelectedYandexPoint() {
    if (!selectedYandexPoint || !selectedYandexPoint.id) {
      return;
    }

    if (!yandexWidgetContainer || !yandexWidgetContainer.dataset.saveUrl) {
      alert('Не найден URL сохранения пункта выдачи');
      return;
    }

    setButtonLoadingState(yandexSaveButton);

    var address = selectedYandexPoint.address || {};
    var body = new URLSearchParams();

    body.append('service_code', 'yandex');
    body.append('point_code', selectedYandexPoint.id || '');
    body.append('point_type', selectedYandexPoint.type || '');
    body.append('point_name', selectedYandexPoint.name || '');
    body.append('point_address', buildYandexAddress(selectedYandexPoint));
    body.append('point_comment', address.comment || '');
    body.append('city', address.locality || '');
    body.append('postal_code', address.postal_code || '');
    body.append('region', address.region || address.sub_region || '');
    body.append('country', address.country || '');
    body.append('raw_payload', JSON.stringify(selectedYandexPoint || {}));

    fetch(yandexWidgetContainer.dataset.saveUrl, {
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
        setButtonEnabledState(yandexSaveButton);
        alert(error.message || 'Не удалось сохранить пункт выдачи');
      });
  }

  function saveSelectedRussianPostPoint() {
    if (!selectedRussianPostPoint || !selectedRussianPostPoint.id) {
      return;
    }

    if (!russianPostWidgetContainer || !russianPostWidgetContainer.dataset.saveUrl) {
      alert('Не найден URL сохранения пункта выдачи');
      return;
    }

    setButtonLoadingState(russianPostSaveButton);

    var pointTypeText = formatRussianPostPointType(selectedRussianPostPoint.pvzType);
    var body = new URLSearchParams();

    body.append('service_code', 'russian_post');
    body.append('point_code', selectedRussianPostPoint.id || '');
    body.append('point_type', selectedRussianPostPoint.pvzType || '');
    body.append('point_name', '');
    body.append('point_comment', '');
    body.append('point_address', buildRussianPostAddress(selectedRussianPostPoint));
    body.append('city', selectedRussianPostPoint.cityTo || '');
    body.append('postal_code', selectedRussianPostPoint.indexTo || '');
    body.append('region', selectedRussianPostPoint.regionTo || '');
    body.append('country', 'RU');
    body.append('raw_payload', JSON.stringify(selectedRussianPostPoint || {}));

    fetch(russianPostWidgetContainer.dataset.saveUrl, {
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
        setButtonEnabledState(russianPostSaveButton);
        alert(error.message || 'Не удалось сохранить пункт выдачи');
      });
  }

  function loadCdekOfficeDetails(pointCode, countryCode, servicePath) {
    if (!pointCode || !servicePath) {
      return Promise.resolve(null);
    }

    var detailsUrl = servicePath
      + '&action=office_details'
      + '&point_code=' + encodeURIComponent(pointCode)
      + '&country_code=' + encodeURIComponent((countryCode || 'RU').toUpperCase());

    return fetch(detailsUrl, {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then(function (response) {
        if (!response.ok) {
          return null;
        }

        return response.json();
      })
      .then(function (json) {
        if (!json || !json.success || !json.office) {
          return null;
        }

        return json.office;
      })
      .catch(function () {
        return null;
      });
  }

  function initCdekWidget() {
    if (!cdekWidgetContainer) {
      return Promise.reject(new Error('CDEK widget container not found'));
    }

    if (typeof window.CDEKWidget !== 'function') {
      return Promise.reject(new Error('CDEKWidget is not loaded'));
    }

    if (cdekWidgetInstance) {
      return Promise.resolve(cdekWidgetInstance);
    }

    if (cdekWidgetInitPromise) {
      return cdekWidgetInitPromise;
    }

    cdekWidgetInitPromise = new Promise(function (resolve, reject) {
      try {
        var widgetCountryCode = (cdekWidgetContainer.dataset.widgetCountryCode || '').toUpperCase();
        var cdekServicePath = '/index.php?route=extension/module/cdek_widget/service';

        if (widgetCountryCode) {
          cdekServicePath += '&country_code=' + encodeURIComponent(widgetCountryCode);
        }

        cdekWidgetContainer.innerHTML = '';

        cdekWidgetInstance = new window.CDEKWidget({
          root: 'pickup-point-cdek-widget-container',
          apiKey: cdekWidgetContainer.dataset.widgetApiKey || '',
          defaultLocation: [
            parseFloat(cdekWidgetContainer.dataset.widgetDefaultLng || '37.6176'),
            parseFloat(cdekWidgetContainer.dataset.widgetDefaultLat || '55.7558')
          ],
          servicePath: cdekServicePath,
          lang: cdekWidgetContainer.dataset.widgetLang || 'rus',

          hideFilters: {
            have_cashless: true,
            have_cash: true,
            is_dressing_room: true,
            type: true
          },

          hideDeliveryOptions: {
            door: true,
            office: false
          },

          onChoose: function (deliveryMode, tariff, address) {
            selectedCdekDeliveryMode = deliveryMode || '';
            selectedCdekTariff = tariff ? JSON.parse(JSON.stringify(tariff)) : null;

            if (deliveryMode !== 'office' || !address) {
              resetSelectedCdekPoint();
              return;
            }

            var rawAddress = JSON.parse(JSON.stringify(address));
            var selectedPoint = {
              code: rawAddress.code || '',
              type: rawAddress.type || '',
              name: rawAddress.name || '',
              address: rawAddress.address || '',
              point_comment: rawAddress.address_comment || '',
              city: rawAddress.city || '',
              postal_code: rawAddress.postal_code || '',
              region: rawAddress.region || '',
              country_code: widgetCountryCode || rawAddress.country_code || 'RU',
              location: Array.isArray(rawAddress.location) ? rawAddress.location.slice() : [],
              work_time: rawAddress.work_time || '',
              raw_point: rawAddress
            };

            loadCdekOfficeDetails(selectedPoint.code, selectedPoint.country_code, cdekServicePath)
              .then(function (officeDetails) {
                if (officeDetails && typeof officeDetails === 'object') {
                  if (officeDetails.type) {
                    selectedPoint.type = officeDetails.type;
                  }

                  if (officeDetails.name) {
                    selectedPoint.name = officeDetails.name;
                  }

                  if (officeDetails.address) {
                    selectedPoint.address = officeDetails.address;
                  }

                  if (officeDetails.address_comment) {
                    selectedPoint.point_comment = officeDetails.address_comment;
                  }

                  if (officeDetails.city) {
                    selectedPoint.city = officeDetails.city;
                  }

                  if (officeDetails.postal_code) {
                    selectedPoint.postal_code = officeDetails.postal_code;
                  }

                  if (officeDetails.region) {
                    selectedPoint.region = officeDetails.region;
                  }

                  if (officeDetails.country_code) {
                    selectedPoint.country_code = officeDetails.country_code;
                  }

                  if (Array.isArray(officeDetails.location)) {
                    selectedPoint.location = officeDetails.location.slice();
                  }

                  if (officeDetails.work_time) {
                    selectedPoint.work_time = officeDetails.work_time;
                  }

                  if (officeDetails.raw_point && typeof officeDetails.raw_point === 'object') {
                    selectedPoint.raw_point = officeDetails.raw_point;
                  }
                }

                selectedCdekPoint = selectedPoint;
                updateSelectedCdekPointUi();
                setButtonEnabledState(cdekSaveButton);
              });
          }
        });

        resolve(cdekWidgetInstance);
      } catch (error) {
        cdekWidgetInitPromise = null;
        cdekWidgetInstance = null;
        reject(error);
      }
    });

    return cdekWidgetInitPromise;
  }

  function waitForYandexWidgetLibrary() {
    return new Promise(function (resolve, reject) {
      if (window.YaDelivery && typeof window.YaDelivery.createWidget === 'function') {
        resolve();
        return;
      }

      var timeoutId = window.setTimeout(function () {
        reject(new Error('YaDelivery widget is not loaded'));
      }, 10000);

      function onLoad() {
        window.clearTimeout(timeoutId);
        document.removeEventListener('YaNddWidgetLoad', onLoad);
        resolve();
      }

      document.addEventListener('YaNddWidgetLoad', onLoad);
    });
  }

  function bindYandexWidgetSelectionHandler() {
    if (yandexWidgetHandlerBound) {
      return;
    }

    document.addEventListener('YaNddWidgetPointSelected', function (event) {
            selectedYandexPoint = {
      id: event.detail.id || '',
      name: event.detail.name || '',
      type: event.detail.type || '',
      is_yandex_branded: typeof event.detail.is_yandex_branded === 'boolean'
        ? event.detail.is_yandex_branded
        : null,
      payment_methods: Array.isArray(event.detail.payment_methods) ? event.detail.payment_methods : [],
      position: event.detail.position || {},
      address: event.detail.address || {}
    };

    hideYandexLoadingOverlay();
    updateSelectedYandexPointUi();
    });

    yandexWidgetHandlerBound = true;
  }

  function bindYandexWidgetDomObserver() {
    if (!yandexWidgetContainer || yandexWidgetObserver) {
      return;
    }

    yandexWidgetObserver = new MutationObserver(function (mutations) {
      var hasRealChanges = mutations.some(function (mutation) {
        return mutation.addedNodes.length > 0 || mutation.removedNodes.length > 0;
      });

      if (hasRealChanges) {
        scheduleHideYandexLoadingOverlay();
      }
    });

    yandexWidgetObserver.observe(yandexWidgetContainer, {
      childList: true,
      subtree: true
    });
  }

  function bindYandexWidgetActivityEvents() {
    if (!yandexWidgetContainer) {
      return;
    }

    ['wheel', 'mousedown', 'touchstart'].forEach(function (eventName) {
      yandexWidgetContainer.addEventListener(eventName, function () {
        showYandexLoadingOverlay();
        scheduleHideYandexLoadingOverlay();
      }, true);
    });

    yandexWidgetContainer.addEventListener('input', function () {
      showYandexLoadingOverlay();
      scheduleHideYandexLoadingOverlay();
    }, true);
  }

  function initYandexWidget() {
    if (!yandexWidgetContainer) {
      return Promise.reject(new Error('Yandex widget container not found'));
    }

    if (yandexWidgetInstance) {
      return Promise.resolve(yandexWidgetInstance);
    }

    if (yandexWidgetInitPromise) {
      return yandexWidgetInitPromise;
    }

    yandexWidgetInitPromise = waitForYandexWidgetLibrary()
      .then(function () {
        if (!yandexWidgetContainer.dataset.configUrl) {
          throw new Error('Yandex widget config URL not found');
        }

        return fetch(yandexWidgetContainer.dataset.configUrl, {
          method: 'GET',
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
      })
      .then(function (response) {
        return response.json();
      })
      .then(function (json) {
        if (!json || !json.success || !json.widget) {
          throw new Error('Не удалось получить конфигурацию Яндекс виджета');
        }

        bindYandexWidgetSelectionHandler();
        bindYandexWidgetDomObserver();
        bindYandexWidgetActivityEvents();

        showYandexLoadingOverlay();

        window.YaDelivery.createWidget({
          containerId: 'pickup-point-yandex-widget-container',
          params: json.widget
        });

        yandexWidgetContainer.dataset.widgetLoaded = '1';
        yandexWidgetInstance = true;

        scheduleHideYandexLoadingOverlay();

        return yandexWidgetInstance;
      })
      .catch(function (error) {
        yandexWidgetInitPromise = null;
        yandexWidgetInstance = null;
        throw error;
      });

    return yandexWidgetInitPromise;
  }

  window.onRussianPostPointSelected = function (point) {
    if (!point || !point.id) {
      return;
    }

    selectedRussianPostPoint = point;
    updateSelectedRussianPostPointUi();
  };

  function initRussianPostWidget() {
    if (!russianPostWidgetContainer) {
      return Promise.reject(new Error('Russian Post widget container not found'));
    }

    if (typeof window.ecomStartWidget !== 'function') {
      return Promise.reject(new Error('Russian Post widget is not loaded'));
    }

    russianPostWidgetContainer.innerHTML = '';

    return new Promise(function (resolve, reject) {
      try {
        var widgetId = parseInt(russianPostWidgetContainer.dataset.widgetId || '0', 10);

        if (!widgetId) {
          throw new Error('Russian Post widget ID not found');
        }

        var widgetOptions = {
          id: widgetId,
          containerId: 'pickup-point-russian-post-widget-container',
          callbackFunction: window.onRussianPostPointSelected
        };

        var startZip = (russianPostWidgetContainer.dataset.startZip || '').trim();
        var startLocation = (russianPostWidgetContainer.dataset.startLocation || '').trim();

        if (startZip) {
          widgetOptions.startZip = startZip;
        } else if (startLocation) {
          widgetOptions.start_location = startLocation;
        }

        window.ecomStartWidget(widgetOptions);

        russianPostWidgetContainer.dataset.widgetLoaded = '1';

        resolve(true);
      } catch (error) {
        reject(error);
      }
    });
  }

  function openModal(title, serviceName, labelType, pickerMode, widgetType) {
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('pickup-modal-open');

    if (widgetType !== 'cdek') {
      resetSelectedCdekPoint();
    }

    if (widgetType !== 'yandex') {
      resetSelectedYandexPoint();
    }

    if (widgetType !== 'russian_post') {
      resetSelectedRussianPostPoint();
    }

    if (modalTitle) {
      modalTitle.textContent = title;
    }

    if (pickerMode === 'widget' && widgetType === 'cdek') {
      setModalMode('widget', 'cdek');

      if (selectedCdekPoint && selectedCdekDeliveryMode === 'office') {
      updateSelectedCdekPointUi();
      setButtonEnabledState(cdekSaveButton);
    }

      initCdekWidget().catch(function (error) {
        console.error('[CDEK] Widget init error', error);
      });
    } else if (pickerMode === 'widget' && widgetType === 'yandex') {
      setModalMode('widget', 'yandex');
      showYandexLoadingOverlay();

      initYandexWidget().catch(function (error) {
        console.error('[YANDEX] Widget init error', error);
        hideYandexLoadingOverlay();
        alert(error.message || 'Не удалось загрузить виджет Яндекс Доставки');
      });
    } else if (pickerMode === 'widget' && widgetType === 'russian_post') {
      setModalMode('widget', 'russian_post');

      if (selectedRussianPostPoint && selectedRussianPostPoint.id) {
        updateSelectedRussianPostPointUi();
      }

      initRussianPostWidget().catch(function (error) {
        console.error('[RUSSIAN POST] Widget init error', error);
        alert(error.message || 'Не удалось загрузить виджет Почты России');
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
    hideYandexLoadingOverlay();
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

  if (yandexSaveButton) {
    yandexSaveButton.addEventListener('click', function () {
      saveSelectedYandexPoint();
    });
  }

  if (russianPostSaveButton) {
    russianPostSaveButton.addEventListener('click', function () {
      saveSelectedRussianPostPoint();
    });
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && modal.classList.contains('is-open')) {
      closeModal();
    }
  });
});