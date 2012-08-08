<?php
namespace Hooch;

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
