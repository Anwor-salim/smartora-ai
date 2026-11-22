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

        $this->info("Testing Gemini Key with Google API...");

        // Try gemini-1.5-flash first, then gemini-2.0-flash
        $models = ['gemini-1.5-flash', 'gemini-2.0-flash', 'gemini-pro'];
        $workingModel = null;

        foreach ($models as $model) {
            try {
                $response = Http::timeout(15)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}", [
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
                    $this->info("Response from {$model}: " . trim($text));
                    $workingModel = $model;
                    break;
                } else {
                    $this->warn("Model {$model} returned error: " . $response->body());
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
