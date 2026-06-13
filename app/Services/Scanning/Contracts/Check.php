<?php

namespace App\Services\Scanning\Contracts;

use App\Services\Scanning\CheckResult;

interface Check
{
    public function run(string $domain): CheckResult;
}
