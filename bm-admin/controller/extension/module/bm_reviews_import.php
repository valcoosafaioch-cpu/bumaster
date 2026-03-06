<?php
class ControllerExtensionModuleBmReviewsImport extends Controller {
  private $error = [];

  public function index() {
    $this->load->language('extension/module/bm_reviews_import');

    $this->document->setTitle($this->language->get('heading_title'));

    $this->load->model('setting/setting');
    $this->load->model('extension/module/bm_reviews_import');

    if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validate()) {
      $options = [
        'create_admin_reply' => !empty($this->request->post['create_admin_reply']) ? 1 : 0,
        'skip_duplicates'    => !empty($this->request->post['skip_duplicates']) ? 1 : 0,
      ];

      if (!isset($this->request->files['csv']) || empty($this->request->files['csv']['tmp_name'])) {
        $this->error['warning'] = $this->language->get('error_file');
      } else {
        $tmp = $this->request->files['csv']['tmp_name'];
        $name = $this->request->files['csv']['name'];

        $result = $this->model_extension_module_bm_reviews_import->importCsv($tmp, $options);

        $this->session->data['bm_reviews_import_result'] = $result;
        $this->session->data['success'] = sprintf($this->language->get('text_success'), $name);

        $this->response->redirect($this->url->link(
          'extension/module/bm_reviews_import',
          'user_token=' . $this->session->data['user_token'],
          true
        ));
      }
    }

    // Breadcrumbs
    $data['breadcrumbs'] = [];

    $data['breadcrumbs'][] = [
      'text' => $this->language->get('text_home'),
      'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
    ];

    $data['breadcrumbs'][] = [
      'text' => $this->language->get('text_extension'),
      'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
    ];

    $data['breadcrumbs'][] = [
      'text' => $this->language->get('heading_title'),
      'href' => $this->url->link('extension/module/bm_reviews_import', 'user_token=' . $this->session->data['user_token'], true)
    ];

    $data['action'] = $this->url->link('extension/module/bm_reviews_import', 'user_token=' . $this->session->data['user_token'], true);
    $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

    // Defaults for options
    $data['create_admin_reply'] = isset($this->request->post['create_admin_reply']) ? (int)$this->request->post['create_admin_reply'] : 1;
    $data['skip_duplicates']    = isset($this->request->post['skip_duplicates']) ? (int)$this->request->post['skip_duplicates'] : 1;

    // Errors
    if (!empty($this->error['warning'])) {
      $data['error_warning'] = $this->error['warning'];
    } elseif (!empty($this->session->data['error_warning'])) {
      $data['error_warning'] = $this->session->data['error_warning'];
      unset($this->session->data['error_warning']);
    } else {
      $data['error_warning'] = '';
    }

    // Success
    if (!empty($this->session->data['success'])) {
      $data['success'] = $this->session->data['success'];
      unset($this->session->data['success']);
    } else {
      $data['success'] = '';
    }

    // Result report
    if (!empty($this->session->data['bm_reviews_import_result'])) {
      $data['result'] = $this->session->data['bm_reviews_import_result'];
      unset($this->session->data['bm_reviews_import_result']);
    } else {
      $data['result'] = null;
    }

    $data['user_token'] = $this->session->data['user_token'];

    $data['header'] = $this->load->controller('common/header');
    $data['column_left'] = $this->load->controller('common/column_left');
    $data['footer'] = $this->load->controller('common/footer');

    $this->response->setOutput($this->load->view('extension/module/bm_reviews_import', $data));
  }

  public function install() {
    $this->load->model('user/user_group');
    $this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/bm_reviews_import');
    $this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/bm_reviews_import');
  }

  public function uninstall() {}

  protected function validate() {
    if (!$this->user->hasPermission('modify', 'extension/module/bm_reviews_import')) {
      $this->error['warning'] = $this->language->get('error_permission');
    }

    return !$this->error;
  }
}
