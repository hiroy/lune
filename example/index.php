<?php
require_once dirname(dirname(__FILE__)) . '/karinto.php';

karinto::$function_dir = 'functions';
karinto::$input_encoding = 'UTF-8';
karinto::$output_encoding = 'UTF-8';
karinto::$layout_template = 'layout.php';

karinto::run();


function get_(karinto_request $req, karinto_response $res)
{
    $res->output('This is a test page');
}

function get_foo(karinto_request $req, karinto_response $res)
{
    $res->content_type_html('UTF-8');
    $res->message = 'Hello ' . $req->name;
    $res->render();
}

