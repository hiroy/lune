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

// >= PHP 5.3
karinto::dispatch('/foo', function ($req, $res) {
    $res->content_type_html('UTF-8');
    $res->message = 'Hello ' . $req->name;
    $res->render('foo.php');
});

karinto::run();

