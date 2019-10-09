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


use medcenter24\mcCore\App\Accident;
use medcenter24\mcCore\Tests\TestCase;
use medcenter24\McImport\Contract\CaseGeneratorInterface;
use medcenter24\McImport\Contract\CaseImporterDataProvider;
use medcenter24\McImport\Exceptions\ImporterException;
use medcenter24\McImport\Services\CaseImporter\CaseImporterService;

class CaseImporterServiceTest extends TestCase
{

    public function testGetImportableExtensions(): void
    {
        $extensions = ['.doc', '.js', '.exe'];

        $dataProviderMock = $this->getMockBuilder(CaseImporterDataProvider::class)->getMock();
        $dataProviderMock->method('getFileExtensions')->willReturn($extensions);

        $generatorMock = $this->getMockBuilder(CaseGeneratorInterface::class)->getMock();

        $serviceMock = new CaseImporterService([
            CaseImporterService::OPTION_PROVIDERS => [$dataProviderMock],
            CaseImporterService::OPTION_CASE_GENERATOR => $generatorMock,
        ]);
        $ext = $serviceMock->getImportableExtensions();
        self::assertSame($extensions, $ext);
    }

    /**
     * @throws ImporterException
     */
    public function testImport(): void
    {
        $dataProviderMock = $this->createMock(CaseImporterDataProvider::class);
        $dataProviderMock->method('isFit')->willReturn(true);

        $mockedAccident = $this->createMock(Accident::class);
        $mockedAccident->method('getAttribute')->willReturn(1);

        $generatorMock = $this->getMockBuilder(CaseGeneratorInterface::class)->getMock();
        $generatorMock->method('createCase')->willReturn($mockedAccident);

        $service = new CaseImporterService([
            CaseImporterService::OPTION_PROVIDERS => [$dataProviderMock],
            CaseImporterService::OPTION_CASE_GENERATOR => $generatorMock,
        ]);
        $service->import('$path');
        self::assertSame([1], $service->getImportedAccidents());
    }
}
