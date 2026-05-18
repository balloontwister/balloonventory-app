<?php

namespace Database\Seeders;

use App\Enums\AdminLevel;
use App\Models\BalloonList;
use App\Models\Business;
use App\Models\Membership;
use App\Models\User;
use App\Scopes\BusinessScope;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::withTrashed()->updateOrCreate(
            ['email' => 'todd@twistedballoon.com'],
            [
                'name' => 'Todd',
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'Zaphod1974')),
                'admin_level' => AdminLevel::SuperAdmin,
                'email_verified_at' => now(),
                'deleted_at' => null,
            ]
        );

        $business = Business::updateOrCreate(
            ['slug' => 'balloonventory'],
            [
                'name' => 'Balloonventory',
                'slug' => 'balloonventory',
            ]
        );

        // Ensure an owner membership exists
        $membership = Membership::firstOrCreate(
            [
                'user_id' => $user->id,
                'business_id' => $business->id,
            ],
            [
                'role' => 'owner',
                'business_badge_color' => '#6366F1',
                'joined_at' => now(),
            ]
        );

        // Seed the Favorites list if it doesn't exist for this business
        $hasFavorites = BalloonList::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $business->id)
            ->where('is_business_favorites', true)
            ->exists();

        if (! $hasFavorites) {
            BalloonList::withoutGlobalScope(BusinessScope::class)->create([
                'business_id' => $business->id,
                'name' => 'Favorites',
                'is_business_favorites' => true,
                'created_by_user_id' => $user->id,
            ]);
        }
    }
}
