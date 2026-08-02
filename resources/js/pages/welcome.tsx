import { Head } from '@inertiajs/react';

export default function Welcome() {
    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-6 py-12 dark:bg-gray-950">
                <div className="flex flex-col items-center gap-2 text-center">
                    <h1 className="text-3xl font-semibold text-gray-900 dark:text-white">
                        Recruiter Labs
                    </h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Manage your recruitment workflow in one place.
                    </p>
                </div>
            </div>
        </>
    );
}
