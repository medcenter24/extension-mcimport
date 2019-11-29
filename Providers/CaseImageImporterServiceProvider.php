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

namespace medcenter24\McImport\Providers;


use medcenter24\mcCore\App\Services\DomDocumentService;

/**
 * @todo implement the service which will load only the images that needed (excluding sign or logo) and bind them to the case
 * Class CaseImageImporterServiceProvider
 * @package medcenter24\McImport\Providers
 */
class CaseImageImporterServiceProvider
{
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(__CLASS__, static function() {
            return new CaseImageImporterService([
                // path to the examples of the excluded images
                CaseImageImporterService::OPTION_EXCLUDED_DIR => ''
            ]);
        });
    }
}