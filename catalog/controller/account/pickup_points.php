<?php
class ControllerAccountPickupPoints extends Controller {
	public function index(): void {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/pickup_points', '', true);
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('account/pickup_points');
		$this->load->model('account/pickup_point');
		$this->load->model('account/customer');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_empty'] = $this->language->get('text_empty');
		$data['text_not_selected'] = $this->language->get('text_not_selected');
		$data['text_current_pickup_point'] = $this->language->get('text_current_pickup_point');
		$data['text_department'] = $this->language->get('text_department');

		$data['text_modal_search_label'] = $this->language->get('text_modal_search_label');
		$data['text_modal_map_title'] = $this->language->get('text_modal_map_title');
		$data['text_modal_list_title'] = $this->language->get('text_modal_list_title');
		$data['text_modal_loading'] = $this->language->get('text_modal_loading');
		$data['text_modal_empty'] = $this->language->get('text_modal_empty');
		$data['text_modal_map_stub'] = $this->language->get('text_modal_map_stub');
		$data['text_modal_list_stub'] = $this->language->get('text_modal_list_stub');
		$data['text_modal_close'] = $this->language->get('text_modal_close');

		$data['text_selected_pickup_point'] = $this->language->get('text_selected_pickup_point');
		$data['text_point_code_label'] = $this->language->get('text_point_code_label');
		$data['text_point_type_label'] = $this->language->get('text_point_type_label');
		$data['text_point_type_pickup_point'] = $this->language->get('text_point_type_pickup_point');
		$data['text_point_type_terminal'] = $this->language->get('text_point_type_terminal');
		$data['button_save_pickup_point'] = $this->language->get('button_save_pickup_point');
		$data['text_pickup_point_saving'] = $this->language->get('text_pickup_point_saving');
		$data['text_map_updating'] = $this->language->get('text_map_updating');

		$data['text_point_kind_label'] = $this->language->get('text_point_kind_label');
		$data['text_point_kind_pickup_point'] = $this->language->get('text_point_kind_pickup_point');
		$data['text_point_kind_terminal'] = $this->language->get('text_point_kind_terminal');

		$data['text_point_brand_label'] = $this->language->get('text_point_brand_label');
		$data['text_point_brand_yandex'] = $this->language->get('text_point_brand_yandex');
		$data['text_point_brand_partner'] = $this->language->get('text_point_brand_partner');

		$cdek_widget_enabled = (bool)$this->config->get('cdek_widget_enabled');
		$cdek_widget_version = (string)$this->config->get('cdek_widget_version');
		$cdek_widget_account = (string)$this->config->get('cdek_widget_account');
		$cdek_widget_default_city = (string)$this->config->get('cdek_widget_default_city');
		$cdek_widget_lang = (string)$this->config->get('cdek_widget_lang');

		$yandex_widget_enabled_raw = $this->config->get('yandex_widget_enabled');
		$yandex_widget_enabled = $yandex_widget_enabled_raw === null ? true : (bool)$yandex_widget_enabled_raw;
		$yandex_widget_default_city = (string)$this->config->get('yandex_widget_default_city');
		$yandex_widget_lang = (string)$this->config->get('yandex_widget_lang');

		if ($yandex_widget_lang === '') {
			$yandex_widget_lang = 'ru_RU';
		}

		$data['cdek_widget_enabled'] = $cdek_widget_enabled;
		

		$data['cdek_widget'] = array(
			'version' => $cdek_widget_version,
			'account' => $cdek_widget_account,
			'default_city' => $cdek_widget_default_city,
			'lang' => $cdek_widget_lang
		);

		$data['yandex_widget'] = array(
			'default_city' => $yandex_widget_default_city,
			'lang' => $yandex_widget_lang
		);

		$data['cdek_save_url'] = $this->url->link('account/pickup_points/saveCdekPoint', '', true);
		$data['yandex_save_url'] = $this->url->link('account/pickup_points/saveYandexPoint', '', true);
		$data['yandex_widget_service_url'] = $this->url->link('extension/module/yandex_widget/service', '', true);

		$customer_id = (int)$this->customer->getId();
		$customer_info = $this->model_account_customer->getCustomer($customer_id);
		$customer_country_id = !empty($customer_info['country_id']) ? (int)$customer_info['country_id'] : 1;
		$is_russia_customer = ($customer_country_id === 1);

		$data['cdek_widget_enabled'] = $cdek_widget_enabled;
		
		$saved_points = $this->model_account_pickup_point->getPickupPointsByCustomerId($customer_id);
		$saved_points_by_service = array();

		foreach ($saved_points as $saved_point) {
			$saved_points_by_service[$saved_point['service_code']] = $saved_point;
		}

		$service_definitions = array(
			array(
				'code' => 'cdek',
				'name' => $this->language->get('text_service_cdek'),
				'label_type' => 'pickup_point',
				'select_text' => $this->language->get('button_select_pickup_point'),
				'change_text' => $this->language->get('button_change_pickup_point'),
				'modal_title' => sprintf(
					$this->language->get('text_modal_title_pickup_point'),
					$this->language->get('text_service_cdek')
				),
				'picker_mode' => $cdek_widget_enabled ? 'widget' : 'stub',
				'widget_type' => $cdek_widget_enabled ? 'cdek' : ''
			)
		);

		if ($is_russia_customer) {
			$service_definitions[] = array(
				'code' => 'yandex',
				'name' => $this->language->get('text_service_yandex'),
				'label_type' => 'pickup_point',
				'select_text' => $this->language->get('button_select_pickup_point'),
				'change_text' => $this->language->get('button_change_pickup_point'),
				'modal_title' => sprintf(
					$this->language->get('text_modal_title_pickup_point'),
					$this->language->get('text_service_yandex')
				),
				'picker_mode' => $yandex_widget_enabled ? 'widget' : 'stub',
				'widget_type' => $yandex_widget_enabled ? 'yandex' : ''
			);

			$service_definitions[] = array(
				'code' => 'russian_post',
				'name' => $this->language->get('text_service_russian_post'),
				'label_type' => 'department',
				'select_text' => $this->language->get('button_select_department'),
				'change_text' => $this->language->get('button_change_department'),
				'modal_title' => sprintf(
					$this->language->get('text_modal_title_department'),
					$this->language->get('text_service_russian_post')
				),
				'picker_mode' => 'stub',
				'widget_type' => ''
			);
		}

		$data['services'] = array();

		foreach ($service_definitions as $service) {
			$saved_point = $saved_points_by_service[$service['code']] ?? array();
			$is_selected = !empty($saved_point);

			$address_line = '';
			$meta_line = '';

			if ($is_selected) {
				$address_line = (string)$saved_point['display_line'];

				if ($address_line === '') {
					$address_parts = array();

					if (!empty($saved_point['city'])) {
						$address_parts[] = $saved_point['city'];
					}

					if (!empty($saved_point['address'])) {
						$address_parts[] = $saved_point['address'];
					}

					$address_line = implode(', ', $address_parts);

					if ($address_line === '' && !empty($saved_point['address'])) {
						$address_line = $saved_point['address'];
					}
				}

				if ($service['label_type'] === 'department') {
					if (!empty($saved_point['postal_code'])) {
						$meta_line = $this->language->get('text_postal_code_prefix') . $saved_point['postal_code'];
					} elseif (!empty($saved_point['point_code'])) {
						$meta_line = $this->language->get('text_postal_code_prefix') . $saved_point['point_code'];
					}
				} elseif ($service['code'] === 'yandex') {
					$meta_parts = array();
					$point_partner = (string)($saved_point['point_partner'] ?? '');
					$point_type = (string)($saved_point['point_type'] ?? '');
					$point_comment = trim((string)($saved_point['point_comment'] ?? ''));

					if ($point_type === 'POSTAMAT') {
						$meta_parts[] = 'Постамат';
					} elseif ($point_partner === 'YANDEX_MARKET') {
						$meta_parts[] = 'ПВЗ Яндекс.Маркет';
					} elseif ($point_partner === '5POST') {
						$meta_parts[] = 'ПВЗ 5Post';
					} else {
						$meta_parts[] = 'ПВЗ';
					}

					if ($point_comment !== '') {
						$meta_parts[] = 'Как добраться: ' . $point_comment;
					}

					$meta_line = implode(' • ', $meta_parts);
				} else {
					$meta_parts = array();

					if (!empty($saved_point['point_code'])) {
						$meta_parts[] = $this->language->get('text_point_code_prefix') . $saved_point['point_code'];
					}

					if (!empty($saved_point['point_type'])) {
						$meta_parts[] = $this->language->get('text_point_type_label') . ': ' . $this->formatPointType((string)$saved_point['point_type']);
					}

					$meta_line = implode(' • ', $meta_parts);
				}
			}

			$data['services'][] = array(
				'code' => $service['code'],
				'name' => $service['name'],
				'label_type' => $service['label_type'],
				'label_text' => $service['label_type'] === 'department'
					? $this->language->get('text_department')
					: $this->language->get('text_current_pickup_point'),
				'is_selected' => $is_selected,
				'status_text' => $is_selected ? '' : $this->language->get('text_not_selected'),
				'address_line' => $address_line,
				'meta_line' => $meta_line,
				'button_text' => $is_selected ? $service['change_text'] : $service['select_text'],
				'modal_title' => $service['modal_title'],
				'picker_mode' => $service['picker_mode'],
				'widget_type' => $service['widget_type']
			);
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/pickup_points', $data));
	}

	public function saveCdekPoint(): void {
		$this->load->language('account/pickup_points');
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');

		if (!$this->customer->isLogged()) {
			$this->sendJson(array(
				'success' => false,
				'error' => $this->language->get('error_auth_required')
			));

			return;
		}

		if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST') {
			$this->sendJson(array(
				'success' => false,
				'error' => $this->language->get('error_invalid_method')
			));

			return;
		}

		$service_code = (string)($this->request->post['service_code'] ?? '');
		$delivery_mode = (string)($this->request->post['delivery_mode'] ?? '');
		$point_code = trim((string)($this->request->post['point_code'] ?? ''));
		$point_type = trim((string)($this->request->post['point_type'] ?? ''));
		$point_name = trim((string)($this->request->post['point_name'] ?? ''));
		$point_address = trim((string)($this->request->post['point_address'] ?? ''));
		$city = trim((string)($this->request->post['city'] ?? ''));
		$postal_code = trim((string)($this->request->post['postal_code'] ?? ''));
		$region = trim((string)($this->request->post['region'] ?? ''));
		$country = trim((string)($this->request->post['country'] ?? ''));
		$raw_payload = (string)($this->request->post['raw_payload'] ?? '');
		$tariff_json = (string)($this->request->post['tariff_json'] ?? '');

		if ($service_code !== 'cdek') {
			$this->sendJson(array(
				'success' => false,
				'error' => 'Поддерживается только СДЭК'
			));

			return;
		}

		if ($delivery_mode !== 'office') {
			$this->sendJson(array(
				'success' => false,
				'error' => 'Можно сохранить только пункт выдачи'
			));

			return;
		}

		$tariff = json_decode($tariff_json, true);

		if (is_string($tariff)) {
			$tariff = json_decode($tariff, true);
		}

		if ($point_code === '' || $point_address === '') {
			$this->sendJson(array(
				'success' => false,
				'error' => $this->language->get('error_pickup_point_data')
			));

			return;
		}

		$display_line = $this->buildDisplayLine($city, $point_address);

		$raw_payload_array = json_decode($raw_payload, true);

		if (!is_array($raw_payload_array)) {
			$raw_payload_array = array();
		}

		$raw_payload = json_encode(
			array(
				'source' => 'cdek_widget',
				'delivery_mode' => $delivery_mode,
				'tariff' => is_array($tariff) ? $tariff : array(),
				'address' => $raw_payload_array
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		$this->saveCustomerPickupPoint(
			(int)$this->customer->getId(),
			array(
				'service_code' => 'cdek',
				'service_name' => $this->language->get('text_service_cdek'),
				'point_code' => $point_code,
				'point_type' => $point_type,
				'point_name' => $point_name,
				'address' => $point_address,
				'city' => $city,
				'postal_code' => $postal_code,
				'region' => $region,
				'country' => $country,
				'display_line' => $display_line,
				'raw_payload' => $raw_payload
			)
		);

		$this->sendJson(array(
			'success' => true,
			'message' => $this->language->get('text_pickup_point_saved')
		));
	}

	public function saveYandexPoint(): void {
		$this->load->language('account/pickup_points');
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');

		if (!$this->customer->isLogged()) {
			$this->sendJson(array(
				'success' => false,
				'error' => $this->language->get('error_auth_required')
			));

			return;
		}

		if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST') {
			$this->sendJson(array(
				'success' => false,
				'error' => $this->language->get('error_invalid_method')
			));

			return;
		}

		$service_code = (string)($this->request->post['service_code'] ?? '');
		$point_code = trim((string)($this->request->post['point_code'] ?? ''));
		$point_type = trim((string)($this->request->post['point_type'] ?? ''));
		$point_name = trim((string)($this->request->post['point_name'] ?? ''));
		$point_address = trim((string)($this->request->post['point_address'] ?? ''));
		$point_comment = trim((string)($this->request->post['point_comment'] ?? ''));
		$city = trim((string)($this->request->post['city'] ?? ''));
		$postal_code = trim((string)($this->request->post['postal_code'] ?? ''));
		$region = trim((string)($this->request->post['region'] ?? ''));
		$country = trim((string)($this->request->post['country'] ?? ''));
		$raw_payload = (string)($this->request->post['raw_payload'] ?? '');

		if ($service_code !== 'yandex') {
			$this->sendJson(array(
				'success' => false,
				'error' => 'Поддерживается только Яндекс Доставка'
			));

			return;
		}

		if ($point_code === '' || $point_address === '') {
			$this->sendJson(array(
				'success' => false,
				'error' => $this->language->get('error_pickup_point_data')
			));

			return;
		}

		$allowed_types = array('pickup_point', 'terminal');

		if ($point_type !== '' && !in_array($point_type, $allowed_types, true)) {
			$point_type = '';
		}

		$comment_lc = function_exists('mb_strtolower')
			? mb_strtolower($point_comment, 'UTF-8')
			: strtolower($point_comment);

		$point_partner = '';

		if ($comment_lc !== '') {
			if (strpos($comment_lc, '5post') !== false || strpos($comment_lc, 'пятёрочк') !== false) {
				$point_partner = '5POST';
			} else {
				$point_partner = 'YANDEX_MARKET';
			}
		}

		if ($point_type === '') {
			if ($comment_lc !== '' && strpos($comment_lc, 'постамат') !== false) {
				$point_type = 'POSTAMAT';
			} else {
				$point_type = 'PVZ';
			}
		}

		$display_line = $point_address !== ''
			? $point_address
			: $this->buildDisplayLine($city, $point_name);

		$raw_payload_array = json_decode($raw_payload, true);

		if (!is_array($raw_payload_array)) {
			$raw_payload_array = array();
		}

		$raw_payload = json_encode(
			array(
				'source' => 'yandex_widget',
				'point' => $raw_payload_array
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		$this->saveCustomerPickupPoint(
			(int)$this->customer->getId(),
			array(
				'service_code' => 'yandex',
				'service_name' => $this->language->get('text_service_yandex'),
				'point_code' => $point_code,
				'point_type' => $point_type,
				'point_partner' => $point_partner,
				'point_name' => $point_name,
				'address' => $point_address,
				'point_comment' => $point_comment,
				'city' => $city,
				'postal_code' => $postal_code,
				'region' => $region,
				'country' => $country,
				'display_line' => $display_line,
				'raw_payload' => $raw_payload
			)
		);

		$this->sendJson(array(
			'success' => true,
			'message' => $this->language->get('text_pickup_point_saved')
		));
	}

	private function saveCustomerPickupPoint(int $customer_id, array $data): void {
		$customer_id = (int)$customer_id;
		$service_code = $this->db->escape((string)$data['service_code']);
		$service_name = $this->db->escape((string)$data['service_name']);
		$point_code = $this->db->escape((string)$data['point_code']);
		$point_type = $this->db->escape((string)($data['point_type'] ?? ''));
		$point_partner = $this->db->escape((string)($data['point_partner'] ?? ''));
		$point_name = $this->db->escape((string)$data['point_name']);
		$address = $this->db->escape((string)$data['address']);
		$point_comment = $this->db->escape((string)($data['point_comment'] ?? ''));
		$city = $this->db->escape((string)$data['city']);
		$postal_code = $this->db->escape((string)$data['postal_code']);
		$region = $this->db->escape((string)$data['region']);
		$country = $this->db->escape((string)$data['country']);
		$display_line = $this->db->escape((string)$data['display_line']);
		$raw_payload = $this->db->escape((string)$data['raw_payload']);

		$query = $this->db->query(
			"SELECT customer_pickup_point_id
			FROM `" . DB_PREFIX . "customer_pickup_point`
			WHERE customer_id = '" . $customer_id . "'
			  AND service_code = '" . $service_code . "'
			LIMIT 1"
		);

		if (!empty($query->row['customer_pickup_point_id'])) {
			$this->db->query(
				"UPDATE `" . DB_PREFIX . "customer_pickup_point`
				SET service_name = '" . $service_name . "',
					point_code = '" . $point_code . "',
					point_type = '" . $point_type . "',
					point_partner = '" . $point_partner . "',
					point_name = '" . $point_name . "',
					address = '" . $address . "',
					point_comment = '" . $point_comment . "',
					city = '" . $city . "',
					postal_code = '" . $postal_code . "',
					region = '" . $region . "',
					country = '" . $country . "',
					display_line = '" . $display_line . "',
					raw_payload = '" . $raw_payload . "',
					date_modified = NOW()
				WHERE customer_pickup_point_id = '" . (int)$query->row['customer_pickup_point_id'] . "'"
			);
		} else {
			$this->db->query(
				"INSERT INTO `" . DB_PREFIX . "customer_pickup_point`
				SET customer_id = '" . $customer_id . "',
					service_code = '" . $service_code . "',
					service_name = '" . $service_name . "',
					point_code = '" . $point_code . "',
					point_type = '" . $point_type . "',
					point_partner = '" . $point_partner . "',
					point_name = '" . $point_name . "',
					address = '" . $address . "',
					point_comment = '" . $point_comment . "',
					city = '" . $city . "',
					postal_code = '" . $postal_code . "',
					region = '" . $region . "',
					country = '" . $country . "',
					display_line = '" . $display_line . "',
					raw_payload = '" . $raw_payload . "',
					date_added = NOW(),
					date_modified = NOW()"
			);
		}
	}

	private function buildDisplayLine(string $city, string $address): string {
		$parts = array();

		if ($city !== '') {
			$parts[] = $city;
		}

		if ($address !== '') {
			$parts[] = $address;
		}

		return implode(', ', $parts);
	}

	private function formatPointType(string $point_type): string {
		$this->load->language('account/pickup_points');

		if ($point_type === 'terminal') {
			return $this->language->get('text_point_type_terminal');
		}

		if ($point_type === 'pickup_point') {
			return $this->language->get('text_point_type_pickup_point');
		}

		return $point_type;
	}

	private function sendJson(array $json): void {
		$this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}
}