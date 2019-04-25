<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
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

namespace medcenter24\McImport\Services;


use medcenter24\mcCore\App\Accident;
use medcenter24\McImport\Contract\CaseImporter;
use medcenter24\McImport\Contract\CaseImporterProvider;

class CaseImporterService implements CaseImporter
{
    public function import(string $path): Accident
    {
        // get Docx from the storage and run importer
        /** @var CaseImporterProvider $provider */
        $provider = resolve(CaseImporterProvider::class);
        $provider->load($path);
        $provider->import();

        return $provider->getLastAccident();
    }
}
