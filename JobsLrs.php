<?php

namespace Apps\Tms\Packages\Jobs\Lrs;

use Apps\Tms\Packages\Jobs\Invoices\JobsInvoices;
use Apps\Tms\Packages\Jobs\Invoices\Model\AppsTmsJobsInvoices;
use Apps\Tms\Packages\Jobs\Lrs\Model\AppsTmsJobsLrs;
use Apps\Tms\Packages\Jobs\Lrs\Settings;
use Apps\Tms\Packages\Jobs\Trips\Model\AppsTmsJobsTrips;
use System\Base\BasePackage;

class JobsLrs extends BasePackage
{
    protected $modelToUse = AppsTmsJobsLrs::class;

    protected $packageName = 'lrs';

    protected $settings = Settings::class;

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
        // if (isset($data['lr'])) {//New LR via Form
        //     $data['id'] = (int) $data['lr'];
        //     $data['archived'] = false;
        //     unset($data['lr']);
        // }

        // if (!isset($data['id'])) {
        //     $this->getDbCount(true);

        //     $data['id'] = $this->getLastInsertedId() + 1;
        // }

        if (!isset($data['financial_year'])) {
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

        // if (!isset($data['invoice_no'])) {
        //     $invoicesPackage = new \Apps\Tms\Packages\Jobs\Invoices\JobsInvoices;

        //     $newInvoiceNumber = $invoicesPackage->getNextInvoiceNumber($data['financial_year']);

        //     $data['invoice_no'] = $newInvoiceNumber;
        // }

        //Add new LR
        if ($viaExtractor) {
            $this->setFFAddUsingUpdateOrInsert(true);
        }

        $activityLogs = [];

        if (!isset($data['archived'])) {
            $data['archived'] = false;
        }

        $newLr = $this->add($data);

        if (!$newLr) {
            $this->addResponse('Not able to add LR for the Job!', 1);

            return false;
        }

        //Add Trip
        $lr = $this->packagesData->last;

        $activityLogs = array_merge($activityLogs, $lr);

        $newTripArr = [];
        $newTripArr['id'] = $lr['id'];
        $newTripArr['financial_year'] = $lr['financial_year'];

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

        $activityLogs = array_merge($activityLogs, $tripsPackage->packagesData->last);

        //Add Invoice
        $newInvoiceArr = [];
        $newInvoiceArr['id'] = $lr['id'];
        $newInvoiceArr['financial_year'] = $lr['financial_year'];

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

        $activityLogs = array_merge($activityLogs, $invoicesPackage->packagesData->last);

        $this->addActivityLog($activityLogs);

        $this->addResponse('Added new Job!');

        return true;
    }

    public function updateLr($data)
    {
        $lr = $this->getLr($data['id']);

        $trip = $lr['trip'];
        $invoice = $lr['invoice'];

        if ($this->update(array_merge($lr, $data))) {
            try {
                $tripsPackage = new \Apps\Tms\Packages\Jobs\Trips\JobsTrips;
                $tripsPackage->update(array_merge($trip, $data));

                $invoicesPackage = new \Apps\Tms\Packages\Jobs\Invoices\JobsInvoices;
                $invoicesPackage->update(array_merge($invoice, $data));

                $lr = $this->getLr($data['id']);

                if (isset($data['update_all_expenses'])) {
                    $expensesPackage = new \Apps\Tms\Packages\Jobs\Expenses\JobsExpenses;

                    if (!$lr['expenses']) {
                        $lr['expenses'] = [];
                        $lr['expenses'] = $expensesPackage->checkCarryForwardedExpenses(['employee_id' => $lr['trip']['employee_id']]);
                    }

                    if (isset($lr['expenses']) && count($lr['expenses']) > 0) {
                        foreach ($lr['expenses'] as $expense) {
                            $expense['lr_no'] = $lr['lr_no'];//Update LR just in case it has changed

                            if ($lr['trip']['employee_id'] !== $expense['employee_id']) {
                                $expense['carry_forward'] = true;//Carry Forward expense for next trip.
                            } else {
                                $expense['carry_forward'] = null;
                            }

                            $expensesPackage->update($expense);
                        }
                    }
                }

                $this->releaseMutex($lr);

                unset($data['lr']);

                $this->addActivityLog($data, array_merge($lr, $trip, $invoice));
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

    public function getFy()
    {
        $now = \Carbon\Carbon::now();

        if ($now->month < 4) {
            $nowEndYear = substr($now->year, 2);
            $nowStartYear = substr($now->clone()->subYear(1)->year, 2);
        } else {
            $nowStartYear = substr($now->year, 2);
            $nowEndYear = substr($now->clone()->addYear(1)->year, 2);
        }

        $financialYear = $nowStartYear . '-' . $nowEndYear;

        $this->addResponse('Generated financial year', 0, ['financialYear' => $financialYear]);
    }

    public function checkLr($data)
    {
        if ($this->config->databasetype === 'db') {
            $params =
                [
                    'conditions'    => 'lr_no = :lrNo: AND financial_year = :financialYear:',
                    'bind'          =>
                        [
                            'lrNo'              => $data['lr_no'],
                            'financialYear'     => $data['financial_year']
                        ]
                ];
        } else {
            $params = ['conditions' => ['lr_no', '=', $data['lr_no']],['financial_year', '=', $data['financial_year']]];
        }

        $lr = $this->getByParams($params);

        if ($lr && count($lr) === 1) {
            $this->addResponse('Lorry Receipt #' . $data['lr_no'] . ' already exists!', 1);

            return false;
        }

        $newLr = $this->getNextLr($data['financial_year']);

        if (isset($newLr['lr_no'])) {
            if ((int) $data['lr_no'] > $newLr['lr_no']) {
                $this->addResponse('Lorry Receipt #' . $data['lr_no'] . ' is valid', 2, ['newLr' => $newLr]);

                return true;
            }
        }

        $this->addResponse('Lorry Receipt #' . $data['lr_no'] . ' is valid');
    }

    public function getNextLr($financialYear = null)
    {
        $now = \Carbon\Carbon::now();

        if (!isset($financialYear) ||
            (isset($financialYear) && $financialYear === '')
        ) {
            if ($now->month < 4) {
                $nowEndYear = substr($now->year, 2);
                $nowStartYear = substr($now->clone()->subYear(1)->year, 2);
            } else {
                $nowStartYear = substr($now->year, 2);
                $nowEndYear = substr($now->clone()->addYear(1)->year, 2);
            }

            $financialYear = $nowStartYear . '-' . $nowEndYear;
        }

        if ($this->config->databasetype === 'db') {
            $params =
                [
                    'conditions'    => 'financial_year = :financialYear:',
                    'bind'          =>
                        [
                            'financialYear'         => $financialYear
                        ]
                ];
        } else {
            $params = ['conditions' => ['financial_year', '=', $financialYear]];
        }

        $lrs = $this->getByParams($params);

        $lrNumbers = [];

        $newLr['financial_year'] = $financialYear;
        $newLr['date'] = $now->format('d-m-Y');

        if ($lrs && count($lrs) > 0) {
            foreach ($lrs as $lr) {
                if ($lr['lr_no'] > 10000) {//Taking into note that while importing we create own lr numbers using timestamp.
                    continue;
                }
                array_push($lrNumbers, (int) $lr['lr_no']);
            }

            if (count($lrNumbers) > 0) {
                asort($lrNumbers);

                $newLr['lr_no'] = $this->helper->last($lrNumbers) + 1;

                $this->addResponse('Generated next lorry receipt #', 0, ['newLr' => $newLr]);

                return $newLr;
            }
        }

        $newLr['lr_no'] = 1;

        $this->addResponse('Generated next lorry receipt #', 0, ['newLr' => $newLr]);

        return $newLr;
    }

    public function updateDocument($data)
    {
        $this->setFFValidation(false);

        $lr = $this->getById((int) $data['lr_no']);

        if (!isset($lr['documents'])) {
            $lr['documents'] = [];
        }
        if (!isset($data['documents'])) {
            $data['documents'] = [];
        }
        if (is_string($lr['documents'])) {
            $lr['documents'] = $this->helper->decode($lr['documents'], true);
        }

        if (isset($lr['documents']) && count($lr['documents']) > 0) {
            $lr['documents'] = array_replace($data['documents'], array_intersect_key($data['documents'], $lr['documents']));
        } else {
            $lr['documents'] = $data['documents'];
        }

        foreach ($lr['documents'] as $uuid => &$document) {
            if (!isset($document['account_id'])) {
                $document['account_id'] = 0;
            } else {
                $document['account_id'] = (int) $document['account_id'];
            }

            if ($document['account_id'] === 0) {
                if ($this->access->auth->check()) {
                    $document['account_id'] = $this->access->auth->account()['id'];
                    $account = $this->basepackages->accounts->getAccountById($this->access->auth->account()['id']);

                    if ($account && isset($account['contact']['full_name'])) {
                        $document['account_name'] = $account['contact']['full_name'];
                    }
                } else {
                    $document['account_name'] = '-';
                }
            }

            if (!isset($document['date'])) {
                $document['date'] = (\Carbon\Carbon::now('Asia/Kolkata'))->toAtomString();
            }
        }

        if ($this->update($lr)) {
            if (isset($data['removesigned']) && $data['removesigned'] == 'true') {
                try {
                    $invoicesPackage = new \Apps\Tms\Packages\Jobs\Invoices\JobsInvoices;

                    $invoicesPackage->update(['id' => $lr['id'], 'signed_uuid' => null, 'signed_document' => null, 'signed_by' => null, 'signed_at' => null]);
                } catch (\throwable $e) {
                    $this->addResponse($e->getMessage(), 1);

                    return false;
                }
            }

            $this->addResponse('Added documents to job', 0, ['documents' => $lr['documents']]);

            return true;
        }

        $this->addResponse('Error while updating documents for job', 1);

        return false;
    }

    public function getLrAvailableStatus()
    {
        return
            [
                '1' =>
                    [
                        'id' => '1',
                        'name'  => 'Open'
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
                        'name'  => 'Complete'
                    ],
                '5' =>
                    [
                        'id' => '5',
                        'name'  => 'Invalid'
                    ]
            ];
    }

    public function email($data)
    {
        if (!isset($data['to']) || (isset($data['to']) && $data['to'] === '')) {{
            $this->addResponse('Please provide to address!', 1);

            return false;
        }}

        if (!isset($data['subject']) || (isset($data['subject']) && $data['subject'] === '')) {{
            $this->addResponse('Please provide subject!', 1);

            return false;
        }}

        if (!isset($data['message'])) {{
            $this->addResponse('Please provide message!', 1);

            return false;
        }}

        $package = $this->modules->packages->getPackageByClass(get_class(new \Apps\Tms\Packages\Jobs\Invoices\JobsInvoices));

        if (!$package) {
            return $this->throwIdNotFound();
        }

        $lr = $this->getLr((int) $data['lr_no']);
        if (isset($lr['organisation_id']) && $lr['organisation_id'] !== 0) {
            $companiesPackage = new \Apps\Tms\Packages\Companies\Companies;

            $lr['organisation'] = $companiesPackage->getCompany($lr['organisation_id']);
        }

        $organisation_settings = [];
        if (isset($package['settings']['organisations'][$lr['organisation_id']])) {
            $organisation_settings = $package['settings']['organisations'][$lr['organisation_id']];
        }

        $emailData['app_id'] = $this->apps->getAppInfo()['id'];
        $emailData['domain_id'] = $this->domains->getDomain()['id'];
        $emailData['status'] = 1;
        $emailData['priority'] = 3;

        if (isset($organisation_settings['invoice_sender']) && $organisation_settings['invoice_sender'] !== '') {
            $emailData['from'] = $organisation_settings['invoice_sender'];
        } else if (isset($lr['organisation']['company_email'])) {
            $emailData['from'] = $lr['organisation']['company_email'] . '|' . $lr['organisation']['name'];
        }

        $data['to'] = str_replace(' ', '', $data['to']);
        $toAddresses = explode(',', $data['to']);
        $emailData['to_addresses'] = $toAddresses;

        $data['cc'] = str_replace(' ', '', $data['cc']);
        $ccAddresses = explode(',', $data['cc']);
        $emailData['cc_addresses'] = $ccAddresses;

        $data['bcc'] = str_replace(' ', '', $data['bcc']);
        $bccAddresses = explode(',', $data['bcc']);
        $emailData['bcc_addresses'] = $bccAddresses;

        $emailData['subject'] = $data['subject'];
        $emailData['body'] = $data['message'];
        if (isset($data['attachments'])) {
            $emailData['attachments'] = $data['attachments'];
        }

        $this->basepackages->emailqueue->addQueue($emailData);

        $this->addResponse($this->basepackages->emailqueue->packagesData->responseMessage, $this->basepackages->emailqueue->packagesData->responseCode);
    }

    public function getFormattedInvoice($job, $organisation_settings)
    {
        if (isset($organisation_settings['invoice_format']) && $organisation_settings['invoice_format'] !== '') {
            preg_match_all('/{.*?}/', $organisation_settings['invoice_format'], $invoiceFormatArr);

            $formattedInvoice = str_replace('{', '', str_replace('}', '', $organisation_settings['invoice_format']));

            if (count($invoiceFormatArr[0]) > 0) {
                foreach ($invoiceFormatArr[0] as $invoiceFormatKey) {
                    $invoiceFormatKey = str_replace('{', '', str_replace('}', '', $invoiceFormatKey));

                    $path = findKeyLocation($job, $invoiceFormatKey);

                    if (!$path) {
                        continue;
                    }

                    $value = array_reduce($path, function($carry, $key) {
                        return $carry[$key];
                    }, $job);

                    if ($value) {
                        $formattedInvoice = str_replace($invoiceFormatKey, $value, $formattedInvoice);
                    } else {
                        $formattedInvoice = '';
                    }
                }
            }

            return $formattedInvoice;
        }

        return '';
    }
}