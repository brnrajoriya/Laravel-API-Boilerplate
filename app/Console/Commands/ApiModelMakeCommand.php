<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GeneratesApiFiles;
use Illuminate\Foundation\Console\ModelMakeCommand;

/**
 * Replaces Laravel's `make:model`:
 *  - `-c / -r / --api / -a` create the API controller (with requests, resource, policy and test),
 *  - `-R` without a controller creates `Requests/{Model}/Index|Store|UpdateRequest`,
 *  - `-p` creates the boilerplate's ownership-aware policy.
 */
class ApiModelMakeCommand extends ModelMakeCommand
{
    use GeneratesApiFiles;

    protected function createController()
    {
        // Pass the model so the API stub is used even for a plain `-c`.
        $this->input->setOption('api', true);

        parent::createController();
    }

    protected function createFormRequests()
    {
        $this->generateApiRequests($this->qualifyClass($this->getNameInput()));
    }

    protected function createPolicy()
    {
        $this->generateApiPolicy($this->qualifyClass($this->getNameInput()));
    }
}
