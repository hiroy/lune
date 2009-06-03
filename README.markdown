# karinto - MONOGUSA web application framework

## Required

* PHP 5.1 or higher
* mbstring

## Usage

/index.php

    <?php
    require_once 'karinto.php';
    karinto::run();
    
    // GET /
    function get_(karinto_request $req, karinto_response $res)
    {
        $res->output('This is a test page.');
    }
    
    // GET /foo
    function get_foo(karinto_request $req, karinto_response $res)
    {
        $res->message = 'Hello ' . $req->name;
        $res->render();
    }

/templates/myapp_foo.php

    <html>
    <body>
    <?php echo $message; ?>
    </body>
    </html>

Please access "/index.php/foo?name=bar"

## License

This code is free to use under the terms of the New BSD License.

