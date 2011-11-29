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
    private $gets = array();
    private $posts = array();
    private $preprocessors = array();
    public $notFoundBody = '';
    public $errorBody = '';
    public $basePath = '';

    public function __construct($strictPaths=false)
    {
        $this->strictPaths = $strictPaths;
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

    public function get($pattern, $callback)
    {
        $this->gets[$pattern] = $callback;
    }

    public function post($pattern, $callback)
    {
        $this->posts[$pattern] = $callback;
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

}

function object_to_array($var) {
    $result = array();
    $references = array();

    // loop over elements/properties
    foreach ($var as $key => $value) {
        // recursively convert objects
        if (is_object($value) || is_array($value)) {
            // but prevent cycles
            if (!in_array($value, $references)) {
                $result[$key] = object_to_array($value);
                $references[] = $value;
            }
        } else {
            // simple values are untouched
            $result[$key] = $value;
        }
    }
    return $result;
}


?>
