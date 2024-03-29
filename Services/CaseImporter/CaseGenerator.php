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
use medcenter24\mcCore\App\Entity\Accident;
use medcenter24\mcCore\App\Entity\AccidentAbstract;
use medcenter24\mcCore\App\Entity\AccidentStatus;
use medcenter24\mcCore\App\Entity\AccidentType;
use medcenter24\mcCore\App\Entity\Assistant;
use medcenter24\mcCore\App\Entity\City;
use medcenter24\mcCore\App\Entity\Country;
use medcenter24\mcCore\App\Entity\Disease;
use medcenter24\mcCore\App\Entity\Doctor;
use medcenter24\mcCore\App\Entity\DoctorAccident;
use medcenter24\mcCore\App\Entity\Survey;
use medcenter24\mcCore\App\Entity\Document;
use medcenter24\mcCore\App\Exceptions\InconsistentDataException;
use medcenter24\mcCore\App\Entity\FinanceCurrency;
use medcenter24\mcCore\App\Helpers\FileHelper;
use medcenter24\mcCore\App\Entity\Hospital;
use medcenter24\mcCore\App\Entity\HospitalAccident;
use medcenter24\mcCore\App\Entity\Patient;
use medcenter24\mcCore\App\Entity\Payment;
use medcenter24\mcCore\App\Entity\Region;
use medcenter24\mcCore\App\Services\Entity\AbstractModelService;
use medcenter24\mcCore\App\Services\Entity\AccidentService;
use medcenter24\mcCore\App\Services\Entity\AccidentStatusService;
use medcenter24\mcCore\App\Services\Entity\AccidentTypeService;
use medcenter24\mcCore\App\Services\Entity\AssistantService;
use medcenter24\mcCore\App\Services\Entity\CityService;
use medcenter24\mcCore\App\Services\Core\Logger\DebugLoggerTrait;
use medcenter24\mcCore\App\Services\Entity\CountryService;
use medcenter24\mcCore\App\Services\Entity\CurrencyService;
use medcenter24\mcCore\App\Services\Entity\DiagnosticService;
use medcenter24\mcCore\App\Services\Entity\DoctorAccidentService;
use medcenter24\mcCore\App\Services\Entity\ServiceService;
use medcenter24\mcCore\App\Services\Entity\DoctorService;
use medcenter24\mcCore\App\Services\Entity\SurveyService;
use medcenter24\mcCore\App\Services\Entity\DocumentService;
use medcenter24\mcCore\App\Services\File\TmpFileService;
use medcenter24\mcCore\App\Services\Entity\HospitalAccidentService;
use medcenter24\mcCore\App\Services\Entity\HospitalService;
use medcenter24\mcCore\App\Services\Entity\PatientService;
use medcenter24\mcCore\App\Services\Entity\PaymentService;
use medcenter24\mcCore\App\Services\Core\ServiceLocator\ServiceLocatorTrait;
use medcenter24\mcCore\App\Services\Entity\RegionService;
use medcenter24\mcCore\App\Services\Entity\UserService;
use medcenter24\mcCore\App\Entity\User;
use medcenter24\McImport\Contract\CaseGeneratorInterface;
use medcenter24\McImport\Contract\CaseImporterDataProvider;
use medcenter24\McImport\Entities\Importing\ImportingCase;
use medcenter24\McImport\Exceptions\CaseGeneratorException;
use medcenter24\McImport\Services\ImportLog\ImportLogService;
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

    /**
     * @var ImportingCase
     */
    private $entity;

    /**
     * To use with tests
     * @var string
     */
    private $date;

    /**
     * @param CaseImporterDataProvider $dataProvider - initialized data provider to get data from
     * @return int Accident identifier
     * @throws CaseGeneratorException
     */
    public function createCase(CaseImporterDataProvider $dataProvider): int
    {
        // sometimes it could help to proceed images
        set_time_limit(0);

        $this->log('Case creation started...');
        $this->entity = new ImportingCase();

        if ($this->date) {
            $this->getEntity()->setCurrentTime($this->date);
        }

        $this->dataProvider = $dataProvider;

        $this->checkData();
        try {
            $this->getEntity()->setAccident($this->createAccident());
        } catch (InconsistentDataException $e) {
            throw new CaseGeneratorException($e->getMessage());
        }
        $this->addServices();
        $this->addDiagnostics();
        $this->addSurveys();
        $this->addDocuments();

        // don't want to duplicate logs, so it will be written only once - on success
        $this->writeImportLog($dataProvider, json_encode(['status' => 'imported']), $this->getEntity()->getAccident());

        $accidentId = $this->getEntity()->getAccident()->getAttribute('id');
        unset($this->entity);
        return $accidentId;
    }

    /**
     * @return ImportingCase
     */
    private function getEntity(): ImportingCase
    {
        return $this->entity;
    }

    private function writeImportLog(CaseImporterDataProvider $dataProvider, string $status, Accident $accident = null): void
    {
        $this->getImportLogService()->log($dataProvider->getPath(), $dataProvider, $status, $accident);
    }

    private function getImportLogService(): ImportLogService
    {
        return $this->getServiceLocator()->get(ImportLogService::class);
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
        if (!$this->getEntity()->hasUser()) {
            /** @var UserService $userService */
            $userService = $this->getServiceLocator()->get(UserService::class);
            /** @var User $user */
            $user = $userService->firstOrCreate([
                'name' => 'Importer',
                'email' => 'importer@medcenter24.com',
            ]);
            $this->getEntity()->setUser($user);
        }
        return $this->getEntity()->getUser();
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
            'income_payment_id' => $this->getIncomePayment()->getAttribute('id'),
            'cash_payment_id' => $this->getCashPayment()->getAttribute('id'),
            'assistant_payment_id' => null,
            'caseable_id' => $this->getCaseable()->getAttribute('id'),
            'caseable_type' => $this->getDataProvider()->getCaseableType(),
            'ref_num' => $this->getDataProvider()->getInternalRefNumber(),
            'title' => $this->getAccidentTitle(),
            'address' => '',
            'handling_time' => Carbon::parse($this->getDataProvider()->getCaseCreationDate()),
            'contacts' => $this->getDataProvider()->getPatientContacts(),
            'symptoms' => $this->getDataProvider()->getPatientSymptoms(),
        ]);

        // first status always `new`, now we need to add status `imported`
        $accidentService->setStatus($accident, $this->getAccidentStatus());

        $accident->setAttribute('created_at', Carbon::parse($this->getDataProvider()->getCaseCreationDate()));
        $accident->setAttribute('updated_at', $this->getEntity()->getCurrentTime());
        $accident->save(['timestamps' => false]);

        $this->log('Created case ' . $accident->getAttribute('id'));
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
        if (!$this->getEntity()->hasPatient()) {
            /** @var PatientService $patientService */
            $patientService = $this->getServiceLocator()->get(PatientService::class);
            /** @var Patient $patient */
            $patient = $patientService->firstOrCreate([
                'name' => $this->getDataProvider()->getPatientName(),
                'birthday' => Carbon::parse($this->getDataProvider()->getPatientBirthday()),
            ]);
            $this->getEntity()->setPatient($patient);
        }
        return $this->getEntity()->getPatient();
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
        /** @var AccidentStatusService $service */
        $service = $this->getServiceLocator()->get(AccidentStatusService::class);
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
            'value' => 0,
            'currency_id' => $this->getCurrency()->getAttribute('id'),
            'fixed' => 1,
            'created_by' => $this->getImporterUser()->getAttribute('id'),
        ]);
        return $payment;
    }

    /**
     * Fixed 0 while we don't know real income value
     * @return Payment
     * @throws CaseGeneratorException
     */
    private function getIncomePayment(): Payment
    {
        /** @var PaymentService $paymentService */
        $paymentService = $this->getServiceLocator()->get(PaymentService::class);
        /** @var Payment $payment */
        $payment = $paymentService->create([
            'value' => $this->getDataProvider()->getIncomePrice(),
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
        if (!$this->getEntity()->hasCaseable()) {
            switch ($this->getDataProvider()->getCaseableType()) {
                case DoctorAccident::class:
                    $caseable = $this->createDoctorAccident();
                    break;

                case HospitalAccident::class:
                    $caseable = $this->createHospitalAccident();
                    break;

                default: throw new InconsistentDataException('Undefined caseable type');
            }
            $this->getEntity()->setCaseable($caseable);
        }

        return $this->getEntity()->getCaseable();
    }

    /**
     * @return DoctorAccident
     * @throws CaseGeneratorException
     */
    private function createDoctorAccident(): DoctorAccident
    {
        $doctorAccidentService = $this->getServiceLocator()->get(DoctorAccidentService::class);
        /** @var DoctorAccident $doctorAccident */
        $doctorAccident = $doctorAccidentService->create([
            'doctor_id' => $this->getDoctor()->getAttribute('id'),
            'recommendation' => $this->getDataProvider()->getDoctorRecommendation(),
            // other investigations stored in surveys
            'investigation' => $this->getDataProvider()->getAdditionalDoctorInvestigation(),
            'visit_time' => Carbon::parse($this->getDataProvider()->getCaseCreationDate()),
        ]);

        $doctorAccident->setAttribute('created_at', Carbon::parse($this->getDataProvider()->getCaseCreationDate()));
        $doctorAccident->setAttribute('updated_at', Carbon::parse($this->getEntity()->getCurrentTime()));
        $doctorAccident->save(['timestamps' => false]);

        return $doctorAccident;
    }

    /**
     * @return Doctor
     * @throws CaseGeneratorException
     */
    private function getDoctor(): Doctor
    {
        /** @var DoctorService $doctorService */
        $doctorService = $this->getServiceLocator()->get(DoctorService::class);
        if ($this->getDataProvider()->getDoctorName()) {
            $name = $this->getDataProvider()->getDoctorName();
        } else {
            $doctorsData = $this->getDataProvider()->getDefaultDoctorData();
            if (!is_array($doctorsData) || !array_key_exists('name', $doctorsData)) {
                throw new CaseGeneratorException('Default doctors data not provided');
            }
            $name = $doctorsData['name'];
        }

        $doctor = $doctorService->first(['name' => $name]);

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
     * @param string $updatedTime
     */
    public function setUpdatedDate(string $updatedTime): void
    {
        $this->date = $updatedTime;
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
                    'disease_id' => $dataItem['disease_id'] ?? 0,
                ]);
                $allObjs[] = $obj->getAttribute('id');
            }
        }

        return $allObjs;
    }

    /**
     * @throws CaseGeneratorException
     */
    private function addServices(): void
    {
        /** @var array $dataList */
        $dataList = $this->getDataProvider()->getDoctorServices();
        /** @var AbstractModelService|ServiceService $service */
        $service = $this->getServiceLocator()->get(ServiceService::class);
        /** @var MorphToMany $model */
        $model = $this->getEntity()->getCaseable()->services();

        $ids = $this->createFormattedDocResourcesIds($dataList, $service);
        $this->bindMorphed($ids, $model);
    }

    /**
     * @throws CaseGeneratorException
     */
    private function addDiagnostics(): void
    {
        /** @var array $dataList */
        $dataList = $this->getDataProvider()->getDoctorDiagnostics();
        /** @var AbstractModelService|DiagnosticService $service */
        $service = $this->getServiceLocator()->get(DiagnosticService::class);
        /** @var MorphToMany $model */
        $model = $this->getEntity()->getCaseable()->diagnostics();
        $ids = $this->createFormattedDocResourcesIds($dataList, $service);
        $this->bindMorphed($ids, $model);
    }

    /**
     * @throws CaseGeneratorException
     */
    private function addSurveys(): void
    {
        $dataList = $this->getDataProvider()->getDoctorSurveys();
        $model = $this->getEntity()->getCaseable()->surveys();

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

                if (Str::length($dataItem['title']) > 180 && mb_stripos($dataItem['title'], ',') !== false) {
                    foreach (explode(',', $dataItem['title']) as $title) {
                        $allObjs[] = $this
                            ->addSurvey($title, $dataItem['description'] ?? '', $dataItem['disease_id'] ?? '')
                            ->getAttribute('id');
                    }
                } else {
                    $allObjs[] = $this
                        ->addSurvey($dataItem['title'], $dataItem['description'] ?? '', $dataItem['disease_id'] ?? 0)
                        ->getAttribute('id');
                }
            }
        }

        $this->bindMorphed($allObjs, $model);
    }

    private function addSurvey(string $title = '', string $description='', string $disease=''): Survey
    {
        /** @var SurveyService $service */
        $service = $this->getServiceLocator()->get(SurveyService::class);

        $diseaseService = $this->getServiceLocator()->get(DiseaseService::class);
        /** @var Disease $diseaseModel */
        $diseaseModel = $diseaseService->create($disease);
        /** @var Survey $obj */
        return $service->byTitleLettersOrCreate([
            'created_by' => $this->getImporterUser()->getAttribute('id'),
            'title' => Str::limit($title, 250),
            'description' => $description,
            'disease_id' => $diseaseModel ? $diseaseModel->getAttribute('id') : 0,
        ]);
    }

    /**
     * @throws CaseGeneratorException
     */
    private function addDocuments(): void
    {
        /** @var array $dataList */
        $dataList = $this->getDataProvider()->getImages();
        /** @var DocumentService $service */
        $service = $this->getServiceLocator()->get(DocumentService::class);
        /** @var MorphToMany $model */
        $model = $this->getEntity()->getAccident()->documents();

        /** @var TmpFileService $tmpFileService */
        $tmpFileService = $this->getServiceLocator()->get(TmpFileService::class);

        try {
            $files = [];
            foreach ($dataList as $item) {
                if ($item instanceof UploadedFile) {
                    $files[] = $item;
                } elseif (is_array($item)) {
                    if (!array_key_exists('imageContent', $item)) {
                        throw new CaseGeneratorException('File "'.$item['name'].'" does not have a content');
                    }
                    // create new tmp file which will be deleted after uploading
                    $tmpFile = $tmpFileService->createTmpFile('imported');
                    FileHelper::writeFile($tmpFile, $item['imageContent']);
                    $fileName =$item['name'];
                    $fileName = preg_replace('/\.'.$item['ext'].'/', '', $fileName);
                    $fileName = FileHelper::purifiedFileName($fileName).'.'.$item['ext'];
                    $files[] = new UploadedFile($tmpFile, $fileName);
                }
            }

            $docs = $service->createDocumentsFromFiles($files, $this->getImporterUser());
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

    private function getCashPayment()
    {

    }
}
