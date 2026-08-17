# Notification API

Base URL lokal: `http://127.0.0.1:8000/api/v1`

Semua endpoint membutuhkan header berikut dan akun dengan email terverifikasi:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

## Endpoint notifikasi

| Method | Endpoint | Fungsi |
|---|---|---|
| `GET` | `/notifications` | List dengan cursor pagination |
| `GET` | `/notifications/unread-count` | Counter ringan untuk badge navbar |
| `PATCH` | `/notifications/{uuid}/read` | Tandai satu notifikasi dibaca |
| `PATCH` | `/notifications/read-all` | Tandai semua notifikasi dibaca |
| `DELETE` | `/notifications/{uuid}` | Hapus notifikasi milik user |

Query list yang tersedia:

```text
status=all|read|unread
type=invoice.paid|invoice.due_soon|customer.created|report.weekly_ready|revenue.target_reached
perPage=1..50
cursor=CURSOR_DARI_RESPONSE
```

Contoh respons `GET /notifications?status=unread&perPage=20`:

```json
{
  "success": true,
  "data": {
    "notifications": [
      {
        "id": "19f357dc-4e7d-41b1-82d6-0ba039782f42",
        "type": "invoice.paid",
        "title": "Invoice #INV-2026-042 Lunas",
        "message": "Toko Budi telah melakukan pembayaran sebesar Rp 450.000.",
        "tone": "blue",
        "icon": "receipt",
        "actionUrl": "/invoices/42",
        "context": {"invoiceId": 42, "paymentId": 18, "amount": 450000},
        "isRead": false,
        "readAt": null,
        "createdAt": "2026-08-17T13:30:00+00:00"
      }
    ],
    "unreadCount": 3,
    "pagination": {
      "perPage": 20,
      "hasMore": false,
      "nextCursor": null,
      "previousCursor": null
    }
  }
}
```

Frontend menghitung teks relatif seperti "Baru saja" atau "2 jam lalu" dari `createdAt` dan menentukan grup Hari Ini/Sebelumnya. Jangan menyimpan hasil format relatif tersebut.

## Fetch frontend

```ts
export type ApiNotification = {
  id: string;
  type: "invoice.paid" | "invoice.due_soon" | "customer.created" |
    "report.weekly_ready" | "revenue.target_reached";
  title: string;
  message: string;
  tone: "blue" | "yellow" | "pink" | "muted";
  icon: "receipt" | "alert" | "celebration" | "customer" | "file";
  actionUrl: string | null;
  context: Record<string, string | number | boolean | null>;
  isRead: boolean;
  readAt: string | null;
  createdAt: string;
};

const response = await fetch(`${API_URL}/api/v1/notifications?status=all&perPage=20`, {
  headers: {
    Accept: "application/json",
    Authorization: `Bearer ${token}`,
  },
});
const payload = await response.json();
```

## Target pendapatan

| Method | Endpoint | Fungsi |
|---|---|---|
| `GET` | `/revenue-targets/{year}/{month}` | Target, revenue, dan progress periode |
| `PUT` | `/revenue-targets/{year}/{month}` | Simpan target dengan body `{"amount": 50000000}` |

Ketika pembayaran membuat revenue mencapai target, notifikasi `revenue.target_reached` hanya dibuat sekali.

## Laporan mingguan

| Method | Endpoint | Fungsi |
|---|---|---|
| `GET` | `/weekly-reports` | Histori laporan mingguan |
| `GET` | `/weekly-reports/{id}` | Detail metrik laporan |
| `GET` | `/weekly-reports/{id}/pdf` | Unduh PDF menggunakan Bearer token |

PDF harus diambil sebagai `blob` oleh frontend karena endpoint terproteksi Bearer token.

## Queue dan scheduler

Gunakan konfigurasi berikut di deployment:

```env
QUEUE_CONNECTION=database
QUEUE_AFTER_COMMIT=true
NOTIFICATION_TIMEZONE=Asia/Jakarta
NOTIFICATION_INVOICE_DUE_DAYS=2
NOTIFICATION_RETENTION_DAYS=180
```

Local development:

```bash
php artisan migrate
php artisan queue:work --queue=notifications,reports,default --tries=3
php artisan schedule:work
```

Production menjalankan `queue:work` melalui process manager seperti Supervisor/systemd dan `php artisan schedule:run` melalui cron setiap menit. Semua producer menggunakan dedupe key dan queued listener berjalan setelah transaksi database berhasil commit.
