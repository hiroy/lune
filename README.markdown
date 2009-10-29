# Lune - a PHP minimal framework

## Required

* PHP 5.1 or higher
* mbstring

## Usage

/index.php

    <?php
    require_once 'Lune.php';
    
    Lune::route('/', 'myapp_default');
    function myapp_default(Lune_Request $req, Lune_Response $res)
    {
        $res->output('This is a test page');
    }
    
    Lune::route('/foo', 'myapp_foo');
    function myapp_foo(Lune_Request $req, Lune_Response $res)
    {
        $res->contentTypeHtml('UTF-8');
        $res->message = 'Hello ' . $req->name;
        $res->render();   // equals to $res->render('myapp_foo.php');
    }
    
    // PHP version >= 5.3.0
    Lune::route('/bar', function (Lune_Request $req, Lune_Response $res) {
        $res->render('myapp_foo.php');
    });

    Lune::run();

/templates/myapp_foo.php

    <html>
    <body>
    <p><?php echo $message; ?></p>
    </body>
    </html>

Please access "/index.php/foo?name=bar"

## License

This code is free to use under the terms of the New BSD License.

