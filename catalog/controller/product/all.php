<?php
class ControllerProductAll extends Controller {
	public function index() {
		$this->document->addStyle('catalog/view/theme/materialize/stylesheet/all.css');
		$this->load->language('product/category'); // возьмём переводы кнопок/пагинации из стандартного набора
		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('tool/image');

		// F2 AJAX: вернуть карту корзины и суммарное количество товаров
		if (isset($this->request->get['ajax']) && $this->request->get['ajax'] === 'cartmap') {
			$map = [];
			$total_qty = 0;
			foreach ($this->cart->getProducts() as $cp) {
				$pid = (int)$cp['product_id'];
				$qty = (int)$cp['quantity'];
				$map[$pid] = [
					'key' => isset($cp['cart_id']) ? $cp['cart_id'] : (isset($cp['key']) ? $cp['key'] : ''),
					'qty' => $qty
				];
				$total_qty += $qty;
			}
			$this->response->addHeader('Content-Type: application/json; charset=utf-8');
			$this->response->setOutput(json_encode(['success' => true, 'map' => $map, 'total_qty' => $total_qty]));
			return;
		}

		// F2 AJAX: HTML мини-корзины (независимо от темы)
		if (isset($this->request->get['ajax']) && $this->request->get['ajax'] === 'minicart') {
			$this->load->model('tool/image');

			$items_html = '';
			$subtotal = 0;

			foreach ($this->cart->getProducts() as $p) {
				$pid   = (int)$p['product_id'];
				$name  = $p['name'];
				$qty   = (int)$p['quantity'];
				$img   = !empty($p['image']) ? $this->model_tool_image->resize($p['image'], 50, 50) : '';
				$line_total = (float)$p['total'];           // цена * qty от ядра
				$subtotal  += $line_total;

				$price_fmt = $this->currency->format($line_total, $this->session->data['currency']);

				$items_html .=
					'<div class="bm-mini-item">' .
					($img ? '<img class="bm-mini-thumb" src="' . $img . '" alt="">' : '') .
					'<div class="bm-mini-meta">' .
						'<div class="bm-mini-name">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</div>' .
						'<div class="bm-mini-qty">x ' . $qty . '</div>' .
					'</div>' .
					'<div class="bm-mini-price">' . $price_fmt . '</div>' .
					'</div>';
			}

			$subtotal_fmt = $this->currency->format($subtotal, $this->session->data['currency']);

			$html =
			'<div class="bm-mini-list">' . ($items_html ?: '<div class="bm-mini-empty">Корзина пуста</div>') . '</div>' .
			'<div class="bm-mini-summary">' .
				'<div class="bm-mini-row"><span>Сумма:</span><strong>' . $subtotal_fmt . '</strong></div>' .
			'</div>';

			$this->response->addHeader('Content-Type: text/html; charset=utf-8');
			$this->response->setOutput($html);
			return; // важно: не продолжать рендер страницы
		}
		// -------- ПАРАМЕТРЫ ИЗ URL --------
		// Пагинация/лимит
		$page  = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
		$limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 20;
		if (!in_array($limit, [20, 50, 100, 300])) { $limit = 20; } // 999999 = "Все"

		// Сортировка
		$sort  = isset($this->request->get['sort']) ? $this->request->get['sort'] : 'release_desc';
		$allowed_sorts = ['release_desc','release_asc','date_desc','price_asc','price_desc','name_asc','name_desc'];
		if (!in_array($sort, $allowed_sorts)) { $sort = 'release_desc'; }

		// Флаги
		$show_new     = !empty($this->request->get['new']) ? 1 : 0;       // новинки (сбрасывают остальные фильтры — реализуем на шаге 4)
		$show_archive = !empty($this->request->get['archive']) ? 1 : 0;   // включить архивные (quantity=0)
		$only_discount  = !empty($this->request->get['discount']) ? 1 : 0;  // показывать только товары со скидкой

		// Глобальная поисковая строка (как на стандартной странице поиска)
		if (isset($this->request->get['search'])) {
			$search = trim((string)$this->request->get['search']);
		} else {
			$search = '';
		}

		// Приведение к формату стандартной модели:
		// filter_name / filter_tag / filter_description
		$filter_name        = $search;
		$filter_tag         = $search; // как в product/search: если tag не задан, он равен search
		$filter_description = '';      // по умолчанию не ищем в описании (как на стандартной странице поиска без галочки)
		
		// Производители (массив manufacturer_id[] из формы фильтров)
		$filter_manufacturer_ids = [];
		if (!empty($this->request->get['manufacturer_id'])) {
			$raw = $this->request->get['manufacturer_id'];
			$items = [];

			if (is_array($raw)) {
				// На всякий случай: если вдруг пришёл массив значений
				foreach ($raw as $val) {
					$parts = explode(',', (string)$val);
					foreach ($parts as $part) {
						$mid = (int)$part;
						if ($mid > 0) {
							$items[] = $mid;
						}
					}
				}
			} else {
				// Обычный случай: одна строка "5,12,20"
				$parts = explode(',', (string)$raw);
				foreach ($parts as $part) {
					$mid = (int)$part;
					if ($mid > 0) {
						$items[] = $mid;
					}
				}
			}

			if ($items) {
				$filter_manufacturer_ids = array_values(array_unique($items));
			}
		}

		// Мульти-фильтры (пока скелет — на шаге 4 подключим реальную фильтрацию по атрибутам/категориям/брендам)
		$filter_category_ids = !empty($this->request->get['cat'])   ? explode(',', $this->request->get['cat'])   : [];
		$filter_materials    = !empty($this->request->get['material']) ? explode(',', $this->request->get['material']) : [];
		$filter_scales       = !empty($this->request->get['scale'])    ? explode(',', $this->request->get['scale'])    : [];
		$filter_levels       = !empty($this->request->get['level'])    ? explode(',', $this->request->get['level'])    : [];
		$filter_periods      = !empty($this->request->get['period'])   ? explode(',', $this->request->get['period'])   : [];
		$filter_nations      = !empty($this->request->get['nation'])   ? explode(',', $this->request->get['nation'])   : [];
		$filter_kids         = isset($this->request->get['kids']) ? (int)$this->request->get['kids'] : null;              // 0/1
		$filter_accessories  = isset($this->request->get['accessories']) ? (int)$this->request->get['accessories'] : null; // 0/1


		$filter_category_ids = $filter_category_ids
			? array_values(array_unique(array_map('intval', $filter_category_ids)))
			: [];
		// -------- МАППИНГ СОРТИРОВОК В SQL --------
		// release_* — сортировка по "Год выпуска" / "Месяц выпуска" (NULL — вниз)
		$sql_sort = "pd.name ASC";
		// language_id текущего языка
			$lid = (int)$this->config->get('config_language_id');

			// подставь реальные ID атрибутов "Год выпуска" и "Месяц выпуска"
			$YEAR_ATTR_ID  = 101; // <-- твой attribute_id
			$MONTH_ATTR_ID = 102; // <-- твой attribute_id
			// выражение «итоговая цена для сортировки»: сначала SPECIAL, потом DISCOUNT, иначе обычная цена
        	$effective_price_expr = "(CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";

		switch ($sort) {
			case 'release_desc':
				$sql_sort =
					" (SELECT CAST(pa1.text AS UNSIGNED)
						FROM " . DB_PREFIX . "product_attribute pa1
						WHERE pa1.product_id = p.product_id
							AND pa1.attribute_id = {$YEAR_ATTR_ID}
							AND pa1.language_id = {$lid}
						LIMIT 1) DESC,
						(SELECT CAST(pa2.text AS UNSIGNED)
						FROM " . DB_PREFIX . "product_attribute pa2
						WHERE pa2.product_id = p.product_id
							AND pa2.attribute_id = {$MONTH_ATTR_ID}
							AND pa2.language_id = {$lid}
						LIMIT 1) DESC,
						p.date_available DESC, LCASE(pd.name) ASC";
				break;
			case 'release_asc':
				$sql_sort =
					" (SELECT CAST(pa1.text AS UNSIGNED)
						FROM " . DB_PREFIX . "product_attribute pa1
						WHERE pa1.product_id = p.product_id
							AND pa1.attribute_id = {$YEAR_ATTR_ID}
							AND pa1.language_id = {$lid}
						LIMIT 1) ASC,
						(SELECT CAST(pa2.text AS UNSIGNED)
						FROM " . DB_PREFIX . "product_attribute pa2
						WHERE pa2.product_id = p.product_id
							AND pa2.attribute_id = {$MONTH_ATTR_ID}
							AND pa2.language_id = {$lid}
						LIMIT 1) ASC,
						p.date_available DESC, LCASE(pd.name) ASC";
				break;
			case 'date_desc':
				$sql_sort = "p.date_available DESC, LCASE(pd.name) ASC";
				break;
			case 'price_asc':
				// сортировка по конечной цене для покупателя (special → discount → price)
				$sql_sort = $effective_price_expr . " ASC, LCASE(pd.name) ASC";
				break;
			case 'price_desc':
				// то же самое, но от дорогих к дешёвым
				$sql_sort = $effective_price_expr . " DESC, LCASE(pd.name) ASC";
				break;
			case 'name_asc':
				$sql_sort = "LCASE(pd.name) ASC";
				break;
			case 'name_desc':
				$sql_sort = "LCASE(pd.name) DESC";
				break;
		}

		// -------- ПОДГОТОВКА ФИЛЬТРА ДЛЯ МОДЕЛИ --------
		$filter_data = [
			'page'        => $page,
			'limit'       => $limit,
			'sort'        => $sql_sort,
			'sort_key'    => $sort,            // ← ДОБАВЛЕНО
			'show_new'    => $show_new,
			'show_archive'=> $show_archive,
			'discount_only' => $only_discount,

			// параметры поиска — как в стандартной модели getProducts()
			'filter_name'        => $filter_name,
			'filter_tag'         => $filter_tag,
			'filter_description' => $filter_description,

			// скелет мульти-фильтров — в шаге 4 реализуем реальный SQL-фильтр по атрибутам/категориям/брендам
			'manufacturer_ids' => $filter_manufacturer_ids,
			'category_ids'  => $filter_category_ids,
			'materials'     => $filter_materials,
			'scales'        => $filter_scales,
			'levels'        => $filter_levels,
			'periods'       => $filter_periods,
			'nations'       => $filter_nations,
			'kids'          => $filter_kids,        // 0/1/null
			'accessories'   => $filter_accessories, // 0/1/null
		];

		// --- F2: список производителей для фильтра ---
		// Базовый контекст для брендов: как в $filter_data, но без пагинации и без самих manufacturer_ids
		$manufacturer_filter_data = $filter_data;
		unset(
			$manufacturer_filter_data['page'],
			$manufacturer_filter_data['limit'],
			$manufacturer_filter_data['manufacturer_ids']
		);

		// Модель вернёт набор manufacturer_id, которые реально встречаются в текущем контексте
		$raw_manufacturers = $this->model_catalog_product->getManufacturersForFilter($manufacturer_filter_data);

		$this->load->model('catalog/manufacturer');

		$data['manufacturers'] = [];
		// --- F6–F9: фасетные списки атрибутов (масштаб, сложность, период, нация) ---
		$scale_values  = [];
		$level_values  = [];
		$period_values = [];
		$nation_values = [];

		// Базовый контекст для атрибутов: как в $filter_data, но без пагинации
		$attribute_filter_data = $filter_data;
		unset($attribute_filter_data['page'], $attribute_filter_data['limit']);

		// Масштабы (атрибут 201) — не учитываем текущий выбор масштабов, чтобы список не "зажимал" сам себя
		$scale_filter_data = $attribute_filter_data;
		unset($scale_filter_data['scales']);
		$scale_values = $this->model_catalog_product->getAttributeValuesForFilter(201, $scale_filter_data);

		// Сложность (атрибут 204)
		$level_filter_data = $attribute_filter_data;
		unset($level_filter_data['levels']);
		$level_values = $this->model_catalog_product->getAttributeValuesForFilter(204, $level_filter_data);

		// Исторический период (атрибут 202)
		$period_filter_data = $attribute_filter_data;
		unset($period_filter_data['periods']);
		$period_values = $this->model_catalog_product->getAttributeValuesForFilter(202, $period_filter_data);

		// Нация (атрибут 203)
		$nation_filter_data = $attribute_filter_data;
		unset($nation_filter_data['nations']);
		$nation_values = $this->model_catalog_product->getAttributeValuesForFilter(203, $nation_filter_data);
		$data['filter_manufacturer_ids'] = $filter_manufacturer_ids;

		if (!empty($raw_manufacturers) && is_array($raw_manufacturers)) {
			foreach ($raw_manufacturers as $row) {
				// Вытаскиваем корректный ID из массива
				if (is_array($row)) {
					if (isset($row['manufacturer_id'])) {
						$mid = (int)$row['manufacturer_id'];
					} elseif (isset($row['mid'])) {
						$mid = (int)$row['mid'];
					} else {
						continue; // неизвестный формат
					}
				} else {
					$mid = (int)$row;
				}

				if ($mid <= 0) {
					continue;
				}

				$m = $this->model_catalog_manufacturer->getManufacturer($mid);
				if (!$m) {
					continue;
				}

				$thumb = '';
				if (!empty($m['image'])) {
					$thumb = $this->model_tool_image->resize($m['image'], 200, 200);
				}

				$data['manufacturers'][] = [
					'manufacturer_id' => $mid,
					'name'            => $m['name'],
					'thumb'           => $thumb,
				];
			}

			// Сортируем бренды по алфавиту
			usort($data['manufacturers'], function ($a, $b) {
				return strcasecmp($a['name'], $b['name']);
			});
		}

		// -------- ВЫБОРКА --------
		// В этом шаге используем наш кастомный метод (добавим его в модель в п.2.2)
		$total = $this->model_catalog_product->getTotalProductsAdvanced($filter_data);
		$results = $this->model_catalog_product->getProductsAdvanced($filter_data);

		// --- F1: дерево категорий для структурного фильтра ---
		$selected_category_ids = $filter_category_ids;
		$category_tree = $this->buildCategoryTree(0, $selected_category_ids);

		// === F1: bulk-выборка спеццен и скидок для всех товаров страницы ===
		$special_map  = [];
		$discount_map = [];

		// F2: карта корзины product_id → { key, qty }
		$cart_map = [];
		foreach ($this->cart->getProducts() as $cp) {
			$pid = (int)$cp['product_id'];
			$cart_map[$pid] = ['key' => $cp['cart_id'] ?? ($cp['key'] ?? ''), 'qty' => (int)$cp['quantity']];
		}

		$ids = array_values(array_filter(array_map('intval', array_column($results, 'product_id'))));
		if ($ids) {
			$customer_group_id = $this->customer->isLogged()
				? (int)$this->customer->getGroupId()
				: (int)$this->config->get('config_customer_group_id');

			// 1) Активные SPECIAL: берем "лучшую" (минимальную по цене с учетом приоритета внутри MIN)
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

			// 2) Активные DISCOUNT для qty=1 (используем только если нет SPECIAL)
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
				$pid = (int)$r['product_id'];
				if (!isset($special_map[$pid])) { // скидка работает только при отсутствии спеццены
					$discount_map[$pid] = (float)$r['price'];
				}
			}
		}

		// -------- ПОДГОТОВКА ДАННЫХ ВИДА --------
		$this->load->model('tool/image');

		$data['category_tree']        = $category_tree;
		$data['selected_category_ids'] = $selected_category_ids;
		// F6–F9: текущие выбранные значения атрибутов
		$data['filter_scales']  = $filter_scales;
		$data['filter_levels']  = $filter_levels;
		$data['filter_periods'] = $filter_periods;
		$data['filter_nations'] = $filter_nations;

		// F6–F9: списки доступных значений атрибутов
		$data['scales']  = $scale_values;
		$data['levels']  = $level_values;
		$data['periods'] = $period_values;
		$data['nations'] = $nation_values;
		$data['products'] = [];

		// === VARIANTS (bulk): плашка "Есть варианты" ===

		// имя атрибута, по которому объединяем
		// === VARIANTS (bulk): определяем attribute_id напрямую ===
		$variant_attr_name = 'Название товара для объединения в одну карточку';

		$query = $this->db->query("
			SELECT attribute_id 
			FROM " . DB_PREFIX . "attribute_description 
			WHERE LOWER(TRIM(REPLACE(REPLACE(name, '\xC2\xA0',' '), '  ', ' '))) = '" . 
			$this->db->escape(mb_strtolower($variant_attr_name)) . "'
				AND language_id = " . (int)$this->config->get('config_language_id') . "
			LIMIT 1
		");
		$variant_attr_id = $query->num_rows ? (int)$query->row['attribute_id'] : 0;

		$variant_groups = [];
		if ($variant_attr_id) {
			// 1️⃣ Получаем все значения, встречающиеся ≥2 раз среди товаров с остатком > 0
			$sql = "
			SELECT LOWER(TRIM(REPLACE(REPLACE(pa.text, '\xC2\xA0',' '), '  ', ' '))) AS text_norm
			FROM " . DB_PREFIX . "product_attribute pa
			JOIN " . DB_PREFIX . "product p ON p.product_id = pa.product_id
			WHERE pa.attribute_id = " . (int)$variant_attr_id . "
				AND pa.language_id = " . (int)$this->config->get('config_language_id') . "
				AND p.quantity > 0
			GROUP BY text_norm
			HAVING COUNT(*) >= 2
			";
			$rows = $this->db->query($sql)->rows;
			foreach ($rows as $r) {
				if (!empty($r['text_norm'])) {
					$variant_groups[$r['text_norm']] = true;
				}
			}

			// 2️⃣ Для товаров текущей страницы получаем их значения этого атрибута
			if ($results) {
				$ids = array_column($results, 'product_id');
				$sql2 = "
				SELECT pa.product_id,
						LOWER(TRIM(REPLACE(REPLACE(pa.text, '\xC2\xA0',' '), '  ', ' '))) AS text_norm
				FROM " . DB_PREFIX . "product_attribute pa
				WHERE pa.attribute_id = " . (int)$variant_attr_id . "
					AND pa.language_id = " . (int)$this->config->get('config_language_id') . "
					AND pa.product_id IN (" . implode(',', array_map('intval', $ids)) . ")
				";
				$res2 = $this->db->query($sql2)->rows;
				$variant_flags = [];
				foreach ($res2 as $r2) {
					$variant_flags[(int)$r2['product_id']] =
						!empty($r2['text_norm']) && isset($variant_groups[$r2['text_norm']]);
				}
			}
		}

		foreach ($results as $result) {
			$image = $result['image'] ? $this->model_tool_image->resize($result['image'], 300, 300) : $this->model_tool_image->resize('placeholder.png', 300, 300);
			
			// Архивный ли товар
			$is_archive = (int)$result['quantity'] <= 0;

			// Базовая цена (как строка из format/tax)
			$price_str = '';
			if (!is_null($result['price'])) {
				// Если модель не вернула tax_class_id — это ОК: calculate сам переживет null/0
				$tax_class_id = isset($result['tax_class_id']) ? $result['tax_class_id'] : 0;
				$price_str = $this->currency->format(
					$this->tax->calculate($result['price'], $tax_class_id, $this->config->get('config_tax')),
					$this->session->data['currency']
				);
			}

			// Новая цена: сначала SPECIAL, иначе DISCOUNT qty=1
			$special_num = null;
			$pid = (int)$result['product_id'];
			if (isset($special_map[$pid])) {
				$special_num = $special_map[$pid];
			} elseif (isset($discount_map[$pid])) {
				$special_num = $discount_map[$pid];
			}

			// Отформатировать «новую» цену, если есть
			$special_str = '';
			if ($special_num !== null) {
				$tax_class_id = isset($result['tax_class_id']) ? $result['tax_class_id'] : 0;
				$special_str = $this->currency->format(
					$this->tax->calculate($special_num, $tax_class_id, $this->config->get('config_tax')),
					$this->session->data['currency']
				);
			}

			// Нормализатор: убрать копейки и валютный знак слева, поставить " ₽" справа
			// + разделение тысяч пробелом: 1000 -> 1 000
			$__clean = function ($s) {
				// оставляем только цифры и разделители
				$s = preg_replace('/[^\d\.,]/u', '', (string)$s);

				// убираем дробную часть (.00 / ,00)
				$s = preg_replace('/([.,])00\b/u', '', $s);

				// берём только целую часть
				$parts = preg_split('/[.,]/u', $s);
				$int = isset($parts[0]) ? preg_replace('/[^\d]/u', '', $parts[0]) : '';

				if ($int === '') {
					return '';
				}

				// группировка тысяч пробелом: 1000 -> 1 000
				$formatted = number_format((int)$int, 0, '.', ' ');

				return $formatted . ' ₽';
			};

			$price_clean     = $price_str   ? $__clean($price_str)   : '';
			$special_clean   = $special_str ? $__clean($special_str) : '';
			$price_old_clean = $special_str ? $__clean($price_str)   : '';

			$product_is_out = ((int)$result['quantity'] <= 0);

			// F1: процент скидки (округление ВВЕРХ) — считаем ТОЛЬКО для товаров в наличии
			$discount_percent = 0;
			if (
				!$product_is_out &&           // ← ключевое условие
				$special_num !== null &&
				(float)$result['price'] > 0
			) {
				$discount_percent = (int)ceil((1 - ($special_num / (float)$result['price'])) * 100);
				if ($discount_percent < 0) { $discount_percent = 0; }
				if ($discount_percent > 99) { $discount_percent = 99; }
			}

			// F2: данные для контролов корзины
			$pid = (int)$result['product_id'];
			$in_cart_qty = isset($cart_map[$pid]) ? (int)$cart_map[$pid]['qty'] : 0;
			$cart_key    = isset($cart_map[$pid]) ? (string)$cart_map[$pid]['key'] : '';
			$max_qty     = max(0, (int)$result['quantity']); // остаток на складе

			if (!is_null($result['price'])) {
				$price_str = $this->currency->format(
					$this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')),
					$this->session->data['currency']
				);
			}

			if (isset($result['special']) && !is_null($result['special'])) {
				$special_str = $this->currency->format(
					$this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')),
					$this->session->data['currency']
				);
			}

			$price_clean     = $price_str   ? $__clean($price_str)   : '';
			$special_clean   = $special_str ? $__clean($special_str) : '';
			$price_old_clean = $special_str ? $__clean($price_str)   : ''; // старая показывается только если есть спец-цена

			// флаг наличия для шаблона продукта
			$product_is_out = ((int)$result['quantity'] <= 0);

			// === BM meta fields (manufacturer, model, scale, difficulty_text) ===
			$manufacturer    = isset($result['manufacturer']) ? $result['manufacturer'] : '';
			$model_code      = isset($result['model']) ? $result['model'] : '';
			$has_variants = !empty($variant_flags[$result['product_id']]);
			$scale           = '';
			$difficulty_text = '';
			$completeness_text = ''; // ← добавили
			$has_accessories = false; // для Плашки2
			$note_text = '';

			// Тянем атрибуты и ищем «Масштаб» и «Сложность»
			$attrs = $this->model_catalog_product->getProductAttributes((int)$result['product_id']);
			foreach ($attrs as $group) {
				if (empty($group['attribute'])) continue;
				foreach ($group['attribute'] as $attr) {
					// Нормализация: trim + замена NBSP на обычный пробел + схлопывание повторов
					$name = mb_strtolower($attr['name']);
					$val  = mb_strtolower($attr['text']);
					$name = str_replace("\xC2\xA0", ' ', $name); // NBSP → space
					$val  = str_replace("\xC2\xA0", ' ', $val);  // NBSP → space
					$name = trim(preg_replace('/\s+/u', ' ', $name));
					$val  = trim(preg_replace('/\s+/u', ' ', $val));

					// Масштаб: допускаем разные названия
					if ($scale === '' && in_array($name, ['масштаб','масштаб модели','scale'])) {
						$scale = $val; // пример: "1:33"
					}

					// Сложность: допускаем разные названия
					if ($difficulty_text === '' && in_array($name, ['сложность','уровень сложности','difficulty'])) {
						$difficulty_text = $val; // пример: "Для начинающих" / "Средний" / "Высокая"
					}

					// Комплектация: допускаем разные названия
					if ($completeness_text === '' && in_array($name, [
						'комплектация','состав набора','комплектность','completeness','equipment','set contents'
					])) {
						$completeness_text = $val; // пример: "Журнал", "Журнал + лазерная резка", "Лазерная резка"
					}

					// Примечание: берём оригинальный текст (без to-lower), поддерживаем синонимы
					if ($note_text === '') {
						$name_lc   = trim(preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', mb_strtolower($attr['name']))));
						$raw_value = trim(preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', (string)$attr['text'])));

						if (in_array($name_lc, ['примечание','прим.','note','notes'], true)) {
							if ($raw_value !== '') {
								// мягкая страховка длины для листинга (визуально всё равно режем CSS)
								$note_text = $raw_value; // показываем полностью
							}
						}
					}

					// С аксессуарами: ловим разные названия и «позитивные» значения
					if (!$has_accessories && in_array($name, [
						'с аксессуарами',           // базовый
						'с аксессуарами комплект',  // на всякий
						'аксессуары в комплекте',
						'аксессуары',
						'with accessories',
						'accessories included'
					])) {
						if (in_array($val, ['да','yes','true','1','есть'])) {
							$has_accessories = true;
						}
					}
				}
			}
			// === /BM meta fields ===

			// === BM: гарантируем manufacturer/model и подготавливаем иконку производителя ===
			if (!$manufacturer || !$model_code) {
				// Быстрый фоллбэк: достаём полные данные товара
				$p = $this->model_catalog_product->getProduct((int)$result['product_id']);
				if (!$manufacturer && !empty($p['manufacturer'])) {
					$manufacturer = $p['manufacturer'];
				}
				if (!$model_code && !empty($p['model'])) {
					$model_code = $p['model'];
				}
			}

			// Иконка производителя (16–18px). Нужен manufacturer_id → берём из getProduct.
			$manufacturer_icon = '';
			if (!empty($p['manufacturer_id'])) {
				$this->load->model('catalog/manufacturer');
				$this->load->model('tool/image');
				$m = $this->model_catalog_manufacturer->getManufacturer((int)$p['manufacturer_id']);
				if (!empty($m['image'])) {
					$manufacturer_icon = $this->model_tool_image->resize($m['image'], 18, 18);
				}
			}
			// === /BM ===

			// === BM: Плашка1 (Нет в наличии / Новинка / Для начинающих) ===
			$badge1 = null; // по умолчанию нет плашки

			// 1) Нет в наличии — если quantity <= 0
			if ((int)$result['quantity'] <= 0) {
				$badge1 = [
					'text'  => 'Нет в наличии',
					'class' => 'product-ribbon--out'
				];
			}

			// 2) Новинка — если нет "Нет в наличии" и дата до 20 дней назад
			if (!$badge1) {
				$date_available = !empty($result['date_available']) ? strtotime($result['date_available']) : strtotime($result['date_added']);
				if ($date_available !== false) {
					$days = (time() - $date_available) / 86400;
					if ($days <= 20) {
						$badge1 = [
							'text'  => 'Новинка',
							'class' => 'product-ribbon--new'
						];
					}
				}
			}

			// 3) Для начинающих — если ни одно условие выше и есть атрибут "Для детей" = Да
			if (!$badge1) {
				$is_for_kids = false;
				foreach ($attrs as $group) {
					if (empty($group['attribute'])) continue;
					foreach ($group['attribute'] as $attr) {
						$name = mb_strtolower(trim($attr['name']));
						$val  = mb_strtolower(trim($attr['text']));
						if (in_array($name, ['для детей','подходит для детей','kids','for kids'])) {
							if (in_array($val, ['да','yes','true','1'])) {
								$is_for_kids = true;
								break 2;
							}
						}
					}
				}
				if ($is_for_kids) {
					$badge1 = [
						'text'  => 'Начинающим',
						'class' => 'product-ribbon--beginner'
					];
				}
			}

			// Прокидываем в шаблон
			if ($badge1) {
				$result['badge1'] = $badge1;
			}
			// === /BM: Плашка1 ===

			$price = '';
			$special = '';

			if (!is_null($result['price'])) {
				$price = $this->currency->format(
					$this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')),
					$this->session->data['currency']
				);
			}

			if (isset($result['special']) && !is_null($result['special'])) {
				$special = $this->currency->format(
					$this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')),
					$this->session->data['currency']
				);
			}

			$data['products'][] = [
				'product_id' => $result['product_id'],
				'thumb'      => $image,
				'name'       => $result['name'],
				'manufacturer'    => $manufacturer,
				'manufacturer_icon'  => $manufacturer_icon,
				'model'           => $model_code,
				'scale'           => $scale,
				'difficulty_text' => $difficulty_text,
				'has_accessories' => $has_accessories,
				'has_variants' => $has_variants,
				'note_text' => $note_text,
				'badge1' => isset($result['badge1']) ? $result['badge1'] : null,
				'completeness_text' => $completeness_text,
				'price'   => $price,
				'special' => $special, // будет пустой строкой, если спец-цены нет
				// F1: цены для листинга
				'price_clean'     => $price_clean,      // "11600 ₽"
				'special_clean'   => $special_clean,    // "10500 ₽" (если есть скидка)
				'price_old_clean' => $price_old_clean,  // "11600 ₽" (если есть скидка)
				'is_out'          => $product_is_out,   // флаг наличия в составе product
				// F2: управление корзиной
				'in_cart_qty'          => $in_cart_qty,
				'cart_key'             => $cart_key,
				'max_qty'              => $max_qty,
				'has_required_options' => $has_required_options,
				'discount_percent' => $discount_percent,
				'quantity'   => (int)$result['quantity'],
				'href'       => $this->url->link('product/product', 'product_id=' . (int)$result['product_id']),
				'is_archive' => $is_archive
				
			];
		}

		// Инфострока
		$data['text_found'] = sprintf($this->language->get('text_compare') ? 'Найдено %d товаров' : 'Найдено %d товаров', (int)$total); // просто строка "Найдено X товаров"
		$data['current_sort'] = $sort;

		// Сортировки/лимиты для селектов
		$data['sorts'] = [
			['value'=>'release_desc','text'=>'От новых к старым (по выпуску)'],
			['value'=>'release_asc','text'=>'От старых к новым (по выпуску)'],
			['value'=>'date_desc','text'=>'По дате поступления'],
			['value'=>'price_asc','text'=>'По цене (возрастание)'],
			['value'=>'price_desc','text'=>'По цене (убывание)'],
			['value'=>'name_asc','text'=>'По названию (A–Я)'],
			['value'=>'name_desc','text'=>'По названию (Я–A)'],
		];
		$data['limits'] = [20,50,100,300];

		// Пагинация
		$pagination = new Pagination();
		$pagination->total = (int)$total;
		$pagination->page  = $page;
		$pagination->limit = $limit;
		$pagination->url   = $this->url->link('product/all', $this->buildQuery(['page' => '{page}']));
		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'),
			($total) ? (($page - 1) * $limit) + 1 : 0,
			((($page - 1) * $limit) > ($total - $limit)) ? $total : ((($page - 1) * $limit) + $limit),
			$total, ceil($total / $limit)
		);

// --- Компактная пагинация: если страниц ≤10 — показываем все, иначе с "…" ---
$total_pages = (int)ceil($total / $limit);
$data['pages'] = [];
$data['page'] = $page;
$data['total_pages'] = $total_pages;

$make_url = function($num) {
    return $this->url->link('product/all', $this->buildQuery(['page' => $num]));
};

$add_page = function($num, $label = null, $active = false, $disabled = false, $is_ellipsis = false) use (&$data, $make_url) {
    $data['pages'][] = [
        'num'         => $num,
        'label'       => $label !== null ? $label : $num,
        'url'         => ($is_ellipsis || $disabled) ? '' : $make_url($num),
        'active'      => $active,
        'disabled'    => $disabled,
        'is_ellipsis' => $is_ellipsis
    ];
};

$prev = max(1, $page - 1);
$next = min($total_pages, $page + 1);
$add_page($prev, '‹', false, $page == 1);

if ($total_pages <= 10) {
    // Показываем все страницы подряд
    for ($i = 1; $i <= $total_pages; $i++) {
        $add_page($i, null, $i == $page);
    }
} else {
    // Версия с троеточиями
    $window = 2; // можно увеличить до 3, если хочешь шире «окно» вокруг текущей

    // 1-я
    $add_page(1, null, $page == 1);

    // Левая троеточие
    if ($page > ($window + 3)) {
        $add_page(0, '…', false, true, true);
    }

    // Диапазон вокруг текущей
    $start = max(2, $page - $window);
    $end   = min($total_pages - 1, $page + $window);
    for ($i = $start; $i <= $end; $i++) {
        $add_page($i, null, $i == $page);
    }

    // Правая троеточие
    if ($page < $total_pages - ($window + 2)) {
        $add_page(0, '…', false, true, true);
    }

    // Последняя
    $add_page($total_pages, null, $page == $total_pages);
}

$add_page($next, '›', false, $page == $total_pages);

		// Прямые URL для FAB-кнопок "предыдущая/следующая"
		$data['prev_url'] = ($page > 1)
		? $this->url->link('product/all', $this->buildQuery(['page' => $page - 1]))
		: '';
		$data['next_url'] = ($total_pages > 1 && $page < $total_pages)
		? $this->url->link('product/all', $this->buildQuery(['page' => $page + 1]))
		: '';
		// Служебные
		$data['sort']        = $sort;
		$data['limit']       = $limit;
		$data['show_new']    = $show_new;
		$data['show_archive'] = $show_archive;
		$data['only_discount'] = $only_discount;
		$data['kids']        = $filter_kids;
        $data['accessories'] = $filter_accessories;

		// Общие куски
		$data['column_left']   = $this->load->controller('common/column_left');
		$data['column_right']  = $this->load->controller('common/column_right'); // если не используешь — можно оставить пустым
		$data['content_top']   = $this->load->controller('common/content_top');
		$data['content_bottom']= $this->load->controller('common/content_bottom');
		$data['footer']        = $this->load->controller('common/footer');
		$data['header']        = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('product/all', $data));
	}

	// Строим дерево категорий для структурного фильтра (F1)
	private function buildCategoryTree($parent_id = 0, $selected_ids = []) {
		$tree = [];
		$categories = $this->model_catalog_category->getCategories($parent_id);

		foreach ($categories as $category) {
			$cid = (int)$category['category_id'];

			// F1: пропускаем служебную категорию 9999 "Все товары"
			if ($cid === 9999) {
				continue;
			}

			$children = $this->buildCategoryTree($cid, $selected_ids);

			$tree[] = [
				'category_id' => $cid,
				'name'        => $category['name'],
				'children'    => $children,
				'checked'     => in_array($cid, $selected_ids, true),
			];
		}

		return $tree;
	}

	// Собираем URL c учётом текущих параметров, переопределяя нужные
	private function buildQuery($override = []) {
		$keep = $this->request->get;

		// Не держим токены/route
		unset($keep['_route_'], $keep['route']);

		foreach ($override as $k=>$v) {
			if ($v === null) { unset($keep[$k]); }
			else { $keep[$k] = $v; }
		}
		$qs = http_build_query($keep);
		return $qs ? $qs : '';
	}
}