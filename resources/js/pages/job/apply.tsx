import { Head } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import type { Job } from '@/types/models/job';
import type { Referral } from '@/types/models/referral';

interface ApplyProps {
    referral?: Referral;
    job: Job;
    phoneCountries: PhoneCountryOption[];
    preview?: boolean;
}

interface PhoneCountryOption {
    value: string;
    label: string;
    calling_code: string;
    mask: string;
    placeholder: string;
}

interface IconProps {
    children: ReactNode;
    className?: string;
}

function Icon({ children, className = 'size-5' }: IconProps) {
    return (
        <svg
            aria-hidden="true"
            className={className}
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth="1.8"
            stroke="currentColor"
        >
            {children}
        </svg>
    );
}

function BriefcaseIcon({ className }: { className?: string }) {
    return (
        <Icon className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M20.25 14.15v4.1a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25v-4.1m16.5 0a2.25 2.25 0 0 0 1.5-2.12V8.75A2.25 2.25 0 0 0 19.5 6.5h-15a2.25 2.25 0 0 0-2.25 2.25v3.28a2.25 2.25 0 0 0 1.5 2.12m16.5 0a23.8 23.8 0 0 1-8.25 1.48 23.8 23.8 0 0 1-8.25-1.48M9 6.5V4.75A1.25 1.25 0 0 1 10.25 3.5h3.5A1.25 1.25 0 0 1 15 4.75V6.5m-4.5 5.25h3"
            />
        </Icon>
    );
}

function BuildingIcon({ className }: { className?: string }) {
    return (
        <Icon className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M3.75 21h16.5M5.25 21V5.25A2.25 2.25 0 0 1 7.5 3h5.25A2.25 2.25 0 0 1 15 5.25V21m3.75 0V9.75A2.25 2.25 0 0 0 16.5 7.5H15M8.25 7.5h3m-3 3h3m-3 3h3m-3 3h3M18 12h.01M18 15h.01M18 18h.01"
            />
        </Icon>
    );
}

function CalendarIcon({ className }: { className?: string }) {
    return (
        <Icon className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M6.75 3v2.25M17.25 3v2.25M3.75 9h16.5m-15-4.5h13.5A1.5 1.5 0 0 1 20.25 6v13.5A1.5 1.5 0 0 1 18.75 21H5.25a1.5 1.5 0 0 1-1.5-1.5V6a1.5 1.5 0 0 1 1.5-1.5Z"
            />
        </Icon>
    );
}

function DocumentIcon({ className }: { className?: string }) {
    return (
        <Icon className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M14.25 3.75H6.75A1.5 1.5 0 0 0 5.25 5.25v13.5a1.5 1.5 0 0 0 1.5 1.5h10.5a1.5 1.5 0 0 0 1.5-1.5V8.25m-4.5-4.5 4.5 4.5m-4.5-4.5v4.5h4.5M8.25 12h7.5m-7.5 3h7.5m-7.5 3h4.5"
            />
        </Icon>
    );
}

function SparklesIcon({ className }: { className?: string }) {
    return (
        <Icon className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M9.8 3.3c.2-.8 1.3-.8 1.5 0l.5 1.9a5.25 5.25 0 0 0 3.7 3.7l1.9.5c.8.2.8 1.3 0 1.5l-1.9.5a5.25 5.25 0 0 0-3.7 3.7l-.5 1.9c-.2.8-1.3.8-1.5 0l-.5-1.9a5.25 5.25 0 0 0-3.7-3.7l-1.9-.5c-.8-.2-.8-1.3 0-1.5l1.9-.5a5.25 5.25 0 0 0 3.7-3.7l.5-1.9Zm8.45 11.95.17.62c.2.73.77 1.3 1.5 1.5l.63.18-.63.17a2.18 2.18 0 0 0-1.5 1.5l-.17.63-.18-.63a2.18 2.18 0 0 0-1.5-1.5l-.62-.17.62-.18a2.18 2.18 0 0 0 1.5-1.5l.18-.62Z"
            />
        </Icon>
    );
}

function ArrowIcon({ className }: { className?: string }) {
    return (
        <Icon className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M5 12h14m-5.25-5.25L19 12l-5.25 5.25"
            />
        </Icon>
    );
}

function formatDate(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'long',
        timeZone: 'UTC',
        year: 'numeric',
    }).format(new Date(value));
}

function JobDescription({ description }: { description: string | null }) {
    if (!description) {
        return (
            <p className="leading-8 text-slate-600 dark:text-slate-300">
                More information about this opportunity will be available soon.
            </p>
        );
    }

    return (
        <div
            className="flex flex-col gap-5 leading-8 text-slate-600 dark:text-slate-300 [&_a]:font-medium [&_a]:text-blue-600 [&_a]:underline [&_a]:underline-offset-4 dark:[&_a]:text-blue-300 [&_blockquote]:border-l-4 [&_blockquote]:border-blue-200 [&_blockquote]:pl-4 dark:[&_blockquote]:border-blue-400/40 [&_code]:rounded [&_code]:bg-slate-100 [&_code]:px-1.5 [&_code]:py-0.5 dark:[&_code]:bg-white/10 [&_h2]:pt-3 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:tracking-tight [&_h2]:text-slate-950 dark:[&_h2]:text-white [&_h3]:pt-2 [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-slate-950 dark:[&_h3]:text-white [&_ol]:list-decimal [&_ol]:space-y-2 [&_ol]:pl-6 [&_pre]:overflow-x-auto [&_pre]:rounded-2xl [&_pre]:bg-slate-950 [&_pre]:p-5 [&_pre]:text-slate-100 [&_table]:w-full [&_table]:border-collapse [&_td]:border [&_td]:border-slate-200 [&_td]:p-3 dark:[&_td]:border-white/10 [&_th]:border [&_th]:border-slate-200 [&_th]:bg-slate-50 [&_th]:p-3 [&_th]:text-left dark:[&_th]:border-white/10 dark:[&_th]:bg-white/5 [&_ul]:list-disc [&_ul]:space-y-2 [&_ul]:pl-6"
            dangerouslySetInnerHTML={{ __html: description }}
        />
    );
}

const fieldClassName =
    'mt-2 block min-h-12 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-950 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 dark:border-white/10 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500';

function acceptedFileTypes(fileTypes: Job['accepted_cv_types']): string {
    return fileTypes.map((fileType) => `.${fileType.extension}`).join(',');
}

function formatPhone(value: string, mask: string): string {
    const digits = value.replace(/\D/g, '');
    let formatted = '';
    let digitIndex = 0;

    for (const character of mask) {
        if (character === '9') {
            if (digitIndex >= digits.length) {
                break;
            }

            formatted += digits[digitIndex];
            digitIndex += 1;
            continue;
        }

        if (digitIndex < digits.length) {
            formatted += character;
        }
    }

    return formatted;
}

interface ApplicationFormProps {
    job: Job;
    phoneCountries: PhoneCountryOption[];
    sectionRef: React.RefObject<HTMLElement | null>;
}

function ApplicationForm({
    job,
    phoneCountries,
    sectionRef,
}: ApplicationFormProps) {
    const defaultPhoneCountry =
        phoneCountries.find((country) => country.value === 'BR') ??
        phoneCountries[0];
    const [phoneCountry, setPhoneCountry] = useState(
        defaultPhoneCountry?.value ?? '',
    );
    const [phone, setPhone] = useState('');
    const selectedPhoneCountry =
        phoneCountries.find((country) => country.value === phoneCountry) ??
        defaultPhoneCountry;
    const coverLetterFileTypes = job.cover_letter_file_types ?? [];

    function preventSubmission(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
    }

    return (
        <section
            ref={sectionRef}
            id="application-form"
            className="scroll-mt-6 rounded-3xl border border-blue-100 bg-white p-6 shadow-xl shadow-blue-950/5 sm:p-8 lg:p-10 dark:border-blue-400/20 dark:bg-slate-900"
        >
            <div className="flex flex-col gap-3 border-b border-slate-100 pb-7 sm:flex-row sm:items-start sm:justify-between dark:border-white/10">
                <div>
                    <p className="text-xs font-semibold tracking-widest text-blue-600 uppercase dark:text-blue-300">
                        Your application
                    </p>
                    <h2 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">
                        Tell us about yourself
                    </h2>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Fields marked with an asterisk are required. Submission
                        will be enabled in a future release.
                    </p>
                </div>
                <span className="w-fit rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 ring-1 ring-amber-200 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/20">
                    Preview only
                </span>
            </div>

            <form onSubmit={preventSubmission} className="mt-8 space-y-9">
                <fieldset>
                    <legend className="text-base font-semibold text-slate-950 dark:text-white">
                        Contact information
                    </legend>
                    <div className="mt-5 grid gap-5 sm:grid-cols-2">
                        <label className="sm:col-span-2">
                            <span className="text-sm font-medium text-slate-700 dark:text-slate-200">
                                Full name{' '}
                                <span className="text-rose-500">*</span>
                            </span>
                            <input
                                type="text"
                                name="name"
                                required
                                maxLength={255}
                                autoComplete="name"
                                className={fieldClassName}
                                placeholder="How should we address you?"
                            />
                        </label>

                        <label>
                            <span className="text-sm font-medium text-slate-700 dark:text-slate-200">
                                Email address{' '}
                                <span className="font-normal text-slate-400">
                                    (optional)
                                </span>
                            </span>
                            <input
                                type="email"
                                name="email"
                                maxLength={255}
                                autoComplete="email"
                                className={fieldClassName}
                                placeholder="you@example.com"
                            />
                        </label>

                        <div>
                            <span className="text-sm font-medium text-slate-700 dark:text-slate-200">
                                Phone{' '}
                                <span className="font-normal text-slate-400">
                                    (optional)
                                </span>
                            </span>
                            <div className="mt-2 grid grid-cols-[minmax(8.5rem,0.8fr)_minmax(0,1.2fr)] gap-2">
                                <select
                                    name="phone_country"
                                    value={phoneCountry}
                                    required
                                    aria-label="Phone country"
                                    onChange={(event) => {
                                        setPhoneCountry(event.target.value);
                                        setPhone('');
                                    }}
                                    className="min-h-12 min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-950 shadow-sm transition outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 dark:border-white/10 dark:bg-slate-950 dark:text-white"
                                >
                                    {phoneCountries.map((country) => (
                                        <option
                                            key={country.value}
                                            value={country.value}
                                        >
                                            {country.label}
                                        </option>
                                    ))}
                                </select>
                                <div className="flex min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition focus-within:border-blue-500 focus-within:ring-4 focus-within:ring-blue-500/10 dark:border-white/10 dark:bg-slate-950">
                                    <span className="flex items-center border-r border-slate-200 px-3 text-sm font-medium text-slate-500 dark:border-white/10 dark:text-slate-400">
                                        {selectedPhoneCountry?.calling_code}
                                    </span>
                                    <input
                                        type="tel"
                                        name="phone"
                                        value={phone}
                                        inputMode="tel"
                                        autoComplete="tel-national"
                                        maxLength={
                                            selectedPhoneCountry?.mask.length
                                        }
                                        placeholder={
                                            selectedPhoneCountry?.placeholder
                                        }
                                        onChange={(event) =>
                                            setPhone(
                                                formatPhone(
                                                    event.target.value,
                                                    selectedPhoneCountry?.mask ??
                                                        '',
                                                ),
                                            )
                                        }
                                        className="min-h-12 min-w-0 flex-1 bg-transparent px-3 py-3 text-sm text-slate-950 outline-none placeholder:text-slate-400 dark:text-white dark:placeholder:text-slate-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <fieldset className="border-t border-slate-100 pt-8 dark:border-white/10">
                    <legend className="text-base font-semibold text-slate-950 dark:text-white">
                        Documents
                    </legend>
                    <div className="mt-5 grid gap-5">
                        <label>
                            <span className="text-sm font-medium text-slate-700 dark:text-slate-200">
                                CV / resume{' '}
                                <span className="text-rose-500">*</span>
                            </span>
                            <input
                                type="file"
                                name="cv"
                                required
                                accept={acceptedFileTypes(
                                    job.accepted_cv_types ?? [],
                                )}
                                className={`${fieldClassName} cursor-pointer file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-400/10 dark:file:text-blue-300`}
                            />
                            <span className="mt-2 block text-xs text-slate-500 dark:text-slate-400">
                                Accepted formats:{' '}
                                {(job.accepted_cv_types ?? [])
                                    .map((fileType) =>
                                        fileType.extension.toUpperCase(),
                                    )
                                    .join(', ')}
                            </span>
                        </label>

                        {job.cover_letter_type === 'file' ? (
                            <label>
                                <span className="text-sm font-medium text-slate-700 dark:text-slate-200">
                                    Cover letter{' '}
                                    {job.cover_letter_required ? (
                                        <span className="text-rose-500">*</span>
                                    ) : (
                                        <span className="font-normal text-slate-400">
                                            (optional)
                                        </span>
                                    )}
                                </span>
                                <input
                                    type="file"
                                    name="cover_letter"
                                    required={job.cover_letter_required}
                                    accept={acceptedFileTypes(
                                        coverLetterFileTypes,
                                    )}
                                    className={`${fieldClassName} cursor-pointer file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-400/10 dark:file:text-blue-300`}
                                />
                                <span className="mt-2 block text-xs text-slate-500 dark:text-slate-400">
                                    Accepted formats:{' '}
                                    {coverLetterFileTypes
                                        .map((fileType) =>
                                            fileType.extension.toUpperCase(),
                                        )
                                        .join(', ')}
                                </span>
                            </label>
                        ) : (
                            <label>
                                <span className="text-sm font-medium text-slate-700 dark:text-slate-200">
                                    Cover letter{' '}
                                    {job.cover_letter_required ? (
                                        <span className="text-rose-500">*</span>
                                    ) : (
                                        <span className="font-normal text-slate-400">
                                            (optional)
                                        </span>
                                    )}
                                </span>
                                <textarea
                                    name="cover_letter"
                                    required={job.cover_letter_required}
                                    rows={6}
                                    className={fieldClassName}
                                    placeholder="Tell us what makes this opportunity meaningful to you."
                                />
                            </label>
                        )}
                    </div>
                </fieldset>

                {job.application_questions.length > 0 && (
                    <fieldset className="border-t border-slate-100 pt-8 dark:border-white/10">
                        <legend className="text-base font-semibold text-slate-950 dark:text-white">
                            Role questions
                        </legend>
                        <div className="mt-5 grid gap-5">
                            {job.application_questions.map((question) => (
                                <label key={question.id}>
                                    <span className="text-sm font-medium text-slate-700 dark:text-slate-200">
                                        {question.question}{' '}
                                        {question.required ? (
                                            <span className="text-rose-500">
                                                *
                                            </span>
                                        ) : (
                                            <span className="font-normal text-slate-400">
                                                (optional)
                                            </span>
                                        )}
                                    </span>
                                    {question.response_type === 'textarea' ? (
                                        <textarea
                                            name={`question_${question.id}`}
                                            required={question.required}
                                            rows={5}
                                            className={fieldClassName}
                                        />
                                    ) : (
                                        <input
                                            type={
                                                question.response_type ===
                                                'number'
                                                    ? 'number'
                                                    : 'text'
                                            }
                                            name={`question_${question.id}`}
                                            required={question.required}
                                            className={fieldClassName}
                                        />
                                    )}
                                    {question.description && (
                                        <span className="mt-2 block text-xs leading-5 text-slate-500 dark:text-slate-400">
                                            {question.description}
                                        </span>
                                    )}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                )}

                <div className="border-t border-slate-100 pt-7 dark:border-white/10">
                    <button
                        type="submit"
                        disabled
                        className="inline-flex min-h-12 w-full cursor-not-allowed items-center justify-center gap-2 rounded-xl bg-slate-300 px-6 py-3 text-sm font-semibold text-slate-600 sm:w-auto dark:bg-white/10 dark:text-slate-400"
                    >
                        Submit application — coming soon
                    </button>
                    <p className="mt-3 text-xs leading-5 text-slate-400">
                        No information entered on this screen will be submitted
                        or stored yet.
                    </p>
                </div>
            </form>
        </section>
    );
}

export default function Apply({
    referral,
    job,
    phoneCountries,
    preview = false,
}: ApplyProps) {
    const [showApplicationForm, setShowApplicationForm] = useState(false);
    const applicationFormRef = useRef<HTMLElement>(null);
    const companyName = job.company?.name ?? 'Recruiter Labs';
    const closingDate = formatDate(job.ends_at);
    const openingDate = formatDate(job.starts_at);
    const questions = job.application_questions ?? [];
    const cvTypes = job.accepted_cv_types ?? [];

    function openApplicationForm() {
        setShowApplicationForm(true);

        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => {
                applicationFormRef.current?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            });
        });
    }

    return (
        <>
            <Head title={`${job.name} at ${companyName}`}>
                <meta
                    name="description"
                    content={`Explore the ${job.name} opportunity at ${companyName}.`}
                />
            </Head>

            <div className="relative min-h-screen overflow-hidden bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-white">
                <div className="pointer-events-none absolute inset-x-0 top-0 h-[38rem] bg-[radial-gradient(circle_at_top_left,rgba(37,99,235,0.12),transparent_42%),radial-gradient(circle_at_top_right,rgba(6,182,212,0.12),transparent_38%)] dark:bg-[radial-gradient(circle_at_top_left,rgba(37,99,235,0.18),transparent_42%),radial-gradient(circle_at_top_right,rgba(6,182,212,0.14),transparent_38%)]" />

                <header className="relative mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-8 sm:py-7 lg:px-10">
                    <div className="flex min-w-0 items-center gap-3 sm:gap-4">
                        <div className="px-3 py-2 shadow-sm">
                            <img
                                src="/assets/image/logo-white.png"
                                alt="RecruiterLabs"
                                className="h-7 w-auto sm:h-9"
                            />
                        </div>
                        <div className="hidden min-w-0 sm:block">
                            <p className="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                Careers at {companyName}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                Find work worth doing
                            </p>
                        </div>
                    </div>

                    <span className="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-sm dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-300">
                        <span className="size-2 rounded-full bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,0.12)]" />
                        {preview ? 'Preview mode' : 'Applications open'}
                    </span>
                </header>

                <main className="relative mx-auto w-full max-w-7xl px-5 pb-16 sm:px-8 sm:pb-20 lg:px-10">
                    {preview && (
                        <div className="mb-5 flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 shadow-sm dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white">
                                <DocumentIcon className="size-5" />
                            </span>
                            <p>
                                Preview mode. You can inspect and fill the form,
                                but no application can be submitted.
                            </p>
                        </div>
                    )}

                    {referral && (
                        <div className="mb-5 flex items-center gap-3 rounded-2xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-800 shadow-sm dark:border-violet-400/20 dark:bg-violet-400/10 dark:text-violet-200">
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-violet-600 text-white">
                                <SparklesIcon className="size-5" />
                            </span>
                            <p>
                                You were referred for this opportunity. We are
                                glad you are here.
                            </p>
                        </div>
                    )}

                    <section className="relative isolate overflow-hidden rounded-[2rem] bg-linear-to-br from-blue-700 via-blue-600 to-cyan-500 px-6 py-8 text-white shadow-2xl shadow-blue-950/15 sm:px-10 sm:py-11 lg:px-14 lg:py-14">
                        <div className="absolute -top-36 -right-24 -z-10 size-96 rounded-full bg-white/15 blur-3xl" />
                        <div className="absolute -bottom-48 left-1/3 -z-10 size-[28rem] rounded-full bg-cyan-200/20 blur-3xl" />

                        <div className="grid items-center gap-10 lg:grid-cols-[minmax(0,1fr)_22rem] lg:gap-14">
                            <div className="max-w-3xl">
                                <div className="flex flex-wrap items-center gap-2.5">
                                    <span className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-sm font-medium backdrop-blur-sm">
                                        <BuildingIcon className="size-4" />
                                        {companyName}
                                    </span>
                                    <span className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-sm font-medium backdrop-blur-sm">
                                        <BriefcaseIcon className="size-4" />
                                        {preview
                                            ? 'Application page preview'
                                            : 'Published opportunity'}
                                    </span>
                                </div>

                                <p className="mt-7 text-sm font-semibold tracking-[0.2em] text-cyan-100 uppercase">
                                    Your next chapter
                                </p>
                                <h1 className="mt-3 max-w-4xl text-4xl leading-tight font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                                    {job.name}
                                </h1>
                                <p className="mt-5 max-w-2xl text-base leading-7 text-blue-50 sm:text-lg">
                                    Bring your craft, curiosity, and perspective
                                    to a team building meaningful products
                                    together.
                                </p>

                                <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <button
                                        type="button"
                                        onClick={openApplicationForm}
                                        className="group inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-white px-6 py-3 text-sm font-semibold text-blue-700 shadow-lg shadow-blue-950/15 transition hover:-translate-y-0.5 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                                    >
                                        {showApplicationForm
                                            ? 'View application form'
                                            : 'Apply for this role'}
                                        <ArrowIcon className="size-4 transition group-hover:translate-x-0.5" />
                                    </button>
                                    <span className="inline-flex items-center justify-center gap-2 text-sm text-blue-100 sm:justify-start">
                                        <CalendarIcon className="size-4" />
                                        {closingDate
                                            ? `Open until ${closingDate}`
                                            : 'No closing date'}
                                    </span>
                                </div>
                            </div>

                            <div className="relative mx-auto hidden h-72 w-full max-w-sm lg:block">
                                <div className="absolute inset-8 rounded-full border border-white/15 bg-white/5 backdrop-blur-sm" />
                                <div className="absolute top-2 right-5 flex size-20 rotate-6 items-center justify-center rounded-3xl border border-white/25 bg-white/15 shadow-2xl backdrop-blur-md">
                                    <SparklesIcon className="size-10" />
                                </div>
                                <div className="absolute bottom-3 left-0 w-72 -rotate-3 rounded-3xl border border-white/25 bg-white/15 p-5 shadow-2xl backdrop-blur-md">
                                    <div className="flex items-center gap-2">
                                        <span className="size-2.5 rounded-full bg-rose-300" />
                                        <span className="size-2.5 rounded-full bg-amber-300" />
                                        <span className="size-2.5 rounded-full bg-emerald-300" />
                                    </div>
                                    <div className="mt-6 flex flex-col gap-3">
                                        <span className="h-2 w-3/4 rounded-full bg-white/80" />
                                        <span className="h-2 w-full rounded-full bg-cyan-100/45" />
                                        <span className="h-2 w-5/6 rounded-full bg-cyan-100/45" />
                                        <span className="mt-2 h-10 w-28 rounded-xl bg-white/20" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div className="mt-7 grid items-start gap-7 lg:grid-cols-[minmax(0,1fr)_22rem]">
                        <div className="flex min-w-0 flex-col gap-7">
                            <section className="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8 lg:p-10 dark:border-white/10 dark:bg-slate-900">
                                <div className="mb-8 flex items-center gap-4 border-b border-slate-100 pb-6 dark:border-white/10">
                                    <span className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-400/10 dark:text-blue-300">
                                        <BriefcaseIcon className="size-6" />
                                    </span>
                                    <div>
                                        <p className="text-xs font-semibold tracking-widest text-blue-600 uppercase dark:text-blue-300">
                                            The opportunity
                                        </p>
                                        <h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">
                                            About this role
                                        </h2>
                                    </div>
                                </div>

                                <JobDescription description={job.description} />
                            </section>

                            {showApplicationForm && (
                                <ApplicationForm
                                    job={job}
                                    phoneCountries={phoneCountries}
                                    sectionRef={applicationFormRef}
                                />
                            )}
                        </div>

                        <aside className="lg:sticky lg:top-6">
                            <div className="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xl shadow-slate-900/5 dark:border-white/10 dark:bg-slate-900 dark:shadow-black/20">
                                <div className="bg-linear-to-br from-slate-950 to-slate-800 p-6 text-white dark:from-blue-700 dark:to-cyan-600">
                                    <span className="flex size-11 items-center justify-center rounded-2xl bg-white/10 text-cyan-200 ring-1 ring-white/15">
                                        <DocumentIcon className="size-6" />
                                    </span>
                                    <h2 className="mt-5 text-xl font-semibold tracking-tight">
                                        Ready to apply?
                                    </h2>
                                    <p className="mt-2 text-sm leading-6 text-slate-300 dark:text-blue-50">
                                        A focused application designed around
                                        this role.
                                    </p>
                                </div>

                                <div className="flex flex-col gap-5 p-6">
                                    <div className="flex items-start gap-3">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-400/10 dark:text-violet-300">
                                            <SparklesIcon className="size-5" />
                                        </span>
                                        <div>
                                            <p className="text-sm font-semibold text-slate-900 dark:text-white">
                                                {questions.length}{' '}
                                                {questions.length === 1
                                                    ? 'question'
                                                    : 'questions'}
                                            </p>
                                            <p className="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                                Tailored to help us understand
                                                your experience.
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex items-start gap-3">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600 dark:bg-cyan-400/10 dark:text-cyan-300">
                                            <DocumentIcon className="size-5" />
                                        </span>
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold text-slate-900 dark:text-white">
                                                Resume formats
                                            </p>
                                            <div className="mt-2 flex flex-wrap gap-1.5">
                                                {cvTypes.map((fileType) => (
                                                    <span
                                                        key={fileType.id}
                                                        className="rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-bold tracking-wide text-slate-600 uppercase dark:bg-white/10 dark:text-slate-300"
                                                    >
                                                        {fileType.extension}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-start gap-3">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-400/10 dark:text-amber-300">
                                            <CalendarIcon className="size-5" />
                                        </span>
                                        <div>
                                            <p className="text-sm font-semibold text-slate-900 dark:text-white">
                                                {closingDate
                                                    ? `Closes ${closingDate}`
                                                    : 'Open-ended campaign'}
                                            </p>
                                            <p className="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                                {openingDate
                                                    ? `Applications opened ${openingDate}.`
                                                    : 'Applications are open now.'}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="h-px bg-slate-100 dark:bg-white/10" />

                                    <button
                                        type="button"
                                        onClick={openApplicationForm}
                                        className="group inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition hover:-translate-y-0.5 hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                                    >
                                        {showApplicationForm
                                            ? 'View application form'
                                            : 'Apply now'}
                                        <ArrowIcon className="size-4 transition group-hover:translate-x-0.5" />
                                    </button>
                                    <p className="text-center text-xs leading-5 text-slate-400">
                                        Your information will only be used for
                                        this recruitment process.
                                    </p>
                                </div>
                            </div>
                        </aside>
                    </div>
                </main>

                <footer className="border-t border-slate-200/80 bg-white/70 dark:border-white/10 dark:bg-slate-950/70">
                    <div className="mx-auto flex w-full max-w-7xl flex-col items-center justify-between gap-3 px-5 py-7 text-center sm:flex-row sm:px-8 sm:text-left lg:px-10">
                        <p className="text-sm font-medium text-slate-700 dark:text-slate-300">
                            {companyName}
                        </p>
                        <p className="text-xs text-slate-400">
                            Candidate experience powered by RecruiterLabs
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
