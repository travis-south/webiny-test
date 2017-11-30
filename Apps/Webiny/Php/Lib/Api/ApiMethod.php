<?php
/**
 * Webiny Platform (http://www.webiny.com/)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Apps\Webiny\Php\Lib\Api;

use Apps\Webiny\Php\Lib\WebinyTrait;
use Apps\Webiny\Php\Lib\Entity\AbstractEntity;
use Apps\Webiny\Php\Lib\Services\AbstractService;
use Apps\Webiny\Php\RequestHandlers\ApiException;
use Webiny\Component\StdLib\StdLibTrait;
use Webiny\Component\Validation\Validation;
use Webiny\Component\Validation\ValidationException;

/**
 * Class ApiMethod
 *
 * This class is used when we want to expose class or service method to the API
 */
class ApiMethod
{
    use StdLibTrait, WebinyTrait;

    private $httpMethod;
    private $pattern;
    private $crud = false;
    /**
     * @var null|AbstractEntity|AbstractService
     */
    private $context;
    private $callbacks = [];
    private $entityCallbacks = [];
    private $eventCallbacks = [];
    private $bodyValidators;
    private $public = false;
    public static $count = 0;

    function __construct($httpMethod, $methodName, $context, $callable = null)
    {
        $this->httpMethod = $httpMethod;
        $this->pattern = $methodName;
        $this->context = $context;
        if ($callable) {
            $this->callbacks = [$callable];
        }
        static::$count++;
    }

    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * Get a URL to current API method using the given $context (entity or service instance)
     *
     * @param AbstractEntity|AbstractService $context Entity or Service instance
     * @param array                          $params Array of params to replace URL placeholders with
     * @param bool                           $replace Replace URL placeholders or not
     *
     * @return string
     */
    public function getUrl($context, $params = [], $replace = true)
    {
        // Determine if this method belongs to entity or service
        $parts = $this->str(get_class($context))->explode('\\')->val();
        $app = $this->str($parts[1])->kebabCase()->val();
        if ($context instanceof AbstractEntity) {
            $contextUrl = 'entities/' . $app . '/' . $this->str($parts[4])->pluralize()->kebabCase()->val();
        } else {
            $contextUrl = 'services/' . $app . '/' . $this->str($parts[4])->kebabCase()->val();
        }

        $url = $this->str($this->pattern)->trimLeft('/');
        if ($replace) {
            foreach ($params as $k => $v) {
                $url->replace('{' . $k . '}', $v);
            }

            if ($context instanceof AbstractEntity && $url->startsWith('{id}')) {
                $url->replace('{id}', $context->id);
            }
        }


        $url = $this->str($this->wConfig()->get('Webiny.ApiUrl') . '/' . $contextUrl . '/' . $url)->trimRight('/');

        return $url->val();
    }

    public function __invoke($params, $context)
    {
        if (($this->httpMethod === 'post' || $this->httpMethod === 'patch') && count($this->bodyValidators)) {
            $this->validateBody($this->wRequest()->getRequestData());
        }

        // Sort callbacks so that callbacks registered from events are executed first
        if (count($this->callbacks) === 0) {
            foreach ($this->eventCallbacks as $cb) {
                $this->callbacks[] = $cb;
            }
            foreach ($this->entityCallbacks as $cb) {
                $this->callbacks[] = $cb;
            }
        }

        $callback = $this->callbacks[0]->bindTo($context);
        $callbackCount = count($this->callbacks);

        if (!$params) {
            $params = [];
        }
        $params = $this->injectParams($callback, $params);
        if ($callbackCount > 1) {
            $params[] = $this->createParent(1, $context);
        }

        return $callback(...$params);
    }

    public function addCallback($callable, $processingEvent = null)
    {
        if ($processingEvent === 'onExtendApi') {
            array_unshift($this->eventCallbacks, $callable);
        } else {
            array_unshift($this->entityCallbacks, $callable);
        }

        return $this;
    }

    /**
     * Set body validators
     *
     * @param array $validators
     *
     * @return $this
     */
    public function setBodyValidators(array $validators = [])
    {
        $this->bodyValidators = $validators;

        return $this;
    }

    /**
     * This method is public and does not require an API token or authorization
     *
     * @return $this
     */
    public function setPublic()
    {
        $this->public = true;

        return $this;
    }

    /**
     * Is this API method public?
     *
     * @return bool
     */
    public function getPublic()
    {
        return $this->public;
    }

    /**
     * Set CRUD state
     *
     * @param bool $crud
     *
     * @return $this
     */
    public function setCrud($crud = true)
    {
        $this->crud = $crud;

        return $this;
    }

    /**
     * Is this a CRUD method?
     *
     * @return bool
     */
    public function getCrud()
    {
        return $this->crud;
    }

    /**
     * Add validators to existing set of validators.
     * Use this if you are overriding parent API method and calling $parent()
     *
     * @param array $validators
     *
     * @return $this
     */
    public function addBodyValidators(array $validators = [])
    {
        $this->bodyValidators = array_merge($this->bodyValidators, $validators);

        return $this;
    }

    protected function injectParams($function, array $params)
    {
        $rm = new \ReflectionFunction($function);

        $methodParams = [];
        $methodRequiredParams = 0;
        /* @var $p \ReflectionParameter */
        foreach ($rm->getParameters() as $p) {
            if ($p->getName() == 'parent') {
                continue;
            }
            $pClass = $p->getClass();
            $methodParams[$p->getName()] = [
                'name'     => $p->getName(),
                'class'    => $pClass ? $pClass->getName() : null,
                'required' => !$p->allowsNull()
            ];

            if (!$p->allowsNull()) {
                $methodRequiredParams++;
            }
        }

        if (count($params) < $methodRequiredParams) {
            $missingParams = array_slice(array_keys($methodParams), count($params));
            throw new ApiException('Missing required params', 'WBY-PARAMS_INJECTOR-1', 400, $missingParams);
        }

        $index = 0;
        $injectedParams = [];
        foreach ($methodParams as $pName => $mp) {
            if ($mp['class']) {
                $requestedValue = $params[$pName];
                // If parameter class is AbstractEntity, it means we need to replace it with the actual context class
                if ($mp['class'] === AbstractEntity::class) {
                    $mp['class'] = get_class($this->context);
                }
                $paramValue = call_user_func_array([$mp['class'], 'findById'], [$requestedValue]);
                if ($mp['required'] && $paramValue === null) {
                    $data = [];
                    $data[$mp['name']] = $mp['class'] . ' with ID `' . $requestedValue . '` was not found!';
                    throw new ApiException('Invalid parameters', 'WBY-PARAMS_INJECTOR-2', 400, $data);
                }
                $injectedParams[] = $paramValue;
            } else {
                $injectedParams[] = $params[$pName];
            }

            $index++;
        }

        return $injectedParams;
    }

    /**
     * @param int                            $index Callback index
     * @param AbstractEntity|AbstractService $bindTo Instance to bind this callback to
     *
     * @return \Closure
     */
    private function createParent($index, $bindTo)
    {
        return function () use ($index, $bindTo) {
            $params = func_get_args();
            $callback = $this->callbacks[$index];
            if (count($this->callbacks) > $index + 1) {
                $params[] = $this->createParent($index + 1, $bindTo);
            }

            $callback = $callback->bindTo($bindTo);

            return $callback(...$params);
        };
    }

    private function validateBody($params)
    {
        $errors = [];

        $params = $this->arr($params);
        foreach ($this->bodyValidators as $key => $validators) {
            $keyValue = $params->keyNested($key);
            if ($this->isString($validators)) {
                $validators = explode(',', $validators);
            }

            // Do not validate if value is not required and empty value is given
            // 'empty' function is not suitable for this check here
            if (!in_array('required', $validators) && (is_null($keyValue) || $keyValue === '')) {
                continue;
            }

            try {
                Validation::getInstance()->validate($keyValue, $validators);
            } catch (ValidationException $e) {
                $errors[$key] = $e->getMessage();
            }
        }

        if (count($errors)) {
            $message = 'Invalid arguments provided to method `' . $this->pattern . '.' . $this->httpMethod . '`';
            throw new ApiMethodException($message, 'WBY-ENTITY-API-METHOD-VALIDATION', 404, $errors);
        }
    }
}
