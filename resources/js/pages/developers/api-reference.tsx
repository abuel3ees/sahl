import { Head } from '@inertiajs/react';
import { InlineMarkdown } from '@/components/docs/inline-markdown';
import { SidebarNav } from '@/components/docs/sidebar-nav';
import { ArrowIcon } from '@/components/site/arrow-icon';
import { DocsLayout } from '@/layouts/docs-layout';
import { fieldsOf, groupOperations, modelSchemas, type Field, type OpenApiDocument, type Operation } from '@/lib/openapi';

type Props = {
    spec: OpenApiDocument;
    apiBaseUrl: string;
    interactiveDocsUrl: string | null;
};

/** Realistic values for the curl examples, matching the seeded demo data. */
const EXAMPLE_VALUES: Record<string, unknown> = {
    login: '28763404821',
    password: 'password',
    device_name: 'curl',
    service: 'good-conduct-certificate',
    purpose: 'employment',
    certificate_language: 'ar',
    note: 'Record is clear.',
    reason: 'The uploaded passport copy is unreadable.',
};

const STATUS_TEXT: Record<string, string> = { '200': 'OK', '201': 'Created', '204': 'No content' };

const ERRORS: [string, string][] = [
    ['401', 'No token, or the token has expired.'],
    ['403', 'Signed in, but your role cannot do this — e.g. a citizen calling the back office.'],
    ['404', 'Not found, or not yours. Other people’s records are never confirmed to exist.'],
    ['409', 'The application was already approved or rejected.'],
    ['422', 'Validation failed. Details per field in `errors`.'],
    ['429', 'Too many attempts. Sign-in: 5 per minute; verification: 30 per minute.'],
];

export default function ApiReference({ spec, apiBaseUrl, interactiveDocsUrl }: Props) {
    const groups = groupOperations(spec);
    const models = modelSchemas(spec);

    return (
        <>
            <Head title="API reference" />
            <DocsLayout
                currentPath="/developers"
                kicker={{ en: 'Developers', ar: 'المطوّرون' }}
                title="API reference"
                intro={
                    <div className="flex flex-col gap-6">
                        <div className="max-w-[760px] text-lg text-neutral-800 [&_p]:m-0 [&_p+p]:mt-3">
                            <InlineMarkdown text={spec.info.description ?? ''} />
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <span className="bg-accent-100 px-2.5 py-1 font-mono text-xs font-semibold text-accent-800">v{spec.info.version}</span>
                            <span className="border border-divider px-2.5 py-1 font-mono text-xs">{apiBaseUrl}</span>
                            <span className="bg-neutral-200 px-2.5 py-1 text-xs font-semibold text-neutral-800">OpenAPI 3.1</span>
                        </div>
                        <div className="flex flex-wrap gap-3">
                            <a
                                href="/developers/openapi.json"
                                download="sahl-openapi.json"
                                className="inline-flex items-center gap-6 bg-accent px-5 py-3.5 font-display text-base font-extrabold text-paper no-underline hover:bg-accent-600"
                            >
                                Download OpenAPI (JSON)
                                <ArrowIcon size={18} />
                            </a>
                            {interactiveDocsUrl && (
                                <a
                                    href={interactiveDocsUrl}
                                    className="inline-flex items-center gap-2 border border-divider px-5 py-3.5 font-display text-base font-extrabold text-ink no-underline hover:bg-ink/7"
                                >
                                    Try it in the interactive console
                                </a>
                            )}
                        </div>
                    </div>
                }
                sidebar={
                    <SidebarNav
                        sections={[
                            {
                                heading: 'Overview',
                                items: [
                                    { id: 'signing-requests', label: 'Signing requests' },
                                    { id: 'errors', label: 'Errors' },
                                ],
                            },
                            ...groups.map((group) => ({
                                heading: group.name,
                                items: group.operations.map((operation) => ({ id: operation.id, label: operation.summary, badge: operation.method })),
                            })),
                            { heading: 'Models', items: models.map(([name]) => ({ id: `model-${name}`, label: name })) },
                        ]}
                    />
                }
            >
                <div className="flex max-w-[1080px] flex-col">
                    <OverviewSections apiBaseUrl={apiBaseUrl} />

                    {groups.map((group) => (
                        <section key={group.name} className="mt-20">
                            <h2 className="m-0 border-t-2 border-divider pt-6 text-4xl tracking-tight">{group.name}</h2>
                            {group.description && <p className="mt-2 mb-0 text-lg text-neutral-700">{group.description}</p>}
                            {group.operations.map((operation) => (
                                <EndpointCard key={operation.id} operation={operation} apiBaseUrl={apiBaseUrl} />
                            ))}
                        </section>
                    ))}

                    <section className="mt-20">
                        <h2 className="m-0 border-t-2 border-divider pt-6 text-4xl tracking-tight">Models</h2>
                        <p className="mt-2 mb-0 text-lg text-neutral-700">The JSON shapes returned inside <Code>data</Code>.</p>
                        {models.map(([name, schema]) => (
                            <div key={name} id={`model-${name}`} className="mt-10 scroll-mt-6">
                                <h3 className="m-0 mb-3 font-mono text-xl">{name}</h3>
                                <FieldTable fields={fieldsOf(spec, schema)} showRequired={false} />
                            </div>
                        ))}
                    </section>
                </div>
            </DocsLayout>
        </>
    );
}

function OverviewSections({ apiBaseUrl }: { apiBaseUrl: string }) {
    return (
        <>
            <section id="signing-requests" className="scroll-mt-6">
                <h2 className="m-0 border-t-2 border-divider pt-6 text-4xl tracking-tight">Signing requests</h2>
                <p className="mt-4 text-base">
                    Exchange a national ID (citizens) or work email (employees) and password for a token, then send it on every request as{' '}
                    <Code>Authorization: Bearer &lt;token&gt;</Code>. Tokens expire after 8 hours. Endpoints marked <PublicTag /> need no token.
                </p>
                <CodeBlock>{`curl -X POST ${apiBaseUrl}/v1/auth/tokens \\
  -H "Accept: application/json" -H "Content-Type: application/json" \\
  -d '{"login":"28763404821","password":"password","device_name":"curl"}'`}</CodeBlock>
                <p className="mt-4 mb-0 text-[15px] text-neutral-700">
                    This password sign-in is the local stand-in. On Azure, sign-in moves to Microsoft Entra ID.
                </p>
            </section>

            <section id="errors" className="mt-20 scroll-mt-6">
                <h2 className="m-0 border-t-2 border-divider pt-6 text-4xl tracking-tight">Errors</h2>
                <p className="mt-4 text-base">
                    Errors are JSON: <Code>{'{ "message": "…", "errors": { … } }'}</Code>
                </p>
                <table className="w-full border-collapse text-[15px]">
                    <tbody>
                        {ERRORS.map(([status, meaning]) => (
                            <tr key={status}>
                                <td className="w-20 border-b border-divider py-2.5 align-top">
                                    <StatusTag status={status} />
                                </td>
                                <td className="border-b border-divider py-2.5">
                                    <InlineMarkdown text={meaning} className="m-0" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </>
    );
}

function EndpointCard({ operation, apiBaseUrl }: { operation: Operation; apiBaseUrl: string }) {
    const pathParams = operation.parameters.filter((parameter) => parameter.in === 'path');
    const queryParams = operation.parameters.filter((parameter) => parameter.in === 'query');

    return (
        <article id={operation.id} className="mt-10 scroll-mt-6 border-2 border-divider bg-paper">
            <header className="flex flex-wrap items-center gap-3 border-b-2 border-divider bg-surface px-5 py-4">
                <MethodTag method={operation.method} />
                <code className="font-mono text-[15px] font-semibold break-all">/api{operation.path}</code>
                <span className="ms-auto">{operation.isPublic ? <PublicTag /> : <TokenTag />}</span>
            </header>

            <div className="flex flex-col gap-6 px-5 py-6">
                <div>
                    <h3 className="m-0 text-2xl tracking-tight">{operation.summary}</h3>
                    {operation.description && <InlineMarkdown text={operation.description} className="mt-2 mb-0 text-neutral-800" />}
                </div>

                {pathParams.length + queryParams.length > 0 && (
                    <Block title="Parameters">
                        <FieldTable
                            fields={[...pathParams, ...queryParams].map((parameter) => ({
                                name: parameter.name,
                                type: `${parameter.in}`,
                                required: parameter.required ?? false,
                                description: parameter.description,
                            }))}
                            typeLabel="In"
                        />
                    </Block>
                )}

                {operation.requestFields.length > 0 && (
                    <Block title="Request body">
                        <FieldTable fields={operation.requestFields} />
                    </Block>
                )}

                <Block title="Responses">
                    <ul className="m-0 flex list-none flex-col p-0">
                        {operation.responses.map((response) => (
                            <li key={response.status} className="flex flex-wrap items-baseline gap-3 border-b border-divider py-2.5 last:border-0">
                                <StatusTag status={response.status} />
                                <span className="text-[15px]">
                                    {response.body?.schemaRef ? (
                                        <>
                                            <Code>data</Code>:{' '}
                                            <a href={`#model-${response.body.schemaRef}`} className="font-mono text-[14px] font-semibold text-accent-700">
                                                {response.body.schemaRef}
                                                {response.body.isList && '[]'}
                                            </a>
                                            {response.body.isPaginated && <span className="text-neutral-700"> · paginated (20 per page)</span>}
                                        </>
                                    ) : (
                                        <InlineMarkdown text={response.description || STATUS_TEXT[response.status] || ''} className="m-0 inline" />
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Block>

                <Block title="Example">
                    <CodeBlock>{curlExample(operation, apiBaseUrl)}</CodeBlock>
                </Block>
            </div>
        </article>
    );
}

function curlExample(operation: Operation, apiBaseUrl: string): string {
    const path = operation.path.replace('{application}', '$APPLICATION_ID').replace('{service}', 'good-conduct-certificate').replace('{code}', '$CODE');
    const lines = [`curl -X ${operation.method} "${apiBaseUrl}${path}"`, '  -H "Accept: application/json"'];

    if (!operation.isPublic) {
        lines.push('  -H "Authorization: Bearer $TOKEN"');
    }

    const body = Object.fromEntries(
        operation.requestFields.filter((field) => field.required || field.name in EXAMPLE_VALUES).map((field) => [field.name, EXAMPLE_VALUES[field.name] ?? '…']),
    );

    if (Object.keys(body).length > 0) {
        lines.push('  -H "Content-Type: application/json"', `  -d '${JSON.stringify(body)}'`);
    }

    return lines.join(' \\\n');
}

/* ─────────────────────────── small pieces ─────────────────────────── */

function Block({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-2">
            <h4 className="m-0 text-xs font-semibold tracking-[.1em] text-neutral-700 uppercase">{title}</h4>
            {children}
        </div>
    );
}

function FieldTable({ fields, showRequired = true, typeLabel = 'Type' }: { fields: Field[]; showRequired?: boolean; typeLabel?: string }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full border-collapse text-[14px]">
                <thead>
                    <tr>
                        {['Field', typeLabel, ...(showRequired ? [''] : []), 'Description'].map((heading, index) => (
                            <th
                                key={index}
                                className="border-b-2 border-divider px-2 py-2 text-start text-[11px] font-semibold tracking-[.08em] whitespace-nowrap text-neutral-700 uppercase"
                            >
                                {heading}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {fields.map((field) => (
                        <tr key={field.name} className="hover:bg-ink/4">
                            <td className="border-b border-divider px-2 py-2 align-top font-mono font-semibold whitespace-nowrap">{field.name}</td>
                            <td className="border-b border-divider px-2 py-2 align-top font-mono text-[13px] text-accent-800">
                                {field.schemaRef ? (
                                    <a href={`#model-${field.schemaRef}`} className="text-accent-700">
                                        {field.type}
                                    </a>
                                ) : (
                                    field.type
                                )}
                            </td>
                            {showRequired && (
                                <td className="border-b border-divider px-2 py-2 align-top text-xs font-semibold whitespace-nowrap text-accent-700">
                                    {field.required ? 'required' : ''}
                                </td>
                            )}
                            <td className="border-b border-divider px-2 py-2 align-top text-neutral-800">
                                {field.description && <InlineMarkdown text={field.description} className="m-0" />}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function MethodTag({ method }: { method: string }) {
    const tone: Record<string, string> = {
        GET: 'border border-ink text-ink',
        POST: 'bg-accent text-paper',
        DELETE: 'bg-ink text-paper',
    };

    return <span className={`w-16 flex-none py-1 text-center font-mono text-xs font-bold ${tone[method] ?? 'bg-neutral-200'}`}>{method}</span>;
}

function StatusTag({ status }: { status: string }) {
    const success = status.startsWith('2');

    return (
        <span className={`inline-block w-12 py-0.5 text-center font-mono text-xs font-bold ${success ? 'bg-ink text-paper' : 'bg-accent-100 text-accent-800'}`}>
            {status}
        </span>
    );
}

function PublicTag() {
    return <span className="bg-neutral-200 px-2.5 py-1 text-xs font-semibold whitespace-nowrap text-neutral-800">Public</span>;
}

function TokenTag() {
    return <span className="bg-accent-100 px-2.5 py-1 text-xs font-semibold whitespace-nowrap text-accent-800">Bearer token</span>;
}

function Code({ children }: { children: React.ReactNode }) {
    return <code className="bg-surface px-1.5 py-0.5 font-mono text-[0.88em] text-accent-800">{children}</code>;
}

function CodeBlock({ children }: { children: string }) {
    return <pre className="m-0 overflow-x-auto bg-ink p-5 font-mono text-[13px] leading-relaxed text-paper">{children}</pre>;
}
