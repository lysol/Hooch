HOOCH
=====

I whipped this up for a couple of small projects outside of work and now I'm
giving it to the internet.

It's a simple microframework that lets PHP do the things it does well, which
depending on the day and the person you ask, either is or isn't much.

Hooch requires Twig, a templating engine very similar to Jinja2 and Django
templates.

Usage:

    require_once 'hooch.php';

    // Instantiate the App class. Provide the base relative URL. 
    // For example, if it's http://127.0.0.1/~test/, use '/~test'
    $app = new \Hooch\App('/~test');

    // You can still access the Twig context.
    $app->twig->addGlobal('test', 1);
    // But the app, basePath, these are all available already.

    // Use a Preprocessor to implement authentication, etc.
    // This is a bad example, but you get it. You're smart.
    class AuthProcessor extends \Hooch\Preprocessor {
        public function test($app, $path) {
            global $authenticated;
            return $authenticated;
        }
    }
    // If you're not using a preprocessor, omit this line.
    $app->preprocess(new AuthProcessor());

    // Handle the home page. True anonymous functions require
    // PHP 5.3.0 and above.

    $app->get('/', function($args) use ($app) {
        return $app->render('home.html');
        $template = $twig->loadTemplate('home.html', array('someVal' => true));
    });
    // Look, even PHP can have moustaches!


    // If you're on an older version of PHP:
    function second_page($args) {
        global $app;
        return $app->render('second_page.html');
    }

    // $args will be: array('name' => :name)
    // The last argument after the callback to any route is the route name,
    // which can be used to automatically build URLs.
    $app->get('/person/:name', second_page, 'second_page');

    // Handle POST requests
    // Here's an example of a redirect as well.
    $app->post('/save', function ($args) use ($app) {
        // Use this to automatically stripslashes.
        // You don't have to!
        $form = $app->getPost();

        if (isset($form['name'])) {
            // Requires the route name, which you defined above.
            $app->seeother('person', array('name' => $form['name']));
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
    }, 'save_ajax');

    // Need to dynamically reference URLs, like Flask?
    $app->get('/anotherpath/:user_id', function ($args) use ($app) {
        return 'test';
    }, 'named_path');

    /*
        In your template, you can reference it via the urlFor method:

        {{ app.urlFor('named_path', {'user_id': 1}) }}
    */

    // Or, in PHP:
    print $app->urlFor('named_path', array('user_id' => 1));



    // Finally, if you just need to render a template without any extra
    // variables:
    $app->tGet('/top10', 'top10.html', 'top10');


    // And the thing that makes it all happen:
    $app->serve();


You'll also need an .htaccess to redirect everything to your index.php file.
This one works:

    RewriteEngine on
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . index.php [L]


