<?php

namespace Database\Seeders;

use App\Models\AuditRule;
use Illuminate\Database\Seeder;

class AuditRuleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('cro.categories') as $category => $categoryConfig) {
            foreach ($categoryConfig['checks'] as $key => $check) {
                AuditRule::updateOrCreate(
                    ['rule_key' => $key],
                    [
                        'category' => $category,
                        'name' => $check['label'],
                        'description' => $check['recommendation'],
                        'severity' => $check['severity'],
                        'weight' => $check['weight'],
                        'config' => [
                            'confidence' => $check['confidence'],
                            'finding' => $check['finding'],
                            'opportunity' => $check['opportunity'],
                        ],
                        'rule_version' => config('cro.version'),
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
