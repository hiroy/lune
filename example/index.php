<?php
require_once dirname(dirname(__FILE__)) . '/karinto.php';

karinto::$input_encoding = 'UTF-8';
karinto::$output_encoding = 'UTF-8';
karinto::$layout_template = 'layout.php';

karinto::dispatch('/', 'myapp_default');
function myapp_default(karinto_request $req, karinto_response $res)
{
    $res->output('This is a test page');
}

karinto::dispatch('/foo', 'myapp_foo');
function myapp_foo(karinto_request $req, karinto_response $res)
{
    $res->content_type_html('UTF-8');
    $res->message = 'Hello ' . $req->name;
    $res->render();
}

// PHP version >= 5.3.0
karinto::dispatch('/bar', function (karinto_request $req, karinto_response $res) {
    $res->render('myapp_bar.php');
});

karinto::run();

