<?php

namespace Tests\Feature;

use App\Enums\CrawlStatus;
use App\Enums\WorkspaceRole;
use App\Jobs\CrawlWebsiteJob;
use App\Models\Crawl;
use App\Models\Project;
use App\Models\User;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Url\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CrawlSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(HostResolver::class, new class implements HostResolver
        {
            public function resolve(string $host): array
            {
                return $host === 'example.com' ? ['93.184.216.34'] : [];
            }
        });
    }

    private function userWithWorkspace(WorkspaceRole $role = WorkspaceRole::Owner): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => WorkspaceRole::Owner->value]);

        $user = $owner;

        if ($role !== WorkspaceRole::Owner) {
            $user = User::factory()->create();
            $workspace->members()->attach($user->id, ['role' => $role->value]);
        }

        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        return [$user, $workspace];
    }

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get(route('analyses.create'))->assertRedirect(route('login'));
        $this->post(route('analyses.store'), ['url' => 'https://example.com'])->assertRedirect(route('login'));
    }

    public function test_a_workspace_member_can_queue_an_analysis(): void
    {
        Queue::fake();

        [$user, $workspace] = $this->userWithWorkspace();

        $response = $this->actingAs($user)->post(route('analyses.store'), [
            'url' => 'Example.com/pricing',
            'pages' => 15,
            'depth' => 2,
        ]);

        $crawl = Crawl::firstOrFail();

        $response->assertRedirect(route('analyses.show', $crawl));

        $this->assertSame($workspace->id, $crawl->workspace_id);
        $this->assertSame($user->id, $crawl->created_by);
        $this->assertSame(CrawlStatus::Queued, $crawl->status);
        $this->assertSame(15, $crawl->pages_requested);
        $this->assertSame(2, $crawl->max_depth);
        $this->assertSame('https://example.com/pricing', data_get($crawl->settings, 'target_url'));
        $this->assertSame('example.com', $crawl->project->website->domain);

        Queue::assertPushed(CrawlWebsiteJob::class, fn (CrawlWebsiteJob $job): bool => $job->crawl->is($crawl));
    }

    public function test_pages_and_depth_are_clamped_to_platform_limits(): void
    {
        Queue::fake();

        [$user] = $this->userWithWorkspace();

        $this->actingAs($user)->post(route('analyses.store'), [
            'url' => 'https://example.com',
            'pages' => (int) config('cro.crawl.max_pages'),
            'depth' => (int) config('cro.crawl.max_depth'),
        ])->assertRedirect();

        $crawl = Crawl::firstOrFail();

        $this->assertSame((int) config('cro.crawl.max_pages'), $crawl->pages_requested);
        $this->assertSame((int) config('cro.crawl.max_depth'), $crawl->max_depth);
    }

    public function test_out_of_range_crawl_settings_are_rejected(): void
    {
        Queue::fake();

        [$user] = $this->userWithWorkspace();

        $this->actingAs($user)
            ->from(route('analyses.create'))
            ->post(route('analyses.store'), [
                'url' => 'https://example.com',
                'pages' => 999,
                'depth' => 99,
            ])
            ->assertRedirect(route('analyses.create'))
            ->assertSessionHasErrors(['pages', 'depth']);

        $this->assertSame(0, Crawl::count());
        Queue::assertNothingPushed();
    }

    public function test_an_invalid_url_is_rejected(): void
    {
        Queue::fake();

        [$user] = $this->userWithWorkspace();

        $this->actingAs($user)
            ->from(route('analyses.create'))
            ->post(route('analyses.store'), ['url' => 'http://not a domain'])
            ->assertRedirect(route('analyses.create'))
            ->assertSessionHasErrors('url');

        $this->assertSame(0, Crawl::count());
        Queue::assertNothingPushed();
    }

    public function test_private_network_addresses_are_rejected(): void
    {
        Queue::fake();

        [$user] = $this->userWithWorkspace();

        foreach (['http://127.0.0.1', 'http://10.0.0.5', 'http://169.254.169.254'] as $url) {
            $this->actingAs($user)
                ->from(route('analyses.create'))
                ->post(route('analyses.store'), ['url' => $url])
                ->assertSessionHasErrors('url');
        }

        $this->assertSame(0, Crawl::count());
        Queue::assertNothingPushed();
    }

    public function test_a_viewer_cannot_start_an_analysis(): void
    {
        Queue::fake();

        [$viewer] = $this->userWithWorkspace(WorkspaceRole::Viewer);

        $this->actingAs($viewer)
            ->post(route('analyses.store'), ['url' => 'https://example.com'])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_a_member_of_another_workspace_cannot_view_or_retry_an_analysis(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => WorkspaceRole::Owner->value]);

        $website = Website::factory()->for($workspace)->create();
        $project = Project::factory()->for($workspace)->for($website)->create();
        $crawl = Crawl::factory()->for($workspace)->for($project)->completed()->create();

        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('analyses.show', $crawl))->assertForbidden();
        $this->actingAs($outsider)->getJson(route('analyses.status', $crawl))->assertForbidden();
        $this->actingAs($outsider)->post(route('analyses.retry', $crawl))->assertForbidden();
    }

    public function test_the_progress_endpoint_returns_a_json_snapshot(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        $website = Website::factory()->for($workspace)->create();
        $project = Project::factory()->for($workspace)->for($website)->create();
        $crawl = Crawl::factory()->for($workspace)->for($project)->running()->create([
            'pages_discovered' => 9,
            'pages_crawled' => 4,
        ]);

        $response = $this->actingAs($user)->getJson(route('analyses.status', $crawl));

        $response->assertOk()->assertJson([
            'status' => 'running',
            'stage' => 'crawling',
            'progress' => 35,
            'pages_discovered' => 9,
            'pages_crawled' => 4,
            'is_terminal' => false,
            'is_failed' => false,
        ]);
    }

    public function test_the_progress_screen_renders_for_the_owner(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        $website = Website::factory()->for($workspace)->create();
        $project = Project::factory()->for($workspace)->for($website)->create();
        $crawl = Crawl::factory()->for($workspace)->for($project)->create([
            'settings' => ['target_url' => 'https://example.com'],
        ]);

        $this->actingAs($user)
            ->get(route('analyses.show', $crawl))
            ->assertOk()
            ->assertSee('Queued')
            ->assertSee('https://example.com');
    }

    public function test_a_failed_analysis_can_be_retried(): void
    {
        Queue::fake();

        [$user, $workspace] = $this->userWithWorkspace();

        $website = Website::factory()->for($workspace)->create();
        $project = Project::factory()->for($workspace)->for($website)->create();
        $crawl = Crawl::factory()->for($workspace)->for($project)->failed()->create([
            'settings' => ['target_url' => 'https://example.com'],
        ]);

        $response = $this->actingAs($user)->post(route('analyses.retry', $crawl));

        $retry = Crawl::where('id', '!=', $crawl->id)->firstOrFail();

        $response->assertRedirect(route('analyses.show', $retry));
        $this->assertSame($crawl->ulid, data_get($retry->summary, 'retry_of'));
        Queue::assertPushed(CrawlWebsiteJob::class);
    }

    public function test_an_active_analysis_cannot_be_retried(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        $website = Website::factory()->for($workspace)->create();
        $project = Project::factory()->for($workspace)->for($website)->create();
        $crawl = Crawl::factory()->for($workspace)->for($project)->running()->create();

        $this->actingAs($user)
            ->from(route('analyses.show', $crawl))
            ->post(route('analyses.retry', $crawl))
            ->assertRedirect(route('analyses.show', $crawl))
            ->assertSessionHasErrors('crawl');
    }
}
