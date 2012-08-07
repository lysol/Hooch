<?php
namespace Hooch;

class Get extends Route
{
    public function __construct($app, $pattern, $callback, $name=null)
    {
        parent::__construct($app, 'GET', $pattern, $callback, $name);
    }
}
