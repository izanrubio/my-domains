<?php

namespace App\Services;

use Carbon\Carbon;
use Iodev\Whois\Factory;

class WhoisService
{
    public function getExpiryDate(string $domain): ?Carbon
    {
        try {
            $info = $this->loadDomainInfo($domain);

            if ($info === null || $info->expirationDate === null) {
                return null;
            }

            return Carbon::createFromTimestamp($info->expirationDate);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function loadDomainInfo(string $domain): mixed
    {
        return Factory::get()->createWhois()->loadDomainInfo($domain);
    }
}
