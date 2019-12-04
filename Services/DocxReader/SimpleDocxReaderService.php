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


use DOMDocument;
use Illuminate\Support\Facades\Log;
use medcenter24\mcCore\App\Exceptions\InconsistentDataException;
use medcenter24\McImport\Contract\DocumentReaderService;
use ZipArchive;

/**
 * Very simple driver to extract data from .docx files
 * external sources don't needed, all you need that php
 *
 * Class SimpleDocxReaderService
 * @package medcenter24\mcCore\App\Services\DocxReader
 */
class SimpleDocxReaderService implements DocumentReaderService
{
    /**
     * @param string $filename
     * @return DOMDocument
     * @throws InconsistentDataException
     */
    public function getDom(string $filename): DOMDocument
    {
        return $this->readZippedXML($filename, 'word/document.xml');
    }

    /**
     * Get only text from the document
     *
     * @param string $filename
     * @return string
     * @throws InconsistentDataException
     */
    public function getText(string $filename): string
    {
        return strip_tags($this->getDom($filename)->saveXML());
    }

    /**
     * @param string $filename
     * @return array
     */
    public function getImages(string $filename): array
    {
        return $this->getZippedMedia($filename);
    }

    /**
     * Get dom of the document
     *
     * @param $archiveFile
     * @param $dataFile
     * @return DomDocument
     * @throws InconsistentDataException
     */
    private function readZippedXML(string $archiveFile, string $dataFile): DomDocument
    {
        // Create new ZIP archive
        $zip = new ZipArchive;

        // Open received archive file
        if (true === ($zipErr = $zip->open($archiveFile))) {
            // If done, search for the data file in the archive
            if (($index = $zip->locateName($dataFile)) !== false) {
                // If found, read it to the string
                $data = $zip->getFromIndex($index);
                // Close archive file
                $zip->close();
                // Load XML from a string
                // Skip errors and warnings
                $xml = new DOMDocument();
                $xml->loadXML($data, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                return $xml;
            }
            $zip->close();
        }
        throw new InconsistentDataException('File ' . $archiveFile . ' can not be read. Zip Error Code: ' . $zipErr);
    }

    /**
     * Get media from the document
     *
     * @param string $archiveFile
     * @return array of medias
     */
    private function getZippedMedia(string $archiveFile): array
    {
        $files = [];

        // Create new ZIP archive
        $zip = new ZipArchive;

        // Open received archive file
        if (true === $zip->open($archiveFile)) {
            // If done, search for the data file in the archive
            // loop through all the files in the archive
            for ( $i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                // is it an image
                if ( $entry['size'] > 0 && preg_match('#\.(jpg|gif|png|jpeg|bmp|wmf)$#i', $entry['name'] )) {
                    $file = $zip->getFromIndex($i);
                    if( $file ) {
                        $ext = pathinfo( basename( $entry['name'] ) . PHP_EOL, PATHINFO_EXTENSION);
                        $files[] = [
                            'name'  => trim($entry['name']),
                            'ext'   => trim($ext),
                            'imageContent' => $file,
                        ];
                    }
                }
            }
            $zip->close();
        } else {
            Log::error('File can not be read', ['file' => $archiveFile]);
        }

        // In case of failure return empty string
        return $files;
    }
}
