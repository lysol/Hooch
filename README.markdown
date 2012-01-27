HOOCH
=====

I whipped this up for a couple of small projects outside of work and now I'm
giving it to the internet.

It's a simple microframework that lets PHP do the things it does well, which
depending on the day and the person you ask, either is or isn't much.

Use [Twig](http://twig.sensiolabs.org) with this. It's nice.

Usage:

    require_once 'Twig/Autoloader.php';
    require_once 'hooch.php';

    Twig_Autoloader::register();
    $loader = new Twig_Loader_Filesystem('templates');
    $twig = new Twig_Environment($loader, array('debug' => true,
        'strict_variables' => true));

    // Assuming ../ is a non-publicly accessible path,
    // put your DB info there.
    $config = parse_ini_file("../config.ini", false);

    // Instantiate the App class. Handles path routing and such.
    $app = new App(true);

    // Retrieve the base URL path from the config file.
    // You must set this if you want anything to work at all.
    // basePath should be equal to the base path of the URL.
    // For example, if it's http://127.0.0.1/~test/, set basePath to
    // /~test/
    $app->basePath = $config['basePath'];

    // These are things we will use in every template, more or less.
    $twig->addGlobal('basePath', $basePath);
    $twig->addGlobal('app', $app);

    // Use a Preprocessor to implement authentication, etc.
    // This is a bad example, but you get it. You're smart.
    class AuthProcessor extends Preprocessor {
        public function test($path) {
            global $authenticated;
            return $authenticated;
        }
    }
    // If you're not using a preprocessor, omit this line.
    $app->preprocess(new AuthProcessor());


    // Handle the home page. True anonymous functions require
    // PHP 5.3.0 and above.

    $app->get('/', function($args) use ($twig) {
        $template = $twig->loadTemplate('home.html');
        return $template->render(array('someVal' => true));
    });
    // Look, even PHP can have moustaches!


    // If you're on an older version of PHP:
    function second_page($args) {
        global $twig;
        $template = $twig->loadTemplate('person.html');
        return $template->render(array('person' => $args['name']));
    }

    $app->get('/person/:name', second_page);

    // Handle POST requests
    // Here's an example of a redirect as well.
    $app->post('/save', function ($args) use ($twig, $app) {
        if (isset($_POST['name'])) {
            $app->seeother('/person/' + $_POST['name']);
        } else {
            // Throw a 404.
            $app->notFound();
        }
    });

    // Need to return some JSON for an AJAX call?

    $app->post('/saveAJAX', function ($args) use ($app) {
        return $app->apiSimple(function() use ($args) {
            return array(
                'test' => 1,
                'another-test' => 2
                );
        });
    });

    // Need to dynamically reference URLs, like Flask?

    $app->get('/anotherpath/:user_id', function ($args) use ($app) {
        return 'test';
    }, 'named_path');

    /*
        In your template (if using Twig), you can reference it via the urlFor method:

        {{ app.urlFor('named_path', {'user_id': 1}) }}
    */

    // Or, in PHP:

    print $app->urlFor('named_path', array('user_id' => 1));


    // And the thing that makes it all happen:
    $app->serve();


You'll also need an .htaccess to redirect everything to your index.php file.
This one works:

    RewriteEngine on
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . index.php [L]


