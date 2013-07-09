<?php

require_once 'config.php';

class Router
{
   const REST_INVOKING = "rest";
   const SHELL_INVOKING = "shell";
   
   # routing errors 
   const ERR_REQUEST = "ERR_REQUEST";
   const ERR_AUTH = "ERR_AUTH";
   const ERR_PARAMS_COUNT = "ERR_PARAMS_COUNT";
   const ERR_PARAMS_1 = "ERR_PARAMS_MISMATCH";
   const ERR_PARAMS_2 = "ERR_PARAMS_MANDATORY_MISSING";
   
   static $routing_steps  = ["preliminaryChecks", "buildMetadata", "authenticate", "authorize", "checkParameters", "invokeMethod"];
      
   public $invoking_type = false;
   
   static $CLASS = false;
   static $METHOD = false;
   
   public $called_class = false;
   public $called_method = false;
   public $called_params = [];
   public $called_params_names = [];
   public $called_params_count = false;
   
   public $real_class = false;
   public $real_method = false;
   public $real_params = [];
   public $real_params_names = [];
   public $real_params_count = false;
   public $real_required_params_count = false;
   
   public $fixed_params = [];
   
   static $authentication_method = false;
   static $me = false;
   static $authorization_method = false;
   static $authorization = false;
   
   public $result = false;
   public $error = null;
   public $output = null;
   
   public $_ = false; # jquery ajax cache bypassing parameter
   public $api_key = false;
   
   # true to enable warnings!
   const DEBUG = false;  
   
   protected static $instance;
   
   final public static function getInstance()
   {  
      if(empty(static::$instance))
         static::$instance = new static();  
      
      return static::$instance;
   }      

   public static function getInstance()
   {  
      if(empty(static::$instance))
         static::$instance = new static();  
      
      return static::$instance;
   }      

   public static function shellInit($string)
   {
      $temp1 = explode("/", $string);
      $temp2 = explode("?", @$temp1[1]);
      $query_string = isset($temp2[1]) ? $temp2[1] : "";
      $called_params = [];
      parse_str($query_string, $called_params);
      
      $called_class = (isset($temp1[0])) ? $temp1[0] : "";
      $called_method = (isset($temp2[0])) ? $temp2[0] : "";
      
      return new self($called_class, $called_method, $called_params, self::SHELL_INVOKING);
   }
   
   public static function RESTInit()
   {
      $called_class = (isset($_REQUEST["called_class"])) ? $_REQUEST["called_class"] : "";
      $called_method = (isset($_REQUEST["called_method"])) ? $_REQUEST["called_method"] : "";
      
      # remove cookies!
      $_REQUEST = array_diff($_REQUEST, $_COOKIE);
 
      foreach(["called_class", "called_method", "PHPSESSID"] as $unset)
         unset($_REQUEST[$unset]);
      
      return new self($called_class, $called_method, $_REQUEST, self::REST_INVOKING);
   }
 
   public function __construct($called_class, $called_method, $called_params, $invoking_type)
   {
      $this->invoking_type = $invoking_type;
      $this->called_class = self::$CLASS = $called_class;
      $this->called_method = self::$METHOD = $called_method;
      $this->called_params = (array)$called_params;
      self::$instance = $this;
   }
   
   public function preliminaryChecks()
   {
      # check whether method exists or not
      if(empty($this->called_class) || $this->called_class == __CLASS__ || empty($this->called_method) || !method_exists($this->called_class, $this->called_method))
      {   
         $this->error = self::ERR_REQUEST;
         return false;
      }
      
      # clean params
      $unsets = ["api_key", "_"];
      foreach($unsets as $key)
         if(isset($this->called_params[$key]))
         {
            $this->$key = $this->called_params[$key];
            unset($this->called_params[$key]);
         }

      # fix boolean values passed as strings
      foreach($this->called_params as &$called_param)
      {
         if($called_param == "true")
            $called_param = true;
         else if($called_param == "false")
            $called_param = false;
         else if($called_param == "null")
            $called_param = null;
      }  
      
      return true;
   }
   
   public function buildMetadata()
   {
      $this->called_params_names = array_keys($this->called_params);
      $this->called_params_count = count($this->called_params);
      
      $this->real_class = new ReflectionClass($this->called_class);
      $this->real_method = new ReflectionMethod($this->called_class, $this->called_method);
      $this->real_params = $this->real_method->getParameters();
      
      # get real parameters names array 
      foreach($this->real_params as $real_param)
         $this->real_params_names[] = $real_param->name;
      
      # get real parameters counts 
      $this->real_params_count = $this->real_method->getNumberOfParameters();
      $this->real_required_params_count = $this->real_method->getNumberOfRequiredParameters();
      
      return true;
   }
   
   public function authenticate()
   {
      if($this->invoking_type != self::REST_INVOKING)
         return true;
      
      if(is_callable(self::$authentication_method))
         self::$me = self::$authentication_method->__invoke($this->api_key);
      return true;
   }
   
   public function authorize()
   {  
      if($this->invoking_type != self::REST_INVOKING)
         return true;

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
   
   public function checkParameters()
   {
      if(self::DEBUG)
      {
         print "Called params count: $this->called_params_count\n";
         print "Required params count: $this->real_required_params_count\n";
         print "Called params names\n";
         print_r($this->called_params_names);
         print "Real params names\n";
         print_r($this->real_params_names);
      }

      if(in_array("open_data", $this->real_params_names))
      {
         $this->fixed_params["open_data"] = $this->called_params;
         return true;
      }
      
      # check method parameters count againist passed parameters count 
      if(($this->called_params_count < $this->real_required_params_count) || ($this->called_params_count > $this->real_params_count))
      {
         $this->error = self::ERR_PARAMS_COUNT;
         return false;
      }

      # check method parameters names againist passed parameters names
      foreach($this->called_params_names as $called_param)
         if(!in_array($called_param, $this->real_params_names))
            $this->error = self::ERR_PARAMS_1;
         
      # check mandatory method parameters againist passed parameters
      foreach($this->real_params as $real_param)
         if(!array_key_exists($real_param->name, $this->called_params) && !$real_param->isDefaultValueAvailable())
            $this->error = self::ERR_PARAMS_2;

      if($this->error)
         return false;
         
      # reorder parameters 
      $this->fixed_params = [];
      foreach($this->real_params as $real_param)
         if($real_param->isDefaultValueAvailable())
            $this->fixed_params[$real_param->name] = (isset($this->called_params[$real_param->name])) ? $this->called_params[$real_param->name] : $real_param->getDefaultValue();
         else
            $this->fixed_params[$real_param->name] = $this->called_params[$real_param->name];
      return true;
   }
      
   public function invokeMethod()
   {  
      $this->result = $this->real_method->invokeArgs(null, $this->fixed_params);
      return true;
   }

   public function outputResult()
   {
      $data = (empty($this->error)) ? ["data" => $this->result] : ["error" => $this->error];
      $output = ($this->invoking_type == self::SHELL_INVOKING) ? print_r($data, 1) : json_encode($data, JSON_PRETTY_PRINT);
      return $output;
   }
   
   public static function setError($error)
   {
      $router = self::getInstance();
      $router->error = $error;
      return false;
   }
}

/* detect shell invocation */
if(isset($argv))
   $router = Router::shellInit(!empty($argv[1]) ? $argv[1] : "");

/* detect browser invocation */
if(isset($_REQUEST["called_class"])) 
   $router = Router::RESTInit();

if(isset($router))
{
   header("Content-type: text/plain");
   foreach($router::$routing_steps as $routing_step)
      if(!$router->$routing_step())
         die($router->outputResult());
   die($router->outputResult());
}

?>
