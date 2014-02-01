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
class ReforumSDK 
{
	/**
	 * SDK версия
	 */
	const VERSION = '2.6';

	// методы
	const ACT_SECTIONS = 'sections';
	const ACT_REGIONS = 'regions';
	const ACT_GEO = 'geo';
	const ACT_FORMSEARCH = 'formSearch';
	const ACT_FORMSEARCH_PART = 'formSearchPart';
	const ACT_SPEC = 'spec';
	const ACT_ADS = 'ads';
	const ACT_ADS_AVAILABLE = 'adsAvailable';
	const ACT_ADVERT = 'advert';
	const ACT_ADVERT_PROPS = 'advertProps';
	const ACT_SIMILAR = 'similar';
	const ACT_SEARCH = 'search';
	const ACT_GEOCODER = 'geocoder';
	const ACT_NEWS = 'news';
	const ACT_ARTICLES = 'article';
	const ACT_FAQ = 'faq';
	const ACT_INFOGRAPHIC = 'infographic';
	const ACT_ADVERT_OF_DAY = 'advert_of_day';
	const ACT_COMPANY_LEADERS = 'company_leaders';
	const ACT_UPLOAD_ADVERT_FOTO = 'uploadAdvertFoto';

	static protected $requestTypeGet = 'GET';
	static protected $requestTypePost = CURLOPT_POST;
	static protected $requestTypePut = CURLOPT_PUT;
	static protected $requestTypeDelete = 'DELETE';

	/**
	 * Базовый URL для API запросов
	 */
	protected $apiBaseUrl = 'http://service.reforum.ru/api.html';
	protected $apiDomain = '';

	/**
	 * Идентификатор партнера
	 */
	protected $partnerId;
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
		$this->partnerId = $this->getOption('id', $options, true);
		$this->secretKey = $this->getOption('secretKey', $options, true);

		$this->apiBaseUrl = $this->getOption('apiBaseUrl', $options, false, $this->apiBaseUrl);
		$parseUrl = parse_url($this->apiBaseUrl);
		$this->apiDomain = $parseUrl['scheme'] . '://' . $parseUrl['host'];

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
		$url = $this->apiBaseUrl . $this->_getUrlParams();

		// формируем POST данных
		$data = array();
		foreach ($this->actions AS $act => $param) {
			$data[$act] = json_encode($param);
		}

		return $this->data = $this->_execRequest($url, self::$requestTypePost, $data);
	}

	/**
	 * геокодирование
	 * @param $params
	 * @return array
	 */
	public function getEncodeGeo($params)
	{
		return $this->_requestList(self::ACT_GEOCODER, self::$requestTypeGet, $params);
	}

	/**
	 * похожие объявления
	 * @param $params
	 * @return array
	 */
	public function getSimilarAdverts($params)
	{
		return $this->_requestList(self::ACT_SIMILAR, self::$requestTypeGet, $params);
	}

	/**
	 * получить список объявлений
	 * @param $params
	 * @return array
	 */
	public function getAdverts($params)
	{
		return $this->_requestList(self::ACT_ADVERT, self::$requestTypeGet, $params);
	}

	/**
	 * получить список объявлений
	 * @param $params
	 * @return array
	 */
	public function getAdvert($params)
	{
		return $this->_requestView(self::ACT_ADVERT, self::$requestTypeGet, $params);
	}

	/**
	 * получить список объявлений
	 * @param $params
	 * @return array
	 */
	public function getAdvertProps($params)
	{
		$params = array(self::ACT_ADVERT => $params);
		$url = $this->apiDomain . '/' . self::ACT_ADVERT . '/' . self::ACT_ADVERT_PROPS . '/' . $this->_getUrlParams($params);
		return $this->_execRequest($url, self::$requestTypeGet);
	}

	/**
	 * @param bool $remember запомнить
	 */
	public function login($username, $password, $rememberMe=false)
	{
		$params = array('username' => $username, 'password' => $password, 'rememberMe' => $rememberMe);
		$url = $this->apiDomain . '/login/' . $this->_getUrlParams(array('login' => $params));
		$resp = $this->_execRequest($url, self::$requestTypeGet);
		return isset($resp['userAccess']) ? $resp['userAccess'] : null;
	}

	public function leadSendMortgage($params)
	{
		$url = $this->apiDomain . '/api/lead/saveMortgage/' . $this->_getUrlParams(array('lead' => $params));
		$resp = $this->_execRequest($url, self::$requestTypePost);
		return isset($resp['lead']) ? $resp['lead'] : null;
	}

	public function leadSendQuery($params)
	{
		$url = $this->apiDomain . '/api/lead/saveQuery/' . $this->_getUrlParams(array('lead' => $params));
		$resp = $this->_execRequest($url, self::$requestTypePost);
		return isset($resp['lead']) ? $resp['lead'] : null;
	}

	public function getData($action = '')
	{
		if ($action && isset($this->data[ $action ])) {
			return $this->data[ $action ];
		}
		return $this->data;
	}

	public function getContentItem($type, $id)
	{
		$url = $this->apiDomain . '/' . $type . '/' . $id .'/' . $this->_getUrlParams(array('id' => $id));
		$resp = $this->_execRequest($url, self::$requestTypeGet);
		return $resp;
	}

	/**
	 * @param $filePath
	 * @return array
	 */
	public function uploadAdvertFoto($filePath)
	{
		$url = $this->apiDomain . '/' . self::ACT_UPLOAD_ADVERT_FOTO . '/';
		return $this->_putFile($url, $filePath);
	}

	protected function _requestView($act, $requestType, $params)
	{
		$url = $this->apiDomain . '/' . $act . '/' . $params['id'] . '/';
		$params = array($act => $params);
		$url .= $this->_getUrlParams($params);
		return $this->_execRequest($url, $requestType);
	}

	protected function _requestList($act, $requestType, $params)
	{
		$params = array($act => $params);
		$url = $this->apiDomain . '/' . $act . '/' . $this->_getUrlParams($params);
		return $this->_execRequest($url, $requestType);
	}

	/**
	 * Закачка файла
	 *
	 * @param $url
	 * @param $filePath
	 * @return array
	 * @throws ReforumApiException
	 */
	protected function _putFile($url, $filePath)
	{
		if (!file_exists($filePath)) {
			throw new ReforumApiException();
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectionTimeout);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);

		$fp = fopen($filePath, 'r');
		curl_setopt($ch, CURLOPT_INFILE, $fp);
		curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filePath) );

		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_UPLOAD, true);

		$result = curl_exec($ch);

		if (curl_errno($ch) > 0) {
			$e = new ReforumApiException(curl_error($ch), curl_errno($ch));
		} else {
			$data = (array)json_decode($result, true);
			if (isset($data['error'])) {
				$e = new ReforumApiException();
			}
		}

		curl_close($ch);


		return $data;
	}

	protected function _execRequest($url, $requestType, array $data=array())
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectionTimeout);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
		if ($requestType == self::$requestTypePut) {
			curl_setopt($ch, $requestType, 1);
		}
		if ($requestType == self::$requestTypePost) {
			curl_setopt($ch, $requestType, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
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
				$e = new ReforumApiException();
			}
		}

		curl_close($ch);
		if ($e) {
			throw $e;
		}

		return $data;
	}

	protected function _getUrlParams($params = array())
	{
		$params['partnerId'] = $this->partnerId;
		$params['actions'] = $this->getActions();
		$params['regionId'] = $this->regionId;
		$params['geoId'] = $this->geoId;
		$params['sig'] = $this->getSignature($params);
		if ($this->dbg) {
			$params['XDEBUG_SESSION_START'] = 'DBG';
			$params['debug'] = 1;
		}
		return '?' . http_build_query($params, null, '&');
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
			if (!is_array($action)) {
				$action = array($action);
			}
			foreach ($action as $id) {
				if(isset($this->data[$id])) {
					var_dump($this->data[$id]);
				}
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