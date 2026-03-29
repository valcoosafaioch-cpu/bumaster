<?php
/**
 * Контроллер карточки товара (кастомная версия Бумажного Мастера)
 * OpenCart 3.0.3.9 + Materialize
 * 
 * Минимум зависимостей. Чистый вывод для нашего шаблона product.twig
 */

class ControllerProductProduct extends Controller {
  public function index() {
    // === 1. Получаем ID товара ===
    if (isset($this->request->get['product_id'])) {
      $product_id = (int)$this->request->get['product_id'];
    } else {
      $this->response->redirect($this->url->link('error/not_found'));
      return;
    }

    // --- ГАРД против ранней вставки Materialize в OCMOD ---
    $this->load->model('catalog/manufacturer');
    $product_info = ['manufacturer_id' => 0];    // временная заглушка для раннего вызова
    // --- /ГАРД ---

    // === 2. Модель товара ===
    $this->load->model('catalog/manufacturer');
    $this->load->model('catalog/product');
    $this->load->model('tool/image');
    $this->load->model('catalog/category');
    $this->load->model('catalog/bm_feedback');

    $product_info = $this->model_catalog_product->getProduct($product_id);

    if (!$product_info) {
      $this->response->redirect($this->url->link('error/not_found'));
      return;
    }

    // Устойчивый идентификатор для отзывов — sku
    $sku = isset($product_info['sku']) ? (string)$product_info['sku'] : '';

    // === 2.0. Текущий пользователь: есть ли уже отзыв по этому sku ===
    $customer_id = $this->customer->isLogged() ? (int)$this->customer->getId() : 0;
    $user_review = null;

    if ($customer_id > 0) {
      $user_review = $this->model_catalog_bm_feedback->getReviewByProductAndCustomer($sku, $customer_id);
    }

    // === 2.1. Инициализация состояний форм отзывов/вопросов ===
    $review_error     = '';
    $review_success   = '';
    $question_error   = '';
    $question_success = '';
    $admin_reply_error   = '';
    $admin_reply_success = '';

    $review_form = [
      'text'   => '',
      'rating' => 0,
      'images' => [],
    ];

    $question_form = [
      'text' => '',
    ];

    // === 2.2. Обработка отправки отзывов и вопросов ===
    if ($this->request->server['REQUEST_METHOD'] === 'POST') {
      $type = isset($this->request->post['feedback_type'])
        ? (string)$this->request->post['feedback_type']
        : '';

      // --- Отзыв ---
      if ($type === 'review') {
        $is_ajax = !empty($this->request->post['is_ajax']);
        if (!$this->customer->isLogged()) {
          $review_error = 'Чтобы оставить отзыв, войдите в личный кабинет или зарегистрируйтесь.';
          if ($is_ajax) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(['error' => $review_error]));
            return;
          }
        } elseif ($user_review) {
          $review_error = 'Вы уже оставили отзыв на этот товар.';
          if ($is_ajax) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(['error' => $review_error]));
            return;
          }
        } else {
          $text   = isset($this->request->post['review_text']) ? trim((string)$this->request->post['review_text']) : '';
          $rating = isset($this->request->post['review_rating']) ? (int)$this->request->post['review_rating'] : 0;

          $review_form['text']   = $text;
          $review_form['rating'] = $rating > 0 ? $rating : 0;

          $len = mb_strlen($text, 'UTF-8');

          if ($len < 5) {
            $review_error = 'Текст отзыва слишком короткий (минимум 5 символов).';
            if ($is_ajax) {
              $this->response->addHeader('Content-Type: application/json');
              $this->response->setOutput(json_encode(['error' => $review_error]));
              return;
            }
          } elseif ($len > 3000) {
            $review_error = 'Текст отзыва слишком длинный (максимум 3000 символов).';
            if ($is_ajax) {
              $this->response->addHeader('Content-Type: application/json');
              $this->response->setOutput(json_encode(['error' => $review_error]));
              return;
            }
          } elseif ($rating < 1 || $rating > 5) {
            $review_error = 'Пожалуйста, укажите оценку от 1 до 5.';
            if ($is_ajax) {
              $this->response->addHeader('Content-Type: application/json');
              $this->response->setOutput(json_encode(['error' => $review_error]));
              return;
            }
          }

          $review_files = [];

          if (
            isset($this->request->files['review_images'])
            && !empty($this->request->files['review_images']['name'])
            && is_array($this->request->files['review_images']['name'])
          ) {
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowed_mimes = [
              'image/jpeg',
              'image/pjpeg',
              'image/png',
              'image/gif',
              'image/webp'
            ];

            $max_files = 5;
            $max_file_size = 5 * 1024 * 1024;   // 5 МБ
            $max_total_size = 25 * 1024 * 1024; // общий лимит 25 МБ

            $names     = $this->request->files['review_images']['name'];
            $tmp_names = $this->request->files['review_images']['tmp_name'];
            $errors    = $this->request->files['review_images']['error'];
            $sizes     = $this->request->files['review_images']['size'];

            $total_size = 0;
            $valid_count = 0;

            foreach ($names as $index => $original_name) {
              $original_name = trim((string)$original_name);

              if ($original_name === '') {
                continue;
              }

              $error = isset($errors[$index]) ? (int)$errors[$index] : UPLOAD_ERR_NO_FILE;

              if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
              }

              if ($error !== UPLOAD_ERR_OK) {
                $review_error = 'Не удалось загрузить одно из фото отзыва.';
                if ($is_ajax) {
                  $this->response->addHeader('Content-Type: application/json');
                  $this->response->setOutput(json_encode(['error' => $review_error]));
                  return;
                }
                break;
              }

              $tmp_name = isset($tmp_names[$index]) ? (string)$tmp_names[$index] : '';
              $size     = isset($sizes[$index]) ? (int)$sizes[$index] : 0;

              if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
                $review_error = 'Не удалось обработать одно из фото отзыва.';
                if ($is_ajax) {
                  $this->response->addHeader('Content-Type: application/json');
                  $this->response->setOutput(json_encode(['error' => $review_error]));
                  return;
                }
                break;
              }

              $valid_count++;

              if ($valid_count > $max_files) {
                $review_error = 'К отзыву можно прикрепить не более 5 фото.';
                if ($is_ajax) {
                  $this->response->addHeader('Content-Type: application/json');
                  $this->response->setOutput(json_encode(['error' => $review_error]));
                  return;
                }
                break;
              }

              if ($size <= 0) {
                $review_error = 'Одно из загруженных фото пустое.';
                if ($is_ajax) {
                  $this->response->addHeader('Content-Type: application/json');
                  $this->response->setOutput(json_encode(['error' => $review_error]));
                  return;
                }
                break;
              }

              if ($size > $max_file_size) {
                $review_error = 'Размер каждого фото не должен превышать 5 МБ.';
                if ($is_ajax) {
                  $this->response->addHeader('Content-Type: application/json');
                  $this->response->setOutput(json_encode(['error' => $review_error]));
                  return;
                }
                break;
              }

              $total_size += $size;

              if ($total_size > $max_total_size) {
                $review_error = 'Общий размер фото не должен превышать 25 МБ.';
                if ($is_ajax) {
                  $this->response->addHeader('Content-Type: application/json');
                  $this->response->setOutput(json_encode(['error' => $review_error]));
                  return;
                }
                break;
              }

              $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

              if (!in_array($extension, $allowed_extensions, true)) {
                $review_error = 'Допустимые форматы фото: jpg, jpeg, png, gif, webp.';
                if ($is_ajax) {
                  $this->response->addHeader('Content-Type: application/json');
                  $this->response->setOutput(json_encode(['error' => $review_error]));
                  return;
                }
                break;
              }

              $mime = '';
              if (function_exists('mime_content_type')) {
                $mime = (string)@mime_content_type($tmp_name);
              }

              if ($mime !== '' && !in_array($mime, $allowed_mimes, true)) {
                $review_error = 'Можно загружать только изображения.';
                if ($is_ajax) {
                  $this->response->addHeader('Content-Type: application/json');
                  $this->response->setOutput(json_encode(['error' => $review_error]));
                  return;
                }
                break;
              }

              $review_files[] = [
                'name'     => $original_name,
                'tmp_name' => $tmp_name,
                'size'     => $size,
                'type'     => $mime,
                'error'    => $error,
              ];
            }
          }

          if ($review_error === '') {
            // Текст варианта из формы (hidden-поле)
            $variant_title = isset($this->request->post['variant_title'])
              ? trim((string)$this->request->post['variant_title'])
              : '';

            $feedback_id = (int)$this->model_catalog_bm_feedback->saveReview(
              $sku,
              $customer_id,
              [
                'text'          => $text,
                'rating'        => $rating,
                'variant_title' => $variant_title,
              ]
            );

            if ($feedback_id > 0 && !empty($review_files)) {
              $this->model_catalog_bm_feedback->addFeedbackImages($feedback_id, $sku, $review_files);
            }

            $review_success = 'Спасибо! Ваш отзыв сохранён.';

            if ($is_ajax) {
              $this->response->addHeader('Content-Type: application/json');
              $this->response->setOutput(json_encode([
                'success' => $review_success
              ]));
              return;
            }

            $this->response->redirect(
              $this->url->link('product/product', 'product_id=' . $product_id . '&bm_scroll=feedback&bm_tab=reviews', true)
            );
            return;
          }
        }
      }

       // --- Ответ администратора на отзыв/вопрос ---
      if ($type === 'admin_reply') {
        // Разрешаем только админской учётке (customer_id = 0)
        if (!$this->customer->isLogged() || (int)$this->customer->getId() !== 1) {
          // На всякий случай просто уходим обратно на товар
          $this->response->redirect($this->url->link('product/product', 'product_id=' . $product_id));
          return;
        }

        $parent_id = isset($this->request->post['parent_id'])
          ? (int)$this->request->post['parent_id']
          : 0;

        $reply_id = isset($this->request->post['reply_id'])
          ? (int)$this->request->post['reply_id']
          : 0;

        $text = isset($this->request->post['admin_reply_text'])
          ? trim((string)$this->request->post['admin_reply_text'])
          : '';

        // Простая валидация
        $len = mb_strlen($text, 'UTF-8');

        if ($parent_id <= 0 || $len < 1) {
          // Нечего сохранять — просто возвращаемся
          $this->response->redirect($this->url->link('product/product', 'product_id=' . $product_id));
          return;
        }

        if ($len > 3000) {
          // Обрежем, чтобы не падать
          $text = mb_substr($text, 0, 3000, 'UTF-8');
        }

        // Если ответ уже есть — редактируем, иначе создаём новый
        if ($reply_id > 0) {
          // Редактируем существующий ответ — пока без письма
          $this->model_catalog_bm_feedback->updateAdminReply($reply_id, $text);

        } else {
          // Новый ответ магазина
          $this->model_catalog_bm_feedback->addAdminReply($parent_id, $text);

          // Письмо покупателю
          $feedback_info = $this->model_catalog_bm_feedback->getFeedbackWithCustomer($parent_id);

          if (!empty($feedback_info) && !empty($feedback_info['email'])) {
            // Тип: review / question
            $is_question = (isset($feedback_info['type']) && $feedback_info['type'] === 'question');

            // Обращение
            $customer_name = trim(($feedback_info['firstname'] ?? '') . ' ' . ($feedback_info['lastname'] ?? ''));
            if ($customer_name === '') {
              $customer_name = 'друг';
            }

            // Название товара у нас уже есть в $product_info
            $product_name = isset($product_info['name']) ? $product_info['name'] : '';

            // Ссылка на товар
            $product_link = $this->url->link('product/product', 'product_id=' . $product_id, true);

            // Тема письма
            $subject = $is_question
              ? 'Ответ на ваш вопрос в магазине «Бумажный Мастер»'
              : 'Ответ на ваш отзыв в магазине «Бумажный Мастер»';

            // Текст письма (простым текстом)
            $message  = 'Здравствуйте, ' . $customer_name . "!\n\n";

            if ($is_question) {
              $message .= "На ваш вопрос о товаре «" . $product_name . "» поступил ответ от магазина.\n\n";
              $message .= "Ваш вопрос:\n";
            } else {
              $message .= "На ваш отзыв о товаре «" . $product_name . "» поступил ответ от магазина.\n\n";
              $message .= "Ваш отзыв:\n";
            }

            // Текст вопроса/отзыва из bm_feedback
            if (!empty($feedback_info['text'])) {
              $message .= $feedback_info['text'] . "\n\n";
            }

            $message .= "Ответ магазина:\n";
            $message .= $text . "\n\n";

            $message .= "Посмотреть товар и полный текст на сайте:\n";
            $message .= $product_link . "\n\n";

            $message .= "Спасибо, что выбираете «Бумажный Мастер»!";

            // Отправка письма через стандартный Mail OpenCart
            $mail = new Mail($this->config->get('config_mail_engine'));
            $mail->parameter      = $this->config->get('config_mail_parameter');
            $mail->smtp_hostname  = $this->config->get('config_mail_smtp_hostname');
            $mail->smtp_username  = $this->config->get('config_mail_smtp_username');
            $mail->smtp_password  = $this->config->get('config_mail_smtp_password');
            $mail->smtp_port      = $this->config->get('config_mail_smtp_port');
            $mail->smtp_timeout   = $this->config->get('config_mail_smtp_timeout');

            $mail->setTo($feedback_info['email']);
            $mail->setFrom($this->config->get('config_email'));
            $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
            $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
            $mail->setText(html_entity_decode($message, ENT_QUOTES, 'UTF-8'));
            $mail->send();
          }
        }

        // После сохранения — редирект обратно на товар
        $this->response->redirect($this->url->link('product/product', 'product_id=' . $product_id));
        return;
      }

      // --- Вопрос ---
      if ($type === 'question') {
        if (!$this->customer->isLogged()) {
          $question_error = 'Чтобы задать вопрос, войдите в личный кабинет или зарегистрируйтесь.';
        } else {
          $text = isset($this->request->post['question_text']) ? trim((string)$this->request->post['question_text']) : '';
          $question_form['text'] = $text;

          $len = mb_strlen($text, 'UTF-8');

          if ($len < 5) {
            $question_error = 'Текст вопроса слишком короткий (минимум 5 символов).';
          } elseif ($len > 1500) {
            $question_error = 'Текст вопроса слишком длинный (максимум 1500 символов).';
          }

          if ($question_error === '') {
            // Текст варианта из формы (hidden-поле)
            $variant_title = isset($this->request->post['variant_title'])
              ? trim((string)$this->request->post['variant_title'])
              : '';

            $this->model_catalog_bm_feedback->addQuestion(
              $sku,
              (int)$this->customer->getId(),
              [
                'text'          => $text,
                'variant_title' => $variant_title,
              ]
            );

            $question_success = 'Ваш вопрос отправлен. Мы ответим на него в ближайшее время.';

            $this->response->redirect($this->url->link('product/product', 'product_id=' . $product_id));
            return;
          }
        }
      }
    }

    // === 3. Подключаем отдельные стили карточки ===
    $this->document->addStyle('catalog/view/theme/materialize/stylesheet/product-page.css');

    // === 4. Основные данные товара ===
    $data = [
      'product_id'   => $product_info['product_id'],
      'name'         => $product_info['name'],
      'sku'          => $product_info['sku'],
      'model'        => $product_info['model'],
      'manufacturer' => $product_info['manufacturer'],
      'description'  => html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8'),
      'price'        => $this->currency->format($product_info['price'], $this->session->data['currency']),
      'special'      => $product_info['special'] ? $this->currency->format($product_info['special'], $this->session->data['currency']) : false,
      'quantity'     => (int)$product_info['quantity']
      
    ];

    // === Цена без копеек + скидка % для шаблона ===
    $__price_raw   = isset($product_info['price'])    ? (float)$product_info['price']    : 0.0;
    $__special_raw = !empty($product_info['special']) ? (float)$product_info['special']  : 0.0;

    // Целое с пробелами по тысячам: 10500 -> "10 500"
    $__fmt_int = function ($n) {
      return number_format((int)round($n, 0), 0, '', ' ');
    };

    $data['price_clean']     = $__fmt_int($__price_raw);
    $data['special_clean']   = $__special_raw > 0 ? $__fmt_int($__special_raw) : '';
    $data['price_old_clean'] = $__special_raw > 0 ? $__fmt_int($__price_raw)   : '';

    $data['discount_percent'] = ($__special_raw > 0 && $__price_raw > 0)
      ? (int)ceil(100 - ($__special_raw / $__price_raw) * 100)
      : 0;

    // === 5. Флаги состояния ===
    $data['is_out'] = $data['quantity'] <= 0;

    // === 5a. Иконка бренда ===
    $data['brand_icon'] = '';
    if (!empty($product_info['manufacturer_id'])) {
      $m = $this->model_catalog_manufacturer->getManufacturer((int)$product_info['manufacturer_id']);
      if (!empty($m['image'])) {
        // Размер под текущий CSS (72×72)
        $data['brand_icon'] = $this->model_tool_image->resize($m['image'], 72, 72);
      }
    }

    // Новинка — если date_available в последние 20 дней
    $data['is_new'] = false;
    if (!empty($product_info['date_available'])) {
      $days = floor((time() - strtotime($product_info['date_available'])) / 86400);
      $data['is_new'] = ($days >= 0 && $days <= 20);
    }

    // === 6. Атрибуты ===
    $attr_map = [
      201 => 'scale', 202 => 'period', 203 => 'nation',
      204 => 'difficulty_text', 205 => 'material',
      206 => 'for_kids', 207 => 'with_accessories',
      301 => 'mag_format', 302 => 'model_length_cm',
      303 => 'manufacturer_country', 304 => 'language',
      305 => 'note', 401 => 'merge_name',
      402 => 'kit', 403 => 'extra'
    ];

    $pp_attr = array_fill_keys(array_values($attr_map), null);
    $data['has_accessories'] = false;

    $attributes = $this->model_catalog_product->getProductAttributes($product_id);
    foreach ($attributes as $group) {
      foreach ($group['attribute'] as $attr) {
        $aid = (int)$attr['attribute_id'];
        if (isset($attr_map[$aid])) {
          $key = $attr_map[$aid];
          $val = trim($attr['text']);
          $pp_attr[$key] = $val;
          if ($aid === 207 && mb_strtolower($val) === 'да') {
            $data['has_accessories'] = true;
          }
        }
      }
    }
    $data['pp_attr'] = $pp_attr;

    // === 7. Плашка ===
    $data['badge_text'] = '';
    if ($data['is_out']) $data['badge_text'] = 'Нет в наличии';
    elseif ($data['is_new']) $data['badge_text'] = 'Новинка';
    elseif (!empty($pp_attr['for_kids']) && mb_strtolower($pp_attr['for_kids']) === 'да')
      $data['badge_text'] = 'Начинающим';

    // Нормализуем флаг "Начинающим", если его ещё нет
    if (!isset($data['is_beginner'])) {
        $data['is_beginner'] = (!empty($pp_attr['for_kids']) && mb_strtolower($pp_attr['for_kids']) === 'да');
    }

    // Тип диагональной плашки для модификатора класса (цвет берётся из CSS по типу)
    if (!empty($data['is_out'])) {
        $data['badge_type'] = 'outofstock';
    } elseif (!empty($data['is_new'])) {
        $data['badge_type'] = 'new';
    } elseif (!empty($data['is_beginner'])) {
        $data['badge_type'] = 'beginner';
    } else {
        $data['badge_type'] = '';
    }


    // === 8. Изображения ===
    $src_main = $product_info['image'] ?: 'placeholder.png';

    // Первое (главное) изображение: маленькая мини и большая версия
    $data['thumb_small'] = $this->model_tool_image->resize($src_main, 80, 80);
    $data['thumb_large'] = $this->model_tool_image->resize($src_main, 1200, 1200);

    // Остальные изображения: мини 64 и большие 1200
    $data['images'] = [];
    $results = $this->model_catalog_product->getProductImages($product_id);
    foreach ($results as $result) {
      if (empty($result['image'])) continue;
      $data['images'][] = [
        'thumb' => $this->model_tool_image->resize($result['image'], 80, 80),
        'large' => $this->model_tool_image->resize($result['image'], 1200, 1200),
        'alt'   => $product_info['name']
      ];
    }

    // === 9. Категория ===
    $category_display = '';
    $cats = $this->model_catalog_product->getCategories($product_id);
    if ($cats && isset($cats[0]['category_id'])) {
      $this->load->model('catalog/category');
      $cat = $this->model_catalog_category->getCategory($cats[0]['category_id']);
      if ($cat) {
        $parent = $this->model_catalog_category->getCategory($cat['parent_id']);
        if ($parent)
          $category_display = $parent['name'] . ' (' . $cat['name'] . ')';
        else
          $category_display = $cat['name'];
      }
    }
    $data['category_display'] = $category_display;

    // === 10. Варианты ===
    // Массив для Twig + флаг показа блока
    $data['variants']      = [];
    $data['variants_show'] = false;

    // 10.1 Берём значение 401 из уже собранных атрибутов
    $merge_raw = '';
    if (!empty($pp_attr['merge_name'])) {
      $merge_raw = trim((string)$pp_attr['merge_name']);
    }

    // Если 401 пустой — логика вариантов не срабатывает
    if ($merge_raw !== '') {
      // Нормализуем как в листинге: trim + mb_strtolower
      $merge_norm = mb_strtolower($merge_raw);

      $attr_merge_id = 401;
      $lang_id       = (int)$this->config->get('config_language_id');
      $cur_pid       = (int)$product_id;

      // 10.2 Ищем "живых" соседей (другие товары этой группы) с тем же 401 и quantity > 0
      // Статус/дату не фильтруем — по договорённости, магазин не держит "полумёртвые" товары.
      $q_siblings = $this->db->query(
        "SELECT 
            p.product_id,
            p.sku,
            p.quantity,
            pd.name,
            kit.text   AS kit,
            extra.text AS extra
        FROM " . DB_PREFIX . "product_attribute pa401
        JOIN " . DB_PREFIX . "product p 
          ON p.product_id = pa401.product_id
        JOIN " . DB_PREFIX . "product_description pd 
          ON pd.product_id = p.product_id 
          AND pd.language_id = " . $lang_id . "
        LEFT JOIN " . DB_PREFIX . "product_attribute kit 
          ON kit.product_id = p.product_id 
          AND kit.attribute_id = 402 
          AND kit.language_id = " . $lang_id . "
        LEFT JOIN " . DB_PREFIX . "product_attribute extra 
          ON extra.product_id = p.product_id 
          AND extra.attribute_id = 403 
          AND extra.language_id = " . $lang_id . "
        WHERE pa401.attribute_id = " . (int)$attr_merge_id . "
          AND pa401.language_id = " . $lang_id . "
          AND TRIM(LOWER(pa401.text)) = '" . $this->db->escape($merge_norm) . "'
          AND pa401.product_id <> " . $cur_pid . "
          AND p.quantity > 0"
      );

      // Есть ли вообще другие товары в наличии?
      $has_other_in_stock = (bool)$q_siblings->num_rows;

      if ($has_other_in_stock) {
      // 10.3 Собираем общий массив вариантов (текущий + соседи)
      $variants_raw = [];

      // Текущий товар как отдельный вариант
      $current_qty      = (int)$product_info['quantity'];
      $current_in_stock = $current_qty > 0;

      $current_kit   = !empty($pp_attr['kit'])   ? trim((string)$pp_attr['kit'])   : '';
      $current_extra = !empty($pp_attr['extra']) ? trim((string)$pp_attr['extra']) : '';

      if ($current_kit !== '') {
        if ($current_extra !== '') {
          $current_title = $current_kit . ' (' . $current_extra . ')';
        } else {
          $current_title = $current_kit;
        }
      } else {
        $current_title = $product_info['name'];
      }

      $variants_raw[] = [
        'product_id' => $cur_pid,
        'sku'        => (string)$product_info['sku'],
        'title'      => $current_title,
        'href'       => '',           // текущий товар — не кликаем
        'is_current' => true,
        'in_stock'   => $current_in_stock,
      ];

      // "Живые" соседи как кликабельные варианты
      foreach ($q_siblings->rows as $row) {
        $kit   = !empty($row['kit'])   ? trim((string)$row['kit'])   : '';
        $extra = !empty($row['extra']) ? trim((string)$row['extra']) : '';

        if ($kit !== '') {
          if ($extra !== '') {
            $title = $kit . ' (' . $extra . ')';
          } else {
            $title = $kit;
          }
        } else {
          $title = $row['name'];
        }

        $variants_raw[] = [
          'product_id' => (int)$row['product_id'],
          'sku'        => (string)$row['sku'],
          'title'      => $title,
          'href'       => $this->url->link('product/product', 'product_id=' . (int)$row['product_id'], true),
          'is_current' => false,
          'in_stock'   => true,  // в запросе выше уже фильтровали по quantity > 0
        ];
      }

      // 10.4 Фиксируем стабильный порядок: сортируем по title (без учёта регистра)
      usort($variants_raw, static function ($a, $b) {
        // Сначала варианты в наличии, потом без остатка
        if ($a['in_stock'] !== $b['in_stock']) {
          return $a['in_stock'] ? -1 : 1;
        }

        // Внутри группы сортируем по названию
        return strcasecmp($a['title'], $b['title']);
      });

      $data['variants'] = $variants_raw;

      // 10.5 Флаг показа блока "Варианты" для Twig
      $data['variants_show'] = true;

        // 10.6 Флаг для плашки "Есть варианты" (как в листинге)
        $data['has_variants'] = true;
        if (!isset($data['flags']) || !is_array($data['flags'])) {
          $data['flags'] = [];
        }
        $data['flags']['variants'] = true;
      }
    }
    // Если $merge_raw пустой или нет других товаров в наличии — $data['variants'] остаётся пустым,
    // $data['variants_show'] = false, плашку "Есть варианты" и блок вариантов не показываем.

    // Заголовок текущего варианта для форм отзыва/вопроса
    $current_kit   = !empty($pp_attr['kit'])   ? trim((string)$pp_attr['kit'])   : '';
    $current_extra = !empty($pp_attr['extra']) ? trim((string)$pp_attr['extra']) : '';

    if ($current_kit !== '') {
      if ($current_extra !== '') {
        $data['current_variant_title'] = $current_kit . ' (' . $current_extra . ')';
      } else {
        $data['current_variant_title'] = $current_kit;
      }
    } else {
      // Если нет атрибутов комплектации — используем название товара
      $data['current_variant_title'] = $product_info['name'];
    }

    // === 11. Отзывы и вопросы (данные для шаблона) ===
    $data['reviews']            = [];
    $data['questions']          = [];
    $data['reviews_count']      = 0;
    $data['questions_count']    = 0;
    $data['can_write_feedback'] = $this->customer->isLogged();
    $data['has_user_review']    = false;
    $data['user_review']        = null;
      // Специальная учётка, которая может оставлять ответы от магазина
    $data['is_admin_feedback_user'] = $this->customer->isLogged() && (int)$this->customer->getId() === 1;

    // Сообщения и значения форм (если были ошибки/успех при POST)
    $data['review_error']     = $review_error;
    $data['review_success']   = $review_success;
    $data['question_error']   = $question_error;
    $data['question_success'] = $question_success;

    $data['review_form']   = $review_form;
    $data['question_form'] = $question_form;

     // Ссылки на вход/регистрацию с возвратом к текущему товару
    $redirect_url = $this->url->link('product/product', 'product_id=' . $product_id, true);

    $data['login_link'] = $this->url->link(
      'account/login',
      'redirect=' . urlencode($redirect_url),
      true
    );

    $data['register_link'] = $this->url->link(
      'account/register',
      'redirect=' . urlencode($redirect_url),
      true
    );

    if ($sku !== '') {
      // --- Собираем список SKU всей группы (атрибут 401 "merge_name") ---
      $group_skus = [];

      // Текущий SKU добавляем всегда
      $group_skus[] = $sku;

      // Пробуем найти остальные товары группы по атрибуту 401
      $merge_raw = '';
      if (!empty($pp_attr['merge_name'])) {
        $merge_raw = trim((string)$pp_attr['merge_name']);
      }

      if ($merge_raw !== '') {
        $merge_norm    = mb_strtolower($merge_raw);
        $attr_merge_id = 401;
        $lang_id       = (int)$this->config->get('config_language_id');

        // Берём все товары с таким же значением 401 (без фильтра по остатку)
        $q_group = $this->db->query(
          "SELECT 
              p.sku
           FROM " . DB_PREFIX . "product_attribute pa401
           JOIN " . DB_PREFIX . "product p 
             ON p.product_id = pa401.product_id
           WHERE pa401.attribute_id = " . (int)$attr_merge_id . "
             AND pa401.language_id = " . $lang_id . "
             AND TRIM(LOWER(pa401.text)) = '" . $this->db->escape($merge_norm) . "'"
        );

        foreach ($q_group->rows as $row) {
          $row_sku = isset($row['sku']) ? trim((string)$row['sku']) : '';
          if ($row_sku === '') {
            continue;
          }

          // защищаемся от дублирования
          if (!in_array($row_sku, $group_skus, true)) {
            $group_skus[] = $row_sku;
          }
        }
      }

      // --- Списки отзывов и вопросов по всей группе SKU ---
      $reviews   = $this->model_catalog_bm_feedback->getReviewsBySkus($group_skus, $customer_id);
      $questions = $this->model_catalog_bm_feedback->getQuestionsBySkus($group_skus, $customer_id);

      // Карта источников (как на странице отзывов)
      $source_map = [
        'ozon'  => ['title' => 'Ozon',         'icon' => '/image/catalog/review_sources/ozon.jpg'],
        'wb'    => ['title' => 'Wildberries',  'icon' => '/image/catalog/review_sources/wb.jpg'],
        'avito' => ['title' => 'Avito',        'icon' => '/image/catalog/review_sources/avito.jpg'],
        'ym'    => ['title' => 'Яндекс Маркет','icon' => '/image/catalog/review_sources/ym.jpg'],
      ];

      // Подготовка отзывов для шаблона карточки товара
      $prepared_reviews = [];

      foreach ($reviews as $r) {
        // 2) Имя автора:
        // - если есть review_customer_id и имя/фамилия заполнены → используем их
        // - если review_customer_id нет → берём author_name
        // - если всё пусто → "Аноним"
        $review_customer_id = (int)($r['customer_id'] ?? 0);

        $firstname = trim((string)($r['firstname'] ?? ''));
        $lastname  = trim((string)($r['lastname'] ?? ''));
        $display_name = trim($firstname . ' ' . $lastname);

        if ($review_customer_id === 0) {
          $author_name = trim((string)($r['author_name'] ?? ''));
          if ($author_name !== '') {
            $display_name = $author_name;
          }
        }

        if ($display_name === '') {
          $display_name = 'Аноним';
        }

        $r['name'] = $display_name;

        // 1) Дата/время: убираем только 00:00:00, иначе оставляем HH:MM (без секунд)
        $raw_date = (string)($r['date_added'] ?? '');
        $display_date = '';

        if ($raw_date !== '') {
          $ts = strtotime($raw_date);
          if ($ts) {
            $time_part = date('H:i:s', $ts);

            if ($time_part === '00:00:00') {
              $display_date = date('d.m.Y', $ts);
            } else {
              $display_date = date('d.m.Y H:i', $ts);
            }
          }
        }

        // Перезаписываем date_added, чтобы шаблон ничего не менял
        if ($display_date !== '') {
          $r['date_added'] = $display_date;
        }

        // 3) Источник справа (если сторонний)     

        $source_code = trim((string)($r['source_code'] ?? ''));
        $source_url  = trim((string)($r['source_url'] ?? ''));

        $r['source_icon']  = '';
        $r['source_title'] = '';
        $r['source_url']   = '';

        if ($source_code !== '' && $source_url !== '' && isset($source_map[$source_code])) {
          $r['source_icon']  = $source_map[$source_code]['icon'];
          $r['source_title'] = $source_map[$source_code]['title'];
          $r['source_url']   = $source_url;
        }

        $prepared_reviews[] = $r;
      }

      $prepared_questions = [];

      foreach ($questions as $q) {
        $question_customer_id = (int)($q['customer_id'] ?? 0);

        $firstname = trim((string)($q['firstname'] ?? ''));
        $lastname  = trim((string)($q['lastname'] ?? ''));
        $display_name = trim($firstname . ' ' . $lastname);

        if ($question_customer_id === 0) {
          $author_name = trim((string)($q['author_name'] ?? ''));
          if ($author_name !== '') {
            $display_name = $author_name;
          }
        }

        if ($display_name === '') {
          $display_name = 'Аноним';
        }

        $q['name'] = $display_name;

        $raw_date = (string)($q['date_added'] ?? '');
        $display_date = '';

        if ($raw_date !== '') {
          $ts = strtotime($raw_date);
          if ($ts) {
            $time_part = date('H:i:s', $ts);

            if ($time_part === '00:00:00') {
              $display_date = date('d.m.Y', $ts);
            } else {
              $display_date = date('d.m.Y H:i', $ts);
            }
          }
        }

        if ($display_date !== '') {
          $q['date_added'] = $display_date;
        }

        $prepared_questions[] = $q;
      }

      $data['reviews']         = $prepared_reviews;
      $data['questions']       = $prepared_questions;
      $data['reviews_count']   = count($prepared_reviews);
      $data['questions_count'] = count($prepared_questions);
      $data['has_user_review'] = !empty($user_review);
      $data['user_review']     = $user_review;
    }

    // === 11. SEO и заголовки ===
    $this->document->setTitle($product_info['meta_title'] ?: $product_info['name']);
    $this->document->setDescription($product_info['meta_description']);
    $this->document->setKeywords($product_info['meta_keyword']);

    // Общие области макета (обязательны для темы Materialize)
    $data['column_left']   = $this->load->controller('common/column_left');
    $data['column_right']  = $this->load->controller('common/column_right');
    $data['content_top']   = $this->load->controller('common/content_top');
    $data['content_bottom']= $this->load->controller('common/content_bottom');
    $data['footer']        = $this->load->controller('common/footer');
    $data['header']        = $this->load->controller('common/header');

    // Бейдж «Есть варианты» для карточки
    if (!isset($data['has_variants'])) {
        // если ранее уже собран массив $data['variants'], используем его;
        // иначе проверь источник (атрибут 401, группировка и т. п.)
        $data['has_variants'] = !empty($data['variants']);
    }

    // === BM: поддержка контроллера кнопки корзины на странице товара ===========
    // Используем уже существующий product_id, если он есть в $data:
    $pid = isset($data['product_id'])
      ? (int)$data['product_id']
      : (isset($product_info['product_id']) ? (int)$product_info['product_id'] : 0);

    // Максимум для плюса (если не задан ранее)
    if (!isset($data['max_qty']) && isset($product_info['quantity'])) {
      $data['max_qty'] = max(0, (int)$product_info['quantity']);
    }

    // Учитываем ли остатки (если не задано ранее)
    if (!isset($data['track_stock']) && array_key_exists('subtract', $product_info)) {
      $data['track_stock'] = !empty($product_info['subtract']);
    }

    // Сколько уже лежит в корзине и key позиции (если не заполнено ранее)
    if (!isset($data['in_cart_qty']) || !isset($data['cart_key'])) {
      $in_cart_qty = 0;
      $cart_key    = '';

      if (!empty($this->cart) && $pid > 0) {
        foreach ($this->cart->getProducts() as $p) {
          if ((int)$p['product_id'] === $pid) {
            $in_cart_qty += (int)$p['quantity'];
            if ($cart_key === '' && !empty($p['key'])) {
              $cart_key = (string)$p['key'];
            }
          }
        }
      }

      if (!isset($data['in_cart_qty'])) $data['in_cart_qty'] = $in_cart_qty;
      if (!isset($data['cart_key']))    $data['cart_key']    = $cart_key;
    }
    // ===========================================================================


    // === 12. Рендерим ===
    $this->response->setOutput($this->load->view('product/product', $data));
  }
}