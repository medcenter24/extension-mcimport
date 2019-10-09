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
use medcenter24\mcCore\App\Accident;
use medcenter24\mcCore\App\Assistant;
use medcenter24\mcCore\App\DoctorAccident;
use medcenter24\mcCore\App\Exceptions\InconsistentDataException;
use medcenter24\mcCore\App\FinanceCurrency;
use medcenter24\mcCore\App\Patient;
use medcenter24\mcCore\App\Payment;
use medcenter24\mcCore\App\Services\AccidentService;
use medcenter24\mcCore\App\Services\AccidentStatusesService;
use medcenter24\mcCore\App\Services\AssistantService;
use medcenter24\mcCore\App\Services\DoctorAccidentService;
use medcenter24\mcCore\App\Services\PatientService;
use medcenter24\mcCore\App\Services\PaymentService;
use medcenter24\mcCore\Tests\TestCase;
use medcenter24\McImport\Contract\CaseImporterDataProvider;
use medcenter24\McImport\Services\CaseImporter\CaseGenerator;
use PHPUnit\Framework\MockObject\MockObject;

class CaseGeneratorTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * @throws InconsistentDataException
     */
    public function testCreateDoctorCase(): void
    {

        /** @var CaseImporterDataProvider|MockObject $dataProvider */
        $dataProvider = $this->createMock(CaseImporterDataProvider::class);
        $dataProvider->method('caseableType')->willReturn(DoctorAccident::class);
        $currencyMock = $this->createMock(FinanceCurrency::class);
        $currencyMock->method('getAttribute')->willReturn(1);
        $dataProvider->method('currency')->willReturn($currencyMock);

        $mockPaymentService = $this->createMock(PaymentService::class);
        $mockPayment = $this->createMock(Payment::class);
        $mockPaymentService->method('create')->willReturn($mockPayment);
        $mockAccidentService = $this->createMock(AccidentService::class);
        $mockAccident = $this->createMock(Accident::class);
        $mockAccident->method('getAttribute')->willReturn(1);
        $mockAccidentService->method('create')->willReturn($mockAccident);
        $mockPatientService = $this->createMock(PatientService::class);
        $mockPatient = $this->createMock(Patient::class);
        $mockPatientService->method('firstOrCreate')->willReturn($mockPatient);
        $mockAccidentStatusesService = $this->createMock(AccidentStatusesService::class);
        $mockAssistantService = $this->createMock(AssistantService::class);
        $mockAssistant = $this->createMock(Assistant::class);
        $mockAssistantService->method('firstOrCreate')->willReturn($mockAssistant);
        $mockDoctorAccidentService = $this->createMock(DoctorAccidentService::class);
        $mockDoctorAccident = $this->createMock(DoctorAccident::class);
        $mockDoctorAccidentService->method('create')->willReturn($mockDoctorAccident);

        $serviceLocator = $this->mockServiceLocator([
            PaymentService::class => $mockPaymentService,
            AccidentService::class => $mockAccidentService,
            PatientService::class => $mockPatientService,
            AccidentStatusesService::class => $mockAccidentStatusesService,
            AssistantService::class => $mockAssistantService,
            DoctorAccidentService::class => $mockDoctorAccidentService,
        ]);

        $generator = new CaseGenerator();
        $generator->setServiceLocator($serviceLocator);
        $accident = $generator->createCase($dataProvider);
        // accident
        self::assertSame(1, $accident->getAttribute('id'));
    }
}
