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


use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use medcenter24\mcCore\App\Accident;
use medcenter24\mcCore\App\AccidentAbstract;
use medcenter24\mcCore\App\AccidentStatus;
use medcenter24\mcCore\App\AccidentStatusHistory;
use medcenter24\mcCore\App\AccidentType;
use medcenter24\mcCore\App\Assistant;
use medcenter24\mcCore\App\City;
use medcenter24\mcCore\App\Country;
use medcenter24\mcCore\App\Doctor;
use medcenter24\mcCore\App\DoctorAccident;
use medcenter24\mcCore\App\FinanceCurrency;
use medcenter24\mcCore\App\Patient;
use medcenter24\mcCore\App\Payment;
use medcenter24\mcCore\App\Region;
use medcenter24\mcCore\App\User;
use medcenter24\mcCore\Tests\samples\SamplesTrait;
use medcenter24\mcCore\Tests\TestCase;
use medcenter24\McImport\Exceptions\CaseGeneratorException;
use medcenter24\McImport\Services\CaseImporter\CaseGenerator;

class CaseGeneratorIntegrationTest extends TestCase
{
    use DatabaseMigrations;
    use SamplesTrait;

    private $dataProvider;

    /**
     * Path to the samples folder
     * @return string
     */
    protected function getSamplesPath(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'samples' . DIRECTORY_SEPARATOR;
    }

    /**
     * Setup unit test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->dataProvider = new TestCaseImportDataProvider();
        $file = $this->getSampleFilePath('no_image_available.jpg');
        copy($file, $this->getSampleFilePath('upload.jpg'));
        $images = [
            new UploadedFile($this->getSampleFilePath('upload.jpg'), 'realName.jpg')
        ];
        $this->dataProvider->setImages($images);
    }

    /**
     * @throws CaseGeneratorException
     */
    public function testCreateDoctorCase(): void
    {
        $generator = new CaseGenerator();
        $id = $generator->createCase($this->dataProvider);
        /** @var Accident $accident */
        $accident = Accident::find($id);
        // accident
        $this->assertSame(1, $accident->getAttribute('id'));
        $this->assertSame('{"id":1,"parent_id":"0","patient_id":"1","accident_type_id":"1","accident_status_id":"2","assistant_id":"1","assistant_ref_num":"bbb-ext-ref","assistant_invoice_id":"0","assistant_guarantee_id":"0","form_report_id":"0","city_id":"1","ref_num":"aaa-inter-ref","title":"i_11012011_doctor_1","address":"","handling_time":"2011-01-11 00:00:00","contacts":"patients contacts","symptoms":"Symptoms of the patient"}', $accident->toJson());

        // props and related models
        /** @var User|null $createdBy */
        $createdBy = $accident->getAttribute('createdBy');
        $this->assertNull($createdBy);

        /** @var Payment $caseablePayment */
        $caseablePayment = $accident->getAttribute('paymentToCaseable');
        $this->assertSame('{"created_by":"1","value":"0","currency_id":"1","fixed":"1","description":""}', $caseablePayment->toJson());

        /** @var FinanceCurrency $caseablePaymentCurrency */
        $caseablePaymentCurrency = $caseablePayment->getAttribute('currency');
        $this->assertSame('{"id":1,"title":"Euro","code":"eu","ico":"fa fa-euro"}', $caseablePaymentCurrency->toJson());

        $payment = $accident->getAttribute('incomePayment');
        $this->assertSame('70', $payment->getAttribute('value'), 'Income generated with correct value');
        $this->assertSame(2, $payment->getAttribute('id'), 'Income generated with correct id');
        $this->assertNull($accident->getAttribute('paymentFromAssistant'), 'No payment from assistant provided');
        $this->assertCount(0, $accident->getAttribute('checkpoints'), 'No checkpoints in test');

        /** @var AccidentStatusHistory $history */
        $history = $accident->history()->orderBy('created_at')->get();
        $this->assertCount(2, $history);

        /** @var AccidentStatusHistory $status */
        $status = $history->get(0);
        $this->assertSame(1, $status->getAttribute('id'));
        $this->assertSame('0', $status->getAttribute('user_id'));
        $this->assertSame(Accident::class, $status->getAttribute('historyable_type'));
        $this->assertSame('1', $status->getAttribute('historyable_id'));
        $this->assertSame('', $status->getAttribute('commentary'));

        /** @var AccidentStatusHistory $status2 */
        $status2 = $history->get(1);
        $this->assertSame(2, $status2->getAttribute('id'));
        $this->assertSame('0', $status2->getAttribute('user_id'));
        $this->assertSame(Accident::class, $status2->getAttribute('historyable_type'));
        $this->assertSame('1', $status2->getAttribute('historyable_id'));
        $this->assertSame('', $status2->getAttribute('commentary'));

        /** @var Assistant $assistant */
        $assistant = $accident->getAttribute('assistant');
        $this->assertSame('{"id":1,"title":"assist title","ref_key":"","email":"","comment":"assist address"}', $assistant->toJson());

        /** @var Patient $patient */
        $patient = $accident->getAttribute('patient');
        $this->assertSame('{"name":"Patient Name","address":"","phones":"","birthday":"1989-05-20 00:00:00","comment":""}', $patient->toJson());

        /** @var City $city */
        $city = $accident->getAttribute('city');
        $this->assertSame('{"id":1,"title":"Barcelona"}', $city->toJson());

        /** @var Region $region */
        $region = $city->getAttribute('region');
        $this->assertSame('{"id":1,"title":"Costa Dorada"}', $region->toJson());

        /** @var Country $country */
        $country = $region->getAttribute('country');
        $this->assertSame('{"id":1,"title":"Spain"}', $country->toJson());

        /** @var AccidentType $type */
        $type = $accident->getAttribute('type');
        $this->assertSame('{"title":"insurance","description":""}', $type->toJson());

        /** @var null $formReport */
        $formReport = $accident->getAttribute('formReport');
        $this->assertNull($formReport);

        /** @var AccidentAbstract $caseable */
        $caseable = $accident->getAttribute('caseable');
        $this->assertInstanceOf(DoctorAccident::class, $caseable);
        $this->assertSame('{"doctor_id":"1","visit_time":"2011-01-11 00:00:00","recommendation":"recommendations made by the doctor","investigation":"t 36.6 C"}', $caseable->toJson());

        /** @var Doctor $doctor */
        $doctor = $caseable->getAttribute('doctor');
        $this->assertSame('{"id":1,"user_id":"0","name":"Doctor Name","ref_key":"","medical_board_num":"board-num","gender":"female","description":""}', $doctor->toJson());

        /** @var AccidentStatus $accidentStatus */
        $accidentStatus = $accident->getAttribute('accidentStatus');
        $this->assertSame('{"id":2,"title":"imported","type":"accident"}', $accidentStatus->toJson());

        $caseableServices = $caseable->getAttribute('services');
        $this->assertCount(2, $caseableServices);
        $this->assertSame('{"id":1,"created_by":"1","title":"s1","description":"test","disease_id":"0","status":"active"}', $caseableServices->get(0)->toJson());
        $this->assertSame('{"id":2,"created_by":"1","title":"s2","description":"test","disease_id":"0","status":"active"}', $caseableServices->get(1)->toJson());

        $diagnostics = $caseable->getAttribute('diagnostics');
        $this->assertCount(2, $diagnostics);
        $this->assertSame('{"id":1,"diagnostic_category_id":"0","title":"diag 1","disease_id":"0","status":"active","description":"test"}', $diagnostics->get(0)->toJson());
        $this->assertSame('{"id":2,"diagnostic_category_id":"0","title":"diag 2","disease_id":"0","status":"active","description":"test"}', $diagnostics->get(1)->toJson());

        $surveys = $caseable->getAttribute('surveys');
        $this->assertCount(4, $surveys);
        $this->assertSame('{"id":1,"title":"General condition is satisfactory.","description":"test","disease_id":"0","status":"active"}', $surveys->get(0)->toJson());
        $this->assertSame('{"id":2,"title":"Heart tones are rhythmic, no pathological noise.","description":"test","disease_id":"0","status":"active"}', $surveys->get(1)->toJson());
        $this->assertSame('{"id":3,"title":"Neurological status is normal.","description":"","disease_id":"0","status":"active"}', $surveys->get(2)->toJson());
        $this->assertSame('{"id":4,"title":"Otherwise, there is no pathology.","description":"test","disease_id":"0","status":"active"}', $surveys->get(3)->toJson());

        $documents = $caseable->getAttribute('documents');
        $this->assertCount(0, $documents);
        $documents = $accident->getAttribute('documents');
        $this->assertCount(1, $documents);
        $this->assertSame('{"created_by":"1","title":"realName.jpg"}', $documents->get(0)->toJson());
    }
}
