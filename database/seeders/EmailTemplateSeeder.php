<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'key' => 'welcome',
                'label' => 'Welcome to Balloonventory',
                'trigger_description' => 'Sent automatically after a new user verifies their email address.',
                'subject' => 'Welcome to Balloonventory, {{user_name}}!',
                'body_html' => <<<'HTML'
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;">Hi {{user_name}},</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;">Welcome to Balloonventory — I'm so glad you're here.</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;">You're all set to start tracking your balloon inventory. Head to your dashboard to get started — you can set up your business, add your balloon catalog, and start scanning right away.</p>
<p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#0A0A0A;">If you ever have questions or get stuck, just reply to this email. I read every one.</p>
<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
    <tr>
        <td style="background:#6D28D9;border-radius:10px;">
            <a href="{{app_url}}" style="display:inline-block;padding:12px 24px;font-size:14px;font-weight:600;color:#FFFFFF;text-decoration:none;">Go to your dashboard →</a>
        </td>
    </tr>
</table>
<p style="margin:0;font-size:15px;line-height:1.6;color:#0A0A0A;">Happy inflating,<br><strong>Tallie</strong><br><span style="font-size:13px;color:#A1A1AA;">at Balloonventory</span></p>
HTML,
                'body_text' => <<<'TEXT'
Hi {{user_name}},

Welcome to Balloonventory — I'm so glad you're here.

You're all set to start tracking your balloon inventory. Head to your dashboard to get started — you can set up your business, add your balloon catalog, and start scanning right away.

Go to your dashboard: {{app_url}}

If you ever have questions or get stuck, just reply to this email. I read every one.

Happy inflating,
Tallie
at Balloonventory
TEXT,
                'is_active' => false,
            ],
            [
                'key' => 'password_changed_by_admin',
                'label' => 'Password Changed by Admin',
                'trigger_description' => 'Sent to a user when an administrator sets a new password for their account (only when the admin chooses to notify the user).',
                'subject' => 'Your Balloonventory password was changed',
                'body_html' => <<<'HTML'
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;">Hi {{user_name}},</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;">An administrator has set a new password for your Balloonventory account.</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;">If you did not expect this change, please contact our support team right away.</p>
<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
    <tr>
        <td style="background:#6D28D9;border-radius:10px;">
            <a href="{{app_url}}" style="display:inline-block;padding:12px 24px;font-size:14px;font-weight:600;color:#FFFFFF;text-decoration:none;">Go to your account →</a>
        </td>
    </tr>
</table>
<p style="margin:0;font-size:15px;line-height:1.6;color:#0A0A0A;">— Tallie at Balloonventory</p>
HTML,
                'body_text' => <<<'TEXT'
Hi {{user_name}},

An administrator has set a new password for your Balloonventory account.

If you did not expect this change, please contact our support team right away.

Go to your account: {{app_url}}

— Tallie at Balloonventory
TEXT,
                'is_active' => true,
            ],
            [
                'key' => 'subscription_upgrade',
                'label' => 'Subscription Upgrade Confirmation',
                'trigger_description' => 'Sent when a user upgrades their subscription plan.',
                'subject' => '',
                'body_html' => '',
                'body_text' => '',
                'is_active' => false,
            ],
        ];

        foreach ($templates as $data) {
            EmailTemplate::updateOrCreate(
                ['key' => $data['key']],
                $data,
            );
        }
    }
}
