<?php

if (!function_exists('curl_init')) {
	throw new Exception('Curl extension is required');
}

class ReforumApiException extends Exception
{
	//
}

/**
 * Библиотека для работы с API reforum.ru
 */
class ReforumSDK {

	/**
	 * SDK версия
	 */
	const VERSION = '1.0';

	/**
	 * Базовый URL для API запросов
	 */
	protected $apiBaseUrl;

	/**
	 * Идентификатор
	 */
	protected $id;
	/**
	 * Секретный ключ для подписи запроса
	 */
	protected $secretKey;
	/**
	 * IP-адрес клиента для подписи
	 */
	protected $clientIP;

	/**
	 * Идентификатор города
	 */
	protected $cityId = '7700000000000000000000000';

	protected $connectionTimeout = 1;
	protected $timeout = 8;
	protected $userAgent = 'Reforum PHP SDK';

	protected $replyOutput = false;


	protected $actions = array();
	protected $data = array();

	/**
	 * Инициализация
	 *
	 * В качестве параметра $config необходимо передать массив
	 * с конфигурацией. В качестве параметров массив должен содержать:
	 *
	 * id integer - идентификатор партнёра
	 * secretKey string - секретный ключ для подписи запросов
	 * clientIP string - IP адрес с которого будут посланы запросы, необходим для подписи
	 *
	 * Дополнительные параметры:
	 *
	 * apiBaseUrl string - Базовый URL для API запросов
	 * cityId string - идентификатор города:
	 * - '7700000000000000000000000' - для Москвы
	 * - '5400000100000000000000000' - для Новосибирска
	 * - '2300000700000000000000000' - для Сочи
	 * - '5500000100000000000000000' - для Омска
	 * - '3800000300000000000000000' - для Иркутска
	 * replyOutput boolean - при выполнении функции execute печатает ответ от сервера и завершает работу скрипта
	 * connectionTimeout integer - ограничение времени на подключение к удалённому серверу
	 * timeout integer - ограничение времени на получение ответа от сервера
	 *
	 * @param array $options Конфигурационный массив
	 */
	public function  __construct(array $options)
	{
		$this->id = $this->getOption('id', $options, true);
		$this->secretKey = $this->getOption('secretKey', $options, true);
		$this->clientIP = $this->getOption('clientIP', $options, true);

		$this->apiBaseUrl = $this->getOption('apiBaseUrl', $options, false, $this->apiBaseUrl);

		$this->replyOutput = $this->getOption('replyOutput', $options, false, $this->replyOutput);
		$this->cityId = $this->getOption('cityId', $options, false, $this->cityId);

		$this->connectionTimeout = $this->getOption('connectionTimeout', $options, false, $this->connectionTimeout);
		$this->timeout = $this->getOption('timeout', $options, false, $this->timeout);
	}

	/**
	 * Добавить действие в запрос
	 *
	 * Добавляет действие в пакет запроса. Возможные действия:
	 * - sections: возвращает список доступных разделов
     * - formSearch: возвращает форму поиска
     * - search: выполняет поиск, и возвращает найденные данные
     * - advert: возвращает данные объявления по id
     * - banners: отдаёт рекламу
	 *
	 * @param string $actionName
	 * @param array $params
	 * @return boolean
	 */
	public function addAction($actionName, array $params = array())
	{
		$this->actions[$actionName] = $params;
		return true;
	}

	/**
	 * Выполняет все действия и возвращает массив с результатом
	 *
	 * @return array
	 */
	public function execute()
	{
		$params = array();
		$params['id'] = $this->id;
		$params['actions'] = $this->getActions();
		$params['cityId'] = $this->cityId;
		$params['sig'] = $this->getSignature($params);

		$url = $this->apiBaseUrl . '?' . http_build_query($params, null, '&');

		// формируем POST данных
		$data = array();
		foreach ($this->actions AS $act => $param) {
			$data[$act] = json_encode($param);
		}

		// запрос
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectionTimeout);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		$result = curl_exec($ch);

		if($this->replyOutput) {
			echo $result; die; // для отладки
		}

		$e = null;
		if (curl_errno($ch) > 0) {
			$e = new ReforumApiException(curl_error($ch), curl_errno($ch));
		} else {
			$data = (array)json_decode($result, true);
			if (isset($data['error'])) {
				$e = new ReforumApiException($data['error']);
			}
		}

		curl_close($ch);
		if ($e) {
			throw $e;
		}

		$this->data = $data;
		return $this->data;
	}

	protected function getOption($key, array $options, $required = false, $default = null)
	{
		if (isset($options[$key])) {
			return $options[$key];
		}

		if($required) {
			throw new Exception("Do not specify a required parameter '$key'", 101);
		}

		return $default;
	}

	protected function getActions()
	{
		return implode(',',array_keys($this->actions));
	}

	protected function getSignature(array $params)
	{
		ksort($params, SORT_STRING);

		$str = '';
		foreach ($params as $key => $value) {
			$str .= sprintf('%s=%s', $key, $value);
		}
		$str .= $this->secretKey . $this->clientIP;

		return md5($str);
	}

	/**
	 *
	 * @param string $action распечатать вызов определённого метода
	 */
	public function print_data_debug($action = null)
	{
		echo '<br /><pre>';
		if($action) {
			if(isset($this->data[$action])) {
				var_dump($this->data[$action]);
			}
		} else {
			var_dump($this->data);
		}
		echo '</pre>';
	}
}


?>
