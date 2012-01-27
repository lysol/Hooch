<?php


class Preprocessor
{
    public function __construct()
    {
    }

    public function fail()
    {

    }

    public function success()
    {

    }

    public function process($path)
    {
        if (!($this->test($path))) {
            $this->fail($path);
        } else {
            $this->success($path);
        }
    }

    public function test($path)
    {

    }
}


class App
{
    private $namedRoutes = array();
    private $gets = array();
    private $posts = array();
    private $preprocessors = array();
    public $notFoundBody = '';
    public $errorBody = '';
    public $basePath = '';

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

    private function buildRoutePattern($pattern)
    {
        $pattern = preg_quote($pattern, '/');
        $pattern = preg_replace('/\\\:([a-zA-Z0-9_]+)/', '(?P<$1>[^\/]+)', $pattern);
        // only match full paths if this is set.
        if ($this->strictPaths)
        {
            // If the last char is a slash, make it optional.
            if (substr($pattern, -1, 1) == '/')
                $pattern = sprintf("^%s$", $pattern . '?');
            else    
                $pattern = sprintf("^%s$", $pattern);
        }
        return "/" . $pattern . "/";
    }

    public function seeother($path)
    {
        $newpath = str_replace('//', '/', $this->basePath . $path);
        header('Location: ' . $newpath);
    }

    public function serve()
    {
        $parts = explode("?", $_SERVER['REQUEST_URI']);
        $url = $parts[0];
        $bpLen = strlen($this->basePath);
        if ($bpLen > 0 && strpos($url, $this->basePath) == 0)
            $url = substr($url, $bpLen - 1);
        if ($url == '')
            $url = '/';

        foreach($this->preprocessors as $preprocessor) {
            $parsed = parse_url($_SERVER['REQUEST_URI']);
            $preprocessor->process($parsed['path']);
        }

        switch ($_SERVER['REQUEST_METHOD'])
        {
            case 'GET':
                $tRoutes = $this->gets;
                break;
            case 'POST':
                $tRoutes = $this->posts;
                break;
            default:
                $this->returnError();
                return;
        }

        foreach($tRoutes as $targetPattern => $callback)
        {
            $targetPattern = $this->buildRoutePattern($targetPattern);
            if (preg_match($targetPattern, $url, $matches))
            {
                $argnames = array_filter(array_keys($matches), 'is_string');
                $args = array();
                foreach ($argnames as $arg)
                {
                    $args[$arg] = $matches[$arg];
                }
                $result = $callback($args);
                if (is_string($result))
                    print $result;
                return;
            }
        }
        $this->notFound();
    }

    public function get($pattern, $callback, $name=null)
    {
        $this->gets[$pattern] = $callback;
        if ($name != null)
            $this->namedPatterns[$name] = $pattern;
    }

    public function post($pattern, $callback, $name=null)
    {
        $this->posts[$pattern] = $callback;
        if ($name != null)
            $this->namedPatterns[$name] = $pattern;
    }

    public function preprocess($preprocessor)
    {
        array_push($this->preprocessors, $preprocessor);
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
        } catch (Exception $err) {
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
        if (!isset($this->namedPatterns[$name]))
            throw new Exception("No pattern named $name");
        $pattern = $this->namedPatterns[$name];
        foreach($args as $key => $val) {
            if (strstr($pattern, ':' . $key) === false)
                throw new Exception("No pattern argument named $name.");
            $pattern = str_replace(':' . $key, $val, $pattern);
        }
        if (strstr($pattern, ':') === ':')
            throw new Exception("Incorrect number or named arguments supplied.");

        return $pattern;
    }

    public function getPost() {
        function stripArray($array) {
            $out = array();
            foreach($array as $key => $value) {
                if (is_array($value))
                    $out[$key] = stripArray($value);
                else if (is_string($value))
                    $out[$key] = stripslashes($value);
                else
                    $out[$key] = $value;
            }
            return $out;
        }
        return stripArray($_POST);
    }
}

?>
