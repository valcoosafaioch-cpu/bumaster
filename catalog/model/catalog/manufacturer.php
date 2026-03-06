<?php
class ModelCatalogManufacturer extends Model {
	public function getManufacturer($manufacturer_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "manufacturer m LEFT JOIN " . DB_PREFIX . "manufacturer_to_store m2s ON (m.manufacturer_id = m2s.manufacturer_id) WHERE m.manufacturer_id = '" . (int)$manufacturer_id . "' AND m2s.store_id = '" . (int)$this->config->get('config_store_id') . "'");

		return $query->row;
	}

	public function getManufacturers($data = array()) {
		if ($data) {
			$sql = "SELECT * FROM " . DB_PREFIX . "manufacturer m LEFT JOIN " . DB_PREFIX . "manufacturer_to_store m2s ON (m.manufacturer_id = m2s.manufacturer_id) WHERE m2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";

			$sort_data = array(
				'name',
				'sort_order'
			);

			if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
				$sql .= " ORDER BY " . $data['sort'];
			} else {
				$sql .= " ORDER BY name";
			}

			if (isset($data['order']) && ($data['order'] == 'DESC')) {
				$sql .= " DESC";
			} else {
				$sql .= " ASC";
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

			$query = $this->db->query($sql);

			return $query->rows;
		} else {
			$manufacturer_data = $this->cache->get('manufacturer.' . (int)$this->config->get('config_store_id'));

			if (!$manufacturer_data) {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "manufacturer m LEFT JOIN " . DB_PREFIX . "manufacturer_to_store m2s ON (m.manufacturer_id = m2s.manufacturer_id) WHERE m2s.store_id = '" . (int)$this->config->get('config_store_id') . "' ORDER BY name");

				$manufacturer_data = $query->rows;

				$this->cache->set('manufacturer.' . (int)$this->config->get('config_store_id'), $manufacturer_data);
			}

			return $manufacturer_data;
		}
	}

	/**
	 * Возвращает производителей, у которых есть товары в наличии.
	 *
	 * Правило проекта: наличие = только p.quantity > 0.
	 *
	 * @param array $data
	 *  - sort: name|sort_order (по умолчанию sort_order, затем name)
	 *  - order: ASC|DESC (по умолчанию ASC)
	 *  - start: int
	 *  - limit: int
	 *
	 * @return array
	 */
	public function getManufacturersInStock($data = array()) {
		$sql = "SELECT DISTINCT m.manufacturer_id, m.name, m.image, m.sort_order\n"
			. "FROM " . DB_PREFIX . "manufacturer m\n"
			. "INNER JOIN " . DB_PREFIX . "manufacturer_to_store m2s ON (m.manufacturer_id = m2s.manufacturer_id)\n"
			. "INNER JOIN " . DB_PREFIX . "product p ON (p.manufacturer_id = m.manufacturer_id)\n"
			. "INNER JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)\n"
			. "WHERE m2s.store_id = '" . (int)$this->config->get('config_store_id') . "'\n"
			. "AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'\n"
			. "AND p.quantity > 0\n";

		$sort_data = array(
			'name',
			'sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sort = $data['sort'];
		} else {
			$sort = 'sort_order';
		}

		$order = (isset($data['order']) && ($data['order'] === 'DESC')) ? 'DESC' : 'ASC';

		// Стабилизируем порядок (чтобы сетка не "плясала")
		if ($sort === 'name') {
			$sql .= "ORDER BY m.name " . $order . ", m.sort_order " . $order;
		} else {
			$sql .= "ORDER BY m.sort_order " . $order . ", m.name " . $order;
		}

		if (isset($data['start']) || isset($data['limit'])) {
			$start = isset($data['start']) ? (int)$data['start'] : 0;
			$limit = isset($data['limit']) ? (int)$data['limit'] : 20;

			if ($start < 0) {
				$start = 0;
			}

			if ($limit < 1) {
				$limit = 20;
			}

			$sql .= " LIMIT " . $start . "," . $limit;
		}

		$query = $this->db->query($sql);
		return $query->rows;
	}
}