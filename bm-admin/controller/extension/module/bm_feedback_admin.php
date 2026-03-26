<?php
class ControllerExtensionModuleBmFeedbackAdmin extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/bm_feedback_admin');
		$this->document->setTitle($this->language->get('heading_title'));
        $this->document->addStyle('view/stylesheet/bm_feedback_admin.css');

		$this->load->model('extension/module/bm_feedback_admin');

		if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validate()) {
			$this->handlePost();
			return;
		}

		$data = array();

		$data['heading_title'] = $this->language->get('heading_title');
		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		} elseif (!empty($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$tab = $this->getActiveTab();
		$subtab = $this->getActiveSubtab();
		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;

		if ($page < 1) {
			$page = 1;
		}

		$limit = 20;
		$start = ($page - 1) * $limit;

		$data['tab'] = $tab;
		$data['subtab'] = $subtab;
		$data['page'] = $page;
		$data['limit'] = $limit;

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => 'Бумажный Мастер',
			'href' => $this->url->link('extension/module/bm_home', 'user_token=' . $data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/bm_feedback_admin', 'user_token=' . $data['user_token'], true)
		);

		$data['action'] = $this->url->link(
			'extension/module/bm_feedback_admin',
			'user_token=' . $data['user_token'] . '&tab=' . $tab . '&subtab=' . $subtab . '&page=' . $page,
			true
		);

		$data['tab_reviews_href'] = $this->url->link(
			'extension/module/bm_feedback_admin',
			'user_token=' . $data['user_token'] . '&tab=reviews&subtab=need_action',
			true
		);

		$data['tab_questions_href'] = $this->url->link(
			'extension/module/bm_feedback_admin',
			'user_token=' . $data['user_token'] . '&tab=questions&subtab=need_action',
			true
		);

		$data['tab_orders_href'] = $this->url->link(
			'extension/module/bm_feedback_admin',
			'user_token=' . $data['user_token'] . '&tab=orders',
			true
		);

		$data['subtab_need_action_href'] = $this->url->link(
			'extension/module/bm_feedback_admin',
			'user_token=' . $data['user_token'] . '&tab=' . $tab . '&subtab=need_action',
			true
		);

		$data['subtab_published_href'] = $this->url->link(
			'extension/module/bm_feedback_admin',
			'user_token=' . $data['user_token'] . '&tab=' . $tab . '&subtab=published',
			true
		);

		$data['subtab_rejected_href'] = $this->url->link(
			'extension/module/bm_feedback_admin',
			'user_token=' . $data['user_token'] . '&tab=' . $tab . '&subtab=rejected',
			true
		);

		$data['need_action_total'] = $this->model_extension_module_bm_feedback_admin->getNeedActionTotal();
		$data['need_action_reviews_total'] = $this->model_extension_module_bm_feedback_admin->getNeedActionReviewsTotal();
		$data['need_action_questions_total'] = $this->model_extension_module_bm_feedback_admin->getNeedActionQuestionsTotal();

		$data['reviews_need_action_total'] = $this->model_extension_module_bm_feedback_admin->getReviewsNeedActionTotal();
		$data['reviews_published_total'] = $this->model_extension_module_bm_feedback_admin->getReviewsPublishedTotal();
		$data['reviews_rejected_total'] = $this->model_extension_module_bm_feedback_admin->getReviewsRejectedTotal();

		$data['questions_need_action_total'] = $this->model_extension_module_bm_feedback_admin->getQuestionsNeedActionTotal();
		$data['questions_published_total'] = $this->model_extension_module_bm_feedback_admin->getQuestionsPublishedTotal();
		$data['questions_rejected_total'] = $this->model_extension_module_bm_feedback_admin->getQuestionsRejectedTotal();

		$data['items'] = array();
		$total = 0;

		if ($tab === 'reviews') {
			if ($subtab === 'published') {
				$total = $data['reviews_published_total'];
				$items = $this->model_extension_module_bm_feedback_admin->getReviewsPublished(array(
					'start' => $start,
					'limit' => $limit
				));
			} elseif ($subtab === 'rejected') {
				$total = $data['reviews_rejected_total'];
				$items = $this->model_extension_module_bm_feedback_admin->getReviewsRejected(array(
					'start' => $start,
					'limit' => $limit
				));
			} else {
				$total = $data['reviews_need_action_total'];
				$items = $this->model_extension_module_bm_feedback_admin->getReviewsNeedAction(array(
					'start' => $start,
					'limit' => $limit
				));
			}
		} elseif ($tab === 'questions') {
			if ($subtab === 'published') {
				$total = $data['questions_published_total'];
				$items = $this->model_extension_module_bm_feedback_admin->getQuestionsPublished(array(
					'start' => $start,
					'limit' => $limit
				));
			} elseif ($subtab === 'rejected') {
				$total = $data['questions_rejected_total'];
				$items = $this->model_extension_module_bm_feedback_admin->getQuestionsRejected(array(
					'start' => $start,
					'limit' => $limit
				));
			} else {
				$total = $data['questions_need_action_total'];
				$items = $this->model_extension_module_bm_feedback_admin->getQuestionsNeedAction(array(
					'start' => $start,
					'limit' => $limit
				));
			}
		} else {
			$items = array();
		}

		foreach ($items as &$item) {
			$item['status_label'] = $this->getStatusLabel($item['moderation_status']);
			$item['source_label'] = $this->getSourceLabel($item['source_code']);
			$item['source_icon'] = $this->getSourceIcon($item['source_code']);
			$item['source_has_link'] = !empty($item['source_url']);

			$item['product_href'] = '';
            if (!empty($item['product_id'])) {
                $catalog = '';

                if (defined('HTTPS_CATALOG') && HTTPS_CATALOG) {
                    $catalog = HTTPS_CATALOG;
                } elseif (defined('HTTP_CATALOG') && HTTP_CATALOG) {
                    $catalog = HTTP_CATALOG;
                }

                if ($catalog !== '') {
                    $item['product_href'] = rtrim($catalog, '/') . '/index.php?route=product/product&product_id=' . (int)$item['product_id'];
                }
            }

			$item['order_href'] = '';
			if (!empty($item['order_id'])) {
				$item['order_href'] = $this->url->link(
					'sale/order/info',
					'user_token=' . $data['user_token'] . '&order_id=' . (int)$item['order_id'],
					true
				);
			}
		}
		unset($item);

		$data['items'] = $items;
		$data['total'] = $total;

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link(
			'extension/module/bm_feedback_admin',
			'user_token=' . $data['user_token'] . '&tab=' . $tab . '&subtab=' . $subtab . '&page={page}',
			true
		);

		$data['pagination'] = ($tab === 'orders') ? '' : $pagination->render();
		$data['results'] = ($tab === 'orders') ? '' : sprintf(
			$this->language->get('text_pagination'),
			($total) ? (($page - 1) * $limit) + 1 : 0,
			((($page - 1) * $limit) > ($total - $limit)) ? $total : ((($page - 1) * $limit) + $limit),
			$total,
			ceil($total / $limit)
		);

		$data['text_orders_stub_title'] = $this->language->get('text_orders_stub_title');
		$data['text_orders_stub_description'] = $this->language->get('text_orders_stub_description');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/bm_feedback_admin', $data));
	}

	private function handlePost() {
		$this->load->language('extension/module/bm_feedback_admin');
		$this->load->model('extension/module/bm_feedback_admin');

		$tab = $this->getActiveTab();
		$subtab = $this->getActiveSubtab();
		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;

		if ($page < 1) {
			$page = 1;
		}

		$feedback_id = isset($this->request->post['feedback_id']) ? (int)$this->request->post['feedback_id'] : 0;
		$action = isset($this->request->post['bm_feedback_action']) ? (string)$this->request->post['bm_feedback_action'] : '';

		if ($feedback_id <= 0) {
			$this->session->data['error_warning'] = $this->language->get('error_feedback_not_found');
			$this->redirectBack($tab, $subtab, $page);
			return;
		}

		if ($action === 'approve') {
			if ($this->model_extension_module_bm_feedback_admin->approveFeedback($feedback_id)) {
				$this->session->data['success'] = $this->language->get('success_approved');
			} else {
				$this->session->data['error_warning'] = $this->language->get('error_action_failed');
			}

			$this->redirectBack($tab, $subtab, $page);
			return;
		}

		if ($action === 'reject') {
			$comment = isset($this->request->post['moderation_comment']) ? trim((string)$this->request->post['moderation_comment']) : '';

			if ($comment === '') {
				$this->session->data['error_warning'] = $this->language->get('error_reject_comment_required');
				$this->redirectBack($tab, $subtab, $page);
				return;
			}

			if ($this->model_extension_module_bm_feedback_admin->rejectFeedback($feedback_id, $comment)) {
				$feedback = $this->model_extension_module_bm_feedback_admin->getFeedbackWithCustomer($feedback_id);

				if ($feedback && !empty($feedback['email'])) {
					$email_ok = $this->sendRejectedEmail($feedback);

					if ($email_ok) {
						$this->session->data['success'] = $this->language->get('success_rejected');
					} else {
						$this->session->data['error_warning'] = $this->language->get('error_email_failed');
					}
				} else {
					$this->session->data['success'] = $this->language->get('success_rejected');
				}
			} else {
				$this->session->data['error_warning'] = $this->language->get('error_action_failed');
			}

			$this->redirectBack($tab, $subtab, $page);
			return;
		}

		if ($action === 'reply') {
			$reply = isset($this->request->post['admin_reply']) ? trim((string)$this->request->post['admin_reply']) : '';

			if ($reply === '') {
				$this->session->data['error_warning'] = $this->language->get('error_reply_required');
				$this->redirectBack($tab, $subtab, $page);
				return;
			}

			if ($this->model_extension_module_bm_feedback_admin->saveReply($feedback_id, $reply)) {
				$feedback = $this->model_extension_module_bm_feedback_admin->getFeedbackWithCustomer($feedback_id);

				if ($feedback && $feedback['type'] === 'question' && !empty($feedback['email'])) {
					$email_ok = $this->sendQuestionReplyEmail($feedback);

					if ($email_ok) {
						$this->session->data['success'] = $this->language->get('success_reply_saved');
					} else {
						$this->session->data['error_warning'] = $this->language->get('error_email_failed');
					}
				} else {
					$this->session->data['success'] = $this->language->get('success_reply_saved');
				}
			} else {
				$this->session->data['error_warning'] = $this->language->get('error_action_failed');
			}

			$this->redirectBack($tab, $subtab, $page);
			return;
		}

		$this->session->data['error_warning'] = $this->language->get('error_action_failed');
		$this->redirectBack($tab, $subtab, $page);
	}

	private function redirectBack($tab, $subtab, $page) {
		$this->response->redirect($this->url->link(
			'extension/module/bm_feedback_admin',
			'user_token=' . $this->session->data['user_token'] . '&tab=' . $tab . '&subtab=' . $subtab . '&page=' . (int)$page,
			true
		));
	}

	private function getActiveTab() {
		$tab = isset($this->request->get['tab']) ? (string)$this->request->get['tab'] : 'reviews';
		$allowed = array('reviews', 'questions', 'orders');

		return in_array($tab, $allowed, true) ? $tab : 'reviews';
	}

	private function getActiveSubtab() {
		$subtab = isset($this->request->get['subtab']) ? (string)$this->request->get['subtab'] : 'need_action';
		$allowed = array('need_action', 'published', 'rejected');

		return in_array($subtab, $allowed, true) ? $subtab : 'need_action';
	}

	private function getStatusLabel($status) {
		if ($status === 'approved') {
			return $this->language->get('text_status_approved');
		}

		if ($status === 'rejected') {
			return $this->language->get('text_status_rejected');
		}

		return $this->language->get('text_status_pending');
	}

	private function getSourceLabel($source_code) {
		$source_code = (string)$source_code;

		$map = array(
			'site'  => 'text_source_site',
			'ozon'  => 'text_source_ozon',
			'wb'    => 'text_source_wb',
			'ym'    => 'text_source_yandex',
			'yandex'=> 'text_source_yandex',
			'avito' => 'text_source_avito'
		);

		if (isset($map[$source_code])) {
			return $this->language->get($map[$source_code]);
		}

		return $this->language->get('text_source_unknown');
	}

	private function getSourceIcon($source_code) {
		$source_code = (string)$source_code;

		$map = array(
			'ozon'   => 'ozon.jpg',
			'wb'     => 'wb.jpg',
			'avito'  => 'avito.jpg',
			'ym'     => 'ym.jpg',
			'yandex' => 'ym.jpg'
		);

		if (!isset($map[$source_code])) {
			return '';
		}

		$catalog = '';

		if (defined('HTTPS_CATALOG') && HTTPS_CATALOG) {
			$catalog = HTTPS_CATALOG;
		} elseif (defined('HTTP_CATALOG') && HTTP_CATALOG) {
			$catalog = HTTP_CATALOG;
		}

		if ($catalog === '') {
			return '';
		}

		return rtrim($catalog, '/') . '/image/catalog/review_sources/' . $map[$source_code];
	}

	private function sendRejectedEmail(array $feedback) {
		$to = isset($feedback['email']) ? trim((string)$feedback['email']) : '';

		if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
			return false;
		}

		$type_label = ($feedback['type'] === 'question') ? 'вопрос' : 'отзыв';

        $product_name = !empty($feedback['product_name'])
            ? htmlspecialchars_decode($feedback['product_name'], ENT_QUOTES)
            : $this->language->get('text_no_product');

		$comment = !empty($feedback['moderation_comment'])
			? nl2br(htmlspecialchars($feedback['moderation_comment'], ENT_QUOTES, 'UTF-8'))
			: $this->language->get('text_no_moderation_comment');

        $feedback_text = !empty($feedback['text'])
            ? nl2br(htmlspecialchars($feedback['text'], ENT_QUOTES, 'UTF-8'))
            : '-';

		$subject = html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8') . ' - уведомление по модерации';

		$message  = '<html><body style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5;color:#222;">';
        $message .= '<p>Здравствуйте!</p>';
        $message .= '<p>Ваш ' . $type_label . ' не прошёл модерацию.</p>';
        $message .= '<p><strong>Товар:</strong> ' . $product_name . '</p>';
        $message .= '<p><strong>Текст ' . ($feedback['type'] === 'question' ? 'вопроса' : 'отзыва') . ':</strong><br>' . $feedback_text . '</p>';
        $message .= '<p><strong>Комментарий модерации:</strong><br>' . $comment . '</p>';
        $message .= '<p>С уважением,<br>' . htmlspecialchars($this->config->get('config_name'), ENT_QUOTES, 'UTF-8') . '</p>';
        $message .= '</body></html>';

		return $this->sendMail($to, $subject, $message);
	}

	private function sendQuestionReplyEmail(array $feedback) {
		$to = isset($feedback['email']) ? trim((string)$feedback['email']) : '';

		if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
			return false;
		}

		$product_name = !empty($feedback['product_name'])
            ? htmlspecialchars_decode($feedback['product_name'], ENT_QUOTES)
            : $this->language->get('text_no_product');

		$reply = !empty($feedback['admin_reply'])
			? nl2br(htmlspecialchars($feedback['admin_reply'], ENT_QUOTES, 'UTF-8'))
			: '';

        $feedback_text = !empty($feedback['text'])
            ? nl2br(htmlspecialchars($feedback['text'], ENT_QUOTES, 'UTF-8'))
            : '-';

		if ($reply === '') {
			return false;
		}

		$subject = html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8') . ' - ответ на ваш вопрос';

		$message  = '<html><body style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5;color:#222;">';
        $message .= '<p>Здравствуйте!</p>';
        $message .= '<p>На ваш вопрос по товару был опубликован ответ.</p>';
        $message .= '<p><strong>Товар:</strong> ' . $product_name . '</p>';
        $message .= '<p><strong>Текст вопроса:</strong><br>' . $feedback_text . '</p>';
        $message .= '<p><strong>Ответ магазина:</strong><br>' . $reply . '</p>';
        $message .= '<p>С уважением,<br>' . htmlspecialchars($this->config->get('config_name'), ENT_QUOTES, 'UTF-8') . '</p>';
        $message .= '</body></html>';

		return $this->sendMail($to, $subject, $message);
	}

	private function sendMail($to, $subject, $message) {
		try {
			$mail = new Mail($this->config->get('config_mail_engine'));
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($to);
			$mail->setFrom($this->config->get('config_email'));
			$mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
			$mail->setSubject($subject);
			$mail->setText(trim(html_entity_decode(strip_tags(str_replace(array('<br>', '<br/>', '<br />', '</p>'), array("\n", "\n", "\n", "</p>\n"), $message)), ENT_QUOTES, 'UTF-8')));
			$mail->setHtml($message);
			$mail->send();

			return true;
		} catch (\Exception $e) {
			return false;
		} catch (\Throwable $e) {
			return false;
		}
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/bm_feedback_admin')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}