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

namespace medcenter24\McImport\Services\ImportLog;


use Carbon\Carbon;
use Exception;
use medcenter24\mcCore\App\Accident;
use medcenter24\mcCore\App\Services\AbstractModelService;
use medcenter24\McImport\Contract\CaseImporterDataProvider;
use medcenter24\McImport\Entities\ImportLog;

class ImportLogService extends AbstractModelService
{

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
            'filename' => $filename,
            'accident_id' => $accident ? $accident->getAttribute('id') : 0,
            'internal_ref_num' => $refNum,
            'external_ref_num' => $extRefNum,
            'data_provider' => get_class($dataProvider),
            'status' => $status,
        ]);
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
    protected function getRequiredFields(): array
    {
        return [
            'filename' => '',
            'accident_id' => 0,
            'internal_ref_num' => '',
            'external_ref_num' => '',
            'data_provider' => '',
            'status' => '',
        ];
    }
}
