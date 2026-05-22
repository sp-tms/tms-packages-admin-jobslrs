<?php

namespace Apps\Tms\Packages\Billing\Lrs\Model;

use System\Base\BaseModel;

class AppsTmsBillingLrs extends BaseModel
{
    public $id;

    public $organisation_id;

    public $vehicle_id;

    public $date;

    public $from;

    public $to;

    public $from_company_id;

    public $to_company_id;

    public $from_company_address_id;

    public $to_company_address_id;
}