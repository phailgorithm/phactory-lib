<?php

if (isset($_GET['domain']) && isset($_ENV['ENABLE_COOKIE_DOMAIN'])) {
    setcookie('domain', $_GET['domain']);
    header('Location: /');
    die;
}

if ( ! (php_sapi_name() == 'cli' && isset($_ENV['PROJECT_CODE']))) {

    $config = sprintf('%s/%s.json',
        PRECONFIG_PATH,
        di()->getHttphost()
    );
    if (!file_exists($config)) {
        throw new Core\Exception\NotFound("Preconfig not found: ${config}");
    }

    # Loads json config data based on domain name as env variables
    # CODE, UMAMI_ID, UMAMI_HASH, SENTRY_DSN_BACKEND, SENTRY_DSN_FRONTEND
    $data = json_decode(file_get_contents($config), true);
    foreach ($data as $k => $v) {
        $_ENV['PROJECT_' . strtoupper($k)] = $v;
    }
}

return function () : string {
    return $_ENV['PROJECT_CODE'];
};
