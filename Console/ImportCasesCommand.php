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


use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use medcenter24\mcCore\App\Helpers\ConverterHelper;
use medcenter24\mcCore\App\Helpers\FileHelper;
use medcenter24\McImport\Services\CaseImporterService;

class ImportCasesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'importer:path {path : Path to the directory with files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import of the cases (to load a lot of local files instead of GUI importer)';

    public function handle(CaseImporterService $importerService): void
    {
        $path = (string)$this->argument('path');
        while (empty($path)) {
            $path = (string)$this->ask('Path to directory with cases:');
        }
        // $this->runImporter($path);

        $this->info('Chosen path: ' . $path . '');

        $totalSizeBytes = FileHelper::getSize($path);
        $totalFilesCount = FileHelper::filesCount($path, ['*\.doc', '*\.docx']);


        if ( $this->confirm('Will be imported ' . ConverterHelper::formatBytes($totalSizeBytes).' from '.$totalFilesCount.' file(s)', true) ) {
            $bar = $this->output->createProgressBar($totalFilesCount);
            $bar->start();

            $errorsCount = 0;
            FileHelper::mapFiles($path, static function(\SplFileInfo $fileInfo) use ($bar, $importerService, &$errorsCount) {
                $converted = false;
                if (preg_match('/^*\.doc$/i', $fileInfo->getFilename()) !== false) {
                    $path = $this->convertFromDocToDocx($fileInfo);
                    $converted = true;
                } else {
                    $path = $fileInfo->getRealPath();
                }

                try {
                    // return Accident if needed
                    $importerService->import($path);
                }catch (\Exception $e) {
                    Log::alert('Case not imported', ['file' => $path, 'error' => $e->getMessage()]);
                    $errorsCount++;
                    // todo copy not imported files to the different directory, to not repeat this long action? or on the other hand we will just skip them?? test it
                }

                $bar->advance();

                if ($converted) {
                    // delete converted file
                    FileHelper::delete($path);
                }
            });

            $bar->finish();
            
            if ($errorsCount) {
                $this->error('Was not imported ' . $errorsCount . ' case(s). Check log now to review them.');
            }
        } else {
            $this->error('Stopped');
        }
    }

    private function convertFromDocToDocx(string $path): string
    {
        $PHPWord = new \PHPWord();
        $document = $PHPWord->loadTemplate($path);
        // Save File
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($PHPWord, 'Word2007');
        $objWriter->save($path . '.docx');
    }
}
