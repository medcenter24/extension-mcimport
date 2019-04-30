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


use medcenter24\McImport\Contract\CaseImporterProviderService;

abstract class DataServiceProviderService implements CaseImporterProviderService
{
    /**
     * @var array
     */
    protected $data = [];

    /**
     * Load file to data provider
     *
     * @param string $path
     * @return self
     */
    abstract public function load($path = ''): CaseImporterProviderService;

    /**
     * Check that file could be parsed by that DataProvider
     */
    abstract public function check(): void;

    /**
     * Load parsed data as array
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Store case (accident) to data base
     */
    abstract public function import(): void;
}
