<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\AiChatService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetGeminiKeyCommand extends Command
{
    protected $signature = 'company:set-gemini {key} {company_id=4}';
    protected $description = 'Set Gemini API key for a company and test connection';

    public function handle(): int
    {
        $key = trim($this->argument('key'));
        $companyId = $this->argument('company_id');

        $company = Company::find($companyId);
        if (!$company) {
            $this->error("Company {$companyId} not found.");
            return 1;
        }

        $this->info("Fetching available models from Google API...");
        $listRes = Http::timeout(15)->get("https://generativelanguage.googleapis.com/v1beta/models?key={$key}");
        $workingModel = null;

        if ($listRes->successful()) {
            $allModels = $listRes->json('models') ?? [];
            $this->info("Found " . count($allModels) . " total models for this API key.");
            
            foreach ($allModels as $m) {
                if (!in_array('generateContent', $m['supportedGenerationMethods'] ?? [])) continue;
                $modelName = $m['name']; // e.g. models/gemini-3.5-flash

                try {
                    $response = Http::timeout(6)->post("https://generativelanguage.googleapis.com/v1beta/{$modelName}:generateContent?key={$key}", [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    ['text' => 'مرحبا']
                                ]
                            ]
                        ]
                    ]);

                    $status = $response->status();
                    $bodySnippet = substr($response->body(), 0, 70);
                    $this->line("{$modelName} => {$status} ({$bodySnippet})");

                    if ($response->successful()) {
                        $this->info(">>> FOUND WORKING MODEL: {$modelName} <<<");
                        $workingModel = $modelName;
                        break;
                    }
                } catch (\Exception $e) {
                    $this->warn("{$modelName} => Exception: " . $e->getMessage());
                }
            }
        }

        if (!$workingModel) {
            $this->error("Could not verify key with tested Gemini models. Saving anyway to company settings.");
        }

        $settings = $company->settings ?? [];
        $settings['gemini_api_key'] = $key;
        if ($workingModel) {
            $settings['gemini_model'] = $workingModel;
        }
        $company->settings = $settings;
        $company->save();

        $this->info("Successfully saved gemini_api_key for Company {$company->name} (ID: {$company->id})!");
        return 0;
    }
}
