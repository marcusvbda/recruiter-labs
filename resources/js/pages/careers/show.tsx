import { Head, Link } from '@inertiajs/react';
import { translate } from '@/components/job-application/job-application';
import type { PublicCompany } from '@/components/job-application/job-application';

interface CareerJob {
    key: string;
    name: string;
    descriptionExcerpt: string | null;
    endsAt: string | null;
    url: string;
}

interface CareersMeta {
    title: string;
    description: string;
    canonicalUrl: string;
    openGraph: {
        title: string;
        description: string;
        url: string;
        imageUrl: string | null;
    };
    robots: string;
}

interface CareersShowProps {
    company: PublicCompany;
    locale: string;
    translations: CareersTranslations;
    jobs: CareerJob[];
    urls: {
        current: string;
        canonical: string;
    };
    meta: CareersMeta;
}

interface CareersTranslations {
    label: string;
    heading: string;
    logo_alt: string;
    open_roles: string;
    opportunity_singular: string;
    opportunity_plural: string;
    view_role: string;
    closes: string;
    empty: string;
}

const closingDate = (locale: string, endsAt: string | null) => {
    if (!endsAt) {
        return null;
    }

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'long',
    }).format(new Date(`${endsAt}T00:00:00`));
};

export default function CareersShow({
    company,
    locale,
    translations,
    jobs,
    meta,
}: CareersShowProps) {
    return (
        <>
            <Head title={meta.title}>
                <meta name="description" content={meta.description} />
                <link rel="canonical" href={meta.canonicalUrl} />
                <meta property="og:title" content={meta.openGraph.title} />
                <meta
                    property="og:description"
                    content={meta.openGraph.description}
                />
                <meta property="og:url" content={meta.openGraph.url} />
                <meta property="og:type" content="website" />
                {meta.openGraph.imageUrl && (
                    <meta
                        property="og:image"
                        content={meta.openGraph.imageUrl}
                    />
                )}
                <meta name="robots" content={meta.robots} />
            </Head>

            <main className="min-h-screen bg-slate-50 px-5 py-10 text-slate-950 sm:px-8 sm:py-16">
                <div className="mx-auto w-full max-w-5xl">
                    <header className="border-b border-slate-200 pb-10 sm:pb-12">
                        <div className="flex items-center gap-4">
                            {company.logoUrl ? (
                                <img
                                    src={company.logoUrl}
                                    alt={translate(translations.logo_alt, {
                                        company: company.name,
                                    })}
                                    className="h-14 w-auto max-w-48 rounded object-contain"
                                />
                            ) : (
                                <span className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-xl font-bold text-white">
                                    {company.name.slice(0, 1).toUpperCase()}
                                </span>
                            )}
                            <p className="text-sm font-semibold tracking-[0.18em] text-blue-700 uppercase">
                                {translations.label}
                            </p>
                        </div>
                        <h1 className="mt-6 text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                            {translate(translations.heading, {
                                company: company.name,
                            })}
                        </h1>
                        {company.description && (
                            <p className="mt-5 max-w-3xl text-base leading-7 whitespace-pre-line text-slate-600 sm:text-lg">
                                {company.description}
                            </p>
                        )}
                    </header>

                    <section
                        className="pt-10 sm:pt-12"
                        aria-labelledby="open-roles-title"
                    >
                        <div className="flex items-baseline justify-between gap-4">
                            <h2
                                id="open-roles-title"
                                className="text-2xl font-semibold tracking-tight"
                            >
                                {translations.open_roles}
                            </h2>
                            <p className="text-sm text-slate-500">
                                {translate(
                                    jobs.length === 1
                                        ? translations.opportunity_singular
                                        : translations.opportunity_plural,
                                    { count: jobs.length },
                                )}
                            </p>
                        </div>

                        {jobs.length === 0 ? (
                            <p className="mt-6 border-l-2 border-slate-300 pl-4 text-slate-600">
                                {translations.empty}
                            </p>
                        ) : (
                            <ul className="mt-6 grid gap-4 sm:grid-cols-2">
                                {jobs.map((job) => {
                                    const date = closingDate(
                                        locale,
                                        job.endsAt,
                                    );

                                    return (
                                        <li key={job.key}>
                                            <Link
                                                href={job.url}
                                                className="group flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-6 transition hover:border-blue-300 hover:shadow-lg hover:shadow-blue-950/5 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-blue-600"
                                            >
                                                <h3 className="text-lg font-semibold text-slate-950 group-hover:text-blue-700">
                                                    {job.name}
                                                </h3>
                                                {job.descriptionExcerpt && (
                                                    <p className="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">
                                                        {job.descriptionExcerpt}
                                                    </p>
                                                )}
                                                <div className="mt-6 flex items-center justify-between gap-3 text-sm">
                                                    <span className="font-semibold text-blue-700">
                                                        {translations.view_role}
                                                    </span>
                                                    {date && (
                                                        <span className="text-right text-slate-500">
                                                            {translate(
                                                                translations.closes,
                                                                { date },
                                                            )}
                                                        </span>
                                                    )}
                                                </div>
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </section>
                </div>
            </main>
        </>
    );
}
