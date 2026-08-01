<?php

namespace Database\Seeders;

use App\Enums\ApplicationQuestionType;
use App\Enums\CoverLetterType;
use App\Models\Company;
use App\Models\CvFileType;
use App\Models\Job;
use App\Models\JobApplicationQuestion;
use App\Models\JobCriterion;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JobSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $company = Company::query()->updateOrCreate(
                ['slug' => 'gravity-labs'],
                [
                    'name' => 'Gravity Labs',
                    'plan_id' => Plan::default()->id,
                ],
            );

            User::query()
                ->where('email', 'admin@user.com')
                ->firstOrFail()
                ->companies()
                ->syncWithoutDetaching([$company->id]);

            $job = Job::query()->firstOrNew([
                'company_id' => $company->id,
                'name' => 'Senior Full Stack Engineer',
            ]);

            $job->fill([
                'description' => <<<'HTML'
<p>Join Gravity Labs to build thoughtful recruiting products used by growing teams around the world.</p>

<h2>What you will do</h2>

<ul>
    <li>Design and deliver end-to-end features with Laravel, React, TypeScript, and PostgreSQL.</li>
    <li>Turn product requirements into maintainable APIs and polished user experiences.</li>
    <li>Improve application performance, automated testing, observability, and developer tooling.</li>
    <li>Collaborate closely with product, design, and engineering from discovery through release.</li>
    <li>Review code and help evolve our technical standards as the team grows.</li>
</ul>

<h2>What we are looking for</h2>

<ul>
    <li>Strong professional experience building modern web applications.</li>
    <li>Solid knowledge of PHP, Laravel, React, TypeScript, HTML, and CSS.</li>
    <li>Confidence working with relational databases, queues, APIs, and automated tests.</li>
    <li>Clear communication, product awareness, and ownership of delivered work.</li>
    <li>Comfortable working in an English-speaking, remote-first environment.</li>
</ul>

<h2>Nice to have</h2>

<ul>
    <li>Experience with Filament, Livewire, Tailwind CSS, CI/CD, or AI-powered products.</li>
    <li>Familiarity with multi-tenant SaaS architecture and recruitment technology.</li>
</ul>

<p>We value practical engineering, kind collaboration, and people who care about the details without losing sight of the customer.</p>
HTML,
                'starts_at' => now()->subDays(7)->toDateString(),
                'ends_at' => now()->addMonths(2)->toDateString(),
                'campaign_expectation' => 'Hire one senior full stack engineer within 60 days, prioritizing strong Laravel and React experience, product thinking, communication, and pragmatic technical decisions.',
                'published' => true,
                'cover_letter_required' => true,
                'cover_letter_type' => CoverLetterType::Text,
            ]);

            if (! $job->exists) {
                $job->key = (string) Str::uuid();
            }

            $job->save();

            $job->acceptedCvTypes()->sync(
                CvFileType::query()->orderBy('sort')->pluck('id'),
            );

            $job->coverLetterFileTypes()->sync(
                CvFileType::query()->orderBy('sort')->pluck('id'),
            );

            JobApplicationQuestion::query()->whereBelongsTo($job)->delete();
            $job->applicationQuestions()->createMany([
                [
                    'company_id' => $company->id,
                    'question' => 'What name should we use when contacting you?',
                    'response_type' => ApplicationQuestionType::Text->value,
                    'description' => 'Share your preferred first and last name.',
                    'required' => true,
                    'sort' => 1,
                ],
                [
                    'company_id' => $company->id,
                    'question' => 'How many years of professional full stack development experience do you have?',
                    'response_type' => ApplicationQuestionType::Number->value,
                    'description' => 'Use whole years. Relevant freelance and consulting work counts.',
                    'required' => true,
                    'sort' => 2,
                ],
                [
                    'company_id' => $company->id,
                    'question' => 'Why are you interested in joining Gravity Labs?',
                    'response_type' => ApplicationQuestionType::Textarea->value,
                    'description' => 'Tell us what caught your attention about the role, product, or team.',
                    'required' => true,
                    'sort' => 3,
                ],
                [
                    'company_id' => $company->id,
                    'question' => 'Share a portfolio, GitHub profile, or project URL.',
                    'response_type' => ApplicationQuestionType::Text->value,
                    'description' => 'Optional, but useful if you have public work that represents your experience.',
                    'required' => false,
                    'sort' => 4,
                ],
            ]);

            JobCriterion::query()->whereBelongsTo($job)->delete();
            $job->jobCriteria()->createMany([
                [
                    'company_id' => $company->id,
                    'prompt' => 'Assess depth of PHP and Laravel experience, including APIs, queues, Eloquent, testing, and maintainable backend design.',
                    'weight' => 10,
                ],
                [
                    'company_id' => $company->id,
                    'prompt' => 'Assess React and TypeScript experience, attention to user experience, accessibility, and frontend code quality.',
                    'weight' => 9,
                ],
                [
                    'company_id' => $company->id,
                    'prompt' => 'Assess system design, relational database knowledge, performance awareness, and pragmatic technical decision-making.',
                    'weight' => 8,
                ],
                [
                    'company_id' => $company->id,
                    'prompt' => 'Assess written communication, collaboration, ownership, and ability to explain trade-offs clearly.',
                    'weight' => 8,
                ],
                [
                    'company_id' => $company->id,
                    'prompt' => 'Assess product thinking and evidence of delivering valuable features from discovery through production.',
                    'weight' => 7,
                ],
            ]);
        });
    }
}
