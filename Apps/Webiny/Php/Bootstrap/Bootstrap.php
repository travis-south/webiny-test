<?php
/**
 * Webiny Platform (http://www.webiny.com/)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Apps\Webiny\Php\Bootstrap;

use Apps\Webiny\Php\Lib\Authorization\Authorization;
use Apps\Webiny\Php\Lib\ConfigLoader;
use Apps\Webiny\Php\Lib\I18N\I18N;
use Apps\Webiny\Php\Lib\Request;
use Apps\Webiny\Php\Lib\Response\ApiResponse;
use Apps\Webiny\Php\Lib\Response\HtmlResponse;
use Apps\Webiny\Php\Lib\Response\AbstractResponse;
use Apps\Webiny\Php\Lib\Response\ResponseEvent;
use Apps\Webiny\Php\Lib\Apps\App;
use Webiny\Component\Config\ConfigException;
use Webiny\Component\Config\ConfigObject;
use Webiny\Component\Entity\Entity;
use Webiny\Component\Http\Http;
use Webiny\Component\Http\Response;
use Webiny\Component\Mongo\Mongo;
use Webiny\Component\Security\Security;
use Webiny\Component\ServiceManager\ServiceManager;
use Webiny\Component\StdLib\StdLibTrait;
use Webiny\Component\StdLib\StdObject\UrlObject\UrlObjectException;
use Webiny\Component\StdLib\SingletonTrait;
use Apps\Webiny\Php\Lib\WebinyTrait;
use Webiny\Component\Storage\Storage;

/**
 * This class is included in the index.php and is responsible for bootstrapping the platform
 */
class Bootstrap
{
    use SingletonTrait, WebinyTrait, StdLibTrait;

    /**
     * @var ErrorHandler
     */
    private $errorHandler;
    /**
     * @var array Js configs to merge after config set is loaded completely
     */
    private $jsConfigs = [];

    protected function init()
    {
        // Load Base environment configuration
        $this->buildConfiguration('Base');

        // Load apps
        /* @var $app App */
        foreach ($this->wApps()->loadApps() as $app) {
            // Webiny app needs to be merged because of the app name key which already exists in the global config
            if ($app instanceof \Apps\Webiny\Php\App) {
                $this->wConfig()->getConfig()->mergeWith(['Webiny' => $app->getConfig()]);
                continue;
            }
            // Add app config into the global config by app name
            $this->wConfig()->getConfig()->set($app->getName(), $app->getConfig());
        }

        // Get environment-specific config
        $environment = $this->getEnvironment();
        if ($environment) {
            $this->buildConfiguration($environment);
        }

        // Append Js configs (these need to be loaded at the very end to inject proper values)
        foreach ($this->jsConfigs as $jsConfig) {
            $this->wConfig()->append($jsConfig);
        }

        // Now that we have the complete global config we need to inject variables
        ConfigLoader::getInstance()->injectVariables($this->wConfig()->getConfig());

        // set error handler
        $this->errorHandler = new ErrorHandler();

        // Register services
        foreach($this->wConfig()->get('Services') as $name => $config) {
            /* @var $config ConfigObject */
            ServiceManager::getInstance()->registerService($name, $config);
        }

        // Set component configs
        $emptyConfig = new ConfigObject();
        Mongo::setConfig($this->wConfig()->get('Mongo', $emptyConfig));
        Entity::setConfig($this->wConfig()->get('Entity', $emptyConfig));
        Security::setConfig($this->wConfig()->get('Security', $emptyConfig));
        Storage::setConfig($this->wConfig()->get('Storage', $emptyConfig));
        Http::setConfig($this->wConfig()->get('Http', $emptyConfig));


        // I18n will be loaded only if it's enabled in YAML config files.
        I18N::getInstance()->init();

        /* @var $app App */
        foreach ($this->wApps() as $app) {
            $app->bootstrap();
        }

        $this->wEvents()->fire('Webiny.Bootstrap.End');
    }

    public function run()
    {
        if ($this->wRequest()->isApi() && $this->wRequest()->header('X-Webiny-Api-Aggregate')) {
            return $this->processMultipleRequests();
        }

        /* @var $response AbstractResponse */
        $response = $this->wEvents()->fire('Webiny.Bootstrap.Request', new BootstrapEvent(), AbstractResponse::class, 1);
        if ($response) {
            if ($response instanceof ApiResponse) {
                $response->setErrors($this->errorHandler->getErrors());
                $this->errorHandler->saveErrorsToLogger();
            }
            $this->processResponse($response);
        }

        // Output 404
        $response = new HtmlResponse('Add 404 handler!', 404);
        $this->processResponse($response);
    }

    /**
     * This will read aggregated requests and execute each one of them as if they were sent individually.
     * All responses are aggregated into a single response.
     *
     * @return Response
     */
    public function processMultipleRequests()
    {
        $requests = $this->wRequest()->getRequestData()['requests'];
        $responses = [];
        $headers = [];
        foreach ($requests as $req) {
            if (count($headers)) {
                foreach (array_keys($headers) as $header) {
                    unset($_SERVER[$header]);
                }
                $headers = [];
            }

            Request::deleteInstance();

            $_GET = $req['query'];
            $_SERVER['REQUEST_URI'] = $this->url($req['url'])->getPath();
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['QUERY_STRING'] = http_build_query($req['query']);
            if (is_array($req['headers'])) {
                foreach ($req['headers'] as $header => $value) {
                    $headers['HTTP_' . $this->str($header)->replace('-', '_')->caseUpper()->val()] = $value;
                }
                $_SERVER = array_merge($_SERVER, $headers);
            }
            Request::getInstance();
            Authorization::getInstance()->reset();
            $response = $this->wEvents()->fire('Webiny.Bootstrap.Request', new BootstrapEvent(), AbstractResponse::class, 1);
            if ($response instanceof ApiResponse) {
                $responseData = $this->processResponse($response, true);
                $responseData['statusCode'] = $response->getStatusCode();
                $responses[] = $responseData;
            } else {
                $responses[] = null;
            }
        }

        $apiResponse = new ApiResponse($responses);
        $response = Response::create($apiResponse->output(), 200);

        return $response->send();
    }

    private function buildConfiguration($environment)
    {
        try {
            // get the configuration files
            $dir = $this->wStorage()->readDir('Configs/' . $environment)->filter('*.yaml');

            // insert them into the global configuration object
            foreach ($dir as &$file) {
                $key = $this->str($file->getKey());
                if ($key->endsWith('Js.yaml')) {
                    $this->jsConfigs[] = $key->val();
                    continue;
                }
                try {
                    $this->wConfig()->append($key->val());
                } catch (ConfigException $e) {
                    continue;
                }
            }

            // append config sets
            $this->wConfig()->append('Configs/Environments.yaml');
        } catch (\Exception $e) {
            throw new \Exception('Unable to build config set ' . $environment . '. ' . $e->getMessage());
        }
    }

    private function getEnvironment()
    {
        $environments = $this->wConfig()->get('Environments', []);
        try {
            $url = $this->wRequest()->getCurrentUrl(true)->getDomain();
            $currentDomain = $this->str($url)->caseLower()->trimRight('/')->val();

            $environment = false;
            foreach ($environments as $name => $domain) {
                if ($domain instanceof ConfigObject) {
                    foreach ($domain as $d) {
                        if ($currentDomain == $this->url($d)->getDomain()) {
                            $environment = $name;
                            break 2;
                        }
                    }
                } elseif ($currentDomain == $this->url($domain)->getDomain()) {
                    $environment = $name;
                    break;
                }
            }
            if (!$environment) {
                $environment = 'Production';
            }
        } catch (UrlObjectException $e) {
            $environment = 'Production';
        }

        return $environment;
    }

    /**
     * @param AbstractResponse $webinyResponse Response object to process
     * @param bool             $return Return response data
     *
     * @return mixed
     */
    private function processResponse(AbstractResponse $webinyResponse, $return = false)
    {
        $event = new ResponseEvent($webinyResponse);
        $this->wEvents()->fire('Webiny.Bootstrap.Response', $event);

        if ($return && $webinyResponse instanceof ApiResponse) {
            return $webinyResponse->getData(true);
        }

        // Build response body
        $responseBody = $webinyResponse->output();
        $response = Response::create($responseBody, $webinyResponse->getStatusCode());
        $response->cacheControl()->setCacheControl($webinyResponse->getCacheControlHeaders());
        $response->send();
    }
}