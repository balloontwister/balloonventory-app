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
                'key' => 'business_invitation',
                'label' => 'Business Invitation',
                'trigger_description' => 'Sent to an existing Balloonventory user when they are invited to join a business.',
                'subject' => 'You\'ve been invited to join {{business_name}} on Balloonventory',
                'body_html' => <<<'HTML'
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;">Hi {{user_name}},</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;"><strong>{{inviter_name}}</strong> has invited you to join <strong>{{business_name}}</strong> on Balloonventory as <strong>{{role_label}}</strong>.</p>
<p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#0A0A0A;">Click the button below to accept and get started right away — no password required.</p>
<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
    <tr>
        <td style="background:#6D28D9;border-radius:10px;">
            <a href="{{accept_url}}" style="display:inline-block;padding:12px 24px;font-size:14px;font-weight:600;color:#FFFFFF;text-decoration:none;">Accept invitation →</a>
        </td>
    </tr>
</table>
<p style="margin:0 0 16px;font-size:13px;line-height:1.6;color:#71717A;">This link expires in 14 days. If you weren't expecting this invitation, you can safely ignore this email.</p>
<p style="margin:0;font-size:15px;line-height:1.6;color:#0A0A0A;">See you there,<br><strong>Tallie</strong><br><span style="font-size:13px;color:#A1A1AA;">at Balloonventory</span></p>
HTML,
                'body_text' => <<<'TEXT'
Hi {{user_name}},

{{inviter_name}} has invited you to join {{business_name}} on Balloonventory as {{role_label}}.

Click the link below to accept — no password required:

{{accept_url}}

This link expires in 14 days. If you weren't expecting this invitation, you can safely ignore this email.

See you there,
Tallie
at Balloonventory
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
            [
                'key' => 'invitation_accepted',
                'label' => 'Invitation Accepted',
                'trigger_description' => 'Sent to a business owner when someone they invited accepts and joins the business.',
                'subject' => '{{actor_name}} joined {{business_name}}',
                'body_html' => <<<'HTML'
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;">Hi {{user_name}},</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;">Good news — <strong>{{actor_name}}</strong> accepted your invitation and has joined <strong>{{business_name}}</strong>.</p>
<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
    <tr>
        <td style="background:#6D28D9;border-radius:10px;">
            <a href="{{app_url}}" style="display:inline-block;padding:12px 24px;font-size:14px;font-weight:600;color:#FFFFFF;text-decoration:none;">Go to your dashboard →</a>
        </td>
    </tr>
</table>
<p style="margin:0;font-size:15px;line-height:1.6;color:#0A0A0A;">— Tallie at Balloonventory</p>
HTML,
                'body_text' => <<<'TEXT'
Hi {{user_name}},

Good news — {{actor_name}} accepted your invitation and has joined {{business_name}}.

Go to your dashboard: {{app_url}}

— Tallie at Balloonventory
TEXT,
                'is_active' => true,
            ],
            [
                'key' => 'member_left_business',
                'label' => 'Member Left Business',
                'trigger_description' => 'Sent to every owner of a business when a member removes themselves from it.',
                'subject' => '{{actor_name}} left {{business_name}}',
                'body_html' => <<<'HTML'
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;">Hi {{user_name}},</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;"><strong>{{actor_name}}</strong> has left <strong>{{business_name}}</strong> and no longer has access.</p>
<p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#0A0A0A;">If this was unexpected, you can re-invite them anytime from your business settings.</p>
<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
    <tr>
        <td style="background:#6D28D9;border-radius:10px;">
            <a href="{{app_url}}" style="display:inline-block;padding:12px 24px;font-size:14px;font-weight:600;color:#FFFFFF;text-decoration:none;">Go to your dashboard →</a>
        </td>
    </tr>
</table>
<p style="margin:0;font-size:15px;line-height:1.6;color:#0A0A0A;">— Tallie at Balloonventory</p>
HTML,
                'body_text' => <<<'TEXT'
Hi {{user_name}},

{{actor_name}} has left {{business_name}} and no longer has access.

If this was unexpected, you can re-invite them anytime from your business settings.

Go to your dashboard: {{app_url}}

— Tallie at Balloonventory
TEXT,
                'is_active' => true,
            ],
            [
                'key' => 'member_role_changed',
                'label' => 'Member Role Changed',
                'trigger_description' => 'Sent to a member when an owner changes their role within a business.',
                'subject' => 'Your role at {{business_name}} has changed',
                'body_html' => <<<'HTML'
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;">Hi {{user_name}},</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;">Your role at <strong>{{business_name}}</strong> is now <strong>{{role_label}}</strong>.</p>
<table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
    <tr>
        <td style="background:#6D28D9;border-radius:10px;">
            <a href="{{app_url}}" style="display:inline-block;padding:12px 24px;font-size:14px;font-weight:600;color:#FFFFFF;text-decoration:none;">Go to your dashboard →</a>
        </td>
    </tr>
</table>
<p style="margin:0;font-size:15px;line-height:1.6;color:#0A0A0A;">— Tallie at Balloonventory</p>
HTML,
                'body_text' => <<<'TEXT'
Hi {{user_name}},

Your role at {{business_name}} is now {{role_label}}.

Go to your dashboard: {{app_url}}

— Tallie at Balloonventory
TEXT,
                'is_active' => true,
            ],
            [
                'key' => 'member_removed',
                'label' => 'Member Removed',
                'trigger_description' => 'Sent to a member when an owner removes them from a business.',
                'subject' => 'Your access to {{business_name}} has been removed',
                'body_html' => <<<'HTML'
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;">Hi {{user_name}},</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;">Your access to <strong>{{business_name}}</strong> has been removed by a business owner.</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;">If you believe this was a mistake, please reach out to the business owner directly.</p>
<p style="margin:0;font-size:15px;line-height:1.6;color:#0A0A0A;">— Tallie at Balloonventory</p>
HTML,
                'body_text' => <<<'TEXT'
Hi {{user_name}},

Your access to {{business_name}} has been removed by a business owner.

If you believe this was a mistake, please reach out to the business owner directly.

— Tallie at Balloonventory
TEXT,
                'is_active' => true,
            ],
        ];

        // firstOrCreate (not updateOrCreate): seed missing templates only, never
        // overwrite an existing row. This keeps the seeder safe to re-run on
        // production, where admins may have edited template copy via the UI.
        foreach ($templates as $data) {
            EmailTemplate::firstOrCreate(
                ['key' => $data['key']],
                $data,
            );
        }
    }
}
