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

namespace medcenter24\McImport\Contract;


use DOMDocument;

/**
 * Getting the base information from the docx file
 * Interface DocxReader
 * @package medcenter24\McImport\Contract
 */
interface DocumentReaderService
{
    /**
     * Object with parsed body
     * @param string $filename
     * @return mixed
     */
    public function getDom(string $filename): DOMDocument;

    /**
     * Get all text from the document
     * @param string $filename
     * @return string
     */
    public function getText(string $filename): string;

    /**
     * Load images from the docx
     * @param string $filename
     * @return array
     */
    public function getImages(string $filename): array;
}
