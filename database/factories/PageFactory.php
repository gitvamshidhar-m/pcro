<?php

namespace Database\Factories;

use App\Enums\PageType;
use App\Models\Crawl;
use App\Models\Page;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    public function definition(): array
    {
        $path = '/'.Str::slug(fake()->words(3, true));
        $url = 'https://example.com'.$path;

        return [
            'workspace_id' => Workspace::factory(),
            'crawl_id' => Crawl::factory(),
            'url' => $url,
            'url_hash' => hash('sha256', $url),
            'path' => $path,
            'page_type' => PageType::Other,
            'depth' => 1,
            'status_code' => 200,
            'title' => fake()->sentence(6),
            'meta_description' => fake()->sentence(14),
            'canonical_url' => $url,
            'h1' => fake()->sentence(7),
            'word_count' => fake()->numberBetween(200, 1800),
            'content_hash' => hash('sha256', $url.'content'),
            'is_rendered' => true,
            'extraction' => [],
        ];
    }

    public function home(): static
    {
        return $this->state(fn (): array => [
            'path' => '/',
            'url' => 'https://example.com/',
            'url_hash' => hash('sha256', 'https://example.com/'),
            'page_type' => PageType::Home,
            'depth' => 0,
        ]);
    }

    public function contact(): static
    {
        return $this->state(fn (): array => [
            'path' => '/contact',
            'url' => 'https://example.com/contact',
            'url_hash' => hash('sha256', 'https://example.com/contact'),
            'page_type' => PageType::Contact,
        ]);
    }
}
