<?php

return function () : string {
    $file = $this->getProject() != 'base' ? sprintf('%s/%s/VERSION', PHACTORY_PATH, $this->getProject()) : '/VERSION';
    return trim(file_get_contents($file));
};