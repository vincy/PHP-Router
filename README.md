PHP-Router
==========

#### What's that?
A REST / SHELL / API Requests Routing System: it provides "a centralized entry point for handling requests".  
Stop making tons of .php files to handle each ajax.  
Stop making static and separated .php files to handle each cron.  

#### Features
- Front Control Pattern implementation with no pain or hassling
- Centralized entry point to handle all app requests and more
- Strict parameters count and name validation
- Self defined authentication and authorization methods

#### Requirements
- PHP 5.4.9 or higher
- 5 minutes of your life

#### How does it work?
Suppose to have the following within your project:

```php
class Animal
{
   public static function eat($hotdogs, $mayo = true, $ketchup = false)
   {  return "yum!";  }
}
```

- You can fire within a shell: 

```
$> php Router.php 'Animal/eat?hotdogs=2&mayo=false'
```

- You can fire within a browser

```
www.mydomain.com/r/Animal/eat?hotdogs=2&mayo=false
```

The output is the pretty printed JSON encoded value returned by the funcion.
```
{
    "data": "yum!"
}
```

If you didn't already know, you can pass array and hashes (objects) using this syntax:

```php
class Animal
{
   public static function eat($hotdogs, $mayo = true, $ketchup = false)
   {  return get_defined_vars();  }
}
```
```
$> php Router.php 'Animal/eat?hotdogs[first]=big&hotdogs[second]=very_big&mayo[]=first_array_element&mayo[]=second_array_element'
$> curl www.mydomain.com/r/Animal/eat?hotdogs[first]=big&hotdogs[second]=very_big&mayo[]=first_array_element&mayo[]=second_array_element
```

Output:
```
{
    "data": {
        "hotdogs": {
            "first": "big",
            "second": "very_big"
        },
        "mayo": [
            "first_array_element",
            "second_array_element"
        ],
        "ketchup": false
    }
}
```


#### Contents
* [Parameters validation](#validation)
* [Security](#security)
* [Trigger app errors](#trigger)

<a name="validation"/>
#### Parameters validation

The Router auto checks what follows:

- The method requested exists
- The passed parameters count is equal or greater then the method mandatory parameters count
- The passed parameters count is not greater then the method parameters count
- The passed parameters names match the method parameters names

If one check fails the Router will return a built-in error.

<a name="security"/>
#### Security
The Router defaults to the Allow / Deny policy: if you do not provide the auth* methods then no access will be provided your app.  
If you just wanna test it, then you can simply set the config var $allow_policy to true.
Then you (and anyone else..) will gain access to anything within your classes:

```php
Router::$allow_policy = true;
```

Otherwise follows these guidelines: in the following example I'll be using a ranking system, but you can use whichever logic you wish!

##### Methods authorization
Define an auth obj for each method within your class:
```php
class Animal
{
   static $auth = [
      "eat" => 2, # just using an int standing for the ranking level needed
      "drink" => 3
   ];
   
   public static function eat($hotdogs, $ketchup = false)
   {  return "yum!";   }
   
   public static function drink($coke, $diet = true)
   {  return "slurp!";  }
}
```

##### Authentication
Define the authentication method that will be used to compare the method auth and the user auth:
```php
Router::$authentication_method = function(){
   return User::getMyRank();
};
```

##### Authorization
Define the authorization method the Router will use to allow class methods access. The authorization method takes two parameters:

1. The requesting user auth returned by the Authentication method
2. The requested method auth obj defined within the class

The logic is up to you: just return a boolean to indicate whether the user has access to the requested method.
```php
Router::$authorization_method = function($my_auth, $method_auth){
   # is my ranking higher or equal to the rank needed for the method?
   if($my_auth >= $method_auth)
      return true;
   else
      return false;
};
```

<a name="trigger"/>
#### Trigger apps errors
To return errors instead of data use the Router::returnError($error) method:
```php
class Animal
{
   public static function eat($hotdogs, $mayo = true, $ketchup = false)
   {  return Router::setError("my_error");  }
}
```
And the request will output an error obj instead of a data obj:
```
{
    "error": "my_error"
}
```