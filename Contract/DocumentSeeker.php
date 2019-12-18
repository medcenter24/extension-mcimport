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

namespace medcenter24\McImport\Contract;

/**
 * Getting mapped data from the DocxReader initialized document
 *
 * Interface DocxParser
 * @package medcenter24\McImport\Contract
 */
interface DocumentSeeker
{
    /**
     * Initialize parser, create data abstractions to find the data from the reader
     * @param string $path
     */
    public function init(string $path): void;

    /**
     * DocumentSeeker has to be initialized to return the data
     * @return bool
     */
    public function isInitialized(): bool;

    /**
     * Looking for the data stored by the path
     * in the tabular presentation of data
     * @param array $mappedPath
     * [1,2,3] => returns rootTable[1][2][3]
     * @return array
     */
    public function rootTableSearch(array $mappedPath): array;

    /**
     * Looking for the data in the string representation of data
     * @param array $mappedPath
     * @return array
     */
    public function rowsSearch(array $mappedPath): array;

    /**
     * Searching for the images in the document
     * @return array
     */
    public function getImages(): array;

    /**
     * Images that have to be excluded
     * not readable images
     * @param array $excludeImages ['pathtoimg1', 'pathtoimg2']
     */
    public function excludeImages(array $excludeImages): void;
}
