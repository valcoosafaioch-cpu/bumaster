<?php
class ControllerAccountEdit extends Controller {
	private $error = array();

	public function index() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/edit', '', true);

			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('account/edit');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment-with-locales.min.js');
		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
		$this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');

		$this->load->model('account/customer');

		$data['telephone_countries'] = $this->getAvailableTelephoneCountries();
		$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->request->post['email'] = $customer_info['email'];

			$this->model_account_customer->editCustomer($this->customer->getId(), $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('account/account', '', true));
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_edit'),
			'href' => $this->url->link('account/edit', '', true)
		);

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['firstname'])) {
			$data['error_firstname'] = $this->error['firstname'];
		} else {
			$data['error_firstname'] = '';
		}

		if (isset($this->error['lastname'])) {
			$data['error_lastname'] = $this->error['lastname'];
		} else {
			$data['error_lastname'] = '';
		}

		if (isset($this->error['email'])) {
			$data['error_email'] = $this->error['email'];
		} else {
			$data['error_email'] = '';
		}

		if (isset($this->error['telephone'])) {
			$data['error_telephone'] = $this->error['telephone'];
		} else {
			$data['error_telephone'] = '';
		}

		if (isset($this->error['custom_field'])) {
			$data['error_custom_field'] = $this->error['custom_field'];
		} else {
			$data['error_custom_field'] = array();
		}

		$data['action'] = $this->url->link('account/edit', '', true);

		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
		}

		if (isset($this->request->post['firstname'])) {
			$data['firstname'] = $this->request->post['firstname'];
		} else {
			$data['firstname'] = $customer_info['firstname'];
		}

		if (isset($this->request->post['lastname'])) {
			$data['lastname'] = $this->request->post['lastname'];
		} else {
			$data['lastname'] = $customer_info['lastname'];
		}

		$data['email'] = $customer_info['email'];

		if (isset($this->request->post['country_id'])) {
			$data['country_id'] = (int)$this->request->post['country_id'];
		} elseif (!empty($customer_info['country_id'])) {
			$data['country_id'] = (int)$customer_info['country_id'];
		} else {
			$data['country_id'] = 1;
		}

		$selected_country = $this->getTelephoneCountryById($data['country_id']);

		if (!$selected_country) {
			$data['country_id'] = 1;
			$selected_country = $this->getTelephoneCountryById($data['country_id']);
		}

		if (isset($this->request->post['telephone_number'])) {
			$data['telephone_number'] = preg_replace('/\D/', '', $this->request->post['telephone_number']);
		} else {
			$telephone_parts = $this->splitTelephoneByCountry($customer_info['telephone'], $selected_country);
			$data['telephone_number'] = $telephone_parts['telephone_number'];
		}

		if (isset($this->request->post['telephone'])) {
			$data['telephone'] = $this->request->post['telephone'];
		} else {
			$data['telephone'] = $customer_info['telephone'];
		}

		if (isset($this->request->post['custom_field']['account'])) {
			$data['account_custom_field'] = $this->request->post['custom_field']['account'];
		} elseif (isset($customer_info)) {
			$data['account_custom_field'] = json_decode($customer_info['custom_field'], true);
		} else {
			$data['account_custom_field'] = array();
		}

		// Custom Fields
		$data['custom_fields'] = array();

		$this->load->model('tool/upload');
		$this->load->model('account/custom_field');

		$custom_fields = $this->model_account_custom_field->getCustomFields($this->config->get('config_customer_group_id'));

		foreach ($custom_fields as $custom_field) {
			if ($custom_field['location'] == 'account') {
				if($custom_field['type'] == 'file' && isset($data['account_custom_field'][$custom_field['custom_field_id']])) {
					$code = $data['account_custom_field'][$custom_field['custom_field_id']];

					$data['account_custom_field'][$custom_field['custom_field_id']] = array();

					$upload_result = $this->model_tool_upload->getUploadByCode($code);
					
					if($upload_result) {
						$data['account_custom_field'][$custom_field['custom_field_id']]['name'] = $upload_result['name'];
						$data['account_custom_field'][$custom_field['custom_field_id']]['code'] = $upload_result['code'];
					} else {
						$data['account_custom_field'][$custom_field['custom_field_id']]['name'] = "";
						$data['account_custom_field'][$custom_field['custom_field_id']]['code'] = $code;
					}
					$data['custom_fields'][] = $custom_field;
				} else {
					$data['custom_fields'][] = $custom_field;
				}
			}
		}

		$data['back'] = $this->url->link('account/account', '', true);

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/edit', $data));
	}

	private function getAvailableTelephoneCountries(): array {
		$query = $this->db->query("
			SELECT country_id, name, iso_code_2, phone_code, phone_digits
			FROM `" . DB_PREFIX . "country`
			WHERE status = '1'
			  AND country_id IN (1, 2, 3)
			ORDER BY FIELD(country_id, 1, 2, 3)
		");

		return $query->rows;
	}

	private function getTelephoneCountryById(int $country_id): array {
		$countries = $this->getAvailableTelephoneCountries();

		foreach ($countries as $country) {
			if ((int)$country['country_id'] === $country_id) {
				return $country;
			}
		}

		return array();
	}

	private function splitTelephoneByCountry(string $telephone, array $country): array {
		$phone_code = isset($country['phone_code']) ? trim($country['phone_code']) : '';
		$telephone_digits = preg_replace('/\D/', '', $telephone);

		if ($phone_code) {
			$phone_code_digits = preg_replace('/\D/', '', $phone_code);

			if ($phone_code_digits && strpos($telephone_digits, $phone_code_digits) === 0) {
				$telephone_digits = substr($telephone_digits, strlen($phone_code_digits));
			}
		}

		return array(
			'phone_code' => $phone_code,
			'telephone_number' => $telephone_digits
		);
	}

	protected function validate() {
		if ((utf8_strlen(trim($this->request->post['firstname'])) < 1) || (utf8_strlen(trim($this->request->post['firstname'])) > 32)) {
			$this->error['firstname'] = $this->language->get('error_firstname');
		}

		if ((utf8_strlen(trim($this->request->post['lastname'])) < 1) || (utf8_strlen(trim($this->request->post['lastname'])) > 32)) {
			$this->error['lastname'] = $this->language->get('error_lastname');
		}

		$country_id = isset($this->request->post['country_id']) ? (int)$this->request->post['country_id'] : 0;
		$telephone_number = isset($this->request->post['telephone_number']) ? preg_replace('/\D/', '', $this->request->post['telephone_number']) : '';

		$telephone_country = $this->getTelephoneCountryById($country_id);

		if (!$telephone_country) {
			$this->error['telephone'] = $this->language->get('error_country');
		} else {
			$phone_digits = isset($telephone_country['phone_digits']) ? (int)$telephone_country['phone_digits'] : 0;
			$phone_code = isset($telephone_country['phone_code']) ? trim($telephone_country['phone_code']) : '';

			if ($phone_digits > 0) {
				if (utf8_strlen($telephone_number) != $phone_digits) {
					$this->error['telephone'] = sprintf($this->language->get('error_telephone_length'), $phone_digits);
				}
			} elseif ($telephone_number === '') {
				$this->error['telephone'] = $this->language->get('error_telephone');
			}

			if (!$phone_code) {
				$this->error['telephone'] = $this->language->get('error_telephone_code');
			}
		}

		if (!$this->error && $telephone_country) {
			$this->request->post['telephone'] = $telephone_country['phone_code'] . $telephone_number;
			$this->request->post['country_id'] = $country_id;
		}

		return !$this->error;
	}
}