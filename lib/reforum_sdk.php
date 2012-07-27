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
	const VERSION = '2.0';

	// методы
	const ACT_SECTIONS = 'sections';
	const ACT_REGIONS = 'regions';
	const ACT_GEO = 'geo';
	const ACT_FORMSEARCH = 'formSearch';
	const ACT_SPEC = 'spec';

	/**
	 * Базовый URL для API запросов
	 */
	protected $apiBaseUrl = 'http://service.reforum.ru/api.html';

	/**
	 * Идентификатор
	 */
	protected $id;
	/**
	 * Секретный ключ для подписи запроса
	 */
	protected $secretKey;

	/**
	 * Идентификатор региона
	 * @deprecated
	 */
	protected $regionId = '';

	/**
	 * @var string Идентификатор гео
	 */
	protected $geoId = 'msk';

	protected $connectionTimeout = 1;
	protected $timeout = 8;
	protected $userAgent = 'Reforum PHP SDK 2';

	protected $replyOutput = false;

	protected $dbg = false;

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
	 *
	 * Дополнительные параметры:
	 *
	 * apiBaseUrl string - базовый URL для API запросов
	 * regionId integer - идентификатор региона deprecated
	 * geoId string - идентификатор гео региона
	 * replyOutput boolean - при выполнении функции execute печатает ответ от сервера и завершает работу скрипта
	 * connectionTimeout integer - ограничение времени на подключение к удалённому серверу
	 * timeout integer - ограничение времени на получение ответа от сервера
	 *
	 * @param array $options Конфигурационный массив
	 * @return void
	 */
	public function  __construct(array $options)
	{
		$this->id = $this->getOption('id', $options, true);
		$this->secretKey = $this->getOption('secretKey', $options, true);

		$this->apiBaseUrl = $this->getOption('apiBaseUrl', $options, false, $this->apiBaseUrl);

		$this->replyOutput = $this->getOption('replyOutput', $options, false, $this->replyOutput);
		$this->dbg = $this->getOption('dbg', $options, false, $this->dbg);
		$this->regionId = $this->getOption('regionId', $options, false, $this->regionId);
		$this->geoId = $this->getOption('geoId', $options, false, $this->geoId);

		$this->connectionTimeout = $this->getOption('connectionTimeout', $options, false, $this->connectionTimeout);
		$this->timeout = $this->getOption('timeout', $options, false, $this->timeout);
	}

	/**
	 * Добавить действие в запрос
	 *
	 * Добавляет действие в пакет запроса. Возможные действия:
	 * - sections: возвращает список доступных разделов
	 * - regions: возвращает список регионов
     * - formSearch: возвращает форму поиска
     * - ads: отдаёт рекламу
     * не реализовано:
	 * - search: выполняет поиск, и возвращает найденные данные
     * - advert: возвращает данные объявления по id
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
		$params['regionId'] = $this->regionId;
		$params['geoId'] = $this->geoId;
		$params['sig'] = $this->getSignature($params);
		if ($this->dbg) {
			$params['XDEBUG_SESSION_START'] = 'DBG';
			$params['debug'] = 1;
		}

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
			var_dump($data);
			echo PHP_EOL, PHP_EOL, $result; 
			die; // для отладки
		}

		$e = null;
		if (curl_errno($ch) > 0) {
			$e = new ReforumApiException(curl_error($ch), curl_errno($ch));
		} else {
			$data = (array)json_decode($result, true);
			if (isset($data['error'])) {
				$e = new ReforumApiException($data['error'], $data['errorNo']);
			}
		}

		curl_close($ch);
		if ($e) {
			throw $e;
		}

		$this->data = $data;
		return $this->data;
	}
	
	/**
	 * Печатает вызов определённого метода
	 *
	 * @param string $action имя метода, по умолчанию выводит все
	 * @return void
	 */
	public function printDataDebug($action = null)
	{
		echo '<br><pre>';
		if($action) {
			if(isset($this->data[$action])) {
				var_dump($this->data[$action]);
			}
		} else {
			var_dump($this->data);
		}
		echo '</pre>';
	}

	/**
	 * Извлекает опцию из массива
	 *
	 * @param string $key ключ опции в массиве
	 * @param array $options массив опций
	 * @param boolean $required обязательное присутсвие значения опции в массиве
	 * @param mixed $default значение опции по умолчанию, при отсутствии в массиве
	 * @return mixed
	 */
	protected function getOption($key, array $options, $required = false, $default = null)
	{
		if (isset($options[$key])) {
			return $options[$key];
		}

		if ($required) {
			throw new ReforumApiException("Do not specify a required parameter '$key'", 101);
		}

		return $default;
	}

	/**
	 * Возвращает список добавленных методов
	 *
	 * @return array
	 */
	protected function getActions()
	{
		return implode(',', array_keys($this->actions));
	}

	/**
	 * Генерирует подпись запроса на основе массива параметров
	 *
	 * @param array $params
	 * @return string
	 */
	protected function getSignature(array $params)
	{
		ksort($params, SORT_STRING);

		$str = '';
		foreach ($params as $key => $value) {
			$str .= sprintf('%s=%s', $key, $value);
		}
		$str .= $this->secretKey;

		return md5($str);
	}
	
}


?>

