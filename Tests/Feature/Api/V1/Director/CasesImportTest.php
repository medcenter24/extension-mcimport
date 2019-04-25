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

namespace medcenter24\mcCore\Tests\Feature\Api\Director;

use medcenter24\mcCore\App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use medcenter24\mcCore\Tests\Feature\Api\JwtHeaders;
use medcenter24\mcCore\Tests\Feature\Api\LoggedUser;
use medcenter24\mcCore\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use medcenter24\McImport\Services\CaseImporterService;

class CasesImportTest extends TestCase
{
    use DatabaseMigrations;
    use JwtHeaders;
    use LoggedUser;

    public function testOptions(): void
    {
        $user = factory(User::class)->create(['password' => bcrypt('foo')]);

        $response = $this->json('OPTIONS', '/api/director/cases/importer', [], $this->headers($user));
        $response->assertStatus(200)
            ->assertHeader('Allow', 'GET,HEAD,POST,PUT,DELETE');
    }

    public function testUpload(): void
    {
        Storage::fake(CaseImporterService::DISC_IMPORTS);

        $response = $this->json('POST', '/api/director/cases/importer',
            [[UploadedFile::fake()->create('imported.docx', 100)]]
            , $this->headers($this->getUser()));

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name']]]);

        $data = $response->json();

        // Assert the file was stored...
        self::assertEquals('imported.docx', $data['data'][0]['name']);
        self::assertCount(1, $this->getUser()->uploads()->get());
        self::assertEquals($data['data'][0]['id'], $this->getUser()->uploads()->first()->id);
    }
}
