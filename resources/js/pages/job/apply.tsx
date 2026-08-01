import { Head } from '@inertiajs/react';
import {
    JobApplication,
    translate,
} from '@/components/job-application/job-application';
import type { JobApplicationProps } from '@/components/job-application/job-application';

export default function Apply(props: JobApplicationProps) {
    const companyName = props.job.company?.name ?? 'Recruiter Labs';

    return (
        <>
            <Head
                title={translate(props.translations.meta.title, {
                    job: props.job.name,
                    company: companyName,
                })}
            >
                <meta
                    name="description"
                    content={translate(props.translations.meta.description, {
                        job: props.job.name,
                        company: companyName,
                    })}
                />
            </Head>

            <JobApplication {...props} />
        </>
    );
}
