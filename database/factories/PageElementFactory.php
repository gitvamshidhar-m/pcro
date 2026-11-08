<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\PageElement;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageElement>
 */
class PageElementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'page_id' => Page::factory(),
            'type' => 'cta',
            'tag' => 'a',
            'text' => 'Book a demo',
            'selector' => 'main a.button',
            'attributes' => ['href' => '/contact'],
            'position' => 1,
        ];
    }
}
