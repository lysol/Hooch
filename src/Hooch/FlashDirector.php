<?php
namespace Hooch;

class FlashDirector // Haha!
{
    public function render($htmlFormat=null)
    {
        $payload = '';
        if (isset($_SESSION['flash']) && is_array($_SESSION['flash']))
            foreach($_SESSION['flash'] as $flash)
                $payload .= $flash->render($htmlFormat);
        unset($_SESSION['flash']);
        return $payload;
    }

    public function __invoke()
    {
        return count($_SESSION['flash']) > 0;
    }
}
