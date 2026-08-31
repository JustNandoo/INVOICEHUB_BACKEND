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

    /**
     * Akun yang diisi oleh seeder demo.
     *
     * Dipisahkan dari konstanta EMAIL supaya isi demo bisa diarahkan ke akun lain tanpa
     * menyunting berkas — cukup disetel sebelum seeder isinya dijalankan. Sengaja memakai
     * properti statis, bukan env(), karena produksi menjalankan config:cache dan env()
     * di luar berkas config akan mengembalikan null di sana.
     */
    public static string $targetEmail = self::EMAIL;

    public static function email(): string
    {
        return static::$targetEmail;
    }

    public function run(): void
    {
        /*
         * Seeder ini menghapus lalu membuat ulang penggunanya. Aman untuk akun demo bawaan
         * di mesin pengembang, tetapi menjalankannya di produksi berarti menghapus akun
         * sungguhan beserta seluruh datanya. Seeder isinya (Commerce, Finance, Tax,
         * Engagement) tidak menghapus apa pun dan tetap boleh dijalankan di mana saja.
         */
        if (app()->environment('production')) {
            $this->command?->error('DemoAccountSeeder menghapus dan membuat ulang pengguna. Dilarang jalan di produksi.');

            return;
        }

        $user = DB::transaction(function (): User {
            $existing = User::query()->where('email', self::email())->first();

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
                'email' => self::email(),
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
