<?php
require_once dirname(dirname(__FILE__)) . '/karinto.php';

karinto::$input_encoding = 'UTF-8';
karinto::$output_encoding = 'UTF-8';
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
    $res->content_type_html('UTF-8');
    $res->message = 'Hello ' . $req->name;
    $res->render();
}

