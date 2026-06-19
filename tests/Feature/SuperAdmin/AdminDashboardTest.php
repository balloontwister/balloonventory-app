<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->superAdmin()->create(['email_verified_at' => now()]);
    }

    private function siteAdmin(): User
    {
        return User::factory()->siteAdmin()->create(['email_verified_at' => now()]);
    }

    // ── Dashboard ───────────────────────────────────────────────────────────────

    public function test_dashboard_lives_at_admin_and_returns_summary_counts(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/Dashboard')
                ->has('summary.users.total')
                ->has('summary.catalog.skus')
                ->has('summary.feedback.open')
                ->has('summary.tickets.open')
                ->has('summary.email.sent_30d')
            );
    }

    public function test_site_admin_can_view_dashboard(): void
    {
        $this->actingAs($this->siteAdmin())
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_old_super_admin_path_is_gone(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/super-admin')
            ->assertNotFound();
    }

    public function test_regular_user_cannot_view_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    // ── Tickets & Email now have their own pages ──────────────────────────────────

    public function test_tickets_index_renders_for_admins(): void
    {
        $this->actingAs($this->siteAdmin())
            ->get(route('admin.tickets.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/SupportTickets/Index')
                ->has('supportTickets')
            );
    }

    public function test_email_index_renders_for_admins(): void
    {
        $this->actingAs($this->siteAdmin())
            ->get(route('admin.email-templates.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/EmailTemplates/Index')
                ->has('templates')
                ->has('emailByDay')
                ->has('emailByMonth')
            );
    }

    // ── Super-Admin-only areas ────────────────────────────────────────────────────

    public function test_stub_pages_render_for_super_admin(): void
    {
        $admin = $this->superAdmin();

        foreach (['admin.subscriptions.index', 'admin.payments.index', 'admin.affiliates.index'] as $name) {
            $this->actingAs($admin)
                ->get(route($name))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('SuperAdmin/ComingSoon'));
        }
    }

    public function test_stub_pages_and_backups_are_forbidden_for_site_admins(): void
    {
        $siteAdmin = $this->siteAdmin();

        foreach ([
            'admin.backups.index',
            'admin.subscriptions.index',
            'admin.payments.index',
            'admin.affiliates.index',
        ] as $name) {
            $this->actingAs($siteAdmin)
                ->get(route($name))
                ->assertForbidden();
        }
    }
}
