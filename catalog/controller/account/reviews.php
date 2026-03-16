<?php
class ControllerAccountReviews extends Controller {
	public function index() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/reviews', '', true);
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('account/account');

		$this->document->setTitle('Мои отзывы и вопросы');

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => 'Личный кабинет',
			'href' => $this->url->link('account/account', '', true)
		);

		$data['breadcrumbs'][] = array(<div class="account-menu-divider"></div>

<a class="collection-item waves-effect blue-grey-text text-darken-4 account-logout"
   href="{{ logout }}"
   rel="nofollow">
   {{ text_logout }}
</a>
			'text' => 'Мои отзывы и вопросы',
			'href' => $this->url->link('account/reviews', '', true)
		);

		$data['heading_title'] = 'Мои отзывы и вопросы';
		$data['text_empty'] = 'Раздел находится в разработке. Здесь будут отображаться отзывы и вопросы пользователя.';

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/reviews', $data));
	}
}