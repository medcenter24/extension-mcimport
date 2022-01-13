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

declare(strict_types=1);

namespace medcenter24\McImport\Services\CaseImporter;

use Carbon\Carbon;
use Exception;
use FilesystemIterator;
use JetBrains\PhpStorm\ArrayShape;
use medcenter24\mcCore\App\Helpers\FileHelper;
use medcenter24\mcCore\App\Services\Core\ServiceLocator\ServiceLocatorTrait;
use medcenter24\McImport\Contract\DocumentReaderService;
use medcenter24\McImport\Exceptions\ImporterException;
use medcenter24\McImport\Providers\DocxReaderServiceProvider;
use SplFileInfo;

abstract class AbstractDocxCaseImportDataProvider extends AbstractCaseImportDataProvider
{
    use ServiceLocatorTrait;

    /**
     * This is docx reader
     * @return array
     */
    public function getFileExtensions(): array
    {
        return ['docx'];
    }

    #[ArrayShape(['startsWith' => "string"])]
    public function getExcludeRules(): array
    {
        return ['startsWith' => '~$'];
    }

    /**
     * @return array
     */
    public function getImages(): array
    {
        /** @var DocumentReaderService $docxReaderService */
        $docxReaderService = $this->getServiceLocator()->get(DocxReaderServiceProvider::class);
        $files = $docxReaderService->getImages($this->getPath());

        $docs = [];
        foreach ($files as $file) {
            if (!$this->isExcludedFile($file)) {
                $docs[] = $file;
            }
        }
        return $docs;
    }

    /**
     * @return string
     */
    protected function getExcludedFilesPath(): string
    {
        return '';
    }

    /**
     * @param array $file
     * @return bool
     */
    private function isExcludedFile(array $file): bool
    {
        /** @var string $excludeDir */
        $excludeDirPath = $this->getExcludedFilesPath();
        if (FileHelper::isDirExists($excludeDirPath)) {
            $excludedFiles = new FilesystemIterator($excludeDirPath);
            /** @var SplFileInfo $exclude */
            foreach ($excludedFiles as $exclude) {
                if (array_key_exists('imageContent', $file)
                    && strcmp($file['imageContent'], file_get_contents($exclude->getRealPath())) === 0
                ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Validate all parsed dates
     * @param string $date
     * @throws ImporterException
     */
    public function checkDate(string $date): void
    {
        try {
            Carbon::parse($date);
        } catch (Exception $e) {
            throw new ImporterException('Incorrect date format "' . $e->getMessage() . '"');
        }
    }
}
