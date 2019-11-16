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
use medcenter24\mcCore\App\Exceptions\CommonException;
use medcenter24\mcCore\App\Helpers\ConverterHelper;
use medcenter24\mcCore\App\Helpers\FileHelper;
use medcenter24\McImport\Contract\CaseImporter;
use medcenter24\McImport\Services\CaseImporter\DryCaseGenerator;
use SplFileInfo;

/**
 * @Example
 * php artisan importer:path --path="/Users/MedCenter/documents/cases" // run import
 * php artisan importer:path --path="/Users/MedCenter/documents/cases" --show-not-imported=1
 * php artisan importer:path --path="/..." --show-not-imported=1 --vvv --dry-run=1 --short-table=1 // dev mode to clean view of import errors
 * --show-not-imported=1 --vvv --dry-run=1 --stop-on-error=1 // development mode to see first import error only
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
    protected $signature = 'importer:path {--path= : Path to the directory with files}
        {--show-not-imported= : Documents which can not be imported}
        {--vvv= : Show detailed reports}
        {--dry-run= : Do the fake import to see all the errors}
        {--short-table= : Cut paths to make table smaller}
        {--stop-on-error= : Stop import on first error}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import the cases (to load a lot of local files instead of GUI importer)';

    /**
     * @param CaseImporter $importerService
     */
    public function handle(CaseImporter $importerService): void
    {
        $path = $this->getPath();

        $this->info('Chosen path: ' . $path . '');

        $fileExtensions = $importerService->getImportableExtensions();
        $excludeRules = $importerService->getExcludeRules();

        $options = $importerService->getOptions();
        if ($this->hasOption('dry-run') && $this->option('dry-run')) {
            $options[CaseImporter::OPTION_CASE_GENERATOR] = new DryCaseGenerator();
        }
        if ($this->hasOption('vvv')) {
            $options[CaseImporter::OPTION_WITH_ERRORS] = true;
        }
        $importerService->setOptions($options);

        $totalSizeBytes = FileHelper::getSize($path, $fileExtensions, $excludeRules);
        $totalFilesCount = FileHelper::filesCount($path, $fileExtensions, $excludeRules);

        if ($this->confirm('Will be imported ' . ConverterHelper::formatBytes($totalSizeBytes) . ' from ' . $totalFilesCount . ' file(s)',
            true)) {
            $bar = $this->output->createProgressBar($totalFilesCount);
            $bar->start();

            $errorsCount = 0;
            $self = $this;

            $notImported = [];

            FileHelper::mapFiles($path, static function (SplFileInfo $fileInfo) use (
                $bar,
                $importerService,
                &$errorsCount,
                $self,
                &$notImported
            ) {

                $path = $fileInfo->getRealPath();

                if ($path) {
                    try {
                        $importerService->import($path);
                    } catch (CommonException $e) {
                        if ($self->hasOption('show-not-imported') && $self->option('show-not-imported')) {

                            $pathToShow = $path;
                            if ($self->hasOption('short-table') && $self->option('short-table')) {
                                $pathToShow = $fileInfo->getFilename();
                            }

                            $error = [[
                                'path' => $pathToShow,
                            ]];

                            if ($self->hasOption('vvv')) {
                                $error = [];
                                foreach ($importerService->getErrors() as $providerErrors) {
                                    foreach ($providerErrors as $providerError) {

                                        if ($self->hasOption('short-table') && $self->option('short-table')) {
                                            $providerError['dataProvider'] = str_replace('medcenter24\McDhv24\Services\Import\DataProviders\\', '', $providerError['dataProvider']);
                                        }

                                        $error[] = [
                                            'path' => $pathToShow,
                                            'dataProvider' => $providerError['dataProvider'],
                                            'cause' => $providerError['cause'],
                                            'details' => $providerError['details']
                                        ];
                                    }
                                }
                            }

                            $notImported += $error;
                        }
                        $errorsCount++;

                        if ($self->stopOnFirstError()) {
                            return false;
                        }
                    }
                }

                $bar->advance();

                return true;
            }, $fileExtensions, $excludeRules);

            $bar->finish();

            if ($self->hasOption('show-not-imported') && $this->option('show-not-imported') && count($notImported)) {
                $this->info("\n\n");
                $this->alert('Not Imported files');
                $headers = ['Path'];
                if ($self->hasOption('vvv')) {
                    $headers[] = 'Details';
                }
                $this->table($headers, $notImported);
            }

            $this->info("\n");
            if ($errorsCount) {
                if ($this->stopOnFirstError()) {
                    $this->error('Stopped on the first error');
                } else {
                    $this->error('Was not imported ' . $errorsCount . ' of ' . $totalFilesCount . ' case(s). Check log now to review them.');
                }
            } else {
                $this->info('Import of the ' . $totalFilesCount . ' case(s) finished with SUCCESS status.');
            }
        } else {
            $this->error('Stopped');
        }
    }

    private function stopOnFirstError(): bool
    {
        return $this->isOptionOn('stop-on-error');
    }

    private function isOptionOn(string $option): bool
    {
        return $this->hasOption($option) && $this->option($option);
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
