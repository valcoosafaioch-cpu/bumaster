<?php
class ControllerAccountRegister extends Controller {
	private $error = array();

	public function index() {
		if ($this->customer->isLogged()) {
			$this->response->redirect($this->url->link('account/account', '', true));
		}

		$this->load->language('account/register');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment-with-locales.min.js');
		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
		$this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');
		$this->document->addStyle('catalog/view/theme/materialize/stylesheet/account.css');

		$this->load->model('account/customer');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$email = trim($this->request->post['email']);
			$customer_info = $this->model_account_customer->getCustomerForEmailVerify($email);

			if (!$customer_info) {
				$this->model_account_customer->addCustomer($this->request->post);

				// Clear any previous login attempts for unregistered accounts.
				$this->model_account_customer->deleteLoginAttempts($email);

				$customer_info = $this->model_account_customer->getCustomerForEmailVerify($email);
			} elseif (empty($customer_info['email_confirmed'])) {
				$this->model_account_customer->updatePendingCustomerByEmail($email, $this->request->post);
				$customer_info = $this->model_account_customer->getCustomerForEmailVerify($email);
			}

			$verify_code = $this->generateEmailVerifyCode();
			$verify_expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

			$this->model_account_customer->setEmailVerifyCode($email, $verify_code, $verify_expires);

			$mail_customer_info = $this->model_account_customer->getCustomerByEmail($email);

			if (!$mail_customer_info) {
				$mail_customer_info = $customer_info;
			}

			$mail_customer_info['verify_code'] = $verify_code;
			$mail_customer_info['verify_expires'] = $verify_expires;

			$this->load->controller('mail/register/verify', $mail_customer_info);
			$this->load->controller('mail/register/alert', array($mail_customer_info));

			$this->session->data['register_confirm_email'] = $email;

			if (!empty($this->request->post['redirect'])
				&& (
					strpos($this->request->post['redirect'], $this->config->get('config_url')) !== false
					|| strpos($this->request->post['redirect'], $this->config->get('config_ssl')) !== false
				)
			) {
				$this->session->data['register_success_redirect'] = $this->request->post['redirect'];
			} else {
				unset($this->session->data['register_success_redirect']);
			}

			unset($this->session->data['guest']);

			$this->response->redirect($this->url->link('account/register_confirm', '', true));
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
			'text' => $this->language->get('text_register'),
			'href' => $this->url->link('account/register', '', true)
		);
		$data['text_account_already'] = sprintf($this->language->get('text_account_already'), $this->url->link('account/login', '', true));

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

		if (isset($this->error['password'])) {
			$data['error_password'] = $this->error['password'];
		} else {
			$data['error_password'] = '';
		}

		if (isset($this->error['confirm'])) {
			$data['error_confirm'] = $this->error['confirm'];
		} else {
			$data['error_confirm'] = '';
		}

		$data['action'] = $this->url->link('account/register', '', true);

		$data['customer_groups'] = array();

		if (is_array($this->config->get('config_customer_group_display'))) {
			$this->load->model('account/customer_group');

			$customer_groups = $this->model_account_customer_group->getCustomerGroups();

			foreach ($customer_groups as $customer_group) {
				if (in_array($customer_group['customer_group_id'], $this->config->get('config_customer_group_display'))) {
					$data['customer_groups'][] = $customer_group;
				}
			}
		}

		if (isset($this->request->post['customer_group_id'])) {
			$data['customer_group_id'] = $this->request->post['customer_group_id'];
		} else {
			$data['customer_group_id'] = $this->config->get('config_customer_group_id');
		}

		if (isset($this->request->post['firstname'])) {
			$data['firstname'] = $this->request->post['firstname'];
		} else {
			$data['firstname'] = '';
		}

		if (isset($this->request->post['lastname'])) {
			$data['lastname'] = $this->request->post['lastname'];
		} else {
			$data['lastname'] = '';
		}

		if (isset($this->request->post['email'])) {
			$data['email'] = $this->request->post['email'];
		} else {
			$data['email'] = '';
		}

		if (isset($this->request->post['telephone'])) {
			$data['telephone'] = $this->request->post['telephone'];
		} else {
			$data['telephone'] = '';
		}

		if (isset($this->request->post['telephone_country'])) {
			$data['telephone_country'] = $this->request->post['telephone_country'];
		} else {
			$data['telephone_country'] = 'RU';
		}

		if (isset($this->request->post['telephone_number'])) {
			$data['telephone_number'] = preg_replace('/\D/', '', $this->request->post['telephone_number']);
		} else {
			$data['telephone_number'] = '';
		}

		// Custom Fields
		$data['custom_fields'] = array();
		
		$this->load->model('account/custom_field');
		
		$custom_fields = $this->model_account_custom_field->getCustomFields();
		
		foreach ($custom_fields as $custom_field) {
			if ($custom_field['location'] == 'account') {
				$data['custom_fields'][] = $custom_field;
			}
		}
		
		if (isset($this->request->post['custom_field']['account'])) {
			$data['register_custom_field'] = $this->request->post['custom_field']['account'];
		} else {
			$data['register_custom_field'] = array();
		}

		if (isset($this->request->post['password'])) {
			$data['password'] = $this->request->post['password'];
		} else {
			$data['password'] = '';
		}

		if (isset($this->request->post['confirm'])) {
			$data['confirm'] = $this->request->post['confirm'];
		} else {
			$data['confirm'] = '';
		}

		if (isset($this->request->post['newsletter'])) {
			$data['newsletter'] = $this->request->post['newsletter'];
		} else {
			$data['newsletter'] = 1;
		}

		// Captcha
		if ($this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('register', (array)$this->config->get('config_captcha_page'))) {
			$data['captcha'] = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha'), $this->error);
		} else {
			$data['captcha'] = '';
		}

		if ($this->config->get('config_account_id')) {
			$this->load->model('catalog/information');

			$information_info = $this->model_catalog_information->getInformation($this->config->get('config_account_id'));

			if ($information_info) {
				$data['text_agree'] = sprintf($this->language->get('text_agree'), $this->url->link('information/information/agree', 'information_id=' . $this->config->get('config_account_id'), true), $information_info['title']);
			} else {
				$data['text_agree'] = '';
			}
		} else {
			$data['text_agree'] = '';
		}

		if (isset($this->request->post['agree'])) {
			$data['agree'] = $this->request->post['agree'];
		} else {
			$data['agree'] = false;
		}

		// Redirect для возврата на предыдущую страницу (например, на товар)
		if (isset($this->request->post['redirect']) && (
				(strpos($this->request->post['redirect'], $this->config->get('config_url')) !== false) ||
				(strpos($this->request->post['redirect'], $this->config->get('config_ssl')) !== false)
			)
		) {
			$data['redirect'] = $this->request->post['redirect'];

		} elseif (isset($this->request->get['redirect']) && (
				(strpos($this->request->get['redirect'], $this->config->get('config_url')) !== false) ||
				(strpos($this->request->get['redirect'], $this->config->get('config_ssl')) !== false)
			)
		) {
			$data['redirect'] = $this->request->get['redirect'];

		} elseif (isset($this->session->data['redirect'])) {
			$data['redirect'] = $this->session->data['redirect'];
			unset($this->session->data['redirect']);

		} else {
			$data['redirect'] = '';
		}


		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/register', $data));
	}

	private function validate() {
		if ((utf8_strlen(trim($this->request->post['firstname'])) < 1) || (utf8_strlen(trim($this->request->post['firstname'])) > 32)) {
			$this->error['firstname'] = $this->language->get('error_firstname');
		}

		if ((utf8_strlen(trim($this->request->post['lastname'])) < 1) || (utf8_strlen(trim($this->request->post['lastname'])) > 32)) {
			$this->error['lastname'] = $this->language->get('error_lastname');
		}

		if ((utf8_strlen($this->request->post['email']) > 96) || !filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
			$this->error['email'] = $this->language->get('error_email');
		}

		$customer_info = $this->model_account_customer->getCustomerForEmailVerify($this->request->post['email']);

		if ($customer_info && !empty($customer_info['email_confirmed'])) {
			$this->error['email'] = sprintf(
				$this->language->get('error_email_exists_confirmed'),
				$this->url->link('account/login', '', true),
				$this->url->link('account/forgotten', '', true)
			);
		}

		$telephone_country = isset($this->request->post['telephone_country']) ? $this->request->post['telephone_country'] : 'RU';
		$telephone_number = isset($this->request->post['telephone_number']) ? preg_replace('/\D/', '', $this->request->post['telephone_number']) : '';

		if ($telephone_country === 'RU') {
			if (!preg_match('/^\d{10}$/', $telephone_number)) {
				$this->error['telephone'] = $this->language->get('error_telephone_russia');
			}
		} else {
			if ($telephone_number === '') {
				$this->error['telephone'] = $this->language->get('error_telephone_other');
			}
		}

		if (!$this->error) {
			$telephone_code = '+7';

			if ($telephone_country === 'BY') {
				$telephone_code = '+375';
			}

			$this->request->post['telephone_number'] = $telephone_number;
			$this->request->post['telephone'] = $telephone_code . $telephone_number;
		}

		// Customer Group
		if (isset($this->request->post['customer_group_id']) && is_array($this->config->get('config_customer_group_display')) && in_array($this->request->post['customer_group_id'], $this->config->get('config_customer_group_display'))) {
			$customer_group_id = $this->request->post['customer_group_id'];
		} else {
			$customer_group_id = $this->config->get('config_customer_group_id');
		}

		// Custom field validation
		$this->load->model('account/custom_field');

		$custom_fields = $this->model_account_custom_field->getCustomFields($customer_group_id);

		foreach ($custom_fields as $custom_field) {
			if ($custom_field['location'] == 'account') {
				if ($custom_field['required'] && empty($this->request->post['custom_field'][$custom_field['location']][$custom_field['custom_field_id']])) {
					$this->error['custom_field'][$custom_field['custom_field_id']] = sprintf($this->language->get('error_custom_field'), $custom_field['name']);
				} elseif (($custom_field['type'] == 'text') && !empty($custom_field['validation']) && !filter_var($this->request->post['custom_field'][$custom_field['location']][$custom_field['custom_field_id']], FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => $custom_field['validation'])))) {
					$this->error['custom_field'][$custom_field['custom_field_id']] = sprintf($this->language->get('error_custom_field'), $custom_field['name']);
				}
			}
		}

		if ((utf8_strlen(html_entity_decode($this->request->post['password'], ENT_QUOTES, 'UTF-8')) < 4) || (utf8_strlen(html_entity_decode($this->request->post['password'], ENT_QUOTES, 'UTF-8')) > 40)) {
			$this->error['password'] = $this->language->get('error_password');
		}

		if ($this->request->post['confirm'] != $this->request->post['password']) {
			$this->error['confirm'] = $this->language->get('error_confirm');
		}

		// Captcha
		if ($this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('register', (array)$this->config->get('config_captcha_page'))) {
			$captcha = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha') . '/validate');

			if ($captcha) {
				$this->error['captcha'] = $captcha;
			}
		}

		// Agree to terms
		if ($this->config->get('config_account_id')) {
			$this->load->model('catalog/information');

			$information_info = $this->model_catalog_information->getInformation($this->config->get('config_account_id'));

			if ($information_info && !isset($this->request->post['agree'])) {
				$this->error['warning'] = sprintf($this->language->get('error_agree'), $information_info['title']);
			}
		}
		
		return !$this->error;
	}

	private function generateEmailVerifyCode() {
		return str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
	}

	public function customfield() {
		$json = array();

		$this->load->model('account/custom_field');

		// Customer Group
		if (isset($this->request->get['customer_group_id']) && is_array($this->config->get('config_customer_group_display')) && in_array($this->request->get['customer_group_id'], $this->config->get('config_customer_group_display'))) {
			$customer_group_id = $this->request->get['customer_group_id'];
		} else {
			$customer_group_id = $this->config->get('config_customer_group_id');
		}

		$custom_fields = $this->model_account_custom_field->getCustomFields($customer_group_id);

		foreach ($custom_fields as $custom_field) {
			$json[] = array(
				'custom_field_id' => $custom_field['custom_field_id'],
				'required'        => $custom_field['required']
			);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}