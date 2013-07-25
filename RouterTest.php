<?php

require "Router.php";
require "Test.php";

class RouterTest extends PHPUnit_Framework_TestCase
{
   private $router;

   function setUp()
   {  $this->router = Router::shellInit("Test/noParams");  }

   function testConstructor()
   {
      $router = Router::shellInit("Test/noParams");
      $this->assertEquals("Test", $router->called_class);
      $this->assertEquals("noParams", $router->called_method);
      $this->assertEmpty($router->called_params);

      $router = Router::shellInit("Test/noParams?");
      $this->assertEquals("Test", $router->called_class);
      $this->assertEquals("noParams", $router->called_method);
      $this->assertEmpty($router->called_params);
   }

   function testMethodExists()
   {
      $router = Router::shellInit("Test/noParams");
      $this->assertTrue($router->methodExists());

      $router = Router::shellInit("Test/freeParams");
      $this->assertTrue($router->methodExists());

      $router = Router::shellInit("Router/method_exists");
      $this->assertFalse($router->methodExists());
   }

   function testBuildMetadata()
   {
      $router = Router::shellInit("Test/noParams");
      $router->buildMetadata();

      $this->assertEquals("Test", $router->called_class);
      $this->assertEquals("noParams", $router->called_method);
      $this->assertEmpty($router->called_params);
      $this->assertEmpty($router->called_params_names);
      $this->assertEquals(0, $router->called_params_count);

      $this->assertInstanceOf("ReflectionClass", $router->real_class);
      $this->assertInstanceOf("ReflectionMethod", $router->real_method);
      $this->assertEmpty($router->real_params);
      $this->assertEmpty($router->real_params_names);
      $this->assertEquals(0, $router->real_params_count);
      $this->assertEquals(0, $router->real_required_params_count);

      $router = Router::shellInit("Test/freeParams");
      $router->buildMetadata();

      $this->assertEquals("Test", $router->called_class);
      $this->assertEquals("freeParams", $router->called_method);
      $this->assertEmpty($router->called_params);
      $this->assertEmpty($router->called_params_names);
      $this->assertEquals(0, $router->called_params_count);

      $this->assertInstanceOf("ReflectionClass", $router->real_class);
      $this->assertInstanceOf("ReflectionMethod", $router->real_method);
      $this->assertEquals([
         "open_data"
      ], $router->real_params_names);
      $this->assertEquals(1, $router->real_params_count);
      $this->assertEquals(1, $router->real_required_params_count);

      $router = Router::shellInit("Test/threeParams");
      $router->buildMetadata();

      $this->assertEquals("Test", $router->called_class);
      $this->assertEquals("threeParams", $router->called_method);
      $this->assertEmpty($router->called_params);
      $this->assertEmpty($router->called_params_names);
      $this->assertEquals(0, $router->called_params_count);

      $this->assertInstanceOf("ReflectionClass", $router->real_class);
      $this->assertInstanceOf("ReflectionMethod", $router->real_method);
      $this->assertEquals([
         "a",
         "b",
         "c"
      ], $router->real_params_names);
      $this->assertEquals(3, $router->real_params_count);
      $this->assertEquals(3, $router->real_required_params_count);

      $router = Router::shellInit("Test/twoParams");
      $router->buildMetadata();

      $this->assertEquals("Test", $router->called_class);
      $this->assertEquals("twoParams", $router->called_method);
      $this->assertEmpty($router->called_params);
      $this->assertEmpty($router->called_params_names);
      $this->assertEquals(0, $router->called_params_count);

      $this->assertInstanceOf("ReflectionClass", $router->real_class);
      $this->assertInstanceOf("ReflectionMethod", $router->real_method);

      $this->assertEquals([
         "a",
         "b",
         "c"
      ], $router->real_params_names);
      $this->assertEquals(3, $router->real_params_count);
      $this->assertEquals(2, $router->real_required_params_count);
   }

   function testUnserializeBools()
   {
      Router::$unserialize_bools = true;
      $router = Router::shellInit("Test/freeParams?true=true&false=false&null=null&param=param");
      $router->buildMetadata();
      $router->unserializeBools();
      $this->assertEquals([
         "true" => true,
         "false" => false,
         "null" => null,
         "param" => "param"
      ], $router->called_params);
   }

   function testVerifyParams()
   {
      $router = Router::shellInit("Test/noParams?");
      $router->buildMetadata();
      $this->assertTrue($router->verifyParamsCount());
      $this->assertTrue($router->verifyParamsNames());
      $this->assertTrue($router->verifyMandatoryParams());

      $router = Router::shellInit("Test/noParams?true=true&false=false&null=null&param=param");
      $router->buildMetadata();
      $this->assertFalse($router->verifyParamsCount());
      $this->assertFalse($router->verifyParamsNames());

      $router = Router::shellInit("Test/threeParams?a=yes&b=no&c=maybe");
      $router->buildMetadata();
      $this->assertTrue($router->verifyParamsCount());
      $this->assertTrue($router->verifyParamsNames());
      $this->assertTrue($router->verifyMandatoryParams());

      $router = Router::shellInit("Test/threeParams?a=yes&b=no&c=maybe&d=sure");
      $router->buildMetadata();
      $this->assertFalse($router->verifyParamsCount());
      $this->assertFalse($router->verifyParamsNames());

      $router = Router::shellInit("Test/twoParams?a=yes&b=no");
      $router->buildMetadata();
      $this->assertTrue($router->verifyParamsCount());
      $this->assertTrue($router->verifyParamsNames());
      $this->assertTrue($router->verifyMandatoryParams());

      $router = Router::shellInit("Test/twoParams?a=yes&b=no&c=maybe");
      $router->buildMetadata();
      $this->assertTrue($router->verifyParamsCount());
      $this->assertTrue($router->verifyParamsNames());
      $this->assertTrue($router->verifyMandatoryParams());
   }

   function testOrderParams()
   {
      $router = Router::shellInit("Test/noParams?");
      $router->buildMetadata();
      $router->verifyParamsCount();
      $router->verifyParamsNames();
      $router->verifyMandatoryParams();
      $router->orderParams();
      $this->assertEmpty($router->ordered_params);

      $router = Router::shellInit("Test/freeParams?true=true&false=false&null=null&param=param");
      $router->buildMetadata();
      $router->verifyParamsCount();
      $router->verifyParamsNames();
      $router->verifyMandatoryParams();
      $router->orderParams();
      $this->assertEquals([
         "open_data" => [
            "true" => "true",
            "false" => "false",
            "null" => "null",
            "param" => "param"
         ],
      ], $router->ordered_params);

      $router = Router::shellInit("Test/threeParams?c=maybe&b=no&a=yes");
      $router->buildMetadata();
      $router->verifyParamsCount();
      $router->verifyParamsNames();
      $router->verifyMandatoryParams();
      $router->orderParams();
      $this->assertEquals([
         "a" => "yes",
         "b" => "no",
         "c" => "maybe"
      ], $router->ordered_params);

      $router = Router::shellInit("Test/twoParams?b=no&a=yes");
      $router->buildMetadata();
      $router->verifyParamsCount();
      $router->verifyParamsNames();
      $router->verifyMandatoryParams();
      $router->orderParams();
      $this->assertEquals([
         "a" => "yes",
         "b" => "no",
         "c" => false
      ], $router->ordered_params);

      $router = Router::shellInit("Test/twoParams?c=maybe&b=no&a=yes");
      $router->buildMetadata();
      $router->verifyParamsCount();
      $router->verifyParamsNames();
      $router->verifyMandatoryParams();
      $router->orderParams();
      $this->assertEquals([
         "a" => "yes",
         "b" => "no",
         "c" => "maybe"
      ], $router->ordered_params);
   }

   function invokeMethod()
   {
      $router = Router::shellInit("Test/noParams?");
      $router->buildMetadata();
      $router->verifyParamsCount();
      $router->verifyParamsNames();
      $router->verifyMandatoryParams();
      $router->orderParams();
      $router->invokeMethod();
      $this->assertEmpty($router->result);
      $router = Router::shellInit("Test/freeParams?true=true&false=false&null=null&param=param");
      $router->buildMetadata();
      $router->verifyParams();
      $router->orderParams();
      $router->invokeMethod();
      $this->assertEquals([
         "open_data" => [
            "true" => "true",
            "false" => "false",
            "null" => "null",
            "param" => "param"
         ],
      ], $router->result);

      $router = Router::shellInit("Test/threeParams?c=maybe&b=no&a=yes");
      $router->buildMetadata();
      $router->verifyParamsCount();
      $router->verifyParamsNames();
      $router->verifyMandatoryParams();
      $router->orderParams();
      $router->invokeMethod();
      $this->assertEquals([
         "a" => "yes",
         "b" => "no",
         "c" => "maybe"
      ], $router->result);

      $router = Router::shellInit("Test/twoParams?b=no&a=yes");
      $router->buildMetadata();
      $router->verifyParamsCount();
      $router->verifyParamsNames();
      $router->verifyMandatoryParams();
      $router->orderParams();
      $router->invokeMethod();
      $this->assertEquals([
         "a" => "yes",
         "b" => "no",
         "c" => false
      ], $router->result);

      $router = Router::shellInit("Test/twoParams?c=maybe&b=no&a=yes");
      $router->buildMetadata();
      $router->verifyParamsCount();
      $router->verifyParamsNames();
      $router->verifyMandatoryParams();
      $router->orderParams();
      $router->invokeMethod();
      $this->assertEquals([
         "a" => "yes",
         "b" => "no",
         "c" => "maybe"
      ], $router->result);
   }
}

?>