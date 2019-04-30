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

namespace medcenter24\McImport\Console;


use ErrorException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use medcenter24\mcCore\App\Helpers\ConverterHelper;
use medcenter24\mcCore\App\Helpers\FileHelper;
use medcenter24\McImport\Contract\CaseImporter;
use medcenter24\McImport\Services\ImporterException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\OLERead;
use SplFileInfo;

class ImportCasesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'importer:path {--path= : Path to the directory with files;}
        {--show-not-imported= : Documents which can not be imported;}
        {--show-not-converted= : Doc files that can not be converted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import of the cases (to load a lot of local files instead of GUI importer)';

    public function handle(CaseImporter $importerService): void
    {
        $path = $this->getPath();

        $this->info('Chosen path: ' . $path . '');

        $totalSizeBytes = FileHelper::getSize($path, ['doc', 'docx']);
        $totalFilesCount = FileHelper::filesCount($path, ['doc', 'docx']);

        if ( $this->confirm('Will be imported ' . ConverterHelper::formatBytes($totalSizeBytes).' from '.$totalFilesCount.' file(s)', true) ) {
            $bar = $this->output->createProgressBar($totalFilesCount);
            $bar->start();

            $errorsCount = 0;
            $self = $this;

            $notConverted = [];
            $notImported = [];

            FileHelper::mapFiles($path, static function(SplFileInfo $fileInfo) use ($bar, $importerService, &$errorsCount, $self, &$notConverted, &$notImported) {
                $converted = false;
                $filename = $fileInfo->getFilename();
                $last = mb_strcut($filename, -4);
                $path = '';

                // todo move all these selectors to doc provider (which will convert doc to docx) and to docx provider which works already, after that provider->check could decide to overwork this file
                if ($last === '.doc') {
                    try {
                        $path = $self->convertFromDocToDocx($fileInfo);
                        $converted = true;
                    } catch (ErrorException $e) {
                        $error = ['path' => $fileInfo->getRealPath(), 'error' => $e->getMessage()];
                        Log::alert('Case not imported. Doc file can not been converted.', $error);
                        if ($self->hasOption('show-not-converted') && $self->option('show-not-converted')) {
                            $notConverted[] = $error;
                        }

                        $errorsCount++;
                    }
                } elseif($last === 'docx') {
                    $path = $fileInfo->getRealPath();
                }

                if ($path) {
                    try {
                        // return Accident if needed
                        $importerService->import($path);
                    } catch (ImporterException $e) {
                        $error = ['path' => $fileInfo->getRealPath(), 'error' => $e->getMessage()];
                        Log::alert('Case not imported. ', $error);
                        if ($self->hasOption('show-not-imported') && $self->option('show-not-imported')) {
                            $notImported[] = $error;
                        }
                        $errorsCount++;
                        // todo copy not imported files to the different directory, to not repeat this long action? or on the other hand we will just skip them?? test it
                    }
                }

                $bar->advance();

                if ($converted) {
                    // delete converted file
                    FileHelper::delete($path);
                }
            }, ['doc', 'docx']);

            $bar->finish();

            if ($self->hasOption('show-not-converted') && $this->option('show-not-converted') && count($notConverted)) {
                $this->info("\n\n");
                $this->alert('Not Converted files');
                $headers = ['Path', 'Message'];
                $this->table($headers, $notConverted);
            }

            if ($self->hasOption('show-not-imported') && $this->option('show-not-imported') && count($notImported)) {
                $this->info("\n\n");
                $this->alert('Not Imported files');
                $headers = ['Path', 'Message'];
                $this->table($headers, $notImported);
            }

            if ($errorsCount) {
                $this->error('Was not imported ' . $errorsCount . ' of ' . $totalFilesCount . ' case(s). Check log now to review them.');
            } else {
                $this->info('Import of the ' . $totalFilesCount . ' case(s) finished with SUCCESS status.');
            }
        } else {
            $this->error('Stopped');
        }
    }

    private function getPath(): string
    {
        $path = (string)$this->option('path');
        while (empty($path)) {
            $path = (string)$this->ask('Path to directory with cases:');
        }
        return $path;
    }

    /**
     * @param string $path
     * @return string
     * @throws ImporterException
     * @throws \PhpOffice\PhpWord\Exception\Exception
     */
    private function convertFromDocToDocx(string $path): string
    {
        // Check if file exists and is readable
        if (!is_readable($path)) {
            throw new ImporterException('Could not open ' . $path . ' for reading! File does not exist, or it is not readable.');
        }

        // Get the file identifier
        // Don't bother reading the whole file until we know it's a valid OLE file
        $data = file_get_contents($path, false, null, 0, 8);

        // Check OLE identifier
        if ($data !== OLERead::IDENTIFIER_OLE) {
            throw new ImporterException('The filename ' . $path . ' is not recognised as an OLE file (probably tmp doc file were saved, ignore it)');
        }

        $phpWord = IOFactory::load($path, 'MsDoc');
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $path .= '.docx';
        $objWriter->save($path);
        return $path;
    }
}
