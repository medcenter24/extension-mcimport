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

namespace medcenter24\McImport\Services\CaseImporter\DocxDataProviders;


use medcenter24\mcCore\App\Exceptions\InconsistentDataException;
use medcenter24\mcCore\App\Helpers\Arr;
use medcenter24\mcCore\App\Services\DomDocumentService;
use medcenter24\mcCore\App\Services\ExtractTableFromArrayService;
use medcenter24\mcCore\App\Services\Core\ServiceLocator\ServiceLocatorTrait;
use medcenter24\McImport\Contract\DocumentReaderService;
use medcenter24\McImport\Exceptions\ImporterException;
use medcenter24\McImport\Providers\DocxReaderServiceProvider;
use medcenter24\McImport\Services\CaseImporter\AbstractCaseImportDataProvider;

/**
 * @fixme probably will need it on the 2016 templates parsing
 *
 *
 * Class TextDataProvider
 * @package medcenter24\McImport\Services\CaseImporter\DocxDataProviders
 */
abstract class TextDataProvider extends AbstractCaseImportDataProvider
{

    use ServiceLocatorTrait;

    /**
     * @return string
     */
    protected function getDocxText(): string
    {
        if (!$this->hasCache('docxText')) {
            /** @var DocumentReaderService $readerService */
            $readerService = $this->getServiceLocator()->get(DocxReaderServiceProvider::class);
            $text = $readerService->getText($this->getPath());
            $this->setCache('docxText', $text);
        }
        return $this->getCache('docxText');
    }

    /**
     * Extends rules with new rules for docx
     * @return array
     */
    protected function getRules(): array
    {
        $rules = parent::getRules();
        $rules['checkTextMarkers'] = [self::RULE_TRUE];
        return $rules;
    }

    /**
     * Root table rules checker
     * @return bool
     * @throws ImporterException
     */
    protected function checkTextMarkers(): bool
    {
        // getting values only
        $markers = array_unique(array_merge(...$this->getTextMarkers()));
        // check that all markers in the text
        foreach ($markers as $marker) {
            $this->throwIfFalse(mb_strpos($this->getDocxText(), $marker), sprintf('Text marker %s has not been found', $marker));
        }

        // check that all markers have correct order
        $pos = 0;
        foreach ($markers as $marker) {
            $this->throwIfFalse( ($pos = mb_strpos($this->getDocxText(), $marker, $pos)), sprintf('Marker %s is not in the order', $marker));
        }
        return true;
    }

    /**
     * List of the text markers
     * @return array
     *
     * @example [
     *      'marker1',
     *      'marker2' in order
     * ]
     */
    abstract protected function getTextMarkers(): array;

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getInternalRefNumber(): string
    {
        return str_replace(' ', '', $this->getMethodDataFromRootTableMap('getInternalRefNumber'));
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getExternalRefNumber(): string
    {
        return str_replace(' ', '', $this->getMethodDataFromRootTableMap('getExternalRefNumber'));
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getPatientContacts(): string
    {
        return $this->getMethodDataFromRootTableMap('getPatientContacts');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getPatientName(): string
    {
        $patientName =  $this->getMethodDataFromRootTableMap('getPatientName');
        $this->throwIfFalse(is_string($patientName), 'Patient name expected to be a string');
        return $patientName;
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getPatientBirthday(): string
    {
        $birthday = $this->getMethodDataFromRootTableMap('getPatientBirthday');
        $this->throwIfFalse(is_string($birthday), 'Birthday expected to be a string');
        return $birthday;
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getVisitTime(): string
    {
        return $this->getMethodDataFromRootTableMap('getVisitTime');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getPatientSymptoms(): string
    {
        return $this->getMethodDataFromRootTableMap('getPatientSymptoms');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getDoctorInvestigation(): string
    {
        return $this->getMethodDataFromRootTableMap('getDoctorInvestigation');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getDoctorRecommendation(): string
    {
        return $this->getMethodDataFromRootTableMap('getDoctorRecommendation');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getDoctorName(): string
    {
        return $this->getMethodDataFromRootTableMap('getDoctorName');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getDoctorMedicalBoardingNum(): string
    {
        return $this->getMethodDataFromRootTableMap('getDoctorMedicalBoardingNum');
    }

    /**
     * @return array
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getDoctorServices(): array
    {
        return $this->getMethodDataFromRootTableMap('getPatientServices');
    }

    /**
     * @return float
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getDoctorPaymentPrice(): float
    {
        return $this->getMethodDataFromRootTableMap('getDoctorPaymentPrice');
    }

    /**
     * @return array
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getParentAccidentMarkers(): array
    {
        return $this->getMethodDataFromRootTableMap('getParentAccidentMarkers');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getHospitalTitle(): string
    {
        return $this->getMethodDataFromRootTableMap('getHospitalTitle');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getCurrency(): string
    {
        return $this->getMethodDataFromRootTableMap('getCurrency');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getAccidentType(): string
    {
        return $this->getMethodDataFromRootTableMap('getAccidentType');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getAssistantTitle(): string
    {
        return $this->getMethodDataFromRootTableMap('getAssistantTitle');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getAssistantAddress(): string
    {
        return $this->getMethodDataFromRootTableMap('getAssistantAddress');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getCaseableType(): string
    {
        return $this->getMethodDataFromRootTableMap('getCaseableType');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getCaseCreationDate(): string
    {
        return $this->getMethodDataFromRootTableMap('getCaseCreationDate');
    }

    /**
     * @return array
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getDoctorDiagnostics(): array
    {
        return $this->getMethodDataFromRootTableMap('getDoctorDiagnostics');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getVisitDate(): string
    {
        return $this->getMethodDataFromRootTableMap('getVisitDate');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getVisitCountry(): string
    {
        return $this->getMethodDataFromRootTableMap('getVisitCountry');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getVisitRegion(): string
    {
        return $this->getMethodDataFromRootTableMap('getVisitRegion');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getVisitCity(): string
    {
        return $this->getMethodDataFromRootTableMap('getVisitCity');
    }

    /**
     * @return string
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function getDoctorGender(): string
    {
        return $this->getMethodDataFromRootTableMap('getDoctorGender');
    }

}
