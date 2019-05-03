<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
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

namespace medcenter24\McImport\Tests\Unit\DocxParser;

use medcenter24\McImport\Services\DocxReader\SimpleDocxReaderService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;
use medcenter24\mcCore\Tests\SamplePath;
use medcenter24\mcCore\Tests\TestCase;

class SimpleDocxReaderTest extends TestCase
{
    use SamplePath;

    /**
     * @var SimpleDocxReaderService
     */
    private $service;

    protected function getService(): SimpleDocxReaderService
    {
        if (!$this->service) {
            $this->service = new SimpleDocxReaderService();
        }

        return $this->service;
    }

    /**
     * @param string $filePath
     * @dataProvider getSamples
     * @return void
     */
    public function testRead($filePath = ''): void
    {
        $this->getService()->load($filePath);
        self::assertContains('Computer science and informatics', $this->getService()->getText(), 'This text is correct');
    }

    /**
     * Array with files for test
     * @return array
     * @throws \ReflectionException
     */
    public function getSamples(): array
    {
        $directory = new RecursiveDirectoryIterator($this->getSamplePath());
        $iterator = new RecursiveIteratorIterator($directory);
        $regEx = new RegexIterator($iterator, '/^.+\.docx$/i', RecursiveRegexIterator::GET_MATCH);
        $files = [];
        foreach ($regEx as $file) {
            $files[] = $file;
        }

        return $files;
    }

    /**
     * test file iterator
     */
    public function testIterator(): void
    {
        $directory = new RecursiveDirectoryIterator($this->getSamplePath());
        $iterator = new RecursiveIteratorIterator($directory);
        $regEx = new RegexIterator($iterator, '/^.+\.docx$/ui', RecursiveRegexIterator::ALL_MATCHES);
        self::assertCount(3, $regEx, 'Files counted correctly');
    }
}
