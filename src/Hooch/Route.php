<?php
namespace Hooch;

class Route
{
    public $routeType;
    public $pattern;
    public $callback;
    public $name;
    public $app;

    public function __construct($app, $routeType, $pattern, $callback, $name=null)
    {
        $this->routeType = $routeType;
        $this->pattern = $pattern;
        $this->callback = $callback;
        $this->name = $name;
        $this->app = $app;
    }

    private function buildRoutePattern($pattern)
    {
        $pattern = preg_quote($pattern, '/');
        $pattern = preg_replace('/\\\:([a-zA-Z0-9_]+)/', '(?P<$1>[^\/]+)', $pattern);
        // only match full paths if this is set.
        //if (isset($this->app->strictPaths) && $this->app->strictPaths)
        //{
            // If the last char is a slash, make it optional.
            if (substr($pattern, -1, 1) == '/')
                $pattern = sprintf("^%s$", $pattern . '?');
            else    
                $pattern = sprintf("^%s$", $pattern);
        //}
        return "/" . $pattern . "/";
    }

    public function dispatch($url)
    {
        if ($_SERVER['REQUEST_METHOD'] != $this->routeType)
            return false;
        $targetPattern = $this->buildRoutePattern($this->pattern);

        if (preg_match($targetPattern, $url, $matches))
        {
            $argnames = array_filter(array_keys($matches), 'is_string');
            $args = array();
            foreach ($argnames as $arg)
            {
                $args[$arg] = $matches[$arg];
            }
            $callback = $this->callback;
            $result = $callback($args);
            if (is_string($result))
                print $result;
            return true;
        }
        return false;
    }
}
