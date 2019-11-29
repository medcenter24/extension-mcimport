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

namespace medcenter24\McImport\Tests\Feature\CaseImporter;


use medcenter24\mcCore\App\DoctorAccident;
use medcenter24\McImport\Contract\CaseImporterDataProvider;

class TestCaseImportDataProvider implements CaseImporterDataProvider
{
    /**
     * @var array
     */
    private $images = [];

    public function init(string $path): CaseImporterDataProvider
    {
        return $this;
    }

    public function isFit(): bool
    {
        return true;
    }

    public function getFileExtensions(): array
    {
        return ['.test'];
    }

    public function getExcludeRules(): array
    {
        return [];
    }

    public function isStoreErrors(): bool
    {
        return false;
    }

    public function getErrors(): array
    {
        return [];
    }

    public function getInternalRefNumber(): string
    {
        return 'aaa-inter-ref';
    }

    public function getExternalRefNumber(): string
    {
        return 'bbb-ext-ref';
    }

    public function getAssistantTitle(): string
    {
        return 'assist title';
    }

    public function getAssistantAddress(): string
    {
        return 'assist address';
    }

    public function getPatientContacts(): string
    {
        return 'patients contacts';
    }

    public function getPatientName(): string
    {
        return 'Patient Name';
    }

    public function getPatientBirthday(): string
    {
        return '20.05.1989';
    }

    public function getVisitTime(): string
    {
        return '12:40';
    }

    public function getPatientSymptoms(): string
    {
        return 'Symptoms of the patient';
    }

    public function getDoctorInvestigation(): string
    {
        return 'investigations made by the doctor';
    }

    public function getDoctorRecommendation(): string
    {
        return 'recommendations made by the doctor';
    }

    public function getDoctorDiagnostics(): array
    {
        return [
            ['title' => 'diag 1', 'category' => 'd1', 'description' => 'test'],
            ['title' => 'diag 2', 'category' => 'd2', 'description' => 'test'],
            ['title' => 'diag 1', 'category' => 'd1', 'description' => 'test'],
        ];
    }

    public function getDoctorName(): string
    {
        return 'Doctor Name';
    }

    public function getDoctorGender(): string
    {
        return 'female';
    }

    public function getDoctorMedicalBoardingNum(): string
    {
        return 'board-num';
    }

    public function getDoctorServices(): array
    {
        return [
            ['title' => 's1', 'price' => 20.0, 'description' => 'test'],
            ['title' => 's2', 'price' => 30.0, 'description' => 'test'],
            ['title' => 's1', 'price' => 40.0, 'description' => 'test'],
        ];
    }

    public function getDoctorPaymentPrice(): float
    {
        return 70.0;
    }

    public function getCaseCreationDate(): string
    {
        return '11.01.2011';
    }

    public function getAccidentType(): string
    {
        return 'insurance';
    }

    public function getCaseableType(): string
    {
        return DoctorAccident::class;
    }

    public function getParentAccidentMarkers(): array
    {
        return [];
    }

    public function getHospitalTitle(): string
    {
        return '';
    }

    public function getCurrency(): string
    {
        return '€';
    }

    public function getVisitDate(): string
    {
        return '22.11.2019';
    }

    public function getVisitCountry(): string
    {
        return 'SPAIN';
    }

    public function getVisitRegion(): string
    {
        return 'COSTA DoRADA';
    }

    public function getVisitCity(): string
    {
        return 'BARCELONA';
    }

    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * for the test only
     * @param array $images
     */
    public function setImages(array $images): void
    {
        $this->images = $images;
    }

    public function isReappointment(): bool
    {
        return false;
    }

    public function getAdditionalDoctorInvestigation(): string
    {
        return 't 36.6 C';
    }

    public function getDefaultDoctorData(): array
    {
        return [
            'name' => 'Doc Name',
            'description' => 'doc info',
            'ref_key' => 'dn',
            'gender' => 'male',
            'medical_board_num' => '123123123',
        ];
    }

    public function getDoctorSurveys(): array
    {
        return [
            [
                'title' => 'General condition is satisfactory.',
                'description' => 'test',
                'disease_code' => ''
            ],
            [
                'title' => 'Heart tones are rhythmic, no pathological noise.',
                'description' => 'test',
                'disease_code' => ''
            ],
            [
                'title' => 'Neurological status is normal.',
                ''
            ],
            [
                'title' => 'Otherwise, there is no pathology.',
                'description' => 'test'
            ],
            [
                'title' => 'Otherwise, there is no pathology.',
                'description' => 'test'
            ],
            [
                'title' => 'Other wise, th  ere is no patho,,logy1234.',
                'description' => 'test'
            ],
        ];
    }

    /**
     * Turn debug mode on
     */
    public function debugModeOn(): void
    {
    }

    /**
     * Turn off debug mode
     */
    public function debugModeOff(): void
    {
    }

    /**
     * @param string $channel
     */
    public function setLogChannel(string $channel): void
    {
    }

    /**
     * Write to the log
     * @param string $msg
     */
    public function log(string $msg): void
    {
    }
}
