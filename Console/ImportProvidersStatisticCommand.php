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

namespace medcenter24\McImport\Console;


use Illuminate\Console\Command;
use medcenter24\mcCore\App\Helpers\ConverterHelper;
use medcenter24\mcCore\App\Helpers\FileHelper;
use medcenter24\McImport\Contract\CaseImporter;
use medcenter24\McImport\Contract\CaseImporterDataProvider;
use medcenter24\McImport\Services\CaseImporter\CaseImporterService;
use SplFileInfo;

/**
 * importer:statistic --path="/path/to/files"
 *
 * Class ImportProvidersStatisticCommand
 * @package medcenter24\McImport\Console
 */
class ImportProvidersStatisticCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'importer:statistic
     {--path= : Path to the directory with files}
     {--show-multiple-stats= : Shows files that can be read by different providers}
     ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show how many files could be parsed by the provider';

    public function handle(CaseImporter $importerService): void
    {
        $path = $this->getPath();

        $this->info('Chosen path: ' . $path . '');
        $fileExtensions = $importerService->getImportableExtensions();
        $excludeRules = $importerService->getExcludeRules();

        $totalSizeBytes = FileHelper::getSize($path, $fileExtensions, $excludeRules);
        $totalFilesCount = FileHelper::filesCount($path, $fileExtensions, $excludeRules);
        
        $this->info('Found ' . ConverterHelper::formatBytes($totalSizeBytes) . ' in ' . $totalFilesCount . ' file(s)');

        $bar = $this->output->createProgressBar($totalFilesCount);
        $bar->start();

        $providers = [];
        $result = [];

        foreach ($importerService->getOption(CaseImporterService::OPTION_PROVIDERS) as $p) {
            $className = get_class($p);
            $name = str_replace('medcenter24\McDhv24\Services\Import\DataProviders\\', '', $className);
            $providers[$name] = $p;
            $result[$name] = 0;
        }

        $multipleProviders = [];

        $self = $this;
        FileHelper::mapFiles($path, static function (SplFileInfo $fileInfo) use (
            $bar,
            $providers,
            $self,
            &$result,
            &$multipleProviders
        ) {

            $path = $fileInfo->getRealPath();

            $fittedProviders = [];
            /**
             * @var string $name
             * @var CaseImporterDataProvider $provider
             */
            foreach ($providers as $name => $provider) {
                $provider->init($path);
                if ($provider->isFit()) {
                    if (!count($fittedProviders)) {
                        $result[$name]++;
                    }
                    $fittedProviders[] = [$name];
                }
            }

            if ($self->showMultiple() && count($fittedProviders) > 1) {
                $multipleProviders[$path] = $fittedProviders;
            }

            $bar->advance();
        }, $fileExtensions, $excludeRules);

        $bar->finish();

        $headers = ['Provider', 'Count fitted'];
        $data = [];
        $this->info('');
        $all = 0;
        foreach ($result as $key => $item) {
            $data[] = [$key, $item];
            $all += $item;
        }
        $percent = round(($all * 100) / $totalFilesCount, 3);
        $this->alert(sprintf('Can be imported %s from %s [%s %s]', $all, $totalFilesCount, $percent, '%'));
        $this->info('Stats:');
        $this->table($headers, $data);

        if ($this->showMultiple() && count($multipleProviders)) {
            $this->error('Some providers could be skipped, covered by many:');
            foreach ($multipleProviders as $path => $providers) {
                if (!is_array($providers)) {
                    var_dump($providers);
                }
                $this->warn('File path ' . $path);
                $this->table(['Providers'], $providers);
            }
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

    private function showMultiple(): bool
    {
        return $this->hasOption('show-multiple-stats') && $this->option('show-multiple-stats');
    }
}
