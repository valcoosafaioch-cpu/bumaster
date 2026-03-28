<?php
class ModelExtensionModuleBmReviewsImport extends Model {
  private $required_headers = [
    'key_id',
    'source_code',
    'author_name',
    'rating',
    'text',
    'date_added'
  ];

  private $optional_headers = [
    'sku',
    'variant_title',
    'source_url',
    'admin_reply_text',
    'external_order_ref'
  ];

  private $allowed_sources = [
    'ozon',
    'wb',
    'ym',
    'avito'
  ];

  private $image_subdir = 'catalog/reviews_photos/';
  private $image_import_temp_subdir = 'catalog/reviews_photos/import_temp/';
  private $column_exists = [];

  public function importCsv($csv_path, array $images = [], array $options = []) {
    $update_duplicates = !empty($options['update_duplicates']);
    $images_on_server = !empty($options['images_on_server']);
    $report = $this->buildEmptyReport();

    if (!is_readable($csv_path)) {
      $report['errors'][] = [
        'row'     => 0,
        'message' => 'CSV-файл недоступен для чтения'
      ];

      return $report;
    }

    $grouped_images = [];
    if (!$images_on_server) {
      $grouped_images = $this->groupUploadedImages($images, $report);
    }

    $import_targets = [];
    $seen_keys = [];
    $current_source_code = null;

    $handle = fopen($csv_path, 'rb');

    if (!$handle) {
      $report['errors'][] = [
        'row'     => 0,
        'message' => 'Не удалось открыть CSV-файл'
      ];

      return $report;
    }

    $first_line = fgets($handle);

    if ($first_line === false) {
      fclose($handle);

      $report['errors'][] = [
        'row'     => 0,
        'message' => 'CSV-файл пустой'
      ];

      return $report;
    }

    $delimiter = $this->detectDelimiter($first_line);

    $headers = str_getcsv($first_line, $delimiter);
    $headers = array_map([$this, 'normalizeHeader'], $headers);

    if (!empty($headers[0])) {
      $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
    }

    $header_validation = $this->validateHeaders($headers);

    if (!$header_validation['ok']) {
      fclose($handle);

      $report['errors'][] = [
        'row'     => 1,
        'message' => $header_validation['message']
      ];

      return $report;
    }

    $header_map = [];
    foreach ($headers as $index => $header) {
      $header_map[$header] = $index;
    }

    $row_num = 1;

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
      $row_num++;

      if ($this->isEmptyRow($row)) {
        continue;
      }

      $report['total_rows']++;

      $assoc = $this->rowToAssoc($row, $header_map);
      $validated = $this->validateRow($assoc);

      if ($validated['error']) {
        $report['errors'][] = [
          'row'     => $row_num,
          'message' => $validated['error']
        ];
        continue;
      }

      if ($current_source_code === null) {
        $current_source_code = $validated['source_code'];

        if ($images_on_server) {
          $grouped_images = $this->groupServerImages($current_source_code, $report);
        }
      } elseif ($current_source_code !== $validated['source_code']) {
        $report['errors'][] = [
          'row'     => $row_num,
          'message' => 'В одном CSV обнаружены разные source_code. Одна загрузка должна содержать только одну площадку.'
        ];
        continue;
      }

      $composite_key = $validated['source_code'] . '|' . $validated['external_key'];

      if (isset($seen_keys[$composite_key])) {
        $report['errors'][] = [
          'row'     => $row_num,
          'message' => 'Повторная строка в текущем CSV по связке source_code + key_id'
        ];
        continue;
      }

      $seen_keys[$composite_key] = true;

      $existing = $this->findExistingReview($validated['source_code'], $validated['external_key']);

      if ($existing) {
        if ($update_duplicates) {
          $updated = $this->updateReview((int)$existing['feedback_id'], $validated);

          if (!$updated) {
            $report['errors'][] = [
              'row'     => $row_num,
              'message' => 'Не удалось обновить существующий отзыв'
            ];
            continue;
          }

          $deleted_images = $this->deleteReviewImages((int)$existing['feedback_id']);
          $report['deleted_images'] += $deleted_images;
          $report['updated_reviews']++;

          $import_targets[$validated['external_key']] = [
            'feedback_id' => (int)$existing['feedback_id'],
            'sku'         => $validated['sku'],
            'source_code' => $validated['source_code'],
            'mode'        => 'updated',
            'row'         => $row_num
          ];
        } else {
          $report['skipped_duplicates']++;
        }

        continue;
      }

      $feedback_id = $this->insertReview($validated);

      if (!$feedback_id) {
        $report['errors'][] = [
          'row'     => $row_num,
          'message' => 'Не удалось вставить новый отзыв'
        ];
        continue;
      }

      $report['inserted_reviews']++;

      $import_targets[$validated['external_key']] = [
        'feedback_id' => (int)$feedback_id,
        'sku'         => $validated['sku'],
        'source_code' => $validated['source_code'],
        'mode'        => 'inserted',
        'row'         => $row_num
      ];
    }

    fclose($handle);

    foreach ($grouped_images as $external_key => $image_items) {
      if (!isset($import_targets[$external_key])) {
        $report['skipped_images'] += count($image_items);
        $report['warnings'][] = 'Изображения для key_id "' . $external_key . '" пропущены: соответствующий отзыв не был импортирован или был пропущен как дубль.';
        continue;
      }

      $target = $import_targets[$external_key];

      $saved_count = $this->saveReviewImages(
        (int)$target['feedback_id'],
        $target['sku'],
        $target['source_code'],
        $image_items,
        $report,
        $images_on_server
      );

      $report['inserted_images'] += $saved_count;
    }

    if ($images_on_server && $current_source_code !== null) {
      $this->cleanupImportTempDirectory($current_source_code, $report);
    }

    return $report;
  }

  private function buildEmptyReport() {
    return [
      'total_rows'         => 0,
      'inserted_reviews'   => 0,
      'updated_reviews'    => 0,
      'skipped_duplicates' => 0,
      'total_images'       => 0,
      'inserted_images'    => 0,
      'deleted_images'     => 0,
      'skipped_images'     => 0,
      'errors'             => [],
      'warnings'           => []
    ];
  }

  private function validateHeaders(array $headers) {
    $missing = array_diff($this->required_headers, $headers);

    if ($missing) {
      return [
        'ok'      => false,
        'message' => 'Не хватает обязательных колонок: ' . implode(', ', $missing)
      ];
    }

    return [
      'ok'      => true,
      'message' => ''
    ];
  }

  private function rowToAssoc(array $row, array $header_map) {
    $fields = array_merge($this->required_headers, $this->optional_headers);
    $result = [];

    foreach ($fields as $field) {
      if (!isset($header_map[$field])) {
        $result[$field] = '';
        continue;
      }

      $index = $header_map[$field];
      $result[$field] = isset($row[$index]) ? trim((string)$row[$index]) : '';
    }

    return $result;
  }

  private function validateRow(array $row) {
    $external_key = trim((string)$row['key_id']);
    $sku = trim((string)$row['sku']);
    $variant_title = trim((string)$row['variant_title']);
    $source_code = strtolower(trim((string)$row['source_code']));
    $source_url = trim((string)$row['source_url']);
    $author_name = trim((string)$row['author_name']);
    $rating_raw = trim((string)$row['rating']);
    $text = trim((string)$row['text']);
    $date_raw = trim((string)$row['date_added']);
    $admin_reply_text = trim((string)$row['admin_reply_text']);
    $external_order_ref = trim((string)$row['external_order_ref']);

    if ($external_key === '') {
      return ['error' => 'Пустой key_id'];
    }

    if ($source_code === '') {
      return ['error' => 'Пустой source_code'];
    }

    if (!in_array($source_code, $this->allowed_sources, true)) {
      return ['error' => 'source_code неизвестен (ожидались: ozon / wb / ym / avito)'];
    }

    if ($author_name === '') {
      return ['error' => 'Пустое author_name'];
    }

    if ($text === '') {
      return ['error' => 'Пустой текст отзыва'];
    }

    if ($rating_raw === '' || !is_numeric($rating_raw)) {
      return ['error' => 'Некорректный rating'];
    }

    $rating = (int)$rating_raw;

    if ($rating < 1 || $rating > 5) {
      return ['error' => 'rating должен быть в диапазоне 1–5'];
    }

    $date_added = $this->normalizeDate($date_raw);

    if ($date_added === null) {
      return ['error' => 'Некорректная date_added'];
    }

    $entity_type = ($sku !== '') ? 'product' : 'store';

    if ($entity_type === 'store') {
      $variant_title = '';
    }

    return [
      'error'              => null,
      'external_key'       => $external_key,
      'sku'                => $sku,
      'variant_title'      => $variant_title,
      'entity_type'        => $entity_type,
      'source_code'        => $source_code,
      'source_url'         => $source_url,
      'author_name'        => $author_name,
      'rating'             => $rating,
      'text'               => $text,
      'date_added'         => $date_added,
      'admin_reply_text'   => $admin_reply_text,
      'external_order_ref' => $external_order_ref,
      'imported_at'        => date('Y-m-d H:i:s')
    ];
  }

  private function findExistingReview($source_code, $external_key) {
    $sql = "
      SELECT feedback_id
      FROM " . DB_PREFIX . "bm_feedback
      WHERE source_code = '" . $this->db->escape($source_code) . "'
        AND external_key = '" . $this->db->escape($external_key) . "'
        AND type = 'review'
    ";

    if ($this->hasColumn('bm_feedback', 'is_admin_reply')) {
      $sql .= " AND is_admin_reply = 0";
    }

    $sql .= " LIMIT 1";

    $query = $this->db->query($sql);

    return $query->num_rows ? $query->row : null;
  }

  private function insertReview(array $data) {
    $fields = [];

    if ($this->hasColumn('bm_feedback', 'parent_id')) {
      $fields[] = "parent_id = 0";
    }

    if ($this->hasColumn('bm_feedback', 'admin_reply')) {
      $fields[] = "admin_reply = " . $this->toSqlValue($data['admin_reply_text']);
    }

    if ($this->hasColumn('bm_feedback', 'customer_id')) {
      $fields[] = "customer_id = NULL";
    }

    $fields[] = "type = 'review'";
    $fields[] = "sku = " . $this->toSqlValue($data['sku']);
    $fields[] = "variant_title = " . $this->toSqlValue($data['variant_title']);
    $fields[] = "source_code = " . $this->toSqlValue($data['source_code']);
    $fields[] = "source_url = " . $this->toSqlValue($data['source_url']);
    $fields[] = "author_name = " . $this->toSqlValue($data['author_name']);
    $fields[] = "rating = " . (int)$data['rating'];
    $fields[] = "text = " . $this->toSqlValue($data['text']);
    $fields[] = "date_added = " . $this->toSqlValue($data['date_added']);

    if ($this->hasColumn('bm_feedback', 'moderation_status')) {
      $fields[] = "moderation_status = 'approved'";
    }

    if ($this->hasColumn('bm_feedback', 'moderated_at')) {
      $fields[] = "moderated_at = " . $this->toSqlValue($data['imported_at']);
    }

    if ($this->hasColumn('bm_feedback', 'date_modified')) {
      $fields[] = "date_modified = " . $this->toSqlValue($data['imported_at']);
    }

    if ($this->hasColumn('bm_feedback', 'entity_type')) {
      $fields[] = "entity_type = " . $this->toSqlValue($data['entity_type']);
    }

    if ($this->hasColumn('bm_feedback', 'external_key')) {
      $fields[] = "external_key = " . $this->toSqlValue($data['external_key']);
    }

    if ($this->hasColumn('bm_feedback', 'external_order_ref')) {
      $fields[] = "external_order_ref = " . $this->toSqlValue($data['external_order_ref']);
    }

    if ($this->hasColumn('bm_feedback', 'imported_at')) {
      $fields[] = "imported_at = " . $this->toSqlValue($data['imported_at']);
    }

    $sql = "
      INSERT INTO " . DB_PREFIX . "bm_feedback
      SET " . implode(",\n          ", $fields);

    $this->db->query($sql);

    return (int)$this->db->getLastId();
  }

  private function updateReview($feedback_id, array $data) {
    $feedback_id = (int)$feedback_id;
    $fields = [];

    $fields[] = "sku = " . $this->toSqlValue($data['sku']);
    $fields[] = "variant_title = " . $this->toSqlValue($data['variant_title']);
    $fields[] = "source_url = " . $this->toSqlValue($data['source_url']);
    $fields[] = "author_name = " . $this->toSqlValue($data['author_name']);
    $fields[] = "rating = " . (int)$data['rating'];
    $fields[] = "text = " . $this->toSqlValue($data['text']);
    $fields[] = "date_added = " . $this->toSqlValue($data['date_added']);

    if ($this->hasColumn('bm_feedback', 'moderation_status')) {
      $fields[] = "moderation_status = 'approved'";
    }

    if ($this->hasColumn('bm_feedback', 'moderated_at')) {
      $fields[] = "moderated_at = " . $this->toSqlValue($data['imported_at']);
    }

    if ($this->hasColumn('bm_feedback', 'date_modified')) {
      $fields[] = "date_modified = " . $this->toSqlValue($data['imported_at']);
    }

    if ($this->hasColumn('bm_feedback', 'entity_type')) {
      $fields[] = "entity_type = " . $this->toSqlValue($data['entity_type']);
    }

    if ($this->hasColumn('bm_feedback', 'admin_reply')) {
      $fields[] = "admin_reply = " . $this->toSqlValue($data['admin_reply_text']);
    }

    if ($this->hasColumn('bm_feedback', 'external_order_ref')) {
      $fields[] = "external_order_ref = " . $this->toSqlValue($data['external_order_ref']);
    }

    if ($this->hasColumn('bm_feedback', 'imported_at')) {
      $fields[] = "imported_at = " . $this->toSqlValue($data['imported_at']);
    }

    if (!$fields) {
      return false;
    }

    $sql = "
      UPDATE " . DB_PREFIX . "bm_feedback
      SET " . implode(",\n          ", $fields) . "
      WHERE feedback_id = " . $feedback_id . "
      LIMIT 1";

    $this->db->query($sql);

    return true;
  }

  private function groupUploadedImages(array $images, array &$report) {
    $grouped = [];
    $files = $this->normalizeUploadedFiles($images);

    foreach ($files as $file) {
      if (empty($file['name'])) {
        continue;
      }

      $report['total_images']++;

      if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
        $report['skipped_images']++;
        $report['warnings'][] = 'Файл "' . $file['name'] . '" пропущен: ошибка загрузки.';
        continue;
      }

      if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $report['skipped_images']++;
        $report['warnings'][] = 'Файл "' . $file['name'] . '" пропущен: временный файл недоступен.';
        continue;
      }

      $parsed = $this->parseImageName($file['name']);

      if (!$parsed) {
        $report['skipped_images']++;
        $report['warnings'][] = 'Файл "' . $file['name'] . '" пропущен: имя должно быть в формате key_id_номер.ext';
        continue;
      }

      $grouped[$parsed['external_key']][] = [
        'name'       => $file['name'],
        'tmp_name'   => $file['tmp_name'],
        'sort_order' => $parsed['sort_order'],
        'extension'  => $parsed['extension']
      ];
    }

    foreach ($grouped as &$items) {
      usort($items, function($a, $b) {
        if ($a['sort_order'] === $b['sort_order']) {
          return strcmp($a['name'], $b['name']);
        }

        return ($a['sort_order'] < $b['sort_order']) ? -1 : 1;
      });
    }
    unset($items);

    return $grouped;
  }

  private function groupServerImages($source_code, array &$report) {
    $grouped = [];
    $source_code = $this->sanitizeFilePart($source_code);
    $directory = rtrim(DIR_IMAGE, "/\\") . '/' . trim($this->image_import_temp_subdir, "/\\") . '/' . $source_code . '/';

    if (!is_dir($directory)) {
      $report['warnings'][] = 'Папка временного импорта не найдена: ' . $directory;
      return [];
    }

    $files = scandir($directory);
    if ($files === false) {
      $report['warnings'][] = 'Не удалось прочитать папку временного импорта: ' . $directory;
      return [];
    }

    foreach ($files as $file_name) {
      if ($file_name === '.' || $file_name === '..') {
        continue;
      }

      $absolute_path = $directory . $file_name;

      if (!is_file($absolute_path)) {
        continue;
      }

      $report['total_images']++;

      $parsed = $this->parseImageName($file_name);

      if (!$parsed) {
        $report['skipped_images']++;
        $report['warnings'][] = 'Файл "' . $file_name . '" в import_temp пропущен: имя должно быть в формате key_id_номер.ext';
        continue;
      }

      $grouped[$parsed['external_key']][] = [
        'name'       => $file_name,
        'tmp_name'   => $absolute_path,
        'sort_order' => $parsed['sort_order'],
        'extension'  => $parsed['extension']
      ];
    }

    foreach ($grouped as &$items) {
      usort($items, function($a, $b) {
        if ($a['sort_order'] === $b['sort_order']) {
          return strcmp($a['name'], $b['name']);
        }

        return ($a['sort_order'] < $b['sort_order']) ? -1 : 1;
      });
    }
    unset($items);

    return $grouped;
  }

  private function normalizeUploadedFiles(array $images) {
    if (!$images) {
      return [];
    }

    if (isset($images['name']) && is_array($images['name'])) {
      $normalized = [];
      $count = count($images['name']);

      for ($i = 0; $i < $count; $i++) {
        $normalized[] = [
          'name'     => isset($images['name'][$i]) ? $images['name'][$i] : '',
          'type'     => isset($images['type'][$i]) ? $images['type'][$i] : '',
          'tmp_name' => isset($images['tmp_name'][$i]) ? $images['tmp_name'][$i] : '',
          'error'    => isset($images['error'][$i]) ? $images['error'][$i] : UPLOAD_ERR_NO_FILE,
          'size'     => isset($images['size'][$i]) ? $images['size'][$i] : 0
        ];
      }

      return $normalized;
    }

    if (isset($images[0]) && is_array($images[0])) {
      return $images;
    }

    if (isset($images['name'])) {
      return [$images];
    }

    return [];
  }

  private function parseImageName($filename) {
    $filename = trim((string)$filename);

    if (!preg_match('~^(.+?)_(\d+)\.(jpg|jpeg|png|webp)$~iu', $filename, $matches)) {
      return null;
    }

    return [
      'external_key' => trim($matches[1]),
      'sort_order'   => (int)$matches[2],
      'extension'    => strtolower($matches[3])
    ];
  }

  private function saveReviewImages($feedback_id, $sku, $source_code, array $images, array &$report, $images_on_server = false) {
    $feedback_id = (int)$feedback_id;
    $saved = 0;

    if ($feedback_id <= 0 || !$images) {
      return 0;
    }

    $source_code = trim((string)$source_code);
    $source_code = $this->sanitizeFilePart($source_code);

    $directory = rtrim(DIR_IMAGE, "/\\") . '/' . trim($this->image_subdir, "/\\") . '/' . $source_code . '/';

    if (!is_dir($directory)) {
      if (!@mkdir($directory, 0777, true) && !is_dir($directory)) {
        $report['warnings'][] = 'Не удалось создать папку для изображений отзывов: ' . $directory;
        $report['skipped_images'] += count($images);
        return 0;
      }
    }

    $base_name = $this->buildImageBaseName($sku, $feedback_id);

    foreach ($images as $index => $image) {
      $extension = strtolower($image['extension']);
      $sort_order = (int)$image['sort_order'];

      if (!$sort_order) {
        $sort_order = $index + 1;
      }

      $file_name = $base_name . '_' . $sort_order . '.' . $extension;
      $relative_path = trim($this->image_subdir, "/\\") . '/' . $source_code . '/' . $file_name;
      $absolute_path = $directory . $file_name;

      $saved_ok = false;

      if ($images_on_server) {
        if (@copy($image['tmp_name'], $absolute_path)) {
          $saved_ok = true;
        }
      } else {
        if (@move_uploaded_file($image['tmp_name'], $absolute_path)) {
          $saved_ok = true;
        } elseif (@copy($image['tmp_name'], $absolute_path)) {
          $saved_ok = true;
        }
      }

      if (!$saved_ok) {
        $report['skipped_images']++;
        $report['warnings'][] = 'Не удалось сохранить изображение "' . $image['name'] . '" для feedback_id=' . $feedback_id;
        continue;
      }

      $this->db->query("
        INSERT INTO " . DB_PREFIX . "bm_feedback_image
        SET feedback_id = " . $feedback_id . ",
            image = '" . $this->db->escape($relative_path) . "',
            sort_order = " . $sort_order
      );

      $saved++;
    }

    return $saved;
  }

  private function deleteReviewImages($feedback_id) {
    $feedback_id = (int)$feedback_id;
    $deleted = 0;

    if ($feedback_id <= 0) {
      return 0;
    }

    $query = $this->db->query("
      SELECT feedback_image_id, image
      FROM " . DB_PREFIX . "bm_feedback_image
      WHERE feedback_id = " . $feedback_id
    );

    foreach ($query->rows as $row) {
      if (!empty($row['image'])) {
        $full_path = rtrim(DIR_IMAGE, "/\\") . '/' . ltrim($row['image'], "/\\");

        if (is_file($full_path)) {
          @unlink($full_path);
        }
      }

      $deleted++;
    }

    $this->db->query("
      DELETE FROM " . DB_PREFIX . "bm_feedback_image
      WHERE feedback_id = " . $feedback_id
    );

    return $deleted;
  }

  private function cleanupImportTempDirectory($source_code, array &$report) {
    $source_code = $this->sanitizeFilePart($source_code);
    $directory = rtrim(DIR_IMAGE, "/\\") . '/' . trim($this->image_import_temp_subdir, "/\\") . '/' . $source_code . '/';

    if (!is_dir($directory)) {
      return;
    }

    $files = scandir($directory);
    if ($files === false) {
      $report['warnings'][] = 'Не удалось очистить папку временного импорта: ' . $directory;
      return;
    }

    foreach ($files as $file_name) {
      if ($file_name === '.' || $file_name === '..') {
        continue;
      }

      $absolute_path = $directory . $file_name;

      if (is_file($absolute_path)) {
        @unlink($absolute_path);
      }
    }
  }

  private function buildImageBaseName($sku, $feedback_id) {
    $prefix = ($sku !== '') ? $sku : 'store';
    $prefix = $this->sanitizeFilePart($prefix);

    return $prefix . '-' . str_pad((int)$feedback_id, 6, '0', STR_PAD_LEFT);
  }

  private function sanitizeFilePart($value) {
    $value = trim((string)$value);
    $value = preg_replace('~[^\pL\pN\-\_\(\)]+~u', '-', $value);
    $value = preg_replace('~-{2,}~', '-', $value);
    $value = trim($value, '-_');

    if ($value === '') {
      return 'store';
    }

    return $value;
  }

  private function toSqlValue($value) {
    if ($value === null) {
      return 'NULL';
    }

    $value = trim((string)$value);

    if ($value === '') {
      return 'NULL';
    }

    return "'" . $this->db->escape($value) . "'";
  }

  private function hasColumn($table, $column) {
    $key = $table . '.' . $column;

    if (array_key_exists($key, $this->column_exists)) {
      return $this->column_exists[$key];
    }

    $query = $this->db->query("
      SHOW COLUMNS FROM " . DB_PREFIX . $table . " LIKE '" . $this->db->escape($column) . "'
    ");

    $this->column_exists[$key] = $query->num_rows > 0;

    return $this->column_exists[$key];
  }

  private function detectDelimiter($line) {
    $semicolon = substr_count($line, ';');
    $comma = substr_count($line, ',');

    return ($semicolon >= $comma) ? ';' : ',';
  }

  private function normalizeHeader($header) {
    return strtolower(trim((string)$header));
  }

  private function isEmptyRow(array $row) {
    foreach ($row as $value) {
      if (trim((string)$value) !== '') {
        return false;
      }
    }

    return true;
  }

  private function normalizeDate($value) {
    $value = trim((string)$value);

    if ($value === '') {
      return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $value)) {
      return $value;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
      return $value . ' 00:00:00';
    }

    if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $value)) {
      $parts = explode('.', $value);
      return $parts[2] . '-' . $parts[1] . '-' . $parts[0] . ' 00:00:00';
    }

    if (preg_match('/^\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2}(:\d{2})?$/', $value)) {
      $parts = preg_split('/\s+/', $value);
      $date = explode('.', $parts[0]);
      $time = $parts[1];

      if (substr_count($time, ':') === 1) {
        $time .= ':00';
      }

      return $date[2] . '-' . $date[1] . '-' . $date[0] . ' ' . $time;
    }

    $timestamp = strtotime($value);

    if ($timestamp !== false) {
      return date('Y-m-d H:i:s', $timestamp);
    }

    return null;
  }
}