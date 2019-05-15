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
use medcenter24\McImport\Contract\DocxReaderService;
use ZipArchive;

/**
 * Very simple driver for reading of the docx files
 * external sources don't needed, all you need that php
 *
 * Class SimpleDocxReaderService
 * @package medcenter24\mcCore\App\Services\DocxReader
 */
class SimpleDocxReaderService implements DocxReaderService
{
    /**
     * Current file
     * @var string
     */
    private $filePath = '';

    /**
     * Parsed body
     * @var DOMDocument
     */
    private $dom;

    /**
     * Load file to read service
     *
     * @param string $path
     * @return $this
     */
    public function load($path = ''): DocxReaderService
    {
        $this->filePath = $path;
        return $this;
    }

    /**
     * File path
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * dom as it is
     */
    public function getDom(): DOMDocument
    {
        $this->dom = $this->docx2text($this->filePath);
        return $this->dom;
    }

    /**
     * Get only text from the document
     * @return string
     */
    public function getText(): string
    {
        return strip_tags($this->getDom()->saveXML());
    }

    public function getImages(): array
    {
        return $this->getZippedMedia($this->filePath);
    }

    /**
     * load file
     *
     * @param $filename
     * @return DOMDocument
     * @throws InconsistentDataException
     */
    private function docx2text($filename): DOMDocument
    {
        return $this->readZippedXML($filename, 'word/document.xml');
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
        Log::info('Open file to read', ['file' => $archiveFile]);
        // Create new ZIP archive
        $zip = new ZipArchive;

        // Open received archive file
        if (true === ($zipErr = $zip->open($archiveFile))) {
            Log::info('Zip was opened', ['file' => $archiveFile]);
            // If done, search for the data file in the archive
            if (($index = $zip->locateName($dataFile)) !== false) {
                Log::info('Index was found', ['file' => $archiveFile]);
                // If found, read it to the string
                $data = $zip->getFromIndex($index);
                // Close archive file
                $zip->close();
                // Load XML from a string
                // Skip errors and warnings
                $xml = new DOMDocument();
                $xml->loadXML($data, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                Log::info('XML was loaded', ['file' => $archiveFile]);
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

        Log::info('Open file to read', ['file' => $archiveFile]);
        // Create new ZIP archive
        $zip = new ZipArchive;

        // Open received archive file
        if (true === $zip->open($archiveFile)) {
            Log::info('Zip was opened', ['file' => $archiveFile]);
            // If done, search for the data file in the archive
            // loop through all the files in the archive
            for ( $i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                // is it an image
                if ( $entry['size'] > 0 && preg_match('#\.(jpg|gif|png|jpeg|bmp)$#i', $entry['name'] )) {
                    $file = $zip->getFromIndex($i);
                    if( $file ){
                        $ext = pathinfo( basename( $entry['name'] ) . PHP_EOL, PATHINFO_EXTENSION);
                        $files[] = [
                            'name'  => $entry['name'],
                            'ext'   => $ext,
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
