<?php
class ModelCatalogBmFeedback extends Model {

  /**
   * Получить все отзывы по товару (без ответов магазина)
   */
  public function getReviewsByProductId($sku, $start = 0, $limit = 100) {
    $sku   = $this->db->escape((string)$sku);
    $start = (int)$start;
    $limit = (int)$limit;

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
              AND f.parent_id = 0
              AND f.is_admin_reply = 0
            ORDER BY f.rating DESC, f.date_added DESC
            LIMIT " . $start . ", " . $limit;

    $query  = $this->db->query($sql);
    $result = [];

    foreach ($query->rows as $row) {
      $display_date = (!empty($row['date_modified']) && $row['date_modified'] !== '0000-00-00 00:00:00')
        ? $row['date_modified']
        : $row['date_added'];

      $result[] = [
        'feedback_id'    => (int)$row['feedback_id'],
        'sku'            => $row['sku'],
        'variant_title'  => isset($row['variant_title']) ? $row['variant_title'] : '',
        'customer_id'    => (int)$row['customer_id'],
        'firstname'      => $row['firstname'],
        'lastname'       => $row['lastname'],

        // если customer_id = 0, имя берём отсюда (заполняется вручную/импортом)
        'author_name'    => isset($row['author_name']) ? (string)$row['author_name'] : '',

        // источник (для сторонних отзывов)
        'source_code'    => isset($row['source_code']) ? (string)$row['source_code'] : '',
        'source_url'     => isset($row['source_url']) ? (string)$row['source_url'] : '',

        'type'           => $row['type'],
        'rating'         => is_null($row['rating']) ? null : (int)$row['rating'],
        'text'           => $row['text'],
        'parent_id'      => (int)$row['parent_id'],
        'is_admin_reply' => (int)$row['is_admin_reply'],
        'date_added'     => $row['date_added'],
        'date_modified'  => $row['date_modified'],
        'images'         => $this->getFeedbackImages($row['feedback_id']),
        'admin_reply'    => $this->getAdminReply($row['feedback_id'])
      ];
    }

    return $result;
  }

  /**
   * Получить все вопросы по товару (без ответов магазина)
   */
  public function getQuestionsByProductId($sku, $start = 0, $limit = 100) {
    $sku   = $this->db->escape((string)$sku);
    $start = (int)$start;
    $limit = (int)$limit;

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
              AND f.parent_id = 0
              AND f.is_admin_reply = 0
            ORDER BY f.date_added DESC
            LIMIT " . $start . ", " . $limit;

    $query  = $this->db->query($sql);
    $result = [];

    foreach ($query->rows as $row) {
      $result[] = [
        'feedback_id'    => (int)$row['feedback_id'],
        'sku'            => $row['sku'],
        'variant_title'  => isset($row['variant_title']) ? $row['variant_title'] : '',
        'customer_id'    => (int)$row['customer_id'],
        'firstname'      => $row['firstname'],
        'lastname'       => $row['lastname'],
        'type'           => $row['type'],
        'rating'         => is_null($row['rating']) ? null : (int)$row['rating'],
        'text'           => $row['text'],
        'parent_id'      => (int)$row['parent_id'],
        'is_admin_reply' => (int)$row['is_admin_reply'],
        'date_added'     => $row['date_added'],
        'date_modified'  => $row['date_modified'],
        'images'         => $this->getFeedbackImages($row['feedback_id']),
        'admin_reply'    => $this->getAdminReply($row['feedback_id'])
      ];
    }

    return $result;
  }

  /**
   * Отзывы по группе вариантов (список SKU)
   */
  public function getReviewsBySkus(array $skus, $start = 0, $limit = 100) {
    $start = (int)$start;
    $limit = (int)$limit;

    if ($start < 0) {
      $start = 0;
    }

    if ($limit < 1) {
      $limit = 100;
    }

    // Нормализуем и экранируем список SKU
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
              AND f.parent_id = 0
              AND f.is_admin_reply = 0
            ORDER BY f.rating DESC, f.date_added DESC
            LIMIT " . $start . "," . $limit;

    $query = $this->db->query($sql);

    $result = [];

    foreach ($query->rows as $row) {
      $result[] = [
        'feedback_id'   => (int)$row['feedback_id'],
        'sku'           => $row['sku'],                             // привязка к варианту
        'variant_title' => isset($row['variant_title']) ? $row['variant_title'] : '',
        'customer_id'   => (int)$row['customer_id'],
        'firstname'     => $row['firstname'],
        'lastname'      => $row['lastname'],

        // для внешних источников (и вообще — если customer_id пуст)
        'author_name'   => isset($row['author_name']) ? $row['author_name'] : '',

        // источник отзыва
        'source_code'   => isset($row['source_code']) ? $row['source_code'] : '',
        'source_url'    => isset($row['source_url']) ? $row['source_url'] : '',

        'type'          => $row['type'],
        'rating'        => is_null($row['rating']) ? null : (int)$row['rating'],
        'text'          => $row['text'],
        'parent_id'     => (int)$row['parent_id'],
        'is_admin_reply'=> (int)$row['is_admin_reply'],
        'date_added'    => $row['date_added'],
        'date_modified' => $row['date_modified'],
        'images'        => $this->getFeedbackImages($row['feedback_id']),
        'admin_reply'   => $this->getAdminReply($row['feedback_id'])
      ];
    }

    return $result;
  }

  /**
   * Вопросы по группе вариантов (список SKU)
   */
  public function getQuestionsBySkus(array $skus, $start = 0, $limit = 100) {
    $start = (int)$start;
    $limit = (int)$limit;

    if ($start < 0) {
      $start = 0;
    }

    if ($limit < 1) {
      $limit = 100;
    }

    // Нормализуем и экранируем список SKU
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
              AND f.parent_id = 0
              AND f.is_admin_reply = 0
            ORDER BY f.date_added DESC
            LIMIT " . $start . "," . $limit;

    $query = $this->db->query($sql);

    $result = [];

    foreach ($query->rows as $row) {
      $result[] = [
        'feedback_id'   => (int)$row['feedback_id'],
        'sku'           => $row['sku'],
        'variant_title' => isset($row['variant_title']) ? $row['variant_title'] : '',
        'customer_id'   => (int)$row['customer_id'],
        'firstname'     => $row['firstname'],
        'lastname'      => $row['lastname'],
        'type'          => $row['type'],
        'rating'        => is_null($row['rating']) ? null : (int)$row['rating'],
        'text'          => $row['text'],
        'parent_id'     => (int)$row['parent_id'],
        'is_admin_reply'=> (int)$row['is_admin_reply'],
        'date_added'    => $row['date_added'],
        'date_modified' => $row['date_modified'],
        'images'        => $this->getFeedbackImages($row['feedback_id']),
        'admin_reply'   => $this->getAdminReply($row['feedback_id'])
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
              AND f.parent_id = 0
              AND f.is_admin_reply = 0
            ORDER BY f.date_added DESC
            LIMIT " . $start . ", " . $limit;

    $query = $this->db->query($sql);
    $result = [];

    foreach ($query->rows as $row) {
      $result[] = [
        'feedback_id'    => (int)$row['feedback_id'],
        'sku'            => isset($row['sku']) ? $row['sku'] : '',
        'variant_title'  => isset($row['variant_title']) ? $row['variant_title'] : '',
        'customer_id'    => (int)$row['customer_id'],
        'firstname'      => $row['firstname'],
        'lastname'       => $row['lastname'],
        'author_name'    => isset($row['author_name']) ? $row['author_name'] : '',
        'source_code'    => isset($row['source_code']) ? $row['source_code'] : null,
        'source_url'     => isset($row['source_url']) ? $row['source_url'] : null,
        'type'           => $row['type'],
        'rating'         => is_null($row['rating']) ? null : (int)$row['rating'],
        'text'           => $row['text'],
        'date_added'     => $row['date_added'],
        'date_modified'  => $row['date_modified'],
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
                                AND parent_id = 0
                                AND is_admin_reply = 0");

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
              AND parent_id = 0
              AND is_admin_reply = 0
            LIMIT 1";

    $query = $this->db->query($sql);

    if ($query->num_rows) {
      $row = $query->row;

      return [
        'feedback_id'   => (int)$row['feedback_id'],
        'sku'           => $row['sku'],
        'customer_id'   => (int)$row['customer_id'],
        'firstname'     => $row['firstname'] ?? '',
        'lastname'      => $row['lastname'] ?? '',
        'type'          => $row['type'],
        'rating'        => is_null($row['rating']) ? null : (int)$row['rating'],
        'text'          => $row['text'],
        'variant_title' => isset($row['variant_title']) ? $row['variant_title'] : '',
        'parent_id'     => (int)$row['parent_id'],
        'is_admin_reply'=> (int)$row['is_admin_reply'],
        'date_added'    => $row['date_added'],
        'date_modified' => $row['date_modified'],
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
      if ($rating < 1) $rating = 1;
      if ($rating > 5) $rating = 5;
    }

    $variant_title = isset($data['variant_title'])
      ? $this->db->escape($data['variant_title'])
      : '';

    // Ищем существующий отзыв этого покупателя по этому SKU
    $existing = $this->getReviewByProductAndCustomer($sku, $customer_id);

    if ($existing) {
      // Обновляем: рейтинг можно только повысить
      $new_rating = $rating;
      if (!is_null($existing['rating']) && !is_null($rating)) {
        if ($rating < $existing['rating']) {
          $new_rating = $existing['rating'];
        }
      }

      $this->db->query("UPDATE `" . DB_PREFIX . "bm_feedback`
                        SET text = '" . $text . "',
                            rating = " . (!is_null($new_rating) ? "'" . (int)$new_rating . "'" : "NULL") . ",
                            variant_title = '" . $variant_title . "',
                            date_modified = NOW()
                        WHERE feedback_id = '" . (int)$existing['feedback_id'] . "'");

      return (int)$existing['feedback_id'];
    } else {
      // Создаём новый отзыв
      $this->db->query("INSERT INTO `" . DB_PREFIX . "bm_feedback`
                        SET sku = '" . $sku . "',
                            customer_id = '" . $customer_id . "',
                            type = 'review',
                            rating = " . (!is_null($rating) ? "'" . (int)$rating . "'" : "NULL") . ",
                            text = '" . $text . "',
                            variant_title = '" . $variant_title . "',
                            parent_id = 0,
                            is_admin_reply = 0,
                            date_added = NOW(),
                            date_modified = NOW()");

      return (int)$this->db->getLastId();
    }
  }

  /**
   * Добавить вопрос пользователя
   */
  public function addQuestion($sku, $customer_id, array $data) {
    $sku         = $this->db->escape((string)$sku);
    $customer_id = (int)$customer_id;

    $text          = isset($data['text']) ? $this->db->escape($data['text']) : '';
    $variant_title = isset($data['variant_title'])
      ? $this->db->escape($data['variant_title'])
      : '';

    $this->db->query("INSERT INTO `" . DB_PREFIX . "bm_feedback`
                      SET sku = '" . $sku . "',
                          customer_id = '" . $customer_id . "',
                          type = 'question',
                          rating = NULL,
                          text = '" . $text . "',
                          variant_title = '" . $variant_title . "',
                          parent_id = 0,
                          is_admin_reply = 0,
                          date_added = NOW(),
                          date_modified = NOW()");

    return (int)$this->db->getLastId();
  }

  /**
   * Добавить ответ магазина (будем использовать после появления админ-УЗ)
   */
 /* public function addAdminReply($parent_id, $admin_customer_id, $text) {
    $parent_id         = (int)$parent_id;
    $admin_customer_id = (int)$admin_customer_id;
    $text              = $this->db->escape($text);

    // Определяем sku и тип родительской записи (review/question)
    $query = $this->db->query("SELECT sku, type FROM `" . DB_PREFIX . "bm_feedback`
                              WHERE feedback_id = '" . $parent_id . "'
                              LIMIT 1");

    if (!$query->num_rows) {
      return 0;
    }

    $sku  = $this->db->escape($query->row['sku']);
    $type = $query->row['type'];

    // Удаляем предыдущий ответ админа, если был
    $this->db->query("DELETE FROM `" . DB_PREFIX . "bm_feedback`
                      WHERE parent_id = '" . $parent_id . "'
                        AND is_admin_reply = 1");

    // Добавляем новый ответ
    $this->db->query("INSERT INTO `" . DB_PREFIX . "bm_feedback`
                      SET sku = '" . $sku . "',
                          customer_id = '" . $admin_customer_id . "',
                          type = '" . $this->db->escape($type) . "',
                          rating = NULL,
                          text = '" . $text . "',
                          parent_id = '" . $parent_id . "',
                          is_admin_reply = 1,
                          date_added = NOW(),
                          date_modified = NOW()");

    return (int)$this->db->getLastId();
  }
 */
  /**
   * Получить ответ магазина к отзыву/вопросу (если есть)
   */
  public function getAdminReply($parent_id) {
    $parent_id = (int)$parent_id;

    $sql = "SELECT f.*, c.firstname, c.lastname
            FROM `" . DB_PREFIX . "bm_feedback` f
            LEFT JOIN `" . DB_PREFIX . "customer` c ON (f.customer_id = c.customer_id)
            WHERE f.parent_id = '" . $parent_id . "'
              AND f.is_admin_reply = 1
            ORDER BY f.date_added ASC
            LIMIT 1";

    $query = $this->db->query($sql);

    if ($query->num_rows) {
      $row = $query->row;

      return [
        'feedback_id' => (int)$row['feedback_id'],
        'customer_id' => (int)$row['customer_id'],
        'firstname'   => $row['firstname'],
        'lastname'    => $row['lastname'],
        'type'        => $row['type'],
        'text'        => $row['text'],
        'date_added'  => $row['date_added'],
        'date_modified' => $row['date_modified']
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

    return $query->rows;
  }

 /**
  * Добавить ответ администратора
  */
  public function addAdminReply($parent_id, $text) {
    $parent_id = (int)$parent_id;
    $text      = $this->db->escape($text);

    // Получаем родительский отзыв/вопрос
    $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "bm_feedback`
                               WHERE feedback_id = '" . $parent_id . "' LIMIT 1");

    if (!$query->num_rows) {
      return false;
    }

    $parent = $query->row;

    $sku           = $this->db->escape($parent['sku']);
    $variant_title = $this->db->escape($parent['variant_title']);
    $type          = $this->db->escape($parent['type']); // review / question

    // Админ отвечает (customer_id=0 — твой вариант)
    $admin_id = 1;

    // Добавляем новую запись — ответ
    $this->db->query("
      INSERT INTO `" . DB_PREFIX . "bm_feedback`
      SET
        `sku`           = '{$sku}',
        `variant_title` = '{$variant_title}',
        `customer_id`   = '{$admin_id}',
        `type`          = '{$type}',
        `rating`        = NULL,
        `text`          = '{$text}',
        `parent_id`     = '{$parent_id}',
        `is_admin_reply`= 1,
        `date_added`    = NOW(),
        `date_modified` = NOW()
    ");

    // Обновляем дату родительского отзыва
    $this->db->query("
      UPDATE `" . DB_PREFIX . "bm_feedback`
      SET date_modified = NOW()
      WHERE feedback_id = '{$parent_id}'
    ");

    return true;
  }

  /**
  * Обновить ответ администратора
  */
  public function updateAdminReply($reply_id, $text) {
    $reply_id = (int)$reply_id;
    $text     = $this->db->escape($text);

    // Обновляем
    $this->db->query("
      UPDATE `" . DB_PREFIX . "bm_feedback`
      SET text = '{$text}', date_modified = NOW()
      WHERE feedback_id = '{$reply_id}' AND is_admin_reply = 1
    ");

    return true;
  }

  public function getAdminReplyId($parent_id) {
    $parent_id = (int)$parent_id;

    $query = $this->db->query("
      SELECT feedback_id
      FROM `" . DB_PREFIX . "bm_feedback`
      WHERE parent_id = '{$parent_id}' AND is_admin_reply = 1
      LIMIT 1
    ");

    if ($query->num_rows) {
      return (int)$query->row['feedback_id'];
    }

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
    // tab: need_answer | answered
    $tab   = !empty($data['tab']) ? $data['tab'] : 'need_answer';
    $start = isset($data['start']) ? (int)$data['start'] : 0;
    $limit = isset($data['limit']) ? (int)$data['limit'] : 10;

    if ($start < 0) {
      $start = 0;
    }

    if ($limit < 1) {
      $limit = 10;
    }

    // Основная запись: отзыв/вопрос покупателя (без ответов)
    // f.parent_id = 0, f.is_admin_reply = 0
    // Ответ админа (если есть) берём через LEFT JOIN r
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
              r.feedback_id AS admin_feedback_id,
              r.date_added  AS date_answered,
              r.text        AS admin_text,
              CONCAT_WS(' ', ca.firstname, ca.lastname) AS admin_name,
              p.product_id,
              pd.name AS product_name,
              CASE 
                WHEN f.customer_id IS NULL OR f.customer_id = 0 THEN f.author_name
                ELSE CONCAT_WS(' ', c.firstname, c.lastname)
              END AS customer_name
            FROM `" . DB_PREFIX . "bm_feedback` f
            LEFT JOIN `" . DB_PREFIX . "bm_feedback` r 
              ON (r.parent_id = f.feedback_id AND r.is_admin_reply = 1)
            LEFT JOIN `" . DB_PREFIX . "customer` c 
              ON (c.customer_id = f.customer_id)
            LEFT JOIN `" . DB_PREFIX . "customer` ca 
              ON (ca.customer_id = r.customer_id)
            LEFT JOIN `" . DB_PREFIX . "product` p 
              ON (p.sku = f.sku)
            LEFT JOIN `" . DB_PREFIX . "product_description` pd 
              ON (pd.product_id = p.product_id 
              AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "')
            WHERE f.parent_id = 0
              AND f.is_admin_reply = 0";

    // Фильтр по наличию/отсутствию ответа админа
    if ($tab === 'need_answer') {
      // ещё нет ответа + не внешний источник
      $sql .= " AND r.feedback_id IS NULL";
      $sql .= " AND (f.source_code IS NULL OR f.source_code = '')";
    } else {
      // ответ уже есть ИЛИ внешний источник (им не требуется ответ)
      $sql .= " AND (r.feedback_id IS NOT NULL OR (f.source_code IS NOT NULL AND f.source_code <> ''))";
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
            LEFT JOIN `" . DB_PREFIX . "bm_feedback` r 
              ON (r.parent_id = f.feedback_id AND r.is_admin_reply = 1)
            WHERE f.parent_id = 0
              AND f.is_admin_reply = 0";

    if ($tab === 'need_answer') {
      $sql .= " AND r.feedback_id IS NULL";
      $sql .= " AND (f.source_code IS NULL OR f.source_code = '')";
    } else {
      $sql .= " AND (r.feedback_id IS NOT NULL OR (f.source_code IS NOT NULL AND f.source_code <> ''))";
    }

    $query = $this->db->query($sql);

    return (int)$query->row['total'];
  }

  


}
