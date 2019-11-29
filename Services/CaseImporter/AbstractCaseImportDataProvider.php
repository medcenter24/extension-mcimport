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


use Carbon\Carbon;
use Exception;
use Illuminate\Support\Str;
use medcenter24\mcCore\App\Helpers\FileHelper;
use medcenter24\mcCore\App\Services\Core\Cache\ArrayCacheTrait;
use medcenter24\mcCore\App\Services\Core\Cache\CacheInterface;
use medcenter24\mcCore\App\Services\Core\Logger\DebugLoggerTrait;
use medcenter24\mcCore\App\Services\Core\Logger\LogInterface;
use medcenter24\McImport\Contract\CaseImporterDataProvider;
use medcenter24\McImport\Exceptions\ImporterException;

abstract class AbstractCaseImportDataProvider implements CaseImporterDataProvider, CacheInterface, LogInterface
{
    use DebugLoggerTrait;
    use ArrayCacheTrait;

    protected const RULE_BOOL = 'bool';
    protected const RULE_STRING = 'string';
    protected const RULE_ARRAY = 'array';
    protected const RULE_REQUIRED = 'required';
    protected const RULE_TRUE = 'true';
    protected const RULE_DATE = 'date';

    /**
     * Cache flag for import errors
     */
    protected const IMPORTS_ERRORS = 'importsErrors';

    /**
     * @var string readable file with data
     */
    private $path;

    /**
     * If we want to use import errors to understand why it is not imported
     * @var bool
     */
    private $storeErrors = false;

    /**
     * Initialize Data Provider with the file that is parsing
     * @param string $path
     * @return CaseImporterDataProvider
     */
    public function init(string $path): CaseImporterDataProvider
    {
        $this->path = $path;
        $this->dropCache();

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function isFit(): bool
    {
        return $this->getCachedResult('isRuleChecked');
    }

    /**
     * @param string $methodName
     * @return mixed
     */
    protected function getCachedResult(string $methodName)
    {
        $key = $methodName . '_method';
        if (!$this->hasCache($key)) {
            $this->setCache($key, $this->$methodName());
        }

        return $this->getCache($key);
    }

    /**
     * Checking that file has correct extension
     * Calls from string
     * @return bool
     */
    private function isFileValid(): bool
    {
        return FileHelper::isExpectedExtensions($this->getPath(), $this->getFileExtensions());
    }

    /**
     * Checking all provided rules
     * @return bool
     */
    private function isRuleChecked(): bool
    {
        $res = true;
        foreach ($this->getRules() as $method => $rule) {
            if (is_array($rule)) {
                foreach ($rule as $r) {
                    $res = $res && $this->checkRule($method, $r);
                }
            } else {
                $res = $res && $this->checkRule($method, $rule);
            }
        }
        return $res;
    }

    /**
     * @param string $method
     * @param string $rule
     * @return bool
     */
    private function checkRule(string $method, string $rule): bool
    {
        $res = false;
        $msg = '';
        try {
            $val = $this->$method();
            switch ($rule) {
                case self::RULE_ARRAY:
                    $res = is_array($val);
                    break;
                case self::RULE_BOOL:
                    $res = is_bool($val);
                    break;
                case self::RULE_STRING:
                    $res = is_string($val);
                    break;
                case self::RULE_REQUIRED:
                    $res = isset($val);
                    break;
                case self::RULE_TRUE:
                    $res = $val === true;
                    break;
                case self::RULE_DATE:
                    $res = false;
                    try {
                        Carbon::parse($val);
                        $res = true;
                    } catch (Exception $e) {}
            }
        } catch (ImporterException $e) {
            $msg = $e->getMessage();
        }

        if (!$res) {
            $this->addError(get_class($this), sprintf('%s !== %s', $method, $rule), $msg);

            $this->log('File "' . $this->path . '" can not be parsed with data provider "'
                . get_class($this) . '", cause data "' . $method . '" not matched to the rule "' . $rule . '"');
            if ($msg) {
                $this->log('Additional import info: '.$msg);
            }
        }

        return $res;
    }

    public function isStoreErrors(): bool
    {
        return $this->storeErrors;
    }

    public function setStoreErrors(bool $active = false): void
    {
        $this->storeErrors = $active;
    }

    protected function addError(string $dataProvider, string $cause, string $details): void
    {
        if ($this->isStoreErrors()) {
            $errors = $this->getErrors();
            $errors[] = [
                'dataProvider' => $dataProvider,
                'cause' => $cause,
                'details' => $details,
            ];
            $this->setCache(self::IMPORTS_ERRORS, $errors);
        }
    }

    /**
     * Stored import errors
     * @return array
     */
    public function getErrors(): array
    {
        return $this->hasCache(self::IMPORTS_ERRORS) ? $this->getCache(self::IMPORTS_ERRORS) : [];
    }

    /**
     * List of rules that have to be checked to validate importable document
     * The main goal of this method is not to validate data, but validate the parser
     * that the parser could get all the data from the places of the document
     * @return array
     */
    protected function getRules(): array
    {
        return [
            'getFileExtensions' => [self::RULE_ARRAY, self::RULE_REQUIRED],
            'isFileValid' => self::RULE_BOOL,
            'getInternalRefNumber' => [self::RULE_STRING, self::RULE_REQUIRED],
            'getExternalRefNumber' => [self::RULE_STRING, self::RULE_REQUIRED],
            'getAssistantTitle' => [self::RULE_STRING, self::RULE_REQUIRED],
            'getPatientContacts' => self::RULE_STRING,
            'getPatientName' => [self::RULE_STRING, self::RULE_REQUIRED],
            'getPatientBirthday' => [self::RULE_STRING, self::RULE_DATE],
            'getParentAccidentMarkers' => self::RULE_ARRAY,
            'getVisitTime' => [self::RULE_STRING, self::RULE_DATE],
            'getVisitDate' => [self::RULE_STRING, self::RULE_REQUIRED, self::RULE_DATE],
            'getVisitCountry' => self::RULE_STRING,
            'getVisitRegion' => self::RULE_STRING,
            'getVisitCity' => self::RULE_STRING,
            'getPatientSymptoms' => [self::RULE_STRING, self::RULE_REQUIRED],
            'getDoctorInvestigation' => [self::RULE_STRING, self::RULE_REQUIRED],
            'getDoctorRecommendation' => [self::RULE_STRING, self::RULE_REQUIRED],
            'getDoctorDiagnostics' => self::RULE_ARRAY,
            'getDoctorName' => [self::RULE_STRING, self::RULE_REQUIRED],
            'getDoctorMedicalBoardingNum' => self::RULE_STRING,
            'getDoctorGender' => [self::RULE_STRING, self::RULE_REQUIRED],
            'getImages' => self::RULE_ARRAY,
            'getCaseableType' => [self::RULE_STRING, self::RULE_REQUIRED]
        ];
    }

    /**
     * @param $condition
     * @param string $message
     * @throws ImporterException
     */
    protected function throwIfFalse($condition, $message = ''): void
    {
        if ($condition === false) {
            $this->log('Condition failed: ' . $message);
            throw new ImporterException($message);
        }
    }

    /**
     * @return string
     */
    abstract protected function getDoctorInvestigation(): string;

    /**
     * @return array
     * @example
     * [
     *   'title' => '',
     *   'description' => '',
     *   'disease_code' => '',
     * ]
     */
    public function getDoctorSurveys(): array
    {
        $surveysPlain = $this->getDoctorInvestigation();
        $surveys = explode('.', $surveysPlain);
        $surveysFormatted = [];
        foreach ($surveys as $survey) {
            $title = Str::ucfirst(trim($survey));
            if ($title) {
                $surveysFormatted[] = [
                    'title' => $title . '.',
                    'description' => 'test',
                    'disease_code' => ''
                ];
            }
        }
        return $surveysFormatted;
    }
}
