<?php

/**
 * PHP Router (https://github.com/OverKiller/PHP-Router)
 *
 * @author     Damiano Barbati <damiano.barbati@gmail.com>
 * @copyright  Copyright (c) 2013-2014 Damiano Barbati (http://www.damianobarbati.com)
 * @license    http://www.wtfpl.net/about/ DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
 * @link       https://github.com/OverKiller/PHP-Router for the source repository
 */

/**
 * The router auto detect if it was called
 */
Router::boot();

class Router
{
   /**
    * Error codes
    *
    * @const string
    */
   const ERR_REQUEST = "ERR_REQUEST";
   const ERR_AUTH = "ERR_AUTH";
   const ERR_PARAMS_COUNT = "ERR_PARAMS_COUNT";
   const ERR_PARAMS_MISMATCH = "ERR_PARAMS_MISMATCH";
   const ERR_PARAMS_MISSING = "ERR_PARAMS_MISSING";

   /**
    * Debug flag: if true then built metadata will be outputted
    *
    * @static bool
    */
   static $debug = false;

   /**
    * Unserialize bools flag: if true then "true", "false" and "null" strings will be converted to booleans true, false and null
    *
    * @static bool
    */
   static $unserialize_bools = true;

   /**
    * Allow policy flag: if true then every method can be executed without any auth needed
    *
    * @static bool
    */
   static $allow_policy = false;

   /**
    * Supposed method metadata
    *
    * @var mixed
    */
   public $called_class = false;
   public $called_method = false;
   public $called_params = [];
   public $called_params_names = [];
   public $called_params_count = false;

   /**
    * Real method metadata
    *
    * @var mixed
    */
   public $real_class = false;
   public $real_method = false;
   public $real_params = [];
   public $real_params_names = [];
   public $real_params_count = false;
   public $real_required_params_count = false;

   /**
    * Params passed ordered to be passed to the invoking method
    *
    * @var array
    */
   public $ordered_params = [];

   /**
    * The user defined method for user authentication
    *
    * @static callable
    */
   static $authentication_method = false;

   /**
    * The user defined method for user authorization
    *
    * @static callable
    */
   static $authorization_method = false;

   /**
    * The value returned by the user defined method for user authentication
    *
    * @static mixed
    */
   static $me = false;

   /**
    * The value returned by the user defined method for user authorization
    *
    * @static mixed
    */
   static $authorization = false;

   /**
    * Singleton implementation of the Router: needed to return error within any point in your code
    *
    * @var null
    */
   protected static $instance;

   /**
    * Return the Router instance
    *
    * @return Router
    */
   static function getInstance($clean)
   {
      if(empty(self::$instance))
         self::$instance = new self();
      else
         foreach(self::$instance as $key => $value)
            self::$instance->$key = null;

      return self::$instance;
   }

   protected function __construct()
   {}

   /**
    * Parse the argv to get the called class, the called method and the params
    *
    * @param $string the url passed to the script
    * @return Router
    */
   public static function shellInit($string)
   {
      preg_match("/(.+?)\/(.+?)(\?(.+?$)|$)/im", $string, $matches);

      $called_class = (!empty($matches[1])) ? $matches[1] : null;

      $called_method = (!empty($matches[2])) ? $matches[2] : null;
      if(substr($called_method, -1) == "?")
         $called_method = substr($called_method, 0, -1);

      $called_params = [];
      if(!empty($matches[4]))
         parse_str($matches[4], $called_params);

      $router = self::getInstance(true);
      $router->called_class = $called_class;
      $router->called_method = $called_method;
      $router->called_params = (array)$called_params;
      return $router;
   }

   /**
    * Get the called class, the called method and the params within the REQUEST superglobal and then merge the POST and GET to get the params
    *
    * @return Router
    */
   static function RESTInit()
   {
      $called_class = (isset($_REQUEST["called_class"])) ? $_REQUEST["called_class"] : "";
      $called_method = (isset($_REQUEST["called_method"])) ? $_REQUEST["called_method"] : "";

      $called_params = array_merge($_POST, $_GET);

      foreach(["called_class", "called_method", "_"] as $unset)
         unset($called_params[$unset]);

      $router = self::getInstance(true);
      $router->called_class = $called_class;
      $router->called_method = $called_method;
      $router->called_params = (array)$called_params;
      return $router;
   }

   /**
    * Check whether the calling method really exists excluding the router itself
    *
    * @return bool
    */
   function methodExists()
   {  return $this->called_class == __CLASS__ ? false : method_exists($this->called_class, $this->called_method);  }

   /**
    * Build the metadata needed to further check for authentication, authorization and params validation
    *
    * @return bool
    */
   function buildMetadata()
   {
      $this->called_params_names = array_keys($this->called_params);
      $this->called_params_count = count($this->called_params);

      $this->real_class = new ReflectionClass($this->called_class);
      $this->real_method = new ReflectionMethod($this->called_class, $this->called_method);
      $this->real_params = $this->real_method->getParameters();

      $this->real_params_names = [];
      foreach($this->real_params as $real_param)
         $this->real_params_names[] = $real_param->name;

      $this->real_params_count = $this->real_method->getNumberOfParameters();
      $this->real_required_params_count = $this->real_method->getNumberOfRequiredParameters();

      if(self::$debug)
      {
         print "Class: $this->called_class\n";
         print "Method: $this->called_method\n";
         print "Called params count: $this->called_params_count\n";
         print "Required params count: $this->real_required_params_count\n";
         print "Called params names:\n";
         print_r($this->called_params_names);
         print "Real params names:\n";
         print_r($this->real_params_names);
      }

      return true;
   }

   /**
    * Call the user defined method for user authentication if callable and save the result in the static "me"
    *
    * @return bool
    */
   function authenticate()
   {
      if(is_callable(self::$authentication_method))
         self::$me = self::$authentication_method->__invoke();
      return true;
   }

   /**
    * Call the user defined authorization method passing the me returned by the authentication method and the method auth defined within the class static auth var if any
    *
    * @return bool
    */
   function authorize()
   {
      try
      {  $auth = $this->real_class->getStaticPropertyValue("auth");  }
      catch(Exception $e)
      {  $auth = null;  }

      $auth = (!empty($auth[$this->called_method])) ? $auth[$this->called_method] : null;

      if(is_callable(self::$authorization_method))
         $authentication = self::$authorization_method->__invoke(self::$me, $auth);
      else
         $authentication = false;

      if(!$authentication)
      {
         $this->error = self::ERR_AUTH;
         return false;
      }

      return true;
   }

   /**
    * Check method parameters count againist passed parameters count
    *
    * @return bool
    */
   function verifyParamsCount()
   {
      if(($this->called_params_count < $this->real_required_params_count) || ($this->called_params_count > $this->real_params_count))
         return false;
      return true;
   }

   /**
    * Check method parameters names againist passed parameters names
    *
    * @return bool
    */
   function verifyParamsNames()
   {
      foreach($this->called_params_names as $called_param)
         if(!in_array($called_param, $this->real_params_names))
            return false;
      return true;
   }

   /**
    * Check mandatory method parameters againist passed parameters
    *
    * @return bool
    */
   function verifyMandatoryParams()
   {
      foreach($this->real_params as $real_param)
         if(!array_key_exists($real_param->name, $this->called_params) && !$real_param->isDefaultValueAvailable())
            return false;
      return true;
   }

   /**
    * Convert every "true", "false" and "null" parameters to bool values true, false and null
    *
    * @return bool
    */
   function unserializeBools()
   {
      array_walk_recursive($this->called_params, function(&$value){
         if($value == "true")
            $value = true;
         else if($value == "false")
            $value  = false;
         else if($value == "null")
            $value = null;
      });

      return true;
   }

   /**
    * Reorder passed params to let them match the expected ones within the method to be invoked
    *
    * @return bool
    */
   function orderParams()
   {
      if(in_array("open_data", $this->real_params_names))
      {
         $this->ordered_params["open_data"] = $this->called_params;
         return true;
      }

      $this->ordered_params = [];
      foreach($this->real_params as $real_param)
         if($real_param->isDefaultValueAvailable())
            $this->ordered_params[$real_param->name] = (isset($this->called_params[$real_param->name])) ? $this->called_params[$real_param->name] : $real_param->getDefaultValue();
         else
            $this->ordered_params[$real_param->name] = $this->called_params[$real_param->name];

      return true;
   }

   /**
    * Return the result of the called method invocation
    *
    * @return mixed
    */
   function invokeMethod()
   {  return $this->real_method->invokeArgs(null, $this->ordered_params);  }

   /**
    * Print the data in an array ["data" => data] according to the invocation type: a simple print_r if the script was cli invoked, a pretty print json encode if the script was REST invoked
    *
    * @param $data
    */
   static function outputData($data)
   {
      $output = ["data" => $data];

      if(php_sapi_name() == "cli")
         print_r($output);
      else
         print(json_encode($output, JSON_PRETTY_PRINT));
   }

   /**
    * Print the error in an array ["error" => error] according to the invocation type: a simple print_r if the script was cli invoked, a pretty print json encode if the script was REST invoked
    *
    * @param $error
    */
   static function outputError($error)
   {
      $output = ["error" => $error];

      if(php_sapi_name() == "cli")
         print_r($output);
      else
         print(json_encode($output, JSON_PRETTY_PRINT));
   }

   /**
    * Let the Router output the error
    *
    * @param $error
    */
   static function returnError($error)
   {  die(self::outputError($error));  }

   /**
    * Detect whether the router was actually invoked or not and execute it
    */
   static function boot()
   {
      /**
       * Detect script invocation
       */
      if(!stristr($_SERVER["SCRIPT_NAME"], __CLASS__ . ".php"))
         return;

      global $argv;

      /**
       * Find out if it was cli or REST invoked and get the router object
       */
      if(php_sapi_name() == "cli")
         $router = Router::shellInit(!empty($argv[1]) ? $argv[1] : "");
      else
         $router = Router::RESTInit();

      /**
       * Routing steps:
       * - verify method existence
       * - authenticate user if needed
       * - verify authorization if needed
       * - verify passed params if needed
       * - unserialize bool params if needed
       * - reorder passed params
       * - invoke method
       * - return json encoded result or errors
       */

      header("Content-type: text/plain");

      if(!$router->methodExists())
         die(self::outputError(self::ERR_REQUEST));

      $router->buildMetadata();

      if(!self::$allow_policy)
      {
         if(!$router->authenticate())
            die(self::outputError(self::ERR_AUTH));

         if(!$router->authorize())
            die(self::outputError(self::ERR_AUTH));
      }

      if(!in_array("open_data", $router->real_params_names))
      {
         if(!$router->verifyParamsCount())
            die(self::outputError(self::ERR_PARAMS_COUNT));

         if(!$router->verifyParamsNames())
            die(self::outputError(self::ERR_PARAMS_MISMATCH));

         if(!$router->verifyMandatoryParams())
            die(self::outputError(self::ERR_PARAMS_MISSING));
      }

      if(self::$unserialize_bools)
         $router->unserializeBools();

      $router->orderParams();
      $result = $router->invokeMethod();

      self::outputData($result);
      die;
   }
}

?>
