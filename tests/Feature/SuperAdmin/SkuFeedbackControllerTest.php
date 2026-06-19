<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\FeedbackStatus;
use App\Models\Business;
use App\Models\Sku;
use App\Models\SkuFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkuFeedbackControllerTest extends TestCase
{
    use RefreshDatabase;

    private function seedFeedback(array $overrides = []): SkuFeedback
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $sku = Sku::factory()->create();

        return SkuFeedback::create(array_merge([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'sku_id' => $sku->id,
            'sku_name' => 'Reported Balloon',
            'field' => 'color',
            'current_value' => 'Fashion Red',
            'suggested_value' => 'Crystal Red',
            'note' => null,
            'status' => FeedbackStatus::Open,
        ], $overrides));
    }

    public function test_super_admin_can_view_the_feedback_log(): void
    {
        $feedback = $this->seedFeedback();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('super-admin.feedback.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/SkuFeedback/Index')
                ->has('feedback.data', 1)
                ->where('feedback.data.0.id', $feedback->id)
                ->where('openCount', 1)
            );
    }

    public function test_regular_user_cannot_view_the_feedback_log(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('super-admin.feedback.index'))
            ->assertForbidden();
    }

    public function test_feedback_can_be_filtered_by_status(): void
    {
        $this->seedFeedback(['sku_name' => 'Still Open']);
        $this->seedFeedback(['sku_name' => 'Done', 'status' => FeedbackStatus::Resolved]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('super-admin.feedback.index', ['status' => 'resolved']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('feedback.data', 1)
                ->where('feedback.data.0.sku_name', 'Done')
            );
    }

    public function test_resolving_stamps_the_reviewer_and_time(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $feedback = $this->seedFeedback();

        $this->actingAs($admin)
            ->patch(route('super-admin.feedback.update-status', $feedback->id), [
                'status' => 'resolved',
            ])
            ->assertSessionHas('success');

        $feedback->refresh();

        $this->assertSame(FeedbackStatus::Resolved, $feedback->status);
        $this->assertSame($admin->id, $feedback->resolved_by_user_id);
        $this->assertNotNull($feedback->resolved_at);
    }

    public function test_reopening_clears_the_review_stamp(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $feedback = $this->seedFeedback([
            'status' => FeedbackStatus::Resolved,
            'resolved_by_user_id' => $admin->id,
            'resolved_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('super-admin.feedback.update-status', $feedback->id), [
                'status' => 'open',
            ]);

        $feedback->refresh();

        $this->assertSame(FeedbackStatus::Open, $feedback->status);
        $this->assertNull($feedback->resolved_by_user_id);
        $this->assertNull($feedback->resolved_at);
    }

    public function test_update_status_rejects_an_invalid_status(): void
    {
        $feedback = $this->seedFeedback();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->patch(route('super-admin.feedback.update-status', $feedback->id), [
                'status' => 'archived',
            ])
            ->assertSessionHasErrors('status');
    }
}
