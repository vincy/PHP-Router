PHP-Router
==========

What's that?
A REST / SHELL / API Requests Routing System: yep! It provides "a centralized entry point for handling requests".
It works out of the box.

How does it work?
Suppose to have a class Animal and a static method eat, then you may do the following:
- fire within a shell "php Router.php 'Animal/eat?fruit=false&hotdog=true'"
- fire within a browser "www.mydomain.com/r/Animal/eat?fruit=false&hotdog=true"

The output is the pretty printed JSON encoded value returned by the funcion. 

Patterns
- Front Controller Pattern

Requirements
- PHP 5.4.9 or higher
