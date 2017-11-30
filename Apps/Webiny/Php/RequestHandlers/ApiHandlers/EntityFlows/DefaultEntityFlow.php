<?php
/**
 * Webiny Platform (http://www.webiny.com/)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Apps\Webiny\Php\RequestHandlers\ApiHandlers\EntityFlows;

use Apps\Webiny\Php\Lib\Entity\AbstractEntity;
use Apps\Webiny\Php\Lib\Exceptions\AppException;
use Apps\Webiny\Php\RequestHandlers\ApiException;
use Webiny\Component\Entity\Attribute\Validation\ValidationException;
use Webiny\Component\Entity\EntityException;
use Webiny\Component\StdLib\Exception\AbstractException;

/**
 * Class DefaultEntityFlow
 */
class DefaultEntityFlow extends AbstractEntityFlow
{
    public function handle(AbstractEntity $entity, $params)
    {
        $httpMethod = strtolower($this->wRequest()->getRequestMethod());
        $matchedMethod = $entity->getApi()->matchMethod($httpMethod, join('/', $params));

        if (!$matchedMethod) {
            $message = 'No method matched the requested URL in ' . get_class($entity);
            throw new ApiException($message, 'WBY-ED-EXECUTE_METHOD_FLOW-3');
        }

        $apiMethod = $matchedMethod->getApiMethod();
        $params = $matchedMethod->getParams();

        $pattern = $apiMethod->getPattern() . '.' . $httpMethod;
        if (!$apiMethod->getPublic() && !$this->wAuth()->canExecute($entity, $pattern)) {
            $message = 'You are not authorized to execute ' . strtoupper($httpMethod) . ':' . $apiMethod->getPattern() . ' on ' . $entity::getClassId();
            $code = $this->wAuth()->hasTokenExpired() ? 'WBY-AUTH-TOKEN-EXPIRED' : 'WBY-AUTHORIZATION';
            throw new ApiException($message, $code, 401);
        }

        $id = $params['id'] ?? null;

        if ($id) {
            $entity = $entity->findById($id);
            if (!$entity) {
                throw new ApiException(get_class($entity) . ' with id `' . $id . '` was not found!', 'WBY-ED-EXECUTE_METHOD_FLOW-2');
            }
        }

        $code = 'WBY-ED-EXECUTE_METHOD_FLOW';
        try {
            return $apiMethod($params, $entity);
        } catch (ApiException $e) {
            throw $e;
        } catch (AppException $e) {
            throw $e;
        } catch (ValidationException $e) {
            throw new ApiException($e->getMessage(), $code, 400, iterator_to_array($e->getIterator()));
        } catch (EntityException $e) {
            throw new ApiException($e->getMessage(), $code, 400, $e->getInvalidAttributes());
        } catch (AbstractException $e) {
            throw new ApiException($e->getMessage(), $code, 400);
        }
    }

    public function canHandle($httpMethod, $params)
    {
        return true;
    }
}