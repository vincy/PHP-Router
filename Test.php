<?php

/**
 * PHP Router (https://github.com/OverKiller/PHP-Router)
 *
 * @author     Damiano Barbati <damiano.barbati@gmail.com>
 * @copyright  Copyright (c) 2013-2014 Damiano Barbati (http://www.damianobarbati.com)
 * @license    http://www.wtfpl.net/about/ DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
 * @link       https://github.com/OverKiller/PHP-Router for the source repository
 */

class Test
{
   static function noParams()
   {  return get_defined_vars();  }

   static function freeParams($open_data)
   {  return get_defined_vars();  }

   static function threeParams($a, $b, $c)
   {  return get_defined_vars();  }

   static function twoParams($a, $b, $c = false)
   {  return get_defined_vars();  }

   static function ops()
   {  return Router::returnError("USER_ERROR");  }
}

?>