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

namespace medcenter24\McImport\Services\DocxReader;


use medcenter24\McImport\Contract\DocToDocxConverter;
use medcenter24\McImport\Services\ImporterException;
use PhpOffice\PhpWord\Exception\Exception as OfficeException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\OLERead;

/**
 * MsDoc to Word2007 converter
 * Class DocToDocxConverter
 * @package medcenter24\McImport\Services\DocxReader
 */
class DocToDocxConverterService implements DocToDocxConverter
{
    /**
     * Converts from old format .doc to new .docx
     * @param string $docPath
     * @param string $toPath - if null then store file to the same dir with .docx ext
     * @return string
     * @throws ImporterException
     * @throws OfficeException
     */
    public function convert(string $docPath, string $toPath = null): string
    {
        // Check if file exists and is readable
        if (!is_readable($docPath)) {
            throw new ImporterException('Could not open ' . $docPath . ' for reading! File does not exist, or it is not readable.');
        }

        // Get the file identifier
        // Don't bother reading the whole file until we know it's a valid OLE file
        $data = file_get_contents($docPath, false, null, 0, 8);

        // Check OLE identifier
        if ($data !== OLERead::IDENTIFIER_OLE) {
            throw new ImporterException('The filename ' . $docPath . ' is not recognised as an OLE file (probably tmp doc file were saved, ignore it)');
        }

        $phpWord = IOFactory::load($docPath, 'MsDoc');
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        if (!$toPath) {
            $docPath .= '.docx';
        }
        $objWriter->save($docPath);

        return $docPath;
    }
}
