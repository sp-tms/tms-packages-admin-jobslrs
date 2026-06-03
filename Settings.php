<?php

namespace Apps\Tms\Packages\Jobs\Lrs;

use System\Base\BasePackage;

class Settings extends BasePackage
{
    public function afterUpdate($packageClass, $package, $data)
    {
        return true;
    }
}