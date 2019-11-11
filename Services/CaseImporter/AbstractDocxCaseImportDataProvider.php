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


use medcenter24\mcCore\App\Services\Core\ServiceLocator\ServiceLocatorTrait;
use medcenter24\McImport\Contract\DocumentReaderService;
use medcenter24\McImport\Providers\DocxReaderServiceProvider;

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
            $docs[] = $file;
        }
        return $docs;
    }
}
