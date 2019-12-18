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

namespace medcenter24\McImport\Contract;


use medcenter24\mcCore\App\Accident;
use medcenter24\mcCore\App\Exceptions\InconsistentDataException;
use medcenter24\McImport\Exceptions\ImporterException;

interface CaseImporterProviderService
{
    /**
     * Import file from the path
     */
    public function import(): void;

    /**
     * imported accident
     * @return Accident
     */
    public function getAccident(): Accident;

    /**
     * Load file to the object
     * @param string $path
     * @return CaseImporterProviderService
     */
    public function load(string $path = ''): self;

    /**
     * Check that file could be parsed by that DataProvider
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function check(): void;

    /**
     * Load parsed data as array
     * @return array
     */
    public function getData(): array;

    /**
     * Each import provider should work with defined type of files
     * we can determine them by a file extension
     * @return array
     */
    public function getFileExtensions(): array;
}
