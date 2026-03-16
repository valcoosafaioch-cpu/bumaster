<?php
class ControllerExtensionModuleAccount extends Controller {
	public function index() {
		$this->load->language('extension/module/account');

		$route = '';

		if (isset($this->request->get['route'])) {
			$route = (string)$this->request->get['route'];
		}

		$data['logged'] = $this->customer->isLogged();

		$data['register'] = $this->url->link('account/register', '', true);
		$data['login'] = $this->url->link('account/login', '', true);
		$data['logout'] = $this->url->link('account/logout', '', true);
		$data['forgotten'] = $this->url->link('account/forgotten', '', true);

		$data['account'] = $this->url->link('account/account', '', true);
		$data['password'] = $this->url->link('account/password', '', true);
		$data['order'] = $this->url->link('account/order', '', true);

		$data['pickup_points'] = $this->url->link('account/pickup_points', '', true);
		$data['reviews'] = $this->url->link('account/reviews', '', true);
		$data['certificates'] = $this->url->link('account/certificates', '', true);

		$data['active_menu'] = '';

		if ($route === 'account/account') {
			$data['active_menu'] = 'account';
		} elseif ($route === 'account/password') {
			$data['active_menu'] = 'password';
		} elseif ($route === 'account/order') {
			$data['active_menu'] = 'order';
		} elseif ($route === 'account/pickup_points') {
			$data['active_menu'] = 'pickup_points';
		} elseif ($route === 'account/reviews') {
			$data['active_menu'] = 'reviews';
		} elseif ($route === 'account/certificates') {
			$data['active_menu'] = 'certificates';
		} elseif ($route === 'account/login') {
			$data['active_menu'] = 'login';
		} elseif ($route === 'account/register') {
			$data['active_menu'] = 'register';
		} elseif ($route === 'account/forgotten') {
			$data['active_menu'] = 'forgotten';
		} elseif ($route === 'account/feedback_admin') {
			$data['active_menu'] = 'feedback_admin';
		}

		// Временная ссылка на административный список отзывов/вопросов.
		// Показываем только для админской учётки (customer_id = 1).
		if ($this->customer->isLogged() && (int)$this->customer->getId() === 1) {
			$data['feedback_admin'] = $this->url->link('account/feedback_admin', '', true);
			$data['text_feedback_admin'] = $this->language->get('text_feedback_admin');
		} else {
			$data['feedback_admin'] = '';
			$data['text_feedback_admin'] = '';
		}

		return $this->load->view('extension/module/account', $data);
	}
}