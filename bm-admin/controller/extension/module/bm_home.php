<?php
class ControllerExtensionModuleBmHome extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/bm_home');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('tool/image');

         if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $news_action = isset($this->request->post['bm_home_news_action'])
                ? (string)$this->request->post['bm_home_news_action']
                : '';

            $content_type = isset($this->request->post['bm_home_content_type'])
                ? trim((string)$this->request->post['bm_home_content_type'])
                : 'news';

            if (!in_array($content_type, array('news', 'mailing'), true)) {
                $content_type = 'news';
            }

            $redirect_tab = ($content_type === 'mailing') ? 'mailing' : 'news';

            if ($news_action === 'save_news') {
                $this->saveNews($this->request->post);

                $this->session->data['success'] = 'Новость успешно сохранена!';

                $this->response->redirect(
                    $this->url->link(
                        'extension/module/bm_home',
                        'user_token=' . $this->session->data['user_token'] . '&tab=' . $redirect_tab,
                        true
                    )
                );

                return;
            }

            if ($news_action === 'delete_news') {
                $news_id = isset($this->request->post['bm_home_news_id'])
                    ? (int)$this->request->post['bm_home_news_id']
                    : 0;

                if ($news_id > 0) {
                    $this->deleteNews($news_id, $content_type);
                }

                $this->session->data['success'] = 'Новость успешно удалена!';

                $this->response->redirect(
                    $this->url->link(
                        'extension/module/bm_home',
                        'user_token=' . $this->session->data['user_token'] . '&tab=' . $redirect_tab,
                        true
                    )
                );

                return;
            }

            if ($news_action === 'send_news_confirm') {
                $news_id = isset($this->request->post['bm_home_news_id'])
                    ? (int)$this->request->post['bm_home_news_id']
                    : 0;

                if ($news_id > 0) {
                    $this->sendNewsMail($news_id, false, $content_type);
                }

                $this->session->data['success'] = 'Рассылка запущена!';

                $this->response->redirect(
                    $this->url->link(
                        'extension/module/bm_home',
                        'user_token=' . $this->session->data['user_token'] . '&tab=' . $redirect_tab,
                        true
                    )
                );

                return;
            }

            if ($news_action === 'send_news_retry_confirm') {
                $news_id = isset($this->request->post['bm_home_news_id'])
                    ? (int)$this->request->post['bm_home_news_id']
                    : 0;

                if ($news_id > 0) {
                    $this->sendNewsMail($news_id, true, $content_type);
                }

                $this->session->data['success'] = 'Повторная рассылка запущена!';

                $this->response->redirect(
                    $this->url->link(
                        'extension/module/bm_home',
                        'user_token=' . $this->session->data['user_token'] . '&tab=' . $redirect_tab,
                        true
                    )
                );

                return;
            }

            if ($news_action === 'hide_news_mail_prompt') {
                $news_id = isset($this->request->post['bm_home_news_id'])
                    ? (int)$this->request->post['bm_home_news_id']
                    : 0;

                if ($news_id > 0) {
                    $this->hideNewsMailPrompt($news_id, $content_type);
                }

                $this->response->redirect(
                    $this->url->link(
                        'extension/module/bm_home',
                        'user_token=' . $this->session->data['user_token'] . '&tab=' . $redirect_tab,
                        true
                    )
                );

                return;
            }

            if ($news_action === 'download_news_log') {
                $news_id = isset($this->request->post['bm_home_news_id'])
                    ? (int)$this->request->post['bm_home_news_id']
                    : 0;

                if ($news_id > 0) {
                    $this->downloadNewsLogCsv($news_id);
                }

                return;
            }

            if ($news_action === 'download_news_attempt_log') {
                $news_id = isset($this->request->post['bm_home_news_id'])
                    ? (int)$this->request->post['bm_home_news_id']
                    : 0;

                $send_id = isset($this->request->post['bm_home_send_id'])
                    ? (int)$this->request->post['bm_home_send_id']
                    : 0;

                if ($news_id > 0 && $send_id > 0) {
                    $this->downloadNewsAttemptLogCsv($news_id, $send_id);
                }

                return;
            }

            $this->model_setting_setting->editSetting('bm_home', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect(
                $this->url->link(
                    'marketplace/extension',
                    'user_token=' . $this->session->data['user_token'] . '&type=module',
                    true
                )
            );
        }

        // Ошибки
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        // Хлебные крошки
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link(
                'common/dashboard',
                'user_token=' . $this->session->data['user_token'],
                true
            )
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link(
                'marketplace/extension',
                'user_token=' . $this->session->data['user_token'] . '&type=module',
                true
            )
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link(
                'extension/module/bm_home',
                'user_token=' . $this->session->data['user_token'],
                true
            )
        );

        // Ссылки формы
        $data['action'] = $this->url->link(
            'extension/module/bm_home',
            'user_token=' . $this->session->data['user_token'],
            true
        );

        $data['cancel'] = $this->url->link(
            'marketplace/extension',
            'user_token=' . $this->session->data['user_token'] . '&type=module',
            true
        );

        $data['user_token'] = $this->session->data['user_token'];

        // --- Поля "Контакты" ---

        if (isset($this->request->post['bm_home_telegram_group'])) {
            $data['bm_home_telegram_group'] = $this->request->post['bm_home_telegram_group'];
        } else {
            $data['bm_home_telegram_group'] = $this->config->get('bm_home_telegram_group');
        }

        if (isset($this->request->post['bm_home_telegram_dm'])) {
            $data['bm_home_telegram_dm'] = $this->request->post['bm_home_telegram_dm'];
        } else {
            $data['bm_home_telegram_dm'] = $this->config->get('bm_home_telegram_dm');
        }

        if (isset($this->request->post['bm_home_vk_group'])) {
            $data['bm_home_vk_group'] = $this->request->post['bm_home_vk_group'];
        } else {
            $data['bm_home_vk_group'] = $this->config->get('bm_home_vk_group');
        }

        if (isset($this->request->post['bm_home_email'])) {
            $data['bm_home_email'] = $this->request->post['bm_home_email'];
        } else {
            $data['bm_home_email'] = $this->config->get('bm_home_email');
        }

        if (isset($this->request->post['bm_home_contact_link'])) {
            $data['bm_home_contact_link'] = $this->request->post['bm_home_contact_link'];
        } else {
            $data['bm_home_contact_link'] = $this->config->get('bm_home_contact_link');
        }

        if (isset($this->request->post['bm_home_about_link'])) {
            $data['bm_home_about_link'] = $this->request->post['bm_home_about_link'];
        } else {
            $data['bm_home_about_link'] = $this->config->get('bm_home_about_link');
        }

        // Изображение для Telegram-группы (баннер)
        if (isset($this->request->post['bm_home_telegram_group_image'])) {
            $tg_group_image = $this->request->post['bm_home_telegram_group_image'];
        } else {
            $tg_group_image = $this->config->get('bm_home_telegram_group_image');
        }

        if ($tg_group_image && is_file(DIR_IMAGE . $tg_group_image)) {
            $data['bm_home_telegram_group_image_thumb'] = $this->model_tool_image->resize($tg_group_image, 100, 100);
        } else {
            $data['bm_home_telegram_group_image_thumb'] = $this->model_tool_image->resize('no_image.png', 100, 100);
        }

        $data['bm_home_telegram_group_image'] = $tg_group_image;

        // Изображение для группы ВКонтакте (баннер)
        if (isset($this->request->post['bm_home_vk_group_image'])) {
            $vk_group_image = $this->request->post['bm_home_vk_group_image'];
        } else {
            $vk_group_image = $this->config->get('bm_home_vk_group_image');
        }

        if ($vk_group_image && is_file(DIR_IMAGE . $vk_group_image)) {
            $data['bm_home_vk_group_image_thumb'] = $this->model_tool_image->resize($vk_group_image, 100, 100);
        } else {
            $data['bm_home_vk_group_image_thumb'] = $this->model_tool_image->resize('no_image.png', 100, 100);
        }

        $data['bm_home_vk_group_image'] = $vk_group_image;

        // Иконка для "Написать нам в Telegram"
        if (isset($this->request->post['bm_home_telegram_dm_icon'])) {
            $tg_dm_icon = $this->request->post['bm_home_telegram_dm_icon'];
        } else {
            $tg_dm_icon = $this->config->get('bm_home_telegram_dm_icon');
        }

        if ($tg_dm_icon && is_file(DIR_IMAGE . $tg_dm_icon)) {
            $data['bm_home_telegram_dm_icon_thumb'] = $this->model_tool_image->resize($tg_dm_icon, 100, 100);
        } else {
            $data['bm_home_telegram_dm_icon_thumb'] = $this->model_tool_image->resize('no_image.png', 100, 100);
        }

        $data['bm_home_telegram_dm_icon'] = $tg_dm_icon;

        // Ссылка на "Правила доставки"
        if (isset($this->request->post['bm_home_delivery_link'])) {
            $data['bm_home_delivery_link'] = $this->request->post['bm_home_delivery_link'];
        } else {
            $data['bm_home_delivery_link'] = $this->config->get('bm_home_delivery_link');
        }

        // --- Новости A5 ---
        $data['bm_home_news'] = $this->getNewsListByType('news');
        $data['bm_home_mailings'] = $this->getNewsListByType('mailing');

        $data['bm_home_news_tags'] = array(
            'Поступления',
            'Акции',
            'Новости магазина',
            'Анонсы',
            'Обновления',
            'Важно',
        );

        // --- Баннер (A1) ---

        if (isset($this->request->post['bm_home_banner_image'])) {
            $banner_image = $this->request->post['bm_home_banner_image'];
        } else {
            $banner_image = $this->config->get('bm_home_banner_image');
        }

        if ($banner_image && is_file(DIR_IMAGE . $banner_image)) {
            $data['bm_home_banner_thumb'] = $this->model_tool_image->resize($banner_image, 100, 100);
        } else {
            $data['bm_home_banner_thumb'] = $this->model_tool_image->resize('no_image.png', 100, 100);
        }

        $data['bm_home_banner_image'] = $banner_image;

        if (isset($this->request->post['bm_home_banner_link'])) {
            $data['bm_home_banner_link'] = $this->request->post['bm_home_banner_link'];
        } else {
            $data['bm_home_banner_link'] = $this->config->get('bm_home_banner_link');
        }

        // Текст на баннере (A1)
        if (isset($this->request->post['bm_home_banner_text'])) {
            $data['bm_home_banner_text'] = $this->request->post['bm_home_banner_text'];
        } else {
            $data['bm_home_banner_text'] = $this->config->get('bm_home_banner_text');
        }

        if (isset($this->request->post['bm_home_banner_status'])) {
            $data['bm_home_banner_status'] = (int)$this->request->post['bm_home_banner_status'];
        } else {
            $status = $this->config->get('bm_home_banner_status');
            $data['bm_home_banner_status'] = ($status !== null && $status !== '') ? (int)$status : 1;
        }

        // --- Слайды (A6) ---

        if (isset($this->request->post['bm_home_slides'])) {
            $bm_home_slides = $this->request->post['bm_home_slides'];
        } else {
            $bm_home_slides = $this->config->get('bm_home_slides');
        }

        if (!is_array($bm_home_slides)) {
            $bm_home_slides = array();
        }

        $data['bm_home_slides'] = array();

        foreach ($bm_home_slides as $key => $slide) {
            $image = !empty($slide['image']) ? $slide['image'] : '';

            if ($image && is_file(DIR_IMAGE . $image)) {
                $thumb = $this->model_tool_image->resize($image, 100, 100);
            } else {
                $thumb = $this->model_tool_image->resize('no_image.png', 100, 100);
            }

            $data['bm_home_slides'][] = array(
                'image'      => $image,
                'thumb'      => $thumb,
                'title'      => isset($slide['title']) ? $slide['title'] : '',
                'link'       => isset($slide['link']) ? $slide['link'] : '',
                'status'     => isset($slide['status']) ? (int)$slide['status'] : 1,
                'sort_order' => isset($slide['sort_order']) ? $slide['sort_order'] : ''
            );
        }

        $data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);
        $data['active_tab'] = isset($this->request->get['tab'])
            ? (string)$this->request->get['tab']
            : 'contacts';

        // Общие части админки
        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/bm_home', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/bm_home')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    private function getNewsListByType($type) {
        $type = trim((string)$type);

        if (!in_array($type, array('news', 'mailing'), true)) {
            $type = 'news';
        }

        $query = $this->db->query("
            SELECT
                news_id,
                title,
                tag,
                short_text,
                full_text,
                date_news,
                content_type,
                mail_sent,
                mail_prompt_hidden,
                mail_total_count,
                mail_success_count,
                mail_fail_count,
                mail_started_at,
                mail_completed_at
            FROM `" . DB_PREFIX . "bm_news`
            WHERE content_type = '" . $this->db->escape($type) . "'
            ORDER BY date_news DESC, news_id DESC
        ");

        $news_list = $query->rows;

        $subscriber_count = $this->getNewsSubscribersCount();
        $customer_count = $this->getCustomersCount();

        foreach ($news_list as &$news) {
            $news['content_type'] = isset($news['content_type']) ? (string)$news['content_type'] : 'news';
            $news['short_text'] = html_entity_decode((string)$news['short_text'], ENT_QUOTES, 'UTF-8');
            $news['full_text'] = html_entity_decode((string)$news['full_text'], ENT_QUOTES, 'UTF-8');

            if ($news['content_type'] === 'mailing') {
                $news['short_text'] = '';
            }
            $news['mail_sent'] = (int)$news['mail_sent'];
            $news['mail_prompt_hidden'] = (int)$news['mail_prompt_hidden'];
            $news['mail_total_count'] = (int)$news['mail_total_count'];
            $news['mail_success_count'] = (int)$news['mail_success_count'];
            $news['mail_fail_count'] = (int)$news['mail_fail_count'];
            $news['mail_started_at'] = $news['mail_started_at'] ? $news['mail_started_at'] : '';
            $news['mail_completed_at'] = $news['mail_completed_at'] ? $news['mail_completed_at'] : '';
            $news['subscriber_count'] = $subscriber_count;
            $news['customer_count'] = $customer_count;

            if (in_array($news['mail_sent'], array(2, 3), true)) {
                $news['retry_fail_count'] = $this->getRetrySubscribersCount((int)$news['news_id']);
            } else {
                $news['retry_fail_count'] = 0;
            }

            $news['send_attempts'] = $this->getNewsSendAttempts((int)$news['news_id']);
            $news['send_attempts_count'] = count($news['send_attempts']);
        }
        unset($news);

        return $news_list;
    }

    private function saveNews(array $post) {
        $news_id = isset($post['bm_home_news_id']) ? (int)$post['bm_home_news_id'] : 0;
        $title = isset($post['bm_home_news_title']) ? trim((string)$post['bm_home_news_title']) : '';
        $tag = isset($post['bm_home_news_tag']) ? trim((string)$post['bm_home_news_tag']) : '';
        $short_text = isset($post['bm_home_news_short_text']) ? (string)$post['bm_home_news_short_text'] : '';
        $full_text = isset($post['bm_home_news_full_text']) ? (string)$post['bm_home_news_full_text'] : '';
        $content_type = isset($post['bm_home_content_type']) ? trim((string)$post['bm_home_content_type']) : 'news';

        if (!in_array($content_type, array('news', 'mailing'), true)) {
            $content_type = 'news';
        }

        if ($title === '') {
            return;
        }

        if ($tag === '') {
            $tag = 'Новости магазина';
        }

        if ($content_type === 'mailing') {
            $short_text = '';
        }

        if ($news_id > 0) {
            $this->db->query("
                UPDATE `" . DB_PREFIX . "bm_news`
                SET
                    `title` = '" . $this->db->escape($title) . "',
                    `tag` = '" . $this->db->escape($tag) . "',
                    `short_text` = '" . $this->db->escape($short_text) . "',
                    `full_text` = '" . $this->db->escape($full_text) . "',
                    `content_type` = '" . $this->db->escape($content_type) . "'
                WHERE `news_id` = " . (int)$news_id . "
                  AND `mail_sent` = 0
            ");

            return;
        }

        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "bm_news`
            SET
                `title` = '" . $this->db->escape($title) . "',
                `tag` = '" . $this->db->escape($tag) . "',
                `short_text` = '" . $this->db->escape($short_text) . "',
                `full_text` = '" . $this->db->escape($full_text) . "',
                `content_type` = '" . $this->db->escape($content_type) . "',
                `date_news` = NOW(),
                `mail_sent` = 0,
                `mail_prompt_hidden` = 0,
                `mail_total_count` = 0,
                `mail_success_count` = 0,
                `mail_fail_count` = 0,
                `mail_started_at` = NULL,
                `mail_completed_at` = NULL
        ");
    }

    private function deleteNews($news_id, $content_type = 'news') {
        $news_id = (int)$news_id;
        $content_type = trim((string)$content_type);

        if (!in_array($content_type, array('news', 'mailing'), true)) {
            $content_type = 'news';
        }

        if ($news_id <= 0) {
            return;
        }

        $this->db->query("
            DELETE FROM `" . DB_PREFIX . "bm_news`
            WHERE `news_id` = " . $news_id . "
              AND `content_type` = '" . $this->db->escape($content_type) . "'
        ");
    }

    private function hideNewsMailPrompt($news_id, $content_type = 'news') {
        $news_id = (int)$news_id;
        $content_type = trim((string)$content_type);

        if (!in_array($content_type, array('news', 'mailing'), true)) {
            $content_type = 'news';
        }

        if ($news_id <= 0) {
            return;
        }

        $this->db->query("
            UPDATE `" . DB_PREFIX . "bm_news`
            SET `mail_prompt_hidden` = 1
            WHERE `news_id` = " . $news_id . "
              AND `content_type` = '" . $this->db->escape($content_type) . "'
              AND `mail_sent` = 0
        ");
    }

    private function getNewsSubscribersCount() {
        $query = $this->db->query("
            SELECT COUNT(*) AS total
            FROM `" . DB_PREFIX . "customer`
            WHERE newsletter = '1'
              AND status = '1'
              AND email <> ''
        ");

        return isset($query->row['total']) ? (int)$query->row['total'] : 0;
    }

    private function getCustomersCount() {
        $query = $this->db->query("
            SELECT COUNT(*) AS total
            FROM `" . DB_PREFIX . "customer`
            WHERE status = '1'
        ");

        return isset($query->row['total']) ? (int)$query->row['total'] : 0;
    }

    private function getNewsSubscribers() {
        $query = $this->db->query("
            SELECT customer_id, email
            FROM `" . DB_PREFIX . "customer`
            WHERE newsletter = '1'
              AND status = '1'
              AND email <> ''
            ORDER BY customer_id ASC
        ");

        return $query->rows;
    }

    private function getLastNewsSendId($news_id) {
        $news_id = (int)$news_id;

        if ($news_id <= 0) {
            return 0;
        }

        $query = $this->db->query("
            SELECT MAX(send_id) AS send_id
            FROM `" . DB_PREFIX . "bm_news_send`
            WHERE news_id = " . $news_id . "
        ");

        return !empty($query->row['send_id']) ? (int)$query->row['send_id'] : 0;
    }

    private function getRetrySubscribersCount($news_id) {
        $news_id = (int)$news_id;
        $last_send_id = $this->getLastNewsSendId($news_id);

        if ($news_id <= 0 || $last_send_id <= 0) {
            return 0;
        }

        $query = $this->db->query("
            SELECT COUNT(*) AS total
            FROM `" . DB_PREFIX . "bm_news_send`
            WHERE news_id = " . $news_id . "
              AND send_id = " . $last_send_id . "
              AND is_sent = 0
        ");

        return isset($query->row['total']) ? (int)$query->row['total'] : 0;
    }

    private function getRetrySubscribers($news_id) {
        $news_id = (int)$news_id;
        $last_send_id = $this->getLastNewsSendId($news_id);

        if ($news_id <= 0 || $last_send_id <= 0) {
            return array();
        }

        $query = $this->db->query("
            SELECT
                s.customer_id,
                IFNULL(c.email, '') AS email
            FROM `" . DB_PREFIX . "bm_news_send` s
            LEFT JOIN `" . DB_PREFIX . "customer` c
                ON c.customer_id = s.customer_id
            WHERE s.news_id = " . $news_id . "
            AND s.send_id = " . $last_send_id . "
            AND s.is_sent = 0
            ORDER BY s.send_row_id ASC
        ");

        return $query->rows;
    }

    private function getNextNewsSendId($news_id) {
        return $this->getLastNewsSendId((int)$news_id) + 1;
    }

    private function getNewsSendAttempts($news_id) {
        $news_id = (int)$news_id;

        if ($news_id <= 0) {
            return array();
        }

        $query = $this->db->query("
            SELECT
                send_id,
                COUNT(*) AS total_count,
                SUM(CASE WHEN is_sent = 1 THEN 1 ELSE 0 END) AS success_count,
                MIN(date_attempt) AS started_at,
                MAX(date_attempt) AS completed_at
            FROM `" . DB_PREFIX . "bm_news_send`
            WHERE news_id = " . $news_id . "
            GROUP BY send_id
            ORDER BY send_id ASC
        ");

        $attempts = array();

        foreach ($query->rows as $row) {
            $total_count = isset($row['total_count']) ? (int)$row['total_count'] : 0;
            $success_count = isset($row['success_count']) ? (int)$row['success_count'] : 0;

            if ($success_count > $total_count) {
                $success_count = $total_count;
            }

            $attempts[] = array(
                'send_id' => (int)$row['send_id'],
                'total_count' => $total_count,
                'success_count' => $success_count,
                'fail_count' => max(0, $total_count - $success_count),
                'started_at' => !empty($row['started_at']) ? $row['started_at'] : '',
                'completed_at' => !empty($row['completed_at']) ? $row['completed_at'] : ''
            );
        }

        return $attempts;
    }

    private function downloadNewsLogCsv($news_id) {
        $news_id = (int)$news_id;

        if ($news_id <= 0) {
            return;
        }

        $news_query = $this->db->query("
            SELECT news_id, title
            FROM `" . DB_PREFIX . "bm_news`
            WHERE news_id = " . $news_id . "
            LIMIT 1
        ");

        if (empty($news_query->row)) {
            return;
        }

        $rows_query = $this->db->query("
            SELECT
                send_id,
                customer_id,
                email,
                is_sent,
                error_text,
                date_attempt
            FROM `" . DB_PREFIX . "bm_news_send`
            WHERE news_id = " . $news_id . "
            ORDER BY send_id ASC, send_row_id ASC
        ");

        $filename = 'news-log-' . $news_id . '-' . date('Y-m-d-H-i-s') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, array(
            'send_id',
            'news_id',
            'customer_id',
            'email',
            'is_sent',
            'error_text',
            'date_attempt'
        ), ';');

        foreach ($rows_query->rows as $row) {
            fputcsv($output, array(
                isset($row['send_id']) ? (int)$row['send_id'] : 0,
                $news_id,
                isset($row['customer_id']) ? (int)$row['customer_id'] : 0,
                isset($row['email']) ? $row['email'] : '',
                !empty($row['is_sent']) ? 1 : 0,
                isset($row['error_text']) ? $row['error_text'] : '',
                isset($row['date_attempt']) ? $row['date_attempt'] : ''
            ), ';');
        }

        fclose($output);
        exit;
    }

    private function downloadNewsAttemptLogCsv($news_id, $send_id) {
        $news_id = (int)$news_id;
        $send_id = (int)$send_id;

        if ($news_id <= 0 || $send_id <= 0) {
            return;
        }

        $news_query = $this->db->query("
            SELECT news_id, title
            FROM `" . DB_PREFIX . "bm_news`
            WHERE news_id = " . $news_id . "
            LIMIT 1
        ");

        if (empty($news_query->row)) {
            return;
        }

        $rows_query = $this->db->query("
            SELECT
                send_id,
                customer_id,
                email,
                is_sent,
                error_text,
                date_attempt
            FROM `" . DB_PREFIX . "bm_news_send`
            WHERE news_id = " . $news_id . "
              AND send_id = " . $send_id . "
            ORDER BY send_row_id ASC
        ");

        $filename = 'news-log-' . $news_id . '-attempt-' . $send_id . '-' . date('Y-m-d-H-i-s') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, array(
            'send_id',
            'news_id',
            'customer_id',
            'email',
            'is_sent',
            'error_text',
            'date_attempt'
        ), ';');

        foreach ($rows_query->rows as $row) {
            fputcsv($output, array(
                isset($row['send_id']) ? (int)$row['send_id'] : 0,
                $news_id,
                isset($row['customer_id']) ? (int)$row['customer_id'] : 0,
                isset($row['email']) ? $row['email'] : '',
                !empty($row['is_sent']) ? 1 : 0,
                isset($row['error_text']) ? $row['error_text'] : '',
                isset($row['date_attempt']) ? $row['date_attempt'] : ''
            ), ';');
        }

        fclose($output);
        exit;
    }

    private function updateNewsMailAggregate($news_id, $content_type = 'news') {
        $news_id = (int)$news_id;
        $content_type = trim((string)$content_type);

        if (!in_array($content_type, array('news', 'mailing'), true)) {
            $content_type = 'news';
        }

        if ($news_id <= 0) {
            return;
        }

        $news_query = $this->db->query("
            SELECT
                `mail_total_count`,
                `mail_started_at`,
                `content_type`
            FROM `" . DB_PREFIX . "bm_news`
            WHERE `news_id` = " . $news_id . "
              AND `content_type` = '" . $this->db->escape($content_type) . "'
            LIMIT 1
        ");

        if (empty($news_query->row)) {
            return;
        }

        $mail_total_count = isset($news_query->row['mail_total_count'])
            ? (int)$news_query->row['mail_total_count']
            : 0;

        $mail_started_at = !empty($news_query->row['mail_started_at'])
            ? $news_query->row['mail_started_at']
            : null;

        if ($mail_total_count < 0) {
            $mail_total_count = 0;
        }

        $success_query = $this->db->query("
            SELECT COUNT(DISTINCT customer_id) AS total
            FROM `" . DB_PREFIX . "bm_news_send`
            WHERE `news_id` = " . $news_id . "
              AND `is_sent` = 1
        ");

        $mail_success_count = isset($success_query->row['total'])
            ? (int)$success_query->row['total']
            : 0;

        if ($mail_success_count > $mail_total_count) {
            $mail_success_count = $mail_total_count;
        }

        $mail_fail_count = max(0, $mail_total_count - $mail_success_count);

        if ($mail_total_count === 0) {
            $mail_sent = 3;
        } elseif ($mail_success_count === 0) {
            $mail_sent = 3;
        } elseif ($mail_fail_count === 0) {
            $mail_sent = 1;
        } else {
            $mail_sent = 2;
        }

        $mail_started_at_sql = $mail_started_at
            ? "'" . $this->db->escape($mail_started_at) . "'"
            : "NULL";

        $this->db->query("
            UPDATE `" . DB_PREFIX . "bm_news`
            SET
                `mail_sent` = " . (int)$mail_sent . ",
                `mail_total_count` = " . (int)$mail_total_count . ",
                `mail_success_count` = " . (int)$mail_success_count . ",
                `mail_fail_count` = " . (int)$mail_fail_count . ",
                `mail_started_at` = " . $mail_started_at_sql . ",
                `mail_completed_at` = NOW()
            WHERE `news_id` = " . $news_id . "
              AND `content_type` = '" . $this->db->escape($content_type) . "'
        ");
    }

    private function sendNewsMail($news_id, $is_retry = false, $content_type = 'news') {
        $news_id = (int)$news_id;
        $content_type = trim((string)$content_type);

        if (!in_array($content_type, array('news', 'mailing'), true)) {
            $content_type = 'news';
        }

        if ($news_id <= 0) {
            return false;
        }

        $news_query = $this->db->query("
            SELECT
                news_id,
                title,
                tag,
                short_text,
                full_text,
                content_type,
                date_news,
                mail_sent,
                mail_total_count,
                mail_started_at
            FROM `" . DB_PREFIX . "bm_news`
            WHERE news_id = " . $news_id . "
              AND content_type = '" . $this->db->escape($content_type) . "'
            LIMIT 1
        ");

        if (empty($news_query->row)) {
            return false;
        }

        $news = $news_query->row;
        $current_mail_sent = isset($news['mail_sent']) ? (int)$news['mail_sent'] : 0;
        $actual_content_type = isset($news['content_type']) ? (string)$news['content_type'] : 'news';

        if (!in_array($actual_content_type, array('news', 'mailing'), true)) {
            $actual_content_type = 'news';
        }

        if (!$is_retry && $current_mail_sent !== 0) {
            return false;
        }

        if ($is_retry && !in_array($current_mail_sent, array(2, 3), true)) {
            return false;
        }

        $short_text = html_entity_decode((string)$news['short_text'], ENT_QUOTES, 'UTF-8');
        $full_text = html_entity_decode((string)$news['full_text'], ENT_QUOTES, 'UTF-8');

        if ($actual_content_type === 'mailing') {
            $mail_text = $full_text;
        } else {
            $mail_text = trim(strip_tags($full_text)) !== '' ? $full_text : $short_text;
        }

        if (trim(strip_tags($mail_text)) === '') {
            return false;
        }

        if ($is_retry) {
            $subscribers = $this->getRetrySubscribers($news_id);
        } else {
            $subscribers = $this->getNewsSubscribers();
        }

        if (!$subscribers) {
            if (!$is_retry) {
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "bm_news`
                    SET
                        `mail_sent` = 3,
                        `mail_total_count` = 0,
                        `mail_success_count` = 0,
                        `mail_fail_count` = 0,
                        `mail_started_at` = NOW(),
                        `mail_completed_at` = NOW()
                    WHERE `news_id` = " . $news_id . "
                      AND `content_type` = '" . $this->db->escape($actual_content_type) . "'
                ");
            } else {
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "bm_news`
                    SET `mail_completed_at` = NOW()
                    WHERE `news_id` = " . $news_id . "
                      AND `content_type` = '" . $this->db->escape($actual_content_type) . "'
                ");
            }

            return false;
        }

        $send_id = $this->getNextNewsSendId($news_id);
        $subject = (string)$news['title'];
        $news_url = HTTPS_CATALOG . 'news';

        $message = '';
        $message .= '<html><body>';
        $message .= '<h2>' . htmlspecialchars((string)$news['title'], ENT_QUOTES, 'UTF-8') . '</h2>';
        $message .= '<div>' . $mail_text . '</div>';

        if ($actual_content_type === 'news') {
            $message .= '<p><a href="' . $news_url . '">Перейти к новостям</a></p>';
        }

        $message .= '<hr>';
        $message .= '<p style="font-size:13px;color:#777;">';
        $message .= 'Если вы не хотите получать рассылки от магазина «Бумажный Мастер», ';
        $message .= 'вы можете отключить их в ';
        $message .= '<a href="' . HTTPS_CATALOG . 'index.php?route=account/account">личном кабинете</a>.';
        $message .= '</p>';
        $message .= '</body></html>';

        if (!$is_retry) {
            $this->db->query("
                UPDATE `" . DB_PREFIX . "bm_news`
                SET
                    `mail_sent` = 4,
                    `mail_prompt_hidden` = 0,
                    `mail_total_count` = " . (int)count($subscribers) . ",
                    `mail_success_count` = 0,
                    `mail_fail_count` = 0,
                    `mail_started_at` = NOW(),
                    `mail_completed_at` = NULL
                WHERE `news_id` = " . $news_id . "
                  AND `content_type` = '" . $this->db->escape($actual_content_type) . "'
            ");
        } else {
            $started_at_sql = !empty($news['mail_started_at'])
                ? "`mail_started_at` = `mail_started_at`"
                : "`mail_started_at` = NOW()";

            $this->db->query("
                UPDATE `" . DB_PREFIX . "bm_news`
                SET
                    `mail_sent` = 4,
                    " . $started_at_sql . ",
                    `mail_completed_at` = NULL
                WHERE `news_id` = " . $news_id . "
                  AND `content_type` = '" . $this->db->escape($actual_content_type) . "'
            ");
        }

        foreach ($subscribers as $subscriber) {
            $customer_id = isset($subscriber['customer_id']) ? (int)$subscriber['customer_id'] : 0;
            $email = isset($subscriber['email']) ? trim((string)$subscriber['email']) : '';

            if ($email === '') {
                $this->db->query("
                    INSERT INTO `" . DB_PREFIX . "bm_news_send`
                    SET
                        `send_id` = " . (int)$send_id . ",
                        `news_id` = " . $news_id . ",
                        `customer_id` = " . $customer_id . ",
                        `email` = '',
                        `is_sent` = 0,
                        `error_text` = '" . $this->db->escape('Пустой email') . "',
                        `date_attempt` = NOW()
                ");

                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->db->query("
                    INSERT INTO `" . DB_PREFIX . "bm_news_send`
                    SET
                        `send_id` = " . (int)$send_id . ",
                        `news_id` = " . $news_id . ",
                        `customer_id` = " . $customer_id . ",
                        `email` = '" . $this->db->escape($email) . "',
                        `is_sent` = 0,
                        `error_text` = '" . $this->db->escape('Некорректный email') . "',
                        `date_attempt` = NOW()
                ");

                continue;
            }

            $is_sent = 0;
            $error_text = '';

            try {
                $mail = new Mail($this->config->get('config_mail_engine'));
                $mail->parameter = $this->config->get('config_mail_parameter');
                $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
                $mail->smtp_username = $this->config->get('config_mail_smtp_username');
                $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
                $mail->smtp_port = $this->config->get('config_mail_smtp_port');
                $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

                $mail->setTo($email);
                $mail->setFrom($this->config->get('config_email'));
                $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
                $mail->setSubject($subject);
                $mail->setText(trim(html_entity_decode(strip_tags(str_replace(array('<br>', '<br/>', '<br />', '</p>'), array("\n", "\n", "\n", "</p>\n"), $message)), ENT_QUOTES, 'UTF-8')));
                $mail->setHtml($message);
                $mail->send();

                $is_sent = 1;
            } catch (\Exception $e) {
                $is_sent = 0;
                $error_text = $e->getMessage();
            } catch (\Throwable $e) {
                $is_sent = 0;
                $error_text = $e->getMessage();
            }

            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "bm_news_send`
                SET
                    `send_id` = " . (int)$send_id . ",
                    `news_id` = " . $news_id . ",
                    `customer_id` = " . $customer_id . ",
                    `email` = '" . $this->db->escape($email) . "',
                    `is_sent` = " . (int)$is_sent . ",
                    `error_text` = '" . $this->db->escape($error_text) . "',
                    `date_attempt` = NOW()
            ");
        }

        $this->updateNewsMailAggregate($news_id, $actual_content_type);

        return true;
    }
}
