<?php

namespace Tygh\Addons\Newsman;

use Tygh\Addons\InstallerInterface;
use Tygh\Core\ApplicationInterface;
use Tygh\Settings;

class Installer implements InstallerInterface
{
    /** @var ApplicationInterface */
    protected $application;

    /**
     * @param ApplicationInterface $app
     */
    public function __construct(ApplicationInterface $app)
    {
        $this->application = $app;
    }

    /**
     * @param ApplicationInterface $app
     * @return static
     */
    public static function factory(ApplicationInterface $app)
    {
        return new static($app);
    }

    public function onBeforeInstall()
    {
    }

    public function onInstall()
    {
        $token = md5(uniqid('newsman_', true));
        Settings::instance()->updateValue('authenticate_token', $token, 'newsman');
    }

    public function onUninstall()
    {
    }
}
