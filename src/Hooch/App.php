<?php
namespace Hooch;

class App
{
    private $routes = array();
    private $subApps = array();
    private $preprocessors = array();
    public $notFoundBody = '';
    public $errorBody = '';
    public $basePath = '';
    private $strictPaths = true;
    public $twig;
    public $error_page = null;
    public $error_args = array();
    public $debug = false;
    private $_flash;
    private $_routeGlobals;

    public $trap = true;

    public function __construct($basePath='/', $twig=null, $loader=null)
    {
        \Twig_Autoloader::register();
        if ($loader == null)
            $loader = new \Twig_Loader_Filesystem(__DIR__);
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
        $this->twig->addGlobal('flash', new FlashDirector());
        session_start();
        $this->twig->addGlobal('session', $_SESSION);
        $this->twig->addGlobal('server', $_SERVER);
        $this->basePath = $basePath;
    }

    public function flash($message, $class='info')
    {
        $flash = new Flash($class, $message);
        if (!isset($_SESSION['flash']))
            $_SESSION['flash'] = array();
        $_SESSION['flash'][] = $flash;
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
        if (!$this->trap)
            return $callable;
        $app = $this;
        $routeGlobals = $this->_routeGlobals;
        return function($args) use ($callable, $app, $routeGlobals) {
            foreach($routeGlobals as $k => $v)
                $app->twig->addGlobal($k, $v);
            try {
                $result = $callable($args);
                return $result;
            } catch (\Exception $err) {
                if ($app->error_page == null)
                    throw $err;
                $message = $err->getMessage();
                $app->flash($err->getMessage(), 'error');
                return $app->render($app->error_page, $app->error_args);
            }
        };
    }

    public function setRouteGlobals($inArray)
    {
        $this->_routeGlobals = $inArray;
    }

    public function clearRouteGlobals()
    {
        $this->_routeGlobals = array();
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
            throw new \Exception('Arguments must be an array.');
        foreach($this->routes as $route) {
            if ($route->name === $name) {
                $pattern = $route->pattern;
                foreach($args as $key => $val) {
                    if (strstr($pattern, ':' . $key) === false)
                        throw new \Exception("No pattern argument named $key.");
                    $pattern = str_replace(':' . $key, $val, $pattern);
                }
                if (strstr($pattern, ':') === ':')
                    throw new \Exception("Incorrect number or named arguments supplied.");

                return str_replace('//', '/', $this->basePath . $pattern);
            }
        }

        throw new \Exception("No route named $name.");

    }

    public function getPost($array=null) {
        if ($array == null)
            $array = $_POST;
        $out = array();
        foreach($array as $key => $value) {
            if (is_array($value))
                $out[$key] = $this->getPost($value);
            else if (is_string($value))
                $out[$key] = stripslashes($value);
            else
                $out[$key] = $value;
        }
        return $out;
    }
}
