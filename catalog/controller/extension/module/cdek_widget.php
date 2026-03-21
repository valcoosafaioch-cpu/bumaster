<?php
class ControllerExtensionModuleCdekWidget extends Controller {
	private $login = '';
	private $secret = '';
	private $baseUrl = 'https://api.cdek.ru/v2';
	private $authToken = '';
	private $requestData = array();
	private $metrics = array();
    private const TOKEN_CACHE_KEY = 'cdek_widget_token';
	private const TOKEN_TTL = 3500;
    private const OFFICES_CACHE_KEY_PREFIX = 'cdek_widget_offices_';
    private const OFFICES_TTL = 600;

	public function service(): void {
		$this->login = (string)$this->config->get('cdek_widget_account');
		$this->secret = (string)$this->config->get('cdek_widget_secure_password');

        if (function_exists('session_status')) {
			if (session_status() === PHP_SESSION_ACTIVE) {
				session_write_close();
			}
		} elseif (session_id()) {
			session_write_close();
		}

		if (!(bool)$this->config->get('cdek_widget_enabled')) {
			$this->sendValidationError('CDEK widget is disabled');
		}

		if ($this->login === '' || $this->secret === '') {
			$this->sendValidationError('CDEK widget credentials are not configured');
		}

		if (!function_exists('curl_init')) {
			$this->sendServerError('cURL extension is not available');
		}

		try {
			$this->process($this->request->get, file_get_contents('php://input'));
		} catch (\Throwable $e) {
			$this->sendServerError($e->getMessage());
		}
	}

	private function process(array $requestData, string $body): void {
		$time = $this->startMetrics();

		$bodyData = json_decode($body, true);
		if (!is_array($bodyData)) {
			$bodyData = array();
		}

		$this->requestData = array_merge($requestData, $bodyData);

		if (!isset($this->requestData['action']) || $this->requestData['action'] === '') {
			$this->sendValidationError('Action is required');
		}

		$this->getAuthToken();

		switch ($this->requestData['action']) {
			case 'offices':
				$this->sendResponse($this->getOffices(), $time);
				break;

			case 'calculate':
				$this->sendResponse($this->calculate(), $time);
				break;

			default:
				$this->sendValidationError('Unknown action');
		}
	}

	private function getAuthToken(): void {
		$cachedToken = $this->cache->get(self::TOKEN_CACHE_KEY);

		if (is_string($cachedToken) && $cachedToken !== '') {
			$this->authToken = $cachedToken;

			return;
		}

		$time = $this->startMetrics();

		$token = $this->httpRequest(
			'oauth/token',
			array(
				'grant_type' => 'client_credentials',
				'client_id' => $this->login,
				'client_secret' => $this->secret
			),
			true
		);

		$this->endMetrics('auth', 'Server Auth Time', $time);

		$result = json_decode($token['result'], true);

		if (!is_array($result) || empty($result['access_token'])) {
			throw new RuntimeException('Server not authorized to CDEK API');
		}

		$this->authToken = (string)$result['access_token'];
		$this->cache->set(self::TOKEN_CACHE_KEY, $this->authToken, self::TOKEN_TTL);
	}

	private function getOffices(): array {
        $time = $this->startMetrics();

        $cacheData = $this->requestData;
        unset($cacheData['action']);

        ksort($cacheData);

        $cacheKey = self::OFFICES_CACHE_KEY_PREFIX . md5(json_encode($cacheData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $cachedResult = $this->cache->get($cacheKey);

        if (is_array($cachedResult) && isset($cachedResult['result'])) {
            $this->endMetrics('office_cache', 'Offices Cache Hit', $time);

            return $cachedResult;
        }

        $result = $this->httpRequest('deliverypoints', $this->requestData);

        $this->cache->set($cacheKey, $result, self::OFFICES_TTL);

        $this->endMetrics('office', 'Offices Request', $time);

        return $result;
    }

	private function calculate(): array {
		$time = $this->startMetrics();

		$result = $this->httpRequest('calculator/tarifflist', $this->requestData, false, true);

		$this->endMetrics('calc', 'Calculate Request', $time);

		return $result;
	}

	private function httpRequest(string $method, array $data, bool $useFormData = false, bool $useJson = false): array {
		$ch = curl_init($this->baseUrl . '/' . $method);

		$headers = array(
			'Accept: application/json',
			'X-App-Name: widget_pvz',
			'X-App-Version: 3.11.1'
		);

		if ($this->authToken) {
			$headers[] = 'Authorization: Bearer ' . $this->authToken;
		}

		if ($useFormData) {
			curl_setopt_array($ch, array(
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $data
			));
		} elseif ($useJson) {
			$headers[] = 'Content-Type: application/json';

			curl_setopt_array($ch, array(
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
			));
		} else {
			curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/' . $method . '?' . http_build_query($data));
		}

		curl_setopt_array($ch, array(
            CURLOPT_USERAGENT => 'widget/3.11.1',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30
        ));

		$response = curl_exec($ch);

		if ($response === false) {
			$error = curl_error($ch);
			$code = curl_errno($ch);
			curl_close($ch);

			throw new RuntimeException($error, $code);
		}

		$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$responseHeaders = substr($response, 0, $headerSize);
		$result = substr($response, $headerSize);

		curl_close($ch);

		if ($httpCode === 401 && $this->authToken !== '') {
			$this->cache->delete(self::TOKEN_CACHE_KEY);
			$this->authToken = '';
		}

		return array(
			'result' => $result,
			'addedHeaders' => $this->getHeaderValue($responseHeaders)
		);
	}

	private function getHeaderValue(string $headers): array {
		$headerLines = explode("\r\n", $headers);

		return array_values(array_filter($headerLines, static function ($line) {
			return $line !== '' && stripos($line, 'X-') !== false;
		}));
	}

	private function sendResponse(array $data, $start): void {
		$this->setHttpResponseCode(200);
		$this->response->addHeader('Content-Type: application/json');
		$this->response->addHeader('X-Service-Version: 3.11.1');

		if (!empty($data['addedHeaders'])) {
			foreach ($data['addedHeaders'] as $header) {
				$this->response->addHeader($header);
			}
		}

		$this->endMetrics('total', 'Total Time', $start);

		if (!empty($this->metrics)) {
			$serverTiming = array();

			foreach ($this->metrics as $metric) {
				$serverTiming[] = $metric['name'] . ';desc="' . $metric['description'] . '";dur=' . $metric['time'];
			}

			$this->response->addHeader('Server-Timing: ' . implode(',', $serverTiming));
		}

		$this->response->setOutput($data['result']);
	}

	private function sendValidationError(string $message): void {
		$this->setHttpResponseCode(400);
		$this->response->addHeader('Content-Type: application/json');
		$this->response->addHeader('X-Service-Version: 3.11.1');
		$this->response->setOutput(json_encode(array('message' => $message), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	private function sendServerError(string $message): void {
		$this->setHttpResponseCode(500);
		$this->response->addHeader('Content-Type: application/json');
		$this->response->addHeader('X-Service-Version: 3.11.1');
		$this->response->setOutput(json_encode(array('message' => $message), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	private function startMetrics() {
		return function_exists('hrtime') ? hrtime(true) : microtime(true);
	}

	private function endMetrics(string $metricName, string $metricDescription, $start): void {
		$duration = function_exists('hrtime')
			? (hrtime(true) - $start) / 1e+6
			: (microtime(true) - $start) * 1000;

		$this->metrics[] = array(
			'name' => $metricName,
			'description' => $metricDescription,
			'time' => round($duration, 2)
		);
	}

	private function setHttpResponseCode(int $code): void {
		$text = 'OK';

		switch ($code) {
			case 400:
				$text = 'Bad Request';
				break;
			case 500:
				$text = 'Internal Server Error';
				break;
			default:
				$text = 'OK';
				break;
		}

		$protocol = !empty($this->request->server['SERVER_PROTOCOL'])
			? $this->request->server['SERVER_PROTOCOL']
			: 'HTTP/1.1';

		$this->response->addHeader($protocol . ' ' . $code . ' ' . $text);
	}
}