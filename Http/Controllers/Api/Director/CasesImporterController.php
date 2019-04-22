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

namespace medcenter24\McImport\Http\Controllers\Api\V1\Director;

use medcenter24\mcCore\App\Http\Controllers\ApiController;
use medcenter24\mcCore\App\Services\CaseImporterService;
use medcenter24\mcCore\App\Services\UploaderService;
use medcenter24\mcCore\App\Transformers\UploadedFileTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CasesImporterController extends ApiController
{
    /**
     * @var CaseImporterService
     */
    private $importerService;

    /**
     * @var UploaderService
     */
    private $uploaderService;

    /**
     * CasesImporterController constructor.
     * @param CaseImporterService $importerService
     * @param UploaderService $uploaderService
     */
    public function __construct(CaseImporterService $importerService, UploaderService $uploaderService)
    {
        parent::__construct();
        $this->importerService = $importerService;

        $this->uploaderService = $uploaderService;
        $this->uploaderService->setOptions([
            UploaderService::CONF_DISK => 'imports',
            UploaderService::CONF_FOLDER => 'cases',
        ]);
    }

    /**
     * @param Request $request
     * @return \Dingo\Api\Http\Response
     */
    public function upload(Request $request)
    {
        if (!count($request->allFiles())) {
            $this->response->errorBadRequest('You need to provide files for import');
        }

        $uploadedFiles = new Collection();
        foreach ($request->allFiles() as $file) {
            foreach ($file as $item) {
                $uploadedCase = $this->uploaderService->upload($item);
                $this->user()->uploads()->save($uploadedCase);
                $uploadedFiles->put($uploadedCase->id, $uploadedCase);
            }
        }

        return $this->response->collection($uploadedFiles, new UploadedFileTransformer);
    }

    /**
     * Already loaded list of files
     * @return \Dingo\Api\Http\Response
     */
    public function uploads()
    {
        $uploadedCases = $this->user()->uploads()->where('storage', $this->uploaderService->getOption(UploaderService::CONF_FOLDER))->get();
        return $this->response->collection($uploadedCases, new UploadedFileTransformer);
    }

    /**
     * @param $id
     * @return \Dingo\Api\Http\Response
     */
    public function import($id)
    {
        $path = $this->uploaderService->getPathById($id);
        $accident = $this->importerService->import($path);
        $this->uploaderService->delete($id);

        return $this->response->accepted(
            url('director/cases', [$accident->id]),
            ['uploadId' => $id, 'accidentId' => $accident->id]
        );
    }

    /**
     * Delete uploaded file
     * @param $id
     * @return \Dingo\Api\Http\Response
     */
    public function destroy ($id)
    {
        $this->uploaderService->delete($id);
        return $this->response->noContent();
    }
}
