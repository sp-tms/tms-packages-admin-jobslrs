<?php

namespace Apps\Tms\Packages\Jobs\Lrs\Model;

use Apps\Tms\Packages\Jobs\Charges\Model\AppsTmsJobsCharges;
use Apps\Tms\Packages\Jobs\Invoices\Model\AppsTmsJobsInvoices;
use Apps\Tms\Packages\Jobs\Payments\Model\AppsTmsJobsPayments;
use Apps\Tms\Packages\Jobs\Trips\Model\AppsTmsJobsTrips;
use System\Base\BaseModel;

class AppsTmsJobsLrs extends BaseModel
{
    protected $modelRelations = [];

    public $id;

    public $lr_no;

    public $financial_year;

    public $organisation_id;

    public $company_id;

    public $vehicle_id;

    public $date;

    public $from_company_id;

    public $to_company_id;

    public $from_company_address_id;

    public $to_company_address_id;

    public $status;

    public $archived;

    public $lr_dev_notes;

    public $documents;

    public $lr_notes;

    public function initialize()
    {
        $this->modelRelations['trip']['relationObj'] = $this->hasOne(
            'id',
            AppsTmsJobsTrips::class,
            'id',
            [
                'alias'                 => 'trip'
            ]
        );

        $this->modelRelations['invoice']['relationObj'] = $this->hasOne(
            'id',
            AppsTmsJobsInvoices::class,
            'id',
            [
                'alias'                 => 'invoice'
            ]
        );

        $this->modelRelations['charges']['relationObj'] = $this->hasMany(
            'id',
            AppsTmsJobsCharges::class,
            'lr_no',
            [
                'alias'                 => 'charges'
            ]
        );

        $this->modelRelations['payments']['relationObj'] = $this->hasMany(
            'id',
            AppsTmsJobsPayments::class,
            'lr_no',
            [
                'alias'                 => 'payments'
            ]
        );

        parent::initialize();
    }

    public function getModelRelations()
    {
        if (count($this->modelRelations) === 0) {
            $this->initialize();
        }

        return $this->modelRelations;
    }
}