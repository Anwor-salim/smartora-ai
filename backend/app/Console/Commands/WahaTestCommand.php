<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class WahaTestCommand extends Command
{
    protected $signature = 'waha:test {action=list} {name=default}';
    protected $description = 'Test WAHA API sessions';

    public function handle(): int
    {
        $action = $this->argument('action');
        $name = $this->argument('name');
        $url = env('WAHA_API_URL', 'http://smartora-waha:3000');
        $key = env('WAHA_API_KEY', 'smartora_secret_api_key_2026');

        $client = Http::withHeaders([
            'X-Api-Key' => $key,
            'Accept' => 'application/json',
        ]);

        if ($action === 'list') {
            $res = $client->get("{$url}/api/sessions");
            $this->info("HTTP " . $res->status());
            $this->line($res->body());
        } elseif ($action === 'create') {
            $res = $client->post("{$url}/api/sessions", [
                'name' => $name,
            ]);
            $this->info("HTTP " . $res->status());
            $this->line($res->body());
        } elseif ($action === 'start') {
            $res = $client->post("{$url}/api/sessions/{$name}/start");
            $this->info("HTTP " . $res->status());
            $this->line($res->body());
        } elseif ($action === 'page') {
            $user = \App\Models\User::where('company_id', 5)->first();
            if (!$user) {
                $this->error("No user for company 5");
                return 1;
            }
            auth()->login($user);
            $page = new \App\Filament\Company\Pages\WhatsAppConnection();
            $page->mount(app(\App\Services\WahaWhatsAppService::class));
            $this->info("Page mounted successfully!");
            $this->line("Status: " . $page->connectionStatus);
            $this->line("QR Url: " . ($page->qrCodeUrl ? substr($page->qrCodeUrl, 0, 40) . '...' : 'none'));
            $this->line("WhatsApp Number: " . $page->whatsappNumber);
        }

        return 0;
    }
}
