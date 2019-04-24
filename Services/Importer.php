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


namespace medcenter24\McImport\Services;


use medcenter24\mcCore\App\Support\Core\Configurable;
use Illuminate\Support\Facades\Log;

class Importer extends Configurable
{

    public function import($path = ''): void
    {
        foreach ($this->getOption('registeredDataProviders') as $registeredProvider) {

            if ($provider = $registeredProvider->load($path)->check()) {

                Log::debug('Provider has matched path', [
                    'provider' => $registeredProvider,
                    'path' => $path
                ]);

                $provider->import();
            } else {

                Log::debug('Provider does not match to path', [
                    'provider' => $registeredProvider,
                    'path' => $path
                ]);
            }
        }
    }
}
