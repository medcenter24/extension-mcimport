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

namespace medcenter24\McImport\Tests\Unit\CaseImporter;


use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use medcenter24\mcCore\App\Accident;
use medcenter24\mcCore\App\AccidentStatus;
use medcenter24\mcCore\App\AccidentType;
use medcenter24\mcCore\App\Assistant;
use medcenter24\mcCore\App\City;
use medcenter24\mcCore\App\Doctor;
use medcenter24\mcCore\App\DoctorAccident;
use medcenter24\mcCore\App\FinanceCurrency;
use medcenter24\mcCore\App\Patient;
use medcenter24\mcCore\App\Payment;
use medcenter24\mcCore\App\Services\AccidentService;
use medcenter24\mcCore\App\Services\AccidentStatusesService;
use medcenter24\mcCore\App\Services\AccidentTypeService;
use medcenter24\mcCore\App\Services\AssistantService;
use medcenter24\mcCore\App\Services\CityService;
use medcenter24\mcCore\App\Services\Core\ServiceLocator\ServiceLocator;
use medcenter24\mcCore\App\Services\CurrencyService;
use medcenter24\mcCore\App\Services\DiagnosticService;
use medcenter24\mcCore\App\Services\DoctorAccidentService;
use medcenter24\mcCore\App\Services\DoctorServiceService;
use medcenter24\mcCore\App\Services\DoctorsService;
use medcenter24\mcCore\App\Services\DoctorSurveyService;
use medcenter24\mcCore\App\Services\DocumentService;
use medcenter24\mcCore\App\Services\File\TmpFileService;
use medcenter24\mcCore\App\Services\PatientService;
use medcenter24\mcCore\App\Services\PaymentService;
use medcenter24\mcCore\App\Services\UserService;
use medcenter24\mcCore\App\User;
use medcenter24\mcCore\Tests\TestCase;
use medcenter24\McImport\Contract\CaseImporterDataProvider;
use medcenter24\McImport\Exceptions\CaseGeneratorException;
use medcenter24\McImport\Services\CaseImporter\CaseGenerator;
use medcenter24\McImport\Services\ImportLog\ImportLogService;
use PHPUnit\Framework\MockObject\MockObject;

class CaseGeneratorTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * @throws CaseGeneratorException
     */
    public function testParentWasNotImportedException(): void
    {
        $this->expectException(CaseGeneratorException::class);
        $this->expectExceptionMessage('Parent accident must be imported before current accident.');

        /** @var CaseImporterDataProvider|MockObject $dataProvider */
        $dataProvider = $this->createMock(CaseImporterDataProvider::class);
        $dataProvider->method('getParentAccidentMarkers')->willReturn(['assistantRefNum' => 'ref-num-assist']);
        /** @var CaseGenerator $generator */
        $generator = new CaseGenerator();
        $generator->createCase($dataProvider);
    }

    /**
     * @throws CaseGeneratorException
     */
    public function testCreateDoctorCase(): void
    {
        $self = $this;

        $updatedTime = '2017-01-17 09:00:00';
        /** @var CaseImporterDataProvider|MockObject $dataProvider */
        $dataProvider = $this->createMock(CaseImporterDataProvider::class);
        $dataProvider->method('getParentAccidentMarkers')->willReturn(['assistantRefNum' => 'ref-num-assist']);
        $dataProvider->method('getCaseableType')->willReturn(DoctorAccident::class);
        $dataProvider->method('getCurrency')->willReturn('$');
        $dataProvider->method('getVisitDate')->willReturn('2011-04-17');
        $dataProvider->method('getVisitTime')->willReturn('08:43:00');
        $dataProvider->method('getCaseCreationDate')->willReturn('2011-04-17 08:43:00');
        $dataProvider->method('getInternalRefNumber')->willReturn('ref-num');
        $dataProvider->method('getExternalRefNumber')->willReturn('external-ref-num');
        $dataProvider->method('getAssistantTitle')->willReturn('fake assistant company');
        $dataProvider->method('getAssistantAddress')->willReturn('address string');
        $dataProvider->method('getPatientSymptoms')->willReturn('Patient symptoms');
        $dataProvider->method('getDefaultDoctorData')->willReturn([
            'name' => 'Doc Name',
            'description' => 'doc info',
            'ref_key' => 'dn',
            'gender' => 'male',
            'medical_board_num' => '123123123',
        ]);

        /** @var PaymentService|MockObject $mockPaymentService */
        $mockPaymentService = $this->createMock(PaymentService::class);
        /** @var Payment|MockObject $mockPayment */
        $mockPayment = $this->createMock(Payment::class);
        $mockPayment->method('getAttribute')->willReturn(1);
        $mockPaymentService->method('create')->willReturn($mockPayment);
        
        /** @var AccidentService|MockObject $mockAccidentService */
        $mockAccidentService = $this->createMock(AccidentService::class);

        /** @var Accident|MockObject $mockParentAccident */
        $mockParentAccident = $this->createMock(Accident::class);
        $mockParentAccident->method('getAttribute')->willReturn(1);
        $mockAccidentService->method('getByAssistantRefNum')->willReturn($mockParentAccident);

        /** @var Accident|MockObject $mockResultCreatedAccident */
        $mockAccidentService->method('create')->willReturnCallback(static function ($params) use ($self) {
            unset($params['handling_time'], $params['created_at']); // dates will be changed on creation, has no sense to check it

            $self->assertSame([
                'created_by' => null,
                'parent_id' => 1,
                'patient_id' => 1,
                'accident_type_id' => 1,
                // 'accident_status_id' => 1, is always new, so have no sense to initialize it
                'assistant_id' => 1,
                'assistant_ref_num' => 'external-ref-num',
                'assistant_invoice_id' => null,
                'assistant_guarantee_id' => null,
                'form_report_id' => null,
                'city_id' => 1,
                'caseable_payment_id' => 1,
                'income_payment_id' => 1,
                'assistant_payment_id' => null,
                'caseable_id' => 1,
                'caseable_type' => DoctorAccident::class,
                'ref_num' => 'ref-num',
                'title' => 'i_20110417084300_doctor_1',
                'address' => '',
                'contacts' => '',
                'symptoms' => 'Patient symptoms',
            ], $params);
            $accidentService = new AccidentService();
            return $accidentService->create($params);
        });

        /** @var PatientService|MockObject $mockPatientService */
        $mockPatientService = $this->createMock(PatientService::class);
        /** @var Patient|MockObject $mockPatient */
        $mockPatient = $this->createMock(Patient::class);
        $mockPatient->method('getAttribute')->willReturn(1);
        $mockPatientService->method('firstOrCreate')->willReturn($mockPatient);

        /** @var AccidentTypeService|MockObject $mockAccidentTypeService */
        $mockAccidentTypeService = $this->createMock(AccidentTypeService::class);
        /** @var AccidentType|MockObject $mockAccidentType */
        $mockAccidentType = $this->createMock(AccidentType::class);
        $mockAccidentType->method('getAttribute')->willReturn(1);
        $mockAccidentTypeService->method('firstOrCreate')->willReturn($mockAccidentType);

        /** @var AccidentStatusesService|MockObject $mockAccidentStatusesService */
        $mockAccidentStatusesService = $this->createMock(AccidentStatusesService::class);
        /** @var AccidentStatus|MockObject $mockAccidentStatus */
        $mockAccidentStatus = $this->createMock(AccidentStatus::class);
        $mockAccidentStatus->method('getAttribute')->willReturn(1);
        $mockAccidentStatusesService->method('getImportedStatus')->willReturn($mockAccidentStatus);

        /** @var AssistantService|MockObject $mockAssistantService */
        $mockAssistantService = $this->createMock(AssistantService::class);
        /** @var Assistant|MockObject $mockAssistant */
        $mockAssistant = $this->createMock(Assistant::class);
        $mockAssistant->method('getAttribute')->willReturn(1);
        $mockAssistantService->method('firstOrCreate')->willReturn($mockAssistant);
        // assistant address
        $dataProvider->method('getAssistantAddress')->willReturn('assistant address');

        /** @var DoctorAccidentService|MockObject $mockDoctorAccidentService */
        $mockDoctorAccidentService = $this->createMock(DoctorAccidentService::class);
        /** @var DoctorAccident|MockObject $mockDoctorAccident */
        $mockDoctorAccident = $this->createMock(DoctorAccident::class);
        $mockDoctorAccident->method('getAttribute')->willReturn(1);
        $mockDoctorAccidentService->method('create')->willReturn($mockDoctorAccident);

        /** @var CityService|MockObject $mockCityService */
        $mockCityService = $this->createMock(CityService::class);
        /** @var City|MockObject $mockCity */
        $mockCity = $this->createMock(City::class);
        $mockCity->method('getAttribute')->willReturn(1);
        $mockCity->method('getAttribute')->willReturn(null);
        $mockCityService->method('firstOrCreate')->willReturn($mockCity);

        /** @var CurrencyService|MockObject $mockCurrencyService */
        $mockCurrencyService = $this->createMock(CurrencyService::class);
        $mockCurrency = $this->createMock(FinanceCurrency::class);
        $mockCurrencyService->method('byMarker')->willReturn($mockCurrency);

        /** @var DoctorsService|MockObject $mockDoctorsService */
        $mockDoctorsService = $this->createMock(DoctorsService::class);
        /** @var Doctor|MockObject $mockDoctor */
        $mockDoctor = $this->createMock(Doctor::class);
        $mockDoctor->method('getAttribute')->willReturn(1);
        $mockDoctorsService->method('first')->willReturn($mockDoctor);

        /** @var UserService|MockObject $mockUserService */
        $mockUserService = $this->createMock(UserService::class);
        /** @var User|MockObject $mockUser */
        $mockUser = $this->createMock(User::class);
        $mockUserService->method('firstOrCreate')->willReturn($mockUser);

        /** @var DoctorServiceService|MockObject $mockDoctorServiceService */
        $mockDoctorServiceService = $this->createMock(DoctorServiceService::class);

        /** @var DiagnosticService|MockObject $mockDiagnosticService */
        $mockDiagnosticService = $this->createMock(DiagnosticService::class);

        /** @var DoctorSurveyService $mockDoctorSurveyService */
        $mockDoctorSurveyService = $this->createMock(DoctorSurveyService::class);

        /** @var DocumentService|MockObject $mockDocumentService */
        $mockDocumentService = $this->createMock(DocumentService::class);

        /** @var TmpFileService $mockTmpFileService */
        $mockTmpFileService = $this->createMock(TmpFileService::class);

        /** @var ImportLogService $mockImportLogService */
        $mockImportLogService = $this->createMock(ImportLogService::class);

        /** @var ServiceLocator|MockObject $serviceLocator */
        $serviceLocator = $this->mockServiceLocator([
            PaymentService::class => $mockPaymentService,
            AccidentService::class => $mockAccidentService,
            PatientService::class => $mockPatientService,
            AccidentStatusesService::class => $mockAccidentStatusesService,
            AssistantService::class => $mockAssistantService,
            DoctorAccidentService::class => $mockDoctorAccidentService,
            AccidentTypeService::class => $mockAccidentTypeService,
            CityService::class => $mockCityService,
            CurrencyService::class => $mockCurrencyService,
            DoctorsService::class => $mockDoctorsService,
            UserService::class => $mockUserService,
            DoctorServiceService::class => $mockDoctorServiceService,
            DiagnosticService::class => $mockDiagnosticService,
            DoctorSurveyService::class => $mockDoctorSurveyService,
            DocumentService::class => $mockDocumentService,
            TmpFileService::class => $mockTmpFileService,
            ImportLogService::class => $mockImportLogService,
        ]);

        $generator = new CaseGenerator();
        $generator->setServiceLocator($serviceLocator);
        $generator->setUpdatedDate($updatedTime);
        $accidentId = $generator->createCase($dataProvider);
        // accident
        self::assertSame(1, $accidentId);
    }
}
