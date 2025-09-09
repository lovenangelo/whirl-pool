import React, { useState } from 'react';
import * as z from 'zod';
import { Button } from '@/components/ui/button';
import { CloneIndexProps, CloneOption, Step } from '@/types/clone';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import clone from '@/routes/clone';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { toast } from 'sonner';
import { Head, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Clone',
        href: clone.index().url,
    },
];

// Helper functions for validation
function validateFilePaths(data: z.infer<typeof cloneSchema>, ctx: z.RefinementCtx) {
    if (!data.sourcePath || data.sourcePath.trim() === '') {
        ctx.addIssue({
            code: "custom",
            message: 'Source path is required for file operations',
            path: ['sourcePath'],
        });
    }

    if (!data.targetPath || data.targetPath.trim() === '') {
        ctx.addIssue({
            code: "custom",
            message: 'Target path is required for file operations',
            path: ['targetPath'],
        });
    }

    if (data.sourcePath && data.targetPath && data.sourcePath === data.targetPath) {
        ctx.addIssue({
            code: "custom",
            message: 'Source and target paths cannot be the same',
            path: ['targetPath'],
        });
    }
}

function validateDatabaseFields(data: z.infer<typeof cloneSchema>, ctx: z.RefinementCtx) {
    // Source database validation
    if (!data.sourceDbHost || data.sourceDbHost.trim() === '') {
        ctx.addIssue({
            code: "custom",
            message: 'Source database host is required for database operations',
            path: ['sourceDbHost'],
        });
    }

    if (!data.sourceDbName || data.sourceDbName.trim() === '') {
        ctx.addIssue({
            code: "custom",
            message: 'Source database name is required for database operations',
            path: ['sourceDbName'],
        });
    } else if (!/^\w+$/.test(data.sourceDbName)) {
        ctx.addIssue({
            code: "custom",
            message: 'Database name can only contain letters, numbers, and underscores',
            path: ['sourceDbName'],
        });
    }

    // Target database validation
    if (!data.targetDbHost || data.targetDbHost.trim() === '') {
        ctx.addIssue({
            code: "custom",
            message: 'Target database host is required for database operations',
            path: ['targetDbHost'],
        });
    }

    if (!data.targetDbName || data.targetDbName.trim() === '') {
        ctx.addIssue({
            code: "custom",
            message: 'Target database name is required for database operations',
            path: ['targetDbName'],
        });
    } else if (!/^\w+$/.test(data.targetDbName)) {
        ctx.addIssue({
            code: "custom",
            message: 'Database name can only contain letters, numbers, and underscores',
            path: ['targetDbName'],
        });
    }

    // Check for same source and target database
    if (data.sourceDbHost && data.targetDbHost &&
        data.sourceDbName && data.targetDbName &&
        data.sourceDbHost === data.targetDbHost &&
        data.sourceDbName === data.targetDbName) {
        ctx.addIssue({
            code: "custom",
            message: 'Source and target database cannot be the same',
            path: ['targetDbName'],
        });
    }
}

// Validation schema with conditional requirements
const cloneSchema = z.object({
    sourcePath: z.string().optional(),
    targetPath: z.string().optional(),
    sourceDbHost: z.string().optional(),
    sourceDbName: z.string().optional(),
    targetDbHost: z.string().optional(),
    targetDbName: z.string().optional(),
    cloneType: z.enum(['full', 'files', 'database']),
}).superRefine((data, ctx) => {
    const needsFiles = data.cloneType === 'full' || data.cloneType === 'files';
    const needsDatabase = data.cloneType === 'full' || data.cloneType === 'database';

    if (needsFiles) {
        validateFilePaths(data, ctx);
    }

    if (needsDatabase) {
        validateDatabaseFields(data, ctx);
    }
});

type CloneFormValues = z.infer<typeof cloneSchema>;

export default function CloneIndex({ auth }: Readonly<CloneIndexProps>) {
    console.log(auth);
    const [isCloning, setIsCloning] = useState<boolean>(false);
    const [steps, setSteps] = useState<Step[]>([]);

    const { data, setData, post, errors, processing } = useForm<CloneFormValues>({
        sourcePath: '',
        targetPath: '',
        sourceDbHost: '127.0.0.1',
        sourceDbName: '',
        targetDbHost: '127.0.0.1',
        targetDbName: '',
        cloneType: 'full',
    });

    const cloneType = data.cloneType;

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        try {
            const validationResult = cloneSchema.safeParse(data);
            if (!validationResult.success) {
                validationResult.error.issues.forEach(issue => {
                    toast.error(issue.message);
                });
                return;
            }

            setIsCloning(true);
            setSteps([]);

            post('/clone', {
                preserveState: true,
                preserveScroll: true,
                onSuccess: (page) => {
                    console.log(page);
                    setSteps(Array.isArray(page.props.steps) ? page.props.steps : []);
                    setIsCloning(false);
                    toast.success('Cloning completed successfully!');
                },
                onError: (errors) => {
                    toast.error('An error occurred: ' + Object.values(errors).join(', '));
                    setIsCloning(false);
                },
            });
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'An unknown error occurred';
            toast.error('An error occurred: ' + errorMessage);
            setIsCloning(false);
        }
    };

    const cloneOptions: CloneOption[] = [
        {
            value: 'full',
            label: 'Full Clone',
            desc: 'Complete WordPress site including files and database',
            icon: (
                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            )
        },
        {
            value: 'files',
            label: 'Files Only',
            desc: 'Copy WordPress files without database',
            icon: (
                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                </svg>
            )
        },
        {
            value: 'database',
            label: 'Database Only',
            desc: 'Clone database without files',
            icon: (
                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                </svg>
            )
        }
    ];

    const needsFiles = cloneType === 'full' || cloneType === 'files';
    const needsDatabase = cloneType === 'full' || cloneType === 'database';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Clone Sites" />
            <div className="py-8">
                <div className="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold text-gray-900 dark:text-white">Clone Sites</h1>
                        <p className="mt-2 text-gray-600 dark:text-white">Clone your WordPress site with files and database options</p>
                    </div>

                    <div className="w-full">
                        {/* Main Form */}
                        <div className="lg:col-span-2 w-full">
                            <div className="w-full bg-white dark:bg-[#161615] shadow-sm border dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] rounded-xl overflow-hidden">
                                <form onSubmit={onSubmit} className="p-8 space-y-8 sm:space-x-8 w-full flex">
                                    {/* Clone Type Selection */}
                                    <div>
                                        <h3 className="text-lg font-semibold text-gray-900 mb-4 dark:text-white">Clone Type</h3>
                                        <div className="grid grid-cols-1 gap-4 sm:w-sm">
                                            {cloneOptions.map((option) => (
                                                <Label
                                                    key={option.value}
                                                    className={`relative flex h-24 items-center p-4 border rounded-xl cursor-pointer hover:border-gray-200 transition-all duration-200 ${data.cloneType === option.value
                                                        ? 'ring-opacity-20 border-gray-200'
                                                        : ''
                                                        }`}
                                                >
                                                    <Input
                                                        type="radio"
                                                        value={option.value}
                                                        checked={data.cloneType === option.value}
                                                        onChange={() => setData('cloneType', option.value)}
                                                        className="sr-only"
                                                    />
                                                    <div className={`flex-shrink-0 mr-4 p-2 rounded-lg ${data.cloneType === option.value ? 'bg-transparent text-white-600' : 'bg-transparent text-gray-500'
                                                        }`}>
                                                        {option.icon}
                                                    </div>
                                                    <div className="flex-1">
                                                        <div className="flex items-center">
                                                            <span className={`font-medium dark:text-white}`}>
                                                                {option.label}
                                                            </span>
                                                            {data.cloneType === option.value && (
                                                                <svg className="ml-2 w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                                                </svg>
                                                            )}
                                                        </div>
                                                        <p className={`mt-1 text-sm font-normal transition-all ${data.cloneType === option.value ? 'text-white' : 'text-gray-500'
                                                            }`}>
                                                            {option.desc}
                                                        </p>
                                                    </div>
                                                </Label>
                                            ))}
                                        </div>
                                    </div>

                                    {/* Source Configuration */}
                                    <div className=" rounded-xl sm:w-md border-l-white">
                                        <div className="flex items-center mb-4">
                                            <h3 className="text-lg font-semibold text-white">Source Configuration</h3>
                                        </div>

                                        <div className="space-y-4">
                                            {needsFiles && (
                                                <div className='text-white'>
                                                    <Label className="block text-sm font-medium mb-2">
                                                        Source Path *
                                                    </Label>
                                                    <Input
                                                        type="text"
                                                        value={data.sourcePath}
                                                        onChange={(e) => setData('sourcePath', e.target.value)}
                                                        className={errors.sourcePath ? 'border-red-500' : ''}
                                                        placeholder="/path/to/wordpress"
                                                    />
                                                    {errors.sourcePath && (
                                                        <p className="mt-1 text-sm text-red-400">{errors.sourcePath}</p>
                                                    )}
                                                </div>
                                            )}

                                            {needsDatabase && (
                                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                    <div>
                                                        <Label>Database Host</Label>
                                                        <Input
                                                            type="text"
                                                            value={data.sourceDbHost}
                                                            onChange={(e) => setData('sourceDbHost', e.target.value)}
                                                            className='mt-2'
                                                            readOnly
                                                            placeholder="127.0.0.1"
                                                        />
                                                    </div>
                                                    <div>
                                                        <Label>Database Name *</Label>
                                                        <Input
                                                            type="text"
                                                            value={data.sourceDbName}
                                                            onChange={(e) => setData('sourceDbName', e.target.value)}
                                                            className={`mt-2 ${errors.sourceDbName ? 'border-red-500' : ''}`}
                                                            placeholder="database_name"
                                                        />
                                                        {errors.sourceDbName && (
                                                            <p className="mt-1 text-sm text-red-400">{errors.sourceDbName}</p>
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {/* Target Configuration */}
                                    <div className="bg-transparent w-md">
                                        <div className="flex items-center mb-4">
                                            <h3 className="text-lg font-semibold text-white">Target Configuration</h3>
                                        </div>

                                        <div className="space-y-4">
                                            {needsFiles && (
                                                <div>
                                                    <Label>
                                                        Target Path *
                                                    </Label>
                                                    <Input
                                                        type="text"
                                                        value={data.targetPath}
                                                        onChange={(e) => setData('targetPath', e.target.value)}
                                                        className={`mt-2 ${errors.targetPath ? 'border-red-500' : ''}`}
                                                        placeholder="/path/to/new-wordpress"
                                                    />
                                                    {errors.targetPath && (
                                                        <p className="mt-1 text-sm text-red-400">{errors.targetPath}</p>
                                                    )}
                                                </div>
                                            )}

                                            {needsDatabase && (
                                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                    <div>
                                                        <Label>Database Host</Label>
                                                        <Input
                                                            type="text"
                                                            value={data.targetDbHost}
                                                            onChange={(e) => setData('targetDbHost', e.target.value)}
                                                            readOnly
                                                            className="mt-2"
                                                            placeholder="127.0.0.1"
                                                        />
                                                    </div>
                                                    <div>
                                                        <Label>Database Name *</Label>
                                                        <Input
                                                            type="text"
                                                            value={data.targetDbName}
                                                            onChange={(e) => setData('targetDbName', e.target.value)}
                                                            className={`mt-2 ${errors.targetDbName ? 'border-red-500' : ''}`}
                                                            placeholder="new_database_name"
                                                        />
                                                        {errors.targetDbName && (
                                                            <p className="mt-1 text-sm text-red-400">{errors.targetDbName}</p>
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {/* Submit Button */}
                                    <div className='flex justify-end w-48'>
                                        <Button
                                            type="submit"
                                            disabled={isCloning || processing}
                                            className=""
                                        >
                                            {isCloning || processing ? (
                                                <>
                                                    <svg className="animate-spin -ml-1 mr-3 h-5 w-5" fill="none" viewBox="0 0 24 24">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Cloning...
                                                </>
                                            ) : (
                                                <div className='flex justify-items-center text-sm'>
                                                    <svg className="mr-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                    </svg>
                                                    Clone
                                                </div>
                                            )}
                                        </Button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {/* Sidebar */}
                        <div className="lg:col-span-1 space-y-6">
                            {/* Progress Steps */}
                            {steps.length > 0 && (
                                <div className="bg-white shadow-sm border border-gray-200 rounded-xl p-6">
                                    <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                        <svg className="mr-2 w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                        Progress
                                    </h3>
                                    <div className="space-y-3">
                                        {steps.map((step: Step) => (
                                            <div key={step.step} className="flex items-center p-3 bg-gray-50 rounded-lg border border-gray-100">
                                                <div className="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                                    <span className="text-sm font-semibold text-blue-700">{step.step}</span>
                                                </div>
                                                <div className="flex-1">
                                                    <p className="text-sm font-medium text-gray-800">{step.message}</p>
                                                </div>
                                                <div className="flex-shrink-0 ml-2">
                                                    <svg className="w-5 h-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                    </svg>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Help Section */}
                            <div className="sm:mt-8">
                                <h3 className="text-lg font-semibold text-white mb-3 flex items-center">
                                    <svg className="mr-2 w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Need Help?
                                </h3>
                                <div className="space-y-3 text-sm text-white">
                                    <div className="flex items-start">
                                        <div className="w-2 h-2 bg-blue-400 rounded-full mt-2 mr-3 flex-shrink-0"></div>
                                        <p><strong>Full Clone:</strong> Copies both files and database for a complete site migration</p>
                                    </div>
                                    <div className="flex items-start">
                                        <div className="w-2 h-2 bg-blue-400 rounded-full mt-2 mr-3 flex-shrink-0"></div>
                                        <p><strong>Files Only:</strong> Perfect for updating themes, plugins, or media files</p>
                                    </div>
                                    <div className="flex items-start">
                                        <div className="w-2 h-2 bg-blue-400 rounded-full mt-2 mr-3 flex-shrink-0"></div>
                                        <p><strong>Database Only:</strong> Transfer content, settings, and user data</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
