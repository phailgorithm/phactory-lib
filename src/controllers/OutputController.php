<?php namespace Phailgorithm\PhactoryLib;

use Timing;

use Phailgorithm\PhactoryLib\Core\Exception\NotFound;
use Illuminate\Support\Collection;
use Phalcon\Mvc\Controller;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Router;
use Phalcon\Http\Response;

/**
 * All controllers funneling to OutputController to set final stages data handling.
 * View or raw output (depending on parent controller enabling of view), metrics and default variables
 */
class OutputController extends Controller {

    private $localroute, $initiator;

    const COMPONENTS = [
        'querystring',
        'tracking',
        'umami'
    ];

    protected $lastError = null;

    protected $outputAddMetadata = true;

    protected $xGrace,
              $xTtl;

    protected $actionReturn;
    protected $route;

    private $tests = array();


    protected $components;

    protected function loadComponents(array $components = null ) {
        if (!$this->components) {
            $this->components = new Collection();
        }
        if (is_null($components)) {
            $components = array_merge(self::COMPONENTS, static::COMPONENTS);
        }

        foreach ($components as $c) {
            if (!isset($this->components[$c])) {
                try {
                    $this->components[$c] = call_user_func([ $this, sprintf('get%sComponent', ucfirst($c)) ]);
                } catch (\Throwable $e) {
                    $this->components[$c] = null;
                    // d($e);
                }
            }
        }
    }



    /**
     * Checks if the request is authorized to being sent as json
     *
     * @return bool
     */
    private function isAuth() : bool {
        return
            (ENV != 'frontend' && isset($_GET['jsonOutput']))
            ||
            ( isset($_ENV['JSON_ENABLED_PER_REQUEST']) && !!$_ENV['JSON_ENABLED_PER_REQUEST'] && isset($_GET['jsonOutput']))
        ;
    }

    /**
     * Function to check if output can be sent as json. Exteneded by other controllers
     *
     * @return bool
     */
    protected function isJsonOutputAllowed() : bool {
        return $this->isAuth();
    }


    protected function getRouteName() : string {
        return $this->route ?: di()->getRouter()->getMatchedRoute()->getName();
    }


    /**
     * First call before any other controller
     */
    public function beforeExecuteRoute(Dispatcher $dispatcher) {
        di()->getTiming()->start('controller');

        if ($this instanceOf Antibotable) {
            # Check for ban..
            if (false) {
                $this->dispatcher->forward([
                    'namespace' => 'Core',
                    'controller' => 'Error',
                    'action' => 'forbidden'
                ]);
            }
        }

        $this->route = $this->initiator = $this->getRouteName();
        $f = explode('.', $this->route);
        array_shift($f);
        $this->localroute = implode('.', $f);

        $edgecache = conf()->cache->routing->get($this->route);

        if ($edgecache) {
            $this->xTtl = $edgecache->get('ttl', conf()->cache->get('defaultTtl', null));
            $this->xGrace = $edgecache->get('grace', conf()->cache->get('defaultGrace', null));
            debug("TTL: {$this->xTtl}");
            debug("Grace: {$this->xGrace}");
        }


        $this->cookies->useEncryption(false);

        if ($this instanceOf Abtestable) {
            if (preg_match_all('/([A-Za-z0-9]+)=([A-Za-z0-9]+)/', $_SERVER['HTTP_X_ABTESTHASH'] , $tests, PREG_SET_ORDER)) {
                foreach ($tests as $t) {
                    $this->tests[ strtolower($t[1]) ] = $t[2];
                }
            }
            foreach ($_GET as $k => $v) {
                if (substr($k, 0, 7) == 'abtest_') {
                    $this->tests[ strtolower(substr($k, 7)) ] = $v;
                }
            }
        }
    }

    /**
     * Last call after any other controller
     */
    public function afterExecuteRoute(Dispatcher $dispatcher) {
        $this->loadComponents();


        if ($this instanceOf Abtestable) {
            foreach ($this->tests as $testName => $testValue) {
                $ck = getenv('ABTEST_COOKIE_PREFIX') .strtolower($testName);
                $this->cookies->set($ck, $testValue, time() + 60*60*24*365);
            }
            $this->view->setVar(Abtestable::class, $this->tests);
        }


        di()->getTiming()->stop('controller');

        $timings = Timing::getTimings($round = 3, $slowThreshold = -INF, function($vs) {
            arsort($vs);
            return $vs; //array_slice($vs, 0 ,3 );
        });
        // d($timings);
        $slowest = array_key_first($timings);
        $performance = array(
            'handler' => $this->route,
            'initiator' => $this->initiator,
            'slowest' => [
                'name' => $slowest,
                's' => $timings[$slowest]
            ]
        );
        if (!is_null($this->lastError)) {
            $performance['err'] = $this->lastError;
        }

        if ($this->xTtl) {
            $this->response->setHeader('X-PH-VC-TTL', $this->xTtl);
        }
        if ($this->xGrace) {
            $this->response->setHeader('X-PH-VC-Grace', $this->xGrace);
        }

        $this->response->setHeader('X-Metrics', json_encode($performance));

        if ($this->di->has('project')) {
            $this->response->setHeader('X-Project', $this->di->getProject());
            $this->response->setHeader('X-ProjectVersion', $this->di->getProjectVersion());
        }

        $this->actionReturn = $dispatcher->getReturnedValue();

        if ($this->outputAddMetadata) {
            if ($this->di->has('i18n')) {
                $this->view->setVar('i18n', $this->di->getI18n()->toArray());
            }

            if ($this->di->has('config') && conf()->get('envsInView', false)) {
                $this->view->setVar('headers', $_SERVER);
                $this->view->setVar('env', $_ENV);
            }

            $this->view->setVars(array(
                'VERSION' => VERSION,
                'ENV'     => ENV,
                'HOST'    => $_SERVER['HTTP_HOST'],

                'view'    => $this->actionReturn,
                'components' => $this->components->toArray(),

                'name'    => $this->route,
                'globals' => [
                    'dev' => $this->di->get('dev')
                ],
                // 'config'  => $this->di->has('config') ? $this->di->getConfig()->toArray() : [],
                'project' => array_merge([
                    'code' => $this->di->has('project') ? $this->di->getProject() : null,
                    'version' => $this->di->has('project') && !di()->get('dev') ? $this->di->getProjectVersion() : ENV
                ],$this->di->has('config') ? $this->di->getConfig()->project->toArray() : []),

                'website' => [
                    'envs' => $GLOBALS['DOMAIN_ENVS'],
                    'domain' => di()->getDomain(),
                    'gtm_id' => $_ENV['PROJECT_GTM_ID'],
                    'gtag_id' => $_ENV['PROJECT_GTAG_ID'],
                    'adsense_id' => $_ENV['PROJECT_ADSENSE_ID'],
                    'config' => $_ENV['PROJECT_CONFIG'],
                    'google_site_verification' => $_ENV['PROJECT_GOOGLE_SITE_VERIFICATION']
                ],
            ));
        } else {
            $this->view->setVars($this->actionReturn);
        }

        # @IMPORTANT to use the proper response object - when a previous controller returned a Response object, default behavior does not apply
        if (! ($dispatcher->getReturnedValue() instanceOf Response)) {

            if ($this->isJsonOutputAllowed()) {
                $this->view->disable();
                $viewParams = $this->view->getParamsToView();

                array_walk_recursive($viewParams, function(&$var, $key) {
                    if ($var === INF) {
                        $var = "+∞";
                    } elseif ($var === -INF) {
                        $var = "-∞";
                    }
                });
            }

            debug('cache-metrics',Cache::getMetrics());
            debug('localcache-metrics',Localcache::getMetrics());

            if ($this->view->isDisabled()) {

                if ($this->isJsonOutputAllowed() ) {

                    if (isset($_GET['i18n']) && !empty($_ENV['DEV_DEBUG_ENABLED'])) {
                        foreach (array_dot($viewParams['i18n']) as $k => $v) {
                            printf("INSERT INTO i18n (project, key, %s) VALUES ((SELECT id FROM project WHERE code = '%s'), '%s', '%s');\n",
                                di()->getLocale(),
                                di()->getProject(),
                                $k,
                                str_replace("'","''", $v)
                            );
                        }
                        die;

                    } else {
                        $this->view->setVars(
                            isset($_GET['dot']) ? array_dot($viewParams) : $viewParams,
                            false
                        );
                    }
                }


                $this->response->setJsonContent( $this->view->getParamsToView(), JSON_PRETTY_PRINT ) ;
                // $this->response->setContent( json_encode($this->view->getParamsToView(), JSON_PRETTY_PRINT )) ;
            } elseif ($this->view->getContent()) {
                $this->response->setContent( $this->view->getContent() );
            } else {
                if (!$this->view->getMainView() ) {
                    $f = explode('.', $this->route);
                    array_shift($f);
                    $file = implode('/', $f);
                    $view = $this->view->getViewsDir() . $file . '.twig';
                    if (!file_exists($view)) {
                        throw new NotFound("View doesnt exist: $view");
                    }
                    $this->view->setMainView($file);
                }
                $this->view->start();

                try {
                    $this->view->render( $this->view->getMainView(), null );
                } catch (\Exception $e) {
                    throw $e;
                }

                $this->view->finish();
                $this->response->setContent( $this->view->getContent() );
            }

            if ($this->request->getMethod() == 'HEAD') {
                $this->response->setContent('');
            }


            $dispatcher->setReturnedValue($this->response);
            // return $this->response;
        }
    }

    protected function getQuerystringComponent() : Collection {
        return new Collection($_GET);
    }

    protected function getUmamiComponent() : ?Collection {
        if ($this->di->has('config')) {
            return new Collection([
                'file' => '_base/components/javascript/umami.twig',
                'data' => $this->di->getConfig()->project->umami->toArray()
            ]);
        }
        return null;
    }

    protected function getTrackingComponent() : ?Collection {
        return new Collection([
            'file' => '_base/components/javascript/tracking.twig',
            'data' => [
                'url' => '/core/trail', # @TODO - dynamic
                'pagedata' => array_filter([
                    'src' => $this->request->get('src'),
                    'route' => $this->localroute
                ])
            ]
        ]);
    }

    protected function getJsonQuerystringComponent() : Collection {
        return new Collection(
            !empty($_GET['json']) ? json_decode($_GET['json'], true)
            :
            []
        );
    }



}