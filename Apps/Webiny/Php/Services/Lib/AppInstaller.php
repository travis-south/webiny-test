<?php

namespace Apps\Webiny\Php\Services\Lib;

use Apps\Webiny\Php\Entities\User;
use Apps\Webiny\Php\Entities\UserRole;
use Apps\Webiny\Php\Lib\Exceptions\AppException;
use Apps\Webiny\Php\Lib\WebinyTrait;
use Composer\Semver\Semver;
use Webiny\Component\StdLib\StdLibTrait;

class AppInstaller
{
    use WebinyTrait, StdLibTrait;

    private $appData;

    public function __construct($appData)
    {
        $this->appData = $appData;
    }

    /**
     * Check app requirements
     *
     * @throws AppException
     */
    public function checkRequirements()
    {
        // If we are trying to install Webiny itself, there will be no extra requirements
        if ($this->appData['localName'] !== 'Webiny') {
            // Each app requires a certain version of Webiny
            $webinyVersion = $this->wApps()->getApp('Webiny')->getVersion();
            $requiredVersion = $this->appData['webinyVersion'];
            if (!Semver::satisfies($webinyVersion, $requiredVersion)) {
                throw new AppException('This app requires Webiny ' . $requiredVersion . '. Please update Webiny first.');
            }
        }

        return $this;
    }

    public function install()
    {
        $appData = $this->appData;
        $cliPath = $this->wCli()->getUrl();

        $curl = new \Curl\Curl();
        $curl->setTimeout(0);
        $curl->setOpt(CURLOPT_WRITEFUNCTION, function ($curl, $data) {
            $chunks = array_filter(explode('_-_', $data));

            foreach ($chunks as $chunk) {
                $res = json_decode($chunk, true);
                $this->echo($res);
                if (isset($res['error'])) {
                    // Throw to abort installation
                    throw new AppException($res['error']);
                }
            }

            return strlen($data);
        });

        // Pick only the necessary data to send to CLI
        $cliData = ['action' => 'install-app'];
        $keys = ['id', 'name', 'localName', 'packagist', 'repository', 'version', 'webinyVersion'];
        foreach ($keys as $key) {
            $cliData[$key] = $appData[$key];
        }

        $curl->get($cliPath, $cliData);
        $this->echo(['message' => 'Finalizing...']);

        // If we got this far it means everything is ok and now we need to assign admin roles
        $app = $this->wApps()->loadApp($appData['localName']);

        /* @var $user User */
        $user = $this->wAuth()->getUser();
        foreach ($app->getUserRoles() as $role) {
            if ($role['isAdminRole'] ?? false) {
                $user->roles[] = UserRole::findOne(['slug' => $role['slug']]);
            }
        }

        // Save user and send new user roles to the client
        if ($user->save()) {
            $this->echo($user->toArray('roles.slug'));
        }

        return true;
    }

    private function echo ($data)
    {
        $data['id'] = uniqid();
        echo json_encode($data) . "_-_";
        flush();
    }
}