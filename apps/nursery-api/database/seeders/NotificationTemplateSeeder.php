<?php

namespace Database\Seeders;

use App\Modules\Notification\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['order_confirmed', 'email', 'Order confirmed — {{order_number}}', 'Hi {{customer_name}}, your order {{order_number}} is confirmed.'],
            ['order_shipped', 'email', 'Order shipped — {{order_number}}', 'Good news {{customer_name}}! Order {{order_number}} has shipped. Tracking: {{tracking_number}}'],
            ['order_delivered', 'email', 'Delivered — {{order_number}}', 'Order {{order_number}} was delivered. We hope your plants thrive!'],
            ['payment_failed', 'email', 'Payment failed — {{order_number}}', 'We could not process payment for {{order_number}}. Please retry from your account.'],
            ['subscription_cycle_created', 'email', 'Subscription order ready', 'Your subscription cycle order is ready. Please complete payment for the next delivery.'],
            ['return_updated', 'email', 'Return update', 'There is an update on your return request.'],
            ['review_moderated', 'email', 'Review update', 'Your product review was moderated.'],
            ['campaign_promo', 'email', '{{title}}', '{{body}}'],
        ];

        foreach ($rows as [$code, $channel, $subject, $body]) {
            NotificationTemplate::query()->updateOrCreate(
                ['code' => $code, 'channel' => $channel, 'locale' => 'en'],
                [
                    'name' => ucwords(str_replace('_', ' ', $code)),
                    'subject' => $subject,
                    'body' => $body,
                    'status' => 'active',
                ],
            );
        }
    }
}
