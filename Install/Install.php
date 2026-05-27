<?php

namespace Apps\Tms\Packages\Jobs\Lrs\Install;

use Apps\Tms\Packages\Jobs\Lrs\Install\Schema\JobsLrs;
use Apps\Tms\Packages\Jobs\Lrs\Model\AppsTmsJobsLrs;
use System\Base\BasePackage;
use System\Base\Providers\ModulesServiceProvider\DbInstaller;

class Install extends BasePackage
{
    protected $databases;

    protected $dbInstaller;

    public function init()
    {
        $this->databases =
            [
                'apps_tms_jobs_lrs'  => [
                    'schema'        => new JobsLrs,
                    'model'         => new AppsTmsJobsLrs,
                    'configParams'  =>
                        [
                            'min_index_chars' => 3
                        ]
                ]
            ];

        $this->dbInstaller = new DbInstaller;

        return $this;
    }

    public function install()
    {
        $this->preInstall();

        $this->installDb();

        $this->postInstall();

        return true;
    }

    protected function preInstall()
    {
        return true;
    }

    public function installDb()
    {
        $this->dbInstaller->installDb($this->databases);

        return true;
    }

    public function postInstall()
    {
        //Do anything after installation.
        return true;
    }

    public function truncate()
    {
        $this->dbInstaller->truncate($this->databases);
    }

    public function uninstall($remove = false)
    {
        if ($remove) {
            //Check Relationship
            //Drop Table(s)
            $this->dbInstaller->uninstallDb($this->databases);
        }

        return true;
    }
}