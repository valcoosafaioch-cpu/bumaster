<?php
class ControllerCommonHome extends Controller {
    public function index() {
        // 1. SEO-мета с настроек магазина
        $this->document->setTitle($this->config->get('config_meta_title'));
        $this->document->setDescription($this->config->get('config_meta_description'));
        $this->document->setKeywords($this->config->get('config_meta_keyword'));

        // Канонический URL для главной
        if (isset($this->request->get['route'])) {
            $this->document->addLink($this->config->get('config_url'), 'canonical');
        }

        // Инициализируем массив данных для шаблона
        $data = array();

        // Базовый URL (нужен для JSON-LD в home.twig)
        if (!empty($this->request->server['HTTPS']) && $this->request->server['HTTPS'] != 'off') {
            $data['base'] = $this->config->get('config_ssl');
        } else {
            $data['base'] = $this->config->get('config_url');
        }

        // 2. Модели, которые пригодятся для главной
        $this->load->model('catalog/category');
        $this->load->model('catalog/product');
        $this->load->model('catalog/manufacturer');
        $this->load->model('catalog/bm_feedback');
        $this->load->model('setting/setting');
        $this->load->model('tool/image');

        // 3. Скелет данных для home.twig

        // Левый столбец
        //  A3: дерево категорий с количеством товаров в наличии
        $product_counts = $this->getCategoryProductCountsInStock();
        $data['categories_tree'] = $this->buildCategoriesTree(0, $product_counts);   // A3 Категории

        // Правый столбец
        $data['bm_home_contacts'] = array();  // A4.1 Контакты магазина
        $data['store_reviews']    = array();  // A4.2 Отзывы о магазине
        $data['account_block']    = array();  // A4.3 Блок аккаунта / вход

        // --- A4.3 Блок аккаунта / вход ---
        $is_logged = !empty($data['logged']) ? $data['logged'] : $this->customer->isLogged();

        $firstname = '';
        if ($is_logged) {
            $firstname = trim($this->customer->getFirstName());
            $lastname  = trim($this->customer->getLastName());

            $fullname = trim($firstname . ' ' . $lastname);
        }

        $data['account_block'] = array(
            'is_logged'     => $is_logged,
            'fullname' => $fullname,
            'href_account'  => $this->url->link('account/account', '', true),
            'href_login'    => $this->url->link('account/login', '', true),
            'href_register' => $this->url->link('account/register', '', true),
        );

        // Центральная колонка
        $data['bm_home_text']     = '';       // A5 Текст / приветствие
        $data['bm_home_slides']   = array();  // A6 Слайдер
        $data['latest_products']  = array();  // A7.1 Последние поступления
        $data['special_products'] = array();  // A7.2 Спецпредложения
        $data['brands']           = array();  // A7.3 Производители (бренды)

        // ---------------------------------------------------------------------
        // B3. Чтение настроек модуля "Бумажный Мастер — Главная" (bm_home)
        // ---------------------------------------------------------------------
        $bm_home = $this->model_setting_setting->getSetting('bm_home');

        // --- Контакты (A4.1) ---
        $contacts         = array(); // строки (ТГ ЛС, email, правила, о магазине)
        $contact_banners  = array(); // баннеры (Telegram-группа, VK-группа)

        // Базовый URL для картинок
        $server = $data['base'];

        // VK: группа (баннер)
        $vk_group = isset($bm_home['bm_home_vk_group']) ? trim($bm_home['bm_home_vk_group']) : '';
        $vk_group_image = isset($bm_home['bm_home_vk_group_image']) ? trim($bm_home['bm_home_vk_group_image']) : '';

        if ($vk_group !== '' && $vk_group_image !== '') {
            $contact_banners[] = array(
                'type'   => 'vk_group',
                'href'   => $vk_group,
                'image'  => $server . 'image/' . $vk_group_image,
                'target' => '_blank',
            );
        }

        // Telegram: группа (баннер)
        $tg_group = isset($bm_home['bm_home_telegram_group']) ? trim($bm_home['bm_home_telegram_group']) : '';
        $tg_group_image = isset($bm_home['bm_home_telegram_group_image']) ? trim($bm_home['bm_home_telegram_group_image']) : '';

        if ($tg_group !== '' && $tg_group_image !== '') {
            $contact_banners[] = array(
                'type'   => 'telegram_group',
                'href'   => $tg_group,
                'image'  => $server . 'image/' . $tg_group_image,
                'target' => '_blank',
            );
        }

        // Telegram: личные сообщения / бот (строка с иконкой)
        $tg_dm      = isset($bm_home['bm_home_telegram_dm']) ? trim($bm_home['bm_home_telegram_dm']) : '';
        $tg_dm_icon = isset($bm_home['bm_home_telegram_dm_icon']) ? trim($bm_home['bm_home_telegram_dm_icon']) : '';

        if ($tg_dm !== '') {
            $contacts[] = array(
                'type'   => 'telegram_dm',
                'label'  => 'Написать нам в Telegram',
                'href'   => $tg_dm,
                'icon'   => ($tg_dm_icon !== '' ? $server . 'image/' . $tg_dm_icon : ''),
                'target' => '_blank',
            );
        }

        // Ссылка на страницу отзывов (бывшее поле email)
        $reviews_link = isset($bm_home['bm_home_email']) ? trim($bm_home['bm_home_email']) : '';

        if ($reviews_link !== '') {
            $contacts[] = array(
                'type'   => 'store_reviews',
                'label'  => 'Отзывы о магазине',
                'href'   => $reviews_link,
                'icon'   => '',
                'target' => '',
            );
        }

        // Заголовок "Обратная связь" — ссылка на страницу формы
        $contact_link = isset($bm_home['bm_home_contact_link']) ? trim($bm_home['bm_home_contact_link']) : '';
        $data['bm_home_contact_link'] = $contact_link;

        // Ссылка на "Правила доставки" (строка без иконки)
        $delivery_link = isset($bm_home['bm_home_delivery_link']) ? trim($bm_home['bm_home_delivery_link']) : '';
        if ($delivery_link !== '') {
            $contacts[] = array(
                'type'   => 'page_delivery',
                'label'  => 'Правила доставки',
                'href'   => $delivery_link,
                'icon'   => '',
                'target' => '',
            );
        }

        // Ссылка на страницу "О магазине" (строка без иконки)
        $about_link = isset($bm_home['bm_home_about_link']) ? trim($bm_home['bm_home_about_link']) : '';
        if ($about_link !== '') {
            $contacts[] = array(
                'type'   => 'page_about',
                'label'  => 'О магазине',
                'href'   => $about_link,
                'icon'   => '',
                'target' => '',
            );
        }

        // В шаблон
        $data['bm_home_contacts_banners'] = $contact_banners;
        $data['bm_home_contacts']         = $contacts;
        // Для совместимости, если где-то ещё использовался старый ключ
        $data['contacts']                 = $contacts;

        // --- Текст A5 ---
        $bm_home_text_raw  = isset($bm_home['bm_home_text']) ? $bm_home['bm_home_text'] : '';
        $bm_home_text_html = html_entity_decode($bm_home_text_raw, ENT_QUOTES, 'UTF-8');

        // Обрезаем «пустую» разметку summernote (например, <p><br></p>, неразрывные пробелы и т.п.)
        $home_text_clean = trim(str_replace("\xC2\xA0", ' ', strip_tags($bm_home_text_html)));

        if ($home_text_clean === '') {
            // Считаем блок пустым — не показываем его на главной
            $bm_home_text_html = '';
        }

        $data['bm_home_text'] = $bm_home_text_html;
        $data['home_text']    = $bm_home_text_html;

        // --- Слайдер A6 (массив структур image/title/link/status/sort_order) ---
        $slides_raw = array();
        if (isset($bm_home['bm_home_slides']) && is_array($bm_home['bm_home_slides'])) {
            $slides_raw = $bm_home['bm_home_slides'];
        }

        $slides = array();

        foreach ($slides_raw as $slide) {
            if (!is_array($slide)) {
                continue;
            }

            $status = isset($slide['status']) ? (int)$slide['status'] : 1;
            if (!$status) {
                continue;
            }

            $image      = isset($slide['image']) ? $slide['image'] : '';
            $title      = isset($slide['title']) ? $slide['title'] : '';
            $link       = isset($slide['link']) ? $slide['link'] : '';
            $sort_order = isset($slide['sort_order']) ? (int)$slide['sort_order'] : 0;

            $thumb = '';
            if ($image && is_file(DIR_IMAGE . $image)) {
                // Пока жёстко задаём размер; при необходимости подправим под верстку A6
                $thumb = $this->model_tool_image->resize($image, 1200, 400);
            }

            $slides[] = array(
                'image'      => $image,
                'thumb'      => $thumb,
                'title'      => $title,
                'link'       => $link,
                'status'     => $status,
                'sort_order' => $sort_order,
            );
        }

        if ($slides) {
            usort($slides, function ($a, $b) {
                if ($a['sort_order'] == $b['sort_order']) {
                    return 0;
                }

                return ($a['sort_order'] < $b['sort_order']) ? -1 : 1;
            });
        }

        $data['bm_home_slides'] = $slides;
        $data['slides']         = $slides;

        // ---------------------------------------------------------------------
        // A7.1 Последние поступления (date_available DESC)
        // ---------------------------------------------------------------------
        $latest_filter = array(
            // как в all.php для sort=date_desc
            'sort'         => 'p.date_available DESC, LCASE(pd.name) ASC',
            'sort_key'     => 'date_desc',
            'limit'        => 10,
            'show_archive' => 0,
        );

        $latest_results = $this->model_catalog_product->getProductsAdvanced($latest_filter);

        // Карта корзины product_id → { key, qty }
        $cart_map = array();
        foreach ($this->cart->getProducts() as $cp) {
            $pid = (int)$cp['product_id'];
            $cart_map[$pid] = array(
                'key' => isset($cp['cart_id']) ? $cp['cart_id'] : (isset($cp['key']) ? $cp['key'] : ''),
                'qty' => (int)$cp['quantity'],
            );
        }

        // bulk-выборка спеццен/скидок только для нужных товаров (для скидки % и цен)
        $special_map  = array();
        $discount_map = array();
        $ids = array_values(array_filter(array_map('intval', array_column($latest_results, 'product_id'))));

        if ($ids) {
            $customer_group_id = $this->customer->isLogged()
                ? (int)$this->customer->getGroupId()
                : (int)$this->config->get('config_customer_group_id');

            $sql_sp = "
                SELECT ps.product_id, MIN(ps.price) AS price
                FROM " . DB_PREFIX . "product_special ps
                WHERE ps.product_id IN (" . implode(',', $ids) . ")
                AND ps.customer_group_id = " . (int)$customer_group_id . "
                AND (ps.date_start = '0000-00-00' OR ps.date_start <= NOW())
                AND (ps.date_end   = '0000-00-00' OR ps.date_end   >= NOW())
                GROUP BY ps.product_id
            ";
            foreach ($this->db->query($sql_sp)->rows as $r) {
                $special_map[(int)$r['product_id']] = (float)$r['price'];
            }

            $sql_dc = "
                SELECT pd.product_id, MIN(pd.price) AS price
                FROM " . DB_PREFIX . "product_discount pd
                WHERE pd.product_id IN (" . implode(',', $ids) . ")
                AND pd.customer_group_id = " . (int)$customer_group_id . "
                AND pd.quantity <= 1
                AND (pd.date_start = '0000-00-00' OR pd.date_start <= NOW())
                AND (pd.date_end   = '0000-00-00' OR pd.date_end   >= NOW())
                GROUP BY pd.product_id
            ";
            foreach ($this->db->query($sql_dc)->rows as $r) {
                $discount_map[(int)$r['product_id']] = (float)$r['price'];
            }
        }

        // ---------------------------------------------------------------------
        // Helper: сборка массива для _card.twig (используется в блоках A7.*)
        // ---------------------------------------------------------------------
        $clean_price = function ($s) {
            $s = preg_replace('/[^\d\s\.]/u', '', (string)$s);      // оставить цифры/пробелы/разделители
            $s = trim(preg_replace('/\s+/u', ' ', $s));             // схлопнуть пробелы
            $s = preg_replace('/([.])00\b/u', '', $s);              // убрать .00 в конце
            return $s !== '' ? ($s . ' ₽') : '';
        };

        $bm_build_cards = function (array $results, array $variants_map, array $special_map, array $discount_map, array $cart_map) use ($clean_price) {
            $cards = array();

            foreach ($results as $result) {
                $pid = (int)$result['product_id'];

                // Доп. данные товара (manufacturer/model/tax_class_id)
                $p = $this->model_catalog_product->getProduct($pid);

                $image = !empty($result['image'])
                    ? $this->model_tool_image->resize($result['image'], 300, 300)
                    : $this->model_tool_image->resize('placeholder.png', 300, 300);

                $product_is_out = ((int)$result['quantity'] <= 0);

                // Новая цена: сначала SPECIAL, иначе DISCOUNT qty<=1
                $special_num = null;
                if (isset($special_map[$pid])) {
                    $special_num = $special_map[$pid];
                } elseif (isset($discount_map[$pid])) {
                    $special_num = $discount_map[$pid];
                }

                $tax_class_id = !empty($p['tax_class_id']) ? (int)$p['tax_class_id'] : 0;

                $price_str = '';
                if (!is_null($result['price'])) {
                    $price_str = $this->currency->format(
                        $this->tax->calculate($result['price'], $tax_class_id, $this->config->get('config_tax')),
                        $this->session->data['currency']
                    );
                }

                $special_str = '';
                if ($special_num !== null) {
                    $special_str = $this->currency->format(
                        $this->tax->calculate($special_num, $tax_class_id, $this->config->get('config_tax')),
                        $this->session->data['currency']
                    );
                }

                $price_clean     = $price_str ? $clean_price($price_str) : '';
                $special_clean   = $special_str ? $clean_price($special_str) : '';
                $price_old_clean = $special_str ? $clean_price($price_str) : '';

                // Скидка %: только для товаров в наличии
                $discount_percent = 0;
                if (!$product_is_out && $special_num !== null && (float)$result['price'] > 0) {
                    $discount_percent = (int)ceil((1 - ($special_num / (float)$result['price'])) * 100);
                    if ($discount_percent < 0) $discount_percent = 0;
                    if ($discount_percent > 99) $discount_percent = 99;
                }

                // Данные для контролов корзины
                $in_cart_qty = isset($cart_map[$pid]) ? (int)$cart_map[$pid]['qty'] : 0;
                $cart_key    = isset($cart_map[$pid]) ? (string)$cart_map[$pid]['key'] : '';
                $max_qty     = max(0, (int)$result['quantity']);

                // Обязательные опции?
                $has_required_options = false;
                $options = $this->model_catalog_product->getProductOptions($pid);
                foreach ($options as $opt) {
                    if (!empty($opt['required'])) {
                        $has_required_options = true;
                        break;
                    }
                }

                // Атрибуты для карточки
                $scale = '';
                $completeness_text = '';
                $has_accessories = false;
                $note_text = '';
                $attrs = $this->model_catalog_product->getProductAttributes($pid);

                foreach ($attrs as $group) {
                    if (empty($group['attribute'])) continue;

                    foreach ($group['attribute'] as $attr) {
                        $name = mb_strtolower($attr['name']);
                        $val  = mb_strtolower($attr['text']);
                        $name = str_replace("\xC2\xA0", ' ', $name);
                        $val  = str_replace("\xC2\xA0", ' ', $val);
                        $name = trim(preg_replace('/\s+/u', ' ', $name));
                        $val  = trim(preg_replace('/\s+/u', ' ', $val));

                        if ($scale === '' && in_array($name, array('масштаб', 'масштаб модели', 'scale'), true)) {
                            $scale = $val;
                        }

                        if ($completeness_text === '' && in_array($name, array(
                            'комплектация', 'состав набора', 'комплектность', 'completeness', 'equipment', 'set contents'
                        ), true)) {
                            $completeness_text = $val;
                        }

                        if ($note_text === '') {
                            $name_lc   = trim(preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', mb_strtolower($attr['name']))));
                            $raw_value = trim(preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', (string)$attr['text'])));
                            if (in_array($name_lc, array('примечание', 'прим.', 'note', 'notes'), true) && $raw_value !== '') {
                                $note_text = $raw_value;
                            }
                        }

                        if (!$has_accessories && in_array($name, array(
                            'с аксессуарами',
                            'с аксессуарами комплект',
                            'аксессуары в комплекте',
                            'аксессуары',
                            'with accessories',
                            'accessories included'
                        ), true)) {
                            if (in_array($val, array('да', 'yes', 'true', '1', 'есть'), true)) {
                                $has_accessories = true;
                            }
                        }
                    }
                }

                // Иконка производителя
                $manufacturer_icon = '';
                if (!empty($p['manufacturer_id'])) {
                    $m = $this->model_catalog_manufacturer->getManufacturer((int)$p['manufacturer_id']);
                    if (!empty($m['image'])) {
                        $manufacturer_icon = $this->model_tool_image->resize($m['image'], 18, 18);
                    }
                }

                // Плашка1: Нет в наличии / Новинка / Начинающим
                $badge1 = null;
                if ((int)$result['quantity'] <= 0) {
                    $badge1 = array(
                        'text'  => 'Нет в наличии',
                        'class' => 'product-ribbon--out',
                    );
                }

                if (!$badge1) {
                    $date_available = !empty($result['date_available'])
                        ? strtotime($result['date_available'])
                        : (!empty($result['date_added']) ? strtotime($result['date_added']) : false);

                    if ($date_available !== false) {
                        $days = (time() - $date_available) / 86400;
                        if ($days <= 20) {
                            $badge1 = array(
                                'text'  => 'Новинка',
                                'class' => 'product-ribbon--new',
                            );
                        }
                    }
                }

                if (!$badge1) {
                    $is_for_kids = false;
                    foreach ($attrs as $group) {
                        if (empty($group['attribute'])) {
                            continue;
                        }
                        foreach ($group['attribute'] as $attr) {
                            $name2 = mb_strtolower(trim($attr['name']));
                            $val2  = mb_strtolower(trim($attr['text']));
                            if (in_array($name2, array('для детей', 'подходит для детей', 'kids', 'for kids'), true)) {
                                if (in_array($val2, array('да', 'yes', 'true', '1'), true)) {
                                    $is_for_kids = true;
                                    break 2;
                                }
                            }
                        }
                    }
                    if ($is_for_kids) {
                        $badge1 = array(
                            'text'  => 'Начинающим',
                            'class' => 'product-ribbon--beginner',
                        );
                    }
                }

                $cards[] = array(
                    'product_id'            => $pid,
                    'thumb'                 => $image,
                    'name'                  => $result['name'],
                    'manufacturer'          => !empty($p['manufacturer']) ? $p['manufacturer'] : '',
                    'manufacturer_icon'     => $manufacturer_icon,
                    'model'                 => !empty($p['model']) ? $p['model'] : '',
                    'scale'                 => $scale,
                    'completeness_text'     => $completeness_text,
                    'note_text'             => $note_text,
                    'has_accessories'       => $has_accessories,
                    'has_variants'          => !empty($variants_map[$pid]),
                    'badge1'                => $badge1,
                    'discount_percent'      => $discount_percent,
                    'price_clean'           => $price_clean,
                    'special_clean'         => $special_clean,
                    'price_old_clean'       => $price_old_clean,
                    'is_out'                => $product_is_out,
                    'in_cart_qty'           => $in_cart_qty,
                    'cart_key'              => $cart_key,
                    'max_qty'               => $max_qty,
                    'has_required_options'  => $has_required_options,
                    'quantity'              => (int)$result['quantity'],
                    'href'                  => $this->url->link('product/product', 'product_id=' . $pid),
                );
            }

            return $cards;
        };

        // A7.1: варианты (атрибут 401) — флаг, есть ли другие товары с таким же значением
        $variant_attr_id = 401;
        $variant_flags = array();
        if ($ids) {
            $sql_attr = "
                SELECT pa.product_id,
                    LOWER(TRIM(REPLACE(REPLACE(pa.text, '\xC2\xA0',' '), '  ', ' '))) AS text_norm
                FROM " . DB_PREFIX . "product_attribute pa
                WHERE pa.attribute_id = " . (int)$variant_attr_id . "
                AND pa.language_id = " . (int)$this->config->get('config_language_id') . "
                AND pa.product_id IN (" . implode(',', $ids) . ")
            ";
            $rows = $this->db->query($sql_attr)->rows;

            $texts = array();
            foreach ($rows as $r) {
                if (!empty($r['text_norm'])) {
                    $texts[(int)$r['product_id']] = $r['text_norm'];
                }
            }

            if ($texts) {
                $unique = array_values(array_unique(array_values($texts)));
                $conds = array();
                foreach ($unique as $t) {
                    $conds[] = "LOWER(TRIM(REPLACE(REPLACE(pa.text, '\xC2\xA0',' '), '  ', ' '))) = '" . $this->db->escape($t) . "'";
                }

                // Считаем количество товаров в наличии по каждому text_norm
                $sql_cnt = "
                    SELECT
                        LOWER(TRIM(REPLACE(REPLACE(pa.text, '\xC2\xA0',' '), '  ', ' '))) AS text_norm,
                        COUNT(DISTINCT pa.product_id) AS total
                    FROM " . DB_PREFIX . "product_attribute pa
                    LEFT JOIN " . DB_PREFIX . "product p ON (p.product_id = pa.product_id)
                    WHERE pa.attribute_id = " . (int)$variant_attr_id . "
                    AND pa.language_id = " . (int)$this->config->get('config_language_id') . "
                    AND p.status = '1'
                    AND p.date_available <= NOW()
                    AND p.quantity > 0
                    AND (" . implode(' OR ', $conds) . ")
                    GROUP BY text_norm
                ";
                $counts = array();
                foreach ($this->db->query($sql_cnt)->rows as $r2) {
                    $counts[(string)$r2['text_norm']] = (int)$r2['total'];
                }

                foreach ($texts as $pid => $t) {
                    $variant_flags[(int)$pid] = (isset($counts[$t]) && (int)$counts[$t] > 1);
                }
            }
        }

        // Нормализатор цены: убрать копейки и валютный знак слева, поставить " ₽" справа
        $clean_price = function ($s) {
            // оставляем только цифры и разделители
            $s = preg_replace('/[^\d\.,]/u', '', (string)$s);

            // убираем дробную часть (.00 / ,00)
            $s = preg_replace('/([.,])00\b/u', '', $s);

            // берём только целую часть (до разделителя, если он остался)
            $parts = preg_split('/[.,]/u', $s);
            $int = isset($parts[0]) ? preg_replace('/[^\d]/u', '', $parts[0]) : '';

            if ($int === '') {
                return '';
            }

            // группировка тысяч пробелом: 1000 -> 1 000
            $formatted = number_format((int)$int, 0, '.', ' ');

            return $formatted . ' ₽';
        };

        $data['latest_products'] = array();

        $data['latest_products'] = $bm_build_cards($latest_results, $variant_flags, $special_map, $discount_map, $cart_map);

        $data['latest_all_href'] = $this->url->link('product/all', 'sort=date_desc');

        // ---------------------------------------------------------------------
        // A7.2 Спецпредложения (со скидками)
        // - берём через discount_only=1 (логика как у фильтра F3)
        // - если товаров < 6 — блок не рисуем
        // ---------------------------------------------------------------------
        $special_filter = array(
            'discount_only' => 1,
            'sort'          => 'p.date_available DESC, LCASE(pd.name) ASC',
            'sort_key'      => 'date_desc',
            'limit'         => 10,
            'show_archive'  => 0,
        );

        $special_results = $this->model_catalog_product->getProductsAdvanced($special_filter);

        // Если меньше 6 — не показываем блок вообще
        if (is_array($special_results) && count($special_results) >= 6) {
            $data['special_products'] = array();

            $special_ids = array_values(array_filter(array_map('intval', array_column($special_results, 'product_id'))));

            // bulk-выборка спеццен/скидок только для нужных товаров (для скидки % и цен)
            $special_special_map  = array();
            $special_discount_map = array();

            if ($special_ids) {
                $customer_group_id = $this->customer->isLogged()
                    ? (int)$this->customer->getGroupId()
                    : (int)$this->config->get('config_customer_group_id');

                $sql_sp2 = "
                    SELECT ps.product_id, MIN(ps.price) AS price
                    FROM " . DB_PREFIX . "product_special ps
                    WHERE ps.product_id IN (" . implode(',', $special_ids) . ")
                    AND ps.customer_group_id = " . (int)$customer_group_id . "
                    AND (ps.date_start = '0000-00-00' OR ps.date_start <= NOW())
                    AND (ps.date_end   = '0000-00-00' OR ps.date_end   >= NOW())
                    GROUP BY ps.product_id
                ";
                foreach ($this->db->query($sql_sp2)->rows as $r) {
                    $special_special_map[(int)$r['product_id']] = (float)$r['price'];
                }

                $sql_dc2 = "
                    SELECT pd.product_id, MIN(pd.price) AS price
                    FROM " . DB_PREFIX . "product_discount pd
                    WHERE pd.product_id IN (" . implode(',', $special_ids) . ")
                    AND pd.customer_group_id = " . (int)$customer_group_id . "
                    AND pd.quantity <= 1
                    AND (pd.date_start = '0000-00-00' OR pd.date_start <= NOW())
                    AND (pd.date_end   = '0000-00-00' OR pd.date_end   >= NOW())
                    GROUP BY pd.product_id
                ";
                foreach ($this->db->query($sql_dc2)->rows as $r) {
                    $special_discount_map[(int)$r['product_id']] = (float)$r['price'];
                }
            }

            // A7.2: варианты (атрибут 401) — флаг, есть ли другие товары с таким же значением
            $variant_attr_id = 401;
            $special_variant_flags = array();

            if ($special_ids) {
                $sql_attr2 = "
                    SELECT pa.product_id,
                        LOWER(TRIM(REPLACE(REPLACE(pa.text, '\xC2\xA0',' '), '  ', ' '))) AS text_norm
                    FROM " . DB_PREFIX . "product_attribute pa
                    WHERE pa.attribute_id = " . (int)$variant_attr_id . "
                    AND pa.language_id = " . (int)$this->config->get('config_language_id') . "
                    AND pa.product_id IN (" . implode(',', $special_ids) . ")
                ";
                $rows2 = $this->db->query($sql_attr2)->rows;

                $texts2 = array();
                foreach ($rows2 as $r) {
                    if (!empty($r['text_norm'])) {
                        $texts2[(int)$r['product_id']] = $r['text_norm'];
                    }
                }

                if ($texts2) {
                    $unique2 = array_values(array_unique(array_values($texts2)));
                    $conds2 = array();
                    foreach ($unique2 as $t) {
                        $conds2[] = "LOWER(TRIM(REPLACE(REPLACE(pa.text, '\xC2\xA0',' '), '  ', ' '))) = '" . $this->db->escape($t) . "'";
                    }

                    $sql_cnt2 = "
                        SELECT
                            LOWER(TRIM(REPLACE(REPLACE(pa.text, '\xC2\xA0',' '), '  ', ' '))) AS text_norm,
                            COUNT(DISTINCT pa.product_id) AS total
                        FROM " . DB_PREFIX . "product_attribute pa
                        LEFT JOIN " . DB_PREFIX . "product p ON (p.product_id = pa.product_id)
                        WHERE pa.attribute_id = " . (int)$variant_attr_id . "
                        AND pa.language_id = " . (int)$this->config->get('config_language_id') . "
                        AND p.status = '1'
                        AND p.date_available <= NOW()
                        AND p.quantity > 0
                        AND (" . implode(' OR ', $conds2) . ")
                        GROUP BY text_norm
                    ";
                    $counts2 = array();
                    foreach ($this->db->query($sql_cnt2)->rows as $r2) {
                        $counts2[(string)$r2['text_norm']] = (int)$r2['total'];
                    }

                    foreach ($texts2 as $pid => $t) {
                        $special_variant_flags[(int)$pid] = (isset($counts2[$t]) && (int)$counts2[$t] > 1);
                    }
                }
            }

            $data['special_products'] = $bm_build_cards($special_results, $special_variant_flags, $special_special_map, $special_discount_map, $cart_map);

            $data['special_all_href'] = $this->url->link('product/all', 'discount=1');
        } else {
            // Явно гасим блок (чтобы twig точно его не рисовал)
            $data['special_products'] = array();
        }

        // ---------------------------------------------------------------------
        // A7.3 Производители (бренды)
        // Важно (правила проекта): "в наличии" = только quantity > 0.
        // ---------------------------------------------------------------------
        $brands = $this->model_catalog_manufacturer->getManufacturersInStock(array(
            'sort'  => 'sort_order',
            'order' => 'ASC',
        ));

        $data['brands'] = array();

        foreach ($brands as $brand) {
            $image = isset($brand['image']) ? (string)$brand['image'] : '';

            if ($image && is_file(DIR_IMAGE . $image)) {
                $thumb = $this->model_tool_image->resize($image, 120, 120);
            } else {
                $thumb = $this->model_tool_image->resize('placeholder.png', 120, 120);
            }

            $data['brands'][] = array(
                'manufacturer_id' => (int)$brand['manufacturer_id'],
                'name'            => (string)$brand['name'],
                'thumb'           => $thumb,
                'href'            => $this->url->link('product/all', 'manufacturer_id=' . (int)$brand['manufacturer_id']),
            );
        }

        // ---------------------------------------------------------------------
        // Общие части макета (шапка/футер и стандартные зоны темы)
        // ---------------------------------------------------------------------
        $data['column_left']    = $this->load->controller('common/column_left');
        $data['column_right']   = $this->load->controller('common/column_right');
        $data['content_top']    = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer']         = $this->load->controller('common/footer');
        $data['header']         = $this->load->controller('common/header');

        // Рендерим нашу новую главную
        $this->response->setOutput($this->load->view('common/home', $data));
    }

        /**
     * Считает количество товаров "в наличии" по категориям.
     *
     * Важно:
     * - Берём только товары с quantity > 0.
     * - Учитываем только текущий магазин (product_to_store).
     *
     * @return array [category_id => total]
     */
    private function getCategoryProductCountsInStock() {
        $result = array();

        $sql = "SELECT p2c.category_id, COUNT(*) AS total
                FROM " . DB_PREFIX . "product p
                INNER JOIN " . DB_PREFIX . "product_to_category p2c
                    ON (p.product_id = p2c.product_id)
                INNER JOIN " . DB_PREFIX . "product_to_store p2s
                    ON (p.product_id = p2s.product_id)
                WHERE p.quantity > 0
                  AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
                GROUP BY p2c.category_id";

        $query = $this->db->query($sql);

        foreach ($query->rows as $row) {
            $result[(int)$row['category_id']] = (int)$row['total'];
        }

        return $result;
    }

	/**
	 * BM HOME · A3 — дерево категорий с количеством
	 *
	 * Собирает дерево категорий и считает количество товаров
	 * (в наличии) в каждой ветке.
	 */
	private function buildCategoriesTree($parent_id = 0, array $product_counts = array(), $level = 1) {
		$tree = array();

		$categories = $this->model_catalog_category->getCategories($parent_id);

		foreach ($categories as $category) {
			$category_id = (int)$category['category_id'];

			// Кол-во товаров в текущей категории
			$self_count = isset($product_counts[$category_id]) ? (int)$product_counts[$category_id] : 0;

			// Рекурсивно считаем дочерние
			$children = $this->buildCategoriesTree($category_id, $product_counts, $level + 1);

			$children_count = 0;
			foreach ($children as $child) {
				$children_count += (int)$child['count'];
			}

			$count = $self_count + $children_count;

			// Категории без товаров не показываем
			if ($count <= 0) {
				continue;
			}

			// Список ID для параметра cat: родитель + все дочерние категории
            // (даже если в них сейчас нет товаров в наличии)
            $selected_ids = array($category_id);

            $descendant_ids = $this->getAllDescendantCategoryIds($category_id);
            if (!empty($descendant_ids)) {
                $selected_ids = array_merge($selected_ids, $descendant_ids);
            }

            $selected_ids = array_values(array_unique(array_map('intval', $selected_ids)));

            $tree[] = array(
                'category_id' => $category_id,
                'name'        => $category['name'],
                'count'       => $count,
                'href'        => $this->url->link(
                    'product/all',
                    'cat=' . implode(',', $selected_ids)
                ),
                'children'    => $children,
            );
		}

		return $tree;
	}

    /**
     * Возвращает список ВСЕХ дочерних категорий для заданного category_id,
     * независимо от наличия товаров (чистая структура справочника категорий).
     *
     * Используется для формирования параметра cat при переходе из блока A3.
     *
     * @param int $category_id
     * @return int[]
     */
    private function getAllDescendantCategoryIds($category_id) {
        $ids = array();
        $category_id = (int)$category_id;

        // Берём всех прямых потомков
        $children = $this->model_catalog_category->getCategories($category_id);

        foreach ($children as $child) {
            $child_id = (int)$child['category_id'];
            $ids[] = $child_id;

            // Рекурсивно добавляем их потомков
            $child_descendants = $this->getAllDescendantCategoryIds($child_id);
            if (!empty($child_descendants)) {
                $ids = array_merge($ids, $child_descendants);
            }
        }

        return array_values(array_unique($ids));
    }
}
