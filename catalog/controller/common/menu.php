<?php
class ControllerCommonMenu extends Controller {
	public function index() {
		$this->load->language('common/menu');

		// Menu
		$this->load->model('catalog/category');

		$this->load->model('catalog/product');

		$data['categories'] = array();

		$categories = $this->model_catalog_category->getCategories(0);

		foreach ($categories as $category) {
			if ($category['top']) {
				// Level 2
				$children_data = array();

				$children = $this->model_catalog_category->getCategories($category['category_id']);

				foreach ($children as $child) {
					$filter_data = array(
						'filter_category_id'  => $child['category_id'],
						'filter_sub_category' => true
					);

					$children_data[] = array(
						'name'  => $child['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_catalog_product->getTotalProducts($filter_data) . ')' : ''),
						'href'  => $this->url->link('product/category', 'path=' . $category['category_id'] . '_' . $child['category_id'])
					);
				}

				// Level 1
				$data['categories'][] = array(
					'name'     => $category['name'],
					'children' => $children_data,
					'column'   => $category['column'] ? $category['column'] : 1,
					'href'     => $this->url->link('product/category', 'path=' . $category['category_id'])
				);
			}
		}
		// Красивый URL для каталога (product/all) с фолбэком
		$lid = (int)$this->config->get('config_language_id');
		$sid = (int)$this->config->get('config_store_id');

		$this->load->model('setting/setting'); // не обязателен, просто пример загрузки модели; главное, что $this->db доступен

		$q = $this->db->query("SELECT keyword FROM " . DB_PREFIX . "seo_url WHERE store_id = " . $sid . " AND language_id = " . $lid . " AND query = 'product/all' LIMIT 1");

		$link = $this->url->link('product/all'); // попробуем стандартный путь
		if ($q->num_rows) {
			// если стандартный путь не переписался, подставим keyword вручную
			if (strpos($link, 'index.php?route=') !== false) {
				$base = $this->request->server['HTTPS'] ? $this->config->get('config_ssl') : $this->config->get('config_url');
				$link = rtrim($base, '/') . '/' . $q->row['keyword'];
			}
		}
		$data['link_catalog'] = $link;

		// Передача текста поиска в шаблон меню
        if (isset($this->request->get['search'])) {
            $data['search'] = $this->request->get['search'];
        } else {
            $data['search'] = '';
        }
		
		return $this->load->view('common/menu', $data);
	}
}
