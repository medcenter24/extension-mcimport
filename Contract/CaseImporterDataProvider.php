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


use medcenter24\mcCore\App\AccidentType;

interface CaseImporterDataProvider
{
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

    // ******* Case data *********//

    public function internalRefNumber(): string;

    public function externalRefNumber(): string;

    public function assistanceTitle(): string;

    public function patientPoliceNumber(): string;

    public function patientName(): string;

    public function patientBirthday(): string;

    public function isFirstVisit(): bool;

    public function visitTime(): string;

    public function visitPlace(): string;

    public function patientSymptoms(): string;

    public function requestReason(): string;

    public function doctorInvestigation(): string;

    public function doctorRecommendation(): string;

    public function doctorDiagnostics(): string;

    public function doctorGender(): string;

    public function doctorName(): string;

    public function doctorMedicalBoardNum(): string;

    public function patientServices(): string;

    public function totalPrice(): string;

    public function caseCreationDate(): string;

    public function accidentType(): AccidentType;

    public function caseableType(): string;
}
