/**
 * Just enough OpenAPI 3.1 reading to render Sahl's API reference: resolving $refs, merging
 * allOf, describing types, and grouping operations by tag.
 */

export type Schema = {
    $ref?: string;
    type?: string | string[];
    title?: string;
    description?: string;
    format?: string;
    enum?: (string | number)[];
    const?: string | number;
    properties?: Record<string, Schema>;
    required?: string[];
    items?: Schema;
    allOf?: Schema[];
    anyOf?: Schema[];
    oneOf?: Schema[];
    minLength?: number;
    maxLength?: number;
};

type Parameter = { name: string; in: 'path' | 'query' | 'header'; required?: boolean; description?: string; schema?: Schema };
type MediaContent = { content?: Record<string, { schema?: Schema }> };
type ResponseObject = MediaContent & { $ref?: string; description?: string };

type RawOperation = {
    operationId: string;
    summary?: string;
    description?: string;
    tags?: string[];
    security?: unknown[];
    parameters?: Parameter[];
    requestBody?: MediaContent & { required?: boolean };
    responses: Record<string, ResponseObject>;
};

export type OpenApiDocument = {
    info: { title: string; version: string; description?: string };
    tags?: { name: string; description?: string }[];
    paths: Record<string, Record<string, RawOperation>>;
    components: {
        schemas: Record<string, Schema>;
        responses?: Record<string, ResponseObject>;
    };
};

export type Field = {
    name: string;
    type: string;
    required: boolean;
    description?: string;
    /** Name of a component schema this field points at, for linking. */
    schemaRef?: string;
};

export type Operation = {
    id: string;
    method: string;
    path: string;
    summary: string;
    description?: string;
    isPublic: boolean;
    parameters: Parameter[];
    requestFields: Field[];
    responses: { status: string; description: string; body?: { schemaRef?: string; isList: boolean; isPaginated: boolean } }[];
};

export type OperationGroup = { name: string; description?: string; operations: Operation[] };

const HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

export function refName(ref: string): string {
    return ref.split('/').pop() ?? ref;
}

function lookup<T>(doc: OpenApiDocument, ref: string): T {
    const [, , section, name] = ref.split('/');

    return (doc.components as Record<string, Record<string, unknown>>)[section][name] as T;
}

/** Resolve a $ref (one level) and fold allOf parts into a single object schema. */
export function flatten(doc: OpenApiDocument, schema: Schema): Schema {
    if (schema.$ref) {
        return flatten(doc, lookup<Schema>(doc, schema.$ref));
    }

    if (!schema.allOf) {
        return schema;
    }

    return schema.allOf.reduce<Schema>(
        (merged, part) => {
            const flat = flatten(doc, part);

            return {
                ...merged,
                title: merged.title ?? flat.title,
                properties: { ...merged.properties, ...flat.properties },
                required: [...(merged.required ?? []), ...(flat.required ?? [])],
            };
        },
        { type: 'object' },
    );
}

/** The component schema a property points at, if any (through allOf/anyOf wrappers). */
function referencedSchema(schema: Schema): string | undefined {
    if (schema.$ref) {
        return refName(schema.$ref);
    }

    for (const part of [...(schema.allOf ?? []), ...(schema.anyOf ?? []), ...(schema.oneOf ?? [])]) {
        if (part.$ref) {
            return refName(part.$ref);
        }
    }

    return undefined;
}

/** A short, human-readable type, e.g. `"ar" | "en"`, `string | null`, `ServiceResource[]`. */
export function describeType(doc: OpenApiDocument, schema: Schema): string {
    if (schema.$ref) {
        const target = lookup<Schema>(doc, schema.$ref);

        return target.enum ? describeType(doc, target) : refName(schema.$ref);
    }

    if (schema.const !== undefined) {
        return JSON.stringify(schema.const);
    }

    if (schema.enum) {
        return schema.enum.map((value) => JSON.stringify(value)).join(' | ');
    }

    const union = schema.anyOf ?? schema.oneOf;
    if (union) {
        return union.map((part) => describeType(doc, part)).join(' | ');
    }

    if (schema.allOf) {
        const ref = referencedSchema(schema);

        return ref ?? 'object';
    }

    const types = Array.isArray(schema.type) ? schema.type : [schema.type ?? 'any'];

    return types
        .map((type) => {
            if (type === 'array') {
                return `${schema.items ? describeType(doc, schema.items) : 'any'}[]`;
            }

            return schema.format && type === 'string' ? `string (${schema.format})` : type;
        })
        .join(' | ');
}

export function fieldsOf(doc: OpenApiDocument, schema: Schema): Field[] {
    const flat = flatten(doc, schema);
    const required = new Set(flat.required ?? []);

    return Object.entries(flat.properties ?? {}).map(([name, property]) => ({
        name,
        type: describeType(doc, property),
        required: required.has(name),
        description: property.description ?? (property.$ref ? lookup<Schema>(doc, property.$ref).description : undefined),
        schemaRef: (() => {
            const ref = referencedSchema(property.type === 'array' && property.items ? property.items : property);

            return ref && !lookup<Schema>(doc, `#/components/schemas/${ref}`).enum ? ref : undefined;
        })(),
    }));
}

function describeBody(doc: OpenApiDocument, schema: Schema | undefined): Operation['responses'][number]['body'] {
    const data = schema?.properties?.data;

    if (!data) {
        return undefined;
    }

    const isList = data.type === 'array';

    return {
        schemaRef: referencedSchema(isList && data.items ? data.items : data),
        isList,
        isPaginated: Boolean(schema?.properties?.meta),
    };
}

export function groupOperations(doc: OpenApiDocument): OperationGroup[] {
    const groups = new Map<string, OperationGroup>((doc.tags ?? []).map((tag) => [tag.name, { ...tag, operations: [] }]));

    for (const [path, methods] of Object.entries(doc.paths)) {
        for (const method of HTTP_METHODS) {
            const raw = methods[method];
            if (!raw) {
                continue;
            }

            const tag = raw.tags?.[0] ?? 'Other';
            if (!groups.has(tag)) {
                groups.set(tag, { name: tag, operations: [] });
            }

            const requestSchema = raw.requestBody?.content?.['application/json']?.schema;

            groups.get(tag)!.operations.push({
                id: raw.operationId.replaceAll('.', '-'),
                method: method.toUpperCase(),
                path,
                summary: raw.summary ?? path,
                description: raw.description,
                isPublic: Array.isArray(raw.security) && raw.security.length === 0,
                parameters: raw.parameters ?? [],
                requestFields: requestSchema ? fieldsOf(doc, requestSchema) : [],
                responses: Object.entries(raw.responses)
                    .map(([status, response]) => {
                        const resolved = response.$ref ? lookup<ResponseObject>(doc, response.$ref) : response;

                        return {
                            status,
                            description: resolved.description ?? '',
                            body: describeBody(doc, resolved.content?.['application/json']?.schema),
                        };
                    })
                    .sort((a, b) => a.status.localeCompare(b.status)),
            });
        }
    }

    return [...groups.values()].filter((group) => group.operations.length > 0);
}

/** Component schemas worth documenting as models: objects, not enums or request bodies. */
export function modelSchemas(doc: OpenApiDocument): [string, Schema][] {
    return Object.entries(doc.components.schemas).filter(([name, schema]) => !schema.enum && name.endsWith('Resource'));
}
