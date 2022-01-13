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

namespace medcenter24\McImport\Tests\Unit\CaseParser;


use medcenter24\mcCore\App\Helpers\FileHelper;
use medcenter24\mcCore\Tests\TestCase;
use medcenter24\McImport\Contract\CaseImporterDataProvider;
use SplFileInfo;

abstract class AbstractParserTest extends TestCase
{
    abstract public function getSamplePath(): string;

    abstract public function getDataProvider(): CaseImporterDataProvider;

    abstract public function currentTemplatePath(): string;

    abstract public function getExpectedData(string $key);

    /**
     * List of files that could be converted too
     * @return array
     */
    protected function coveredTemplates(): array
    {
        return [];
    }
    
    /**
     * @return array
     */
    public function getTemplates(): array
    {
        $sampleDir = $this->getSamplePath();
        $paths = [];
        FileHelper::mapFiles($sampleDir, static function (SplFileInfo $file) use (&$paths) {
            $paths[] = [$file->getRealPath()];
        });

        return $paths;
    }

    public function testCoveredTemplates(): void
    {
        foreach ($this->coveredTemplates() as $coveredTemplate) {
            /** @var CaseImporterDataProvider $docxDataProvider */
            $provider = $this->getDataProvider();
            $provider->setStoreErrors(true);
            $provider->init($coveredTemplate);
            /* $provider->debugModeOn();
            $provider->setLogChannel('stderr'); */
            $this->assertTrue($provider->isFit(), 'File ' . $coveredTemplate . ' can not be parsed. Errors: '
                . print_r($provider->getErrors(), 1));
        }
        
        if (!count($this->coveredTemplates())) {
            $this->assertTrue(true, 'No templates to test');
        }
    }
    
    /**
     * @param string $path
     * @dataProvider getTemplates
     */
    public function testTemplates(string $path): void
    {
        if ($path === $this->currentTemplatePath() || in_array($path, $this->coveredTemplates(), true)) {
            $this->assertTrue(true);
            return;
        }
        /** @var CaseImporterDataProvider $docxDataProvider */
        $provider = $this->getDataProvider();
        $provider->init($path);
        /* $provider->debugModeOn();
        $provider->setLogChannel('stderr'); */
        $this->assertFalse($provider->isFit(), 'Path ' . $path . ' can be parsed');
    }

    public function testCurrentTemplate(): void
    {
        $provider = $this->getDataProvider();
        $provider->init($this->currentTemplatePath());

        $provider->debugModeOn();
        $provider->setLogChannel('stderr');

        foreach ([
                     'getAssistantTitle',
                     'getAssistantAddress',
                     'getPatientName',
                     'getPatientBirthday',
                     'getExternalRefNumber',
                     'getInternalRefNumber',
                     'getPatientSymptoms',
                     'getDoctorSurveys',
                     'getAdditionalDoctorInvestigation',
                     'getDoctorRecommendation',
                     'getDoctorDiagnostics',
                     'getDoctorName',
                     'getDoctorGender',
                     'getDoctorMedicalBoardingNum',
                     'getDoctorServices',
                     'getIncomePrice',
                     'getCurrency',
                     'getVisitTime',
                     'getVisitDate',
                     'getVisitCountry',
                     'getVisitRegion',
                     'getVisitCity',
                     'getParentAccidentMarkers',
                 ] as $method) {
            $this->assertSame($this->getExpectedData($method), $provider->$method(), 'Failed "' . $method . '" method');
        }

        // this assertion contents results of others, we need to keep it on very bottom
        $this->assertTrue($provider->isFit());
        // check medias
        $this->assertCount($this->getExpectedData('getImages'), $provider->getImages());
    }
}
