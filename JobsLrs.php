<?php

namespace Apps\Tms\Packages\Jobs\Lrs;

use Apps\Tms\Packages\Jobs\Invoices\JobsInvoices;
use Apps\Tms\Packages\Jobs\Invoices\Model\AppsTmsJobsInvoices;
use Apps\Tms\Packages\Jobs\Lrs\Model\AppsTmsJobsLrs;
use Apps\Tms\Packages\Jobs\Trips\Model\AppsTmsJobsTrips;
use System\Base\BasePackage;

class JobsLrs extends BasePackage
{
    protected $modelToUse = AppsTmsJobsLrs::class;

    protected $packageName = 'lrs';

    public $lrs;

    public function init()
    {

        parent::init();

        return $this;
    }

    public function getLr($lrsId)
    {
        if ($this->config->databasetype === 'db') {
            $lrsObj = $this->getFirst('id', $lrsId);

            if ($lrsObj) {
                $lr = $lrsObj->toArray();

                // $addressObj = $lrsObj->getAddresses();

                // $lr['address'] = [];

                // if ($addressObj) {
                //     $lr['address'] = $addressObj->toArray();
                // }

                return $lr;
            }
        } else {
            $this->setFFRelations(true);
            // $this->setFFRelationsConditions(['addresses' => ['package_name', '=', 'Companies'], 'contacts' => ['package_name', '=', 'Companies']]);

            $lr = $this->getFirst('id', $lrsId, false, true, null, [], true);

            return $lr;
        }

        return false;
    }

    public function addLr($data, $viaExtractor = false)
    {
        if (isset($data['lr'])) {//New LR via Form
            $data['id'] = (int) $data['lr'];
            $data['archived'] = false;
            unset($data['lr']);
        }

        if (!isset($data['id'])) {
            $this->getDbCount(true);

            $data['id'] = $this->getLastInsertedId() + 1;
        }

        if (!isset($data['financial_year'])) {
            //Need to autogenerate using financial_year and increamented value from last invoice_no.
            try {
                $date = \Carbon\Carbon::now();

                if ($date->year > 2000) {
                    if ($date->month < 4) {
                        $endYear = substr($date->year, 2);
                        $startYear = substr($date->clone()->subYear(1)->year, 2);
                    } else {
                        $startYear = substr($date->year, 2);
                        $endYear = substr($date->clone()->addYear(1)->year, 2);
                    }
                } else {
                    if ($date->month < 4) {
                        $endYear = $date->year;
                        $startYear = $date->clone()->subYear(1)->year;
                    } else {
                        $startYear = $date->year;
                        $endYear = $date->clone()->addYear(1)->year;
                    }
                }
            } catch (\throwable $e) {
                $this->addResponse('Cannot process financial year! Contact developer.', 1);

                return false;
            }

            $data['financial_year'] = $startYear . '-' . $endYear;
        }

        if (!isset($data['invoice_no'])) {
            $invoicesPackage = new \Apps\Tms\Packages\Jobs\Invoices\JobsInvoices;

            $newInvoiceNumber = $invoicesPackage->getNextInvoiceNumber($data['financial_year']);

            $data['invoice_no'] = $newInvoiceNumber;
        }

        //Add new LR
        $this->setFFAddUsingUpdateOrInsert(true);
        $newLr = $this->add($data);

        if (!$newLr) {
            $this->addResponse('Not able to add LR for the Job!', 1);

            return false;
        }

        //Add Trip
        $lr = $this->packagesData->last;

        $now = \Carbon\Carbon::now();

        $newTripArr = [];
        $newTripArr['id'] = $lr['id'];
        $newTripArr['employee_id'] = 0;
        $newTripArr['start_date'] = $now->toDateString();
        $newTripArr['end_date'] = $now->toDateString();
        $newTripArr['start_location_id'] = 0;
        $newTripArr['load_location_id'] = 0;
        $newTripArr['unload_location_id'] = 0;
        $newTripArr['end_location_id'] = 0;
        $newTripArr['status'] = $lr['status'];
        $newTripArr['archived'] = false;

        $tripsPackage = new \Apps\Tms\Packages\Jobs\Trips\JobsTrips;
        $tripsPackage->setFFAddUsingUpdateOrInsert(true);

        try {
            $newTrip = $tripsPackage->add($newTripArr);
        } catch (\throwable $e) {
            $this->addResponse('Not able to add Trip for the Job!', 1);

            $this->remove($lr['id']);

            if ($viaExtractor) {
                throw $e;
            }

            return false;
        }

        if (!$newTrip) {
            $this->remove($lr['id']);

            $this->addResponse('Not able to add Trip for the Job!', 1);

            return false;
        }

        //Add Invoice
        $newInvoiceArr = [];
        $newInvoiceArr['id'] = $lr['id'];
        $newInvoiceArr['financial_year'] = $lr['financial_year'];
        $newInvoiceArr['invoice_no'] = $data['invoice_no'];
        $newInvoiceArr['invoice_date'] = $lr['date'];
        $newInvoiceArr['due_date'] = $lr['date'];
        $newInvoiceArr['start_location_id'] = 0;
        $newInvoiceArr['po_number'] = 0;
        $newInvoiceArr['material_invoice_no'] = 0;
        $newInvoiceArr['end_location_id'] = 0;

        $invoicesPackage = new \Apps\Tms\Packages\Jobs\Invoices\JobsInvoices;
        $invoicesPackage->setFFAddUsingUpdateOrInsert(true);

        try {
            $newInvoice = $invoicesPackage->add($newInvoiceArr);
        } catch (\throwable $e) {
            $this->remove($lr['id']);
            $tripsPackage->remove($lr['id']);

            $this->addResponse($e->getMessage(), 1);

            if ($viaExtractor) {
                throw $e;
            }

            return false;
        }

        if (!$newInvoice) {
            $this->remove($lr['id']);
            $tripsPackage->remove($lr['id']);

            $this->addResponse('Not able to add Invoice for the Job!', 1);

            return false;
        }

        $this->addResponse('Added new Job!');

        return true;
    }

    public function updateLr($data)
    {
        if ($this->update($data)) {
            try {
                $invoicesPackage = new \Apps\Tms\Packages\Jobs\Invoices\JobsInvoices;

                $invoicesPackage->update($data);
            } catch (\throwable $e) {
                $this->addResponse($e->getMessage(), 1);

                return false;
            }

            $this->addResponse('Lr updated');

            return true;
        }

        $this->addResponse('Error Updating Lr', 1);
    }

    public function removeLr($data)
    {
        $lr = $this->getLr($data['id']);

        //Archive Lr and do not delete it!
        $lr['archived'] = true;

        if ($this->updateLr($lr)) {
            $this->addResponse('Lr archived');

            return true;
        }

        $this->addResponse('Error removing lr', 1);

        return false;
    }

    public function checkLr($data)
    {
        $lr = $this->getLr($data['lr_no']);

        if ($lr) {
            $this->addResponse('Lorry Receipt: ' . $data['lr_no'] . ' already exists!', 1);

            return false;
        }

        $this->addResponse('Lorry Receipt ' . $data['lr_no'] . ' is valid');
    }

    public function getNextLr()
    {
        $newLr = [];

        if ($this->getDbCount(true) === 0) {
            $newLr['lr_no'] = 1;
        } else {
            $newLr['lr_no'] = $this->getLastInsertedId() + 1;
        }

        $now = \Carbon\Carbon::now();
        if ($now->month < 4) {
            $nowEndYear = substr($now->year, 2);
            $nowStartYear = substr($now->clone()->subYear(1)->year, 2);
        } else {
            $nowStartYear = substr($now->year, 2);
            $nowEndYear = substr($now->clone()->addYear(1)->year, 2);
        }

        $newLr['financial_year'] = $nowStartYear . '-' . $nowEndYear;

        $newLr['date'] = $now->toDateString();

        $this->addResponse('Generated LR Details', 0, ['newLr' => $newLr]);

        return $newLr;
    }

    public function updateDocument($data)
    {
        $this->setFFValidation(false);

        if (!isset($data['uuid'])) {
            $this->addResponse('UUID of file not set', 1);

            return false;
        }

        $remove = false;
        if (isset($data['remove']) && $data['remove'] == 'true') {
            $remove = true;
        }

        if (!$remove && !isset($data['org_file_name'])) {
            $this->addResponse('File name not set', 1);

            return false;
        }

        $lr = $this->getById((int) $data['lr_no']);

        if (!isset($lr['documents'])) {
            $lr['documents'] = [];
        }

        if (is_string($lr['documents'])) {
            $lr['documents'] = $this->helper->decode($lr['documents'], true);
        }

        if ($remove) {
            if (isset($lr['documents'][$data['uuid']])) {
                unset($lr['documents'][$data['uuid']]);

                $this->basepackages->storages->removeFile($data['uuid']);
            }
        } else {
            $lr['documents'][$data['uuid']]['uuid'] = $data['uuid'];
            $lr['documents'][$data['uuid']]['org_file_name'] = $data['org_file_name'];
        }

        if ($this->update($lr)) {
            $this->addResponse('Added document to job', 0, ['documents' => $lr['documents']]);

            if ($remove) {
                $this->addResponse('Removed document from job', 0, ['documents' => $lr['documents']]);
            }

            return true;
        }

        $this->addResponse('Error while updating documents for job', 1);

        return false;
    }

    public function getLrAvailableStatus()
    {
        return
            [
                '0' =>
                    [
                        'id' => '0',
                        'name'  => 'Open'
                    ],
                '1' =>
                    [
                        'id' => '1',
                        'name'  => 'Complete'
                    ],
                '2' =>
                    [
                        'id' => '2',
                        'name'  => 'On Trip'
                    ],
                '3' =>
                    [
                        'id' => '3',
                        'name'  => 'Payment Pending'
                    ],
                '4' =>
                    [
                        'id' => '4',
                        'name'  => 'Invalid'
                    ]
            ];
    }
}