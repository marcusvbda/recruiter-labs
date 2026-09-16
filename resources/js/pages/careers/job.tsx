import { Head } from '@inertiajs/react';
import { JobApplication } from '@/components/job-application/job-application';
import type { JobApplicationProps } from '@/components/job-application/job-application';

export default function CareersJob(props: JobApplicationProps) {
    if (!props.meta) {
        return <JobApplication {...props} />;
    }

    return (
        <>
            <Head title={props.meta.title}>
                <meta name="description" content={props.meta.description} />
                <link rel="canonical" href={props.meta.canonicalUrl} />
                <meta
                    property="og:title"
                    content={props.meta.openGraph.title}
                />
                <meta
                    property="og:description"
                    content={props.meta.openGraph.description}
                />
                <meta property="og:url" content={props.meta.openGraph.url} />
                <meta property="og:type" content="website" />
                {props.meta.openGraph.imageUrl && (
                    <meta
                        property="og:image"
                        content={props.meta.openGraph.imageUrl}
                    />
                )}
                <meta name="robots" content={props.meta.robots} />
            </Head>

            <JobApplication {...props} />
        </>
    );
}
