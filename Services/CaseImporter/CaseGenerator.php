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


use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use medcenter24\mcCore\App\Accident;
use medcenter24\mcCore\App\AccidentAbstract;
use medcenter24\mcCore\App\AccidentStatus;
use medcenter24\mcCore\App\AccidentType;
use medcenter24\mcCore\App\Assistant;
use medcenter24\mcCore\App\City;
use medcenter24\mcCore\App\Country;
use medcenter24\mcCore\App\Doctor;
use medcenter24\mcCore\App\DoctorAccident;
use medcenter24\mcCore\App\DoctorSurvey;
use medcenter24\mcCore\App\Document;
use medcenter24\mcCore\App\Exceptions\InconsistentDataException;
use medcenter24\mcCore\App\FinanceCurrency;
use medcenter24\mcCore\App\Helpers\FileHelper;
use medcenter24\mcCore\App\Helpers\StrHelper;
use medcenter24\mcCore\App\Hospital;
use medcenter24\mcCore\App\HospitalAccident;
use medcenter24\mcCore\App\Patient;
use medcenter24\mcCore\App\Payment;
use medcenter24\mcCore\App\Region;
use medcenter24\mcCore\App\Services\AbstractModelService;
use medcenter24\mcCore\App\Services\AccidentService;
use medcenter24\mcCore\App\Services\AccidentStatusesService;
use medcenter24\mcCore\App\Services\AccidentTypeService;
use medcenter24\mcCore\App\Services\AssistantService;
use medcenter24\mcCore\App\Services\CityService;
use medcenter24\mcCore\App\Services\Core\Logger\DebugLoggerTrait;
use medcenter24\mcCore\App\Services\CountryService;
use medcenter24\mcCore\App\Services\CurrencyService;
use medcenter24\mcCore\App\Services\DiagnosticService;
use medcenter24\mcCore\App\Services\DoctorAccidentService;
use medcenter24\mcCore\App\Services\DoctorServiceService;
use medcenter24\mcCore\App\Services\DoctorsService;
use medcenter24\mcCore\App\Services\DoctorSurveyService;
use medcenter24\mcCore\App\Services\DocumentService;
use medcenter24\mcCore\App\Services\File\TmpFileService;
use medcenter24\mcCore\App\Services\HospitalAccidentService;
use medcenter24\mcCore\App\Services\HospitalService;
use medcenter24\mcCore\App\Services\PatientService;
use medcenter24\mcCore\App\Services\PaymentService;
use medcenter24\mcCore\App\Services\Core\ServiceLocator\ServiceLocatorTrait;
use medcenter24\mcCore\App\Services\RegionService;
use medcenter24\mcCore\App\Services\UserService;
use medcenter24\mcCore\App\User;
use medcenter24\McImport\Contract\CaseGeneratorInterface;
use medcenter24\McImport\Contract\CaseImporterDataProvider;
use medcenter24\McImport\Exceptions\CaseGeneratorException;
use Spatie\MediaLibrary\Exceptions\FileCannotBeAdded\DiskDoesNotExist;
use Spatie\MediaLibrary\Exceptions\FileCannotBeAdded\FileDoesNotExist;
use Spatie\MediaLibrary\Exceptions\FileCannotBeAdded\FileIsTooBig;
use Illuminate\Http\UploadedFile;

/**
 * Transforms data provider to the new case
 * Class CaseGenerator
 * @package medcenter24\McImport\Services\CaseImporter
 */
class CaseGenerator implements CaseGeneratorInterface
{
    use ServiceLocatorTrait;
    use DebugLoggerTrait;

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
     * Current create date to use the same time
     * @var string
     */
    private $currentTime;

    /**
     * User which created for the importer purposes
     * @var User
     */
    private $user;

    /**
     * @param CaseImporterDataProvider $dataProvider - initialized data provider to get data from
     * @return Accident
     * @throws CaseGeneratorException
     */
    public function createCase(CaseImporterDataProvider $dataProvider): Accident
    {
        $this->dataProvider = $dataProvider;

        $this->checkData();
        try {
            $accident = $this->createAccident();
        } catch (InconsistentDataException $e) {
            throw new CaseGeneratorException($e->getMessage());
        }
        $this->addServices($accident);
        $this->addDiagnostics($accident);
        $this->addSurveys($accident);
        $this->addDocuments($accident);

        return $accident;
    }

    /**
     * Throws an exception if this data can't be imported
     * @throws CaseGeneratorException
     */
    private function checkData(): void
    {
        $this->checkRefNumDuplication();
    }

    /**
     * @throws CaseGeneratorException
     */
    private function checkRefNumDuplication(): void
    {
        /** @var AccidentService $accidentService */
        $accidentService = $this->getServiceLocator()->get(AccidentService::class);
        if ($accidentService->count([
            'ref_num' => $this->getDataProvider()->getInternalRefNumber()
        ])) {
            throw new CaseGeneratorException('This referral number already used');
        }
    }

    private function getImporterUser(): User
    {
        if (!$this->user) {
            /** @var UserService $userService */
            $userService = $this->getServiceLocator()->get(UserService::class);
            $this->user = $userService->firstOrCreate([
                'name' => 'Importer',
                'email' => 'importer@medcenter24.com',
            ]);
        }
        return $this->user;
    }

    /**
     * @return CaseImporterDataProvider
     * @throws CaseGeneratorException
     */
    private function getDataProvider(): CaseImporterDataProvider
    {
        if (!$this->dataProvider) {
            throw new CaseGeneratorException('Data provider is not defined');
        }
        return $this->dataProvider;
    }

    /**
     * @return Accident
     * @throws CaseGeneratorException
     * @throws InconsistentDataException
     */
    private function createAccident(): Accident
    {
        /** @var AccidentService $accidentService */
        $accidentService = $this->getServiceLocator()->get(AccidentService::class);
        /** @var Accident $accident */
        $accident = $accidentService->create([
            'created_by' => $this->getImporterUser()->getAttribute('id'),
            'parent_id' => $this->getParent() ? $this->getParent()->getAttribute('id') : '',
            'patient_id' => $this->getPatient()->getAttribute('id'),
            'accident_type_id' => $this->getAccidentType()->getAttribute('id'),
            'assistant_id' => $this->getAssistant()->getAttribute('id'),
            'assistant_ref_num' => $this->getDataProvider()->getExternalRefNumber(),
            'assistant_invoice_id' => null,
            'assistant_guarantee_id' => null,
            'form_report_id' => null,
            'city_id' => $this->getCity()->getAttribute('id'),
            'caseable_payment_id' => $this->getCaseablePayment()->getAttribute('id'),
            'income_payment_id' => null,
            'assistant_payment_id' => null,
            'caseable_id' => $this->getCaseable()->getAttribute('id'),
            'caseable_type' => $this->getDataProvider()->getCaseableType(),
            'ref_num' => $this->getDataProvider()->getInternalRefNumber(),
            'title' => $this->getAccidentTitle(),
            'address' => '',
            'handling_time' => Carbon::parse($this->getDataProvider()->getCaseCreationDate()),
            'contacts' => $this->getDataProvider()->getPatientContacts(),
            'symptoms' => $this->getDataProvider()->getPatientSymptoms(),
            'created_at' => Carbon::parse($this->getDataProvider()->getCaseCreationDate()),
            'updated_at' => $this->getUpdatedDate(),
        ]);

        // first status always `new`, now we need to add status `imported`
        $accidentService->setStatus($accident, $this->getAccidentStatus());
        return $accident;
    }

    /**
     * @return Accident|null
     * @throws CaseGeneratorException
     */
    private function getParent(): ?Accident
    {
        $accident = null;
        $markers = $this->getDataProvider()->getParentAccidentMarkers();
        if (count($markers)) {
            /** @var AccidentService $accidentService */
            $accidentService = $this->getServiceLocator()->get(AccidentService::class);
            if (array_key_exists('assistantRefNum', $markers)) {
                $accident = $accidentService->getByAssistantRefNum($markers['assistantRefNum']);
            }
            if (!$accident) {
                throw new CaseGeneratorException('Parent accident must be imported before current accident.');
            }
        }
        return $accident;
    }

    /**
     * @return Patient
     * @throws CaseGeneratorException
     */
    private function getPatient(): Patient
    {
        if (!$this->patient) {
            /** @var PatientService $patientService */
            $patientService = $this->getServiceLocator()->get(PatientService::class);
            $this->patient = $patientService->firstOrCreate([
                'name' => $this->getDataProvider()->getPatientName(),
                'birthday' => Carbon::parse($this->getDataProvider()->getPatientBirthday()),
            ]);
        }
        return $this->patient;
    }

    /**
     * @return AccidentType
     * @throws CaseGeneratorException
     */
    private function getAccidentType(): AccidentType
    {
        $type = $this->getDataProvider()->getAccidentType();
        /** @var AccidentTypeService $accidentTypeService */
        $accidentTypeService = $this->getServiceLocator()->get(AccidentTypeService::class);
        /** @var AccidentType $accidentType */
        $accidentType = $accidentTypeService->firstOrCreate([
            'title' => $type,
        ]);
        return $accidentType;
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

    /**
     * @return Assistant
     * @throws CaseGeneratorException
     */
    private function getAssistant(): Assistant
    {
        /** @var AssistantService $service */
        $service = $this->getServiceLocator()->get(AssistantService::class);
        /** @var Assistant $assistant */
        $assistant = $service->firstOrCreate([
            'title' => $this->getDataProvider()->getAssistantTitle(),
        ]);
        if (!$assistant->getAttribute('comment') && $this->getDataProvider()->getAssistantAddress()) {
            $assistant->update([
                'comment' => $this->getDataProvider()->getAssistantAddress(),
            ]);
        }
        return $assistant;
    }

    /**
     * @return City
     * @throws CaseGeneratorException
     */
    private function getCity(): City
    {
        /** @var CityService $cityService */
        $cityService = $this->getServiceLocator()->get(CityService::class);
        /** @var City $city */
        $city = $cityService->firstOrCreate([
            'title' => Str::title($this->getDataProvider()->getVisitCity()),
        ]);

        // trying to create or find country and region
        if (!$city->getAttribute('region') && $this->getDataProvider()->getVisitRegion()) {
            /** @var CountryService $countryService */
            $countryService = $this->getServiceLocator()->get(CountryService::class);
            /** @var Country $country */
            $country = $countryService->firstOrCreate([
                'title' => Str::title($this->getDataProvider()->getVisitCountry()),
            ]);

            /** @var RegionService $regionService */
            $regionService = $this->getServiceLocator()->get(RegionService::class);
            /** @var Region $region */
            $region = $regionService->firstOrCreate([
                'title' => Str::title($this->getDataProvider()->getVisitRegion()),
                'country_id' => $country->getAttribute('id'),
            ]);

            $city->region()->associate($region);
            $city->save();
        }

        return $city;
    }

    /**
     * @return Payment
     * @throws CaseGeneratorException
     */
    private function getCaseablePayment(): Payment
    {
        /** @var PaymentService $paymentService */
        $paymentService = $this->getServiceLocator()->get(PaymentService::class);
        /** @var Payment $payment */
        $payment = $paymentService->create([
            'value' => $this->getDataProvider()->getDoctorPaymentPrice(),
            'currency_id' => $this->getCurrency()->getAttribute('id'),
            'fixed' => 1,
            'created_by' => $this->getImporterUser()->getAttribute('id'),
        ]);
        return $payment;
    }

    /**
     * @return FinanceCurrency
     * @throws CaseGeneratorException
     */
    private function getCurrency(): FinanceCurrency
    {
        /** @var CurrencyService $currencyService */
        $currencyService = $this->getServiceLocator()->get(CurrencyService::class);
        $currencySymbol = $this->getDataProvider()->getCurrency();
        return $currencyService->byMarker($currencySymbol);
    }

    /**
     * @return AccidentAbstract
     * @throws CaseGeneratorException
     * @throws InconsistentDataException
     */
    private function getCaseable(): AccidentAbstract
    {
        if (!$this->caseable) {
            switch ($this->getDataProvider()->getCaseableType()) {
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

    /**
     * @return DoctorAccident
     * @throws CaseGeneratorException
     */
    private function createDoctorAccident(): DoctorAccident
    {
        $doctorAccidentService = $this->getServiceLocator()->get(DoctorAccidentService::class);
        return $doctorAccidentService->create([
            'doctor_id' => $this->getDoctor()->getAttribute('id'),
            'recommendation' => $this->getDataProvider()->getDoctorRecommendation(),
            // other investigations stored in surveys
            'investigation' => $this->getDataProvider()->getAdditionalDoctorInvestigation(),
            'visit_time' => Carbon::parse($this->getDataProvider()->getCaseCreationDate()),
            'created_at' => Carbon::parse($this->getDataProvider()->getCaseCreationDate()),
            'updated_at' => Carbon::parse($this->getUpdatedDate()),
        ]);
    }

    /**
     * @return Doctor
     * @throws CaseGeneratorException
     */
    private function getDoctor(): Doctor
    {
        /** @var DoctorsService $doctorService */
        $doctorService = $this->getServiceLocator()->get(DoctorsService::class);
        $doctor = $doctorService->first([
            'name' => $this->getDataProvider()->getDoctorName(),
        ]);
        if (!$doctor) {
            if ($this->getDataProvider()->getDoctorName()) {
                $doctorsData = [
                    'name' => $this->getDataProvider()->getDoctorName(),
                    'description' => '',
                    'ref_key' => '',
                    'gender' => $this->getDataProvider()->getDoctorGender(),
                    'medical_board_num' => $this->getDataProvider()->getDoctorMedicalBoardingNum(),
                ];
            } else {
                $doctorsData = $this->getDataProvider()->getDefaultDoctorData();
            }
            /** @var Doctor $doctor */
            $doctor = $doctorService->create($doctorsData);
        }
        return $doctor;
    }

    /**
     * @return string
     */
    private function getUpdatedDate(): string
    {
        if (!$this->currentTime) {
            $this->currentTime = Carbon::now()->format('Y-m-d H:i:s');
        }
        return Carbon::parse($this->currentTime);
    }

    /**
     * @param string $updatedTime
     */
    public function setUpdatedDate(string $updatedTime): void
    {
        $this->currentTime = $updatedTime;
    }

    /**
     * @return HospitalAccident
     * @throws CaseGeneratorException
     */
    private function createHospitalAccident(): HospitalAccident
    {
        /** @var HospitalAccidentService $hospitalAccidentService */
        $hospitalAccidentService = $this->getServiceLocator()->get(HospitalAccidentService::class);
        /** @var HospitalAccident $hospitalAccident */
        $hospitalAccident = $hospitalAccidentService->create([
            'hospital_id' => $this->getHospital(),
        ]);

        return $hospitalAccident;
    }

    /**
     * @return Hospital
     * @throws CaseGeneratorException
     */
    private function getHospital(): Hospital
    {
        /** @var HospitalService $hospitalService */
        $hospitalService = $this->getServiceLocator()->get(HospitalService::class);
        /** @var Hospital $hospital */
        $hospital = $hospitalService->firstOrCreate([
            'title' => $this->getDataProvider()->getHospitalTitle()
        ]);
        return $hospital;
    }

    /**
     * @return string
     * @throws CaseGeneratorException
     * @throws InconsistentDataException
     */
    private function getAccidentTitle(): string
    {
        $type = $this->getDataProvider()->getCaseableType() === DoctorAccident::class ? 'doctor' : 'hospital';
        return 'i_'.preg_replace('/\D/', '', $this->getDataProvider()->getCaseCreationDate())
            .'_'.$type
            .'_'.$this->getCaseable()->getAttribute('id');
    }

    /**
     * To attach related data to the morphToMany relations
     * @param array $ids
     * @param MorphToMany $model
     */
    private function bindMorphed(array $ids, MorphToMany $model): void
    {
        if (count($ids)) {
            $allObjs = array_unique($ids);
            $model->attach($allObjs);
        }
    }

    /**
     * @param array $dataList
     * @param AbstractModelService $service
     * @return array
     * @throws CaseGeneratorException
     */
    private function createFormattedDocResourcesIds(array $dataList, AbstractModelService $service): array
    {
        $allObjs = [];
        if (count($dataList)) {
            foreach ($dataList as $dataItem) {

                if (!is_array($dataItem) ) {
                    throw new CaseGeneratorException('Array expected "' . print_r($dataItem, 1) . '"');
                }
                if (!array_key_exists('title', $dataItem)) {
                    throw new CaseGeneratorException('Undefined title of the resource');
                }

                $obj = $service->firstOrCreate([
                    'created_by' => $this->getImporterUser()->getAttribute('id'),
                    'title' => $dataItem['title'],
                    'description' => $dataItem['description'] ?? '',
                    'disease_code' => $dataItem['disease_code'] ?? '',
                ]);
                $allObjs[] = $obj->getAttribute('id');
            }
        }

        return $allObjs;
    }

    /**
     * @param Accident $accident
     * @throws CaseGeneratorException
     */
    private function addServices(Accident $accident): void
    {
        /** @var array $dataList */
        $dataList = $this->getDataProvider()->getDoctorServices();
        /** @var AbstractModelService|DoctorServiceService $service */
        $service = $this->getServiceLocator()->get(DoctorServiceService::class);
        /** @var MorphToMany $model */
        $model = $accident->services();

        $ids = $this->createFormattedDocResourcesIds($dataList, $service);
        $this->bindMorphed($ids, $model);
    }

    /**
     * @param Accident $accident
     * @throws CaseGeneratorException
     */
    private function addDiagnostics(Accident $accident): void
    {
        /** @var array $dataList */
        $dataList = $this->getDataProvider()->getDoctorDiagnostics();
        /** @var AbstractModelService|DiagnosticService $service */
        $service = $this->getServiceLocator()->get(DiagnosticService::class);
        /** @var MorphToMany $model */
        $model = $accident->diagnostics();
        $ids = $this->createFormattedDocResourcesIds($dataList, $service);
        $this->bindMorphed($ids, $model);
    }

    /**
     * @param Accident $accident
     * @throws CaseGeneratorException
     */
    private function addSurveys(Accident $accident): void
    {
        $dataList = $this->getDataProvider()->getDoctorSurveys();
        /** @var DoctorSurveyService $service */
        $service = $this->getServiceLocator()->get(DoctorSurveyService::class);
        $model = $accident->surveys();

        // we have to exclude duplications
        $allObjs = [];
        if (count($dataList)) {
            foreach ($dataList as $dataItem) {

                if (!is_array($dataItem) ) {
                    throw new CaseGeneratorException('Array expected "' . print_r($dataItem, 1) . '"');
                }
                if (!array_key_exists('title', $dataItem)) {
                    throw new CaseGeneratorException('Undefined title of the resource');
                }

                /** @var DoctorSurvey $obj */
                $obj = $service->byTitleLettersOrCreate([
                    'created_by' => $this->getImporterUser()->getAttribute('id'),
                    'title' => $dataItem['title'],
                    'description' => $dataItem['description'] ?? '',
                    'disease_code' => $dataItem['disease_code'] ?? '',
                ]);
                $allObjs[] = $obj->getAttribute('id');
            }
        }

        $this->bindMorphed($allObjs, $model);
    }

    /**
     * @param Accident $accident
     * @throws CaseGeneratorException
     */
    private function addDocuments(Accident $accident): void
    {
        /** @var array $dataList */
        $dataList = $this->getDataProvider()->getImages();
        /** @var DocumentService $service */
        $service = $this->getServiceLocator()->get(DocumentService::class);
        /** @var MorphToMany $model */
        $model = $accident->documents();

        try {
            
            $filePaths = $dataList;
            
            $docs = $service->createDocumentsFromFiles($filePaths, $this->getImporterUser());
            $ids = [];
            if ($docs->count()) {
                /** @var Document $doc */
                foreach ($docs as $doc) {
                    $ids[] = $doc->getAttribute('id');
                }
                $this->bindMorphed($ids, $model);
            }
        } catch (DiskDoesNotExist $e) {
            throw new CaseGeneratorException($e->getMessage());
        } catch (FileDoesNotExist $e) {
            throw new CaseGeneratorException($e->getMessage());
        } catch (FileIsTooBig $e) {
            throw new CaseGeneratorException($e->getMessage());
        }
    }
}
