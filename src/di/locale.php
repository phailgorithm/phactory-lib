<?php
return function () {
    return $_ENV['PROJECT_LOCALE'] ?? 'en';
};