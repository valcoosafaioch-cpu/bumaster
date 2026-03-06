<?php
class ModelExtensionModuleBmReviewsImport extends Model {
  private $required_headers = [
    'sku',
    'variant_title',
    'source_code',
    'source_url',
    'author_name',
    'rating',
    'text',
    'date_added',
    'admin_reply_text',
  ];

  public function importCsv($tmp_file, array $options = []) {
    $create_admin_reply = !empty($options['create_admin_reply']);
    $skip_duplicates = !empty($options['skip_duplicates']);

    $report = [
      'total_rows'        => 0,
      'inserted_reviews'  => 0,
      'inserted_replies'  => 0,
      'skipped_duplicates'=> 0,
      'errors'            => [],
      'warnings'          => [],
    ];

    if (!is_readable($tmp_file)) {
      $report['errors'][] = ['row' => 0, 'message' => 'Файл недоступен для чтения'];
      return $report;
    }

    $handle = fopen($tmp_file, 'rb');
    if (!$handle) {
      $report['errors'][] = ['row' => 0, 'message' => 'Не удалось открыть файл'];
      return $report;
    }

    // Read first line to detect delimiter and headers
    $first_line = fgets($handle);
    if ($first_line === false) {
      fclose($handle);
      $report['errors'][] = ['row' => 0, 'message' => 'Пустой файл'];
      return $report;
    }

    $delimiter = $this->detectDelimiter($first_line);

    // Parse headers
    $headers = str_getcsv($first_line, $delimiter);
    $headers = array_map([$this, 'normalizeHeader'], $headers);

    // BOM fix (first header)
    if (!empty($headers[0])) {
      $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
    }

    $missing = array_diff($this->required_headers, $headers);
    if ($missing) {
      fclose($handle);
      $report['errors'][] = [
        'row' => 1,
        'message' => 'Не хватает колонок: ' . implode(', ', $missing)
      ];
      return $report;
    }

    $header_map = [];
    foreach ($headers as $idx => $h) {
      $header_map[$h] = $idx;
    }

    // Now parse remaining rows with fgetcsv
    $row_num = 1; // header = 1
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
      $row_num++;
      // skip fully empty rows
      if ($this->isEmptyRow($row)) {
        continue;
      }

      $report['total_rows']++;

      $data = $this->rowToAssoc($row, $header_map);

      $validated = $this->validateRow($data);
      if ($validated['error']) {
        $report['errors'][] = ['row' => $row_num, 'message' => $validated['error']];
        continue;
      }

      $sku           = $validated['sku'];
      $variant_title = $validated['variant_title'];
      $source_code   = $validated['source_code'];
      $source_url    = $validated['source_url'];
      $author_name   = $validated['author_name'];
      $rating        = $validated['rating'];
      $text          = $validated['text'];
      $date_added    = $validated['date_added'];
      $reply_text    = $validated['admin_reply_text'];

      // Duplicate check (by source_url) for external reviews
      if ($skip_duplicates && $source_url !== '') {
        if ($this->existsBySourceUrl($source_url)) {
          $report['skipped_duplicates']++;
          continue;
        }
      }

      // Insert buyer review (external): customer_id = NULL, date_modified not set
      $feedback_id = $this->insertBuyerReview([
        'sku'           => $sku,
        'variant_title' => $variant_title,
        'source_code'   => ($source_code !== '' ? $source_code : null),
        'source_url'    => ($source_url !== '' ? $source_url : null),
        'author_name'   => $author_name,
        'rating'        => $rating,
        'text'          => $text,
        'date_added'    => $date_added,
      ]);

      if ($feedback_id) {
        $report['inserted_reviews']++;

        // Insert admin reply if present
        if ($create_admin_reply && $reply_text !== '') {
          $ok = $this->insertAdminReply([
            'parent_id'     => $feedback_id,
            'sku'           => $sku,
            'variant_title' => $variant_title,
            'text'          => $reply_text,
            'date_added'    => $date_added,
          ]);

          if ($ok) {
            $report['inserted_replies']++;
          } else {
            $report['errors'][] = ['row' => $row_num, 'message' => 'Не удалось вставить ответ магазина (DB)'];
          }
        }
      } else {
        $report['errors'][] = ['row' => $row_num, 'message' => 'Не удалось вставить отзыв (DB)'];
      }
    }

    fclose($handle);
    return $report;
  }

  private function detectDelimiter($line) {
    $sc = substr_count($line, ';');
    $cm = substr_count($line, ',');
    return ($sc >= $cm) ? ';' : ',';
  }

  private function normalizeHeader($h) {
    $h = trim((string)$h);
    $h = strtolower($h);
    return $h;
  }

  private function isEmptyRow(array $row) {
    foreach ($row as $v) {
      if (trim((string)$v) !== '') return false;
    }
    return true;
  }

  private function rowToAssoc(array $row, array $header_map) {
    $out = [];
    foreach ($this->required_headers as $key) {
      $idx = $header_map[$key];
      $out[$key] = isset($row[$idx]) ? trim((string)$row[$idx]) : '';
    }
    return $out;
  }

  private function validateRow(array $r) {
    // sku/variant optional
    $sku = trim($r['sku']);
    $variant_title = trim($r['variant_title']);

    $source_code = strtolower(trim($r['source_code']));
    $source_url  = trim($r['source_url']);
    $author_name = trim($r['author_name']);

    $rating_raw = trim($r['rating']);
    $text = trim($r['text']);
    $date_raw = trim($r['date_added']);

    $admin_reply_text = trim($r['admin_reply_text']);

    if ($text === '') {
      return ['error' => 'Пустой текст отзыва'];
    }

    if ($author_name === '') {
      return ['error' => 'Пустое author_name'];
    }

    // rating
    if ($rating_raw === '' || !is_numeric($rating_raw)) {
      return ['error' => 'Некорректный rating'];
    }
    $rating = (int)$rating_raw;
    if ($rating < 1 || $rating > 5) {
      return ['error' => 'rating должен быть 1–5'];
    }

    // source validation (MVP)
    $allowed = ['ozon','wb','ym','avito',''];
    if (!in_array($source_code, $allowed, true)) {
      return ['error' => 'source_code неизвестен (ожидались: ozon/wb/ym/avito)'];
    }

    if ($source_code !== '' && $source_url === '') {
      return ['error' => 'source_url пустой при заполненном source_code'];
    }

    // date parsing
    $date_added = $this->normalizeDate($date_raw);
    if ($date_added === null) {
      return ['error' => 'Некорректная date_added'];
    }

    return [
      'error'            => null,
      'sku'              => $sku,
      'variant_title'    => $variant_title,
      'source_code'      => $source_code,
      'source_url'       => $source_url,
      'author_name'      => $author_name,
      'rating'           => $rating,
      'text'             => $text,
      'date_added'       => $date_added,
      'admin_reply_text' => $admin_reply_text,
    ];
  }

  private function normalizeDate($value) {
    $v = trim((string)$value);
    if ($v === '') return null;

    // YYYY-MM-DD HH:MM:SS
    if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $v)) {
      return $v;
    }

    // YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
      return $v . ' 00:00:00';
    }

    // DD.MM.YYYY
    if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $v)) {
      $parts = explode('.', $v);
      return $parts[2] . '-' . $parts[1] . '-' . $parts[0] . ' 00:00:00';
    }

    // try strtotime fallback
    $ts = strtotime($v);
    if ($ts !== false) {
      return date('Y-m-d H:i:s', $ts);
    }

    return null;
  }

  private function existsBySourceUrl($source_url) {
    $source_url = $this->db->escape($source_url);

    $q = $this->db->query(
      "SELECT feedback_id
       FROM " . DB_PREFIX . "bm_feedback
       WHERE source_url = '" . $source_url . "'
         AND type = 'review'
         AND is_admin_reply = 0
       LIMIT 1"
    );

    return $q->num_rows > 0;
  }

  private function insertBuyerReview(array $d) {
    $sku = $this->db->escape((string)$d['sku']);
    $variant_title = $this->db->escape((string)$d['variant_title']);
    $author_name = $this->db->escape((string)$d['author_name']);
    $text = $this->db->escape((string)$d['text']);
    $date_added = $this->db->escape((string)$d['date_added']);
    $rating = (int)$d['rating'];

    $source_code_sql = 'NULL';
    if (!empty($d['source_code'])) {
      $source_code_sql = "'" . $this->db->escape((string)$d['source_code']) . "'";
    }

    $source_url_sql = 'NULL';
    if (!empty($d['source_url'])) {
      $source_url_sql = "'" . $this->db->escape((string)$d['source_url']) . "'";
    }

    // customer_id = NULL, date_modified not set
    $this->db->query(
      "INSERT INTO " . DB_PREFIX . "bm_feedback
       SET parent_id = 0,
           is_admin_reply = 0,
           type = 'review',
           sku = '" . $sku . "',
           variant_title = '" . $variant_title . "',
           source_code = " . $source_code_sql . ",
           source_url = " . $source_url_sql . ",
           customer_id = NULL,
           author_name = '" . $author_name . "',
           rating = '" . $rating . "',
           text = '" . $text . "',
           date_added = '" . $date_added . "'"
    );

    return (int)$this->db->getLastId();
  }

  private function insertAdminReply(array $d) {
    $parent_id = (int)$d['parent_id'];
    $sku = $this->db->escape((string)$d['sku']);
    $variant_title = $this->db->escape((string)$d['variant_title']);
    $text = $this->db->escape((string)$d['text']);
    $date_added = $this->db->escape((string)$d['date_added']);

    $this->db->query(
      "INSERT INTO " . DB_PREFIX . "bm_feedback
       SET parent_id = '" . $parent_id . "',
           is_admin_reply = 1,
           type = 'review',
           sku = '" . $sku . "',
           variant_title = '" . $variant_title . "',
           source_code = NULL,
           source_url = NULL,
           customer_id = 1,
           author_name = NULL,
           rating = NULL,
           text = '" . $text . "',
           date_added = '" . $date_added . "'"
    );

    return true;
  }
}
