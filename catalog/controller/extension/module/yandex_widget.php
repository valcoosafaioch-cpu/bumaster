<?php
class ControllerExtensionModuleYandexWidget extends Controller {
	public function service(): void {
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');

		$city = '';
        $lang = trim((string)$this->config->get('yandex_widget_lang'));
        $source_platform_station = trim((string)$this->config->get('yandex_widget_source_platform_station'));
        $physical_dims_weight_gross = (int)$this->config->get('yandex_widget_physical_dims_weight_gross');

        if ($this->customer->isLogged()) {
            $this->load->model('account/pickup_point');

            $saved_points = $this->model_account_pickup_point->getPickupPointsByCustomerId((int)$this->customer->getId());

            if (is_array($saved_points)) {
                foreach ($saved_points as $saved_point) {
                    if (($saved_point['service_code'] ?? '') === 'yandex' && !empty($saved_point['city'])) {
                        $city = trim((string)$saved_point['city']);
                        break;
                    }
                }

                if ($city === '') {
                    foreach ($saved_points as $saved_point) {
                        if (($saved_point['service_code'] ?? '') === 'cdek' && !empty($saved_point['city'])) {
                            $city = trim((string)$saved_point['city']);
                            break;
                        }
                    }
                }
            }
        }

        if ($city === '') {
            $city = trim((string)$this->config->get('yandex_widget_default_city'));
        }

        if ($city === '') {
            $city = 'Москва';
        }

		if ($lang === '') {
			$lang = 'ru_RU';
		}

		$widget = array(
			'city' => $city,
			'size' => array(
				'height' => '450px',
				'width' => '100%'
			),
			'show_select_button' => true,
			'filter' => array(
				'type' => array('pickup_point', 'terminal'),
				'payment_methods' => array('already_paid'),
				'payment_methods_filter' => 'or'
			)
		);

		if ($source_platform_station !== '') {
			$widget['source_platform_station'] = $source_platform_station;
		}

		if ($physical_dims_weight_gross > 0) {
			$widget['physical_dims_weight_gross'] = $physical_dims_weight_gross;
		}

		$this->response->setOutput(json_encode(array(
			'success' => true,
			'widget' => $widget,
			'lang' => $lang
		), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}
}