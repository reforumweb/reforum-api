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
	const ACT_MORTGAGE            = 'mortgage';
	const ACT_ANALYTICS           = 'analytics';
	const ACT_REPAIR              = 'repair';
	const ACT_INFOGRAPHIC = 'infographic';
	const ACT_ADVERT_OF_DAY = 'advert_of_day';
	const ACT_COMPANY_LEADERS = 'company_leaders';
	const ACT_ADVERTISING = 'advertising';
	const ACT_PARTNERS = 'partners';
	const ACT_UPLOAD_ADVERT_FOTO = 'uploadAdvertFoto';
	const ACT_ADVERT_FORM = 'advert_form';
	const ACT_CONTEXT_TGB = 'context_tgb';
	const ACT_CATALOG_COTTAGE = 'catalogCottage';
	const ACT_CATALOG_NEWBUILDING = 'catalogNewbuilding';
	const ACT_ADVERT_COUNTER = 'advert_counter';

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
		$this->geoId = $this->getOption('geoId', $options, false, $this->geoId);

		$this->connectionTimeout = $this->getOption('connectionTimeout', $options, false, $this->connectionTimeout);
		$this->timeout = $this->getOption('timeout', $options, false, $this->timeout);
	}

	/**
	 * Добавить действие в запрос
	 *
	 * Добавляет действие в пакет запроса. Возможные действия:
	 * - sections: возвращает список доступных разделов
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
		return $this;
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
		$params['geoId'] = $this->geoId;
		$params['sig'] = $this->getSignature($params);
		if ($this->dbg) {
			$params['XDEBUG_SESSION_START'] = 'DBG';
			$params['debug'] = 1;
		}
		return '?' . http_build_query($params, null, '&');
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
			$str .= $key . '=' . $value;
		}
		$str .= $this->secretKey;

		return md5($str);
	}

}
