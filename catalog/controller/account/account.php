<?php
class ControllerAccountAccount extends Controller {
	public function index() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/account', '', true);

			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('account/account');
		$this->load->model('account/customer');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true)
		);

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

		$data['heading_title'] = $this->language->get('heading_title');

		$data['edit'] = $this->url->link('account/edit', '', true);
		$data['pickup_points'] = $this->url->link('account/pickup_points', '', true);
		$data['newsletter_action'] = $this->url->link('account/newsletter', '', true);

		$data['text_profile_title'] = $this->language->get('text_profile_title');
		$data['text_stats_title'] = $this->language->get('text_stats_title');

		$data['text_lastname'] = $this->language->get('text_lastname');
		$data['text_firstname'] = $this->language->get('text_firstname');
		$data['text_telephone'] = $this->language->get('text_telephone');
		$data['text_email_label'] = $this->language->get('text_email_label');
		$data['text_newsletter_label'] = $this->language->get('text_newsletter_label');

		$data['text_reward_total'] = $this->language->get('text_reward_total');
		$data['text_cashback_level'] = $this->language->get('text_cashback_level');
		$data['text_active_orders'] = $this->language->get('text_active_orders');
		$data['text_total_orders'] = $this->language->get('text_total_orders');
		$data['text_total_completed_sum'] = $this->language->get('text_total_completed_sum');

		$data['text_pickup_points_title'] = $this->language->get('text_pickup_points_title');
		$data['text_pickup_points_stub'] = $this->language->get('text_pickup_points_stub');
		$data['text_go_to_pickup_points'] = $this->language->get('text_go_to_pickup_points');

		$data['text_yes_short'] = $this->language->get('text_yes_short');
		$data['text_no_short'] = $this->language->get('text_no_short');
		$data['text_empty_value'] = $this->language->get('text_empty_value');
		$data['text_currency_rub_short'] = $this->language->get('text_currency_rub_short');
		$data['text_edit_profile'] = $this->language->get('text_edit_profile');

		$data['firstname'] = !empty($customer_info['firstname']) ? $customer_info['firstname'] : $data['text_empty_value'];
		$data['lastname'] = !empty($customer_info['lastname']) ? $customer_info['lastname'] : $data['text_empty_value'];
		$data['telephone'] = !empty($customer_info['telephone']) ? $customer_info['telephone'] : $data['text_empty_value'];
		$data['email'] = !empty($customer_info['email']) ? $customer_info['email'] : $data['text_empty_value'];

		$data['newsletter'] = !empty($customer_info['newsletter']) ? 1 : 0;
		$data['newsletter_text'] = $data['newsletter'] ? $data['text_yes_short'] : $data['text_no_short'];

		$customer_id = (int)$this->customer->getId();

		$active_status_ids = array(1, 2, 3, 4, 5, 6, 7);
		$total_status_ids = array(1, 2, 3, 4, 5, 6, 7, 8);
		$completed_status_ids = array(8);

		$data['reward_total'] = $this->getRewardTotal($customer_id);
		$data['active_orders_total'] = $this->getOrdersCountByStatuses($customer_id, $active_status_ids);
		$data['orders_total'] = $this->getOrdersCountByStatuses($customer_id, $total_status_ids);
		$data['orders_completed_sum'] = $this->getOrdersSumByStatuses($customer_id, $completed_status_ids);

		$data['cashback_level'] = $data['text_empty_value'];

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/account', $data));
	}

	protected function getRewardTotal($customer_id) {
		$query = $this->db->query("
			SELECT COALESCE(SUM(points), 0) AS total
			FROM `" . DB_PREFIX . "customer_reward`
			WHERE customer_id = '" . (int)$customer_id . "'
		");

		return (int)$query->row['total'];
	}

	protected function getOrdersCountByStatuses($customer_id, array $status_ids) {
		if (!$status_ids) {
			return 0;
		}

		$statuses = implode(',', array_map('intval', $status_ids));

		$query = $this->db->query("
			SELECT COUNT(*) AS total
			FROM `" . DB_PREFIX . "order`
			WHERE customer_id = '" . (int)$customer_id . "'
			  AND order_status_id IN (" . $statuses . ")
		");

		return (int)$query->row['total'];
	}

	protected function getOrdersSumByStatuses($customer_id, array $status_ids) {
		if (!$status_ids) {
			return '0';
		}

		$statuses = implode(',', array_map('intval', $status_ids));

		$query = $this->db->query("
			SELECT COALESCE(SUM(total), 0) AS total
			FROM `" . DB_PREFIX . "order`
			WHERE customer_id = '" . (int)$customer_id . "'
			  AND order_status_id IN (" . $statuses . ")
		");

		return number_format((float)$query->row['total'], 0, '.', ' ');
	}

	public function country() {
		$json = array();

		$this->load->model('localisation/country');

		$country_info = $this->model_localisation_country->getCountry($this->request->get['country_id']);

		if ($country_info) {
			$this->load->model('localisation/zone');

			$json = array(
				'country_id'        => $country_info['country_id'],
				'name'              => $country_info['name'],
				'iso_code_2'        => $country_info['iso_code_2'],
				'iso_code_3'        => $country_info['iso_code_3'],
				'address_format'    => $country_info['address_format'],
				'postcode_required' => $country_info['postcode_required'],
				'zone'              => $this->model_localisation_zone->getZonesByCountryId($this->request->get['country_id']),
				'status'            => $country_info['status']
			);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}