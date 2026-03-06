<?php
// BM_DEBUG_2025_11_26
class ModelCatalogProduct extends Model {
	public function updateViewed($product_id) {
		$this->db->query("UPDATE " . DB_PREFIX . "product SET viewed = (viewed + 1) WHERE product_id = '" . (int)$product_id . "'");
	}

	public function getProduct($product_id) {
		$query = $this->db->query("SELECT DISTINCT *, pd.name AS name, p.image, m.name AS manufacturer, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special, (SELECT points FROM " . DB_PREFIX . "product_reward pr WHERE pr.product_id = p.product_id AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "') AS reward, (SELECT ss.name FROM " . DB_PREFIX . "stock_status ss WHERE ss.stock_status_id = p.stock_status_id AND ss.language_id = '" . (int)$this->config->get('config_language_id') . "') AS stock_status, (SELECT wcd.unit FROM " . DB_PREFIX . "weight_class_description wcd WHERE p.weight_class_id = wcd.weight_class_id AND wcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS weight_class, (SELECT lcd.unit FROM " . DB_PREFIX . "length_class_description lcd WHERE p.length_class_id = lcd.length_class_id AND lcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS length_class, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review r2 WHERE r2.product_id = p.product_id AND r2.status = '1' GROUP BY r2.product_id) AS reviews, p.sort_order FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id) WHERE p.product_id = '" . (int)$product_id . "' AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'");

		if ($query->num_rows) {
			return array(
				'product_id'       => $query->row['product_id'],
				'name'             => $query->row['name'],
				'description'      => $query->row['description'],
				'meta_title'       => $query->row['meta_title'],
				'meta_description' => $query->row['meta_description'],
				'meta_keyword'     => $query->row['meta_keyword'],
				'tag'              => $query->row['tag'],
				'model'            => $query->row['model'],
				'sku'              => $query->row['sku'],
				'upc'              => $query->row['upc'],
				'ean'              => $query->row['ean'],
				'jan'              => $query->row['jan'],
				'isbn'             => $query->row['isbn'],
				'mpn'              => $query->row['mpn'],
				'location'         => $query->row['location'],
				'quantity'         => $query->row['quantity'],
				'stock_status'     => $query->row['stock_status'],
				'image'            => $query->row['image'],
				'manufacturer_id'  => $query->row['manufacturer_id'],
				'manufacturer'     => $query->row['manufacturer'],
				'price'            => ($query->row['discount'] ? $query->row['discount'] : $query->row['price']),
				'special'          => $query->row['special'],
				'reward'           => $query->row['reward'],
				'points'           => $query->row['points'],
				'tax_class_id'     => $query->row['tax_class_id'],
				'date_available'   => $query->row['date_available'],
				'weight'           => $query->row['weight'],
				'weight_class_id'  => $query->row['weight_class_id'],
				'length'           => $query->row['length'],
				'width'            => $query->row['width'],
				'height'           => $query->row['height'],
				'length_class_id'  => $query->row['length_class_id'],
				'subtract'         => $query->row['subtract'],
				'rating'           => round(($query->row['rating']===null) ? 0 : $query->row['rating']),
				'reviews'          => $query->row['reviews'] ? $query->row['reviews'] : 0,
				'minimum'          => $query->row['minimum'],
				'sort_order'       => $query->row['sort_order'],
				'status'           => $query->row['status'],
				'date_added'       => $query->row['date_added'],
				'date_modified'    => $query->row['date_modified'],
				'viewed'           => $query->row['viewed']
			);
		} else {
			return false;
		}
	}

		/**
	 * Лёгкая выборка товара по SKU.
	 *
	 * Используется на странице /all_reviews (отзывы о магазине), чтобы
	 * показать мини-карточку товара (ссылка + миниатюра) если sku у отзыва заполнен.
	 *
	 * Важно: не привязываемся к status, чтобы не терять связь в случае
	 * ручной работы с данными (см. правила проекта по статусу).
	 */
	public function getProductLiteBySku($sku) {
		$sku = $this->db->escape((string)$sku);

		if ($sku === '') {
			return false;
		}

		$query = $this->db->query(
			"SELECT p.product_id, p.image, pd.name AS name
			FROM " . DB_PREFIX . "product p
			LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
			LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
			WHERE p.sku = '" . $sku . "'
				AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
				AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
			ORDER BY p.product_id DESC
			LIMIT 1"
			);

		if ($query->num_rows) {
			return array(
				'product_id' => (int)$query->row['product_id'],
				'name'       => $query->row['name'],
				'image'      => $query->row['image']
			);
		}

		return false;
	}


	public function getProducts($data = array()) {
		$sql = "SELECT p.product_id, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special";

		if (!empty($data['filter_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)";
			} else {
				$sql .= " FROM " . DB_PREFIX . "product_to_category p2c";
			}

			if (!empty($data['filter_filter'])) {
				$sql .= " LEFT JOIN " . DB_PREFIX . "product_filter pf ON (p2c.product_id = pf.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (pf.product_id = p.product_id)";
			} else {
				$sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
			}
		} else {
			$sql .= " FROM " . DB_PREFIX . "product p";
		}

		$sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";

		if (!empty($data['filter_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
			} else {
				$sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
			}

			if (!empty($data['filter_filter'])) {
				$implode = array();

				$filters = explode(',', $data['filter_filter']);

				foreach ($filters as $filter_id) {
					$implode[] = (int)$filter_id;
				}

				$sql .= " AND pf.filter_id IN (" . implode(',', $implode) . ")";
			}
		}

		if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
			$sql .= " AND (";

			if (!empty($data['filter_name'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

				foreach ($words as $word) {
					$implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " " . implode(" AND ", $implode) . "";
				}

				if (!empty($data['filter_description'])) {
					$sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
				}
			}

			if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
				$sql .= " OR ";
			}

			if (!empty($data['filter_tag'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_tag'])));

				foreach ($words as $word) {
					$implode[] = "pd.tag LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " " . implode(" AND ", $implode) . "";
				}
			}

			if (!empty($data['filter_name'])) {
				$sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.sku) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.upc) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.ean) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.jan) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.isbn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.mpn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
			}

			$sql .= ")";
		}

		if (!empty($data['filter_manufacturer_id'])) {
			$sql .= " AND p.manufacturer_id = '" . (int)$data['filter_manufacturer_id'] . "'";
		}

		$sql .= " GROUP BY p.product_id";

		$sort_data = array(
			'pd.name',
			'p.model',
			'p.quantity',
			'p.price',
			'rating',
			'p.sort_order',
			'p.date_available'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
				$sql .= " ORDER BY LCASE(" . $data['sort'] . ")";
			} elseif ($data['sort'] == 'p.price') {
				$sql .= " ORDER BY (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
			} else {
				$sql .= " ORDER BY " . $data['sort'];
			}
		} else {
			$sql .= " ORDER BY p.sort_order";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC, LCASE(pd.name) DESC";
		} else {
			$sql .= " ASC, LCASE(pd.name) ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$product_data = array();

		$query = $this->db->query($sql);

		foreach ($query->rows as $result) {
			$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
		}

		return $product_data;
	}

	public function getProductSpecials($data = array()) {
		$sql = "SELECT DISTINCT ps.product_id, (SELECT AVG(rating) FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = ps.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating FROM " . DB_PREFIX . "product_special ps LEFT JOIN " . DB_PREFIX . "product p ON (ps.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) GROUP BY ps.product_id";

		$sort_data = array(
			'pd.name',
			'p.model',
			'ps.price',
			'rating',
			'p.sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
				$sql .= " ORDER BY LCASE(" . $data['sort'] . ")";
			} else {
				$sql .= " ORDER BY " . $data['sort'];
			}
		} else {
			$sql .= " ORDER BY p.sort_order";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC, LCASE(pd.name) DESC";
		} else {
			$sql .= " ASC, LCASE(pd.name) ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$product_data = array();

		$query = $this->db->query($sql);

		foreach ($query->rows as $result) {
			$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
		}

		return $product_data;
	}

	public function getLatestProducts($limit) {
		$product_data = $this->cache->get('product.latest.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit);

		if (!$product_data) {
			$product_data = array();
			$query = $this->db->query("SELECT p.product_id FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' ORDER BY p.date_added DESC LIMIT " . (int)$limit);

			foreach ($query->rows as $result) {
				$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
			}

			$this->cache->set('product.latest.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit, $product_data);
		}

		return $product_data;
	}

	public function getPopularProducts($limit) {
		$product_data = $this->cache->get('product.popular.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit);
	
		if (!$product_data) {
			$product_data = array();
			$query = $this->db->query("SELECT p.product_id FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' ORDER BY p.viewed DESC, p.date_added DESC LIMIT " . (int)$limit);
	
			foreach ($query->rows as $result) {
				$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
			}
			
			$this->cache->set('product.popular.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit, $product_data);
		}
		
		return $product_data;
	}

	public function getBestSellerProducts($limit) {
		$product_data = $this->cache->get('product.bestseller.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit);

		if (!$product_data) {
			$product_data = array();

			$query = $this->db->query("SELECT op.product_id, SUM(op.quantity) AS total FROM " . DB_PREFIX . "order_product op LEFT JOIN `" . DB_PREFIX . "order` o ON (op.order_id = o.order_id) LEFT JOIN `" . DB_PREFIX . "product` p ON (op.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE o.order_status_id > '0' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' GROUP BY op.product_id ORDER BY total DESC LIMIT " . (int)$limit);

			foreach ($query->rows as $result) {
				$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
			}

			$this->cache->set('product.bestseller.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit, $product_data);
		}

		return $product_data;
	}

	public function getProductAttributes($product_id) {
		$product_attribute_group_data = array();

		$product_attribute_group_query = $this->db->query("SELECT ag.attribute_group_id, agd.name FROM " . DB_PREFIX . "product_attribute pa LEFT JOIN " . DB_PREFIX . "attribute a ON (pa.attribute_id = a.attribute_id) LEFT JOIN " . DB_PREFIX . "attribute_group ag ON (a.attribute_group_id = ag.attribute_group_id) LEFT JOIN " . DB_PREFIX . "attribute_group_description agd ON (ag.attribute_group_id = agd.attribute_group_id) WHERE pa.product_id = '" . (int)$product_id . "' AND agd.language_id = '" . (int)$this->config->get('config_language_id') . "' GROUP BY ag.attribute_group_id ORDER BY ag.sort_order, agd.name");

		foreach ($product_attribute_group_query->rows as $product_attribute_group) {
			$product_attribute_data = array();

			$product_attribute_query = $this->db->query("SELECT a.attribute_id, ad.name, pa.text FROM " . DB_PREFIX . "product_attribute pa LEFT JOIN " . DB_PREFIX . "attribute a ON (pa.attribute_id = a.attribute_id) LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE pa.product_id = '" . (int)$product_id . "' AND a.attribute_group_id = '" . (int)$product_attribute_group['attribute_group_id'] . "' AND ad.language_id = '" . (int)$this->config->get('config_language_id') . "' AND pa.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY a.sort_order, ad.name");

			foreach ($product_attribute_query->rows as $product_attribute) {
				$product_attribute_data[] = array(
					'attribute_id' => $product_attribute['attribute_id'],
					'name'         => $product_attribute['name'],
					'text'         => $product_attribute['text']
				);
			}

			$product_attribute_group_data[] = array(
				'attribute_group_id' => $product_attribute_group['attribute_group_id'],
				'name'               => $product_attribute_group['name'],
				'attribute'          => $product_attribute_data
			);
		}

		return $product_attribute_group_data;
	}

	public function getProductOptions($product_id) {
		$product_option_data = array();

		$product_option_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_option po LEFT JOIN `" . DB_PREFIX . "option` o ON (po.option_id = o.option_id) LEFT JOIN " . DB_PREFIX . "option_description od ON (o.option_id = od.option_id) WHERE po.product_id = '" . (int)$product_id . "' AND od.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY o.sort_order");

		foreach ($product_option_query->rows as $product_option) {
			$product_option_value_data = array();

			$product_option_value_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_option_value pov LEFT JOIN " . DB_PREFIX . "option_value ov ON (pov.option_value_id = ov.option_value_id) LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE pov.product_id = '" . (int)$product_id . "' AND pov.product_option_id = '" . (int)$product_option['product_option_id'] . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY ov.sort_order");

			foreach ($product_option_value_query->rows as $product_option_value) {
				$product_option_value_data[] = array(
					'product_option_value_id' => $product_option_value['product_option_value_id'],
					'option_value_id'         => $product_option_value['option_value_id'],
					'name'                    => $product_option_value['name'],
					'image'                   => $product_option_value['image'],
					'quantity'                => $product_option_value['quantity'],
					'subtract'                => $product_option_value['subtract'],
					'price'                   => $product_option_value['price'],
					'price_prefix'            => $product_option_value['price_prefix'],
					'weight'                  => $product_option_value['weight'],
					'weight_prefix'           => $product_option_value['weight_prefix']
				);
			}

			$product_option_data[] = array(
				'product_option_id'    => $product_option['product_option_id'],
				'product_option_value' => $product_option_value_data,
				'option_id'            => $product_option['option_id'],
				'name'                 => $product_option['name'],
				'type'                 => $product_option['type'],
				'value'                => $product_option['value'],
				'required'             => $product_option['required']
			);
		}

		return $product_option_data;
	}

	public function getProductDiscounts($product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$product_id . "' AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND quantity > 1 AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY quantity ASC, priority ASC, price ASC");

		return $query->rows;
	}

	public function getProductImages($product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "' ORDER BY sort_order ASC");

		return $query->rows;
	}

	public function getProductRelated($product_id) {
		$product_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_related pr LEFT JOIN " . DB_PREFIX . "product p ON (pr.related_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pr.product_id = '" . (int)$product_id . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'");

		foreach ($query->rows as $result) {
			$product_data[$result['related_id']] = $this->getProduct($result['related_id']);
		}

		return $product_data;
	}

	public function getProductLayoutId($product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_layout WHERE product_id = '" . (int)$product_id . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");

		if ($query->num_rows) {
			return (int)$query->row['layout_id'];
		} else {
			return 0;
		}
	}

	public function getCategories($product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");

		return $query->rows;
	}

	public function getTotalProducts($data = array()) {
		$sql = "SELECT COUNT(DISTINCT p.product_id) AS total";

		if (!empty($data['filter_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)";
			} else {
				$sql .= " FROM " . DB_PREFIX . "product_to_category p2c";
			}

			if (!empty($data['filter_filter'])) {
				$sql .= " LEFT JOIN " . DB_PREFIX . "product_filter pf ON (p2c.product_id = pf.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (pf.product_id = p.product_id)";
			} else {
				$sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
			}
		} else {
			$sql .= " FROM " . DB_PREFIX . "product p";
		}

		$sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";

		if (!empty($data['filter_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
			} else {
				$sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
			}

			if (!empty($data['filter_filter'])) {
				$implode = array();

				$filters = explode(',', $data['filter_filter']);

				foreach ($filters as $filter_id) {
					$implode[] = (int)$filter_id;
				}

				$sql .= " AND pf.filter_id IN (" . implode(',', $implode) . ")";
			}
		}

		if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
			$sql .= " AND (";

			if (!empty($data['filter_name'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

				foreach ($words as $word) {
					$implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " " . implode(" AND ", $implode) . "";
				}

				if (!empty($data['filter_description'])) {
					$sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
				}
			}

			if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
				$sql .= " OR ";
			}

			if (!empty($data['filter_tag'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_tag'])));

				foreach ($words as $word) {
					$implode[] = "pd.tag LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " " . implode(" AND ", $implode) . "";
				}
			}

			if (!empty($data['filter_name'])) {
				$sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.sku) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.upc) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.ean) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.jan) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.isbn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.mpn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
			}

			$sql .= ")";
		}

		if (!empty($data['filter_manufacturer_id'])) {
			$sql .= " AND p.manufacturer_id = '" . (int)$data['filter_manufacturer_id'] . "'";
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}

	public function getProfile($product_id, $recurring_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "recurring r JOIN " . DB_PREFIX . "product_recurring pr ON (pr.recurring_id = r.recurring_id AND pr.product_id = '" . (int)$product_id . "') WHERE pr.recurring_id = '" . (int)$recurring_id . "' AND status = '1' AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'");

		return $query->row;
	}

	public function getProfiles($product_id) {
		$query = $this->db->query("SELECT rd.* FROM " . DB_PREFIX . "product_recurring pr JOIN " . DB_PREFIX . "recurring_description rd ON (rd.language_id = " . (int)$this->config->get('config_language_id') . " AND rd.recurring_id = pr.recurring_id) JOIN " . DB_PREFIX . "recurring r ON r.recurring_id = rd.recurring_id WHERE pr.product_id = " . (int)$product_id . " AND status = '1' AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' ORDER BY sort_order ASC");

		return $query->rows;
	}

	public function getTotalProductSpecials() {
		$query = $this->db->query("SELECT COUNT(DISTINCT ps.product_id) AS total FROM " . DB_PREFIX . "product_special ps LEFT JOIN " . DB_PREFIX . "product p ON (ps.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))");

		if (isset($query->row['total'])) {
			return $query->row['total'];
		} else {
			return 0;
		}
	}

	public function checkProductCategory($product_id, $category_ids) {
		
		$implode = array();

		foreach ($category_ids as $category_id) {
			$implode[] = (int)$category_id;
		}
		
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "' AND category_id IN(" . implode(',', $implode) . ")");
  	    return $query->row;
	}

	public function getTotalProductsAdvanced($data = []) {
		// Базовый SELECT
		$sql = "SELECT COUNT(DISTINCT p.product_id) AS total
				FROM " . DB_PREFIX . "product p";

		$customer_group_id = (int)$this->config->get('config_customer_group_id');

		// --- F1: фильтр по категориям ---
		$category_ids = [];

		if (!empty($data['category_ids']) && is_array($data['category_ids'])) {
			foreach ($data['category_ids'] as $category_id) {
				$category_id = (int)$category_id;
				if ($category_id > 0) {
					$category_ids[] = $category_id;
				}
			}

			if ($category_ids) {
				$sql .= " LEFT JOIN " . DB_PREFIX . "product_to_category pc ON (p.product_id = pc.product_id)";
			}
		}

		 // Описание товара
        $sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
		          WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
		            AND p.status = '1'";

        // Поиск по имени/описанию/тегам/модели/артикулам — как в getTotalProducts()
        if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
            $sql .= " AND (";

            if (!empty($data['filter_name'])) {
                $implode = array();

                $words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

                foreach ($words as $word) {
                    $implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
                }

                if ($implode) {
                    $sql .= " " . implode(" AND ", $implode) . "";
                }

                if (!empty($data['filter_description'])) {
                    $sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
                }
            }

            if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
                $sql .= " OR ";
            }

            if (!empty($data['filter_tag'])) {
                $implode = array();

                $words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_tag'])));

                foreach ($words as $word) {
                    $implode[] = "pd.tag LIKE '%" . $this->db->escape($word) . "%'";
                }

                if ($implode) {
                    $sql .= " " . implode(" AND ", $implode) . "";
                }
            }

            if (!empty($data['filter_name'])) {
                $sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.sku)   = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.upc)   = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.ean)   = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.jan)   = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.isbn)  = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.mpn)   = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
            }

            $sql .= ")";
        }

        // Условие по категориям (если выбраны)
        if ($category_ids) {
            $sql .= " AND pc.category_id IN(" . implode(',', $category_ids) . ")";
        }

		// F2: фильтр по производителям
		$manufacturer_ids = [];

		if (!empty($data['manufacturer_ids']) && is_array($data['manufacturer_ids'])) {
			foreach ($data['manufacturer_ids'] as $mid) {
				$mid = (int)$mid;
				if ($mid > 0) {
					$manufacturer_ids[] = $mid;
				}
			}

			if ($manufacturer_ids) {
				$sql .= " AND p.manufacturer_id IN(" . implode(',', $manufacturer_ids) . ")";
			}
		}

		// --- F6–F9: фильтры по атрибутам (масштаб, сложность, период, нация) ---

		// F6: Масштаб (атрибут 201)
		if (!empty($data['scales']) && is_array($data['scales'])) {
			$scale_values = [];

			foreach ($data['scales'] as $value) {
				$value = trim((string)$value);
				if ($value !== '') {
					$scale_values[] = $this->db->escape($value);
				}
			}

			if ($scale_values) {
				$conditions = [];
				foreach ($scale_values as $v) {
					$conditions[] = "pa_scale.text = '" . $v . "'";
				}

				$sql .= " AND EXISTS (
					SELECT 1
					FROM " . DB_PREFIX . "product_attribute pa_scale
					WHERE pa_scale.product_id = p.product_id
					  AND pa_scale.attribute_id = 201
					  AND pa_scale.language_id = '" . (int)$this->config->get('config_language_id') . "'
					  AND (" . implode(' OR ', $conditions) . ")
				)";
			}
		}

		// F7: Сложность (атрибут 204)
		if (!empty($data['levels']) && is_array($data['levels'])) {
			$level_values = [];

			foreach ($data['levels'] as $value) {
				$value = trim((string)$value);
				if ($value !== '') {
					$level_values[] = $this->db->escape($value);
				}
			}

			if ($level_values) {
				$conditions = [];
				foreach ($level_values as $v) {
					$conditions[] = "pa_level.text = '" . $v . "'";
				}

				$sql .= " AND EXISTS (
					SELECT 1
					FROM " . DB_PREFIX . "product_attribute pa_level
					WHERE pa_level.product_id = p.product_id
					  AND pa_level.attribute_id = 204
					  AND pa_level.language_id = '" . (int)$this->config->get('config_language_id') . "'
					  AND (" . implode(' OR ', $conditions) . ")
				)";
			}
		}

		// F8: Исторический период (атрибут 202)
		if (!empty($data['periods']) && is_array($data['periods'])) {
			$period_values = [];

			foreach ($data['periods'] as $value) {
				$value = trim((string)$value);
				if ($value !== '') {
					$period_values[] = $this->db->escape($value);
				}
			}

			if ($period_values) {
				$conditions = [];
				foreach ($period_values as $v) {
					$conditions[] = "pa_period.text = '" . $v . "'";
				}

				$sql .= " AND EXISTS (
					SELECT 1
					FROM " . DB_PREFIX . "product_attribute pa_period
					WHERE pa_period.product_id = p.product_id
					  AND pa_period.attribute_id = 202
					  AND pa_period.language_id = '" . (int)$this->config->get('config_language_id') . "'
					  AND (" . implode(' OR ', $conditions) . ")
				)";
			}
		}

		// F9: Нация (атрибут 203)
		if (!empty($data['nations']) && is_array($data['nations'])) {
			$nation_values = [];

			foreach ($data['nations'] as $value) {
				$value = trim((string)$value);
				if ($value !== '') {
					$nation_values[] = $this->db->escape($value);
				}
			}

			if ($nation_values) {
				$conditions = [];
				foreach ($nation_values as $v) {
					$conditions[] = "pa_nation.text = '" . $v . "'";
				}

				$sql .= " AND EXISTS (
					SELECT 1
					FROM " . DB_PREFIX . "product_attribute pa_nation
					WHERE pa_nation.product_id = p.product_id
					  AND pa_nation.attribute_id = 203
					  AND pa_nation.language_id = '" . (int)$this->config->get('config_language_id') . "'
					  AND (" . implode(' OR ', $conditions) . ")
				)";
			}
		}

		// F11: Для детей / Для начинающих (атрибут 206)
        if (!empty($data['kids'])) {
            $sql .= " AND EXISTS (
                SELECT 1
                FROM " . DB_PREFIX . "product_attribute pa_kids
                WHERE pa_kids.product_id   = p.product_id
                  AND pa_kids.attribute_id = 206
                  AND pa_kids.language_id  = '" . (int)$this->config->get('config_language_id') . "'
                  AND pa_kids.text <> ''
            )";
        }

        // F12: С аксессуарами (атрибут 207)
        if (!empty($data['accessories'])) {
            $sql .= " AND EXISTS (
                SELECT 1
                FROM " . DB_PREFIX . "product_attribute pa_acc
                WHERE pa_acc.product_id   = p.product_id
                  AND pa_acc.attribute_id = 207
                  AND pa_acc.language_id  = '" . (int)$this->config->get('config_language_id') . "'
                  AND pa_acc.text <> ''
            )";
        }

		// Поиск по имени/описанию/тегам/модели/артикулам — как в стандартном getTotalProducts()
		if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
			$sql .= " AND (";

			if (!empty($data['filter_name'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

				foreach ($words as $word) {
					$implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " " . implode(" AND ", $implode) . "";
				}

				if (!empty($data['filter_description'])) {
					$sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
				}
			}

			if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
				$sql .= " OR ";
			}

			if (!empty($data['filter_tag'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_tag'])));

				foreach ($words as $word) {
					$implode[] = "pd.tag LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " " . implode(" AND ", $implode) . "";
				}
			}

			if (!empty($data['filter_name'])) {
				$sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.sku) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.upc) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.ean) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.jan) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.isbn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.mpn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
			}

			$sql .= ")";
		}

		// Архивные по умолчанию исключаем
		if (empty($data['show_archive'])) {
			$sql .= " AND p.quantity > 0";
		}

		// Новинки (последние 20 дней по дате поступления, только товары в наличии)
		if (!empty($data['show_new'])) {
			$sql .= " AND p.quantity > 0"
				. " AND DATE(p.date_available) >= DATE_SUB(CURDATE(), INTERVAL 20 DAY)";
		}

		// F3: фильтр "Со скидками"
		if (!empty($data['discount_only'])) {
			$sql .= "
				AND p.quantity > 0
				AND (
					EXISTS (
						SELECT 1
						FROM " . DB_PREFIX . "product_special ps
						WHERE ps.product_id = p.product_id
						AND ps.customer_group_id = '" . (int)$customer_group_id . "'
						AND (ps.date_start = '0000-00-00' OR ps.date_start <= NOW())
						AND (ps.date_end   = '0000-00-00' OR ps.date_end   >= NOW())
						AND ps.price < p.price
					)
					OR (
						NOT EXISTS (
							SELECT 1
							FROM " . DB_PREFIX . "product_special ps
							WHERE ps.product_id = p.product_id
							AND ps.customer_group_id = '" . (int)$customer_group_id . "'
							AND (ps.date_start = '0000-00-00' OR ps.date_start <= NOW())
							AND (ps.date_end   = '0000-00-00' OR ps.date_end   >= NOW())
						)
						AND EXISTS (
							SELECT 1
							FROM " . DB_PREFIX . "product_discount pd
							WHERE pd.product_id = p.product_id
							AND pd.customer_group_id = '" . (int)$customer_group_id . "'
							AND pd.quantity <= 1
							AND (pd.date_start = '0000-00-00' OR pd.date_start <= NOW())
							AND (pd.date_end   = '0000-00-00' OR pd.date_end   >= NOW())
							AND pd.price < p.price
						)
					)
				)
			";
		}

		$query = $this->db->query($sql);
		return (int)$query->row['total'];
	}

	public function getProductsAdvanced($data = []) {
		$start = isset($data['page']) && isset($data['limit'])
			? max(0, ((int)$data['page'] - 1) * (int)$data['limit'])
			: 0;
		$limit = isset($data['limit']) ? (int)$data['limit'] : 20;
		$customer_group_id = (int)$this->config->get('config_customer_group_id');

		// Базовый SELECT
		$sql = "SELECT DISTINCT
			p.product_id,
			p.image,
			p.price,
			p.quantity,
			pd.name,
			p.date_added,
			p.date_available,
			(SELECT price FROM " . DB_PREFIX . "product_discount pd2
			WHERE pd2.product_id = p.product_id
				AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
				AND pd2.quantity = '1'
				AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW())
				AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW()))
			ORDER BY pd2.priority ASC, pd2.price ASC
			LIMIT 1) AS discount,
			(SELECT price FROM " . DB_PREFIX . "product_special ps
			WHERE ps.product_id = p.product_id
				AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
				AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW())
				AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))
			ORDER BY ps.priority ASC, ps.price ASC
			LIMIT 1) AS special
		        FROM " . DB_PREFIX . "product p";

		// --- F1: фильтр по категориям ---
		$category_ids = [];

		if (!empty($data['category_ids']) && is_array($data['category_ids'])) {
			foreach ($data['category_ids'] as $category_id) {
				$category_id = (int)$category_id;
				if ($category_id > 0) {
					$category_ids[] = $category_id;
				}
			}

			if ($category_ids) {
				$sql .= " LEFT JOIN " . DB_PREFIX . "product_to_category pc ON (p.product_id = pc.product_id)";
			}
		}

		// Описание товара
		$sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
		          WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
		            AND p.status = '1'";

		// Условие по выбранным категориям
		if ($category_ids) {
			$sql .= " AND pc.category_id IN(" . implode(',', $category_ids) . ")";
		}

		// F2: фильтр по производителям
		$manufacturer_ids = [];

		if (!empty($data['manufacturer_ids']) && is_array($data['manufacturer_ids'])) {
			foreach ($data['manufacturer_ids'] as $mid) {
				$mid = (int)$mid;
				if ($mid > 0) {
					$manufacturer_ids[] = $mid;
				}
			}

			if ($manufacturer_ids) {
				$sql .= " AND p.manufacturer_id IN(" . implode(',', $manufacturer_ids) . ")";
			}
		}

		// --- F6–F9: фильтры по атрибутам (масштаб, сложность, период, нация) ---

		// F6: Масштаб (атрибут 201)
		if (!empty($data['scales']) && is_array($data['scales'])) {
			$scale_values = [];

			foreach ($data['scales'] as $value) {
				$value = trim((string)$value);
				if ($value !== '') {
					$scale_values[] = $this->db->escape($value);
				}
			}

			if ($scale_values) {
				$conditions = [];
				foreach ($scale_values as $v) {
					$conditions[] = "pa_scale.text = '" . $v . "'";
				}

				$sql .= " AND EXISTS (
					SELECT 1
					FROM " . DB_PREFIX . "product_attribute pa_scale
					WHERE pa_scale.product_id = p.product_id
					  AND pa_scale.attribute_id = 201
					  AND pa_scale.language_id = '" . (int)$this->config->get('config_language_id') . "'
					  AND (" . implode(' OR ', $conditions) . ")
				)";
			}
		}

		// F7: Сложность (атрибут 204)
		if (!empty($data['levels']) && is_array($data['levels'])) {
			$level_values = [];

			foreach ($data['levels'] as $value) {
				$value = trim((string)$value);
				if ($value !== '') {
					$level_values[] = $this->db->escape($value);
				}
			}

			if ($level_values) {
				$conditions = [];
				foreach ($level_values as $v) {
					$conditions[] = "pa_level.text = '" . $v . "'";
				}

				$sql .= " AND EXISTS (
					SELECT 1
					FROM " . DB_PREFIX . "product_attribute pa_level
					WHERE pa_level.product_id = p.product_id
					  AND pa_level.attribute_id = 204
					  AND pa_level.language_id = '" . (int)$this->config->get('config_language_id') . "'
					  AND (" . implode(' OR ', $conditions) . ")
				)";
			}
		}

		// F8: Исторический период (атрибут 202)
		if (!empty($data['periods']) && is_array($data['periods'])) {
			$period_values = [];

			foreach ($data['periods'] as $value) {
				$value = trim((string)$value);
				if ($value !== '') {
					$period_values[] = $this->db->escape($value);
				}
			}

			if ($period_values) {
				$conditions = [];
				foreach ($period_values as $v) {
					$conditions[] = "pa_period.text = '" . $v . "'";
				}

				$sql .= " AND EXISTS (
					SELECT 1
					FROM " . DB_PREFIX . "product_attribute pa_period
					WHERE pa_period.product_id = p.product_id
					  AND pa_period.attribute_id = 202
					  AND pa_period.language_id = '" . (int)$this->config->get('config_language_id') . "'
					  AND (" . implode(' OR ', $conditions) . ")
				)";
			}
		}

		// F9: Нация (атрибут 203)
		if (!empty($data['nations']) && is_array($data['nations'])) {
			$nation_values = [];

			foreach ($data['nations'] as $value) {
				$value = trim((string)$value);
				if ($value !== '') {
					$nation_values[] = $this->db->escape($value);
				}
			}

			if ($nation_values) {
				$conditions = [];
				foreach ($nation_values as $v) {
					$conditions[] = "pa_nation.text = '" . $v . "'";
				}

				$sql .= " AND EXISTS (
					SELECT 1
					FROM " . DB_PREFIX . "product_attribute pa_nation
					WHERE pa_nation.product_id = p.product_id
					  AND pa_nation.attribute_id = 203
					  AND pa_nation.language_id = '" . (int)$this->config->get('config_language_id') . "'
					  AND (" . implode(' OR ', $conditions) . ")
				)";
			}
		}

		// F11: Для детей / Для начинающих (атрибут 206)
        if (!empty($data['kids'])) {
            $sql .= " AND EXISTS (
                SELECT 1
                FROM " . DB_PREFIX . "product_attribute pa_kids
                WHERE pa_kids.product_id   = p.product_id
                  AND pa_kids.attribute_id = 206
                  AND pa_kids.language_id  = '" . (int)$this->config->get('config_language_id') . "'
                  AND pa_kids.text <> ''
            )";
        }

        // F12: С аксессуарами (атрибут 207)
        if (!empty($data['accessories'])) {
            $sql .= " AND EXISTS (
                SELECT 1
                FROM " . DB_PREFIX . "product_attribute pa_acc
                WHERE pa_acc.product_id   = p.product_id
                  AND pa_acc.attribute_id = 207
                  AND pa_acc.language_id  = '" . (int)$this->config->get('config_language_id') . "'
                  AND pa_acc.text <> ''
            )";
        }

		// Поиск по имени/описанию/тегам/модели/артикулам — как в стандартном getTotalProducts()
		if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
			$sql .= " AND (";

			if (!empty($data['filter_name'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

				foreach ($words as $word) {
					$implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " " . implode(" AND ", $implode) . "";
				}

				if (!empty($data['filter_description'])) {
					$sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
				}
			}

			if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
				$sql .= " OR ";
			}

			if (!empty($data['filter_tag'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_tag'])));

				foreach ($words as $word) {
					$implode[] = "pd.tag LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " " . implode(" AND ", $implode) . "";
				}
			}

			if (!empty($data['filter_name'])) {
				$sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.sku) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.upc) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.ean) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.jan) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.isbn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.mpn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
			}

			$sql .= ")";
		}

		// Исключаем архивные (если не включены принудительно)
		if (empty($data['show_archive'])) {
			$sql .= " AND p.quantity > 0";
		}

		// Новинки (последние 20 дней по дате поступления, только товары в наличии)
		if (!empty($data['show_new'])) {
			$sql .= " AND p.quantity > 0"
				. " AND DATE(p.date_available) >= DATE_SUB(CURDATE(), INTERVAL 20 DAY)";
		}

		// F3: фильтр "Со скидками"
		if (!empty($data['discount_only'])) {
			$sql .= "
				AND p.quantity > 0
				AND (
					EXISTS (
						SELECT 1
						FROM " . DB_PREFIX . "product_special ps
						WHERE ps.product_id = p.product_id
						AND ps.customer_group_id = '" . (int)$customer_group_id . "'
						AND (ps.date_start = '0000-00-00' OR ps.date_start <= NOW())
						AND (ps.date_end   = '0000-00-00' OR ps.date_end   >= NOW())
						AND ps.price < p.price
					)
					OR (
						NOT EXISTS (
							SELECT 1
							FROM " . DB_PREFIX . "product_special ps
							WHERE ps.product_id = p.product_id
							AND ps.customer_group_id = '" . (int)$customer_group_id . "'
							AND (ps.date_start = '0000-00-00' OR ps.date_start <= NOW())
							AND (ps.date_end   = '0000-00-00' OR ps.date_end   >= NOW())
						)
						AND EXISTS (
							SELECT 1
							FROM " . DB_PREFIX . "product_discount pd
							WHERE pd.product_id = p.product_id
							AND pd.customer_group_id = '" . (int)$customer_group_id . "'
							AND pd.quantity <= 1
							AND (pd.date_start = '0000-00-00' OR pd.date_start <= NOW())
							AND (pd.date_end   = '0000-00-00' OR pd.date_end   >= NOW())
							AND pd.price < p.price
						)
					)
				)
			";
		}

		// F5: сортировка
        // Если включено "Показать отсутствующие" И сортировка по цене,
        // то сначала товары в наличии, затем отсутствующие,
        // внутри групп — по цене (ASC/DESC из $data['sort']).
        $use_stock_priority = !empty($data['show_archive'])	&& !empty($data['sort_key']) && in_array($data['sort_key'], ['price_asc', 'price_desc'], true);

		if (!empty($data['sort'])) {
            if ($use_stock_priority) {
                // Сначала в наличии, потом отсутствующие, внутри — по выбранному полю
                $sql .= ' ORDER BY (p.quantity > 0) DESC, ';
                $sql .= $data['sort'];
            } else {
                // Обычная сортировка без приоритета наличия
                $sql .= ' ORDER BY ' . $data['sort'];
            }
        }

        $sql .= " LIMIT " . (int)$start . "," . (int)$limit;

        $query = $this->db->query($sql);
		return $query->rows;
	}

	public function getManufacturersForFilter($data = []) {
		// Базовый SELECT: уникальные производители
		$sql = "SELECT DISTINCT p.manufacturer_id
		        FROM " . DB_PREFIX . "product p";

		$customer_group_id = (int)$this->config->get('config_customer_group_id');

		// Категории (как в getProductsAdvanced / getTotalProductsAdvanced)
		$category_ids = [];

		if (!empty($data['category_ids']) && is_array($data['category_ids'])) {
			foreach ($data['category_ids'] as $category_id) {
				$category_id = (int)$category_id;
				if ($category_id > 0) {
					$category_ids[] = $category_id;
				}
			}

			if ($category_ids) {
				$sql .= " LEFT JOIN " . DB_PREFIX . "product_to_category pc ON (p.product_id = pc.product_id)";
			}
		}

		// Описание товара
		$sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
		          WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
		            AND p.status = '1'";

		// Условие по категориям (если выбраны)
		if ($category_ids) {
			$sql .= " AND pc.category_id IN(" . implode(',', $category_ids) . ")";
		}

		// Поиск по имени/описанию/тегам/модели/артикулам — как в getTotalProductsAdvanced()
		if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
			$sql .= " AND (";

			if (!empty($data['filter_name'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

				foreach ($words as $word) {
					$implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " " . implode(" AND ", $implode) . "";
				}

				if (!empty($data['filter_description'])) {
					$sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
				}
			}

			if (!empty($data['filter_tag'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_tag'])));

				foreach ($words as $word) {
					$implode[] = "pd.tag LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " OR " . implode(" AND ", $implode) . "";
				}
			}

			if (!empty($data['filter_name'])) {
				$sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.sku)   = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.upc)   = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.ean)   = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.jan)   = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.isbn)  = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.mpn)   = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
			}

			$sql .= ")";
		}

		// --- F6–F9: фильтры по атрибутам (масштаб, сложность, период, нация) ---

		// F6: Масштаб (атрибут 201)
		if (!empty($data['scales']) && is_array($data['scales'])) {
			$scale_values = [];

			foreach ($data['scales'] as $value) {
				$value = trim((string)$value);
				if ($value !== '') {
					$scale_values[] = $this->db->escape($value);
				}
			}

			if ($scale_values) {
				$conditions = [];
				foreach ($scale_values as $v) {
					$conditions[] = "pa_scale.text = '" . $v . "'";
				}

				$sql .= " AND EXISTS (
					SELECT 1
					FROM " . DB_PREFIX . "product_attribute pa_scale
					WHERE pa_scale.product_id = p.product_id
					  AND pa_scale.attribute_id = 201
					  AND pa_scale.language_id = '" . (int)$this->config->get('config_language_id') . "'
					  AND (" . implode(' OR ', $conditions) . ")
				)";
			}
		}

		// F7: Сложность (атрибут 204)
		if (!empty($data['levels']) && is_array($data['levels'])) {
			$level_values = [];

			foreach ($data['levels'] as $value) {
				$value = trim((string)$value);
				if ($value !== '') {
					$level_values[] = $this->db->escape($value);
				}
			}

			if ($level_values) {
				$conditions = [];
				foreach ($level_values as $v) {
					$conditions[] = "pa_level.text = '" . $v . "'";
				}

				$sql .= " AND EXISTS (
					SELECT 1
					FROM " . DB_PREFIX . "product_attribute pa_level
					WHERE pa_level.product_id = p.product_id
					  AND pa_level.attribute_id = 204
					  AND pa_level.language_id = '" . (int)$this->config->get('config_language_id') . "'
					  AND (" . implode(' OR ', $conditions) . ")
				)";
			}
		}

		// F8: Исторический период (атрибут 202)
		if (!empty($data['periods']) && is_array($data['periods'])) {
			$period_values = [];

			foreach ($data['periods'] as $value) {
				$value = trim((string)$value);
				if ($value !== '') {
					$period_values[] = $this->db->escape($value);
				}
			}

			if ($period_values) {
				$conditions = [];
				foreach ($period_values as $v) {
					$conditions[] = "pa_period.text = '" . $v . "'";
				}

				$sql .= " AND EXISTS (
					SELECT 1
					FROM " . DB_PREFIX . "product_attribute pa_period
					WHERE pa_period.product_id = p.product_id
					  AND pa_period.attribute_id = 202
					  AND pa_period.language_id = '" . (int)$this->config->get('config_language_id') . "'
					  AND (" . implode(' OR ', $conditions) . ")
				)";
			}
		}

		// F9: Нация (атрибут 203)
		if (!empty($data['nations']) && is_array($data['nations'])) {
			$nation_values = [];

			foreach ($data['nations'] as $value) {
				$value = trim((string)$value);
				if ($value !== '') {
					$nation_values[] = $this->db->escape($value);
				}
			}

			if ($nation_values) {
				$conditions = [];
				foreach ($nation_values as $v) {
					$conditions[] = "pa_nation.text = '" . $v . "'";
				}

				$sql .= " AND EXISTS (
					SELECT 1
					FROM " . DB_PREFIX . "product_attribute pa_nation
					WHERE pa_nation.product_id = p.product_id
					  AND pa_nation.attribute_id = 203
					  AND pa_nation.language_id = '" . (int)$this->config->get('config_language_id') . "'
					  AND (" . implode(' OR ', $conditions) . ")
				)";
			}
		}

		// F11: Для детей / Для начинающих (атрибут 206)
        if (!empty($data['kids'])) {
            $sql .= " AND EXISTS (
                SELECT 1
                FROM " . DB_PREFIX . "product_attribute pa_kids
                WHERE pa_kids.product_id   = p.product_id
                  AND pa_kids.attribute_id = 206
                  AND pa_kids.language_id  = '" . (int)$this->config->get('config_language_id') . "'
                  AND pa_kids.text <> ''
            )";
        }

        // F12: С аксессуарами (атрибут 207)
        if (!empty($data['accessories'])) {
            $sql .= " AND EXISTS (
                SELECT 1
                FROM " . DB_PREFIX . "product_attribute pa_acc
                WHERE pa_acc.product_id   = p.product_id
                  AND pa_acc.attribute_id = 207
                  AND pa_acc.language_id  = '" . (int)$this->config->get('config_language_id') . "'
                  AND pa_acc.text <> ''
            )";
        }

		// Архивные по умолчанию исключаем
		if (empty($data['show_archive'])) {
			$sql .= " AND p.quantity > 0";
		}

		// Новинки (последние 20 дней по дате поступления, только товары в наличии)
		if (!empty($data['show_new'])) {
			$sql .= " AND p.quantity > 0"
				. " AND DATE(p.date_available) >= DATE_SUB(CURDATE(), INTERVAL 20 DAY)";
		}

		// F3: фильтр "Со скидками" для брендов
		if (!empty($data['discount_only'])) {
			$sql .= "
				AND p.quantity > 0
				AND (
					EXISTS (
						SELECT 1
						FROM " . DB_PREFIX . "product_special ps
						WHERE ps.product_id = p.product_id
						AND ps.customer_group_id = '" . (int)$customer_group_id . "'
						AND (ps.date_start = '0000-00-00' OR ps.date_start <= NOW())
						AND (ps.date_end   = '0000-00-00' OR ps.date_end   >= NOW())
						AND ps.price < p.price
					)
					OR (
						NOT EXISTS (
							SELECT 1
							FROM " . DB_PREFIX . "product_special ps
							WHERE ps.product_id = p.product_id
							AND ps.customer_group_id = '" . (int)$customer_group_id . "'
							AND (ps.date_start = '0000-00-00' OR ps.date_start <= NOW())
							AND (ps.date_end   = '0000-00-00' OR ps.date_end   >= NOW())
						)
						AND EXISTS (
							SELECT 1
							FROM " . DB_PREFIX . "product_discount pd
							WHERE pd.product_id = p.product_id
							AND pd.customer_group_id = '" . (int)$customer_group_id . "'
							AND (pd.date_start = '0000-00-00' OR pd.date_start <= NOW())
							AND (pd.date_end   = '0000-00-00' OR pd.date_end   >= NOW())
							AND pd.price < p.price
						)
					)
				)
			";
		}

		$query = $this->db->query($sql);

		$result = [];

		foreach ($query->rows as $row) {
			$mid = (int)$row['manufacturer_id'];
			if ($mid > 0) {
				$result[] = $mid;
			}
		}

		return $result;
	}

	public function getAttributeValuesForFilter($attribute_id, $data = []) {
		$attribute_id = (int)$attribute_id;

		$language_id        = (int)$this->config->get('config_language_id');
		$store_id           = (int)$this->config->get('config_store_id');
		$customer_group_id  = (int)$this->config->get('config_customer_group_id');

		$sql = "SELECT DISTINCT TRIM(pa.text) AS value
				FROM " . DB_PREFIX . "product_attribute pa
				INNER JOIN " . DB_PREFIX . "product p ON (pa.product_id = p.product_id)
				INNER JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
				LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id)
				LEFT JOIN " . DB_PREFIX . "product_special ps ON (
					p.product_id = ps.product_id
					AND ps.customer_group_id = '" . (int)$customer_group_id . "'
					AND (ps.date_start = '0000-00-00' OR ps.date_start <= NOW())
					AND (ps.date_end   = '0000-00-00' OR ps.date_end   >= NOW())
				)
				LEFT JOIN " . DB_PREFIX . "product_discount pd ON (
					p.product_id = pd.product_id
					AND pd.customer_group_id = '" . (int)$customer_group_id . "'
					AND pd.quantity = 1
					AND (pd.date_start = '0000-00-00' OR pd.date_start <= NOW())
					AND (pd.date_end   = '0000-00-00' OR pd.date_end   >= NOW())
				)
				LEFT JOIN " . DB_PREFIX . "product_description pd2 ON (
					p.product_id = pd2.product_id
					AND pd2.language_id = '" . (int)$language_id . "'
				)
				WHERE pa.attribute_id = '" . $attribute_id . "'
				AND pa.language_id  = '" . $language_id . "'
				AND p2s.store_id    = '" . $store_id . "'
				AND p.status = '1'
				AND p.date_available <= NOW()";

		// Категории (учитываем выбранную категорию/подкатегорию)
		if (!empty($data['category_ids']) && is_array($data['category_ids'])) {
			$category_ids = array_map('intval', $data['category_ids']);
			$sql .= " AND p2c.category_id IN (" . implode(',', $category_ids) . ")";
		}

		// Производители
		if (!empty($data['manufacturer_ids']) && is_array($data['manufacturer_ids'])) {
			$manufacturer_ids = array_map('intval', $data['manufacturer_ids']);
			$sql .= " AND p.manufacturer_id IN (" . implode(',', $manufacturer_ids) . ")";
		}

		// Показ отсутствующих: по умолчанию исключаем архив/0, если флаг НЕ включён
		if (empty($data['show_archive'])) {
			$sql .= " AND p.quantity > 0";
		}

		// Новинки
		if (!empty($data['show_new'])) {
			// То же окно, что и для is_new (20 дней)
			$sql .= " AND p.date_available >= DATE_SUB(CURDATE(), INTERVAL 20 DAY)";
		}

		// Только со скидкой
		if (!empty($data['discount_only'])) {
			$sql .= " AND (ps.price IS NOT NULL OR pd.price IS NOT NULL)";
		}

		 // Поиск по имени/описанию/тегам/модели/артикулам — как в getTotalProducts()
        if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
            $sql .= " AND (";

            if (!empty($data['filter_name'])) {
                $implode = array();

                $words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

                foreach ($words as $word) {
                    $implode[] = "pd2.name LIKE '%" . $this->db->escape($word) . "%'";
                }

                if ($implode) {
                    $sql .= " " . implode(" AND ", $implode) . "";
                }

                if (!empty($data['filter_description'])) {
                    $sql .= " OR pd2.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
                }
            }

            if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
                $sql .= " OR ";
            }

            if (!empty($data['filter_tag'])) {
                $implode = array();

                $words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_tag'])));

                foreach ($words as $word) {
                    $implode[] = "pd2.tag LIKE '%" . $this->db->escape($word) . "%'";
                }

                if ($implode) {
                    $sql .= " " . implode(" AND ", $implode) . "";
                }
            }

            if (!empty($data['filter_name'])) {
                $sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.sku)   = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.upc)   = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.ean)   = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.jan)   = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.isbn)  = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
                $sql .= " OR LCASE(p.mpn)   = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
            }

            $sql .= ")";
        }

		// ----- Атрибутные фильтры: масштаб / сложность / период / нация -----

		// Масштаб (атрибут 201)
		if (!empty($data['scales']) && is_array($data['scales'])) {
			$values = [];

			foreach ($data['scales'] as $value) {
				$value = trim((string)$value);

				if ($value === '') {
					continue;
				}

				$values[] = "'" . $this->db->escape($value) . "'";
			}

			if ($values) {
				$sql .= " AND EXISTS (
					SELECT 1
					FROM " . DB_PREFIX . "product_attribute pa_scale
					WHERE pa_scale.product_id   = p.product_id
					AND pa_scale.attribute_id = '201'
					AND pa_scale.language_id  = '" . $language_id . "'
					AND TRIM(pa_scale.text) IN (" . implode(',', $values) . ")
				)";
			}
		}

		// Сложность (атрибут 204)
		if (!empty($data['levels']) && is_array($data['levels'])) {
			$values = [];

			foreach ($data['levels'] as $value) {
				$value = trim((string)$value);

				if ($value === '') {
					continue;
				}

				$values[] = "'" . $this->db->escape($value) . "'";
			}

			if ($values) {
				$sql .= " AND EXISTS (
					SELECT 1
					FROM " . DB_PREFIX . "product_attribute pa_level
					WHERE pa_level.product_id   = p.product_id
					AND pa_level.attribute_id = '204'
					AND pa_level.language_id  = '" . $language_id . "'
					AND TRIM(pa_level.text) IN (" . implode(',', $values) . ")
				)";
			}
		}

		// Исторический период (атрибут 202)
		if (!empty($data['periods']) && is_array($data['periods'])) {
			$values = [];

			foreach ($data['periods'] as $value) {
				$value = trim((string)$value);

				if ($value === '') {
					continue;
				}

				$values[] = "'" . $this->db->escape($value) . "'";
			}

			if ($values) {
				$sql .= " AND EXISTS (
					SELECT 1
					FROM " . DB_PREFIX . "product_attribute pa_period
					WHERE pa_period.product_id   = p.product_id
					AND pa_period.attribute_id = '202'
					AND pa_period.language_id  = '" . $language_id . "'
					AND TRIM(pa_period.text) IN (" . implode(',', $values) . ")
				)";
			}
		}

		// Нация (атрибут 203)
		if (!empty($data['nations']) && is_array($data['nations'])) {
			$values = [];

			foreach ($data['nations'] as $value) {
				$value = trim((string)$value);

				if ($value === '') {
					continue;
				}

				$values[] = "'" . $this->db->escape($value) . "'";
			}

			if ($values) {
				$sql .= " AND EXISTS (
					SELECT 1
					FROM " . DB_PREFIX . "product_attribute pa_nation
					WHERE pa_nation.product_id   = p.product_id
					AND pa_nation.attribute_id = '203'
					AND pa_nation.language_id  = '" . $language_id . "'
					AND TRIM(pa_nation.text) IN (" . implode(',', $values) . ")
				)";
			}
		}

		 // F11: Для детей / Для начинающих (атрибут 206)
        if (!empty($data['kids'])) {
            $sql .= " AND EXISTS (
                SELECT 1
                FROM " . DB_PREFIX . "product_attribute pa_kids
                WHERE pa_kids.product_id   = p.product_id
                  AND pa_kids.attribute_id = 206
                  AND pa_kids.language_id  = '" . $language_id . "'
                  AND TRIM(pa_kids.text) <> ''
            )";
        }

        // F12: С аксессуарами (атрибут 207)
        if (!empty($data['accessories'])) {
            $sql .= " AND EXISTS (
                SELECT 1
                FROM " . DB_PREFIX . "product_attribute pa_acc
                WHERE pa_acc.product_id   = p.product_id
                  AND pa_acc.attribute_id = 207
                  AND pa_acc.language_id  = '" . $language_id . "'
                  AND TRIM(pa_acc.text) <> ''
            )";
        }

		$sql .= " ORDER BY value ASC";

		$query = $this->db->query($sql);

		$values = [];

		foreach ($query->rows as $row) {
			$val = trim((string)$row['value']);

			if ($val === '') {
				continue;
			}

			$values[] = $val;
		}

		return $values;
    }

    /**
     * Подсчёт количества товаров в наличии по категории.
     *
     * Считаем только товары В НАЛИЧИИ (p.quantity > 0), без учёта status/date_available.
     * Используется на главной странице для блока дерева категорий.
     *
     * @param int $category_id
     *
     * @return int
     */
    public function getTotalProductsInStockByCategory($category_id) {
        $category_id = (int)$category_id;

        if ($category_id <= 0) {
            return 0;
        }

        $sql = "SELECT COUNT(DISTINCT p.product_id) AS total
                FROM " . DB_PREFIX . "product p
                LEFT JOIN " . DB_PREFIX . "product_to_category pc ON (p.product_id = pc.product_id)
                LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
                WHERE p.quantity > 0
                  AND pc.category_id = '" . $category_id . "'
                  AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";

        $query = $this->db->query($sql);

        return (int)$query->row['total'];
    }
}

