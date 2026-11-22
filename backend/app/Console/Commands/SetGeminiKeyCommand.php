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
        if ($listRes->successful()) {
            $allModels = $listRes->json('models') ?? [];
            $this->info("Found " . count($allModels) . " total models for this API key.");
            foreach ($allModels as $m) {
                if (in_array('generateContent', $m['supportedGenerationMethods'] ?? [])) {
                    $this->line(" - " . $m['name']);
                }
            }
        } else {
            $this->warn("Could not list models: " . $listRes->body());
        }

        // Candidate models with exact names from ListModels
        $candidateModels = [
            'models/gemini-2.5-flash',
            'models/gemini-flash-latest',
            'models/gemini-2.5-flash-lite',
            'models/gemini-3.5-flash',
            'models/gemini-3.8-flash',
            'models/gemini-pro-latest'
        ];
        $workingModel = null;

        foreach ($candidateModels as $model) {
            try {
                $response = Http::timeout(15)->post("https://generativelanguage.googleapis.com/v1beta/{$model}:generateContent?key={$key}", [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => 'أجب بكلمة واحدة فقط: ممتاز']
                            ]
                        ]
                    ]
                ]);

                $this->info("Model [{$model}] Status: " . $response->status());

                if ($response->successful()) {
                    $json = $response->json();
                    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $this->info("SUCCESS! Response from {$model}: " . trim($text));
                    $workingModel = $model;
                    break;
                } else {
                    $this->warn("Model {$model} returned: " . substr($response->body(), 0, 150));
                }
            } catch (\Exception $e) {
                $this->error("Exception on {$model}: " . $e->getMessage());
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
