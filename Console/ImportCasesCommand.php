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


use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use medcenter24\mcCore\App\Exceptions\CommonException;
use medcenter24\mcCore\App\Helpers\ConverterHelper;
use medcenter24\mcCore\App\Helpers\FileHelper;
use medcenter24\McImport\Contract\CaseImporter;
use SplFileInfo;

/**
 * Example
 * php artisan import:path --path="/Users/MedCenter/documents/cases" --show-not-imported=1
 * Class ImportCasesCommand
 * @package medcenter24\McImport\Console
 */
class ImportCasesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'importer:path {--path= : Path to the directory with files;}
        {--show-not-imported= : Documents which can not be imported;}';

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

        $fileExtensions = $importerService->getImportableExtensions();
        $totalSizeBytes = FileHelper::getSize($path, $fileExtensions);
        $totalFilesCount = FileHelper::filesCount($path, $fileExtensions);

        if ( $this->confirm('Will be imported ' . ConverterHelper::formatBytes($totalSizeBytes).' from '.$totalFilesCount.' file(s)', true) ) {
            $bar = $this->output->createProgressBar($totalFilesCount);
            $bar->start();

            $errorsCount = 0;
            $self = $this;

            $notImported = [];

            FileHelper::mapFiles($path, static function(SplFileInfo $fileInfo) use ($bar, $importerService, &$errorsCount, $self, &$notImported) {
                $converted = false;
                $path = $fileInfo->getRealPath();

                if ($path) {
                    try {
                        // return Accident if needed
                        $importerService->import($path);
                    } catch (CommonException $e) {
                        $error = ['path' => $fileInfo->getRealPath(), 'error' => $e->getMessage()];
                        Log::alert('Case not imported. ', $error);
                        if ($self->hasOption('show-not-imported') && $self->option('show-not-imported')) {
                            $notImported[] = $error;
                        }
                        $errorsCount++;
                    }
                }

                $bar->advance();

                if ($converted) {
                    // delete converted file
                    FileHelper::delete($path);
                }
            }, $fileExtensions);

            $bar->finish();

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
}
