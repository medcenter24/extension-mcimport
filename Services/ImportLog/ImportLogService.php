<?php

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2019 (original work) MedCenter24.com;
 */

declare(strict_types = 1);

namespace medcenter24\McImport\Services\ImportLog;

use Exception;
use medcenter24\mcCore\App\Accident;
use medcenter24\mcCore\App\Services\AbstractModelService;
use medcenter24\McImport\Contract\CaseImporterDataProvider;
use medcenter24\McImport\Entities\ImportLog;

class ImportLogService extends AbstractModelService
{
    public const FIELD_FILENAME = 'filename';
    public const FIELD_ACCIDENT_ID = 'accident_id';
    public const FIELD_INTERNAL_REF_NUM = 'internal_ref_num';
    public const FIELD_EXTERNAL_REF_NUM = 'external_ref_num';
    public const FIELD_DATA_PROVIDER = 'data_provider';
    public const FIELD_STATUS = 'status';

    public const FILLABLE = [
        self::FIELD_FILENAME,
        self::FIELD_ACCIDENT_ID,
        self::FIELD_INTERNAL_REF_NUM,
        self::FIELD_EXTERNAL_REF_NUM,
        self::FIELD_DATA_PROVIDER,
        self::FIELD_STATUS,
    ];

    public const UPDATABLE = [
        self::FIELD_FILENAME,
        self::FIELD_ACCIDENT_ID,
        self::FIELD_INTERNAL_REF_NUM,
        self::FIELD_EXTERNAL_REF_NUM,
        self::FIELD_DATA_PROVIDER,
        self::FIELD_STATUS,
    ];

    public const VISIBLE = [
        self::FIELD_FILENAME,
        self::FIELD_ACCIDENT_ID,
        self::FIELD_INTERNAL_REF_NUM,
        self::FIELD_EXTERNAL_REF_NUM,
        self::FIELD_DATA_PROVIDER,
        self::FIELD_STATUS,
    ];

    /**
     * Write log
     * @param string $filename
     * @param CaseImporterDataProvider $dataProvider
     * @param string $status
     * @param Accident|null $accident
     */
    public function log(string $filename, CaseImporterDataProvider $dataProvider, string $status, Accident $accident = null): void
    {
        try {
            $refNum = $dataProvider->getInternalRefNumber();
            $extRefNum = $dataProvider->getExternalRefNumber();
        } catch (Exception $e) {
            $refNum = $extRefNum = '';
        }

        $this->create([
            self::FIELD_FILENAME => $filename,
            self::FIELD_ACCIDENT_ID => $accident ? $accident->getAttribute('id') : 0,
            self::FIELD_INTERNAL_REF_NUM => $refNum,
            self::FIELD_EXTERNAL_REF_NUM => $extRefNum,
            self::FIELD_DATA_PROVIDER => get_class($dataProvider),
            self::FIELD_STATUS => $status,
        ]);
    }

    /**
     * Checks that path already imported
     * @param string $path
     * @return bool
     */
    public function isImported(string $path): bool
    {
        return $this->count([self::FIELD_FILENAME => $path]) > 0;
    }

    /**
     * Name of the Model (ex: City::class)
     * @return string
     */
    protected function getClassName(): string
    {
        return ImportLog::class;
    }

    /**
     * Initialize defaults to avoid database exceptions
     * (different storage have different rules, so it is correct to set defaults instead of nothing)
     * @return array
     */
    protected function getFillableFieldDefaults(): array
    {
        return [
            self::FIELD_FILENAME => '',
            self::FIELD_ACCIDENT_ID => 0,
            self::FIELD_INTERNAL_REF_NUM => '',
            self::FIELD_EXTERNAL_REF_NUM => '',
            self::FIELD_DATA_PROVIDER => '',
            self::FIELD_STATUS => '',
        ];
    }

    protected function getUpdatableFields(): array
    {
        return self::UPDATABLE;
    }
}
