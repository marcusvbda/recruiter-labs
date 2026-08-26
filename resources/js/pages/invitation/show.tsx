import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type InvitationState =
    | 'invalid'
    | 'expired'
    | 'revoked'
    | 'accepted'
    | 'already_member'
    | 'guest'
    | 'email_mismatch'
    | 'email_unverified'
    | 'acceptable';

interface InvitationUrls {
    accept: string | null;
    login: string | null;
    register: string | null;
    verify: string | null;
    workspace: string | null;
}

interface InvitationTranslations {
    meta: {
        title: string;
        description: string;
    };
    details: {
        workspace: string;
        invited_by: string;
        invited_email: string;
        expires_at: string;
        signed_in_as: string;
        unknown_inviter: string;
    };
    states: Record<InvitationState, { title: string; description: string }>;
    actions: {
        accept: string;
        login: string;
        register: string;
        verify: string;
        workspace: string;
    };
    flash: {
        accepted: string;
    };
    register: {
        email_locked: string;
    };
}

interface InvitationPageProps {
    state: InvitationState;
    workspace: string | null;
    inviter: string | null;
    invitedEmail: string | null;
    expiresAt: string | null;
    currentEmail: string | null;
    urls: InvitationUrls;
    translations: InvitationTranslations;
}

const terminalStates: InvitationState[] = [
    'invalid',
    'expired',
    'revoked',
    'accepted',
];

/**
 * Substitutes the `:workspace` and `:email` placeholders the backend leaves
 * untouched in state copy. `:workspace` always comes from the `workspace`
 * prop and `:email` always comes from `currentEmail` — never from a
 * withheld field such as `invitedEmail`.
 */
function localizeInvitationCopy(
    template: string,
    workspace: string | null,
    currentEmail: string | null,
): string {
    let text = template;

    if (workspace) {
        text = text.replaceAll(':workspace', workspace);
    }

    if (currentEmail) {
        text = text.replaceAll(':email', currentEmail);
    }

    return text;
}

function formatExpiresAt(value: string | null): string | null {
    if (!value) {
        return null;
    }

    const locale =
        typeof document !== 'undefined' ? document.documentElement.lang : 'en';

    return new Intl.DateTimeFormat(locale || 'en', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
    }).format(new Date(value));
}

const DetailRow = ({ label, value }: { label: string; value: string }) => {
    return (
        <div className="flex items-start justify-between gap-4 py-3">
            <dt className="text-sm text-slate-500">{label}</dt>
            <dd className="text-right text-sm font-medium text-slate-900">
                {value}
            </dd>
        </div>
    );
};

const InvitationDetails = ({
    workspace,
    inviter,
    invitedEmail,
    expiresAt,
    currentEmail,
    translations,
}: {
    workspace: string | null;
    inviter: string | null;
    invitedEmail: string | null;
    expiresAt: string | null;
    currentEmail: string | null;
    translations: InvitationTranslations['details'];
}) => {
    const formattedExpiresAt = formatExpiresAt(expiresAt);
    const hasDetails = workspace || invitedEmail || formattedExpiresAt;

    if (!hasDetails && !currentEmail) {
        return null;
    }

    return (
        <dl className="mt-6 divide-y divide-slate-100 border-t border-slate-100">
            {workspace && (
                <DetailRow label={translations.workspace} value={workspace} />
            )}
            {workspace && (
                <DetailRow
                    label={translations.invited_by}
                    value={inviter ?? translations.unknown_inviter}
                />
            )}
            {invitedEmail && (
                <DetailRow
                    label={translations.invited_email}
                    value={invitedEmail}
                />
            )}
            {formattedExpiresAt && (
                <DetailRow
                    label={translations.expires_at}
                    value={formattedExpiresAt}
                />
            )}
            {currentEmail && (
                <DetailRow
                    label={translations.signed_in_as}
                    value={currentEmail}
                />
            )}
        </dl>
    );
};

const InvitationActions = ({
    state,
    urls,
    translations,
}: {
    state: InvitationState;
    urls: InvitationUrls;
    translations: InvitationTranslations['actions'];
}) => {
    const [isAccepting, setIsAccepting] = useState(false);

    if (state === 'acceptable' && urls.accept) {
        const acceptUrl = urls.accept;

        return (
            <button
                type="button"
                disabled={isAccepting}
                onClick={() => {
                    if (isAccepting) {
                        return;
                    }

                    setIsAccepting(true);

                    router.post(
                        acceptUrl,
                        {},
                        {
                            onFinish: () => setIsAccepting(false),
                        },
                    );
                }}
                className="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition hover:-translate-y-0.5 hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0"
            >
                {translations.accept}
            </button>
        );
    }

    if (state === 'guest' && (urls.login || urls.register)) {
        return (
            <div className="flex flex-col gap-3 sm:flex-row">
                {urls.login && (
                    <a
                        href={urls.login}
                        className="inline-flex min-h-12 flex-1 items-center justify-center rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition hover:-translate-y-0.5 hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                    >
                        {translations.login}
                    </a>
                )}
                {urls.register && (
                    <a
                        href={urls.register}
                        className="inline-flex min-h-12 flex-1 items-center justify-center rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                    >
                        {translations.register}
                    </a>
                )}
            </div>
        );
    }

    if (state === 'email_unverified' && urls.verify) {
        return (
            <a
                href={urls.verify}
                className="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition hover:-translate-y-0.5 hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
            >
                {translations.verify}
            </a>
        );
    }

    if (state === 'already_member' && urls.workspace) {
        return (
            <a
                href={urls.workspace}
                className="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition hover:-translate-y-0.5 hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
            >
                {translations.workspace}
            </a>
        );
    }

    return null;
};

export default function InvitationShow({
    state,
    workspace,
    inviter,
    invitedEmail,
    expiresAt,
    currentEmail,
    urls,
    translations,
}: InvitationPageProps) {
    const stateCopy = translations.states[state];
    const title = localizeInvitationCopy(
        stateCopy.title,
        workspace,
        currentEmail,
    );
    const description = localizeInvitationCopy(
        stateCopy.description,
        workspace,
        currentEmail,
    );
    const isTerminal = terminalStates.includes(state);

    return (
        <>
            <Head title={translations.meta.title}>
                <meta
                    name="description"
                    content={translations.meta.description}
                />
            </Head>

            <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-50 px-5 py-12 text-slate-950">
                <div className="pointer-events-none absolute inset-x-0 top-0 h-[38rem] bg-[radial-gradient(circle_at_top_left,rgba(37,99,235,0.12),transparent_42%),radial-gradient(circle_at_top_right,rgba(6,182,212,0.12),transparent_38%)]" />

                <div className="relative w-full max-w-md">
                    <div className="mb-8 flex justify-center">
                        <img
                            src="/assets/image/logo.png"
                            alt="RecruiterLabs"
                            className="h-8 w-auto"
                        />
                    </div>

                    <section className="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-xl shadow-blue-950/5 sm:p-8">
                        {workspace && (
                            <span
                                className={`inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold ${
                                    isTerminal
                                        ? 'bg-slate-100 text-slate-600'
                                        : 'bg-blue-50 text-blue-700'
                                }`}
                            >
                                {workspace}
                            </span>
                        )}

                        <h1 className="mt-4 text-2xl font-semibold tracking-tight text-slate-950">
                            {title}
                        </h1>
                        <p className="mt-3 text-sm leading-6 text-slate-500">
                            {description}
                        </p>

                        <InvitationDetails
                            workspace={workspace}
                            inviter={inviter}
                            invitedEmail={invitedEmail}
                            expiresAt={expiresAt}
                            currentEmail={currentEmail}
                            translations={translations.details}
                        />

                        <div className="mt-8">
                            <InvitationActions
                                state={state}
                                urls={urls}
                                translations={translations.actions}
                            />
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}
