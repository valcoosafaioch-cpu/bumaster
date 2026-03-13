<?php
class ControllerAccountLogin extends Controller {
	private $error = array();

	public function index() {
		$this->load->model('account/customer');

		// Login override for admin users
		if (!empty($this->request->get['token'])) {
			$this->customer->logout();
			$this->cart->clear();

			unset($this->session->data['order_id']);
			unset($this->session->data['payment_address']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['shipping_address']);
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['comment']);
			unset($this->session->data['coupon']);
			unset($this->session->data['reward']);
			unset($this->session->data['voucher']);
			unset($this->session->data['vouchers']);

			$customer_info = $this->model_account_customer->getCustomerByToken($this->request->get['token']);

			if ($customer_info && $this->customer->login($customer_info['email'], '', true)) {
				// Default Addresses
				$this->load->model('account/address');

				if ($this->config->get('config_tax_customer') == 'payment') {
					$this->session->data['payment_address'] = $this->model_account_address->getAddress($this->customer->getAddressId());
				}

				if ($this->config->get('config_tax_customer') == 'shipping') {
					$this->session->data['shipping_address'] = $this->model_account_address->getAddress($this->customer->getAddressId());
				}

				$this->response->redirect($this->url->link('account/account', '', true));
			}
		}

		if ($this->customer->isLogged()) {
			$this->response->redirect($this->url->link('account/account', '', true));
		}

		$this->load->language('account/login');

		$this->document->setTitle($this->language->get('heading_title'));

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if (isset($this->request->post['confirm_unverified_email'])) {
				$this->handleConfirmUnverifiedEmailRequest();
			} elseif ($this->validate()) {
				// Unset guest
				unset($this->session->data['guest']);

				// Default Shipping Address
				$this->load->model('account/address');

				if ($this->config->get('config_tax_customer') == 'payment') {
					$this->session->data['payment_address'] = $this->model_account_address->getAddress($this->customer->getAddressId());
				}

				if ($this->config->get('config_tax_customer') == 'shipping') {
					$this->session->data['shipping_address'] = $this->model_account_address->getAddress($this->customer->getAddressId());
				}

				// Wishlist
				if (isset($this->session->data['wishlist']) && is_array($this->session->data['wishlist'])) {
					$this->load->model('account/wishlist');

					foreach ($this->session->data['wishlist'] as $key => $product_id) {
						$this->model_account_wishlist->addWishlist($product_id);

						unset($this->session->data['wishlist'][$key]);
					}
				}

				// Added strpos check to pass McAfee PCI compliance test
				if (isset($this->request->post['redirect']) && $this->request->post['redirect'] != $this->url->link('account/logout', '', true) && (strpos($this->request->post['redirect'], $this->config->get('config_url')) !== false || strpos($this->request->post['redirect'], $this->config->get('config_ssl')) !== false)) {
					$this->response->redirect(str_replace('&amp;', '&', $this->request->post['redirect']));
				} else {
					$this->response->redirect($this->url->link('account/account', '', true));
				}
			}
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
			'text' => $this->language->get('text_login'),
			'href' => $this->url->link('account/login', '', true)
		);

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];

			unset($this->session->data['error']);
		} elseif (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['action'] = $this->url->link('account/login', '', true);
		$data['register'] = $this->url->link('account/register', '', true);
		$data['forgotten'] = $this->url->link('account/forgotten', '', true);

		$data['show_confirm_email_button'] = !empty($this->error['show_confirm_email_button']);
		$data['confirm_email_action'] = $this->url->link('account/login', '', true);

		if (!empty($this->error['confirm_email_message'])) {
			$data['confirm_email_message'] = $this->error['confirm_email_message'];
		} else {
			$data['confirm_email_message'] = '';
		}

		if (!empty($this->error['confirm_email_email'])) {
			$data['confirm_email_email'] = $this->error['confirm_email_email'];
		} elseif (isset($this->request->post['email'])) {
			$data['confirm_email_email'] = $this->request->post['email'];
		} else {
			$data['confirm_email_email'] = '';
		}

		// Added strpos check to pass McAfee PCI compliance test
        if (isset($this->request->post['redirect']) && (
                (strpos($this->request->post['redirect'], $this->config->get('config_url')) !== false) ||
                (strpos($this->request->post['redirect'], $this->config->get('config_ssl')) !== false)
            )
        ) {
            // redirect уже пришёл из формы (POST)
            $data['redirect'] = $this->request->post['redirect'];

        } elseif (isset($this->request->get['redirect']) && (
                (strpos($this->request->get['redirect'], $this->config->get('config_url')) !== false) ||
                (strpos($this->request->get['redirect'], $this->config->get('config_ssl')) !== false)
            )
        ) {
            // redirect пришёл в GET (например, с карточки товара)
            $data['redirect'] = $this->request->get['redirect'];

        } elseif (isset($this->session->data['redirect'])) {
            // старый сценарий через сессию
            $data['redirect'] = $this->session->data['redirect'];
            unset($this->session->data['redirect']);

        } else {
            $data['redirect'] = '';
        }

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->request->post['email'])) {
			$data['email'] = $this->request->post['email'];
		} else {
			$data['email'] = '';
		}

		if (isset($this->request->post['password'])) {
			$data['password'] = $this->request->post['password'];
		} else {
			$data['password'] = '';
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/login', $data));
	}

	protected function validate() {
		$email = trim($this->request->post['email']);
		$password = isset($this->request->post['password']) ? $this->request->post['password'] : '';

		// Check how many login attempts have been made.
		$login_info = $this->model_account_customer->getLoginAttempts($email);

		if ($login_info && ($login_info['total'] >= $this->config->get('config_login_attempts')) && strtotime('-1 hour') < strtotime($login_info['date_modified'])) {
			$this->error['warning'] = $this->language->get('error_attempts');
		}

		$customer_info = $this->model_account_customer->getCustomerForEmailVerify($email);

		if ($customer_info && !$customer_info['status']) {
			$this->error['warning'] = $this->language->get('error_approved');
		}

		if (!$this->error && $customer_info && $this->isValidCustomerPassword($customer_info, $password) && empty($customer_info['email_confirmed'])) {
			$this->error['warning'] = $this->language->get('error_email_not_confirmed');
			$this->error['confirm_email_message'] = $this->language->get('text_confirm_email_message');
			$this->error['show_confirm_email_button'] = true;
			$this->error['confirm_email_email'] = $email;

			return false;
		}

		if (!$this->error) {
			if (!$this->customer->login($email, $password)) {
				$this->error['warning'] = $this->language->get('error_login');

				$this->model_account_customer->addLoginAttempt($email);
			} else {
				$this->model_account_customer->deleteLoginAttempts($email);
				unset($this->session->data['register_confirm_email']);
			}
		}

		return !$this->error;
	}

	private function handleConfirmUnverifiedEmailRequest() {
		$email = isset($this->request->post['confirm_email_email']) ? trim($this->request->post['confirm_email_email']) : '';

		if ($email === '') {
			$this->error['warning'] = 'Не удалось определить email для подтверждения.';
			return;
		}

		$customer_info = $this->model_account_customer->getCustomerForEmailVerify($email);

		if (!$customer_info || !empty($customer_info['email_confirmed'])) {
			$this->error['warning'] = 'Аккаунт для подтверждения не найден или уже подтверждён.';
			return;
		}

		if (!$customer_info['status']) {
			$this->error['warning'] = $this->language->get('error_approved');
			return;
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

		$this->session->data['register_confirm_email'] = $email;

		$this->response->redirect($this->url->link('account/register_confirm', '', true));
	}

	private function isValidCustomerPassword($customer_info, $password) {
		if (empty($customer_info) || !isset($customer_info['password'])) {
			return false;
		}

		$password = html_entity_decode($password, ENT_QUOTES, 'UTF-8');

		if (password_verify($password, $customer_info['password'])) {
			return true;
		}

		if (!empty($customer_info['salt']) && $customer_info['password'] === sha1($customer_info['salt'] . sha1($customer_info['salt'] . sha1($password)))) {
			return true;
		}

		return false;
	}

	private function generateEmailVerifyCode() {
		return str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
	}

}
