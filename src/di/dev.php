<?php
return function() : bool {
	return  in_array($_ENV['ENV'], ['dev','staging', 'testing']) && !empty($_ENV['DEV_DEBUG_ENABLED']);
};
