<?php
class ControllerCommonHeader extends Controller {
	public function index() {
		// Текущий маршрут
		$route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : '';

		// Глобальные стили темы «Бумажный Мастер»
		$this->document->addStyle('catalog/view/theme/materialize/stylesheet/custom.css?v=2025-11-06');

		// Стили только для главной
		if ($route === '' || $route === 'common/home') {
			$this->document->addStyle('catalog/view/theme/materialize/stylesheet/home.css?v=2025-12-08');
		}

		// Стили только для блока аккаунта
		if (strpos($route, 'account/') === 0) {
			$this->document->addStyle('catalog/view/theme/materialize/stylesheet/account.css');
		}

		// Глобальные скрипты
		$this->document->addScript('catalog/view/theme/materialize/js/custom.js');

		// Скрипты главной
		if ($route === '' || $route === 'common/home') {
			$this->document->addScript('catalog/view/theme/materialize/js/home.js');
		}

		// Скрипты блока аккаунта
		if (strpos($route, 'account/') === 0) {
			$this->document->addScript('catalog/view/theme/materialize/js/account.js');
		}

		// Скрипты страницы пунктов выдачи
		if ($route === 'account/pickup_points') {
			$this->document->addScript('catalog/view/theme/materialize/js/pickup-points.js');
		}

		// Скрипты каталога
		if ($route === 'product/all') {
			$this->document->addScript('catalog/view/theme/materialize/js/katalog.js');
		}

		// Скрипты карточки товара
		if ($route === 'product/product') {
			$this->document->addScript('catalog/view/theme/materialize/js/product-page.js');
		}

		// Скрипты оформления заказа
		if ($route === 'checkout/checkout') {
			$this->document->addScript('catalog/view/theme/materialize/js/checkout.js');
		}

		// Скрипты корзины
		// Пока не подключаем cart.js.
		// Если для checkout/cart появится отдельная логика, добавим так:
		// if ($route === 'checkout/cart') {
		// 	$this->document->addScript('catalog/view/theme/materialize/js/cart.js');
		// }

		// Analytics
		$this->load->model('setting/extension');
		// Настройки модуля «Бумажный Мастер — Главная» (баннер A1)
		$this->load->model('setting/setting');

		$data['analytics'] = array();

		$analytics = $this->model_setting_extension->getExtensions('analytics');

		foreach ($analytics as $analytic) {
			if ($this->config->get('analytics_' . $analytic['code'] . '_status')) {
				$data['analytics'][] = $this->load->controller('extension/analytics/' . $analytic['code'], $this->config->get('analytics_' . $analytic['code'] . '_status'));
			}
		}

		if ($this->request->server['HTTPS']) {
			$server = $this->config->get('config_ssl');
		} else {
			$server = $this->config->get('config_url');
		}

		if (is_file(DIR_IMAGE . $this->config->get('config_icon'))) {
			$this->document->addLink($server . 'image/' . $this->config->get('config_icon'), 'icon');
		}

		$data['title'] = $this->document->getTitle();

		$data['base'] = $server;
		$data['description'] = $this->document->getDescription();
		$data['keywords'] = $this->document->getKeywords();
		$data['links'] = $this->document->getLinks();
		$data['styles'] = $this->document->getStyles();
		$data['scripts'] = $this->document->getScripts('header');
		$data['lang'] = $this->language->get('code');
		$data['direction'] = $this->language->get('direction');

		$data['name'] = $this->config->get('config_name');

		$data['route'] = $route;
		
		if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
			$data['logo'] = $server . 'image/' . $this->config->get('config_logo');
		} else {
			$data['logo'] = '';
		}

		$this->load->language('common/header');

		// Текст поиска для строки в шапке
        if (isset($this->request->get['search'])) {
            $data['header_search'] = $this->request->get['search'];
        } else {
            $data['header_search'] = '';
        }
		
		// Wishlist
		if ($this->customer->isLogged()) {
			$this->load->model('account/wishlist');

			$data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), $this->model_account_wishlist->getTotalWishlist());
		} else {
			$data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), (isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0));
		}

		$data['text_logged'] = sprintf($this->language->get('text_logged'), $this->url->link('account/account', '', true), $this->customer->getFirstName(), $this->url->link('account/logout', '', true));
		
		$data['home'] = $this->url->link('common/home');
		// ссылки для мобильного меню
		$data['link_home']    = $this->url->link('common/home');
		$data['link_catalog'] = $this->url->link('product/all');          // даст /katalog при включённом SEO
		$data['link_account'] = $this->url->link('account/account', '', true);
		$data['link_cart']    = $this->url->link('checkout/cart');
		// ссылки для мобильного меню
		
		$data['wishlist'] = $this->url->link('account/wishlist', '', true);
		$data['logged'] = $this->customer->isLogged();
		$data['account'] = $this->url->link('account/account', '', true);
		$data['register'] = $this->url->link('account/register', '', true);
		$data['login'] = $this->url->link('account/login', '', true);
		$data['order'] = $this->url->link('account/order', '', true);
		$data['transaction'] = $this->url->link('account/transaction', '', true);
		$data['download'] = $this->url->link('account/download', '', true);
		$data['logout'] = $this->url->link('account/logout', '', true);
		$data['shopping_cart'] = $this->url->link('checkout/cart');
		$data['checkout'] = $this->url->link('checkout/checkout', '', true);
		$data['contact'] = $this->url->link('information/contact');
		$data['telephone'] = $this->config->get('config_telephone');
		
		// Баннер A1 из настроек bm_home
        $bm_home = $this->model_setting_setting->getSetting('bm_home');

        $data['bm_home_banner_image']  = '';
        $data['bm_home_banner_link']   = '';
        $data['bm_home_banner_text']   = '';
        $data['bm_home_banner_status'] = 0;

        if (!empty($bm_home['bm_home_banner_image']) && is_file(DIR_IMAGE . $bm_home['bm_home_banner_image'])) {
            // Отдаём именно относительный путь, в Twig будем использовать как image/{{ bm_home_banner_image }}
            $data['bm_home_banner_image'] = $bm_home['bm_home_banner_image'];
        }

        if (!empty($bm_home['bm_home_banner_link'])) {
            $data['bm_home_banner_link'] = $bm_home['bm_home_banner_link'];
        }

        if (!empty($bm_home['bm_home_banner_text'])) {
			$data['bm_home_banner_text'] = html_entity_decode($bm_home['bm_home_banner_text'], ENT_QUOTES, 'UTF-8');
		}

        if (isset($bm_home['bm_home_banner_status'])) {
            $data['bm_home_banner_status'] = (int)$bm_home['bm_home_banner_status'];
        } else {
            // По умолчанию считаем баннер включённым
            $data['bm_home_banner_status'] = 1;
        }

		$data['language'] = $this->load->controller('common/language');
		$data['currency'] = $this->load->controller('common/currency');
		$data['search'] = $this->load->controller('common/search');
		$data['cart'] = $this->load->controller('common/cart');
		$data['menu'] = $this->load->controller('common/menu');

		return $this->load->view('common/header', $data);
	}
}
