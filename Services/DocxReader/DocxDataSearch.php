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

namespace medcenter24\McImport\Services\DocxReader;


use DOMDocument;
use medcenter24\mcCore\App\Exceptions\InconsistentDataException;
use medcenter24\mcCore\App\Services\DocumentService;
use medcenter24\mcCore\App\Services\DomDocumentService;
use medcenter24\mcCore\App\Services\ExtractTableFromArrayService;
use medcenter24\mcCore\App\Services\ServiceLocatorTrait;
use medcenter24\McImport\Contract\DocumentReaderService;
use medcenter24\McImport\Contract\DocumentSeeker;
use medcenter24\McImport\Providers\DocxDomDocumentService;
use medcenter24\McImport\Providers\DocxReaderServiceProvider;
use medcenter24\McImport\Providers\DocxTablesExtractService;

class DocxDataSearch implements DocumentSeeker
{
    use ServiceLocatorTrait;
    
    /**
     * @var string
     */
    private $filePath;

    /**
     * List of the paths to the images that should not be imported
     * @var array
     */
    private $excludeImages = [];

    /**
     * @var bool
     */
    private $isInitialized = false;

    /**
     * doc in the table data format
     * @var array 
     */
    private $rootTables = [];

    /**
     * @var DOMDocument
     */
    private $dom;

    /**
     * Initialize seeker to work with document
     * @param string $path
     */
    public function init(string $path): void
    {
        $this->filePath = $path;
        // checking that file can be read
        $this->getDom();
        $this->isInitialized = true;
    }

    /**
     * @param array $excludeImages
     */
    public function excludeImages(array $excludeImages): void
    {
        $this->excludeImages = $excludeImages;
    }

    /**
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * @return bool
     */
    public function isInitialized(): bool
    {
        return $this->isInitialized;
    }

    /**
     * Looking for data in the rootTable data provider
     * if not found it returns empty table
     * @param array $mappedPath
     * @return array
     * @throws InconsistentDataException
     */
    public function rootTableSearch(array $mappedPath): array
    {
        $res = [];
        $table = $this->getRootTables();
        foreach ($mappedPath as $key) {
            if (is_array($table) && array_key_exists($key, $table)) {
                $table = $table[$key];
            }
        }
        if ($table) {
            $res = $table;
        }

        return $res;
    }

    public function rowsSearch(array $mappedPath): array
    {
        return $this->getRows();
    }

    /**
     * @return array
     */
    public function getImages(): array
    {
        return $this->getDocumentReaderService()->getImages($this->getFilePath());
    }

    /**
     * @return array
     * @throws InconsistentDataException
     */
    private function getRootTables(): array
    {
        if (!$this->rootTables) {
            $domDocumentService = $this->getDomDocumentService();
            $table = $domDocumentService->toArray($this->getDom());

            $tablesExtractService = $this->getTablesExtractService();
            $data = $tablesExtractService->extract($table);

            $this->rootTables = $data[ExtractTableFromArrayService::TABLES];
        }
        return $this->rootTables;
    }

    /**
     * @return DOMDocument|mixed
     */
    private function getDom() {
        if (!$this->dom) {
            $readerService = $this->getDocumentReaderService();
            $this->dom = $readerService->getDom($this->getFilePath());
        }
        return $this->dom;
    }

    private function getRows(): array
    {
        var_dump('tobe done');die;
    }

    private function getTablesExtractService(): ExtractTableFromArrayService
    {
        return $this->getServiceLocator()->get(DocxTablesExtractService::class);
    }

    private function getDocumentReaderService(): DocumentReaderService
    {
        return $this->getServiceLocator()->get(DocxReaderServiceProvider::class);
    }

    private function getDomDocumentService(): DomDocumentService
    {
        return $this->getServiceLocator()->get(DocxDomDocumentService::class);
    }

    private function getDocumentService(): DocumentService
    {
        return $this->getServiceLocator()->get(DocumentService::class);
    }
}
