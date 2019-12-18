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


use Dingo\Api\Http\Response;
use medcenter24\mcCore\App\Accident;
use medcenter24\mcCore\App\Http\Controllers\ApiController;
use medcenter24\mcCore\App\Services\Core\ServiceLocator\ServiceLocatorTrait;
use medcenter24\mcCore\App\Services\UploaderService;
use medcenter24\mcCore\App\Transformers\UploadedFileTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use medcenter24\McImport\Contract\CaseImporter;
use medcenter24\McImport\Exceptions\ImporterException;
use medcenter24\McImport\Services\CaseImporter\CaseImporterService;

class CasesImporterController extends ApiController
{
    use ServiceLocatorTrait;

    /**
     * @var UploaderService
     */
    private $uploaderService;

    /**
     * CasesImporterController constructor.
     * @param UploaderService $uploaderService
     */
    public function __construct(UploaderService $uploaderService)
    {
        parent::__construct();

        $this->uploaderService = $uploaderService;
        $this->uploaderService->setOptions([
            UploaderService::CONF_DISK => 'imports',
            UploaderService::CONF_FOLDER => 'cases',
        ]);
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function upload(Request $request): Response
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
     * @return Response
     */
    public function uploads(): Response
    {
        $uploadedCases = $this->user()->uploads()->where('storage', $this->uploaderService->getOption(UploaderService::CONF_FOLDER))->get();
        return $this->response->collection($uploadedCases, new UploadedFileTransformer);
    }

    /**
     * @param $id
     * @return Response
     * @throws ImporterException
     */
    public function import($id): Response
    {
        $path = $this->uploaderService->getPathById($id);
        /** @var CaseImporterService $importerService */
        $importerService = $this->getServiceLocator()->get(CaseImporter::class);
        $importerService->import($path);
        /** @var Accident $accident */
        $accident = current($importerService->getImportedAccidents());
        $this->uploaderService->delete($id);

        return $this->response->accepted(
            url('director/cases', [$accident->id]),
            ['uploadId' => $id, 'accidentId' => $accident->id]
        );
    }

    /**
     * Delete uploaded file
     * @param $id
     * @return Response
     */
    public function destroy ($id): Response
    {
        $this->uploaderService->delete($id);
        return $this->response->noContent();
    }
}
