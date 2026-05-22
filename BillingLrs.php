<?php

namespace Apps\Tms\Packages\Billing\Lrs;

use Apps\Tms\Packages\Billing\Lrs\Model\AppsTmsBillingLrs;
use System\Base\BasePackage;

class BillingLrs extends BasePackage
{
    protected $modelToUse = AppsTmsBillingLrs::class;

    protected $packageName = 'lrs';

    public $lrs;

    public function init()
    {
        return $this;
    }

    public function getLr($lrsId)
    {
        if ($this->config->databasetype === 'db') {
            $lrsObj = $this->getFirst('id', $lrsId);

            if ($lrsObj) {
                $lr = $lrsObj->toArray();

                $addressObj = $lrsObj->getAddresses();

                $lr['address'] = [];

                if ($addressObj) {
                    $lr['address'] = $addressObj->toArray();
                }

                return $lr;
            }
        } else {
            // $this->setFFRelations(true);
            // $this->setFFRelationsConditions(['addresses' => ['package_name', '=', 'Companies'], 'contacts' => ['package_name', '=', 'Companies']]);

            $lr = $this->getFirst('id', $lrsId, false, true, null, [], true);

            return $lr;
        }

        return false;
    }

    public function addLr($data)
    {
        if ($this->add($data)) {
            $lr = $this->packagesData->last;

            $this->addResponse('Lr added');

            return true;
        }

        $this->addResponse('Error Adding Lr', 1);
    }

    public function updateLr($data)
    {
        if ($this->update($data)) {
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

    // public function getLrByRegistrationNo($registrationNo)
    // {
    //     if ($this->config->databasetype === 'db') {
    //         $params =
    //             [
    //                 'conditions'    => 'registration_no = :registrationNo:',
    //                 'bind'          =>
    //                     [
    //                         'registrationNo'         => $registrationNo
    //                     ]
    //             ];
    //     } else {
    //         $params = ['conditions' => ['registration_no', '=', $registrationNo]];
    //     }

    //     $lr = $this->getByParams($params);

    //     if ($lr && count($lr) > 0) {
    //         $lr = $this->getLr($lr[0]['id']);

    //         return $lr;
    //     }

    //     return false;
    // }
}