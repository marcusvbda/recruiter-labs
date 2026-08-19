<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Pipeline;
use Illuminate\Database\Seeder;

class PipelineSeeder extends Seeder
{
    /**
     * Two contrasting recruitment processes for the demo company, with different
     * communication configured per stage so the on-enter emails can be exercised.
     *
     * Stage semantics are declared here, never inferred from names: `Offer` and
     * `Approved` are the last stages before a decision, `Hired`/`Transferred`
     * end the process positively and `Rejected` ends it without a hire.
     *
     * @var array<string, array{description: string, is_default: bool, statuses: list<array<string, mixed>>}>
     */
    private const PIPELINES = [
        'External Recruitment' => [
            'description' => 'Hiring process for candidates applying from outside the company.',
            'is_default' => true,
            'statuses' => [
                [
                    'name' => 'Applied',
                    'color' => '#3b82f6',
                    'sends_email' => true,
                    'email_subject' => 'We received your application - {{ job.title }}',
                    'email_body' => '<p>Hi {{ candidate.name }},</p><p>Thank you for applying for <strong>{{ job.title }}</strong> at {{ company.name }}. Your application is in and our team is reviewing it.</p><p>We will get back to you with an update as soon as we can.</p><p>Thanks,<br>{{ company.name }}</p>',
                ],
                [
                    'name' => 'Screening',
                    'color' => '#f59e0b',
                    'sends_email' => false,
                ],
                [
                    'name' => 'Technical Interview',
                    'color' => '#8b5cf6',
                    'sends_email' => true,
                    'email_subject' => 'Next step for {{ job.title }}: technical interview',
                    'email_body' => '<p>Hi {{ candidate.name }},</p><p>Good news — you have progressed to the technical interview stage for <strong>{{ job.title }}</strong>.</p><p>A member of our team will contact you at {{ candidate.email }} to agree on a date and time.</p><p>Talk soon,<br>{{ company.name }}</p>',
                ],
                [
                    'name' => 'Offer',
                    'color' => '#06b6d4',
                    'is_final_stage' => true,
                    'sends_email' => false,
                ],
                [
                    'name' => 'Hired',
                    'color' => '#22c55e',
                    'is_hired' => true,
                    'sends_email' => true,
                    'email_subject' => 'Welcome to {{ company.name }}!',
                    'email_body' => '<p>Hi {{ candidate.name }},</p><p>We are delighted to confirm that you have been hired for <strong>{{ job.title }}</strong>.</p><p>Our team will be in touch shortly with your onboarding details.</p><p>Welcome aboard,<br>{{ company.name }}</p>',
                ],
                [
                    'name' => 'Rejected',
                    'color' => '#ef4444',
                    'is_terminal' => true,
                    'sends_email' => true,
                    'email_subject' => 'Update regarding {{ job.title }}',
                    'email_body' => '<p>Hi {{ candidate.name }},</p><p>Thank you for the time you invested in your application for <strong>{{ job.title }}</strong> at {{ company.name }}.</p><p>After careful consideration we have decided not to move forward on this occasion. We would be glad to see you apply again for a future opening.</p><p>All the best,<br>{{ company.name }}</p>',
                ],
            ],
        ],
        'Internal Recruitment' => [
            'description' => 'Movement of existing employees into a different role.',
            'is_default' => false,
            'statuses' => [
                ['name' => 'Identified', 'color' => '#3b82f6', 'sends_email' => false],
                [
                    'name' => 'Manager Review',
                    'color' => '#f59e0b',
                    'sends_email' => true,
                    'email_subject' => 'Your internal application for {{ job.title }}',
                    'email_body' => '<p>Hi {{ candidate.name }},</p><p>Your interest in <strong>{{ job.title }}</strong> has been shared with the hiring manager for review.</p><p>Thanks,<br>{{ company.name }}</p>',
                ],
                ['name' => 'Internal Interview', 'color' => '#8b5cf6', 'sends_email' => false],
                ['name' => 'Approved', 'color' => '#06b6d4', 'is_final_stage' => true, 'sends_email' => false],
                [
                    'name' => 'Transferred',
                    'color' => '#22c55e',
                    'is_hired' => true,
                    'sends_email' => true,
                    'email_subject' => 'Your move to {{ job.title }} is confirmed',
                    'email_body' => '<p>Hi {{ candidate.name }},</p><p>Your transfer to <strong>{{ job.title }}</strong> has been confirmed. Your manager will follow up with the transition plan.</p><p>Congratulations,<br>{{ company.name }}</p>',
                ],
            ],
        ],
    ];

    public function run(): void
    {
        $company = Company::query()->where('slug', 'gravity-labs')->first();

        if (! $company instanceof Company) {
            return;
        }

        foreach (self::PIPELINES as $name => $definition) {
            $pipeline = Pipeline::query()->updateOrCreate(
                [
                    'company_id' => $company->getKey(),
                    'name' => $name,
                ],
                [
                    'description' => $definition['description'],
                    'is_default' => $definition['is_default'],
                ],
            );

            foreach ($definition['statuses'] as $order => $status) {
                $pipeline->statuses()->updateOrCreate(
                    ['name' => $status['name']],
                    [
                        'company_id' => $company->getKey(),
                        'color' => $status['color'],
                        'order' => $order + 1,
                        'is_final_stage' => $status['is_final_stage'] ?? false,
                        'is_hired' => $status['is_hired'] ?? false,
                        'is_terminal' => $status['is_terminal'] ?? false,
                        'sends_email' => $status['sends_email'],
                        'email_subject' => $status['email_subject'] ?? null,
                        'email_body' => $status['email_body'] ?? null,
                    ],
                );
            }
        }
    }
}
