<?php

require_once 'config.php';

/*
 **************************
 * OVERKILLING PHP ROUTER *
 **************************
*/

define("INVALID_REQUEST", "request not valid");
define("INVALID_PARAMETERS_COUNT", "missing or extra parameters passed");
define("INVALID_PARAMETERS", "parameters not valid");

define("LOGIN_AUTHENTICATION_FAILED", "login not valid in order to fulfill the request");
define("API_AUTHENTICATION_FAILED", "API key not valid in order to fulfill the request");

define("INVALID_PARAMETER_TYPE", "type not valid for parameter");
define("INVALID_PARAMETER_SUBTYPE", "elements type not valid for parameter");
define("INVALID_PARAMETER_MIN_SIZE", "min size exceded for parameter");
define("INVALID_PARAMETER_MAX_SIZE", "max size exceded for parameter");
define("INVALID_PARAMETER_MATCH", "match not passed for parameter");

define("SCRIPT_INVOKING", 1);
define("SHELL_INVOKING", 2);
define("REST_INVOKING", 3);

class Router
{
   static $invoking_method = self::SCRIPT_INVOKING;
 
   static $required_class_name;
   static $required_method_name;
   static $required_parameters;
   
   static $real_class;
   static $real_method;
   static $real_parameters;
   static $real_parameters_names;
   static $real_parameters_number;
   static $real_required_parameters_number;
   static $parameters_definitions = null;
   
   static $result = null;
   static $reports = array();
   static $debugging = false;
   
   static $API_key = null;
   static $cache_bypass;

   const SCRIPT_INVOKING = 1;
   const SHELL_INVOKING = 2;
   const REST_INVOKING = 3;
   
   public static function __callStatic($name, $parameters)
   {
      @list($class_name, $method_name) = explode("_", $name); 
      return self::_route($class_name, $method_name, $parameters);
   }
   
   public static function _ShellRouting($string)
   {
      /* rewrite emulation */
      $temp1 = explode("/", $string);
      $temp2 = explode("?", @$temp1[1]);
      $query_string = @$temp2[1];
      $parameters = array();
      parse_str($query_string, $parameters);
      
      @list($class_name, $method_name) = array(@$temp1[0], @$temp2[0]);      
      return self::_route($class_name, $method_name, $parameters);
   }
   
   public static function _RESTRouting()
   {
      @list($class_name, $method_name) = array($_REQUEST['required_class_name'], $_REQUEST['required_method_name']);
      unset($_REQUEST['required_class_name']);
      unset($_REQUEST['required_method_name']);
      $parameters = $_REQUEST;
      return self::_route($class_name, $method_name, $parameters);
   }
   
   public static function _route($required_class_name, $required_method_name, $required_parameters)
   {
      /* preliminary checks */
      if(empty($required_class_name) || empty($required_method_name) || !method_exists($required_class_name, $required_method_name))
      {   
         Router::$reports = INVALID_REQUEST;
         return self::_outputResult(true);
      }
      
      self::$required_class_name = $required_class_name;
      self::$required_method_name = $required_method_name;
      
      /* router variables fixing */
      $unsets = array('API_key', 'cache_bypass', 'debugging');
      foreach($unsets as $key)
      {
         self::$$key = @$required_parameters[$key];
         unset($required_parameters[$key]);
      }

      self::$required_parameters = (array)$required_parameters;

      /* set boolean values */
      foreach(self::$required_parameters as $required_parameter_name => $required_parameter_value)
         if($required_parameter_value == "false")
            self::$required_parameters[$required_parameter_name] = false;
         else if($required_parameter_value == "true")
            self::$required_parameters[$required_parameter_name] = true;

      /* let's route */
      $routing_sequence = array(
         '_buildMetadata',
         '_validateAuthentication', 
         '_checkParameters',
         '_validateParameters',
         '_invokeMethod',
         '_outputResult'
      );
      
      foreach($routing_sequence as $routing_step)
      {
         if($routing_step == '_validateAuthentication' && self::$invoking_method == SCRIPT_INVOKING)
            continue;
         
         if($routing_step == '_validateParameters' && !self::$parameters_definitions)
            continue;
         
         self::$routing_step();
         
         if(self::$reports)
            return self::_outputResult();
      }
      
      return self::$result;
        
      /*
      if(self::$invoking_method != SCRIPT_INVOKING)
      {
         self::_buildMetadata();
         self::_validateAuthentication(); 
         self::_checkParameters();
         self::_validateParameters();
         self::_invokeMethod();
         self::_outputResult();
      }
      else
      {
         self::_buildMetadata();
         //self::_validateAuthentication();
         self::_checkParameters();
         self::_validateParameters();
         self::_invokeMethod();
         self::_outputResult();
      }
      */
   }
   
   public static function _buildMetadata()
   {
      self::$real_class = new ReflectionClass(self::$required_class_name);
      self::$real_method = new ReflectionMethod(self::$required_class_name, self::$required_method_name);
      self::$real_parameters = self::$real_method->getParameters();
      
      /* get real parameters names array */
      self::$real_parameters_names = array();
      foreach(self::$real_parameters as $real_parameter)
         self::$real_parameters_names[] = $real_parameter->name;
      
      /* get real parameters counts */
      self::$real_parameters_number = self::$real_method->getNumberOfParameters();
      self::$real_required_parameters_number = self::$real_method->getNumberOfRequiredParameters();

      try
      {  self::$parameters_definitions = new ReflectionClass("Parameters");  }
      catch(Exception $e)
      {  self::$parameters_definitions = null;  }
   }
   
   public static function _validateAuthentication()
   {
      /* 
       * In order to allow Router.php to route to request to a class, the latter must provide one or both of the 
       * following static array parameters:
       *    static $login_auth = array();
       *    static $API_auth = array();
       * Possible elements:
       *    minimum => int : means the minimum level required in order to access every method within the class, set
       *       to null to allow access to everyone 
       *    method_name => minum_auth_value : means the minum level required in order to access the function
       * 
       * 1) Both types can be declared
       * 2) Enabling just one of them means disabling the access for the other one (else would be allow all -_-)
       * 3) Enabling one of them with a minimum set to null means access for everythin in the class for both
       */
      
      $possible_auths = array("API_auth", "login_auth");

      foreach($possible_auths as $possible_auth)
      {
         try
         {  $$possible_auth = self::$real_class->getStaticPropertyValue($possible_auth);  }
         catch(Exception $e)
         {  $$possible_auth = array();  }
      }

      /* check for allow all */
      if((isset($API_auth['default']) && !$API_auth['default']) || (isset($login_auth['default']) && !$login_auth['default']))
         return;
      
      /* check if API authentication was required for the method */
      if(self::$API_key)
      {
         if(!empty($API_auth[self::$required_method_name]))
         {
            if(!self::_isValidAPIKey($API_auth[self::$required_method_name]))
               Router::$reports = API_AUTHENTICATION_FAILED;
         }
         /* else check if a minimum API authentication was required for every method anyway */
         else if(isset($API_auth['default']))
            if(!self::_isValidAPIKey($API_auth['default']))
               Router::$reports = API_AUTHENTICATION_FAILED;
      }
      else
      {
         /* check if login authentication was required for the method */
         if(!empty($login_auth[self::$required_method_name]))
         {
            if(!self::_isValidLogin($login_auth[self::$required_method_name]))
               Router::$reports = LOGIN_AUTHENTICATION_FAILED;
         }
         /* else check if a minimum login authentication level was required for every method anyway */
         else if(isset($login_auth['default']))
            if(!self::_isValidLogin($login_auth['default']))
               Router::$reports = LOGIN_AUTHENTICATION_FAILED;
      }
   }
   
   public static function _checkParameters()
   {
      $required_class_name = self::$required_class_name;
      $required_method_name = self::$required_method_name;
      $required_parameters = self::$required_parameters;
      $required_parameters_names = array_keys(self::$required_parameters);
      $required_parameters_number = count(self::$required_parameters);
            
      /* check method parameters number againist passed parameters number */
      if(($required_parameters_number < self::$real_required_parameters_number) || ($required_parameters_number > self::$real_parameters_number))
         Router::$reports[] = INVALID_PARAMETERS_COUNT;
      
      /* check method parameters names againist passed parameters names for rest and shell invoking */
      if(self::$invoking_method != SCRIPT_INVOKING)
         foreach(self::$real_parameters as $real_parameter)
            if(!$real_parameter->isDefaultValueAvailable() && !isset(self::$required_parameters[$real_parameter->name]))
               Router::$reports[] = INVALID_PARAMETERS_COUNT;

      if(Router::$reports)
         return;

      /* reorder parameters for rest and shell invoking or rename them form script invoking */
      $ordered_parameters = array();
      if(self::$invoking_method != SCRIPT_INVOKING)
      {
         foreach(self::$real_parameters as $real_parameter)
         {
            /* set the default value for optional parameters if not passed */
            if($real_parameter->isDefaultValueAvailable())
               $ordered_parameters[$real_parameter->name] = (isset(self::$required_parameters[$real_parameter->name])) ? self::$required_parameters[$real_parameter->name] : $real_parameter->getDefaultValue();
            else
               $ordered_parameters[$real_parameter->name] = self::$required_parameters[$real_parameter->name];
         }
      }
      /* else rename them for script invoking */
      else
         foreach(self::$real_parameters as $index => $real_parameter)
            $ordered_parameters[$real_parameter->name] = self::$required_parameters[$index];
      
      self::$required_parameters = $ordered_parameters;   
   }
   
   public static function _validateParameters()
   {
      /* why not a simple array_walk_recursive? cuz "Any key that holds an array will not be passed to the function". */
      foreach(self::$required_parameters as $key => $value)
         self::_validateParameter($key, $value);
   }
   
   public static function _validateParameter($parameter_name, $parameter_value, $subtype_check = false)
   {
      /* if no parameter definition was specified then the parameter is assumed validated */
      try
      {  $parameter = self::$parameters_definitions->getStaticPropertyValue($parameter_name);  }
      catch(Exception $e)
      {  return true;  }
      
      $conditions = array("type", "subtype", "no_key", "min", "max", "positive_match", "negative_match", "error_code");
      foreach($conditions as $condition)
         $$condition = @$parameter[$condition];
      
      /* type */
      if($type != null)
      {
         switch($type)
         {
            case 'int':
               if(!is_numeric($parameter_value))
                  Router::$reports[] = INVALID_PARAMETER_TYPE . ": $parameter_name";
               if(strpos($parameter_value, "."))
                  Router::$reports[] = INVALID_PARAMETER_TYPE . ": $parameter_name";
               $parameter_value = (int)$parameter_value;
               break;

            case 'float':
              if(!is_numeric($parameter_value))
                  Router::$reports[] = INVALID_PARAMETER_TYPE . ": $parameter_name";
               $parameter_value = (float)$parameter_value;
               break;

            case 'timestamp':
               if(!(((string)(int)$timestamp === $timestamp) && ($timestamp <= PHP_INT_MAX) && ($timestamp >= ~PHP_INT_MAX)))
                  Router::$reports[] = INVALID_PARAMETER_TYPE . ": $parameter_name";
               break;

            case 'json':
               if(!json_decode($parameter_value))
                  Router::$reports[] = INVALID_PARAMETER_TYPE . ": $parameter_name";
               break;

            case 'bool':
               if(!is_bool($parameter_value))
                  Router::$reports[] = INVALID_PARAMETER_TYPE . ": $parameter_name";
               break;

            default:
               break;
         }
      }

      /* min value */
      if($min != null)
      {
         switch($type)
         {
            case 'string':
               if(strlen($parameter_value) < $min)
                  Router::$reports[] = INVALID_PARAMETER_MIN_SIZE . ": $parameter_name";
               break;

            case 'array':
                  if(count($parameter_value) < $min)
                     Router::$reports[] = INVALID_PARAMETER_MIN_SIZE . ": $parameter_name";
               break;
            
            case 'int':
            case 'float':
            case 'timestamp':
               if($parameter_value < $min)
                  Router::$reports[] = INVALID_PARAMETER_MIN_SIZE . ": $parameter_name";
               break;
            
            default:
               break;
         }
      }

      /* max value */
      if($max != null)
      {
         switch($type)
         {
            case 'string':
               if(strlen($parameter_value) > $max)
                  Router::$reports[] = INVALID_PARAMETER_MAX_SIZE . ": $parameter_name";
               break;
            
            case 'array':
               if(count($parameter_value) > $max)
                  Router::$reports[] = INVALID_PARAMETER_MAX_SIZE . ": $parameter_name";
               break;

            case 'int':
            case 'float':
            case 'timestamp':
               if($parameter_value > $max)
                  Router::$reports[] = INVALID_PARAMETER_MAX_SIZE . ": $parameter_name";
               break;

            default:
               break;
         }
      }

      /* positive match */
      if($positive_match != null && in_array($type, array('string', 'json')))
      {
         preg_match($positive_match, $parameter_value, $matches);
         if(empty($matches))
            Router::$reports[] = INVALID_PARAMETER_MATCH . ": $parameter_name";
      }

      /* negative match */
      if($negative_match != null && in_array($type, array('string', 'json')))
      {
         preg_match($negative_match, $parameter_value, $matches);
         if(!empty($matches))
            Router::$reports[] = INVALID_PARAMETER_MATCH . ": $parameter_name";
      }
      
      /* ARRAYS HEADACHES */
      
      /* subtype checking */
      if($subtype)
      {
         $parameter_value = (array)$parameter_value;
         foreach($parameter_value as $element)
            if(!self::_validateParameter($subtype, $element, true))
               break;
      }
      
      /* if not already cycling through an array for subtype checking but the parameter is an array itself anyway */
      if(!$subtype_check && is_array($parameter_value))
         /* check subelements */
         foreach($parameter_value as $key => $value)
         {
            /* check if key is not allowed or the mothafucka is gonna change not allowed fields, at least for his level */ 
            if($no_key && preg_match($no_key, $key))
               self::$reports[] = INVALID_PARAMETERS_COUNT;
            self::_validateParameter($key, $value);
         }
   }
      
   public static function _invokeMethod()
   {  self::$result = self::$real_method->invokeArgs(null, self::$required_parameters);  }

   public static function _outputResult()
   {
      if(self::$invoking_method == self::SCRIPT_INVOKING)
         return false;
      
      $output = array('data' => self::$result, 'reports' => array_unique((array)self::$reports));

      if(self::$invoking_method == self::SHELL_INVOKING)
         die(print_r($output, 1));
      else if(self::$invoking_method == self::REST_INVOKING)
      {
         header("Content-type: text/plain");
         if(self::$debugging)
            die(print_r($output, 1));
         else
            die(json_encode($output));
      }
   }
   
   public static function _isValidAPIKey($auth_level)
   {  return true;  }

   public static function _isValidLogin($auth_level)
   {  return (!empty($_SESSION['rank']) && $_SESSION['rank'] >= $auth_level) ? true : false;  }
}

//header("Content-type: text/plain");
$debug_backtrace = debug_backtrace();
//die();

/* detect shell invocation */
if(isset($argv))
{
   //print "shell invocation";
   Router::$invoking_method = Router::SHELL_INVOKING;
   Router::_ShellRouting(@$argv[1]);
   die();
}

/* detect browser invocation */
if(isset($_REQUEST['class_name']) || empty($debug_backtrace))
{
   //print "browser invocation";
   Router::$invoking_method = Router::REST_INVOKING;
   Router::_RESTRouting();
   die();
}

?>