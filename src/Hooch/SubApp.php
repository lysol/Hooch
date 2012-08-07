<?php
namespace Hooch;

class SubApp
{
    public $app;
    public $prefix;

    public function __construct($basePath, $basePrefix, $app)
    {
        $app->setBasePath($basePath . $basePrefix);
        $this->app = $app;
        $this->prefix = $basePrefix;
        $this->basePath = $basePath;
    }

    public function dispatch($url)
    {
        if (substr($url, -1) == '/')
            $url = substr($url, 0, strlen($url) - 1);
        if (preg_match('/^' . preg_quote($this->prefix, '/') . '/', $url, $matches)) {
            $url = substr($url, 0, strlen($this->basePrefix));
            $this->app->serve($url);
             return true;
        } else {
            return false;
        }
    }
}
