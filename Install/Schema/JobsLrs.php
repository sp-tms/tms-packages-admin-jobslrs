<?php

namespace Apps\Tms\Packages\Jobs\Lrs\Install\Schema;

use Phalcon\Db\Column;
use Phalcon\Db\Index;

class JobsLrs
{
    public function columns()
    {
        return
        [
           'columns' => [
                new Column(
                    'id',
                    [
                        'type'          => Column::TYPE_INTEGER,
                        'notNull'       => true,
                        'autoIncrement' => true,
                        'primary'       => true,
                    ]
                ),
                //We will use this to archive and only show current year in job list to avoid chaos on the list page.
                new Column(
                    'financial_year',
                    [
                        'type'          => Column::TYPE_VARCHAR,
                        'size'          => 10,
                        'notNull'       => true,
                    ]
                ),
                new Column(//Self Company ID
                    'organisation_id',
                    [
                        'type'          => Column::TYPE_INTEGER,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'company_id',
                    [
                        'type'          => Column::TYPE_INTEGER,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'vehicle_id',
                    [
                        'type'          => Column::TYPE_SMALLINTEGER,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'date',
                    [
                        'type'          => Column::TYPE_VARCHAR,
                        'size'          => 20,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'from_company_id',
                    [
                        'type'          => Column::TYPE_INTEGER,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'to_company_id',
                    [
                        'type'          => Column::TYPE_INTEGER,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'from_company_address_id',
                    [
                        'type'          => Column::TYPE_INTEGER,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'to_company_address_id',
                    [
                        'type'          => Column::TYPE_INTEGER,
                        'notNull'       => true,
                    ]
                ),
                new Column(//Status of LR: Open, On Trip, Done, Cancelled, Invalid
                    'status',
                    [
                        'type'          => Column::TYPE_TINYINTEGER,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'archived',
                    [
                        'type'          => Column::TYPE_BOOLEAN,
                        'notNull'       => true,
                    ]
                ),
                new Column(
                    'lr_dev_notes',
                    [
                        'type'          => Column::TYPE_VARCHAR,
                        'size'          => 2048,
                        'notNull'       => false,
                    ]
                ),
                new Column(
                    'documents',
                    [
                        'type'          => Column::TYPE_JSON,
                        'notNull'       => false,
                    ]
                ),
                new Column(
                    'lr_notes',
                    [
                        'type'          => Column::TYPE_VARCHAR,
                        'size'          => 2048,
                        'notNull'       => false,
                    ]
                ),
            ]
        ];
    }

    public function indexes()
    {
        return
        [
            new Index(
                'column_INDEX',
                [
                    'financial_year',
                    'organisation_id',
                    'company_id',
                    'from_company_address_id',
                    'to_company_address_id'
                ],
                'INDEX'
            )
        ];
    }
}
