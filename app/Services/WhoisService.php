<?php

namespace App\Services;

use Carbon\Carbon;
use Iodev\Whois\Factory;

class WhoisService
{
    public function getExpiryDate(string $domain): ?Carbon
    {
        try {
            $whois = Factory::get()->createWhois();
            $info = $whois->loadDomainInfo($domain);

            if ($info === null || $info->expirationDate === null) {
                return null;
            }

            return Carbon::createFromTimestamp($info->expirationDate);
        } catch (\Throwable) {
            return null;
        }
    }
}
