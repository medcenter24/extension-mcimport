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

use Dingo\Api\Routing\Router;
use medcenter24\McImport\Http\Controllers\Api\V1\Director\CasesImporterController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/** @var Router $api */
$api = app(Router::class);

$api->group([
    'version' => 'v1',
    'middleware' => 'api',
    'prefix' => 'api',
], static function (Router $api) {
    $api->version('v1', ['middleware' => ['cors']], static function (Router $api) {
        $api->group([
            'middleware' => 'api.auth'
        ], static function (Router $api) {

            $api->group(['prefix' => 'director', 'middleware' => ['role:director']], static function (Router $api) {

                $api->group(['prefix' => 'cases'], static function (Router $api) {

                    $api->post('importer', CasesImporterController::class . '@upload');
                    $api->get('importer', CasesImporterController::class . '@uploads');
                    $api->put('importer/{id}', CasesImporterController::class . '@import');
                    $api->delete('importer/{id}', CasesImporterController::class . '@destroy');
                });
            });
        });
    });
});
