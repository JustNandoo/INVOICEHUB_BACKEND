<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class DemoAccountSeeder extends Seeder
{
    public const EMAIL = 'demo@invoicehub.id';

    public const PASSWORD = 'DemoInvoiceHub2026!';

    public function run(): void
    {
        $user = DB::transaction(function (): User {
            $existing = User::query()->where('email', self::EMAIL)->first();

            if ($existing) {
                DatabaseNotification::query()
                    ->where('notifiable_type', $existing->getMorphClass())
                    ->where('notifiable_id', $existing->id)
                    ->delete();
                $existing->delete();
            }

            $now = CarbonImmutable::now();
            $user = User::query()->create([
                'name' => 'Rani Prameswari',
                'business_name' => 'Kopi Karsa Nusantara',
                'email' => self::EMAIL,
                'password' => self::PASSWORD,
                'terms_accepted_at' => $now->subYear(),
                'whatsapp' => '+6281234567890',
                'whatsapp_normalized' => '6281234567890',
                'city' => 'Bandung',
                'business_type' => 'retail',
                'password_changed_at' => $now->subMonths(3),
            ]);
            $user->forceFill([
                'role' => User::ROLE_USER,
                'email_verified_at' => $now->subYear()->addMinutes(4),
                'created_at' => $now->subYear(),
                'updated_at' => $now,
            ])->saveQuietly();

            app(SubscriptionService::class)->activatePlan(
                $user,
                'pro',
                'demo_seed',
                $now->addYear(),
                ['demoAccount' => true, 'note' => 'Local product demonstration account'],
            );

            return $user;
        });

        $this->command?->info("Creating complete demo data for {$user->email}...");
        $this->call([
            DemoCommerceSeeder::class,
            DemoFinanceSeeder::class,
            DemoTaxSeeder::class,
            DemoEngagementSeeder::class,
        ]);
    }
}
