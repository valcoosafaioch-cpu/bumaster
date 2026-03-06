<?php
class ControllerExtensionModuleAccount extends Controller {
	public function index() {
		$this->load->language('extension/module/account');

		$data['logged'] = $this->customer->isLogged();
		$data['register'] = $this->url->link('account/register', '', true);
		$data['login'] = $this->url->link('account/login', '', true);
		$data['logout'] = $this->url->link('account/logout', '', true);
		$data['forgotten'] = $this->url->link('account/forgotten', '', true);
		$data['account'] = $this->url->link('account/account', '', true);
		$data['edit'] = $this->url->link('account/edit', '', true);
		$data['password'] = $this->url->link('account/password', '', true);
		$data['address'] = $this->url->link('account/address', '', true);
		$data['wishlist'] = $this->url->link('account/wishlist');
		$data['order'] = $this->url->link('account/order', '', true);
		$data['download'] = $this->url->link('account/download', '', true);
		$data['reward'] = $this->url->link('account/reward', '', true);
		$data['return'] = $this->url->link('account/return', '', true);
		$data['transaction'] = $this->url->link('account/transaction', '', true);
		$data['newsletter'] = $this->url->link('account/newsletter', '', true);
		$data['recurring'] = $this->url->link('account/recurring', '', true);
		// Ссылка на административный список отзывов/вопросов
		// Показываем только для админской учётки (customer_id = 1)
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