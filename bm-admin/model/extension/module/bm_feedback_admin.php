<?php
class ModelExtensionModuleBmFeedbackAdmin extends Model {
	/**
	 * Общее количество записей, требующих действия
	 */
	public function getNeedActionTotal() {
		return $this->getNeedActionReviewsTotal() + $this->getNeedActionQuestionsTotal();
	}

	/**
	 * Количество отзывов, требующих действия
	 */
	public function getNeedActionReviewsTotal() {
		return $this->getFeedbackTotalByMode('review', 'need_action');
	}

	/**
	 * Количество вопросов, требующих действия
	 */
	public function getNeedActionQuestionsTotal() {
		return $this->getFeedbackTotalByMode('question', 'need_action');
	}

	/**
	 * Отзывы — требуют действия
	 */
	public function getReviewsNeedAction(array $data = []) {
		return $this->getFeedbackListByMode('review', 'need_action', $data);
	}

	/**
	 * Отзывы — опубликованные
	 */
	public function getReviewsPublished(array $data = []) {
		return $this->getFeedbackListByMode('review', 'published', $data);
	}

	/**
	 * Отзывы — отклонённые
	 */
	public function getReviewsRejected(array $data = []) {
		return $this->getFeedbackListByMode('review', 'rejected', $data);
	}

	/**
	 * Количество отзывов — требуют действия
	 */
	public function getReviewsNeedActionTotal() {
		return $this->getFeedbackTotalByMode('review', 'need_action');
	}

	/**
	 * Количество отзывов — опубликованные
	 */
	public function getReviewsPublishedTotal() {
		return $this->getFeedbackTotalByMode('review', 'published');
	}

	/**
	 * Количество отзывов — отклонённые
	 */
	public function getReviewsRejectedTotal() {
		return $this->getFeedbackTotalByMode('review', 'rejected');
	}

	/**
	 * Вопросы — требуют действия
	 */
	public function getQuestionsNeedAction(array $data = []) {
		return $this->getFeedbackListByMode('question', 'need_action', $data);
	}

	/**
	 * Вопросы — опубликованные
	 */
	public function getQuestionsPublished(array $data = []) {
		return $this->getFeedbackListByMode('question', 'published', $data);
	}

	/**
	 * Вопросы — отклонённые
	 */
	public function getQuestionsRejected(array $data = []) {
		return $this->getFeedbackListByMode('question', 'rejected', $data);
	}

	/**
	 * Количество вопросов — требуют действия
	 */
	public function getQuestionsNeedActionTotal() {
		return $this->getFeedbackTotalByMode('question', 'need_action');
	}

	/**
	 * Количество вопросов — опубликованные
	 */
	public function getQuestionsPublishedTotal() {
		return $this->getFeedbackTotalByMode('question', 'published');
	}

	/**
	 * Количество вопросов — отклонённые
	 */
	public function getQuestionsRejectedTotal() {
		return $this->getFeedbackTotalByMode('question', 'rejected');
	}

	/**
	 * Получить одну запись полностью
	 */
	public function getFeedbackItem($feedback_id) {
		$feedback_id = (int)$feedback_id;

		$sql = "SELECT
					f.*,
					c.firstname,
					c.lastname,
					c.email,
					p.product_id,
					p.image AS product_image,
					pd.name AS product_name,
					(
						SELECT COUNT(*)
						FROM `" . DB_PREFIX . "bm_feedback_image` fi
						WHERE fi.feedback_id = f.feedback_id
					) AS image_count
				FROM `" . DB_PREFIX . "bm_feedback` f
				LEFT JOIN `" . DB_PREFIX . "customer` c
					ON (c.customer_id = f.customer_id)
				LEFT JOIN `" . DB_PREFIX . "product` p
					ON (p.sku = f.sku)
				LEFT JOIN `" . DB_PREFIX . "product_description` pd
					ON (
						pd.product_id = p.product_id
						AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
					)
				WHERE f.feedback_id = '" . $feedback_id . "'
				LIMIT 1";

		$query = $this->db->query($sql);

		if (!$query->num_rows) {
			return null;
		}

		return $this->prepareFeedbackRow($query->row);
	}

	/**
	 * Одобрить запись
	 */
	public function approveFeedback($feedback_id) {
		$feedback_id = (int)$feedback_id;

		$query = $this->db->query("SELECT feedback_id, source_code, entity_type
			FROM `" . DB_PREFIX . "bm_feedback`
			WHERE feedback_id = '" . $feedback_id . "'
			LIMIT 1");

		if (!$query->num_rows) {
			return false;
		}

		$row = $query->row;

		if ($row['entity_type'] !== 'product') {
			return false;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "bm_feedback`
			SET moderation_status = 'approved',
				moderation_comment = NULL,
				moderated_at = NOW(),
				date_modified = NOW()
			WHERE feedback_id = '" . $feedback_id . "'");

		return true;
	}

	/**
	 * Отклонить запись с обязательным комментарием
	 */
	public function rejectFeedback($feedback_id, $comment) {
		$feedback_id = (int)$feedback_id;
		$comment = trim((string)$comment);

		if ($comment === '') {
			return false;
		}

		$comment = $this->db->escape($comment);

		$query = $this->db->query("SELECT feedback_id, entity_type
			FROM `" . DB_PREFIX . "bm_feedback`
			WHERE feedback_id = '" . $feedback_id . "'
			LIMIT 1");

		if (!$query->num_rows) {
			return false;
		}

		$row = $query->row;

		if ($row['entity_type'] !== 'product') {
			return false;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "bm_feedback`
			SET moderation_status = 'rejected',
				moderation_comment = '" . $comment . "',
				moderated_at = NOW(),
				date_modified = NOW()
			WHERE feedback_id = '" . $feedback_id . "'");

		return true;
	}

	/**
	 * Сохранить ответ магазина
	 * Разрешено только для записей с сайта
	 */
	public function saveReply($feedback_id, $reply) {
		$feedback_id = (int)$feedback_id;
		$reply = trim((string)$reply);

		if ($reply === '') {
			return false;
		}

		$reply = $this->db->escape($reply);

		$query = $this->db->query("SELECT feedback_id, source_code, moderation_status, entity_type
			FROM `" . DB_PREFIX . "bm_feedback`
			WHERE feedback_id = '" . $feedback_id . "'
			LIMIT 1");

		if (!$query->num_rows) {
			return false;
		}

		$row = $query->row;

		if ($row['entity_type'] !== 'product') {
			return false;
		}

		if (!empty($row['source_code']) && $row['source_code'] !== 'site') {
			return false;
		}

		if ($row['moderation_status'] !== 'approved') {
			return false;
		}

		$this->db->query("UPDATE `" . DB_PREFIX . "bm_feedback`
			SET admin_reply = '" . $reply . "',
				admin_reply_date_added = IF(admin_reply_date_added IS NULL, NOW(), admin_reply_date_added),
				admin_reply_date_modified = NOW(),
				date_modified = NOW()
			WHERE feedback_id = '" . $feedback_id . "'");

		return true;
	}

	/**
	 * Картинки отзыва
	 */
	public function getFeedbackImages($feedback_id) {
		$feedback_id = (int)$feedback_id;

		$query = $this->db->query("SELECT *
			FROM `" . DB_PREFIX . "bm_feedback_image`
			WHERE feedback_id = '" . $feedback_id . "'
			ORDER BY sort_order ASC, feedback_image_id ASC");

		return $query->rows;
	}

	/**
	 * Запись + покупатель для email-уведомлений
	 */
	public function getFeedbackWithCustomer($feedback_id) {
		$feedback_id = (int)$feedback_id;

		$sql = "SELECT
					f.*,
					c.firstname,
					c.lastname,
					c.email,
					p.product_id,
					pd.name AS product_name
				FROM `" . DB_PREFIX . "bm_feedback` f
				LEFT JOIN `" . DB_PREFIX . "customer` c
					ON (c.customer_id = f.customer_id)
				LEFT JOIN `" . DB_PREFIX . "product` p
					ON (p.sku = f.sku)
				LEFT JOIN `" . DB_PREFIX . "product_description` pd
					ON (
						pd.product_id = p.product_id
						AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
					)
				WHERE f.feedback_id = '" . $feedback_id . "'
				LIMIT 1";

		$query = $this->db->query($sql);

		if (!$query->num_rows) {
			return null;
		}

		return $this->prepareFeedbackRow($query->row);
	}

	/**
	 * Универсальная выборка списка по типу и режиму
	 */
	private function getFeedbackListByMode($type, $mode, array $data = []) {
		$type = $this->normalizeType($type);
		$mode = $this->normalizeMode($mode);

		$start = isset($data['start']) ? (int)$data['start'] : 0;
		$limit = isset($data['limit']) ? (int)$data['limit'] : 20;

		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 20;
		}

		$sql = "SELECT
					f.*,
					c.firstname,
					c.lastname,
					c.email,
					p.product_id,
					p.image AS product_image,
					pd.name AS product_name,
					(
						SELECT COUNT(*)
						FROM `" . DB_PREFIX . "bm_feedback_image` fi
						WHERE fi.feedback_id = f.feedback_id
					) AS image_count
				FROM `" . DB_PREFIX . "bm_feedback` f
				LEFT JOIN `" . DB_PREFIX . "customer` c
					ON (c.customer_id = f.customer_id)
				LEFT JOIN `" . DB_PREFIX . "product` p
					ON (p.sku = f.sku)
				LEFT JOIN `" . DB_PREFIX . "product_description` pd
					ON (
						pd.product_id = p.product_id
						AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
					)
				WHERE f.type = '" . $this->db->escape($type) . "'
					AND f.entity_type = 'product'
					AND " . $this->buildModeCondition('f', $mode) . "
				ORDER BY f.date_added DESC
				LIMIT " . $start . ", " . $limit;

		$query = $this->db->query($sql);
		$result = [];

		foreach ($query->rows as $row) {
			$result[] = $this->prepareFeedbackRow($row);
		}

		return $result;
	}

	/**
	 * Универсальный total по типу и режиму
	 */
	private function getFeedbackTotalByMode($type, $mode) {
		$type = $this->normalizeType($type);
		$mode = $this->normalizeMode($mode);

		$sql = "SELECT COUNT(*) AS total
				FROM `" . DB_PREFIX . "bm_feedback` f
				WHERE f.type = '" . $this->db->escape($type) . "'
					AND f.entity_type = 'product'
					AND " . $this->buildModeCondition('f', $mode);

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * Подготовка одной строки для админки
	 */
	private function prepareFeedbackRow(array $row) {
		$feedback_id = (int)$row['feedback_id'];
		$type = isset($row['type']) ? (string)$row['type'] : '';
		$source_code = isset($row['source_code']) ? (string)$row['source_code'] : '';
		$moderation_status = isset($row['moderation_status']) ? (string)$row['moderation_status'] : 'pending';
		$admin_reply = isset($row['admin_reply']) ? (string)$row['admin_reply'] : '';

		$customer_name = '';

		if (!empty($row['customer_id'])) {
			$customer_name = trim(
				(isset($row['firstname']) ? $row['firstname'] : '') . ' ' .
				(isset($row['lastname']) ? $row['lastname'] : '')
			);
		}

		if ($customer_name === '' && !empty($row['author_name'])) {
			$customer_name = (string)$row['author_name'];
		}

		$is_external = ($source_code !== '' && $source_code !== 'site');
		$is_readonly = $is_external ? 1 : 0;
		$needs_reply = ($moderation_status === 'approved' && !$is_external && $admin_reply === '');
		$needs_moderation = ($moderation_status === 'pending');

		if ($moderation_status === 'pending') {
			$status_date = !empty($row['date_modified']) ? $row['date_modified'] : $row['date_added'];
		} else {
			$status_date = !empty($row['moderated_at']) ? $row['moderated_at'] : $row['date_modified'];
		}

		return [
			'feedback_id'               => $feedback_id,
			'product_id'                => isset($row['product_id']) ? (int)$row['product_id'] : 0,
			'product_name'              => isset($row['product_name']) ? (string)$row['product_name'] : '',
			'product_image'             => isset($row['product_image']) ? (string)$row['product_image'] : '',
			'sku'                       => isset($row['sku']) ? (string)$row['sku'] : '',
			'variant_title'             => isset($row['variant_title']) ? (string)$row['variant_title'] : '',
			'customer_id'               => isset($row['customer_id']) ? (int)$row['customer_id'] : 0,
			'firstname'                 => isset($row['firstname']) ? (string)$row['firstname'] : '',
			'lastname'                  => isset($row['lastname']) ? (string)$row['lastname'] : '',
			'email'                     => isset($row['email']) ? (string)$row['email'] : '',
			'author_name'               => isset($row['author_name']) ? (string)$row['author_name'] : '',
			'customer_name'             => $customer_name,
			'type'                      => $type,
			'entity_type'               => isset($row['entity_type']) ? (string)$row['entity_type'] : 'product',
			'order_id'                  => isset($row['order_id']) ? (int)$row['order_id'] : 0,
			'external_order_ref'        => isset($row['external_order_ref']) ? (string)$row['external_order_ref'] : '',
			'source_code'               => $source_code,
			'source_url'                => isset($row['source_url']) ? (string)$row['source_url'] : '',
			'is_external'               => $is_external,
			'has_images'                => !empty($row['image_count']),
			'is_readonly'               => $is_readonly,
			'rating'                    => isset($row['rating']) && $row['rating'] !== null ? (int)$row['rating'] : null,
			'text'                      => isset($row['text']) ? (string)$row['text'] : '',
			'moderation_status'         => $moderation_status,
			'moderation_comment'        => isset($row['moderation_comment']) ? (string)$row['moderation_comment'] : '',
			'moderated_at'              => isset($row['moderated_at']) ? $row['moderated_at'] : null,
			'status_date'               => $status_date,
			'admin_reply'               => $admin_reply,
			'admin_reply_date_added'    => isset($row['admin_reply_date_added']) ? $row['admin_reply_date_added'] : null,
			'admin_reply_date_modified' => isset($row['admin_reply_date_modified']) ? $row['admin_reply_date_modified'] : null,
			'date_added'                => isset($row['date_added']) ? $row['date_added'] : null,
			'date_modified'             => isset($row['date_modified']) ? $row['date_modified'] : null,
			'needs_reply'               => $needs_reply,
			'needs_moderation'          => $needs_moderation,
			'images'                    => ($type === 'review') ? $this->getFeedbackImages($feedback_id) : []
		];
	}

	/**
	 * Условие по режиму вкладки
	 */
	private function buildModeCondition($alias, $mode) {
		$mode = $this->normalizeMode($mode);

		if ($mode === 'published') {
            return "("
                . $alias . ".moderation_status = 'approved'"
                . " AND ("
                    // либо внешний источник
                    . "("
                        . $alias . ".source_code IS NOT NULL"
                        . " AND " . $alias . ".source_code <> ''"
                        . " AND " . $alias . ".source_code <> 'site'"
                    . ")"
                    // либо есть ответ магазина
                    . " OR ("
                        . $alias . ".admin_reply IS NOT NULL"
                        . " AND " . $alias . ".admin_reply <> ''"
                    . ")"
                . ")"
            . ")";
        }

		if ($mode === 'rejected') {
			return $alias . ".moderation_status = 'rejected'";
		}

		return "("
			. $alias . ".moderation_status = 'pending'"
			. " OR ("
				. $alias . ".moderation_status = 'approved'"
				. " AND ("
					. $alias . ".source_code = 'site'"
					. " OR " . $alias . ".source_code IS NULL"
					. " OR " . $alias . ".source_code = ''"
				. ")"
				. " AND ("
					. $alias . ".admin_reply IS NULL"
					. " OR " . $alias . ".admin_reply = ''"
				. ")"
			. ")"
		. ")";
	}

	/**
	 * Нормализация типа
	 */
	private function normalizeType($type) {
		return ($type === 'question') ? 'question' : 'review';
	}

	/**
	 * Нормализация режима
	 */
	private function normalizeMode($mode) {
		$allowed = ['need_action', 'published', 'rejected'];

		return in_array($mode, $allowed, true) ? $mode : 'need_action';
	}
}