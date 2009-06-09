<?php
require_once dirname(dirname(__FILE__)) . '/karinto.php';

karinto::$layout_template = 'layout.php';

karinto::route('/', 'myapp_default');
karinto::route('/foo', 'myapp_foo');

karinto::run();


function myapp_default(karinto_request $req, karinto_response $res)
{
    $res->output('This is a test page');
}

function myapp_foo(karinto_request $req, karinto_response $res)
{
    $res->message = 'Hello ' . $req->name;
    $res->render();
}

