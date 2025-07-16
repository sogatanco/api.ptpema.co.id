<?php

namespace App\Modules\Projects\Services;

use App\Models\Employe;
use App\Modules\Projects\Repositories\ProjectRepository;

class ProjectService
{
    public function __construct(protected ProjectRepository $repository){}

    public function listProjectByDivision()
    {
        $employeId = Employe::employeId();
        $divisionId = Employe::getEmployeDivision($employeId)->organization_id;
        return $this->repository->listProjectByDivision($divisionId);
    }
}
