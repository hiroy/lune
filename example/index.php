<?php
require_once dirname(dirname(__FILE__)) . '/Lune.php';

Lune::$layoutTemplate = 'layout.php';
Lune::$notFoundCallback = 'myapp_404';

function myapp_404(Lune_Request $req, Lune_Response $res)
{
    $res->render(); // equals to $res->render('myapp_404.php');
}

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
    $res->render('myapp_bar.php');
});

Lune::run();
