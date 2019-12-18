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


use Illuminate\Support\Facades\Log;
use medcenter24\mcCore\App\Services\Core\ServiceLocator\ServiceLocatorTrait;
use medcenter24\mcCore\App\Support\Core\Configurable;
use medcenter24\McImport\Contract\CaseGeneratorInterface;
use medcenter24\McImport\Contract\CaseImporter;
use medcenter24\McImport\Contract\CaseImporterDataProvider;
use medcenter24\McImport\Exceptions\ImporterException;
use medcenter24\McImport\Services\ImportLog\ImportLogService;

class CaseImporterService extends Configurable implements CaseImporter
{
    use ServiceLocatorTrait;

    public const DISC_IMPORTS = 'imports';
    public const DUPLICATION_EXCEPTION_CODE = 77;

    /**
     * List of imported cases (id of Accidents)
     * @var array
     */
    private $importedAccidents = [];

    /**
     * @var array
     */
    private $errors = [];

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

        if ($this->getImportLogService()->isImported($path)) {
            throw new ImporterException('Already imported', self::DUPLICATION_EXCEPTION_CODE);
        }

        /** @var CaseImporterDataProvider $registeredProvider */
        $errors = [];
        foreach ($this->getOption(self::OPTION_PROVIDERS) as $registeredProvider) {
            if ($this->getOption(self::OPTION_WITH_ERRORS)) {
                $registeredProvider->setStoreErrors(true);
            }
            $registeredProvider->init($path);
            if ($registeredProvider->isFit()) {
                $this->importedAccidents[] = $this->createCase($registeredProvider);
                $imported = true;
                break;
            }

            $errors[] = $registeredProvider->getErrors();
        }

        if (!$imported) {
            $this->errors += $errors;
            Log::error('File not being imported', [
                'file' => $path,
            ]);
            throw new ImporterException('Not Imported');
        }
    }

    private function getImportLogService(): ImportLogService
    {
        return $this->getServiceLocator()->get(ImportLogService::class);
    }

    public function getErrors(): array
    {
        return $this->errors;
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

        return array_unique(array_merge(...$ext));
    }

    /**
     * @return array
     */
    public function getExcludeRules(): array {
        $rules = [];
        /** @var CaseImporterDataProvider $provider */
        foreach ($this->getOption(self::OPTION_PROVIDERS) as $provider) {
            $rules[] = $provider->getExcludeRules();
        }

        return array_unique(array_merge(...$rules));
    }

    /**
     * @param CaseImporterDataProvider $dataProvider
     * @return int
     */
    private function createCase(CaseImporterDataProvider $dataProvider): int
    {
        /** @var CaseGeneratorInterface $caseGenerator */
        $caseGenerator = $this->getOption(self::OPTION_CASE_GENERATOR);
        return $caseGenerator->createCase($dataProvider);
    }
}
