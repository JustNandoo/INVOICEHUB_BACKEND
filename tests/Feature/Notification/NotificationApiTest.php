<?php

namespace Tests\Feature\Notification;

use App\Enums\Notification\BusinessNotificationType;
use App\Enums\Notification\NotificationIcon;
use App\Enums\Notification\NotificationTone;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_api_requires_verified_authentication(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $user = User::factory()->unverified()->create();

        $this->withToken($this->token($user))->getJson('/api/v1/notifications')->assertForbidden();
    }

    public function test_user_can_list_and_filter_notifications_with_cursor_pagination(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $first = $this->notification($user, 'invoice-paid:1', BusinessNotificationType::InvoicePaid);
        $first->forceFill(['created_at' => now()->subMinute()])->save();
        $second = $this->notification($user, 'invoice-due:2', BusinessNotificationType::InvoiceDueSoon);
        $this->notification($other, 'invoice-paid:99', BusinessNotificationType::InvoicePaid);
        $first->markAsRead();

        $response = $this->withToken($this->token($user))->getJson('/api/v1/notifications?status=unread&perPage=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data.notifications')
            ->assertJsonPath('data.notifications.0.id', $second->id)
            ->assertJsonPath('data.notifications.0.type', BusinessNotificationType::InvoiceDueSoon->value)
            ->assertJsonPath('data.notifications.0.isRead', false)
            ->assertJsonPath('data.unreadCount', 1)
            ->assertJsonPath('data.pagination.perPage', 1);

        $this->withToken($this->token($user))->getJson('/api/v1/notifications?type=invoice.paid')
            ->assertOk()->assertJsonCount(1, 'data.notifications')
            ->assertJsonPath('data.notifications.0.id', $first->id);
    }

    public function test_user_can_read_all_delete_and_get_unread_count(): void
    {
        $user = User::factory()->create();
        $first = $this->notification($user, 'customer-created:1', BusinessNotificationType::CustomerCreated);
        $second = $this->notification($user, 'invoice-due:1', BusinessNotificationType::InvoiceDueSoon);
        $token = $this->token($user);

        $this->withToken($token)->getJson('/api/v1/notifications/unread-count')
            ->assertOk()->assertJsonPath('data.unreadCount', 2);
        $this->withToken($token)->patchJson("/api/v1/notifications/{$first->id}/read")
            ->assertOk()->assertJsonPath('data.notification.isRead', true);
        $this->withToken($token)->patchJson('/api/v1/notifications/read-all')
            ->assertOk()->assertJsonPath('data.updatedCount', 1)
            ->assertJsonPath('data.unreadCount', 0);
        $this->withToken($token)->deleteJson("/api/v1/notifications/{$second->id}")
            ->assertOk()->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('notifications', ['id' => $second->id]);
    }

    public function test_notifications_are_deduplicated_and_cannot_be_accessed_by_another_user(): void
    {
        $owner = User::factory()->create();
        $notification = $this->notification($owner, 'invoice-paid:42', BusinessNotificationType::InvoicePaid);
        $duplicate = $this->notification($owner, 'invoice-paid:42', BusinessNotificationType::InvoicePaid);
        $intruder = User::factory()->create();
        $token = $this->token($intruder);

        $this->assertSame($notification->id, $duplicate->id);
        $this->assertDatabaseCount('notifications', 1);
        $this->withToken($token)->patchJson("/api/v1/notifications/{$notification->id}/read")->assertNotFound();
        $this->withToken($token)->deleteJson("/api/v1/notifications/{$notification->id}")->assertNotFound();
    }

    private function notification(User $user, string $key, BusinessNotificationType $type)
    {
        return app(NotificationService::class)->createOnce(
            $user,
            $type,
            $key,
            'Test notification',
            'This notification was created by an automated test.',
            NotificationTone::Blue,
            NotificationIcon::Receipt,
            '/dashboard',
        );
    }

    private function token(User $user): string
    {
        return $user->createToken('notification-test')->plainTextToken;
    }
}
