<?php
class ModelCatalogBmFeedback extends Model {

  /**
   * Получить все отзывы по товару (без ответов магазина)
   */
  public function getReviewsByProductId($sku, $customer_id = 0, $start = 0, $limit = 100) {
    $sku         = $this->db->escape((string)$sku);
    $customer_id = (int)$customer_id;
    $start       = (int)$start;
    $limit       = (int)$limit;

    if ($start < 0) {
      $start = 0;
    }

    if ($limit < 1) {
      $limit = 100;
    }

    $sql = "SELECT f.*, c.firstname, c.lastname
            FROM `" . DB_PREFIX . "bm_feedback` f
            LEFT JOIN `" . DB_PREFIX . "customer` c ON (f.customer_id = c.customer_id)
            WHERE f.sku = '" . $sku . "'
              AND f.type = 'review'
              AND f.entity_type = 'product'
              AND (
                f.moderation_status = 'approved'";

    if ($customer_id > 0) {
      $sql .= " OR (f.moderation_status = 'pending' AND f.customer_id = '" . $customer_id . "')";
    }

    $sql .= ")
            ORDER BY
              CASE
                WHEN f.moderation_status = 'pending' AND f.customer_id = '" . $customer_id . "' THEN 0
                ELSE 1
              END,
              f.rating DESC,
              f.date_added DESC
            LIMIT " . $start . ", " . $limit;

    $query  = $this->db->query($sql);
    $result = [];

    foreach ($query->rows as $row) {
      $result[] = [
        'feedback_id'               => (int)$row['feedback_id'],
        'sku'                       => $row['sku'],
        'variant_title'             => isset($row['variant_title']) ? $row['variant_title'] : '',
        'customer_id'               => (int)$row['customer_id'],
        'firstname'                 => $row['firstname'],
        'lastname'                  => $row['lastname'],
        'author_name'               => isset($row['author_name']) ? (string)$row['author_name'] : '',
        'source_code'               => isset($row['source_code']) ? (string)$row['source_code'] : '',
        'source_url'                => isset($row['source_url']) ? (string)$row['source_url'] : '',
        'type'                      => $row['type'],
        'rating'                    => is_null($row['rating']) ? null : (int)$row['rating'],
        'text'                      => $row['text'],
        'moderation_status'         => isset($row['moderation_status']) ? $row['moderation_status'] : 'pending',
        'moderation_comment'        => isset($row['moderation_comment']) ? $row['moderation_comment'] : '',
        'admin_reply'               => isset($row['admin_reply']) ? $row['admin_reply'] : '',
        'admin_reply_date_added'    => isset($row['admin_reply_date_added']) ? $row['admin_reply_date_added'] : null,
        'admin_reply_date_modified' => isset($row['admin_reply_date_modified']) ? $row['admin_reply_date_modified'] : null,
        'date_added'                => $row['date_added'],
        'date_modified'             => $row['date_modified'],
        'images'                    => $this->getFeedbackImages($row['feedback_id'])
      ];
    }

    return $result;
  }

  /**
   * Получить все вопросы по товару (без ответов магазина)
   */
  public function getQuestionsByProductId($sku, $customer_id = 0, $start = 0, $limit = 100) {
    $sku         = $this->db->escape((string)$sku);
    $customer_id = (int)$customer_id;
    $start       = (int)$start;
    $limit       = (int)$limit;

    if ($start < 0) {
      $start = 0;
    }

    if ($limit < 1) {
      $limit = 100;
    }

    $sql = "SELECT f.*, c.firstname, c.lastname
            FROM `" . DB_PREFIX . "bm_feedback` f
            LEFT JOIN `" . DB_PREFIX . "customer` c ON (f.customer_id = c.customer_id)
            WHERE f.sku = '" . $sku . "'
              AND f.type = 'question'
              AND f.entity_type = 'product'
              AND (
                f.moderation_status = 'approved'";

    if ($customer_id > 0) {
      $sql .= " OR (f.moderation_status = 'pending' AND f.customer_id = '" . $customer_id . "')";
    }

    $sql .= ")
            ORDER BY
              CASE
                WHEN f.moderation_status = 'pending' AND f.customer_id = '" . $customer_id . "' THEN 0
                ELSE 1
              END,
              f.date_added DESC
            LIMIT " . $start . ", " . $limit;

    $query  = $this->db->query($sql);
    $result = [];

    foreach ($query->rows as $row) {
      $result[] = [
        'feedback_id'               => (int)$row['feedback_id'],
        'sku'                       => $row['sku'],
        'variant_title'             => isset($row['variant_title']) ? $row['variant_title'] : '',
        'customer_id'               => (int)$row['customer_id'],
        'firstname'                 => $row['firstname'],
        'lastname'                  => $row['lastname'],
        'author_name'               => isset($row['author_name']) ? (string)$row['author_name'] : '',
        'source_code'               => isset($row['source_code']) ? (string)$row['source_code'] : '',
        'source_url'                => isset($row['source_url']) ? (string)$row['source_url'] : '',
        'type'                      => $row['type'],
        'rating'                    => is_null($row['rating']) ? null : (int)$row['rating'],
        'text'                      => $row['text'],
        'moderation_status'         => isset($row['moderation_status']) ? $row['moderation_status'] : 'pending',
        'moderation_comment'        => isset($row['moderation_comment']) ? $row['moderation_comment'] : '',
        'admin_reply'               => isset($row['admin_reply']) ? $row['admin_reply'] : '',
        'admin_reply_date_added'    => isset($row['admin_reply_date_added']) ? $row['admin_reply_date_added'] : null,
        'admin_reply_date_modified' => isset($row['admin_reply_date_modified']) ? $row['admin_reply_date_modified'] : null,
        'date_added'                => $row['date_added'],
        'date_modified'             => $row['date_modified'],
        'images'                    => $this->getFeedbackImages($row['feedback_id'])
      ];
    }

    return $result;
  }

  /**
   * Отзывы по группе вариантов (список SKU)
   */
  public function getReviewsBySkus(array $skus, $customer_id = 0, $start = 0, $limit = 100) {
    $customer_id = (int)$customer_id;
    $start       = (int)$start;
    $limit       = (int)$limit;

    if ($start < 0) {
      $start = 0;
    }

    if ($limit < 1) {
      $limit = 100;
    }

    $sku_list = [];

    foreach ($skus as $sku) {
      $sku = trim((string)$sku);

      if ($sku === '') {
        continue;
      }

      $sku_list[] = "'" . $this->db->escape($sku) . "'";
    }

    if (empty($sku_list)) {
      return [];
    }

    $sql = "SELECT f.*, c.firstname, c.lastname
            FROM `" . DB_PREFIX . "bm_feedback` f
            LEFT JOIN `" . DB_PREFIX . "customer` c ON (f.customer_id = c.customer_id)
            WHERE f.sku IN (" . implode(',', $sku_list) . ")
              AND f.type = 'review'
              AND f.entity_type = 'product'
              AND (
                f.moderation_status = 'approved'";

    if ($customer_id > 0) {
      $sql .= " OR (f.moderation_status = 'pending' AND f.customer_id = '" . $customer_id . "')";
    }

    $sql .= ")
            ORDER BY
              CASE
                WHEN f.moderation_status = 'pending' AND f.customer_id = '" . $customer_id . "' THEN 0
                ELSE 1
              END,
              f.rating DESC,
              f.date_added DESC
            LIMIT " . $start . ", " . $limit;

    $query = $this->db->query($sql);
    $result = [];

    foreach ($query->rows as $row) {
      $result[] = [
        'feedback_id'               => (int)$row['feedback_id'],
        'sku'                       => $row['sku'],
        'variant_title'             => isset($row['variant_title']) ? $row['variant_title'] : '',
        'customer_id'               => (int)$row['customer_id'],
        'firstname'                 => $row['firstname'],
        'lastname'                  => $row['lastname'],
        'author_name'               => isset($row['author_name']) ? $row['author_name'] : '',
        'source_code'               => isset($row['source_code']) ? $row['source_code'] : '',
        'source_url'                => isset($row['source_url']) ? $row['source_url'] : '',
        'type'                      => $row['type'],
        'rating'                    => is_null($row['rating']) ? null : (int)$row['rating'],
        'text'                      => $row['text'],
        'moderation_status'         => isset($row['moderation_status']) ? $row['moderation_status'] : 'pending',
        'moderation_comment'        => isset($row['moderation_comment']) ? $row['moderation_comment'] : '',
        'admin_reply'               => isset($row['admin_reply']) ? $row['admin_reply'] : '',
        'admin_reply_date_added'    => isset($row['admin_reply_date_added']) ? $row['admin_reply_date_added'] : null,
        'admin_reply_date_modified' => isset($row['admin_reply_date_modified']) ? $row['admin_reply_date_modified'] : null,
        'date_added'                => $row['date_added'],
        'date_modified'             => $row['date_modified'],
        'images'                    => $this->getFeedbackImages($row['feedback_id'])
      ];
    }

    return $result;
  }

  /**
   * Вопросы по группе вариантов (список SKU)
   */
  public function getQuestionsBySkus(array $skus, $customer_id = 0, $start = 0, $limit = 100) {
    $customer_id = (int)$customer_id;
    $start       = (int)$start;
    $limit       = (int)$limit;

    if ($start < 0) {
      $start = 0;
    }

    if ($limit < 1) {
      $limit = 100;
    }

    $sku_list = [];

    foreach ($skus as $sku) {
      $sku = trim((string)$sku);

      if ($sku === '') {
        continue;
      }

      $sku_list[] = "'" . $this->db->escape($sku) . "'";
    }

    if (empty($sku_list)) {
      return [];
    }

    $sql = "SELECT f.*, c.firstname, c.lastname
            FROM `" . DB_PREFIX . "bm_feedback` f
            LEFT JOIN `" . DB_PREFIX . "customer` c ON (f.customer_id = c.customer_id)
            WHERE f.sku IN (" . implode(',', $sku_list) . ")
              AND f.type = 'question'
              AND f.entity_type = 'product'
              AND (
                f.moderation_status = 'approved'";

    if ($customer_id > 0) {
      $sql .= " OR (f.moderation_status = 'pending' AND f.customer_id = '" . $customer_id . "')";
    }

    $sql .= ")
            ORDER BY
              CASE
                WHEN f.moderation_status = 'pending' AND f.customer_id = '" . $customer_id . "' THEN 0
                ELSE 1
              END,
              f.date_added DESC
            LIMIT " . $start . "," . $limit;

    $query = $this->db->query($sql);
    $result = [];

    foreach ($query->rows as $row) {
      $result[] = [
        'feedback_id'               => (int)$row['feedback_id'],
        'sku'                       => $row['sku'],
        'variant_title'             => isset($row['variant_title']) ? $row['variant_title'] : '',
        'customer_id'               => (int)$row['customer_id'],
        'firstname'                 => $row['firstname'],
        'lastname'                  => $row['lastname'],
        'author_name'               => isset($row['author_name']) ? $row['author_name'] : '',
        'source_code'               => isset($row['source_code']) ? $row['source_code'] : '',
        'source_url'                => isset($row['source_url']) ? $row['source_url'] : '',
        'type'                      => $row['type'],
        'rating'                    => is_null($row['rating']) ? null : (int)$row['rating'],
        'text'                      => $row['text'],
        'moderation_status'         => isset($row['moderation_status']) ? $row['moderation_status'] : 'pending',
        'moderation_comment'        => isset($row['moderation_comment']) ? $row['moderation_comment'] : '',
        'admin_reply'               => isset($row['admin_reply']) ? $row['admin_reply'] : '',
        'admin_reply_date_added'    => isset($row['admin_reply_date_added']) ? $row['admin_reply_date_added'] : null,
        'admin_reply_date_modified' => isset($row['admin_reply_date_modified']) ? $row['admin_reply_date_modified'] : null,
        'date_added'                => $row['date_added'],
        'date_modified'             => $row['date_modified'],
        'images'                    => $this->getFeedbackImages($row['feedback_id'])
      ];
    }

    return $result;
  }

    /**
   * Получить отзывы о магазине (все отзывы типа review, без ответов администратора)
   *
   * Используется для страницы /all_reviews (route: information/store_reviews)
   *
   * Важно:
   * - sku может быть пустым (например, отзывы с Avito о продавце)
   * - источник: source_code/source_url NULL для отзывов с сайта
   * - автор для внешних отзывов хранится в author_name
   */
  public function getStoreReviews($start = 0, $limit = 20) {
    $start = (int)$start;
    $limit = (int)$limit;

    if ($start < 0) {
      $start = 0;
    }

    if ($limit < 1) {
      $limit = 20;
    }

    $sql = "SELECT f.*, c.firstname, c.lastname
            FROM `" . DB_PREFIX . "bm_feedback` f
            LEFT JOIN `" . DB_PREFIX . "customer` c ON (f.customer_id = c.customer_id)
            WHERE f.type = 'review'
              AND f.moderation_status = 'approved'
            ORDER BY f.date_added DESC
            LIMIT " . $start . ", " . $limit;

    $query = $this->db->query($sql);
    $result = [];

    foreach ($query->rows as $row) {
      $result[] = [
        'feedback_id'               => (int)$row['feedback_id'],
        'sku'                       => isset($row['sku']) ? $row['sku'] : '',
        'variant_title'             => isset($row['variant_title']) ? $row['variant_title'] : '',
        'customer_id'               => (int)$row['customer_id'],
        'firstname'                 => $row['firstname'],
        'lastname'                  => $row['lastname'],
        'author_name'               => isset($row['author_name']) ? $row['author_name'] : '',
        'source_code'               => isset($row['source_code']) ? $row['source_code'] : null,
        'source_url'                => isset($row['source_url']) ? $row['source_url'] : null,
        'type'                      => $row['type'],
        'rating'                    => is_null($row['rating']) ? null : (int)$row['rating'],
        'text'                      => $row['text'],
        'moderation_status'         => isset($row['moderation_status']) ? $row['moderation_status'] : 'pending',
        'moderation_comment'        => isset($row['moderation_comment']) ? $row['moderation_comment'] : '',
        'admin_reply'               => isset($row['admin_reply']) ? $row['admin_reply'] : '',
        'admin_reply_date_added'    => isset($row['admin_reply_date_added']) ? $row['admin_reply_date_added'] : null,
        'admin_reply_date_modified' => isset($row['admin_reply_date_modified']) ? $row['admin_reply_date_modified'] : null,
        'date_added'                => $row['date_added'],
        'date_modified'             => $row['date_modified'],
        'images'                    => $this->getFeedbackImages($row['feedback_id'])
      ];
    }

    return $result;
  }

  /**
   * Количество отзывов о магазине
   */
  public function getTotalStoreReviews() {
    $query = $this->db->query("SELECT COUNT(*) AS total
                              FROM `" . DB_PREFIX . "bm_feedback`
                              WHERE type = 'review'
                                AND moderation_status = 'approved'");

    return (int)$query->row['total'];
  }

  /**
   * Проверить, есть ли уже отзыв пользователя по товару
   */
  public function getReviewByProductAndCustomer($sku, $customer_id) {
    $sku         = $this->db->escape((string)$sku);
    $customer_id = (int)$customer_id;

    $sql = "SELECT *
            FROM `" . DB_PREFIX . "bm_feedback`
            WHERE sku = '" . $sku . "'
              AND customer_id = '" . $customer_id . "'
              AND type = 'review'
              AND entity_type = 'product'
              AND moderation_status IN ('pending', 'approved')
            ORDER BY
              CASE
                WHEN moderation_status = 'pending' THEN 0
                ELSE 1
              END,
              feedback_id DESC
            LIMIT 1";

    $query = $this->db->query($sql);

    if ($query->num_rows) {
      $row = $query->row;

      return [
        'feedback_id'               => (int)$row['feedback_id'],
        'sku'                       => $row['sku'],
        'customer_id'               => (int)$row['customer_id'],
        'firstname'                 => $row['firstname'] ?? '',
        'lastname'                  => $row['lastname'] ?? '',
        'type'                      => $row['type'],
        'rating'                    => is_null($row['rating']) ? null : (int)$row['rating'],
        'text'                      => $row['text'],
        'variant_title'             => isset($row['variant_title']) ? $row['variant_title'] : '',
        'moderation_status'         => isset($row['moderation_status']) ? $row['moderation_status'] : 'pending',
        'moderation_comment'        => isset($row['moderation_comment']) ? $row['moderation_comment'] : '',
        'admin_reply'               => isset($row['admin_reply']) ? $row['admin_reply'] : '',
        'admin_reply_date_added'    => isset($row['admin_reply_date_added']) ? $row['admin_reply_date_added'] : null,
        'admin_reply_date_modified' => isset($row['admin_reply_date_modified']) ? $row['admin_reply_date_modified'] : null,
        'date_added'                => $row['date_added'],
        'date_modified'             => $row['date_modified'],
        'images'                    => $this->getFeedbackImages($row['feedback_id']),
      ];
    }

    return null;
  }

  /**
   * Создать или обновить отзыв пользователя по товару
   * Логика: один отзыв на товар, рейтинг можно только повысить
   */
  public function saveReview($sku, $customer_id, array $data) {
    $sku         = $this->db->escape((string)$sku);
    $customer_id = (int)$customer_id;

    $text   = isset($data['text']) ? $this->db->escape($data['text']) : '';
    $rating = isset($data['rating']) ? (int)$data['rating'] : null;

    if ($rating !== null) {
      if ($rating < 1) {
        $rating = 1;
      }

      if ($rating > 5) {
        $rating = 5;
      }
    }

    $variant_title = isset($data['variant_title'])
      ? $this->db->escape($data['variant_title'])
      : '';

    $existing = $this->getReviewByProductAndCustomer($sku, $customer_id);

    // Отзыв уже существует — больше не редактируем и не обновляем
    if ($existing) {
      return 0;
    }

    $this->db->query("INSERT INTO `" . DB_PREFIX . "bm_feedback`
                      SET sku = '" . $sku . "',
                          customer_id = '" . $customer_id . "',
                          source_code = 'site',
                          source_url = NULL,
                          type = 'review',
                          entity_type = 'product',
                          order_id = NULL,
                          rating = " . (!is_null($rating) ? "'" . (int)$rating . "'" : "NULL") . ",
                          text = '" . $text . "',
                          variant_title = '" . $variant_title . "',
                          moderation_status = 'pending',
                          moderation_comment = NULL,
                          moderated_at = NULL,
                          admin_reply = NULL,
                          admin_reply_date_added = NULL,
                          admin_reply_date_modified = NULL,
                          date_added = NOW(),
                          date_modified = NOW()");

    $feedback_id = (int)$this->db->getLastId();

    $this->sendAdminFeedbackNotification($feedback_id);

    return $feedback_id;
  }

  /**
   * Добавить вопрос пользователя
   */
  public function addQuestion($sku, $customer_id, array $data) {
    $sku         = $this->db->escape((string)$sku);
    $customer_id = (int)$customer_id;

    $text = isset($data['text']) ? $this->db->escape($data['text']) : '';
    $variant_title = isset($data['variant_title'])
      ? $this->db->escape($data['variant_title'])
      : '';

    $this->db->query("INSERT INTO `" . DB_PREFIX . "bm_feedback`
                      SET sku = '" . $sku . "',
                          customer_id = '" . $customer_id . "',
                          source_code = 'site',
                          source_url = NULL,
                          type = 'question',
                          entity_type = 'product',
                          order_id = NULL,
                          rating = NULL,
                          text = '" . $text . "',
                          variant_title = '" . $variant_title . "',
                          moderation_status = 'pending',
                          moderation_comment = NULL,
                          moderated_at = NULL,
                          admin_reply = NULL,
                          admin_reply_date_added = NULL,
                          admin_reply_date_modified = NULL,
                          date_added = NOW(),
                          date_modified = NOW()");

    $feedback_id = (int)$this->db->getLastId();

    $this->sendAdminFeedbackNotification($feedback_id);

    return $feedback_id;
  }

  /**
   * Добавить ответ магазина (будем использовать после появления админ-УЗ)
   */
  public function addAdminReply($parent_id, $text) {
    $parent_id = (int)$parent_id;
    $text      = $this->db->escape($text);

    $query = $this->db->query("SELECT feedback_id, source_code
                              FROM `" . DB_PREFIX . "bm_feedback`
                              WHERE feedback_id = '" . $parent_id . "'
                              LIMIT 1");

    if (!$query->num_rows) {
      return false;
    }

    $row = $query->row;

    if (!empty($row['source_code']) && $row['source_code'] !== 'site') {
      return false;
    }

    $this->db->query("UPDATE `" . DB_PREFIX . "bm_feedback`
                      SET admin_reply = '" . $text . "',
                          admin_reply_date_added = IF(admin_reply_date_added IS NULL, NOW(), admin_reply_date_added),
                          admin_reply_date_modified = NOW(),
                          date_modified = NOW()
                      WHERE feedback_id = '" . $parent_id . "'");

    return true;
  }
  /**
   * Получить ответ магазина к отзыву/вопросу (если есть)
   */
  public function getAdminReply($feedback_id) {
    $feedback_id = (int)$feedback_id;

    $query = $this->db->query("SELECT admin_reply, admin_reply_date_added, admin_reply_date_modified
                              FROM `" . DB_PREFIX . "bm_feedback`
                              WHERE feedback_id = '" . $feedback_id . "'
                              LIMIT 1");

    if ($query->num_rows && !empty($query->row['admin_reply'])) {
      return [
        'text'          => $query->row['admin_reply'],
        'date_added'    => $query->row['admin_reply_date_added'],
        'date_modified' => $query->row['admin_reply_date_modified']
      ];
    }

    return null;
  }

  /**
   * Фото для отзыва/вопроса
   */
  public function getFeedbackImages($feedback_id) {
    $feedback_id = (int)$feedback_id;

    $query = $this->db->query("SELECT *
                              FROM `" . DB_PREFIX . "bm_feedback_image`
                              WHERE feedback_id = '" . $feedback_id . "'
                              ORDER BY sort_order ASC, feedback_image_id ASC");

    if (!$query->num_rows) {
      return [];
    }

    $this->load->model('tool/image');

    $result = [];

    foreach ($query->rows as $row) {
      $image_file = '';

      if (!empty($row['image'])) {
        $image_file = (string)$row['image'];
      } elseif (!empty($row['image_path'])) {
        $image_file = (string)$row['image_path'];
      } elseif (!empty($row['path'])) {
        $image_file = (string)$row['path'];
      } elseif (!empty($row['filename'])) {
        $image_file = (string)$row['filename'];
      } elseif (!empty($row['file'])) {
        $image_file = (string)$row['file'];
      }

      $image_file = trim($image_file);

      if ($image_file === '') {
        continue;
      }

      $absolute_path = DIR_IMAGE . ltrim($image_file, '/');

      if (!is_file($absolute_path)) {
        continue;
      }

      $result[] = [
        // сохраняем исходные поля для совместимости
        'feedback_image_id' => isset($row['feedback_image_id']) ? (int)$row['feedback_image_id'] : 0,
        'sort_order'        => isset($row['sort_order']) ? (int)$row['sort_order'] : 0,
        'image'             => $image_file,
        'image_path'        => $image_file,
        'path'              => $image_file,
        'filename'          => basename($image_file),
        'file'              => $image_file,

        // добавляем уже подготовленные поля для новых шаблонов
        'thumb'             => $this->model_tool_image->resize($image_file, 120, 120),
        'popup'             => 'image/' . ltrim($image_file, '/'),
      ];
    }

    return $result;
  }

  public function addFeedbackImages($feedback_id, $sku, array $files) {
    $feedback_id = (int)$feedback_id;
    $sku         = trim((string)$sku);

    if ($feedback_id <= 0 || empty($files)) {
      return;
    }

    $directory = DIR_IMAGE . 'catalog/reviews_photos/site/';

    if (!is_dir($directory)) {
      mkdir($directory, 0775, true);
    }

    $sku_safe = preg_replace('/[^A-Za-z0-9\-_]+/u', '-', $sku);
    $sku_safe = trim($sku_safe, '-_');

    if ($sku_safe === '') {
      $sku_safe = 'review';
    }

    $feedback_part = str_pad((string)$feedback_id, 6, '0', STR_PAD_LEFT);
    $sort_order    = 0;

    foreach ($files as $index => $file) {
      if (empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
        continue;
      }

      $original_name = isset($file['name']) ? (string)$file['name'] : '';
      $extension     = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

      if ($extension === '') {
        $mime = '';
        if (function_exists('mime_content_type')) {
          $mime = (string)@mime_content_type($file['tmp_name']);
        }

        $mime_map = [
          'image/jpeg' => 'jpg',
          'image/pjpeg' => 'jpg',
          'image/png'  => 'png',
          'image/gif'  => 'gif',
          'image/webp' => 'webp'
        ];

        if ($mime && isset($mime_map[$mime])) {
          $extension = $mime_map[$mime];
        } else {
          $extension = 'jpg';
        }
      }

      $file_number   = $index + 1;
      $filename      = $sku_safe . '-' . $feedback_part . '_' . $file_number . '.' . $extension;
      $relative_path = 'catalog/reviews_photos/site/' . $filename;
      $absolute_path = DIR_IMAGE . $relative_path;

      if (!@move_uploaded_file($file['tmp_name'], $absolute_path)) {
        if (!@rename($file['tmp_name'], $absolute_path)) {
          continue;
        }
      }

      @chmod($absolute_path, 0644);

      $this->db->query("INSERT INTO `" . DB_PREFIX . "bm_feedback_image`
                        SET feedback_id = '" . $feedback_id . "',
                            image = '" . $this->db->escape($relative_path) . "',
                            sort_order = '" . (int)$sort_order . "'");

      $sort_order++;
    }
  }

  /**
  * Обновить ответ администратора
  */
  public function updateAdminReply($feedback_id, $text) {
    $feedback_id = (int)$feedback_id;
    $text        = $this->db->escape($text);

    $query = $this->db->query("SELECT source_code
                              FROM `" . DB_PREFIX . "bm_feedback`
                              WHERE feedback_id = '" . $feedback_id . "'
                              LIMIT 1");

    if (!$query->num_rows) {
      return false;
    }

    $row = $query->row;

    if (!empty($row['source_code']) && $row['source_code'] !== 'site') {
      return false;
    }

    $this->db->query("UPDATE `" . DB_PREFIX . "bm_feedback`
                      SET admin_reply = '" . $text . "',
                          admin_reply_date_modified = NOW(),
                          date_modified = NOW()
                      WHERE feedback_id = '" . $feedback_id . "'");

    return true;
  }

  public function getAdminReplyId($parent_id) {
    return 0;
  }

    /**
   * Получить отзыв/вопрос с данными покупателя для уведомления
   */
  public function getFeedbackWithCustomer($feedback_id) {
    $feedback_id = (int)$feedback_id;

    $query = $this->db->query("
      SELECT bf.*,
             c.email,
             c.firstname,
             c.lastname
      FROM `" . DB_PREFIX . "bm_feedback` bf
      LEFT JOIN `" . DB_PREFIX . "customer` c
        ON (bf.customer_id = c.customer_id)
      WHERE bf.feedback_id = '" . $feedback_id . "'
      LIMIT 1
    ");

    if ($query->num_rows) {
      return $query->row;
    }

    return null;
  }

  public function getAdminFeedback(array $data = []) {
    $tab   = !empty($data['tab']) ? $data['tab'] : 'need_answer';
    $start = isset($data['start']) ? (int)$data['start'] : 0;
    $limit = isset($data['limit']) ? (int)$data['limit'] : 10;

    if ($start < 0) {
      $start = 0;
    }

    if ($limit < 1) {
      $limit = 10;
    }

    $sql = "SELECT
              f.feedback_id,
              f.sku,
              f.variant_title,
              f.customer_id,
              f.author_name,
              f.source_code,
              f.source_url,
              f.type,
              CASE WHEN f.type = 'question' THEN 1 ELSE 0 END AS is_question,
              f.rating,
              f.text,
              f.date_added,
              f.moderation_status,
              f.moderation_comment,
              f.admin_reply,
              f.admin_reply_date_added AS date_answered,
              '' AS admin_name,
              p.product_id,
              pd.name AS product_name,
              CASE
                WHEN f.customer_id IS NULL OR f.customer_id = 0 THEN f.author_name
                ELSE CONCAT_WS(' ', c.firstname, c.lastname)
              END AS customer_name
            FROM `" . DB_PREFIX . "bm_feedback` f
            LEFT JOIN `" . DB_PREFIX . "customer` c
              ON (c.customer_id = f.customer_id)
            LEFT JOIN `" . DB_PREFIX . "product` p
              ON (p.sku = f.sku)
            LEFT JOIN `" . DB_PREFIX . "product_description` pd
              ON (pd.product_id = p.product_id
              AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "')
            WHERE f.entity_type = 'product'";

    if ($tab === 'need_answer') {
      $sql .= " AND (
                  f.moderation_status = 'pending'
                  OR (
                    f.moderation_status = 'approved'
                    AND (f.source_code = 'site' OR f.source_code IS NULL OR f.source_code = '')
                    AND (f.admin_reply IS NULL OR f.admin_reply = '')
                  )
                )";
    } else {
      $sql .= " AND (
                  f.moderation_status = 'rejected'
                  OR (
                    f.moderation_status = 'approved'
                    AND (
                      (f.source_code IS NOT NULL AND f.source_code <> '' AND f.source_code <> 'site')
                      OR (f.admin_reply IS NOT NULL AND f.admin_reply <> '')
                    )
                  )
                )";
    }

    $sql .= " ORDER BY f.date_added DESC";
    $sql .= " LIMIT " . (int)$start . "," . (int)$limit;

    $query = $this->db->query($sql);

    return $query->rows;
  }

  public function getTotalAdminFeedback(array $data = []) {
    $tab = !empty($data['tab']) ? $data['tab'] : 'need_answer';

    $sql = "SELECT COUNT(*) AS total
            FROM `" . DB_PREFIX . "bm_feedback` f
            WHERE f.entity_type = 'product'";

    if ($tab === 'need_answer') {
      $sql .= " AND (
                  f.moderation_status = 'pending'
                  OR (
                    f.moderation_status = 'approved'
                    AND (f.source_code = 'site' OR f.source_code IS NULL OR f.source_code = '')
                    AND (f.admin_reply IS NULL OR f.admin_reply = '')
                  )
                )";
    } else {
      $sql .= " AND (
                  f.moderation_status = 'rejected'
                  OR (
                    f.moderation_status = 'approved'
                    AND (
                      (f.source_code IS NOT NULL AND f.source_code <> '' AND f.source_code <> 'site')
                      OR (f.admin_reply IS NOT NULL AND f.admin_reply <> '')
                    )
                  )
                )";
    }

    $query = $this->db->query($sql);

    return (int)$query->row['total'];
  }

    /**
   * Отправить уведомление админу о новом отзыве / вопросе с сайта
   */
  private function sendAdminFeedbackNotification($feedback_id) {
    $feedback_id = (int)$feedback_id;

    if ($feedback_id <= 0) {
      return false;
    }

    $feedback = $this->getFeedbackForAdminNotification($feedback_id);

    if (!$feedback) {
      return false;
    }

    // Уведомляем только о новых записях с сайта
    if (!isset($feedback['source_code']) || $feedback['source_code'] !== 'site') {
      return false;
    }

    $to = trim((string)$this->config->get('config_email'));

    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
      return false;
    }

    $type = isset($feedback['type']) ? (string)$feedback['type'] : '';

    if ($type === 'question') {
      $subject = 'Получен вопрос по товару';
    } elseif ($type === 'review') {
      $subject = 'Получен отзыв по товару';
    } else {
      return false;
    }

    $product_name = !empty($feedback['product_name'])
      ? htmlspecialchars_decode($feedback['product_name'], ENT_QUOTES)
      : 'Товар не найден';

    $sku = !empty($feedback['sku']) ? $feedback['sku'] : '-';

    $author_name = trim(
      (isset($feedback['firstname']) ? $feedback['firstname'] : '') . ' ' .
      (isset($feedback['lastname']) ? $feedback['lastname'] : '')
    );

    if ($author_name === '') {
      $author_name = !empty($feedback['author_name']) ? $feedback['author_name'] : 'Не указан';
    }

    $customer_email = !empty($feedback['email']) ? $feedback['email'] : '';
    $variant_title  = !empty($feedback['variant_title']) ? $feedback['variant_title'] : '';
    $text           = !empty($feedback['text']) ? nl2br(htmlspecialchars($feedback['text'], ENT_QUOTES, 'UTF-8')) : '-';

    $message  = '<html><body style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5;color:#222;">';

    if ($type === 'question') {
      $message .= '<p>На сайте получен новый вопрос по товару.</p>';
    } else {
      $message .= '<p>На сайте получен новый отзыв по товару.</p>';
    }

    $message .= '<p><strong>Товар:</strong> ' . $product_name . '</p>';
    $message .= '<p><strong>Артикул:</strong> ' . htmlspecialchars($sku, ENT_QUOTES, 'UTF-8') . '</p>';

    if ($variant_title !== '') {
      $message .= '<p><strong>Вариант:</strong> ' . htmlspecialchars($variant_title, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $message .= '<p><strong>Автор:</strong> ' . htmlspecialchars($author_name, ENT_QUOTES, 'UTF-8') . '</p>';

    if ($customer_email !== '') {
      $message .= '<p><strong>Email:</strong> ' . htmlspecialchars($customer_email, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    if ($type === 'review' && isset($feedback['rating']) && $feedback['rating'] !== null) {
      $message .= '<p><strong>Оценка:</strong> ' . (int)$feedback['rating'] . '/5</p>';
      $message .= '<p><strong>Текст отзыва:</strong><br>' . $text . '</p>';
    } else {
      $message .= '<p><strong>Текст вопроса:</strong><br>' . $text . '</p>';
    }

    $message .= '</body></html>';

    return $this->sendAdminNotificationMail($to, $subject, $message);
  }

  /**
   * Получить запись с данными товара и покупателя для уведомления админу
   */
  private function getFeedbackForAdminNotification($feedback_id) {
    $feedback_id = (int)$feedback_id;

    $query = $this->db->query("
      SELECT
        bf.*,
        c.email,
        c.firstname,
        c.lastname,
        pd.name AS product_name
      FROM `" . DB_PREFIX . "bm_feedback` bf
      LEFT JOIN `" . DB_PREFIX . "customer` c
        ON (bf.customer_id = c.customer_id)
      LEFT JOIN `" . DB_PREFIX . "product` p
        ON (p.sku = bf.sku)
      LEFT JOIN `" . DB_PREFIX . "product_description` pd
        ON (
          pd.product_id = p.product_id
          AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
        )
      WHERE bf.feedback_id = '" . $feedback_id . "'
      LIMIT 1
    ");

    if ($query->num_rows) {
      return $query->row;
    }

    return null;
  }

  /**
   * Отправка email-уведомления админу
   */
  private function sendAdminNotificationMail($to, $subject, $message) {
    try {
      $mail = new Mail($this->config->get('config_mail_engine'));
      $mail->parameter = $this->config->get('config_mail_parameter');
      $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
      $mail->smtp_username = $this->config->get('config_mail_smtp_username');
      $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
      $mail->smtp_port = $this->config->get('config_mail_smtp_port');
      $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

      $mail->setTo($to);
      $mail->setFrom($this->config->get('config_email'));
      $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
      $mail->setSubject($subject);
      $mail->setText(trim(html_entity_decode(strip_tags(str_replace(array('<br>', '<br/>', '<br />', '</p>'), array("\n", "\n", "\n", "</p>\n"), $message)), ENT_QUOTES, 'UTF-8')));
      $mail->setHtml($message);
      $mail->send();

      return true;
    } catch (Exception $e) {
      return false;
    } catch (Throwable $e) {
      return false;
    }
  }


}
