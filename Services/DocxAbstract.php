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


use FilesystemIterator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use medcenter24\mcCore\App\Accident;
use medcenter24\mcCore\App\Assistant;
use medcenter24\mcCore\App\DoctorAccident;
use medcenter24\mcCore\App\Document;
use medcenter24\mcCore\App\Exceptions\InconsistentDataException;
use medcenter24\mcCore\App\Helpers\Arr;
use medcenter24\mcCore\App\Patient;
use medcenter24\mcCore\App\Services\AccidentService;
use medcenter24\mcCore\App\Services\AccidentStatusesService;
use medcenter24\mcCore\App\Services\AccidentTypeService;
use medcenter24\mcCore\App\Services\AssistantService;
use medcenter24\mcCore\App\Services\CaseServices\Finance\CaseFinanceService;
use medcenter24\mcCore\App\Services\DiagnosticService;
use medcenter24\mcCore\App\Services\DoctorAccidentService;
use medcenter24\mcCore\App\Services\DoctorServiceService;
use medcenter24\mcCore\App\Services\DocumentService;
use medcenter24\mcCore\App\Services\DomDocumentService;
use medcenter24\mcCore\App\Services\ExtractTableFromArrayService;
use medcenter24\mcCore\App\Services\PatientService;
use medcenter24\mcCore\App\Services\ServiceLocatorTrait;
use medcenter24\McImport\Contract\CaseImporterProviderService;
use medcenter24\McImport\Contract\DocumentReaderService;
use medcenter24\McImport\Services\DataServiceProviderService;
use medcenter24\McImport\Exceptions\ImporterException;
use SplFileInfo;

/**
 * Abstraction which allows us to import case data from the docx files
 *
 * Class DocxAbstract
 * @package medcenter24\McImport\Services\Import
 * @deprecated
 * @TODO delete after importer rewriting
 */
abstract class DocxAbstract extends DataServiceProviderService
{
    use ServiceLocatorTrait;
    /** Generated models from the imported file */

    /**
     * main accident
     * @var Accident
     */
    private $accident;

    /** Tools */

    /**
     * Main tables
     * @var array
     */
    private $rootTables = [];

    /**
     * Validation of the accident
     * @var bool
     */
    protected $isValidAccident = false;

    /**
     * @var ExtractTableFromArrayService
     */
    private $tableExtractorService;

    /**
     * I know that this importer could read docx only
     * @return array
     */
    public function getFileExtensions(): array
    {
        return ['docx'];
    }

    /**
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function import(): void
    {
        $this->check();

        // creates accident and doctor accident
        $this->createDoctorAccident();
        $this->importAdditionalData();
        // load images from the docx as a documents
        $this->loadDocuments();
        $this->afterImport();
    }

    abstract protected function importAdditionalData(): void;

    /**
     * If content in the tables it is easy to handle it
     * @return ExtractTableFromArrayService
     */
    protected function getTableExtractorService(): ExtractTableFromArrayService
    {
        if (!$this->tableExtractorService) {
            $this->tableExtractorService = $this->getServiceLocator()->get(ExtractTableFromArrayService::class, [
                ExtractTableFromArrayService::CONFIG_TABLE => ['w:tbl'],
                ExtractTableFromArrayService::CONFIG_ROW => ['w:tr'],
                ExtractTableFromArrayService::CONFIG_CEIL => ['w:tc'],
            ]);
        }

        return $this->tableExtractorService;
    }

    /**
     * @var DomDocumentService
     */
    private $domService;

    protected function getDomService(): DomDocumentService
    {
        if (!$this->domService) {
            $this->domService = $this->getServiceLocator()->get(DomDocumentService::class, [
                DomDocumentService::STRIP_STRING => true,
                DomDocumentService::CONFIG_WITHOUT_ATTRIBUTES => true,
            ]);
        }

        return $this->domService;
    }

    /**
     * @var DocumentReaderService
     */
    private $readerService;

    /**
     * @return DocumentReaderService
     */
    protected function getReaderService(): DocumentReaderService
    {
        if (!$this->readerService) {
            $this->readerService = $this->getServiceLocator()->get(DocumentReaderService::class);
        }
        return $this->readerService;
    }

    /**
     * Initialize new file for import
     * @param string $path
     * @return CaseImporterProviderService|DocxAbstract
     */
    public function load(string $path): CaseImporterProviderService
    {
        $this->dropErrors();
        $this->reload();
        $this->getReaderService()->load($path);
        return $this;
    }

    /**
     * Destroy all stored data from the previous import
     */
    protected function reload(): void
    {
        $this->accident = null;
        $this->rootTables = [];
        $this->isValidAccident = false;
    }

    /**
     * List of static phrases from the template of the case for import
     * to determine that this is definitely this template
     * @return array
     */
    abstract protected function getCheckPoints(): array;

    /**
     * Test imported file to be sure that current provider can handle it
     * @throws ImporterException
     * @throws InconsistentDataException
     */
    public function check(): void
    {
        $text = $this->getReaderService()->getText();
        foreach ($this->getCheckPoints() as $checkPoint) {
            if (mb_strpos($text, $checkPoint) === false) {
                $this->addError([
                    'message' => 'File content is not match to expected checkpoints',
                    'checkpoint' => $checkPoint,
                    'path' => $this->getReaderService()->getFilePath(),
                ]);
                throw new ImporterException('Checkpoint not found');
            }
        }

        // this will run accidents validator
        $this->loadRootTables();
    }

    /**
     * All documents should have main tables in the content;
     * @return void
     * @throws InconsistentDataException
     * @throws ImporterException
     */
    private function loadRootTables(): void
    {
        if (!$this->rootTables) {
            $data = $this->getTableExtractorService()->extract($this->getDomService()->toArray($this->getReaderService()->getDom()));
            $this->rootTables = $data[ExtractTableFromArrayService::TABLES];
        }
        $this->validateAccident();
    }

    /**
     * All word docs has their content as a tables
     * so this is top level of these tables
     * @return array
     */
    public function getRootTables(): array
    {
        return $this->rootTables;
    }

    /**
     * @param $condition
     * @param string $message
     * @throws ImporterException
     */
    protected function throwIfFalse($condition, $message = ''): void
    {
        if ($condition === false) {
            $this->addError([
                'message' => $message,
            ]);
            throw new ImporterException($message);
        }
    }

    /**
     * count of the root table elements
     * @return int
     */
    abstract protected function expectedCountOfRootTable(): int;

    /**
     * Check that addresses exist where we will look for data
     * @return array
     */
    abstract protected function getRequiredAddrRootTable(): array;

    /**
     * @throws ImporterException
     */
    private function checkRootTables(): void
    {
        $this->throwIfFalse(
            count($this->getRootTables()) === $this->expectedCountOfRootTable(),
                'Count of the root tables ('
                    . count($this->getRootTables())
                    . ') is not equal expected '
                    . $this->expectedCountOfRootTable()
        );

        // all paths that are going to be used with provider
        foreach ($this->getRequiredAddrRootTable() as $addr) {
            $this->throwIfFalse(Arr::keysExists($this->getRootTables(), $addr), 'Address ' . implode(',', $addr) . ' not found in the root table');
        }
    }

    /**
     * Parse document and get ref num from it
     * @return string
     */
    abstract protected function getReferralNumber(): string;

    /**
     * Check that this referral number hasn't been used yet
     * @throws ImporterException
     */
    private function checkReferralNumber(): void
    {
        $this->throwIfFalse(
            $this->getServiceLocator()->get(AccidentService::class)->getCountByReferralNum($this->getReferralNumber()) === 0,
            'Referral number loaded'
        );
    }

    /**
     * Checks that accident has all required data to be stored
     * @throws ImporterException
     */
    protected function validateAccident(): void
    {
        if (!$this->isValidAccident) {
            $this->checkRootTables();
            $this->checkReferralNumber();
        }
    }

    /**
     * Creates blank models DoctorAccident and Accident
     */
    protected function createDoctorAccident(): void
    {
        /** @var Model $caseable */
        $caseable = $this->getServiceLocator()->get(DoctorAccidentService::class)->create();
        $this->accident = $this->getServiceLocator()->get(AccidentService::class)->create([
            'caseable_id' => $caseable->getAttribute('id'),
            'caseable_type' => DoctorAccident::class,
            'accident_type_id' => $this->getServiceLocator()->get(AccidentTypeService::class)->getInsuranceType()->getAttribute('id'),
        ]);
    }

    public function getAccident(): Accident
    {
        return $this->accident;
    }

    protected function afterImport(): void
    {
        $title = Str::limit('[' . $this->getAccident()->getAttribute('ref_num') . '] '
            . $this->getAccident()->getAttribute('patient')->getAttribute('name')
            . ' (' . $this->getAccident()->getAttribute('assistant')->getAttribute('title') . ')', 255);
        $this->getAccident()->update([
            'title' => $title,
            'accident_status_id' => $this->getStatusService()
                ->getImportedStatus()
                ->getAttribute('id'),
        ]);
    }

    private function getStatusService(): AccidentStatusesService
    {
        return $this->getServiceLocator()->get(AccidentStatusesService::class);
    }

    /**
     * Load documents into the case
     */
    private function loadDocuments(): void
    {
        $files = $this->getReaderService()->getImages();
        foreach ($files as $file) {
            /** @var Document $document */
            // todo it will create document unassigned, garbage
            $document = $this->getServiceLocator()->get(DocumentService::class)->create([
                'title' => $file['name']
            ]);

            $fileName = $this->getAccident()->getAttribute('id') . '_import.' . trim($file['ext']);
            if (Storage::disk('imports')->exists($fileName)) {
                Log::error('The same file will be overwritten with the import process', ['name' => $fileName]);
            }

            if ($this->isExcludedImage($file)) {
                continue;
            }

            Storage::disk('imports')->put($fileName, $file['imageContent']);
            $document
                ->addMedia(storage_path('imports'.DIRECTORY_SEPARATOR.$fileName))
                ->toMediaCollection(DocumentService::CASES_FOLDERS, DocumentService::DISC_IMPORTS);
            Storage::disk('imports')->delete($fileName);

            $this->getAccident()->documents()->attach($document);
            $this->getAccident()->getAttribute('patient')->documents()->attach($document);
        }
    }

    private function isExcludedImage($file): bool
    {
        $excludeDir = __DIR__ . DIRECTORY_SEPARATOR . 'exclude';
        $excludedFiles = new FilesystemIterator($excludeDir);
        /** @var SplFileInfo $exclude */
        foreach ($excludedFiles as $exclude) {
            if (strcmp($file['imageContent'], file_get_contents($exclude->getRealPath())) === 0) {
                return true;
            }
        }
        return false;
    }

    protected function getFromRootTable(array $path): array
    {
        $res = [];
        $table = $this->getRootTables();
        foreach ($path as $key) {
            if (is_array($table) && array_key_exists($key, $table)) {
                $table = $table[$key];
            }
        }
        if ($table) {
            $res = $table;
        }

        return $res;
    }

    protected function storeAssistant(string $title, string $comment): Assistant
    {
        /** @var Assistant $assistant */
        $assistant = $this->getServiceLocator()->get(AssistantService::class)->firstOrCreate(['title' => Str::title($title)]);
        if (empty($assistant->comment) && $comment){
            $assistant->update(['comment' => $comment]);
        }
        return $assistant;
    }

    protected function storePatient(string $patientRow): Patient
    {
        if (strpos($patientRow, ',') === false) {
            $data = ['name' => trim(Str::title($patientRow))];
        } else {
            [$name, $birthday] = explode(',', $patientRow);
            $birthday = date('Y-m-d', strtotime($birthday));
            $data = [
                'name' => trim(Str::title($name)),
                'birthday' => $birthday,
            ];
        }
        return $this->getServiceLocator()->get(PatientService::class)->firstOrCreate($data);
    }

    protected function storeDiagnose(string $diagnoseStr): void
    {
        $commaPos = mb_strrpos($diagnoseStr, ',');
        if ($commaPos) {
            $diagnose = $this->getServiceLocator()->get(DiagnosticService::class)->create([
                'title' => trim(mb_substr($diagnoseStr, 0, $commaPos)),
                'disease_code' => str_replace(' ', '', mb_substr($diagnoseStr, $commaPos + 1)),
            ]);
            $this->getAccident()->getAttribute('caseable')->diagnostics()->attach($diagnose);
        } else {
            Log::debug('Diagnostic last comma not found, but needed for the disease code');
        }
    }

    protected function storeService(string $title, string $description): void
    {
        /** @var DoctorServiceService $mService */
        $mService = $this->getServiceLocator()->get(DoctorServiceService::class)->create([
            'title' => $title,
            'description' => $description,
        ]);

        $this->getAccident()->getAttribute('caseable')->services()->attach($mService);
    }

    /**
     * @param string $price
     * @throws InconsistentDataException
     */
    protected function storeCaseablePayment(string $price): void
    {
        $price = str_replace(',', '.', $price);
        $price = (float) $price;
        /** @var CaseFinanceService $financeService */
        $financeService = $this->getServiceLocator()->get(CaseFinanceService::class);
        $financeService->save($this->getAccident(), 'caseable', ['price' => $price, 'fixed' => 1]);
    }
}
