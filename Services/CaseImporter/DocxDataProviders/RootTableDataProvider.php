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

namespace medcenter24\McImport\Services\CaseImporter\DocxDataProviders;

use ErrorException;
use medcenter24\mcCore\App\Exceptions\InconsistentDataException;
use medcenter24\mcCore\App\Helpers\Arr;
use medcenter24\mcCore\App\Services\Core\Cache\ArrayCacheTrait;
use medcenter24\mcCore\App\Services\DomDocumentService;
use medcenter24\mcCore\App\Services\ExtractTableFromArrayService;
use medcenter24\mcCore\App\Services\Core\ServiceLocator\ServiceLocatorTrait;
use medcenter24\McImport\Exceptions\ImporterException;
use medcenter24\McImport\Providers\DocxReaderServiceProvider;
use medcenter24\McImport\Services\CaseImporter\AbstractDocxCaseImportDataProvider;

abstract class RootTableDataProvider extends AbstractDocxCaseImportDataProvider
{
    use ServiceLocatorTrait;
    use ArrayCacheTrait;

    /**
     * One of the markers
     */
    public const PARENT_ACCIDENT_MARKER_INTERNAL_REF_NUM = 'internal_ref_num';

    /**
     * @return mixed
     * @throws InconsistentDataException
     */
    protected function getDocxRoot(): mixed
    {
        if (!$this->hasCache('docx')) {
            /** @var ExtractTableFromArrayService $extractor */
            $extractor = $this->getServiceLocator()->get(ExtractTableFromArrayService::class, [
                ExtractTableFromArrayService::CONFIG_TABLE => ['w:tbl'],
                ExtractTableFromArrayService::CONFIG_ROW => ['w:tr'],
                ExtractTableFromArrayService::CONFIG_CEIL => ['w:tc'],
            ]);
            $readerService = $this->getServiceLocator()->get(DocxReaderServiceProvider::class);
            $dom = $readerService->getDom($this->getPath());
            $domService = $this->getServiceLocator()->get(DomDocumentService::class, [
                DomDocumentService::STRIP_STRING => true,
                DomDocumentService::CONFIG_WITHOUT_ATTRIBUTES => true,
            ]);
            $domArray = $domService->toArray($dom);
            $extracted = $extractor->extract($domArray);
            $this->setCache('docx', $extracted);
        }
        return $this->getCache('docx');
    }

    /**
     * @return mixed
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    protected function getRootTables(): mixed
    {
        if (!$this->hasCache('rootTable')) {
            $tables = $this->getDocxRoot();
            $this->throwIfFalse(is_array($tables) && array_key_exists(ExtractTableFromArrayService::TABLES, $tables),
                'Incorrect root tables parsing');
            $this->setCache('rootTable', Arr::recursiveValues($tables[ExtractTableFromArrayService::TABLES]));
        }
        return $this->getCache('rootTable');
    }

    /**
     * Extends rules with new rules for docx
     * @return array
     */
    protected function getRules(): array
    {
        $rules = parent::getRules();
        $rules['checkRootTable'] = [self::RULE_TRUE];
        return $rules;
    }

    /**
     * Root table rules checker
     * @return bool
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    protected function checkRootTable(): bool
    {
        $map = $this->rootTableMap();
        $this->throwIfFalse(array_key_exists('checkPoints', $map), 'Checkpoints not set in the root table map');
        foreach ($map['checkPoints'] as $checkpoint) {
            try {
                $this->throwIfFalse(Arr::keysExists($this->getRootTables(), $checkpoint['path']),
                    'Root table address ' . implode(',', $checkpoint['path']) . ' not found ["'.$checkpoint['value'].'"]');
                $this->throwIfFalse($this->getRootTableData($checkpoint['path']) === $checkpoint['value'],
                    sprintf('Root table checkpoint not matched, "%s" != stored "%s"',
                        $this->getRootTableData($checkpoint['path']), $checkpoint['value']));
            } catch (ErrorException $e) {
                // php exceptions
                $this->throwIfFalse(false, sprintf('An exception was triggered message: "%s", checkpoint: "%s", path: "%s"',
                    $e->getMessage(), $checkpoint['value'], implode(',', $checkpoint['path'])));
            }
        }
        return true;
    }

    /**
     * @param array $path
     * @return mixed
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    protected function getRootTableData(array $path): mixed
    {
        $table = $this->getRootTables();
        foreach ($path as $key) {
            if (!is_array($table)) {
                $this->throwIfFalse(is_array($table),
                    sprintf('Trying to get value from the string: "%s", but have to be root table', $table));
            }
            $this->throwIfFalse(array_key_exists($key, $table), 'Can not load a data from the root table for the path ' . implode(',', $path));
            $table = $table[$key];
        }
        return $table;
    }

    /**
     * Configuration of the document structure
     *
     * @example
     * [
     *      // static data for the documents
     *      'checkpoints' => [
     *          'value' => 'something that has to be there', // string to compare with stored data
     *          'path' => [0, 0, 0], // root table path $rootTable[0][0][0]
     *          'type' => 'source|array' // array will be converted to string, source will be returned as it is
     *      ],
     *      // changing data in the doc template where data could be taken from
     *      'methods' => [
     *          'path' => [0, 0, 0], // root table path $rootTable[0][0][0]
     *          'type' => 'source|array' // array will be converted to string
     *      ]
     * ]
     *
     * @return array
     */
    abstract protected function rootTableMap(): array;

    /**
     * Getting data from the root table map
     * @param string $methodName
     * @return mixed
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    private function getMethodDataFromRootTableMap(string $methodName): mixed
    {
        $key = 'rootMap_'.$methodName;
        if (!$this->hasCache($key)) {
            $map = $this->rootTableMap();
            $this->throwIfFalse(array_key_exists('methods', $map), 'Root table map has not been configured');
            $methods = $map['methods'];
            $this->throwIfFalse(array_key_exists($methodName, $methods),
                sprintf('Method "%s" not defined in the root table map', $methodName));
            $method = $methods[$methodName];
            $data = $this->getRootTableData($method['path']);
            if ($method['type'] === 'array') {
                $data = Arr::multiArrayToString($data);
            }
            $this->setCache($key, $data);
        }
        return $this->getCache($key);
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getInternalRefNumber(): string
    {
        return str_replace(' ', '', $this->getStringFromRootTableMap('getInternalRefNumber'));
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getExternalRefNumber(): string
    {
        return str_replace(' ', '', $this->getStringFromRootTableMap('getExternalRefNumber'));
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getPatientContacts(): string
    {
        return $this->getStringFromRootTableMap('getPatientContacts');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getPatientName(): string
    {
        return $this->getStringFromRootTableMap('getPatientName');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getPatientBirthday(): string
    {
        return $this->getStringFromRootTableMap('getPatientBirthday');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getVisitTime(): string
    {
        return $this->getStringFromRootTableMap('getVisitTime');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getPatientSymptoms(): string
    {
        return $this->getStringFromRootTableMap('getPatientSymptoms');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    protected function getDoctorInvestigation(): string
    {
        return $this->getStringFromRootTableMap('getDoctorInvestigation');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getDoctorRecommendation(): string
    {
        return $this->getStringFromRootTableMap('getDoctorRecommendation');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getDoctorName(): string
    {
        return $this->getStringFromRootTableMap('getDoctorName');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getDoctorMedicalBoardingNum(): string
    {
        return $this->getStringFromRootTableMap('getDoctorMedicalBoardingNum');
    }

    /**
     * @return array
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getDoctorServices(): array
    {
        return $this->getArrayFromRootTableMap('getPatientServices');
    }

    /**
     * @return float
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getIncomePrice(): float
    {
        return (float) $this->getStringFromRootTableMap('getIncomePrice');
    }

    /**
     * @return array
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getParentAccidentMarkers(): array
    {
        return $this->getArrayFromRootTableMap('getParentAccidentMarkers');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getHospitalTitle(): string
    {
        return $this->getStringFromRootTableMap('getHospitalTitle');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getCityTitle(): string
    {
        return $this->getStringFromRootTableMap('getCityTitle');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getCurrency(): string
    {
        return $this->getStringFromRootTableMap('getCurrency');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getAccidentType(): string
    {
        return $this->getStringFromRootTableMap('getAccidentType');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getAssistantTitle(): string
    {
        return $this->getStringFromRootTableMap('getAssistantTitle');
    }

    /**
     * @param string $method
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    protected function getStringFromRootTableMap(string $method): string
    {
        $res = $this->getMethodDataFromRootTableMap($method);
        $this->throwIfFalse(is_string($res), $method. ' expects string');
        return $res;
    }

    /**
     * @param string $method
     * @return array
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    protected function getArrayFromRootTableMap(string $method): array
    {
        $res = $this->getMethodDataFromRootTableMap($method);
        $this->throwIfFalse(is_array($res), $method. ' expects array');
        return $res;
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getAssistantAddress(): string
    {
        return $this->getStringFromRootTableMap('getAssistantAddress');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getCaseableType(): string
    {
        return $this->getStringFromRootTableMap('getCaseableType');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getCaseCreationDate(): string
    {
        return $this->getStringFromRootTableMap('getCaseCreationDate');
    }

    /**
     * @return array
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getDoctorDiagnostics(): array
    {
        return $this->getArrayFromRootTableMap('getDoctorDiagnostics');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getVisitDate(): string
    {
        return $this->getStringFromRootTableMap('getVisitDate');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getVisitCountry(): string
    {
        return $this->getStringFromRootTableMap('getVisitCountry');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getVisitRegion(): string
    {
        return $this->getStringFromRootTableMap('getVisitRegion');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getVisitCity(): string
    {
        return $this->getStringFromRootTableMap('getVisitCity');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getDoctorGender(): string
    {
        return $this->getStringFromRootTableMap('getDoctorGender');
    }

    /**
     * @return bool
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function isReappointment(): bool
    {
        $markers = $this->getParentAccidentMarkers();
        return array_key_exists(self::PARENT_ACCIDENT_MARKER_INTERNAL_REF_NUM, $markers);
    }

}
