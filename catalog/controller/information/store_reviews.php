<?php
class ControllerInformationStoreReviews extends Controller {
	public function index() {
		$this->load->language('information/store_reviews');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/bm_feedback');
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		// CSS только для страницы отзывов
		$this->document->addStyle('catalog/view/theme/materialize/stylesheet/store-reviews.css');

		$page  = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
		$limit = 20;

		if ($page < 1) {
			$page = 1;
		}

		$start = ($page - 1) * $limit;

		// Словарь источников: source_code => [title, icon]
		$source_map = [
			'ozon'  => ['title' => 'Ozon',         'icon' => '/image/catalog/review_sources/ozon.jpg'],
			'wb'    => ['title' => 'Wildberries',  'icon' => '/image/catalog/review_sources/wb.jpg'],
			'avito' => ['title' => 'Avito',        'icon' => '/image/catalog/review_sources/avito.jpg'],
			'ym'    => ['title' => 'Яндекс Маркет','icon' => '/image/catalog/review_sources/ym.jpg'],
		];

		$total = $this->model_catalog_bm_feedback->getTotalStoreReviews();
        $rows  = $this->model_catalog_bm_feedback->getStoreReviews($start, $limit);

		$reviews = [];

		foreach ($rows as $row) {
			$display_name = '';

			if (!empty($row['customer_id'])) {
				$display_name = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
			}

			if ($display_name === '') {
				$display_name = trim((string)($row['author_name'] ?? ''));
			}

			if ($display_name === '') {
				$display_name = $this->language->get('text_anonymous');
			}

			$date_added = '';
			if (!empty($row['date_added'])) {
				$ts = strtotime($row['date_added']);
				if ($ts) {
					$date_added = date('d.m.Y', $ts);
				}
			}

			$rating = is_null($row['rating']) ? 0 : (int)$row['rating'];
			if ($rating < 0) $rating = 0;
			if ($rating > 5) $rating = 5;

			// Источник (только для внешних): одна иконка + ссылка
			$source_icon  = '';
			$source_title = '';
			$source_url   = '';

			$source_code = $row['source_code'] ?? null;
			$raw_url     = $row['source_url'] ?? null;

			if (!empty($source_code) && !empty($raw_url) && isset($source_map[$source_code])) {
				$source_icon  = $source_map[$source_code]['icon'];
				$source_title = $source_map[$source_code]['title'];
				$source_url   = $raw_url;
			}

			// Мини-товар (только если sku заполнен и товар найден)
			$product = null;
			$sku = trim((string)($row['sku'] ?? ''));

			if ($sku !== '') {
				$p = $this->model_catalog_product->getProductLiteBySku($sku);
				if ($p && !empty($p['product_id'])) {
					$thumb = '';
					if (!empty($p['image'])) {
						$thumb = $this->model_tool_image->resize($p['image'], 80, 80);
					}

					$product = [
						'product_id' => (int)$p['product_id'],
						'name'       => (string)$p['name'],
						'href'       => $this->url->link('product/product', 'product_id=' . (int)$p['product_id']),
						'thumb'      => $thumb,
					];
				}
			}

			// Фото отзыва
			$images = [];

			if (!empty($row['images']) && is_array($row['images'])) {
				foreach ($row['images'] as $image_row) {
					$image_file = '';

					if (!empty($image_row['image'])) {
						$image_file = (string)$image_row['image'];
					} elseif (!empty($image_row['image_path'])) {
						$image_file = (string)$image_row['image_path'];
					} elseif (!empty($image_row['path'])) {
						$image_file = (string)$image_row['path'];
					} elseif (!empty($image_row['filename'])) {
						$image_file = (string)$image_row['filename'];
					} elseif (!empty($image_row['file'])) {
						$image_file = (string)$image_row['file'];
					}

					$image_file = trim($image_file);

					if ($image_file === '') {
						continue;
					}

					$images[] = [
						'thumb' => $this->model_tool_image->resize($image_file, 120, 120),
						'popup' => 'image/' . ltrim($image_file, '/')
					];
				}
			}

			// Ответ магазина
			$admin_reply = trim((string)($row['admin_reply'] ?? ''));

			$reviews[] = [
                'feedback_id'   => (int)$row['feedback_id'],
                'name'          => $display_name,
                'date_added'    => $date_added,
                'rating'        => $rating,
                'text'          => (string)$row['text'],
                'variant_title' => trim((string)($row['variant_title'] ?? '')),
                'product'       => $product,
                'images'        => $images,
                'admin_reply'   => $admin_reply,
                'source_icon'   => $source_icon,
                'source_title'  => $source_title,
                'source_url'    => $source_url,
            ];
		}

		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('information/store_reviews')
		];

		$data['heading_title'] = $this->language->get('heading_title');
		$data['reviews'] = $reviews;

		// Pagination
		$this->load->library('pagination');

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link('information/store_reviews', 'page={page}');

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf(
			$this->language->get('text_pagination'),
			($total) ? ($start + 1) : 0,
			((($start + $limit) > $total) ? $total : ($start + $limit)),
			$total,
			ceil($total / $limit)
		);

		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('information/store_reviews', $data));
	}
}
