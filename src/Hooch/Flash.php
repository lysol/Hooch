<?php
namespace Hooch;

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
