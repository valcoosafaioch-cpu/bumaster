<?php
class ControllerMailRegister extends Controller {
	public function index(&$route, &$args, &$output) {
		if (!empty($args[0]) && is_array($args[0])) {
			$this->verify($args[0]);
		}
	}

	public function verify($customer_info = array()) {
		$this->load->language('mail/register');

		$data = array();
		$data['mail_type'] = 'customer_verify';
		$data['store'] = html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8');
		$data['firstname'] = isset($customer_info['firstname']) ? $customer_info['firstname'] : '';
		$data['lastname'] = isset($customer_info['lastname']) ? $customer_info['lastname'] : '';
		$data['email'] = isset($customer_info['email']) ? $customer_info['email'] : '';
		$data['telephone'] = isset($customer_info['telephone']) ? $customer_info['telephone'] : '';
		$data['newsletter'] = !empty($customer_info['newsletter']) ? $this->language->get('text_yes') : $this->language->get('text_no');
		$data['verify_code'] = isset($customer_info['verify_code']) ? $customer_info['verify_code'] : '';
		$data['verify_expires'] = isset($customer_info['verify_expires']) ? $customer_info['verify_expires'] : '';
		$data['date_added'] = date('d.m.Y H:i:s');
		$data['status_text'] = $this->language->get('text_status_pending');

		$data['text_verify_greeting'] = sprintf($this->language->get('text_verify_greeting'), $data['firstname']);
		$data['text_verify_intro'] = sprintf($this->language->get('text_verify_intro'), $data['store']);
		$data['text_verify_code'] = $this->language->get('text_verify_code');
		$data['text_verify_expire'] = $this->language->get('text_verify_expire');
		$data['text_verify_ignore'] = $this->language->get('text_verify_ignore');
		$data['text_signature'] = $this->language->get('text_signature');

		$data['text_admin_intro'] = $this->language->get('text_admin_intro');
		$data['text_admin_name'] = sprintf($this->language->get('text_admin_name'), $data['lastname'], $data['firstname']);
		$data['text_admin_email'] = sprintf($this->language->get('text_admin_email'), $data['email']);
		$data['text_admin_phone'] = sprintf($this->language->get('text_admin_phone'), $data['telephone']);
		$data['text_admin_newsletter'] = sprintf($this->language->get('text_admin_newsletter'), $data['newsletter']);
		$data['text_admin_date'] = sprintf($this->language->get('text_admin_date'), $data['date_added']);
		$data['text_admin_status'] = sprintf($this->language->get('text_admin_status'), $data['status_text']);

		$mail = new Mail($this->config->get('config_mail_engine'));
		$mail->parameter = $this->config->get('config_mail_parameter');
		$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
		$mail->smtp_username = $this->config->get('config_mail_smtp_username');
		$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
		$mail->smtp_port = $this->config->get('config_mail_smtp_port');
		$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

		$mail->setTo($data['email']);
		$mail->setFrom($this->config->get('config_email'));
		$mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
		$mail->setSubject(sprintf($this->language->get('subject_verify'), html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8')));
		$mail->setHtml($this->load->view('mail/register', $data));
		$mail->send();
	}

	public function alert(&$route, &$args, &$output) {
		if (in_array('account', (array)$this->config->get('config_mail_alert'))) {
			$this->load->language('mail/register');

			$data = array();
			$data['mail_type'] = 'admin_new_customer';
			$data['store'] = html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8');
			$data['firstname'] = isset($args[0]['firstname']) ? $args[0]['firstname'] : '';
			$data['lastname'] = isset($args[0]['lastname']) ? $args[0]['lastname'] : '';
			$data['email'] = isset($args[0]['email']) ? $args[0]['email'] : '';
			$data['telephone'] = isset($args[0]['telephone']) ? $args[0]['telephone'] : '';
			$data['newsletter'] = !empty($args[0]['newsletter']) ? $this->language->get('text_yes') : $this->language->get('text_no');
			$data['verify_code'] = isset($args[0]['verify_code']) ? $args[0]['verify_code'] : '';
			$data['verify_expires'] = isset($args[0]['verify_expires']) ? $args[0]['verify_expires'] : '';
			$data['date_added'] = date('d.m.Y H:i:s');
			$data['status_text'] = $this->language->get('text_status_pending');

			$data['text_verify_greeting'] = sprintf($this->language->get('text_verify_greeting'), $data['firstname']);
			$data['text_verify_intro'] = sprintf($this->language->get('text_verify_intro'), $data['store']);
			$data['text_verify_code'] = $this->language->get('text_verify_code');
			$data['text_verify_expire'] = $this->language->get('text_verify_expire');
			$data['text_verify_ignore'] = $this->language->get('text_verify_ignore');
			$data['text_signature'] = $this->language->get('text_signature');

			$data['text_admin_intro'] = $this->language->get('text_admin_intro');
			$data['text_admin_name'] = sprintf($this->language->get('text_admin_name'), $data['lastname'], $data['firstname']);
			$data['text_admin_email'] = sprintf($this->language->get('text_admin_email'), $data['email']);
			$data['text_admin_phone'] = sprintf($this->language->get('text_admin_phone'), $data['telephone']);
			$data['text_admin_newsletter'] = sprintf($this->language->get('text_admin_newsletter'), $data['newsletter']);
			$data['text_admin_date'] = sprintf($this->language->get('text_admin_date'), $data['date_added']);
			$data['text_admin_status'] = sprintf($this->language->get('text_admin_status'), $data['status_text']);

			$fullname = trim($data['lastname'] . ' ' . $data['firstname']);

			if ($fullname === '') {
				$fullname = $data['email'];
			}

			$subject = sprintf($this->language->get('subject_admin_new'), $fullname);

			$mail = new Mail($this->config->get('config_mail_engine'));
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($this->config->get('config_email'));
			$mail->setFrom($this->config->get('config_email'));
			$mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
			$mail->setSubject($subject);
			$mail->setText($this->load->view('mail/register', $data));
			$mail->send();

			$emails = explode(',', $this->config->get('config_mail_alert_email'));

			foreach ($emails as $email) {
				if ($email && filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
					$mail->setTo(trim($email));
					$mail->send();
				}
			}
		}
	}
}