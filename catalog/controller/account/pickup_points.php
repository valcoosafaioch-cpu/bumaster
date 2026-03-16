<?php
class ControllerAccountPickupPoints extends Controller {
	public function index(): void {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/pickup_points', '', true);
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('account/pickup_points');
		$this->load->model('account/pickup_point');

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

		$customer_id = (int)$this->customer->getId();
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
				'modal_title' => sprintf($this->language->get('text_modal_title_pickup_point'), $this->language->get('text_service_cdek'))
			),
			array(
				'code' => 'yandex',
				'name' => $this->language->get('text_service_yandex'),
				'label_type' => 'pickup_point',
				'select_text' => $this->language->get('button_select_pickup_point'),
				'change_text' => $this->language->get('button_change_pickup_point'),
				'modal_title' => sprintf($this->language->get('text_modal_title_pickup_point'), $this->language->get('text_service_yandex'))
			),
			array(
				'code' => 'russian_post',
				'name' => $this->language->get('text_service_russian_post'),
				'label_type' => 'department',
				'select_text' => $this->language->get('button_select_department'),
				'change_text' => $this->language->get('button_change_department'),
				'modal_title' => sprintf($this->language->get('text_modal_title_department'), $this->language->get('text_service_russian_post'))
			)
		);

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
				} else {
					if (!empty($saved_point['point_code'])) {
						$meta_line = $this->language->get('text_point_code_prefix') . $saved_point['point_code'];
					}
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
				'modal_title' => $service['modal_title']
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
}