<?php
/**
 * Webiny Platform (http://www.webiny.com/)
 *
 * @copyright Copyright Webiny LTD
 */

namespace Apps\Webiny\Php\Lib;

use Apps\Webiny\Php\Bootstrap\Bootstrap;
use Webiny\Component\Config\ConfigException;
use Webiny\Component\StdLib\StdLibTrait;

if (php_sapi_name() !== 'cli') {
    die('CLI scripts can only be executed from CLI context!');
}

abstract class AbstractCli
{
    use StdLibTrait, WebinyTrait;

    final public function __construct($domain = null)
    {
        if (!$domain || $domain === 'Local') {
            try {
                $config = $this->wConfig()->yaml('Configs/Local/Webiny.yaml');
                $domain = $config->get('Webiny.WebUrl');
            } catch (ConfigException $e) {
                // In case no Local config set is present we abort execution
                die('[ERROR]: CLI script must be invoked with a domain name or a `Local` config set present in your Configs folder.');
            }
        }

        $domain = $this->url($domain);
        $_SERVER['SERVER_NAME'] = $domain->getHost();
        if ($domain->getScheme() === 'https') {
            $_SERVER['HTTPS'] = true;
        }
        Bootstrap::getInstance();
    }
}