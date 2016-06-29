<?
namespace API;
/**
  * API для мобильного приложения
  *
  * API для взаимодействия с мобильным приложением
  *
  * @api AppAPI
  * @category API
  * @package API
  * @version 1.0.0
  * @author Sergey Yastrebov <info@crazysquirrel.ru>
  * @copyright CrazySquirrel 2016
  */
  
/**
  * AppAPI класс API
  *
  * AppAPI класс для обработки API мобильного приложения
  *
  * @uses AppAPI::Init(JSON);
  */
class AppAPI extends Config{
	/**
	 * Метод инициализации API.
	 *
	 * Метод получает сериализованный массив параметров в формате JSON
	 * делает проверки и вызывает функцию обработчик конкретного запрашиваемого метода.
	 *
	 * @param array $arData (required) JSON массив с параметрами
	 *		{
	 * 			"APIKEY":"",
	 * 			"Class":"AppAPI",
	 * 			"Method":"",
	 * 			"Parameters":{...}
	 *		}
	 * @return array $result Массив с результатом работы функции или описанием ошибки
     */
	public static function Init($arData){
		if(!empty($arData)){
			if($arData = json_decode($arData,true)){
				if($arData["APIKEY"] == self::$APIKEY){
					if(method_exists(__NAMESPACE__."\\".$arData["Class"],$arData["Method"])){
						$arData["Parameters"] = array_merge($arData["Parameters"],$_FILES);
						/**
						 * Получаем список параметров принимаемых запрашиваемой функцией
						*/
						$arReflection = new \ReflectionMethod(__NAMESPACE__."\\".$arData["Class"],$arData["Method"]);
						$arParams = $arReflection->getParameters();
						$allParams = array();
						$requiredParams = array();
						$optionalParamsValue = array();
						foreach($arParams as $objParam) {
							$allParams[] = $objParam->getName();
							if($objParam->isOptional()){
								$optionalParamsValue[$objParam->getName()] = $objParam->getDefaultValue();
							}else{
								$requiredParams[] = $objParam->getName();					
							}
						}
						/**
						 * Проверяем, что в пришедших данных есть все указанные параметры
						 */
						if(count(array_diff($requiredParams,array_keys($arData["Parameters"]))) == 0){
							/**
							 * Формируем список параметров в том порядке в каком их ожидает метод
							 */
							$arParams = array();
							foreach($allParams as $param){
								$arParams[$param] = (isset($arData["Parameters"][$param])?$arData["Parameters"][$param]:$optionalParamsValue[$param]);
							}
							/**
							 * Глобальное кеширование API
							 */
							$cachePath = str_replace("\\","/","/".__NAMESPACE__."/".str_replace(__NAMESPACE__."\\","",$arData["Class"])."/".$arData["Method"]."/");
							$cacheID = sha1(serialize($arParams));
							$obCache = new \CPHPCache();

							if($arData["Cache"] != "N" && $obCache->InitCache(3600000,$cacheID,$cachePath)) {
								$result = $obCache->GetVars();
							}elseif($obCache->StartDataCache()){
								/**
                                 * Вызываем функцию с заданными параметрами
                                 */
								$result = call_user_func_array(array(__NAMESPACE__ . "\\" . $arData["Class"], $arData["Method"]), $arParams);
								$result = array(
									"APIKEY" => $arData["APIKEY"],
									"Class" => $arData["Class"],
									"Method" => $arData["Method"],
									"Response" => $result
								);
								$obCache->EndDataCache($result);
							}
							return $result;
						}else{
							return self::Error("REQUIRED_PAREMETERS_NOT_SENDED","Необходимо передать минимум: ".implode(",",$requiredParams));
						}
					}else{
						return self::Error("METHOD_DOES_NOT_EXIST");
					}
				}else{
					return self::Error("INVALID_API_KEY");
				}
			}else{
				return self::Error("API_REQUEST_NOT_JSON");
			}
		}else{
			return self::Error("API_REQUEST_IS_EMPTY");
		}
	}
	
	/**
	 * Метод обработки ошибок.
	 *
	 * Метод получает код ошибки.
	 *
	 * @param array $ErrorCode (required) код ошибки
	 * @return array $result Массив с детализацией ошибки
     */
	static public function Error(
		$ErrorCode,
		$ErrorMessage = ""
	){
		$result = array(
			"ErrorCode" => $ErrorCode,
			"ErrorMessage" => $ErrorMessage
		);
		return $result;
	}

	/**
	 * Метод для отчистки всего кеша для конкретного класса
	 * @param $CLASS_NAME
	 */
	static public function clearClassCache(
		$CLASS_NAME
	){
		$reflection = new \ReflectionClass($CLASS_NAME);
		$arMethods = $reflection->getMethods(\ReflectionMethod::IS_STATIC);
		if(!empty($arMethods)){
			$obCache = new \CPHPCache();
			foreach($arMethods as $arMethod){
				if("\\".$arMethod->class == $CLASS_NAME){
					$path = trim(str_replace("\\","/","/".$arMethod->class."/".$arMethod->name."/"),"/");
					$obCache->CleanDir($path);
				}
			}
		}
	}

	/**
	 * Метод для отчистки всего кеша для метода
	 * @param $CLASS_NAME
	 */
	static public function clearMethodCache(
		$METHOD_NAME = ""
	){
		$METHOD_NAME = str_replace("\\","/",$METHOD_NAME);
		$obCache = new \CPHPCache();
		$obCache->CleanDir($METHOD_NAME);
	}

	/**
	 * Метод для получения информации
	 * @param $PATH
	 * @return mixed
	 */
	static public function getInfo(
		$PATH
	){
		ob_start();
		include(rtrim($_SERVER["DOCUMENT_ROOT"],"/").$PATH);
		$info = ob_get_contents();
		ob_end_clean();
		return $info;
	}

	/**
	 * Метод обработки управляемого кеша для инфоблоков
	 * OnAfterIBlockElementAdd
	 * OnAfterIBlockElementUpdate
	 * OnAfterIBlockElementDelete
	 * @param $arFields
	 */
	static public function clearCacheWhenUpdateIBlock(&$arFields){
		switch($arFields["IBLOCK_ID"]){
			case self::$MobileAPPEvents:
				self::clearClassCache('\API\MobileAPP\Events');
			break;
			case self::$MobileAPPLocations:
				self::clearClassCache('\API\MobileAPP\Locations');
			break;
			case self::$MobileAPPTracks:
				self::clearClassCache('\API\MobileAPP\Tracks');
			break;
			case self::$MobileAPPTeams:
				self::clearClassCache('\API\MobileAPP\Teams');
			break;
			case self::$MobileAPPLeagues:
				self::clearClassCache('\API\MobileAPP\Leagues');
			break;
			case self::$MobileAPPCountry:
				self::clearClassCache('\API\MobileAPP\Country');
			break;
			case self::$MobileAPPLocalities:
				self::clearClassCache('\API\MobileAPP\Localities');
			break;
		}
	}

	/**
	 * Метод обработки управляемого кеша для пользователей
	 * OnAfterIBlockElementAdd
	 * OnAfterIBlockElementUpdate
	 * OnAfterIBlockElementDelete
	 * @param $arFields
	 */
	static public function clearCacheWhenUpdateUser(&$arFields){
		self::clearClassCache('\API\MobileAPP\Users');
	}

	/**
	 * Метод для отчистки всего кеша
	 */
	static public function clearAllCache(){
		$obCache = new \CPHPCache();
		$obCache->CleanDir("API");
	}
}
?>