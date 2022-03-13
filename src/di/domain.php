<?php

return function() : string {
    // if (!isset($_ENV['PROJECT_DOMAIN'])) {
    //     return ''; //throw new Exception("Missing domain");
    // }
    return $_ENV['PROJECT_DOMAIN'] ?? '';
};