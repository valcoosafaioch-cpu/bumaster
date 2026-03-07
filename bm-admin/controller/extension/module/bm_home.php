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

            if ($news_action === 'save_news') {
                $this->saveNews($this->request->post);

                $this->session->data['success'] = 'Новость успешно сохранена!';

                $this->response->redirect(
                    $this->url->link(
                        'extension/module/bm_home',
                        'user_token=' . $this->session->data['user_token'] . '&tab=a5',
                        true
                    )
                );
            }

            if ($news_action === 'delete_news') {
                $news_id = isset($this->request->post['bm_home_news_id'])
                    ? (int)$this->request->post['bm_home_news_id']
                    : 0;

                if ($news_id > 0) {
                    $this->deleteNews($news_id);
                }

                $this->session->data['success'] = 'Новость успешно удалена!';

                $this->response->redirect(
                    $this->url->link(
                        'extension/module/bm_home',
                        'user_token=' . $this->session->data['user_token'] . '&tab=a5',
                        true
                    )
                );
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
        $data['bm_home_news'] = $this->getNewsList();

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
            : '';

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

        private function getNewsList() {
        $query = $this->db->query("
            SELECT
                news_id,
                title,
                tag,
                short_text,
                full_text,
                date_news,
                mail_sent
            FROM `" . DB_PREFIX . "bm_news`
            ORDER BY date_news DESC, news_id DESC
        ");

        return $query->rows;
    }

    private function saveNews(array $post) {
        $news_id = isset($post['bm_home_news_id']) ? (int)$post['bm_home_news_id'] : 0;
        $title = isset($post['bm_home_news_title']) ? trim((string)$post['bm_home_news_title']) : '';
        $tag = isset($post['bm_home_news_tag']) ? trim((string)$post['bm_home_news_tag']) : '';
        $short_text = isset($post['bm_home_news_short_text']) ? (string)$post['bm_home_news_short_text'] : '';
        $full_text = isset($post['bm_home_news_full_text']) ? (string)$post['bm_home_news_full_text'] : '';

        if ($title === '') {
            return;
        }

        if ($tag === '') {
            $tag = 'Новости магазина';
        }

        if ($news_id > 0) {
            $this->db->query("
                UPDATE `" . DB_PREFIX . "bm_news`
                SET
                    `title` = '" . $this->db->escape($title) . "',
                    `tag` = '" . $this->db->escape($tag) . "',
                    `short_text` = '" . $this->db->escape($short_text) . "',
                    `full_text` = '" . $this->db->escape($full_text) . "'
                WHERE `news_id` = " . (int)$news_id . "
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
                `date_news` = NOW(),
                `mail_sent` = 0
        ");

        $news_id = (int)$this->db->getLastId();

        if ($news_id > 0) {
            $mail_sent = $this->sendNewsMail($news_id);

            if ($mail_sent) {
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "bm_news`
                    SET `mail_sent` = '1'
                    WHERE `news_id` = " . $news_id . "
                ");
            }
        }
    }

    private function deleteNews($news_id) {
        $news_id = (int)$news_id;

        if ($news_id <= 0) {
            return;
        }

        $this->db->query("
            DELETE FROM `" . DB_PREFIX . "bm_news`
            WHERE `news_id` = " . $news_id . "
        ");
    }

    private function getNewsSubscribers() {
        $query = $this->db->query("
            SELECT email
            FROM `" . DB_PREFIX . "customer`
            WHERE newsletter = '1'
              AND status = '1'
              AND email <> ''
            ORDER BY customer_id ASC
        ");

        return $query->rows;
    }

    private function sendNewsMail($news_id) {
        $news_id = (int)$news_id;

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
                date_news
            FROM `" . DB_PREFIX . "bm_news`
            WHERE news_id = " . $news_id . "
            LIMIT 1
        ");

        if (empty($news_query->row)) {
            return false;
        }

        $news = $news_query->row;

        $short_text = html_entity_decode((string)$news['short_text'], ENT_QUOTES, 'UTF-8');
        $full_text = html_entity_decode((string)$news['full_text'], ENT_QUOTES, 'UTF-8');

        $mail_text = trim(strip_tags($full_text)) !== '' ? $full_text : $short_text;

        $subscribers = $this->getNewsSubscribers();

        if (!$subscribers) {
            return false;
        }

        $subject = 'Бумажный Мастер — ' . (string)$news['title'];

        $news_url = HTTPS_CATALOG . 'news';

        $message = '';
        $message .= '<html><body>';
        $message .= '<h2>' . htmlspecialchars((string)$news['title'], ENT_QUOTES, 'UTF-8') . '</h2>';
        $message .= '<p><strong>Дата:</strong> ' . date('d.m.Y', strtotime($news['date_news'])) . '</p>';
        $message .= '<div>' . $mail_text . '</div>';
        $message .= '<p><a href="' . $news_url . '">Перейти к новостям</a></p>';
        $message .= '</body></html>';

        $sent_count = 0;

        foreach ($subscribers as $subscriber) {
            if (empty($subscriber['email'])) {
                continue;
            }

            $mail = new Mail($this->config->get('config_mail_engine'));
            $mail->parameter = $this->config->get('config_mail_parameter');
            $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
            $mail->smtp_username = $this->config->get('config_mail_smtp_username');
            $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
            $mail->smtp_port = $this->config->get('config_mail_smtp_port');
            $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

            $mail->setTo($subscriber['email']);
            $mail->setFrom($this->config->get('config_email'));
            $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
            $mail->setSubject($subject);
            $mail->setHtml($message);
            $mail->send();

            $sent_count++;
        }

        return ($sent_count > 0);
    }
}
