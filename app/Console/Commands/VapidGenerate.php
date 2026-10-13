<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class VapidGenerate extends Command
{
    protected $signature = 'eip:vapid-generate';

    protected $description = '產生手機推播(Web Push)用的 VAPID 金鑰,請貼到 .env';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $this->line('VAPID_PUBLIC_KEY=' . $keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY=' . $keys['privateKey']);
        $this->newLine();
        $this->warn('私鑰等同密碼:只放在 .env,不要進 git;更換金鑰後所有裝置需重新開啟推播。');

        return self::SUCCESS;
    }
}
