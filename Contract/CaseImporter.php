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
 * Copyright (c) 2019 (original work) MedCenter24.com;
 */

namespace medcenter24\McImport\Contract;


use medcenter24\mcCore\App\Support\Core\ConfigurableInterface;

interface CaseImporter extends ConfigurableInterface
{
    /**
     * DataProviders that can read source files
     * The list of the CaseImporterDataProvider
     */
    public const OPTION_PROVIDERS = 'providers';

    /**
     * Generators that can create cases according to read the data by providers
     * CaseGeneratorInterface
     */
    public const OPTION_CASE_GENERATOR = 'case_generator';

    /**
     * Boolean option - to store or not an imports errors
     * bool
     */
    public const OPTION_WITH_ERRORS = 'saveErrors';

    /**
     * Import a file
     * @param string $path
     */
    public function import(string $path): void;

    /**
     * Which files could be imported
     * @return array
     */
    public function getImportableExtensions(): array;

    /**
     * Rules to skip the file
     * @return array
     */
    public function getExcludeRules(): array;

    /**
     * @return array [] of Accidents
     */
    public function getImportedAccidents(): array;
}
