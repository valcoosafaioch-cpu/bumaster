<?php
class ControllerAccountRegisterConfirm extends Controller {
	private $error = array();

	public function index() {
		if ($this->customer->isLogged()) {
			$this->response->redirect($this->url->link('account/account', '', true));
		}

		$this->load->language('account/register_confirm');
		$this->load->model('account/customer');

		if (empty($this->session->data['register_confirm_email'])) {
			$this->session->data['error_warning'] = $this->language->get('error_session_email');
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$email = $this->session->data['register_confirm_email'];
		$customer_info = $this->model_account_customer->getCustomerForEmailVerify($email);

		if (!$customer_info) {
			unset($this->session->data['register_confirm_email']);
			$this->session->data['error_warning'] = $this->language->get('error_customer_not_found');
			$this->response->redirect($this->url->link('account/register', '', true));
		}

		if (!empty($customer_info['email_confirmed'])) {
			unset($this->session->data['register_confirm_email']);
			$this->session->data['success'] = $this->language->get('text_already_confirmed');
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if (isset($this->request->post['resend_code'])) {
				$this->handleResend($customer_info);
			} elseif (isset($this->request->post['verify_code'])) {
				$this->handleConfirm($customer_info);
			}
		}

		$customer_info = $this->model_account_customer->getCustomerForEmailVerify($email);
		$resend_info = $this->model_account_customer->getEmailVerifyResendInfo($email);

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
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('account/register_confirm', '', true)
		);

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_description'] = $this->language->get('text_description');
		$data['text_email_masked'] = sprintf($this->language->get('text_email_masked'), $this->maskEmail($email));
		$data['text_code_lifetime'] = $this->language->get('text_code_lifetime');
		$data['text_resend_hint'] = $this->language->get('text_resend_hint');
		$data['text_wait_seconds'] = sprintf($this->language->get('text_wait_seconds'), (int)$resend_info['wait_seconds']);

		$data['entry_code'] = $this->language->get('entry_code');
		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['button_resend'] = $this->language->get('button_resend');
		$data['button_login'] = $this->language->get('button_login');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} elseif (!empty($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['code'])) {
			$data['error_code'] = $this->error['code'];
		} else {
			$data['error_code'] = '';
		}

		if (!empty($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['verify_code'] = '';
		$data['action'] = $this->url->link('account/register_confirm', '', true);
		$data['action_resend'] = $this->url->link('account/register_confirm', '', true);
		$data['login'] = $this->url->link('account/login', '', true);
		$data['can_resend'] = !empty($resend_info['can_resend']);
		$data['wait_seconds'] = (int)$resend_info['wait_seconds'];

		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->addStyle('catalog/view/theme/materialize/stylesheet/account.css');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/register_confirm', $data));
	}

	private function handleConfirm($customer_info) {
		$this->load->model('account/customer');

		$code = isset($this->request->post['verify_code']) ? trim($this->request->post['verify_code']) : '';

		if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
			$this->error['code'] = $this->language->get('error_code_format');
			return;
		}

		if ((int)$customer_info['email_verify_attempts'] >= 5) {
			$this->model_account_customer->resetEmailVerify($customer_info['email']);
			$this->error['code'] = $this->language->get('error_attempts_exceeded');
			return;
		}

		if (empty($customer_info['email_verify_code']) || empty($customer_info['email_verify_expires'])) {
			$this->error['code'] = $this->language->get('error_code_missing');
			return;
		}

		if (strtotime($customer_info['email_verify_expires']) < time()) {
			$this->model_account_customer->resetEmailVerify($customer_info['email']);
			$this->error['code'] = $this->language->get('error_code_expired');
			return;
		}

		if ($code !== $customer_info['email_verify_code']) {
			$this->model_account_customer->incrementEmailVerifyAttempts($customer_info['email']);

			$updated_info = $this->model_account_customer->getCustomerForEmailVerify($customer_info['email']);

			if (!empty($updated_info) && (int)$updated_info['email_verify_attempts'] >= 5) {
				$this->model_account_customer->resetEmailVerify($customer_info['email']);
				$this->error['code'] = $this->language->get('error_attempts_exceeded');
			} else {
				$this->error['code'] = $this->language->get('error_code_invalid');
			}

			return;
		}

		$this->model_account_customer->confirmEmail($customer_info['email']);
		$this->model_account_customer->deleteLoginAttempts($customer_info['email']);

		$mail_customer_info = $this->model_account_customer->getCustomerByEmail($customer_info['email']);

		if ($mail_customer_info) {
			$this->load->controller('mail/register_success/success', $mail_customer_info);
			$this->load->controller('mail/register_success/alertDirect', $mail_customer_info);
		}

		$this->customer->login($customer_info['email'], '', true);

		unset($this->session->data['register_confirm_email']);

		$this->response->redirect($this->url->link('account/success', '', true));
	}

	private function handleResend($customer_info) {
		$this->load->model('account/customer');

		$resend_info = $this->model_account_customer->getEmailVerifyResendInfo($customer_info['email']);

		if (empty($resend_info['can_resend'])) {
			$this->error['code'] = sprintf($this->language->get('error_resend_cooldown'), (int)$resend_info['wait_seconds']);
			return;
		}

		$verify_code = $this->generateEmailVerifyCode();
		$verify_expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

		$this->model_account_customer->setEmailVerifyCode($customer_info['email'], $verify_code, $verify_expires);

		$mail_customer_info = $this->model_account_customer->getCustomerByEmail($customer_info['email']);

		if (!$mail_customer_info) {
			$mail_customer_info = $customer_info;
		}

		$mail_customer_info['verify_code'] = $verify_code;
		$mail_customer_info['verify_expires'] = $verify_expires;

		$this->load->controller('mail/register/verify', $mail_customer_info);

		$this->session->data['success'] = $this->language->get('success_code_resent');
	}

	private function generateEmailVerifyCode() {
		return str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
	}


	private function maskEmail($email) {
		$parts = explode('@', $email);

		if (count($parts) !== 2) {
			return $email;
		}

		$name = $parts[0];
		$domain = $parts[1];

		if (utf8_strlen($name) <= 2) {
			$masked_name = utf8_substr($name, 0, 1) . '***';
		} else {
			$masked_name = utf8_substr($name, 0, 2) . '***';
		}

		return $masked_name . '@' . $domain;
	}
}