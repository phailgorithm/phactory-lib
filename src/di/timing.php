<?php
return function() {

    if (di()->get('dev')) {
        $instance = new class extends PhalconDebug {
            public function start($key) {
                Timing::start($key);
                parent::startMeasure($key);
            }
            public function stop($key) {
                Timing::stop($key);
                parent::stopMeasure($key);
            }
            public function add($key, $start, $stop) {
                Timing::addMeasure($key, $start, $stop);
                parent::addMeasure($key, $start, $stop);
            }
            public function break($key) {

                Timing::break($key);
                parent::addMeasure($key, START_MICROTIME, microtime(true));
//                parent::addMeasurePoint($key);
            }
        };
        return $instance;
    }

    return new class {
        public function start($key) {
            Timing::start($key);
        }
        public function stop($key) {
            Timing::stop($key);
        }
        public function add($key, $start, $stop) {
            Timing::addMeasure($key, $start, $stop);
        }
        public function break($key) {
            Timing::break($key);
        }
    };
};
