<?php
class ModelAccountPickupPoint extends Model {
	public function getPickupPointsByCustomerId(int $customer_id): array {
		$query = $this->db->query("
			SELECT *
			FROM `" . DB_PREFIX . "customer_pickup_point`
			WHERE `customer_id` = '" . (int)$customer_id . "'
			ORDER BY `service_name` ASC
		");

		return $query->rows;
	}

	public function getPickupPointByServiceCode(int $customer_id, string $service_code): array {
		$query = $this->db->query("
			SELECT *
			FROM `" . DB_PREFIX . "customer_pickup_point`
			WHERE `customer_id` = '" . (int)$customer_id . "'
			  AND `service_code` = '" . $this->db->escape($service_code) . "'
			LIMIT 1
		");

		return $query->row;
	}

	public function savePickupPoint(int $customer_id, array $data): int {
		$service_code = isset($data['service_code']) ? trim((string)$data['service_code']) : '';
		$existing = $this->getPickupPointByServiceCode($customer_id, $service_code);

		if ($existing) {
			$this->updatePickupPoint($customer_id, $service_code, $data);

			return (int)$existing['customer_pickup_point_id'];
		}

		$this->db->query("
			INSERT INTO `" . DB_PREFIX . "customer_pickup_point` SET
				`customer_id` = '" . (int)$customer_id . "',
				`service_code` = '" . $this->db->escape($service_code) . "',
				`service_name` = '" . $this->db->escape($this->prepareString($data, 'service_name')) . "',
				`point_code` = '" . $this->db->escape($this->prepareString($data, 'point_code')) . "',
				`point_name` = '" . $this->db->escape($this->prepareString($data, 'point_name')) . "',
				`address` = '" . $this->db->escape($this->prepareString($data, 'address')) . "',
				`city` = " . $this->prepareNullableSql($data, 'city') . ",
				`postal_code` = " . $this->prepareNullableSql($data, 'postal_code') . ",
				`region` = " . $this->prepareNullableSql($data, 'region') . ",
				`country` = " . $this->prepareNullableSql($data, 'country') . ",
				`display_line` = " . $this->prepareNullableSql($data, 'display_line') . ",
				`raw_payload` = " . $this->preparePayloadSql($data) . ",
				`date_added` = NOW(),
				`date_modified` = NOW()
		");

		return (int)$this->db->getLastId();
	}

	public function updatePickupPoint(int $customer_id, string $service_code, array $data): void {
		$this->db->query("
			UPDATE `" . DB_PREFIX . "customer_pickup_point` SET
				`service_name` = '" . $this->db->escape($this->prepareString($data, 'service_name')) . "',
				`point_code` = '" . $this->db->escape($this->prepareString($data, 'point_code')) . "',
				`point_name` = '" . $this->db->escape($this->prepareString($data, 'point_name')) . "',
				`address` = '" . $this->db->escape($this->prepareString($data, 'address')) . "',
				`city` = " . $this->prepareNullableSql($data, 'city') . ",
				`postal_code` = " . $this->prepareNullableSql($data, 'postal_code') . ",
				`region` = " . $this->prepareNullableSql($data, 'region') . ",
				`country` = " . $this->prepareNullableSql($data, 'country') . ",
				`display_line` = " . $this->prepareNullableSql($data, 'display_line') . ",
				`raw_payload` = " . $this->preparePayloadSql($data) . ",
				`date_modified` = NOW()
			WHERE `customer_id` = '" . (int)$customer_id . "'
			  AND `service_code` = '" . $this->db->escape($service_code) . "'
		");
	}

	public function deletePickupPointByServiceCode(int $customer_id, string $service_code): void {
		$this->db->query("
			DELETE FROM `" . DB_PREFIX . "customer_pickup_point`
			WHERE `customer_id` = '" . (int)$customer_id . "'
			  AND `service_code` = '" . $this->db->escape($service_code) . "'
		");
	}

	public function clearPickupPointsByCustomerId(int $customer_id): void {
		$this->db->query("
			DELETE FROM `" . DB_PREFIX . "customer_pickup_point`
			WHERE `customer_id` = '" . (int)$customer_id . "'
		");
	}

	protected function prepareString(array $data, string $key): string {
		return isset($data[$key]) ? trim((string)$data[$key]) : '';
	}

	protected function prepareNullableSql(array $data, string $key): string {
		$value = isset($data[$key]) ? trim((string)$data[$key]) : '';

		if ($value === '') {
			return 'NULL';
		}

		return "'" . $this->db->escape($value) . "'";
	}

	protected function preparePayloadSql(array $data): string {
		if (!isset($data['raw_payload']) || $data['raw_payload'] === '' || $data['raw_payload'] === null) {
			return 'NULL';
		}

		$payload = $data['raw_payload'];

		if (is_array($payload) || is_object($payload)) {
			$payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} else {
			$payload = (string)$payload;
		}

		if ($payload === '' || $payload === false) {
			return 'NULL';
		}

		return "'" . $this->db->escape($payload) . "'";
	}
}