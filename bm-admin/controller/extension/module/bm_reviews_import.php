<?php
class ControllerExtensionModuleBmReviewsImport extends Controller {
  private $error = [];

  public function index() {
    $this->load->language('extension/module/bm_reviews_import');


    $this->document->setTitle($this->language->get('heading_title'));


    $this->load->model('setting/setting');
    $this->load->model('extension/module/bm_reviews_import');

    if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validate()) {
      $csv_file = isset($this->request->files['csv']) ? $this->request->files['csv'] : [];
      $image_files = isset($this->request->files['images']) ? $this->request->files['images'] : [];

      $options = [
        'update_duplicates' => !empty($this->request->post['update_duplicates']) ? 1 : 0
      ];

      $csv_validation_error = $this->validateCsvFile($csv_file);
      $images_validation_error = $this->validateImageFiles($image_files);

      if ($csv_validation_error) {
        $this->error['warning'] = $csv_validation_error;
      } elseif ($images_validation_error) {
        $this->error['warning'] = $images_validation_error;
      } else {
        $tmp_path = $csv_file['tmp_name'];
        $csv_name = isset($csv_file['name']) ? $csv_file['name'] : 'reviews.csv';

        $result = $this->model_extension_module_bm_reviews_import->importCsv($tmp_path, $image_files, $options);

        $this->session->data['bm_reviews_import_result'] = $result;

        if (!empty($result['errors'])) {
          $this->session->data['error_warning'] = $this->language->get('text_import_with_errors');
        } else {
          $this->session->data['success'] = sprintf($this->language->get('text_success'), $csv_name);
        }

        $this->response->redirect($this->url->link(
          'extension/module/bm_reviews_import',
          'user_token=' . $this->session->data['user_token'],
          true
        ));
      }
    }

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

    $data['action'] = $this->url->link(
      'extension/module/bm_reviews_import',
      'user_token=' . $this->session->data['user_token'],
      true
    );

    $data['heading_title'] = $this->language->get('heading_title');

    $data['text_import'] = $this->language->get('text_import');
    $data['text_result'] = $this->language->get('text_result');
    $data['text_total_rows'] = $this->language->get('text_total_rows');
    $data['text_errors'] = $this->language->get('text_errors');
    $data['text_warnings'] = $this->language->get('text_warnings');
    $data['text_errors_count'] = $this->language->get('text_errors_count');
    $data['text_inserted_reviews'] = $this->language->get('text_inserted_reviews');
    $data['text_updated_reviews'] = $this->language->get('text_updated_reviews');
    $data['text_skipped_duplicates'] = $this->language->get('text_skipped_duplicates');
    $data['text_total_images'] = $this->language->get('text_total_images');
    $data['text_inserted_images'] = $this->language->get('text_inserted_images');
    $data['text_deleted_images'] = $this->language->get('text_deleted_images');
    $data['text_skipped_images'] = $this->language->get('text_skipped_images');

    $data['entry_file'] = $this->language->get('entry_file');
    $data['entry_images'] = $this->language->get('entry_images');
    $data['entry_options'] = $this->language->get('entry_options');
    $data['entry_update_duplicates'] = $this->language->get('entry_update_duplicates');

    $data['help_csv'] = $this->language->get('help_csv');
    $data['help_images'] = $this->language->get('help_images');
    $data['help_update_duplicates'] = $this->language->get('help_update_duplicates');

    $data['column_row'] = $this->language->get('column_row');
    $data['column_message'] = $this->language->get('column_message');

    $data['button_import'] = $this->language->get('button_import');
    $data['button_cancel'] = $this->language->get('button_cancel');

    $data['cancel'] = $this->url->link(
      'marketplace/extension',
      'user_token=' . $this->session->data['user_token'] . '&type=module',
      true
    );

    $data['update_duplicates'] = isset($this->request->post['update_duplicates'])
      ? (int)$this->request->post['update_duplicates']
      : 0;

    if (!empty($this->error['warning'])) {
      $data['error_warning'] = $this->error['warning'];
    } elseif (!empty($this->session->data['error_warning'])) {
      $data['error_warning'] = $this->session->data['error_warning'];
      unset($this->session->data['error_warning']);
    } else {
      $data['error_warning'] = '';
    }

    if (!empty($this->session->data['success'])) {
      $data['success'] = $this->session->data['success'];
      unset($this->session->data['success']);
    } else {
      $data['success'] = '';
    }

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

    $this->model_user_user_group->addPermission(
      $this->user->getGroupId(),
      'access',
      'extension/module/bm_reviews_import'
    );

    $this->model_user_user_group->addPermission(
      $this->user->getGroupId(),
      'modify',
      'extension/module/bm_reviews_import'
    );
  }

  public function uninstall() {}

  protected function validate() {
    if (!$this->user->hasPermission('modify', 'extension/module/bm_reviews_import')) {
      $this->error['warning'] = $this->language->get('error_permission');
    }

    return !$this->error;
  }

  private function validateCsvFile($file) {
    if (!$file || !isset($file['tmp_name']) || empty($file['tmp_name'])) {
      return $this->language->get('error_csv_required');
    }

    if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
      return $this->language->get('error_csv_upload');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
      return $this->language->get('error_csv_tmp');
    }

    $extension = strtolower(pathinfo(isset($file['name']) ? $file['name'] : '', PATHINFO_EXTENSION));

    if ($extension !== 'csv') {
      return $this->language->get('error_csv_extension');
    }

    return '';
  }

  private function validateImageFiles($files) {
    $normalized = $this->normalizeUploadedFiles($files);

    if (!$normalized) {
      return '';
    }

    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

    foreach ($normalized as $file) {
      if (empty($file['name']) && (!isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE)) {
        continue;
      }

      if (!isset($file['error']) || !in_array((int)$file['error'], [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], true)) {
        return $this->language->get('error_image_upload');
      }

      if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        continue;
      }

      $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

      if (!in_array($extension, $allowed_extensions, true)) {
        return $this->language->get('error_image_extension');
      }
    }

    return '';
  }

  private function normalizeUploadedFiles($files) {
    if (!$files) {
      return [];
    }

    if (isset($files['name']) && is_array($files['name'])) {
      $normalized = [];
      $count = count($files['name']);

      for ($i = 0; $i < $count; $i++) {
        $normalized[] = [
          'name'     => isset($files['name'][$i]) ? $files['name'][$i] : '',
          'type'     => isset($files['type'][$i]) ? $files['type'][$i] : '',
          'tmp_name' => isset($files['tmp_name'][$i]) ? $files['tmp_name'][$i] : '',
          'error'    => isset($files['error'][$i]) ? $files['error'][$i] : UPLOAD_ERR_NO_FILE,
          'size'     => isset($files['size'][$i]) ? $files['size'][$i] : 0
        ];
      }

      return $normalized;
    }

    if (isset($files['name'])) {
      return [$files];
    }

    if (isset($files[0]) && is_array($files[0])) {
      return $files;
    }

    return [];
  }
}