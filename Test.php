<?php

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
}

?>