<?php
class ControllerInformationNews extends Controller {
    public function index() {
        $this->document->setTitle('Новости');

        $this->document->addStyle('catalog/view/theme/materialize/stylesheet/news-page.css');

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => 'Главная',
            'href' => $this->url->link('common/home')
        );

        $data['breadcrumbs'][] = array(
            'text' => 'Новости',
            'href' => $this->url->link('information/news')
        );

        $news_query = $this->db->query("
            SELECT
                news_id,
                title,
                tag,
                short_text,
                full_text,
                date_news,
                content_type
            FROM `" . DB_PREFIX . "bm_news`
            WHERE content_type = 'news'
            ORDER BY date_news DESC, news_id DESC
        ");

        $data['news_list'] = array();

        foreach ($news_query->rows as $index => $row) {
            $short_text = html_entity_decode((string)$row['short_text'], ENT_QUOTES, 'UTF-8');
            $full_text = html_entity_decode((string)$row['full_text'], ENT_QUOTES, 'UTF-8');

            $has_full_text = trim(strip_tags($full_text)) !== '';
            $display_text = $has_full_text ? $full_text : $short_text;

            $data['news_list'][] = array(
                'news_id' => (int)$row['news_id'],
                'title' => (string)$row['title'],
                'tag' => (string)$row['tag'],
                'short_text' => $short_text,
                'full_text' => $full_text,
                'display_text' => $display_text,
                'has_full_text' => $has_full_text,
                'date_news' => (string)$row['date_news'],
                'date_news_fmt' => date('d.m.Y', strtotime($row['date_news'])),
                'is_first' => ($index === 0)
            );
        }

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');

        $this->response->setOutput($this->load->view('information/news', $data));
    }
}