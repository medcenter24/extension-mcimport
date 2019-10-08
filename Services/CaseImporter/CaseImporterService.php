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

namespace medcenter24\McImport\Services\CaseImporter;


use medcenter24\mcCore\App\Accident;
use medcenter24\mcCore\App\Support\Core\Configurable;
use medcenter24\McImport\Contract\CaseGeneratorInterface;
use medcenter24\McImport\Contract\CaseImporter;
use medcenter24\McImport\Contract\CaseImporterDataProvider;
use medcenter24\McImport\Exceptions\ImporterException;

class CaseImporterService extends Configurable implements CaseImporter
{
    public const DISC_IMPORTS = 'imports';
    public const CASES_FOLDERS = 'cases';

    public const OPTION_PROVIDERS = 'providers';
    public const OPTION_CASE_GENERATOR = 'case_generator';

    /**
     * List of imported cases (id of Accidents)
     * @var array
     */
    private $importedAccidents = [];

    /**
     *
     * @param string $path path to the importing file source
     * @throws ImporterException
     */
    public function import(string $path): void
    {
        $imported = false;
        if (!$this->hasOption(self::OPTION_PROVIDERS)) {
            throw new ImporterException('Import providers not configured');
        }

        if (!$this->hasOption(self::OPTION_CASE_GENERATOR)) {
            throw new ImporterException('Case Generator not configured');
        }

        foreach ($this->getOption(self::OPTION_PROVIDERS) as $registeredProvider) {
            /** @var CaseImporterDataProvider $provider */
            $provider = new $registeredProvider;
            $provider->init($path);
            if ($provider->isFit()) {
                /** @var Accident $accident */
                $accident = $this->createCase($registeredProvider);
                $this->importedAccidents[] = $accident->getAttribute('id');
                $imported = true;
                break;
            }
        }

        if (!$imported) {
            logger('File can not be imported', [
                'file' => $path,
            ]);
            throw new ImporterException('Not Imported');
        }
    }

    /**
     * @return array Accident
     */
    public function getImportedAccidents(): array
    {
        return $this->importedAccidents;
    }

    /**
     * @return array
     */
    public function getImportableExtensions(): array {
        $ext = [];
        /** @var CaseImporterDataProvider $provider */
        foreach ($this->getOption(self::OPTION_PROVIDERS) as $provider) {
            $ext[] = $provider->getFileExtensions();
        }

        return array_merge(...$ext);
    }

    private function createCase(CaseImporterDataProvider $dataProvider): Accident
    {
        $caseGeneratorClass = $this->getOption(self::OPTION_CASE_GENERATOR);
        /** @var CaseGeneratorInterface $caseGenerator */
        $caseGenerator = new $caseGeneratorClass;
        return $caseGenerator->createCase($dataProvider);
    }
}
