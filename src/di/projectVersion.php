<?php

return function () : string {
    return trim(file_get_contents('/VERSION'));
};