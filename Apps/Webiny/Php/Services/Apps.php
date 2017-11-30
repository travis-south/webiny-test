<?php
namespace Apps\Webiny\Php\Services;

use Apps\Webiny\Php\Lib\Api\ApiContainer;
use Apps\Webiny\Php\Lib\Apps\App;
use Apps\Webiny\Php\Lib\Exceptions\AppException;
use Apps\Webiny\Php\Lib\Services\AbstractService;

/**
 * Class Apps
 *
 * This service provides meta data about every app
 */
class Apps extends AbstractService
{
    protected static $classId = 'Webiny.Services.Apps';
    protected static $i18nNamespace = 'Webiny.Services.Apps';

    protected function serviceApi(ApiContainer $api)
    {
        /**
         * @api.name Get all active apps meta
         * @api.description This method returns a list of meta data for each active app
         */
        $api->get('/', function () {
            return $this->getAppsMeta();
        })->setPublic();

        /**
         * @api.name Lists all installed apps
         * @api.description Lists all installed apps
         */
        $api->get('/installed', function () {
            $list = [];
            foreach ($this->wApps() as $appObj) {
                /* @var App $appObj */
                $list[] = [
                    'version' => $appObj->getVersion(),
                    'name'    => $appObj->getName(),
                ];
            }

            return $list;
        });

        /**
         * @api.name Get single app/spa meta
         * @api.description This method returns a set of meta data for given app name or all backend apps (if {appName} == "backend")
         */
        $api->get('{appName}', function ($appName = null) {
            if ($appName === 'backend') {
                // Get Backend apps
                $apps = [];
                foreach ($this->getAppsMeta() as $meta) {
                    if ($this->str($meta['name'])->endsWith('.Backend') && $meta['name'] != 'Webiny.Backend') {
                        $apps[] = $meta;
                    }
                }

                return $apps;
            }

            return $this->getAppsMeta($appName);
        })->setPublic();
    }


    /**
     * Get apps meta
     *
     * @param null $app
     *
     * @return array|mixed
     * @throws AppException
     */
    public function getAppsMeta($app = null)
    {
        $assets = [];
        /* @var $appObj App */
        if (!$app) {
            foreach ($this->wApps() as $appObj) {
                $assets = array_merge($assets, $appObj->getBuildMeta());
            }

            return $assets;
        }

        $parts = explode('.', $app);
        $appObj = $this->wApps($parts[0]);
        if ($appObj) {
            if (isset($parts[1])) {
                return $appObj->getBuildMeta($parts[1]);
            }

            return $appObj->getBuildMeta();
        }

        return $assets;
    }
}