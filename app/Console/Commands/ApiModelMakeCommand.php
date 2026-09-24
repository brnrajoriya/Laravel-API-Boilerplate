<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GeneratesApiRequests;
use Illuminate\Foundation\Console\ModelMakeCommand;

/**
 * Replaces Laravel's `make:model`:
 *  - `-c/-r/--api/-a` always create the API controller (via make:controller --model),
 *  - `-R` without a controller creates `Requests/{Model}/Index|Store|UpdateRequest`.
 */
class ApiModelMakeCommand extends ModelMakeCommand
{
    use GeneratesApiRequests;

    protected function createController()
    {
        // Pass the model so the API stub (with requests) is used even for a plain `-c`.
        $this->input->setOption('api', true);

        parent::createController();
    }

    protected function createFormRequests()
    {
        $this->generateApiRequests($this->qualifyClass($this->getNameInput()));
    }
}
