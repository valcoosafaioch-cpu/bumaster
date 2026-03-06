<?php
class ControllerAccountFeedbackAdmin extends Controller {
    public function index() {
        // 1) Обязательная авторизация
        if (!$this->customer->isLogged()) {
            $this->session->data['redirect'] = $this->url->link('account/feedback_admin', '', true);
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        // 2) Доступ только для "админской" УЗ с ID = 1
        if ((int)$this->customer->getId() !== 1) {
            $this->response->redirect($this->url->link('account/account', '', true));
        }

        $this->load->language('account/feedback_admin');
        $this->document->setTitle($this->language->get('heading_title'));

                // Загружаем модель отзывов/вопросов
        $this->load->model('catalog/bm_feedback');

        // Словарь источников: source_code => [title, icon]
        $source_map = [
            'ozon'  => ['title' => 'Ozon',         'icon' => '/image/catalog/review_sources/ozon.jpg'],
            'wb'    => ['title' => 'Wildberries',  'icon' => '/image/catalog/review_sources/wb.jpg'],
            'avito' => ['title' => 'Avito',        'icon' => '/image/catalog/review_sources/avito.jpg'],
            'ym'    => ['title' => 'Яндекс Маркет','icon' => '/image/catalog/review_sources/ym.jpg'],
        ];

        // Какая вкладка активна: требуют ответа или уже с ответом
        $tab = 'need_answer';
        if (!empty($this->request->get['tab']) && $this->request->get['tab'] === 'answered') {
            $tab = 'answered';
        }

        // Пагинация
        $page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }

        $limit = 10;
        $start = ($page - 1) * $limit;

        $filter_data = [
            'tab'   => $tab,
            'start' => $start,
            'limit' => $limit,
        ];

        // Получаем записи для активной вкладки
        $feedback_total = $this->model_catalog_bm_feedback->getTotalAdminFeedback($filter_data);
        $results        = $this->model_catalog_bm_feedback->getAdminFeedback($filter_data);

        $data['tab']       = $tab;
        $data['feedbacks'] = [];

        foreach ($results as $row) {
            $text = trim(strip_tags($row['text']));

            if (utf8_strlen($text) > 150) {
                $text_short = utf8_substr($text, 0, 150) . '…';
            } else {
                $text_short = $text;
            }

            $answer_text_short = '';

            if (!empty($row['admin_text'])) {
                $answer = trim(strip_tags($row['admin_text']));

                if (utf8_strlen($answer) > 150) {
                    $answer_text_short = utf8_substr($answer, 0, 150) . '…';
                } else {
                    $answer_text_short = $answer;
                }
            }

            $type_label = !empty($row['is_question'])
                ? $this->language->get('text_type_question')
                : $this->language->get('text_type_review');

            // Источник (только для внешних): одна иконка + ссылка
         
            $source_code = $row['source_code'] ?? null;
            $raw_url     = $row['source_url'] ?? null;

            $source_icon  = '';
            $source_title = '';
            $source_url   = '';

            if (!empty($source_code) && !empty($raw_url) && isset($source_map[$source_code])) {
                $source_icon  = $source_map[$source_code]['icon'];
                $source_title = $source_map[$source_code]['title'];
                $source_url   = $raw_url;
            }

            $data['feedbacks'][] = [
                'feedback_id'   => (int)$row['feedback_id'],
                'type'          => $type_label,
                'product_name'  => $row['product_name'],
                'product_href'  => $this->url->link(
                    'product/product',
                    'product_id=' . (int)$row['product_id']
                ),
                'customer_name' => $row['customer_name'],
                'rating'        => isset($row['rating']) ? (int)$row['rating'] : null,
                'text'          => $text_short,
                'date_added'    => date(
                    $this->language->get('date_format_short'),
                    strtotime($row['date_added'])
                ),
                'date_answered' => !empty($row['date_answered'])
                    ? date(
                        $this->language->get('date_format_short'),
                        strtotime($row['date_answered'])
                    )
                    : '',
                'admin_name'    => $row['admin_name'], 
                'answer_text'  => $answer_text_short,  
                'source_icon'  => $source_icon,
                'source_title' => $source_title,
                'source_url'   => $source_url, 
            ];
        }

        // Счётчики для табов
        $data['count_need'] = $this->model_catalog_bm_feedback->getTotalAdminFeedback([
            'tab'   => 'need_answer',
            'start' => 0,
            'limit' => 1,
        ]);

        $data['count_answered'] = $this->model_catalog_bm_feedback->getTotalAdminFeedback([
            'tab'   => 'answered',
            'start' => 0,
            'limit' => 1,
        ]);

        // "Показать ещё" для активной вкладки
        $url_base = 'tab=' . $tab;

        if ($start + $limit < $feedback_total) {
            $data['show_more_href'] = $this->url->link(
                'account/feedback_admin',
                $url_base . '&page=' . ($page + 1),
                true
            );
        } else {
            $data['show_more_href'] = '';
        }





        // ссылки для переключения табов
        $data['tab_need_href']     = $this->url->link('account/feedback_admin', 'tab=need_answer', true);
        $data['tab_answered_href'] = $this->url->link('account/feedback_admin', 'tab=answered', true);

        // Хлебные крошки
        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_account'),
            'href' => $this->url->link('account/account', '', true)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('account/feedback_admin', $url_base, true)
        ];

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_tab_need'] = $this->language->get('text_tab_need');
        $data['text_tab_answered'] = $this->language->get('text_tab_answered');
        $data['text_empty_need'] = $this->language->get('text_empty_need');
        $data['text_empty_answered'] = $this->language->get('text_empty_answered');
        $data['button_show_more'] = $this->language->get('button_show_more');
        $data['continue'] = $this->url->link('account/account', '', true);

        $data['column_date_added']    = $this->language->get('column_date_added');
        $data['column_type']          = $this->language->get('column_type');
        $data['column_product']       = $this->language->get('column_product');
        $data['column_customer']      = $this->language->get('column_customer');
        $data['column_text']          = $this->language->get('column_text');
        $data['column_rating']        = $this->language->get('column_rating');
        $data['column_date_answered'] = $this->language->get('column_date_answered');
        $data['column_admin']         = $this->language->get('column_admin');

        // Стандартные части макета
        $data['column_left']   = $this->load->controller('common/column_left');
        $data['column_right']  = $this->load->controller('common/column_right');
        $data['content_top']   = $this->load->controller('common/content_top');
        $data['content_bottom']= $this->load->controller('common/content_bottom');
        $data['footer']        = $this->load->controller('common/footer');
        $data['header']        = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('account/feedback_admin', $data));
    }
}
