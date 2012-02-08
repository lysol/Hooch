<?php

namespace Hooch;

require 'Twig/Autoloader.php';

class Preprocessor
{
    public function __construct()
    {
    }

    public function fail($app, $path)
    {

    }

    public function success($app, $path)
    {

    }

    public function process($app, $path)
    {
        if (!($this->test($app, $path))) {
            $this->fail($app, $path);
        } else {
            $this->success($app, $path);
        }
    }

    public function test($app, $path)
    {

    }
}


class Flash
{
    public $message;
    public $type;

    public function __construct($type, $message)
    {
        $this->type = $type;
        $this->message = $message;
    }

    public function render($htmlFormat=null)
    {
        if ($htmlFormat == null)
            $htmlFormat = "<div class=\"flash flash-$this->type\">%s</div>";
        return sprintf($htmlFormat, $this->message);
    }
}


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


class Get extends Route
{
    public function __construct($app, $pattern, $callback, $name=null)
    {
        parent::__construct($app, 'GET' ,$pattern, $callback, $name);
    }
}

class Post extends Route
{
    public function __construct($app, $pattern, $callback, $name=null)
    {
        parent::__construct($app, 'POST', $pattern, $callback, $name);
    }
}


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

class App
{
    private $routes = array();
    private $subApps = array();
    private $preprocessors = array();
    public $notFoundBody = '';
    public $errorBody = '';
    public $basePath = '';
    private $strictPaths = false;
    public $twig;
    public $error_page = null;
    public $error_args = array();
    private $_flash;


    public function __construct($basePath='/', $twig=null, $loader=null)
    {
        \Twig_Autoloader::register();
        if ($loader == null)
            $loader = new \Twig_Loader_Filesystem('templates');
        if ($twig == null)
            $this->twig = new \Twig_Environment(
                $loader, 
                array('debug' => true, 'strict_variables' => true, 'autoescape' => false)
            );
        else
            $this->twig = $twig;

        // stick pre-stripslash'd data in there
        $this->twig->addGlobal('form', $this->getPost());
        $this->twig->addGlobal('app', $this);
        $this->twig->addGlobal('flash', null);
        session_start();
        $this->twig->addGlobal('session', $_SESSION);
        $this->strictPaths = true;
        $this->basePath = $basePath;
    }

    public function flash($message, $class='info')
    {
        $flash = new Flash($class, $message);
        $this->addGlobal('flash', $flash);
    }

    public function render($template_name, $vars=array())
    {
        // pass it to the twig context
        $template = $this->twig->loadTemplate($template_name);
        return $template->render($vars);
    }

    public function addGlobal($key, $val)
    {
        return $this->twig->addGlobal($key, $val);
    }

    public function notFound()
    {
        header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);
        if ($this->notFoundBody == '')
        {
            print "404 Not Found\n";
        } else {
            print $this->notFoundBody;
        }
    }

    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;
    }

    public function returnError()
    {
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error',
            true, 500);
        if ($this->errorBody == '')
        {
            print "500 Internal Server Error\n";
        } else {
            print $this->errorBody;
        }
    }

    public function seeother($namePath, $args=array())
    {
        try {
            $url = $this->urlFor($namePath, $args);
        } catch (\Exception $err) {
            $url = $namePath;
            $url = str_replace('//', '/', $this->basePath . $url);
        }

        header('Location: ' . $url);
        exit();
    }

    public function serve($url=null)
    {
        if ($url == null) {
            $path = $_SERVER['REQUEST_URI'];
            $parts = explode("?", $path);
            $url = $parts[0];
            $bpLen = strlen($this->basePath);
            if ($bpLen > 0 && strpos($url, $this->basePath) == 0)
                $url = substr($url, $bpLen);
            if ($url == '')
                $url = '/';
        }

        foreach($this->preprocessors as $preprocessor) {
            $parsed = parse_url($_SERVER['REQUEST_URI']);
            $preprocessor->process($this, $url);
        }

        foreach($this->subApps as $subApp) {
            if ($subApp->dispatch($url))
                return;
        }

        foreach($this->routes as $route) {
            if ($route->dispatch($url))
                return;
        }
        $this->notFound();
    }

    public function tGet($pattern, $template_name, $name=null)
    {
        $app = $this;
        $this->routes[] = new Get($this, $pattern, function($args) use ($app, $template_name) {
            return $app->render($template_name, $args);
        }, $name);
    }

    private function wrapCallback($callable)
    {
        $app = $this;
        return function($args) use ($callable, $app, $error_page, $error_args) {
            try {
                $result = $callable($args);
                return $result;
            } catch (\Exception $err) {
                if ($app->error_page == null)
                    throw $err;
                $app->flash($err->getMessage(), 'error');
                return $app->render($app->error_page, $app->error_args);
            }
        };
    }

    public function get($pattern, $callback, $name=null)
    {
        $this->routes[] = new Get($this, $pattern, $this->wrapCallback($callback), $name);
    }

    public function post($pattern, $callback, $name=null)
    {
        $this->routes[] = new Post($this, $pattern, $this->wrapCallback($callback), $name);
    }

    public function subApp($pattern, $app)
    {
        $this->subApps[] = new SubApp($this->basePath, $pattern, $app);
    }

    public function preprocess($preprocessor)
    {
        array_push($this->preprocessors, $preprocessor);
    }

    public function trapErrors($redirect_name, $args=array()) {
        $this->error_page = $redirect_name;
        $this->error_args = $args;
    }

    public function apiSimple($callable) {
        // Take an anonymous function, execute the contents, and respond with the following structure:
        // array(
        //      'result' => what the function returns,
        //      'message' => if an exception is thrown, it's captured into this
        //      );
        $message = '';
        try {
            $result = $callable();
        } catch (\Exception $err) {
            $message = $err->getMessage();
        }
        header('Content-type: application/json');
        header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
        return json_encode(
            array(
                'result' => $result,
                'message' => $message
        ));
    }

    public function urlFor($name, $args=array()) {
        if (!is_array($args))
            throw new Exception('Arguments must be an array.');
        foreach($this->routes as $route) {
            if ($route->name === $name) {
                $pattern = $route->pattern;
                foreach($args as $key => $val) {
                    if (strstr($pattern, ':' . $key) === false)
                        throw new \Exception("No pattern argument named $name.");
                    $pattern = str_replace(':' . $key, $val, $pattern);
                }
                if (strstr($pattern, ':') === ':')
                    throw new \Exception("Incorrect number or named arguments supplied.");

                return str_replace('//', '/', $this->basePath . $pattern);
            }
        }

        throw new \Exception("No route named $name.");

    }

    public function getPost() {
        $stripArray = function ($array) {
            $out = array();
            foreach($array as $key => $value) {
                if (is_array($value))
                    $out[$key] = $stripArray($value);
                else if (is_string($value))
                    $out[$key] = stripslashes($value);
                else
                    $out[$key] = $value;
            }
            return $out;
        };
        return $stripArray($_POST);
    }
}

?>
