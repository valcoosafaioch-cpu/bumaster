<?php
class ControllerExtensionModuleBmHome extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/bm_home');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('tool/image');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
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

        // --- Поле "Текст A5" ---

        if (isset($this->request->post['bm_home_text'])) {
            $data['bm_home_text'] = $this->request->post['bm_home_text'];
        } else {
            $data['bm_home_text'] = $this->config->get('bm_home_text');
        }

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
}
