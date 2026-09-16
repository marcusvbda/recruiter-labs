import { Head } from '@inertiajs/react';
import {
    JobApplication,
    translate,
} from '@/components/job-application/job-application';
import type { JobApplicationProps } from '@/components/job-application/job-application';

export default function Apply(props: JobApplicationProps) {
    const companyName = props.job.company?.name ?? 'Recruiter Labs';
    const title =
        props.meta?.title ??
        translate(props.translations.meta.title, {
            job: props.job.name,
            company: companyName,
        });
    const description =
        props.meta?.description ??
        translate(props.translations.meta.description, {
            job: props.job.name,
            company: companyName,
        });

    return (
        <>
            <Head title={title}>
                <meta name="description" content={description} />
                {props.meta && (
                    <>
                        <link rel="canonical" href={props.meta.canonicalUrl} />
                        <meta
                            property="og:title"
                            content={props.meta.openGraph.title}
                        />
                        <meta
                            property="og:description"
                            content={props.meta.openGraph.description}
                        />
                        <meta
                            property="og:url"
                            content={props.meta.openGraph.url}
                        />
                        <meta property="og:type" content="website" />
                        {props.meta.openGraph.imageUrl && (
                            <meta
                                property="og:image"
                                content={props.meta.openGraph.imageUrl}
                            />
                        )}
                        <meta name="robots" content={props.meta.robots} />
                    </>
                )}
            </Head>

            <JobApplication {...props} />
        </>
    );
}
