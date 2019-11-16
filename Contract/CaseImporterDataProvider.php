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

namespace medcenter24\McImport\Contract;


/**
 * Data provider returns source data only
 * Everything else should create or update controllers or responsible service
 * Interface CaseImporterDataProvider
 * @package medcenter24\McImport\Contract
 */
interface CaseImporterDataProvider
{
    /**
     * One of the markers
     */
    public const PARENT_ACCIDENT_MARKER_INTERNAL_REF_NUM = 'internal_ref_num';

    /**
     * Initialize data provider
     * @param string $path
     * @return $this
     */
    public function init(string $path): self;

    /**
     * Check that this data provider could get data from the initialized document
     * checks that all checkpoints on the expected places
     * @return bool
     */
    public function isFit(): bool;

    /**
     * Each data provider knows which files it can take
     * @return array
     */
    public function getFileExtensions(): array;

    /**
     * Rules to skip import
     * @return array
     */
    public function getExcludeRules(): array;

    /**
     * Check if we have to store errors
     * @return bool
     */
    public function isStoreErrors(): bool;

    /**
     * Stored errors after the import
     * @return array
     */
    public function getErrors(): array;

    // ******* Case data *********//

    /**
     * Internal Ref Number
     * @return string
     */
    public function getInternalRefNumber(): string;

    /**
     * External (Assistant) Ref Number
     * @return string
     */
    public function getExternalRefNumber(): string;

    /**
     * Title of the assistant company
     * @return string
     */
    public function getAssistantTitle(): string;

    /**
     * Address of the assistant company
     * @return string
     */
    public function getAssistantAddress(): string;

    /**
     * Contacts or additional information about the patient
     * @return string
     */
    public function getPatientContacts(): string;

    /**
     * Name of the patient
     * @return string
     */
    public function getPatientName(): string;

    /**
     * Birthday of the patient
     * @return string
     */
    public function getPatientBirthday(): string;

    /**
     * Visit Time and Date
     * @example string '2017-08-13 11:29:54'
     * @return string
     */
    public function getVisitTime(): string;

    /**
     * Symptoms of the patient
     * @return string
     */
    public function getPatientSymptoms(): string;

    /**
     * Additional investigation from the doctor
     * @return string
     */
    public function getDoctorInvestigation(): string;

    /**
     * Recommendation from the doctor to the patient
     * @return string
     */
    public function getDoctorRecommendation(): string;

    /**
     * List of diagnostics, that the doctor did
     * @return array
     * @example [
     *  'title' => 'diagnosed name',
     *  'category' => 'A00'
     * ]
     */
    public function getDoctorDiagnostics(): array;

    /**
     * Name of the doctor
     * @return string
     */
    public function getDoctorName(): string;

    /**
     * Gender of the doctor
     * @return string
     */
    public function getDoctorGender(): string;

    /**
     * Doctors boarding number
     * @return string
     */
    public function getDoctorMedicalBoardingNum(): string;

    /**
     * Doctor services
     * @return array
     */
    public function getDoctorServices(): array;

    /**
     * Total price of the case
     * @return float
     */
    public function getTotalPrice(): float;

    /**
     * Date of the creation
     * @return string
     */
    public function getCaseCreationDate(): string;

    /**
     * Accident Type
     * @example insurance or non-insurance
     * @return string
     */
    public function getAccidentType(): string;

    /**
     * Doctor or Hospital accident
     * @example DoctorCase::class or HospitalCase::class
     * @return string
     */
    public function getCaseableType(): string;

    /**
     * If we can get markers which allow us to define parent case
     * @return array
     */
    public function getParentAccidentMarkers(): array;

    /**
     * Name of the city
     * @return string
     */
    public function getCityTitle(): string;

    /**
     * Hospital name
     * @return string
     */
    public function getHospitalTitle(): string;

    /**
     * Currency of the case
     * @return string
     */
    public function getCurrency(): string;

    /**
     * @return string
     */
    public function getVisitDate(): string;

    /**
     * @return string
     */
    public function getVisitCountry(): string;

    /**
     * @return string
     */
    public function getVisitRegion(): string;

    /**
     * @return string
     */
    public function getVisitCity(): string;

    /**
     * @return array
     */
    public function getImages(): array;

    /**
     * If the case is the visit for the patient that has already been visited
     * @return bool
     */
    public function isReappointment(): bool;
}
