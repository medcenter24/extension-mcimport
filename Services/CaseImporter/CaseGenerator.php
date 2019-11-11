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
use medcenter24\mcCore\App\AccidentAbstract;
use medcenter24\mcCore\App\AccidentStatus;
use medcenter24\mcCore\App\Assistant;
use medcenter24\mcCore\App\DoctorAccident;
use medcenter24\mcCore\App\Exceptions\InconsistentDataException;
use medcenter24\mcCore\App\HospitalAccident;
use medcenter24\mcCore\App\Patient;
use medcenter24\mcCore\App\Payment;
use medcenter24\mcCore\App\Services\AccidentService;
use medcenter24\mcCore\App\Services\AccidentStatusesService;
use medcenter24\mcCore\App\Services\AssistantService;
use medcenter24\mcCore\App\Services\DoctorAccidentService;
use medcenter24\mcCore\App\Services\HospitalAccidentService;
use medcenter24\mcCore\App\Services\PatientService;
use medcenter24\mcCore\App\Services\PaymentService;
use medcenter24\mcCore\App\Services\Core\ServiceLocator\ServiceLocatorTrait;
use medcenter24\McImport\Contract\CaseGeneratorInterface;
use medcenter24\McImport\Contract\CaseImporterDataProvider;

/**
 * Transforms data provider to the new case
 * Class CaseGenerator
 * @package medcenter24\McImport\Services\CaseImporter
 */
class CaseGenerator implements CaseGeneratorInterface
{
    use ServiceLocatorTrait;

    /**
     * @var CaseImporterDataProvider
     */
    private $dataProvider;

    // related models
    /**
     * @var Patient
     */
    private $patient;

    /**
     * @var AccidentAbstract
     */
    private $caseable;

    /**
     * @param CaseImporterDataProvider $dataProvider
     * @return Accident
     * @throws InconsistentDataException
     */
    public function createCase(CaseImporterDataProvider $dataProvider): Accident
    {
        $this->dataProvider = $dataProvider;
        return $this->createAccident();
    }

    private function getDataProvider(): CaseImporterDataProvider
    {
        return $this->dataProvider;
    }

    /**
     * @return Accident
     * @throws InconsistentDataException
     */
    private function createAccident(): Accident
    {
        return $this->getServiceLocator()->get(AccidentService::class)->create([
            'parent_id' => $this->getParentAccidentId(),
            'patient_id' => $this->getPatient()->getAttribute('id'),
            'accident_type_id' => $this->getDataProvider()->accidentType()->getAttribute('id'),
            'accident_status_id' => $this->getAccidentStatus(),
            'assistant_id' => $this->getAssistant()->getAttribute('id'),
            'assistant_ref_num' => $this->getDataProvider()->externalRefNumber(),
            'assistant_invoice_id' => null,
            'assistant_guarantee_id' => null,
            'form_report_id' => null,
            'city_id' => $this->getDataProvider()->city()->getAttribute('id'),
            'caseable_payment_id' => $this->getCaseablePayment()->getAttribute('id'),
            'income_payment_id' => null,
            'assistant_payment_id' => null,
            'caseable_id' => $this->getCaseable()->getAttribute('id'),
            'caseable_type' => get_class($this->getCaseable()),
            'ref_num' => $this->getDataProvider()->internalRefNumber(),
            'title' => $this->getAccidentTitle(),
            'address' => $this->getDataProvider()->visitPlace(),
            'handling_time' => $this->getDataProvider()->caseCreationDate(),
            'contacts' => $this->getDataProvider()->contacts(),
            'symptoms' => $this->getDataProvider()->patientSymptoms(),
            'created_at' => $this->getDataProvider()->caseCreationDate(),
            'updated_at' => $this->getNow(),
        ]);
    }

    /**
     * @return Payment
     */
    private function getCaseablePayment(): Payment
    {
        /** @var PaymentService $paymentService */
        $paymentService = $this->getServiceLocator()->get(PaymentService::class);
        /** @var Payment $payment */
        $payment = $paymentService->create([
            'value' => $this->getDataProvider()->totalPrice(),
            'currency_id' => $this->getDataProvider()->currency()->getAttribute('id'),
        ]);
        return $payment;
    }

    private function getParentAccidentId(): int
    {
        $parent = $this->getDataProvider()->parentAccident();
        return $parent ? $parent->getAttribute('id') : 0;
    }

    private function getPatient(): Patient
    {
        if (!$this->patient) {
            /** @var PatientService $patientService */
            $patientService = $this->getServiceLocator()->get(PatientService::class);
            $this->patient = $patientService->firstOrCreate([
                'name' => $this->getDataProvider()->patientName(),
                'birthday' => $this->getDataProvider()->patientBirthday(),
            ]);
        }
        return $this->patient;
    }

    /**
     * @return AccidentStatus
     */
    private function getAccidentStatus(): AccidentStatus
    {
        /** @var AccidentStatusesService $service */
        $service = $this->getServiceLocator()->get(AccidentStatusesService::class);
        return $service->getImportedStatus();
    }

    private function getAssistant(): Assistant
    {
        /** @var AssistantService $service */
        $service = $this->getServiceLocator()->get(AssistantService::class);
        /** @var Assistant $assistant */
        $assistant = $service->firstOrCreate([
            'title' => $this->getDataProvider()->assistanceTitle(),
        ]);
        return $assistant;
    }

    /**
     * @return AccidentAbstract
     * @throws InconsistentDataException
     */
    private function getCaseable(): AccidentAbstract
    {
        if (!$this->caseable) {
            switch ($this->getDataProvider()->caseableType()) {
                case DoctorAccident::class:
                    $this->caseable = $this->createDoctorAccident();
                    break;

                case HospitalAccident::class:
                    $this->caseable = $this->createHospitalAccident();
                    break;

                default: throw new InconsistentDataException('Undefined caseable type');
            }
        }

        return $this->caseable;
    }

    private function createDoctorAccident(): DoctorAccident
    {
        $doctorAccidentService = $this->getServiceLocator()->get(DoctorAccidentService::class);
        return $doctorAccidentService->create([
            'doctor_id' => $this->getDataProvider()->doctor()->getAttribute('id'),
            'recommendation' => $this->getDataProvider()->doctorRecommendation(),
            'investigation' => $this->getDataProvider()->doctorInvestigation(),
            'visit_time' => $this->getDataProvider()->visitTime(),
            'created_at' => $this->getDataProvider()->caseCreationDate(),
            'updated_at' => $this->getNow(),
        ]);
    }

    private function getNow(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function createHospitalAccident(): HospitalAccident
    {
        /** @var HospitalAccidentService $hospitalAccidentService */
        $hospitalAccidentService = $this->getServiceLocator()->get(HospitalAccidentService::class);
        /** @var HospitalAccident $hospitalAccident */
        $hospitalAccident = $hospitalAccidentService->create([
            'hospital_id' => $this->getDataProvider()->hospitalId(),
        ]);

        return $hospitalAccident;
    }

    private function getAccidentTitle(): string
    {
        $type = $this->getDataProvider()->caseableType() === DoctorAccident::class ? 'doctor' : 'hospital';
        return 'i_'.$this->getDataProvider()->caseCreationDate()
            .'_'.$type
            .'_'.$this->getCaseable()->getAttribute('id');
    }

}
