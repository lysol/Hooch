<?php
namespace Hooch;

class Post extends Route
{
    public function __construct($app, $pattern, $callback, $name=null)
    {
        parent::__construct($app, 'POST', $pattern, $callback, $name);
    }

}
