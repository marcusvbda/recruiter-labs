<?php

namespace App\Http\Requests;

use App\Data\SubmitJobApplicationData;
use App\Enums\ApplicationQuestionType;
use App\Enums\CoverLetterType;
use App\Enums\PhoneCountry;
use App\Models\Job;
use App\Services\UtmParameterExtractor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;
use LogicException;

class SubmitJobApplicationRequest extends FormRequest
{
    private const MAX_TEXT_ANSWER_LENGTH = 1_000;

    private const MAX_TEXTAREA_ANSWER_LENGTH = 10_000;

    private ?Job $submissionJob = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $key = (string) $this->route('key');

        abort_unless(Str::isUuid($key), 404);

        $this->submissionJob = Job::query()
            ->with([
                'applicationQuestions:id,company_id,job_id,question,response_type,required,sort',
                'acceptedCvTypes:id,extension',
                'coverLetterFileTypes:id,extension',
            ])
            ->where('key', $key)
            ->first();

        abort_unless($this->submissionJob instanceof Job, 404);

        App::setLocale((string) $this->submissionJob->getRawOriginal('application_locale'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $job = $this->job();
        $cvExtensions = $job->acceptedCvTypes->pluck('extension')->map(
            fn (string $extension): string => Str::lower($extension),
        )->all();
        $coverLetterExtensions = $job->coverLetterFileTypes->pluck('extension')->map(
            fn (string $extension): string => Str::lower($extension),
        )->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone_country' => ['required', Rule::enum(PhoneCountry::class)],
            'phone' => ['nullable', 'string', 'max:40'],
            'cv' => ['required', File::types($cvExtensions)->extensions($cvExtensions)->max('10mb')],
            'cover_letter' => $job->getRawOriginal('cover_letter_type') === CoverLetterType::File->value
                ? [
                    $job->cover_letter_required ? 'required' : 'nullable',
                    File::types($coverLetterExtensions)->extensions($coverLetterExtensions)->max('10mb'),
                ]
                : [
                    $job->cover_letter_required ? 'required' : 'nullable',
                    'string',
                    'max:10000',
                ],
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable'],
            'referral_key' => ['nullable', 'uuid'],
            'utm' => ['nullable', 'array', 'max:20'],
            'utm.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateConfiguredFileTypes($validator);
                $this->validateAnswers($validator);
                $this->validateUtmNames($validator);
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => __('job_application.validation.required'),
            'string' => __('job_application.validation.string'),
            'email' => __('job_application.validation.email'),
            'max.string' => __('job_application.validation.max_string'),
            'max.file' => __('job_application.validation.max_file'),
            'file' => __('job_application.validation.file'),
            'mimes' => __('job_application.validation.file_type'),
            'mimetypes' => __('job_application.validation.file_type'),
            'extensions' => __('job_application.validation.file_type'),
            'numeric' => __('job_application.validation.numeric'),
            'uuid' => __('job_application.validation.uuid'),
            'enum' => __('job_application.validation.enum'),
            'array' => __('job_application.validation.array'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => __('job_application.form.full_name'),
            'email' => __('job_application.form.email_address'),
            'phone_country' => __('job_application.form.phone_country'),
            'phone' => __('job_application.form.phone'),
            'cv' => __('job_application.form.cv_resume'),
            'cover_letter' => __('job_application.form.cover_letter'),
            'answers' => __('job_application.form.role_questions'),
            'answers.*' => __('job_application.form.role_questions'),
            'referral_key' => __('referrals.label'),
            'utm' => 'UTM',
            'utm.*' => 'UTM',
        ];
    }

    public function job(): Job
    {
        if (! $this->submissionJob instanceof Job) {
            throw new LogicException('The job must be resolved before application validation.');
        }

        return $this->submissionJob;
    }

    public function toData(UtmParameterExtractor $utmParameterExtractor): SubmitJobApplicationData
    {
        $validated = $this->validated();
        $cv = $this->file('cv');

        if (! $cv instanceof UploadedFile) {
            throw new LogicException('A validated CV upload is required.');
        }

        return new SubmitJobApplicationData(
            name: (string) $validated['name'],
            email: (string) $validated['email'],
            phoneCountry: (string) $validated['phone_country'],
            phone: isset($validated['phone']) ? (string) $validated['phone'] : null,
            cv: $cv,
            coverLetter: $this->file('cover_letter') ?? Arr::get($validated, 'cover_letter'),
            answers: Arr::get($validated, 'answers', []),
            referralKey: isset($validated['referral_key']) ? (string) $validated['referral_key'] : null,
            utmParameters: $utmParameterExtractor->extract(
                $this->query(),
                Arr::get($validated, 'utm', []),
            ),
            ipAddress: $this->ip(),
        );
    }

    private function validateAnswers(Validator $validator): void
    {
        $submittedAnswers = $this->input('answers', []);

        if (! is_array($submittedAnswers)) {
            return;
        }

        $questions = $this->job()->applicationQuestions->keyBy('id');

        foreach ($submittedAnswers as $questionId => $value) {
            if (! ctype_digit((string) $questionId) || ! $questions->has((int) $questionId)) {
                $validator->errors()->add(
                    "answers.{$questionId}",
                    __('job_application.validation.unknown_question'),
                );

                continue;
            }

            if ($value !== null && ! is_scalar($value)) {
                $validator->errors()->add(
                    "answers.{$questionId}",
                    __('job_application.validation.string', ['attribute' => $this->attributes()['answers']]),
                );
            }
        }

        foreach ($questions as $question) {
            $attribute = "answers.{$question->getKey()}";
            $value = Arr::get($submittedAnswers, (string) $question->getKey());
            $responseType = ApplicationQuestionType::from(
                (string) $question->getRawOriginal('response_type'),
            );

            if ($question->required && blank($value) && $value !== 0 && $value !== '0') {
                $validator->errors()->add(
                    $attribute,
                    __('job_application.validation.required', ['attribute' => $question->question]),
                );

                continue;
            }

            if (blank($value) && $value !== 0 && $value !== '0') {
                continue;
            }

            if (
                $responseType === ApplicationQuestionType::Number
                && (! is_numeric($value) || ! preg_match('/^-?\d{1,16}(?:\.\d{1,4})?$/', (string) $value))
            ) {
                $validator->errors()->add(
                    $attribute,
                    __('job_application.validation.numeric', ['attribute' => $question->question]),
                );

                continue;
            }

            $maximumLength = $responseType === ApplicationQuestionType::Textarea
                ? self::MAX_TEXTAREA_ANSWER_LENGTH
                : self::MAX_TEXT_ANSWER_LENGTH;

            if ($responseType !== ApplicationQuestionType::Number && Str::length((string) $value) > $maximumLength) {
                $validator->errors()->add(
                    $attribute,
                    __('job_application.validation.max_string', [
                        'attribute' => $question->question,
                        'max' => $maximumLength,
                    ]),
                );
            }
        }
    }

    private function validateUtmNames(Validator $validator): void
    {
        $utmParameters = $this->input('utm', []);

        if (! is_array($utmParameters)) {
            return;
        }

        foreach (array_keys($utmParameters) as $name) {
            if (! preg_match('/^utm_[a-z0-9_]+$/', Str::lower((string) $name))) {
                $validator->errors()->add(
                    "utm.{$name}",
                    __('job_application.validation.invalid_utm'),
                );
            }
        }
    }

    private function validateConfiguredFileTypes(Validator $validator): void
    {
        if ($this->job()->acceptedCvTypes->isEmpty()) {
            $validator->errors()->add('cv', __('job_application.errors.job_unavailable'));
        }

        if (
            $this->job()->getRawOriginal('cover_letter_type') === CoverLetterType::File->value
            && $this->hasFile('cover_letter')
            && $this->job()->coverLetterFileTypes->isEmpty()
        ) {
            $validator->errors()->add('cover_letter', __('job_application.errors.job_unavailable'));
        }
    }
}
